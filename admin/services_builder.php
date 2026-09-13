<?php
$adminSection = 'services_builder';
require __DIR__ . '/partials/header.php';

function sb_type_labels(): array
{
    return services_builder_type_labels();
}

function sb_render_type_options(string $selected = 'text'): void
{
    foreach (sb_type_labels() as $value => $label) {
        $isSelected = $selected === $value ? 'selected' : '';
        echo '<option value="' . e($value) . '" ' . $isSelected . '>' . e($label) . '</option>';
    }
}

function sb_render_card_item(int $blockIndex, int $itemIndex, array $item): void
{
    ?>
    <div class="services-builder-item" data-item>
        <input type="hidden" name="blocks[<?= e((string) $blockIndex) ?>][items][<?= e((string) $itemIndex) ?>][current_image]" value="<?= e((string) ($item['image'] ?? '')) ?>">
        <div class="services-builder-actions" style="justify-content:flex-end;margin-bottom:.75rem;">
            <button type="button" class="admin-btn admin-btn--secondary" data-remove-item>Supprimer cet élément</button>
        </div>
        <div class="admin-form-grid admin-form-grid--2">
            <label class="admin-field">
                <span>Titre</span>
                <input type="text" name="blocks[<?= e((string) $blockIndex) ?>][items][<?= e((string) $itemIndex) ?>][title]" value="<?= e((string) ($item['title'] ?? '')) ?>">
            </label>
            <label class="admin-field">
                <span>Bouton</span>
                <input type="text" name="blocks[<?= e((string) $blockIndex) ?>][items][<?= e((string) $itemIndex) ?>][button_label]" value="<?= e((string) ($item['button_label'] ?? '')) ?>">
            </label>
            <label class="admin-field" style="grid-column:1/-1">
                <span>Texte</span>
                <textarea name="blocks[<?= e((string) $blockIndex) ?>][items][<?= e((string) $itemIndex) ?>][text]" rows="4"><?= e((string) ($item['text'] ?? '')) ?></textarea>
            </label>
            <label class="admin-field">
                <span>Lien</span>
                <input type="text" name="blocks[<?= e((string) $blockIndex) ?>][items][<?= e((string) $itemIndex) ?>][button_url]" value="<?= e((string) ($item['button_url'] ?? '')) ?>" placeholder="electricien-meaux ou https://...">
            </label>
            <label class="admin-field">
                <span>Nouvelle image</span>
                <input type="file" name="block_card_image_<?= e((string) $blockIndex) ?>_<?= e((string) $itemIndex) ?>" accept=".png,.jpg,.jpeg,.webp,.svg">
            </label>
        </div>

        <?php if (!empty($item['image'])): ?>
            <img class="preview-thumb" src="<?= e(asset_url((string) $item['image'])) ?>" alt="">
        <?php endif; ?>
    </div>
    <?php
}

function sb_render_faq_item(int $blockIndex, int $itemIndex, array $item): void
{
    ?>
    <div class="services-builder-item" data-item>
        <div class="services-builder-actions" style="justify-content:flex-end;margin-bottom:.75rem;">
            <button type="button" class="admin-btn admin-btn--secondary" data-remove-item>Supprimer cette question</button>
        </div>
        <div class="admin-form-grid">
            <label class="admin-field">
                <span>Question</span>
                <input type="text" name="blocks[<?= e((string) $blockIndex) ?>][items][<?= e((string) $itemIndex) ?>][question]" value="<?= e((string) ($item['question'] ?? '')) ?>">
            </label>
            <label class="admin-field">
                <span>Réponse (HTML autorisé)</span>
                <textarea name="blocks[<?= e((string) $blockIndex) ?>][items][<?= e((string) $itemIndex) ?>][answer]" rows="5"><?= e((string) ($item['answer'] ?? '')) ?></textarea>
            </label>
        </div>
    </div>
    <?php
}

