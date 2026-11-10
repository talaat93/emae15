<?php
declare(strict_types=1);
/**
 * Gabarits des pages ajoutées en novembre 2026. Appelés par index.php ; chacun
 * affiche une page complète (en-tête, contenu, pied de page).
 * Textes : includes/content_pages.php. Logique : growth.php, client_space.php,
 * payments.php, contracts.php.
 */

/** Bandeau d'aperçu : la page est désactivée, seul un admin connecté la voit. */
function px_draft_banner(bool $enabled, string $catalogPage): void
{
    if ($enabled) return;
    echo '<div class="tarif-draft"><div class="wrap">👁️ Aperçu réservé à l\'administrateur : cette page est désactivée et invisible pour vos visiteurs. '
       . '<a href="'.e(url_for('admin/page_content.php?p='.$catalogPage)).'">L\'activer</a></div></div>';
}

function px_hero(string $eyebrow, string $h1, string $lead, bool $actions = true): void
{
    echo '<section class="page-hero"><div class="wrap"><div class="ph-eyebrow">'.e($eyebrow).'</div>'
       . '<h1 class="ph-h1">'.e($h1).'</h1><p class="ph-lead">'.e($lead).'</p>';
    if ($actions) {
        echo '<div class="btn-row px-actions"><a class="btn btn-p btn-lg" href="'.e(company_phone_link()).'">📞 '.e(company_phone()).'</a>';
        if (($wa = whatsapp_url()) !== '') echo '<a class="btn btn-outline btn-lg" href="'.e($wa).'" target="_blank" rel="noopener">💬 WhatsApp</a>';
        echo '</div>'.render_google_badge();
    }
    echo '</div></section>';
}

function px_list(array $items, string $class = ''): string
{
    if (!$items) return '';
    $h = '<ul class="px-list '.$class.'">';
    foreach ($items as $i) $h .= '<li>'.e($i).'</li>';
    return $h.'</ul>';
}

/* ── 7. Une page panne ── */
function render_page_panne(string $slug): void
{
    $p = panne_page($slug);
    if ($p === null) return;
    $tpl = service_template($p['trade']);
    render_head(['title' => $p['meta_title'], 'description' => $p['meta_desc'], 'canonical' => route_url($slug)]);
    render_header(route_url($slug));
    px_draft_banner(pannes_enabled(), 'page_pannes');
    px_hero('// '.($tpl['label'] ?? 'Dépannage'), $p['icon'].' '.$p['h1'], $p['lead']);
    ?>
<section class="sec sec-navy"><div class="wrap px-narrow">
  <?php if (trim($p['alert']) !== ''): ?><div class="px-alert" role="alert"><strong>⚠️ Danger</strong><p><?= e($p['alert']) ?></p><a class="btn btn-p" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a></div><?php endif; ?>
  <div class="px-cols">
    <div class="px-box">
      <h2 class="px-h2">✅ Ce que vous pouvez vérifier</h2>
      <?= str_replace('<ul', '<ol', str_replace('</ul>', '</ol>', px_list($p['checks'], 'px-steps'))) ?>
    </div>
    <div class="px-box px-box--warn">
      <h2 class="px-h2">⛔ À ne pas faire</h2>
      <?= px_list($p['never']) ?>
    </div>
  </div>
  <div class="px-box px-box--call">
    <h2 class="px-h2">📞 Quand nous appeler</h2>
    <p><?= e($p['call']) ?></p>
    <div class="btn-row px-actions"><a class="btn btn-p btn-lg" href="<?= e(company_phone_link()) ?>">📞 Appeler maintenant</a></div>
  </div>
</div></section>
<section class="sec sec-card"><div class="wrap" style="max-width:700px;">
  <div class="qs-card">
    <div class="hero-card-tag">Rappel gratuit</div>
    <h2 class="px-form-title">Être rappelé par un technicien</h2>
    <?php render_quote_form(service_cards_v14(), 'panne_'.$slug, 'rappel', ['trade' => $p['trade'], 'message' => $p['h1'].' : ']); ?>
  </div>
</div></section>
<?php px_other_pannes($slug, $p['trade']); ?>
<?php
    render_footer();
}

