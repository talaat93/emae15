<?php
declare(strict_types=1);
/**
 * Appels à l'API Claude (Messages API) en cURL.
 *
 * - Modèle réglable (réglage claude_model, par défaut claude-opus-5).
 * - Réponse JSON imposée par output_config.format (json_schema) puis revalidée en PHP.
 * - Fallbacks côté serveur activés : en cas de refus de sécurité, l'API rejoue la
 *   requête sur le modèle de repli recommandé (beta server-side-fallback-2026-07-01).
 * - Sans clé, ou si l'appel échoue, la fonction de simulation fournie par l'appelant
 *   prend le relais : le dispatcher n'est jamais bloqué.
 * Claude assiste : aucune décision (prix, envoi) ne repose sur sa seule réponse.
 */

const CLAUDE_API_URL      = 'https://api.anthropic.com/v1/messages';
const CLAUDE_DEFAULT_MODEL = 'claude-opus-5';

function claude_models(): array
{
    return [
        'claude-opus-5'   => 'Claude Opus 5 (recommandé)',
        'claude-sonnet-5' => 'Claude Sonnet 5 (plus économique)',
    ];
}

function claude_model(): string
{
    $m = integration_setting('claude_model', CLAUDE_DEFAULT_MODEL);
    return array_key_exists($m, claude_models()) ? $m : CLAUDE_DEFAULT_MODEL;
}

function claude_api_key(): string { return integration_secret('claude_api_key'); }
function claude_is_configured(): bool { return claude_api_key() !== ''; }

/**
 * Appel structuré.
 *
 * $req :
 *   purpose    string  libellé court pour le journal (« qualification », « relecture »…)
 *   system     string  consignes
 *   messages   array   messages au format de l'API (role/content)
 *   schema     array   schéma JSON de la réponse attendue
 *   effort     string  low | medium | high (défaut medium)
 *   max_tokens int     défaut 8000
 *   timeout    int     secondes, défaut 60
 *   simulate   callable|null  retourne un tableau conforme au schéma (mode simulation / repli)
 *
 * Retour : ['ok' => bool, 'data' => ?array, 'simulated' => bool, 'error' => ?string,
 *           'refused' => bool, 'model' => ?string]
 */