function sb_render_block(int $index, array $block): void
{
    $type = (string) ($block['type'] ?? 'text');
    ?>
    <article class="repeat-card services-builder-block" data-block data-block-index="<?= e((string) $index) ?>">
        <input type="hidden" name="blocks[<?= e((string) $index) ?>][id]" value="<?= e((string) ($block['id'] ?? '')) ?>">

        <div class="services-builder-block__head">
            <div class="services-builder-block__meta">
                <strong>Bloc <span data-block-number><?= e((string) ($index + 1)) ?></span></strong>
                <span class="services-builder-badge" data-block-type-label><?= e(sb_type_labels()[$type] ?? 'Bloc') ?></span>
            </div>

            <div class="services-builder-actions">
                <button type="button" class="admin-btn admin-btn--secondary" data-move="up">Monter</button>
                <button type="button" class="admin-btn admin-btn--secondary" data-move="down">Descendre</button>
                <button type="button" class="admin-btn admin-btn--secondary" data-remove-block>Supprimer</button>
            </div>
        </div>

        <div class="admin-form-grid admin-form-grid--2">
            <label class="admin-field">
                <span>Type de bloc</span>
                <select name="blocks[<?= e((string) $index) ?>][type]" data-block-type>
                    <?php sb_render_type_options($type); ?>
                </select>
            </label>

            <label class="admin-field">
                <span>Ancre (optionnel)</span>
                <input type="text" name="blocks[<?= e((string) $index) ?>][anchor]" value="<?= e((string) ($block['anchor'] ?? '')) ?>" placeholder="faq-services">
            </label>

            <label class="admin-field">
                <span>Eyebrow</span>
                <input type="text" name="blocks[<?= e((string) $index) ?>][eyebrow]" value="<?= e((string) ($block['eyebrow'] ?? '')) ?>">
            </label>

            <label class="admin-field">
                <span>Titre</span>
                <input type="text" name="blocks[<?= e((string) $index) ?>][title]" value="<?= e((string) ($block['title'] ?? '')) ?>">
            </label>

            <label class="admin-field" style="grid-column:1/-1">
                <span>Sous-titre</span>
                <textarea name="blocks[<?= e((string) $index) ?>][subtitle]" rows="3"><?= e((string) ($block['subtitle'] ?? '')) ?></textarea>
            </label>

            <label class="admin-field">
                <span>Couleur de fond</span>
                <input type="color" name="blocks[<?= e((string) $index) ?>][background]" value="<?= e((string) (($block['background'] ?? '') !== '' ? $block['background'] : '#ffffff')) ?>">
            </label>

            <label class="admin-field">
                <span>Couleur du texte</span>
                <input type="color" name="blocks[<?= e((string) $index) ?>][text_color]" value="<?= e((string) (($block['text_color'] ?? '') !== '' ? $block['text_color'] : '#0b1641')) ?>">
            </label>
        </div>

        <div class="services-builder-type-panel" data-type-panel="text" <?= $type === 'text' ? '' : 'hidden' ?>>
            <div class="admin-form-grid">
                <label class="admin-field">
                    <span>Texte (HTML autorisé)</span>
                    <textarea name="blocks[<?= e((string) $index) ?>][text]" rows="8"><?= e((string) ($block['text'] ?? '')) ?></textarea>
                </label>
                <label class="admin-field">
                    <span>Texte bouton</span>
                    <input type="text" name="blocks[<?= e((string) $index) ?>][button_label]" value="<?= e((string) ($block['button_label'] ?? '')) ?>">
                </label>
                <label class="admin-field">
                    <span>Lien bouton</span>
                    <input type="text" name="blocks[<?= e((string) $index) ?>][button_url]" value="<?= e((string) ($block['button_url'] ?? '')) ?>" placeholder="quote ou https://...">
                </label>
            </div>
        </div>

        <div class="services-builder-type-panel" data-type-panel="image_text" <?= $type === 'image_text' ? '' : 'hidden' ?>>
            <input type="hidden" name="blocks[<?= e((string) $index) ?>][current_image]" value="<?= e((string) ($block['image'] ?? '')) ?>">
            <div class="admin-form-grid admin-form-grid--2">
                <label class="admin-field">
                    <span>Texte (HTML autorisé)</span>
                    <textarea name="blocks[<?= e((string) $index) ?>][text]" rows="8"><?= e((string) ($block['text'] ?? '')) ?></textarea>
                </label>

                <div>
                    <?php if (!empty($block['image'])): ?>
                        <img class="preview-thumb" src="<?= e(asset_url((string) $block['image'])) ?>" alt="">
                    <?php endif; ?>
                    <label class="admin-field">
                        <span>Nouvelle image</span>
                        <input type="file" name="block_image_<?= e((string) $index) ?>" accept=".png,.jpg,.jpeg,.webp,.svg">
                    </label>
                </div>

                <label class="admin-field">
                    <span>Position image</span>
                    <select name="blocks[<?= e((string) $index) ?>][layout]">
                        <option value="image_right" <?= ($block['layout'] ?? '') === 'image_right' ? 'selected' : '' ?>>Image à droite</option>
                        <option value="image_left" <?= ($block['layout'] ?? '') === 'image_left' ? 'selected' : '' ?>>Image à gauche</option>
                    </select>
                </label>

                <label class="admin-field">
                    <span>Texte bouton</span>
                    <input type="text" name="blocks[<?= e((string) $index) ?>][button_label]" value="<?= e((string) ($block['button_label'] ?? '')) ?>">
                </label>

                <label class="admin-field">
                    <span>Lien bouton</span>
                    <input type="text" name="blocks[<?= e((string) $index) ?>][button_url]" value="<?= e((string) ($block['button_url'] ?? '')) ?>">
                </label>
            </div>
        </div>

        <div class="services-builder-type-panel" data-type-panel="cards" <?= $type === 'cards' ? '' : 'hidden' ?>>
            <div class="services-builder-actions" style="margin-bottom:1rem;">
                <button type="button" class="admin-btn admin-btn--primary" data-add-item="cards">Ajouter une carte</button>
            </div>

            <div class="services-builder-items" data-items="cards">
                <?php foreach (($block['items'] ?? []) as $itemIndex => $item): ?>
                    <?php sb_render_card_item($index, (int) $itemIndex, $item); ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="services-builder-type-panel" data-type-panel="faq" <?= $type === 'faq' ? '' : 'hidden' ?>>
            <div class="services-builder-actions" style="margin-bottom:1rem;">
                <button type="button" class="admin-btn admin-btn--primary" data-add-item="faq">Ajouter une question</button>
            </div>

            <div class="services-builder-items" data-items="faq">
                <?php foreach (($block['items'] ?? []) as $itemIndex => $item): ?>
                    <?php sb_render_faq_item($index, (int) $itemIndex, $item); ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="services-builder-type-panel" data-type-panel="cta" <?= $type === 'cta' ? '' : 'hidden' ?>>
            <div class="admin-form-grid admin-form-grid--2">
                <label class="admin-field" style="grid-column:1/-1">
                    <span>Texte CTA (HTML autorisé)</span>
                    <textarea name="blocks[<?= e((string) $index) ?>][text]" rows="7"><?= e((string) ($block['text'] ?? '')) ?></textarea>
                </label>

                <label class="admin-field">
                    <span>Bouton principal</span>
                    <input type="text" name="blocks[<?= e((string) $index) ?>][button_label]" value="<?= e((string) ($block['button_label'] ?? '')) ?>">
                </label>

                <label class="admin-field">
                    <span>Lien principal</span>
                    <input type="text" name="blocks[<?= e((string) $index) ?>][button_url]" value="<?= e((string) ($block['button_url'] ?? '')) ?>">
                </label>

                <label class="admin-field">
                    <span>Bouton secondaire</span>
                    <input type="text" name="blocks[<?= e((string) $index) ?>][button2_label]" value="<?= e((string) ($block['button2_label'] ?? '')) ?>">
                </label>

                <label class="admin-field">
                    <span>Lien secondaire</span>
                    <input type="text" name="blocks[<?= e((string) $index) ?>][button2_url]" value="<?= e((string) ($block['button2_url'] ?? '')) ?>">
                </label>
            </div>
        </div>

        <div class="services-builder-type-panel" data-type-panel="html" <?= $type === 'html' ? '' : 'hidden' ?>>
            <div class="admin-form-grid">
                <label class="admin-field">
                    <span>HTML libre</span>
                    <textarea name="blocks[<?= e((string) $index) ?>][html]" rows="10"><?= e((string) ($block['html'] ?? '')) ?></textarea>
                </label>
            </div>
        </div>
    </article>
    <?php
}