function px_other_pannes(string $current, string $trade): void
{
    $all = array_filter(array_map('panne_page', array_keys(panne_defaults())), static fn(?array $x): bool => $x !== null && $x['slug'] !== $current);
    if (!$all) return;
    echo '<section class="sec sec-navy"><div class="wrap"><div class="svc-label">Autres pannes fréquentes</div><div class="px-chips">';
    foreach ($all as $x) echo '<a class="px-chip" href="'.e(route_url($x['slug'])).'">'.e($x['icon'].' '.$x['h1']).'</a>';
    echo '</div><p style="margin-top:1.25rem;"><a class="btn btn-ghost-p" href="'.e(route_url($trade)).'">Tout savoir sur notre service '.e(mb_strtolower((string)(service_template($trade)['label'] ?? ''), 'UTF-8')).' →</a></p></div></section>';
}

function render_page_pannes_index(): void
{
    render_head(['title' => 'Pannes fréquentes : que faire en attendant le technicien ? | '.company_name(),
                 'description' => 'Chaudière en panne, fuite d\'eau, disjoncteur qui saute : les bons gestes en attendant notre arrivée, et quand nous appeler.',
                 'canonical' => route_url('pannes')]);
    render_header(route_url('pannes'));
    px_draft_banner(pannes_enabled(), 'page_pannes');
    px_hero('Dépannage', 'Pannes fréquentes : que faire ?', 'Les bons gestes en attendant notre arrivée, et les signes qui demandent un professionnel.');
    echo '<section class="sec sec-navy"><div class="wrap"><div class="px-grid">';
    foreach (array_keys(panne_defaults()) as $slug) {
        $p = panne_page($slug);
        if ($p === null) continue;
        echo '<a class="px-card" href="'.e(route_url($slug)).'"><span class="px-card-ico">'.e($p['icon']).'</span><span class="px-card-h">'.e($p['h1']).'</span><span class="px-card-p">'.e($p['lead']).'</span><span class="sp-link">Lire les conseils →</span></a>';
    }
    echo '</div></div></section>';
    render_footer();
}

/* ── 19. Aides et primes ── */
function render_page_aides(): void
{
    $a = aides_defaults();
    render_head(['title' => cp_text('aides_meta_title', $a['aides_meta_title']), 'description' => cp_text('aides_meta_desc', $a['aides_meta_desc']), 'canonical' => route_url('aides')]);
    render_header(route_url('aides'));
    px_draft_banner(aides_enabled(), 'page_aides');
    px_hero('Financement', cp_text('aides_h1', $a['aides_h1']), cp_text('aides_lead', $a['aides_lead']));
    $rge = aides_rge();
    ?>
<section class="sec sec-navy"><div class="wrap px-narrow">
  <div class="px-note <?= $rge ? 'px-note--ok' : '' ?>"><?= $rge ? '✅ ' : 'ℹ️ ' ?><?= e($rge ? cp_text('aides_rge_yes', $a['aides_rge_yes']) : cp_text('aides_rge_no', $a['aides_rge_no'])) ?></div>
  <div class="px-grid">
    <?php foreach (cp_lines('aides_cards', $a['aides_cards']) as $line): [$t, $d] = array_pad(explode('|', $line, 2), 2, ''); ?>
    <div class="px-card px-card--static"><span class="px-card-h"><?= e(trim($t)) ?></span><span class="px-card-p"><?= e(trim($d)) ?></span></div>
    <?php endforeach; ?>
  </div>
  <div class="px-box" style="margin-top:2rem;">
    <h2 class="px-h2">Comment ça se passe</h2>
    <?= str_replace(['<ul', '</ul>'], ['<ol', '</ol>'], px_list(cp_lines('aides_steps', $a['aides_steps']), 'px-steps')) ?>
  </div>
  <p class="tarif-note"><?= e(cp_text('aides_note', $a['aides_note'])) ?> <a href="https://france-renov.gouv.fr" target="_blank" rel="noopener" style="color:var(--p);">france-renov.gouv.fr</a></p>
  <div class="faq-cta">
    <h2>Votre projet peut-il être aidé ?</h2>
    <p>Décrivez-nous vos travaux : nous vous répondons avec un devis détaillé et les aides possibles.</p>
    <div class="acts"><a class="btn btn-p btn-lg" href="<?= e(company_phone_link()) ?>">📞 <?= e(company_phone()) ?></a><a class="btn btn-outline btn-lg" href="<?= e(route_url('quote')) ?>">Demander un devis gratuit</a></div>
  </div>
</div></section>
<?php
    render_footer();
}

