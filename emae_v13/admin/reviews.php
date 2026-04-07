<?php
$adminSection = 'reviews';
require __DIR__ . '/partials/header.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $author  = trim((string)($_POST['author_name'] ?? ''));
    $rating  = max(1, min(5,(int)($_POST['rating'] ?? 5)));
    $content = trim((string)($_POST['content'] ?? ''));
    $service = trim((string)($_POST['service_type'] ?? ''));
    $city    = trim((string)($_POST['city'] ?? ''));
    if ($author !== '' && $content !== '') {
        db_execute('INSERT INTO reviews (author_name,rating,content,service_type,city,is_visible,sort_order) VALUES (?,?,?,?,?,1,?)',
            [$author,$rating,$content,$service,$city,(int)time()]);
        flash('success','Avis ajouté.');
    } else { flash('error','Nom et texte obligatoires.'); }
    redirect_to('admin/reviews.php');
}
if (isset($_GET['toggle'])) {
    $id=(int)$_GET['toggle']; $row=db_fetch('SELECT is_visible FROM reviews WHERE id=?',[$id]);
    if($row) db_execute('UPDATE reviews SET is_visible=? WHERE id=?',[(int)!$row['is_visible'],$id]);
    redirect_to('admin/reviews.php');
}
if (isset($_GET['delete'])) {
    db_execute('DELETE FROM reviews WHERE id=?',[(int)$_GET['delete']]);
    flash('success','Avis supprimé.'); redirect_to('admin/reviews.php');
}
$reviews = db_fetch_all('SELECT * FROM reviews ORDER BY sort_order ASC, id DESC');
?>
<div class="admin-page-toolbar"><div><div class="admin-breadcrumb">Contenus</div><h1 class="admin-page-title">Avis clients</h1><p class="admin-page-subtitle">Les avis visibles s'affichent sur l'accueil. Ajoutez les avis Google ou clients satisfaits.</p></div></div>
<div class="admin-stack">
<section class="admin-panel"><div class="admin-panel__head"><h2>Ajouter un avis</h2></div><div class="admin-panel__body">
<form method="post" class="admin-form-grid admin-form-grid--2"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<label class="admin-field"><span>Auteur *</span><input type="text" name="author_name" required placeholder="Nadia B."></label>
<label class="admin-field"><span>Note /5</span><input type="number" name="rating" min="1" max="5" value="5"></label>
<label class="admin-field"><span>Service</span><select name="service_type"><option value="">Général</option><?php foreach(['Électricité','Plomberie','Chauffage','Climatisation','CVC','Maintenance'] as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select></label>
<label class="admin-field"><span>Ville</span><input type="text" name="city" placeholder="Paris, Meaux…"></label>
<label class="admin-field" style="grid-column:1/-1"><span>Texte de l'avis *</span><textarea name="content" rows="4" required placeholder="Intervention rapide, très professionnel…"></textarea></label>
<div class="admin-savebar" style="grid-column:1/-1;justify-content:flex-start"><button class="admin-btn admin-btn--primary" type="submit">Ajouter</button></div>
</form></div></section>

<section class="admin-panel"><div class="admin-panel__head"><h2>Liste des avis (<?= e((string)count($reviews)) ?>)</h2></div><div class="admin-panel__body admin-table-wrap">
<table class="admin-table"><thead><tr><th>Auteur</th><th>Note</th><th>Service</th><th>Ville</th><th>Texte</th><th>Visible</th><th>Actions</th></tr></thead><tbody>
<?php foreach($reviews as $r): ?>
<tr>
<td style="font-weight:700"><?= e($r['author_name']) ?></td>
<td><?= str_repeat('★',(int)$r['rating']) ?></td>
<td><?= e((string)($r['service_type']??'')) ?></td>
<td><?= e((string)($r['city']??'')) ?></td>
<td style="max-width:260px;font-size:.88rem;color:#5b6b92;"><?= e(mb_substr($r['content'],0,80,'UTF-8')).(mb_strlen($r['content'],'UTF-8')>80?'…':'') ?></td>
<td><?= (int)$r['is_visible']===1?'<span style="color:#16a34a;font-weight:700;">✓</span>':'<span style="color:#dc2626;">✗</span>' ?></td>
<td><div style="display:flex;gap:.4rem;">
<a class="admin-btn admin-btn--secondary" href="<?= e(url_for('admin/reviews.php?toggle='.(int)$r['id'])) ?>" style="min-height:36px;padding:0 .75rem;font-size:.82rem;"><?= (int)$r['is_visible']===1?'Masquer':'Afficher' ?></a>
<a class="admin-btn" href="<?= e(url_for('admin/reviews.php?delete='.(int)$r['id'])) ?>" onclick="return confirm('Supprimer ?')" style="min-height:36px;padding:0 .75rem;font-size:.82rem;background:#fff0f0;color:#dc2626;border:1px solid #fecaca;">×</a>
</div></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