$config = services_builder_config();
$page = $config['page'];
$blocks = $config['blocks'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $postedPage = is_array($_POST['page'] ?? null) ? $_POST['page'] : [];

    $currentHeroImage = trim((string) ($postedPage['current_hero_image'] ?? ''));
    $uploadedHero = upload_image_field('page_hero_image', 'services');

    $pageConfig = [
        'eyebrow'          => trim((string) ($postedPage['eyebrow'] ?? '')),
        'title'            => trim((string) ($postedPage['title'] ?? 'Services')),
        'subtitle'         => trim((string) ($postedPage['subtitle'] ?? '')),
        'meta_title'       => trim((string) ($postedPage['meta_title'] ?? '')),
        'meta_description' => trim((string) ($postedPage['meta_description'] ?? '')),
        'hero_background'  => trim((string) ($postedPage['hero_background'] ?? '#0b1641')),
        'hero_text_color'  => trim((string) ($postedPage['hero_text_color'] ?? '#ffffff')),
        'hero_image'       => $uploadedHero ?: $currentHeroImage,
        'primary_label'    => trim((string) ($postedPage['primary_label'] ?? '')),
        'primary_url'      => trim((string) ($postedPage['primary_url'] ?? '')),
        'secondary_label'  => trim((string) ($postedPage['secondary_label'] ?? '')),
        'secondary_url'    => trim((string) ($postedPage['secondary_url'] ?? '')),
    ];

    $cleanBlocks = [];
    $rawBlocks = is_array($_POST['blocks'] ?? null) ? $_POST['blocks'] : [];

    foreach ($rawBlocks as $blockIndex => $rawBlock) {
        if (!is_array($rawBlock)) {
            continue;
        }

        $type = trim((string) ($rawBlock['type'] ?? 'text'));
        if (!array_key_exists($type, sb_type_labels())) {
            $type = 'text';
        }

        $currentImage = trim((string) ($rawBlock['current_image'] ?? ''));
        $uploadedImage = upload_image_field('block_image_' . $blockIndex, 'services');

        $block = services_builder_normalize_block([
            'id'            => trim((string) ($rawBlock['id'] ?? '')),
            'type'          => $type,
            'anchor'        => trim((string) ($rawBlock['anchor'] ?? '')),
            'eyebrow'       => trim((string) ($rawBlock['eyebrow'] ?? '')),
            'title'         => trim((string) ($rawBlock['title'] ?? '')),
            'subtitle'      => trim((string) ($rawBlock['subtitle'] ?? '')),
            'text'          => (string) ($rawBlock['text'] ?? ''),
            'html'          => (string) ($rawBlock['html'] ?? ''),
            'image'         => $uploadedImage ?: $currentImage,
            'layout'        => trim((string) ($rawBlock['layout'] ?? 'image_right')),
            'button_label'  => trim((string) ($rawBlock['button_label'] ?? '')),
            'button_url'    => trim((string) ($rawBlock['button_url'] ?? '')),
            'button2_label' => trim((string) ($rawBlock['button2_label'] ?? '')),
            'button2_url'   => trim((string) ($rawBlock['button2_url'] ?? '')),
            'background'    => trim((string) ($rawBlock['background'] ?? '')),
            'text_color'    => trim((string) ($rawBlock['text_color'] ?? '')),
        ]);

        if ($type === 'cards') {
            $items = [];
            foreach (($rawBlock['items'] ?? []) as $itemIndex => $rawItem) {
                if (!is_array($rawItem)) {
                    continue;
                }

                $title = trim((string) ($rawItem['title'] ?? ''));
                $text = trim((string) ($rawItem['text'] ?? ''));
                $buttonLabel = trim((string) ($rawItem['button_label'] ?? ''));
                $buttonUrl = trim((string) ($rawItem['button_url'] ?? ''));
                $currentItemImage = trim((string) ($rawItem['current_image'] ?? ''));
                $uploadedItemImage = upload_image_field('block_card_image_' . $blockIndex . '_' . $itemIndex, 'services');
                $image = $uploadedItemImage ?: $currentItemImage;

                if ($title === '' && $text === '' && $buttonLabel === '' && $buttonUrl === '' && $image === '') {
                    continue;
                }

                $items[] = [
                    'title'        => $title,
                    'text'         => $text,
                    'image'        => $image,
                    'button_label' => $buttonLabel,
                    'button_url'   => $buttonUrl,
                ];
            }

            $block['items'] = $items;
        }

        if ($type === 'faq') {
            $items = [];
            foreach (($rawBlock['items'] ?? []) as $rawItem) {
                if (!is_array($rawItem)) {
                    continue;
                }

                $question = trim((string) ($rawItem['question'] ?? ''));
                $answer = (string) ($rawItem['answer'] ?? '');

                if ($question === '' && trim(strip_tags($answer)) === '') {
                    continue;
                }

                $items[] = [
                    'question' => $question,
                    'answer'   => $answer,
                ];
            }

            $block['items'] = $items;
        }

        $cleanBlocks[] = services_builder_normalize_block($block);
    }

    if (!$cleanBlocks) {
        $cleanBlocks[] = services_builder_block_template('text');
    }

    $configToSave = [
        'page'   => $pageConfig,
        'blocks' => $cleanBlocks,
    ];

    set_json_setting('services_page_builder', $configToSave);
    services_builder_sync_page($pageConfig);

    flash('success', 'Page Services enregistrée.');
    redirect_to('admin/services_builder.php');
}
?>