/* ── 12. Estimation de prix ── */
function render_page_estimation(): void
{
    $d = estimation_defaults();
    $trades = estimation_trades();
    $m = (string)($_GET['m'] ?? '');
    $trade = $trades[$m] ?? null;
    $pi = isset($_GET['p']) ? (int)$_GET['p'] : -1;
    $row = $trade['rows'][$pi] ?? null;
    $urgent = ($_GET['u'] ?? '') === '1';
    $step = $trade === null ? 1 : ($row === null ? 2 : (isset($_GET['u']) ? 4 : 3));
    $base = route_url('estimation');
    $link = static fn(array $q): string => $base.(str_contains($base, '?') ? '&' : '?').http_build_query($q);

    render_head(['title' => cp_text('estim_meta_title', $d['estim_meta_title']), 'description' => cp_text('estim_meta_desc', $d['estim_meta_desc']), 'canonical' => $base]);
    render_header($base);
    px_draft_banner(estimation_enabled(), 'page_estimation');
    px_hero('Estimation gratuite', cp_text('estim_h1', $d['estim_h1']), cp_text('estim_lead', $d['estim_lead']), false);
    ?>
<section class="sec sec-navy"><div class="wrap px-narrow">
  <ol class="px-progress" aria-label="Étapes">
    <?php foreach (['Le métier', 'La prestation', 'L\'urgence', 'Votre prix'] as $i => $lab): ?><li class="<?= $i + 1 < $step ? 'done' : ($i + 1 === $step ? 'cur' : '') ?>"><?= e($lab) ?></li><?php endforeach; ?>
  </ol>
  <?php if ($step === 1): ?>
    <h2 class="px-h2">De quel métier avez-vous besoin ?</h2>
    <div class="px-grid px-grid--choice">
      <?php foreach ($trades as $k => $t): ?><a class="px-choice" href="<?= e($link(['m' => $k])) ?>"><span><?= e($t['icon']) ?></span><?= e($t['label']) ?></a><?php endforeach; ?>
    </div>
  <?php elseif ($step === 2): ?>
    <h2 class="px-h2"><?= e($trade['icon'].' '.$trade['label']) ?> : quelle prestation ?</h2>
    <div class="px-grid px-grid--choice">
      <?php foreach ($trade['rows'] as $i => $r): ?><a class="px-choice" href="<?= e($link(['m' => $m, 'p' => $i])) ?>"><?= e($r['label']) ?></a><?php endforeach; ?>
    </div>
    <p><a class="px-back" href="<?= e($base) ?>">← Changer de métier</a></p>
  <?php elseif ($step === 3): ?>
    <h2 class="px-h2">Est-ce urgent ?</h2>
    <div class="px-grid px-grid--choice">
      <a class="px-choice" href="<?= e($link(['m' => $m, 'p' => $pi, 'u' => 0])) ?>"><span>🗓️</span>Non, à planifier</a>
      <a class="px-choice" href="<?= e($link(['m' => $m, 'p' => $pi, 'u' => 1])) ?>"><span>🚨</span>Oui, au plus vite</a>
    </div>
    <p><a class="px-back" href="<?= e($link(['m' => $m])) ?>">← Changer de prestation</a></p>
  <?php else: ?>
    <div class="px-result">
      <div class="px-result-what"><?= e($trade['label'].' — '.$row['label']) ?></div>
      <div class="px-result-price"><?= e($row['price'] !== '' ? $row['price'] : setting('tarifs_quote_label', 'Sur devis')) ?></div>
      <?php if ($row['detail'] !== ''): ?><div class="px-result-detail"><?= e($row['detail']) ?></div><?php endif; ?>
      <p class="px-result-note"><?= e(cp_text('estim_result_note', $d['estim_result_note'])) ?></p>
      <?php if ($urgent): ?><p class="px-result-note">⏱️ <?= e(cp_text('estim_urgent_note', $d['estim_urgent_note'])) ?></p><?php endif; ?>
      <div class="btn-row px-actions"><a class="btn btn-p btn-lg" href="<?= e(company_phone_link()) ?>">📞 Appeler pour confirmer</a></div>
    </div>
    <div class="qs-card" style="margin-top:2rem;">
      <div class="hero-card-tag">Rappel gratuit</div>
      <h2 class="px-form-title">Recevoir un devis précis</h2>
      <?php render_quote_form(service_cards_v14(), 'estimation', $urgent ? 'rappel' : 'devis', ['trade' => $m, 'message' => $row['label'].($urgent ? ' (urgent)' : '').' : ']); ?>
    </div>
    <p><a class="px-back" href="<?= e($base) ?>">← Nouvelle estimation</a></p>
  <?php endif; ?>
</div></section>
<?php
    render_footer();
}

