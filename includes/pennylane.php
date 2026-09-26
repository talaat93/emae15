<?php
declare(strict_types=1);
/**
 * Client de l'API Pennylane v2 « external » (https://app.pennylane.com/api/external/v2).
 * Authentification par jeton Bearer (jeton d'API de l'entreprise, généré dans
 * Pennylane > Paramètres > Connectivité > Développeurs).
 * Chemins et champs vérifiés sur la spécification OpenAPI officielle (Accounting 2.0).
 *
 * Sans jeton, ou si le mode simulation est coché, toutes les fonctions renvoient des
 * données fictives marquées « SIMULATION » : le circuit reste testable de bout en bout.
 */

const PENNYLANE_BASE = 'https://app.pennylane.com/api/external/v2';

function pennylane_token(): string { return integration_secret('pennylane_api_key'); }

/** Simulation : pas de jeton, ou mode simulation forcé dans les réglages. */
function pennylane_simulated(): bool
{
    return pennylane_token() === '' || integration_setting('pennylane_simulation', '0') === '1';
}

/**
 * Requête Pennylane. Réessaie après une attente sur 429 (limite de requêtes) et sur
 * les erreurs serveur passagères. Retour : ['ok' => bool, 'status' => int, 'data' => ?array, 'error' => ?string].
 */
function pennylane_request(string $method, string $path, array $query = [], ?array $body = null, int $timeout = 25): array
{
    $url = PENNYLANE_BASE.$path.($query ? '?'.http_build_query($query) : '');
    $headers = ['Authorization: Bearer '.pennylane_token(), 'Accept: application/json'];
    if ($body !== null) $headers[] = 'Content-Type: application/json';

    for ($attempt = 1; $attempt <= 4; $attempt++) {
        $res = integration_http($method, $url, $headers, $body, $timeout);
        $retryable = $res['status'] === 429 || in_array($res['status'], [502, 503, 504], true) || ($res['error'] !== null && $attempt < 2);
        if (!$retryable || $attempt === 4) break;
        // Attente demandée par l'API si elle est fournie, sinon attente progressive (1 s, 2 s, 4 s).
        $wait = (int)($res['headers']['retry-after'] ?? $res['headers']['ratelimit-reset'] ?? 0);
        $wait = $wait > 0 ? min($wait, 30) : (1 << ($attempt - 1));
        integration_log('pennylane', strtoupper($method).' '.$path.' : nouvel essai', ['http' => $res['status'], 'attente_s' => $wait]);
        sleep($wait);
    }

    $ok = $res['error'] === null && $res['status'] >= 200 && $res['status'] < 300;
    $ctx = ['http' => $res['status'], 'ms' => $res['ms']];
    if (!$ok) {
        $detail = $res['error'] ?? (string)($res['json']['message'] ?? $res['json']['error'] ?? '');
        integration_log('pennylane', strtoupper($method).' '.$path.' : échec', $ctx + ['detail' => mb_substr((string)$detail, 0, 300)]);
        $human = match (true) {
            $res['status'] === 401 => 'Jeton Pennylane refusé : vérifiez-le dans les réglages.',
            $res['status'] === 403 => 'Le jeton Pennylane n\'a pas les droits nécessaires pour cette action.',
            $res['status'] === 404 => 'Élément introuvable dans Pennylane.',
            $res['status'] === 409 => 'Pennylane n\'est pas encore prêt (document en cours de génération) : réessayez dans quelques minutes.',
            $res['status'] === 422 || $res['status'] === 400 => 'Pennylane a refusé les données : '.mb_substr((string)$detail, 0, 200),
            $res['status'] === 429 => 'Trop de requêtes vers Pennylane : réessayez dans une minute.',
            $res['error'] !== null => 'Connexion à Pennylane impossible (réseau ou délai dépassé).',
            default => 'Pennylane est momentanément indisponible (HTTP '.$res['status'].').',
        };
        return ['ok' => false, 'status' => $res['status'], 'data' => $res['json'], 'error' => $human];
    }
    integration_log('pennylane', strtoupper($method).' '.$path.' : ok', $ctx);
    return ['ok' => true, 'status' => $res['status'], 'data' => $res['json'] ?? [], 'error' => null];
}

function pennylane_test_connection(): array
{
    if (pennylane_token() === '') return ['ok' => false, 'message' => 'Aucun jeton : Pennylane fonctionne en mode SIMULATION.'];
    $r = pennylane_request('GET', '/me');
    if (!$r['ok']) return ['ok' => false, 'message' => $r['error']];
    $co = (string)($r['data']['company']['name'] ?? '');
    $mode = integration_setting('pennylane_simulation', '0') === '1' ? ' (le mode simulation reste activé : décochez-le pour travailler en réel)' : '';
    return ['ok' => true, 'message' => 'Connexion réussie'.($co !== '' ? ' — société « '.$co.' »' : '').$mode.'.'];
}