<div class="admin-page-toolbar">
    <div>
        <div class="admin-breadcrumb">Contenus</div>
        <h1 class="admin-page-title">Page services</h1>
        <p class="admin-page-subtitle">Builder dédié à la page générale services, sans toucher à tes landing pages SEO locales.</p>
    </div>
</div>

<form method="post" enctype="multipart/form-data" class="admin-stack" data-services-builder>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <section class="admin-panel">
        <div class="admin-panel__head">
            <h2>Hero de la page services</h2>
        </div>

        <div class="admin-panel__body admin-form-grid admin-form-grid--2">
            <label class="admin-field">
                <span>Eyebrow</span>
                <input type="text" name="page[eyebrow]" value="<?= e((string) ($page['eyebrow'] ?? '')) ?>">
            </label>

            <label class="admin-field">
                <span>Titre principal</span>
                <input type="text" name="page[title]" value="<?= e((string) ($page['title'] ?? '')) ?>">
            </label>

            <label class="admin-field" style="grid-column:1/-1">
                <span>Sous-titre</span>
                <textarea name="page[subtitle]" rows="4"><?= e((string) ($page['subtitle'] ?? '')) ?></textarea>
            </label>

            <label class="admin-field">
                <span>Meta title</span>
                <input type="text" name="page[meta_title]" value="<?= e((string) ($page['meta_title'] ?? '')) ?>">
            </label>

            <label class="admin-field">
                <span>Meta description</span>
                <input type="text" name="page[meta_description]" value="<?= e((string) ($page['meta_description'] ?? '')) ?>">
            </label>

            <label class="admin-field">
                <span>Fond du hero</span>
                <input type="color" name="page[hero_background]" value="<?= e((string) (($page['hero_background'] ?? '') !== '' ? $page['hero_background'] : '#0b1641')) ?>">
            </label>

            <label class="admin-field">
                <span>Couleur du texte</span>
                <input type="color" name="page[hero_text_color]" value="<?= e((string) (($page['hero_text_color'] ?? '') !== '' ? $page['hero_text_color'] : '#ffffff')) ?>">
            </label>

            <input type="hidden" name="page[current_hero_image]" value="<?= e((string) ($page['hero_image'] ?? '')) ?>">

            <div>
                <?php if (!empty($page['hero_image'])): ?>
                    <img class="preview-thumb" src="<?= e(asset_url((string) $page['hero_image'])) ?>" alt="">
                <?php endif; ?>
                <label class="admin-field">
                    <span>Image du hero</span>
                    <input type="file" name="page_hero_image" accept=".png,.jpg,.jpeg,.webp,.svg">
                </label>
            </div>

            <div class="admin-form-grid admin-form-grid--2" style="grid-column:1/-1">
                <label class="admin-field">
                    <span>Bouton principal</span>
                    <input type="text" name="page[primary_label]" value="<?= e((string) ($page['primary_label'] ?? '')) ?>">
                </label>

                <label class="admin-field">
                    <span>Lien principal</span>
                    <input type="text" name="page[primary_url]" value="<?= e((string) ($page['primary_url'] ?? '')) ?>" placeholder="quote, services/electricite, https://...">
                </label>

                <label class="admin-field">
                    <span>Bouton secondaire</span>
                    <input type="text" name="page[secondary_label]" value="<?= e((string) ($page['secondary_label'] ?? '')) ?>">
                </label>

                <label class="admin-field">
                    <span>Lien secondaire</span>
                    <input type="text" name="page[secondary_url]" value="<?= e((string) ($page['secondary_url'] ?? '')) ?>" placeholder="tel:+336... ou contact">
                </label>
            </div>
        </div>
    </section>

    <section class="admin-panel">
        <div class="admin-panel__head">
            <h2>Blocs de la page services</h2>
        </div>

        <div class="admin-panel__body">
            <div class="services-builder-toolbar">
                <button type="button" class="admin-btn admin-btn--primary" data-add-block="text">Ajouter texte</button>
                <button type="button" class="admin-btn admin-btn--primary" data-add-block="image_text">Ajouter texte + image</button>
                <button type="button" class="admin-btn admin-btn--primary" data-add-block="cards">Ajouter cartes</button>
                <button type="button" class="admin-btn admin-btn--primary" data-add-block="faq">Ajouter FAQ</button>
                <button type="button" class="admin-btn admin-btn--primary" data-add-block="cta">Ajouter CTA</button>
                <button type="button" class="admin-btn admin-btn--primary" data-add-block="html">Ajouter HTML libre</button>
            </div>

            <p class="services-builder-empty">Tu peux ajouter, supprimer et réordonner les blocs avec les boutons Monter / Descendre.</p>

            <div class="repeat-grid" data-blocks-host>
                <?php foreach ($blocks as $blockIndex => $block): ?>
                    <?php sb_render_block((int) $blockIndex, $block); ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <div class="admin-savebar">
        <button class="admin-btn admin-btn--primary" type="submit">Enregistrer la page services</button>
    </div>