/* ── 11. Rendez-vous en ligne ── */
function render_page_rdv(): void
{
    $d = booking_page_defaults();
    render_head(['title' => cp_text('rdv_meta_title', $d['rdv_meta_title']), 'description' => cp_text('rdv_meta_desc', $d['rdv_meta_desc']), 'canonical' => route_url('rendez-vous')]);
    render_header(route_url('rendez-vous'));
    if (!booking_enabled()) echo '<div class="tarif-draft"><div class="wrap">👁️ Aperçu réservé à l\'administrateur : la prise de rendez-vous est désactivée. <a href="'.e(url_for('admin/growth.php#rdv')).'">L\'activer</a></div></div>';
    px_hero('Rendez-vous', cp_text('rdv_h1', $d['rdv_h1']), cp_text('rdv_lead', $d['rdv_lead']), false);
    $days  = booking_days();
    $slots = booking_slots();
    $taken = booking_taken($days);
    $cap   = booking_capacity();
    ?>
<section class="sec sec-navy"><div class="wrap px-narrow">
  <div class="px-note">🚨 <?= e(cp_text('rdv_urgent', $d['rdv_urgent'])) ?> <a href="<?= e(company_phone_link()) ?>" style="color:var(--p);font-weight:700;"><?= e(company_phone()) ?></a></div>
  <form class="qs-card" action="<?= e(route_url('rendez-vous')) ?>" method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="form_type" value="quote">
    <input type="hidden" name="request_type" value="rdv">
    <input type="hidden" name="source" value="rendez-vous">
    <input type="text" name="website" value="" class="hp" tabindex="-1" autocomplete="off">
    <h2 class="px-form-title">1. Choisissez un créneau</h2>
    <div class="px-slots">
      <?php foreach ($days as $day): ?>
      <div class="px-day"><div class="px-day-h"><?= e(ucfirst(booking_day_label($day))) ?></div>
        <?php foreach ($slots as $sk => $sl): $full = ($taken[$day.'|'.$sk] ?? 0) >= $cap; ?>
        <label class="px-slot<?= $full ? ' is-full' : '' ?>"><input type="radio" name="booking" value="<?= e($day.'|'.$sk) ?>" required<?= $full ? ' disabled' : '' ?>><span><?= e($sl) ?><?= $full ? ' — complet' : '' ?></span></label>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <h2 class="px-form-title" style="margin-top:1.5rem;">2. Votre demande</h2>
    <div class="f-field"><span class="f-label">Motif *</span><select class="f-input" name="booking_reason" required>
      <?php foreach (cp_lines('rdv_reasons', $d['rdv_reasons']) as $r): ?><option><?= e($r) ?></option><?php endforeach; ?>
    </select></div>
    <div class="f-row">
      <div class="f-field"><span class="f-label">Nom complet *</span><input class="f-input" type="text" name="full_name" required></div>
      <div class="f-field"><span class="f-label">Téléphone *</span><input class="f-input" type="tel" name="phone" required></div>
    </div>
    <div class="f-row">
      <div class="f-field"><span class="f-label">Email</span><input class="f-input" type="email" name="email"></div>
      <div class="f-field"><span class="f-label">Code postal *</span><input class="f-input" type="text" name="postal_code" pattern="[0-9]{5}" maxlength="5" required></div>
    </div>
    <div class="f-field"><span class="f-label">Adresse *</span><input class="f-input" type="text" name="address" required></div>
    <div class="f-field"><span class="f-label">Précisions (facultatif)</span><textarea class="f-input f-textarea" name="message" placeholder="Marque de l'appareil, accès, étage…"></textarea></div>
    <button class="btn btn-p btn-lg btn-block" type="submit">Demander ce rendez-vous</button>
    <p class="f-meta">Nous vous rappelons pour confirmer l'heure exacte.</p>
  </form>
</div></section>
<?php
    render_footer();
}

