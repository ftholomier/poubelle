<?php
/**
 * Rendu générique des champs d'un bloc, piloté par son schéma.
 * Ajouter une clé au catalogue suffit : le formulaire suit automatiquement.
 *
 * @var array  $schema     schéma du type de bloc
 * @var array  $data       valeurs courantes
 * @var string $prefix     préfixe de nom (ex. blocks[0][data])
 * @var array  $languages
 * @var array  $icons
 */

use App\Core\View;

$labels = [
    'eyebrow' => 'Sur-titre', 'title' => 'Titre', 'highlight' => 'Mot mis en avant',
    'text' => 'Texte', 'html' => 'Contenu', 'items' => 'Éléments', 'badges' => 'Étiquettes',
    'bullets' => 'Points clés', 'columns' => 'Colonnes', 'limit' => 'Nombre affiché',
    'primary_label' => 'Bouton principal — libellé', 'primary_url' => 'Bouton principal — lien',
    'secondary_label' => 'Bouton secondaire — libellé', 'secondary_url' => 'Bouton secondaire — lien',
    'cta_label' => 'Bouton — libellé', 'cta_url' => 'Bouton — lien',
    'link_label' => 'Libellé du lien', 'url' => 'Lien', 'image' => 'Image (URL)',
    'image_alt' => 'Texte alternatif de l’image', 'variant' => 'Variante', 'side' => 'Côté de l’image',
    'narrow' => 'Colonne étroite', 'show_map' => 'Afficher la carte', 'show_info' => 'Afficher les coordonnées',
    'icon' => 'Icône', 'value' => 'Valeur', 'prefix' => 'Préfixe', 'suffix' => 'Suffixe',
    'label' => 'Libellé', 'question' => 'Question', 'answer' => 'Réponse', 'alt' => 'Texte alternatif',
];

$choices = [
    'variant' => ['split' => 'Deux colonnes', 'centered' => 'Centré', 'dark' => 'Sombre', 'accent' => 'Accent', 'light' => 'Clair'],
    'side'    => ['right' => 'Image à gauche, texte à droite', 'left' => 'Image à droite, texte à gauche'],
    'columns' => [2 => '2 colonnes', 3 => '3 colonnes'],
];

/** Un champ multilingue se reconnaît à son schéma { fr: '' }. */
$isMultilang = static fn ($default): bool => is_array($default) && isset($default['fr']) && is_string($default['fr']);

foreach ($schema as $key => $default):
    $name  = $prefix . '[' . $key . ']';
    $label = $labels[$key] ?? ucfirst(str_replace('_', ' ', (string) $key));
    $value = $data[$key] ?? $default;

    /* ---------------------------------------------------- Multilingue */
    if ($isMultilang($default)):
        $type = str_contains((string) $key, 'html') || $key === 'answer'
            ? 'wysiwyg'
            : (in_array($key, ['text', 'excerpt'], true) ? 'textarea' : 'text');
        echo View::render('admin/partials/multilang', [
            'name' => $name, 'value' => $value, 'label' => $label,
            'languages' => $languages, 'type' => $type,
        ]);
        continue;
    endif;

    /* ------------------------------------------------ Liste répétable */
    if (is_array($default) && App\Core\Schema::isList($default)):
        $containerId = 'rep-' . substr(md5($name), 0, 10);
        ?>
        <div class="ad-field">
            <label class="ad-label"><?= e($label) ?></label>
            <div class="ad-repeat" id="<?= e($containerId) ?>">
                <?php
                $rows = is_array($value) ? array_values($value) : [];
                foreach ($rows as $i => $row):
                    echo View::render('admin/partials/repeat-item', [
                        'row' => is_array($row) ? $row : [],
                        'index' => $i,
                        'name' => $name,
                        'key' => $key,
                        'languages' => $languages,
                        'icons' => $icons,
                    ]);
                endforeach; ?>

                <template>
                    <?= View::render('admin/partials/repeat-item', [
                        'row' => [], 'index' => '__INDEX__', 'name' => $name,
                        'key' => $key, 'languages' => $languages, 'icons' => $icons,
                    ]) ?>
                </template>
            </div>
            <button type="button" class="ad-btn ad-btn--ghost ad-btn--sm ad-mt"
                    data-repeat-add="<?= e($containerId) ?>">+ Ajouter</button>
        </div>
        <?php
        continue;
    endif;

    /* ----------------------------------------------------- Scalaires */
    if (is_bool($default)): ?>
        <div class="ad-field">
            <label class="ad-switch">
                <input type="hidden" name="<?= e($name) ?>" value="0">
                <input type="checkbox" name="<?= e($name) ?>" value="1"<?= !empty($value) ? ' checked' : '' ?>>
                <span class="ad-switch__track"></span>
                <span><?= e($label) ?></span>
            </label>
        </div>
    <?php elseif (isset($choices[$key])): ?>
        <div class="ad-field">
            <?php $selectId = 'f' . substr(md5($name), 0, 8); ?>
            <label class="ad-label" for="<?= e($selectId) ?>"><?= e($label) ?></label>
            <select class="ad-input" id="<?= e($selectId) ?>" name="<?= e($name) ?>">
                <?php foreach ($choices[$key] as $option => $optionLabel): ?>
                    <option value="<?= e((string) $option) ?>"<?= (string) $value === (string) $option ? ' selected' : '' ?>>
                        <?= e($optionLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php elseif ($key === 'icon'): ?>
        <div class="ad-field">
            <label class="ad-label"><?= e($label) ?></label>
            <select class="ad-input" name="<?= e($name) ?>">
                <option value="">— aucune —</option>
                <?php foreach ($icons as $iconName): ?>
                    <option value="<?= e($iconName) ?>"<?= (string) $value === $iconName ? ' selected' : '' ?>><?= e($iconName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php elseif (is_int($default)): ?>
        <div class="ad-field">
            <label class="ad-label"><?= e($label) ?></label>
            <input class="ad-input" type="number" name="<?= e($name) ?>" value="<?= e((string) $value) ?>">
        </div>
    <?php else: ?>
        <div class="ad-field">
            <label class="ad-label"><?= e($label) ?></label>
            <input class="ad-input" type="text" name="<?= e($name) ?>" value="<?= e((string) $value) ?>"
                <?= str_contains((string) $key, 'url') ? ' placeholder="/contact ou https://…"' : '' ?>
                <?= str_contains((string) $key, 'image') ? ' placeholder="/media/img/… (voir Médias)"' : '' ?>>
        </div>
    <?php endif;
endforeach;