function claude_structured(array $req): array
{
    $purpose  = (string)($req['purpose'] ?? 'appel');
    $schema   = (array)($req['schema'] ?? []);
    $simulate = $req['simulate'] ?? null;
    $fallback = static function (string $why) use ($simulate, $schema, $purpose): array {
        if (is_callable($simulate)) {
            $data = $simulate();
            $errs = $schema ? json_schema_errors($data, $schema) : [];
            if (!$errs) return ['ok' => true, 'data' => $data, 'simulated' => true, 'error' => $why, 'refused' => false, 'model' => null];
            integration_log('claude', $purpose.' : simulation non conforme', ['erreurs' => array_slice($errs, 0, 3)]);
        }
        return ['ok' => false, 'data' => null, 'simulated' => false, 'error' => $why, 'refused' => false, 'model' => null];
    };

    $key = claude_api_key();
    if ($key === '') return $fallback('SIMULATION : aucune clé API Claude n\'est configurée.');

    $effort = in_array($req['effort'] ?? '', ['low', 'medium', 'high'], true) ? $req['effort'] : 'medium';
    $model  = claude_model();
    $body = [
        'model'         => $model,
        'max_tokens'    => (int)($req['max_tokens'] ?? 8000),
        'system'        => (string)($req['system'] ?? ''),
        'messages'      => (array)($req['messages'] ?? []),
        'output_config' => ['effort' => $effort] + ($schema ? ['format' => ['type' => 'json_schema', 'schema' => $schema]] : []),
    ];
    $headers = ['Content-Type: application/json', 'x-api-key: '.$key, 'anthropic-version: 2023-06-01'];
    if ($model === 'claude-opus-5') {
        // Refus de sécurité : l'API rejoue la requête sur le modèle de repli recommandé.
        $body['fallbacks'] = 'default';
        $headers[] = 'anthropic-beta: server-side-fallback-2026-07-01';
    }
    $res = integration_http('POST', CLAUDE_API_URL, $headers, $body, (int)($req['timeout'] ?? 60));

    $json = $res['json'];
    $logCtx = [
        'modele' => $model, 'http' => $res['status'], 'ms' => $res['ms'],
        'arret' => $json['stop_reason'] ?? null,
        'entree' => $json['usage']['input_tokens'] ?? null, 'sortie' => $json['usage']['output_tokens'] ?? null,
    ];

    if ($res['error'] !== null || $res['status'] < 200 || $res['status'] >= 300 || !$json) {
        $msg = $res['error'] ?? ((string)($json['error']['message'] ?? ('HTTP '.$res['status'])));
        integration_log('claude', $purpose.' : échec', $logCtx + ['erreur' => mb_substr($msg, 0, 200)]);
        $human = match (true) {
            $res['status'] === 401 => 'Clé API Claude refusée : vérifiez-la dans les réglages.',
            $res['status'] === 429 => 'Claude est très sollicité : réessayez dans une minute.',
            $res['status'] >= 500 || $res['status'] === 529 => 'Claude est momentanément indisponible.',
            $res['error'] !== null => 'Connexion à Claude impossible (délai dépassé ou réseau).',
            default => 'Réponse inattendue de Claude.',
        };
        return $fallback($human);
    }

    if (($json['stop_reason'] ?? '') === 'refusal') {
        integration_log('claude', $purpose.' : refus', $logCtx + ['categorie' => $json['stop_details']['category'] ?? null]);
        $r = $fallback('Claude a décliné cette demande.');
        $r['refused'] = true;
        return $r;
    }
    if (($json['stop_reason'] ?? '') === 'max_tokens') {
        integration_log('claude', $purpose.' : réponse tronquée', $logCtx);
        return $fallback('La réponse de Claude était trop longue et a été coupée.');
    }

    $text = '';
    foreach ((array)($json['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') $text .= (string)$block['text'];
    }
    $data = json_decode(trim($text), true);
    $errs = is_array($data) && $schema ? json_schema_errors($data, $schema) : (is_array($data) ? [] : ['réponse non JSON']);
    if ($errs) {
        integration_log('claude', $purpose.' : JSON non conforme', $logCtx + ['erreurs' => array_slice($errs, 0, 3)]);
        return $fallback('La réponse de Claude n\'était pas exploitable.');
    }
    integration_log('claude', $purpose.' : ok', $logCtx);
    return ['ok' => true, 'data' => $data, 'simulated' => false, 'error' => null, 'refused' => false, 'model' => (string)($json['model'] ?? $model)];
}

/** Test de connexion pour la page des réglages. */
function claude_test_connection(): array
{
    if (!claude_is_configured()) return ['ok' => false, 'message' => 'Aucune clé : Claude fonctionne en mode SIMULATION.'];
    $r = claude_structured([
        'purpose'    => 'test',
        'system'     => 'Réponds uniquement par le JSON demandé.',
        'messages'   => [['role' => 'user', 'content' => 'Test de connexion : renvoie {"ok": true}.']],
        'schema'     => ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok'], 'additionalProperties' => false],
        'effort'     => 'low',
        'max_tokens' => 200,
        'timeout'    => 30,
    ]);
    return $r['ok'] && !$r['simulated']
        ? ['ok' => true, 'message' => 'Connexion réussie ('.($r['model'] ?? claude_model()).').']
        : ['ok' => false, 'message' => $r['error'] ?? 'Échec du test.'];
}

/** Bloc image base64 pour les messages (photos de rapport). Limité en taille. */
function claude_image_block(string $absPath, int $maxBytes = 3_500_000): ?array
{
    if (!is_file($absPath) || filesize($absPath) > $maxBytes) return null;
    $mime = mime_content_type($absPath) ?: '';
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) return null;
    return ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode((string)file_get_contents($absPath))]];
}