/* ── 15. Contrats d'entretien (page publique) ── */
function render_page_entretien(): void
{
    $d = entretien_defaults();
    render_head(['title' => cp_text('entretien_meta_title', $d['entretien_meta_title']), 'description' => cp_text('entretien_meta_desc', $d['entretien_meta_desc']), 'canonical' => route_url('entretien')]);
    render_header(route_url('entretien'));
    px_draft_banner(entretien_enabled(), 'page_entretien');
    px_hero('Entretien', cp_text('entretien_h1', $d['entretien_h1']), cp_text('entretien_lead', $d['entretien_lead']));
    $quote = setting('tarifs_quote_label', 'Sur devis');
    ?>
<section class="sec sec-navy"><div class="wrap">
  <div class="px-grid">
    <?php foreach (entretien_offers() as $o): [$name, $price, $detail] = array_pad(array_values((array)$o), 3, ''); if (trim((string)$name) === '') continue; ?>
    <div class="px-card px-card--static px-offer">
      <span class="px-card-h"><?= e((string)$name) ?></span>
      <span class="px-offer-price<?= trim((string)$price) === '' ? ' is-quote' : '' ?>"><?= e(trim((string)$price) !== '' ? (string)$price : $quote) ?></span>
      <?= px_list(array_values(array_filter(array_map('trim', explode(';', (string)$detail)), 'strlen'))) ?>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="px-cols" style="margin-top:2rem;">
    <div class="px-box"><h2 class="px-h2">Avec un contrat</h2><?= px_list(cp_lines('entretien_perks', $d['entretien_perks'])) ?></div>
    <div class="px-box"><h2 class="px-h2">Bon à savoir</h2><p><?= e(cp_text('entretien_legal', $d['entretien_legal'])) ?></p></div>
  </div>
</div></section>
<section class="sec sec-card"><div class="wrap" style="max-width:700px;">
  <div class="qs-card">
    <div class="hero-card-tag">Sans engagement</div>
    <h2 class="px-form-title">Demander un contrat d'entretien</h2>
    <?php render_quote_form(service_cards_v14(), 'entretien', 'entretien', ['message' => 'Contrat d\'entretien pour : ']); ?>
  </div>
</div></section>
<?php
    render_footer();
}