</form>

<template id="services-card-item-template">
    <div class="services-builder-item" data-item>
        <input type="hidden" name="blocks[__BLOCK_INDEX__][items][__ITEM_INDEX__][current_image]" value="">
        <div class="services-builder-actions" style="justify-content:flex-end;margin-bottom:.75rem;">
            <button type="button" class="admin-btn admin-btn--secondary" data-remove-item>Supprimer cet élément</button>
        </div>
        <div class="admin-form-grid admin-form-grid--2">
            <label class="admin-field">
                <span>Titre</span>
                <input type="text" name="blocks[__BLOCK_INDEX__][items][__ITEM_INDEX__][title]" value="">
            </label>
            <label class="admin-field">
                <span>Bouton</span>
                <input type="text" name="blocks[__BLOCK_INDEX__][items][__ITEM_INDEX__][button_label]" value="">
            </label>
            <label class="admin-field" style="grid-column:1/-1">
                <span>Texte</span>
                <textarea name="blocks[__BLOCK_INDEX__][items][__ITEM_INDEX__][text]" rows="4"></textarea>
            </label>
            <label class="admin-field">
                <span>Lien</span>
                <input type="text" name="blocks[__BLOCK_INDEX__][items][__ITEM_INDEX__][button_url]" value="">
            </label>
            <label class="admin-field">
                <span>Nouvelle image</span>
                <input type="file" name="block_card_image___BLOCK_INDEX_____ITEM_INDEX__" accept=".png,.jpg,.jpeg,.webp,.svg">
            </label>
        </div>
    </div>
