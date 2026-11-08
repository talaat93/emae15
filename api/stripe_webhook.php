<?php
declare(strict_types=1);
/**
 * Webhook Stripe (à déclarer dans Stripe → Développeurs → Webhooks, avec l'URL
 * https://www.emaee.fr/api/stripe_webhook.php et l'événement
 * « checkout.session.completed » ; copier le secret « whsec_… » dans l'admin,
 * écran Paiements en ligne).
 * - la signature est obligatoire (sinon 400) ;
 * - le paiement n'est enregistré que si le montant correspond au lien créé.
 */
require_once __DIR__.'/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit('{"error":"method"}'); }

$raw = (string)file_get_contents('php://input');
$sig = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
if (strlen($raw) > 1_000_000 || !stripe_signature_valid($raw, $sig, integration_secret('stripe_webhook_secret'))) {
    integration_log('stripe', 'webhook refusé : signature invalide ou absente');
    http_response_code(400);
    exit('{"error":"signature"}');
}

$event = json_decode($raw, true) ?: [];
if (($event['type'] ?? '') === 'checkout.session.completed' || ($event['type'] ?? '') === 'checkout.session.async_payment_succeeded') {
    payment_apply_session((array)($event['data']['object'] ?? []));
}
echo '{"ok":true}';