/* ── Agent relation client : formulaire « suite à votre appel » ──
   Lien personnel (?t=…) préparé par l'agent ou depuis l'admin, prérempli avec ce
   que l'on sait déjà. Sans lien, la page reste utilisable (lien générique à copier
   dans un SMS). Les réponses deviennent une demande, avec les champs dont Pennylane
   aura besoin (particulier ou professionnel, société, SIRET). */
function render_page_suite_appel(): void
{
    $token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
    $link  = $token !== '' ? agent_link_load($token) : null;
    $self  = route_url('suite-appel').($link ? '&t='.rawurlencode($token) : '');
    $pre   = $link ? (json_decode((string)$link['prefill'], true) ?: []) : [];
    $old   = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'suite_appel') {
        verify_csrf();
        if (trim((string)($_POST['website'] ?? '')) !== '') redirect_to($self);
        if (!rate_limit_passed('suite_appel', 8)) { flash('error', 'Merci de patienter quelques secondes.'); redirect_to($self); }
        $res = agent_followup_submit(['t' => $link ? $token : ''] + $_POST, $_FILES);
        if ($res['ok']) {
            $_SESSION['suite_appel_ok'] = 1;
            flash('success', 'Merci, vos informations sont bien arrivées. Nous préparons votre intervention et revenons vers vous rapidement.');
            if (!empty($res['refused'])) flash('error', 'Certaines photos n\'ont pas pu être jointes (JPG, PNG ou WebP, '.QUOTE_PHOTO_MAX_MB.' Mo maximum). Le reste est bien parti.');
            redirect_to($self);
        }
        flash('error', $res['error']);
        $old = array_map(static fn($v) => is_string($v) ? $v : '', $_POST);
    }
    $v = static fn(string $k): string => (string)($old[$k] ?? $pre[$k] ?? '');
    $done = !empty($_SESSION['suite_appel_ok']) || ($link && !empty($link['submitted_at']) && !$old);
    unset($_SESSION['suite_appel_ok']);
    render_head(['title' => 'Suite à votre appel | '.company_name(), 'description' => 'Complétez vos informations pour préparer l\'intervention.', 'canonical' => route_url('suite-appel'), 'robots' => 'noindex,nofollow']);
    render_header('');
    $first = trim((string)strtok($v('full_name'), ' '));
    px_hero('Suite à votre appel', $first !== '' ? 'Bonjour '.$first.', préparons votre intervention' : 'Préparons votre intervention',
        'Merci pour votre appel. Pour que le technicien arrive avec le bon matériel, complétez ces quelques informations. Vous pouvez aussi simplement répondre à notre message.', false);
    echo '<section class="sec sec-navy"><div class="wrap px-narrow">';
    if ($done) {
        echo '<div class="px-note">✅ Vos informations sont bien enregistrées. Nous revenons vers vous rapidement. Une urgence ? Appelez le <a href="'.e(company_phone_link()).'" style="color:var(--p);font-weight:700;">'.e(company_phone()).'</a>.</div></div></section>';
        render_footer();
        return;
    }
    $kind = $v('customer_kind') === 'pro' ? 'pro' : 'particulier';
    $services = ['Électricité', 'Plomberie', 'Chauffage', 'Climatisation / pompe à chaleur', 'VMC', 'Entretien de chaudière', 'Portail / visiophone', 'Autre'];
    ?>
  <div class="px-note">🚨 Urgence en cours (fuite, plus de courant, odeur de gaz) ? Appelez directement le <a href="<?= e(company_phone_link()) ?>" style="color:var(--p);font-weight:700;"><?= e(company_phone()) ?></a>, jour et nuit.</div>
  <form class="qs-card" action="<?= e($self) ?>" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="form_type" value="suite_appel">
    <?php if ($link): ?><input type="hidden" name="t" value="<?= e($token) ?>"><?php endif; ?>
    <input type="text" name="website" value="" class="hp" tabindex="-1" autocomplete="off">
    <h2 class="px-form-title">1. Vos coordonnées</h2>
    <div class="f-field"><span class="f-label">Vous êtes</span>
      <label class="px-radio"><input type="radio" name="customer_kind" value="particulier"<?= $kind === 'particulier' ? ' checked' : '' ?>> un particulier</label>
      <label class="px-radio"><input type="radio" name="customer_kind" value="pro"<?= $kind === 'pro' ? ' checked' : '' ?>> un professionnel, un syndic ou un bailleur</label>
    </div>
    <div class="f-row" data-pro<?= $kind === 'pro' ? '' : ' hidden' ?>>
      <div class="f-field"><span class="f-label">Société *</span><input class="f-input" type="text" name="company_name" value="<?= e($v('company_name')) ?>"></div>
      <div class="f-field"><span class="f-label">SIRET (facultatif)</span><input class="f-input" type="text" name="siret" inputmode="numeric" maxlength="17" value="<?= e($v('siret')) ?>"></div>
    </div>
    <div class="f-row">
      <div class="f-field"><span class="f-label">Nom complet *</span><input class="f-input" type="text" name="full_name" required autocomplete="name" value="<?= e($v('full_name')) ?>"></div>
      <div class="f-field"><span class="f-label">Téléphone *</span><input class="f-input" type="tel" name="phone" required autocomplete="tel" value="<?= e($v('phone')) ?>"></div>
    </div>
    <div class="f-field"><span class="f-label">E-mail</span><input class="f-input" type="email" name="email" autocomplete="email" value="<?= e($v('email')) ?>"></div>
    <h2 class="px-form-title" style="margin-top:1.5rem;">2. Le lieu de l'intervention</h2>
    <div class="f-field"><span class="f-label">Adresse *</span><input class="f-input" type="text" name="address" required autocomplete="street-address" value="<?= e($v('address')) ?>"></div>
    <div class="f-row">
      <div class="f-field"><span class="f-label">Code postal *</span><input class="f-input" type="text" name="postal_code" required pattern="[0-9]{5}" maxlength="5" inputmode="numeric" autocomplete="postal-code" value="<?= e($v('postal_code')) ?>"></div>
      <div class="f-field"><span class="f-label">Ville *</span><input class="f-input" type="text" name="city" required autocomplete="address-level2" value="<?= e($v('city')) ?>"></div>
    </div>
    <h2 class="px-form-title" style="margin-top:1.5rem;">3. Votre besoin</h2>
    <div class="f-field"><span class="f-label">Type d'intervention</span><select class="f-input" name="service_type">
      <?php foreach ($services as $s): ?><option<?= $v('service_type') === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
    </select></div>
    <div class="f-field"><span class="f-label">Décrivez la panne ou les travaux *</span><textarea class="f-input f-textarea" name="description" required placeholder="Ce qui se passe, depuis quand, marque de l'appareil, accès (étage, digicode)…"><?= e($v('description')) ?></textarea></div>
    <?php render_quote_photo_field(); ?>
    <div class="f-field"><span class="f-label">Vos disponibilités (facultatif)</span><textarea class="f-input f-textarea" name="availability" rows="2" placeholder="Exemple : en semaine après 17 h, samedi matin"><?= e($v('availability')) ?></textarea></div>
    <button class="btn btn-p btn-lg btn-block" type="submit">Envoyer mes informations</button>
    <p class="f-meta">Vos informations servent uniquement à préparer cette intervention et son devis. <a href="<?= e(route_url('mentions-legales')) ?>">Données personnelles</a></p>
  </form>
  <script>document.querySelectorAll('input[name="customer_kind"]').forEach(function(r){r.addEventListener('change',function(){document.querySelector('[data-pro]').hidden=this.value!=='pro';});});</script>
</div></section>
<?php
    render_footer();
}
