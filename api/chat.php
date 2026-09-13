<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit('{"error":"Method not allowed"}');
}

boot_session();

// Rate limit : 20 messages par session
$_SESSION['chat_count'] = ($_SESSION['chat_count'] ?? 0) + 1;
if ($_SESSION['chat_count'] > 20) {
    http_response_code(429);
    exit(json_encode(['reply' => 'Limite atteinte. Appelez-nous directement au '.company_phone().'.']));
}

// Chatbot activé ?
if (!setting_bool('chatbot_enabled', false)) {
    http_response_code(503);
    exit(json_encode(['reply' => 'Service indisponible. Appelez le '.company_phone().'.']));
}

$apiKey = setting('claude_api_key', '');
if ($apiKey === '') {
    http_response_code(503);
    exit(json_encode(['reply' => 'Chatbot non configuré. Appelez le '.company_phone().'.']));
}

// Lecture et validation du body JSON
$body    = json_decode((string)file_get_contents('php://input'), true);
$message = trim((string)($body['message'] ?? ''));
$history = is_array($body['history'] ?? null) ? $body['history'] : [];

if ($message === '' || mb_strlen($message, 'UTF-8') > 600) {
    http_response_code(400); exit('{"error":"Message invalide"}');
}

// Prompt système
$systemPrompt = setting('chatbot_system_prompt', '') ?: implode("\n", [
    'Tu es l\'assistant virtuel de '.company_name().', entreprise multitechnique d\'urgence disponible '.company_hours().'.',
    'Services : électricité, plomberie, chauffage & pompe à chaleur, climatisation & CVC.',
    'Zones d\'intervention : '.company_regions().'.',
    'Téléphone : '.company_phone().' | Email : '.company_email().'.',
    '',
    'Règles strictes :',
    '- Réponds en français, sois bref (2-3 phrases max).',
    '- Ne donne jamais de tarif précis — oriente vers un devis gratuit.',
    '- Pour toute urgence, demande d\'appeler le '.company_phone().' immédiatement.',
    '- Si la question est hors sujet (pas nos services), redirige poliment vers notre contact.',
    '- Ne mentionne pas d\'autres entreprises ni de comparatifs.',
]);

// Construction des messages (6 derniers échanges max)
$messages = [];
foreach (array_slice($history, -6) as $h) {
    $r = (string)($h['role'] ?? '');
    $c = trim((string)($h['content'] ?? ''));
    if (in_array($r, ['user','assistant'], true) && $c !== '') {
        $messages[] = ['role' => $r, 'content' => $c];
    }
}
$messages[] = ['role' => 'user', 'content' => $message];

$payload = json_encode([
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 350,
    'system'     => $systemPrompt,
    'messages'   => $messages,
], JSON_UNESCAPED_UNICODE);

// Appel API Anthropic via cURL
if (function_exists('curl_init')) {
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: '.$apiKey,
            'anthropic-version: 2023-06-01',
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'timeout' => 15,
        'header'  => implode("\r\n", [
            'Content-Type: application/json',
            'x-api-key: '.$apiKey,
            'anthropic-version: 2023-06-01',
        ]),
        'content' => $payload,
        'ignore_errors' => true,
    ]]);
    $response = @file_get_contents('https://api.anthropic.com/v1/messages', false, $ctx);
    $httpCode = 200;
}

if ($response === false || ($httpCode !== 0 && $httpCode !== 200)) {
    http_response_code(503);
    exit(json_encode(['reply' => 'Erreur technique. Appelez-nous au '.company_phone().'.']));
}

$data  = json_decode((string)$response, true);
$reply = trim((string)(($data['content'][0]['text'] ?? '')));

if ($reply === '') {
    http_response_code(503);
    exit(json_encode(['reply' => 'Je n\'ai pas pu répondre. Appelez-nous au '.company_phone().'.']));
}

echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);