</template>

<template id="services-faq-item-template">
    <div class="services-builder-item" data-item>
        <div class="services-builder-actions" style="justify-content:flex-end;margin-bottom:.75rem;">
            <button type="button" class="admin-btn admin-btn--secondary" data-remove-item>Supprimer cette question</button>
        </div>
        <div class="admin-form-grid">
            <label class="admin-field">
                <span>Question</span>
                <input type="text" name="blocks[__BLOCK_INDEX__][items][__ITEM_INDEX__][question]" value="">
            </label>
            <label class="admin-field">
                <span>Réponse (HTML autorisé)</span>
                <textarea name="blocks[__BLOCK_INDEX__][items][__ITEM_INDEX__][answer]" rows="5"></textarea>
            </label>
        </div>
    </div>
</template>

<template id="services-block-template">
    <article class="repeat-card services-builder-block" data-block data-block-index="0">
        <input type="hidden" name="blocks[0][id]" value="__BLOCK_ID__">

        <div class="services-builder-block__head">
            <div class="services-builder-block__meta">
                <strong>Bloc <span data-block-number>1</span></strong>
                <span class="services-builder-badge" data-block-type-label>__TYPE_LABEL__</span>
            </div>

            <div class="services-builder-actions">
                <button type="button" class="admin-btn admin-btn--secondary" data-move="up">Monter</button>
                <button type="button" class="admin-btn admin-btn--secondary" data-move="down">Descendre</button>
                <button type="button" class="admin-btn admin-btn--secondary" data-remove-block>Supprimer</button>
            </div>
        </div>

        <div class="admin-form-grid admin-form-grid--2">
            <label class="admin-field">
                <span>Type de bloc</span>
                <select name="blocks[0][type]" data-block-type data-default-type="__TYPE__">
                    <?php sb_render_type_options('text'); ?>
                </select>
            </label>

            <label class="admin-field">
                <span>Ancre (optionnel)</span>
                <input type="text" name="blocks[0][anchor]" value="">
            </label>

            <label class="admin-field">
                <span>Eyebrow</span>
                <input type="text" name="blocks[0][eyebrow]" value="">
            </label>

            <label class="admin-field">
                <span>Titre</span>
                <input type="text" name="blocks[0][title]" value="">
            </label>

            <label class="admin-field" style="grid-column:1/-1">
                <span>Sous-titre</span>
                <textarea name="blocks[0][subtitle]" rows="3"></textarea>
            </label>

            <label class="admin-field">
                <span>Couleur de fond</span>
                <input type="color" name="blocks[0][background]" value="#ffffff">
            </label>

            <label class="admin-field">
                <span>Couleur du texte</span>
                <input type="color" name="blocks[0][text_color]" value="#0b1641">
            </label>
        </div>

        <div class="services-builder-type-panel" data-type-panel="text">
            <div class="admin-form-grid">
                <label class="admin-field">
                    <span>Texte (HTML autorisé)</span>
                    <textarea name="blocks[0][text]" rows="8"></textarea>
                </label>
                <label class="admin-field">
                    <span>Texte bouton</span>
                    <input type="text" name="blocks[0][button_label]" value="">
                </label>
                <label class="admin-field">
                    <span>Lien bouton</span>
                    <input type="text" name="blocks[0][button_url]" value="">
                </label>
            </div>
        </div>

        <div class="services-builder-type-panel" data-type-panel="image_text" hidden>
            <input type="hidden" name="blocks[0][current_image]" value="">
            <div class="admin-form-grid admin-form-grid--2">
                <label class="admin-field">
                    <span>Texte (HTML autorisé)</span>
                    <textarea name="blocks[0][text]" rows="8"></textarea>
                </label>
                <label class="admin-field">
                    <span>Nouvelle image</span>
                    <input type="file" name="block_image_0" accept=".png,.jpg,.jpeg,.webp,.svg">
                </label>
                <label class="admin-field">
                    <span>Position image</span>
                    <select name="blocks[0][layout]">
                        <option value="image_right">Image à droite</option>
                        <option value="image_left">Image à gauche</option>
                    </select>
                </label>
                <label class="admin-field">
                    <span>Texte bouton</span>
                    <input type="text" name="blocks[0][button_label]" value="">
                </label>
                <label class="admin-field">
                    <span>Lien bouton</span>
                    <input type="text" name="blocks[0][button_url]" value="">
                </label>
            </div>
        </div>

        <div class="services-builder-type-panel" data-type-panel="cards" hidden>
            <div class="services-builder-actions" style="margin-bottom:1rem;">
                <button type="button" class="admin-btn admin-btn--primary" data-add-item="cards">Ajouter une carte</button>
            </div>
            <div class="services-builder-items" data-items="cards"></div>
        </div>

        <div class="services-builder-type-panel" data-type-panel="faq" hidden>
            <div class="services-builder-actions" style="margin-bottom:1rem;">
                <button type="button" class="admin-btn admin-btn--primary" data-add-item="faq">Ajouter une question</button>
            </div>
            <div class="services-builder-items" data-items="faq"></div>
        </div>

        <div class="services-builder-type-panel" data-type-panel="cta" hidden>
            <div class="admin-form-grid admin-form-grid--2">
                <label class="admin-field" style="grid-column:1/-1">
                    <span>Texte CTA (HTML autorisé)</span>
                    <textarea name="blocks[0][text]" rows="7"></textarea>
                </label>
                <label class="admin-field">
                    <span>Bouton principal</span>
                    <input type="text" name="blocks[0][button_label]" value="">
                </label>
                <label class="admin-field">
                    <span>Lien principal</span>
                    <input type="text" name="blocks[0][button_url]" value="">
                </label>
                <label class="admin-field">
                    <span>Bouton secondaire</span>
                    <input type="text" name="blocks[0][button2_label]" value="">
                </label>
                <label class="admin-field">
                    <span>Lien secondaire</span>
                    <input type="text" name="blocks[0][button2_url]" value="">
                </label>
            </div>
        </div>

        <div class="services-builder-type-panel" data-type-panel="html" hidden>
            <div class="admin-form-grid">
                <label class="admin-field">
                    <span>HTML libre</span>
                    <textarea name="blocks[0][html]" rows="10"></textarea>
                </label>
            </div>
        </div>
    </article>
</template>

<?php require __DIR__ . '/partials/footer.php'; ?>
