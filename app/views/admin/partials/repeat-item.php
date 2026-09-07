<?php
/**
 * Une ligne d'un champ répétable. Les clés proposées dépendent du type de liste.
 * @var array $row @var int|string $index @var string $name @var string $key
 * @var array $languages @var array $icons
 */

use App\Core\View;

$field = static fn (string $sub): string => $name . '[' . $index . '][' . $sub . ']';

$sets = [
    'badges'  => ['label'],
    'bullets' => ['text'],
];
$fields = $sets[$key] ?? null;

if ($fields === null) {
    // Liste « items » : on déduit les champs utiles des données présentes,
    // sinon on propose le jeu complet le plus courant.
    $fields = match (true) {
        array_key_exists('question', $row) => ['question', 'answer'],
        array_key_exists('value', $row)    => ['value', 'prefix', 'suffix', 'label'],
        array_key_exists('image', $row)    => ['image', 'alt'],
        array_key_exists('icon', $row), array_key_exists('title', $row) => ['icon', 'title', 'text', 'url', 'link_label'],
        default => ['icon', 'title', 'text', 'url', 'link_label'],
    };
}

$labels = [
    'icon' => 'Icône', 'title' => 'Titre', 'text' => 'Texte', 'url' => 'Lien',
    'link_label' => 'Libellé du lien', 'value' => 'Valeur (nombre)', 'prefix' => 'Préfixe',
    'suffix' => 'Suffixe', 'label' => 'Libellé', 'question' => 'Question', 'answer' => 'Réponse',
    'image' => 'Image (URL)', 'alt' => 'Texte alternatif',
];
$multilang = ['title', 'text', 'label', 'question', 'answer', 'link_label'];
?>
<div class="ad-repeat__item">
    <button type="button" class="ad-repeat__remove" data-repeat-remove aria-label="Supprimer cet élément">
        <?= icon('close', '', 15) ?>
    </button>

    <?php foreach ($fields as $sub):
        $label = $labels[$sub] ?? ucfirst($sub);
        $value = $row[$sub] ?? ($multilang && in_array($sub, $multilang, true) ? [] : '');

        if (in_array($sub, $multilang, true)) {
            echo View::render('admin/partials/multilang', [
                'name' => $field($sub), 'value' => $value, 'label' => $label,
                'languages' => $languages,
                'type' => $sub === 'answer' ? 'wysiwyg' : ($sub === 'text' ? 'textarea' : 'text'),
                'rows' => 2,
            ]);
            continue;
        }
        if ($sub === 'icon'): ?>
            <div class="ad-field">
                <label class="ad-label"><?= e($label) ?></label>
                <select class="ad-input" name="<?= e($field($sub)) ?>">
                    <option value="">— aucune —</option>
                    <?php foreach ($icons as $iconName): ?>
                        <option value="<?= e($iconName) ?>"<?= (string) $value === $iconName ? ' selected' : '' ?>><?= e($iconName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php elseif ($sub === 'value'): ?>
            <div class="ad-field">
                <label class="ad-label"><?= e($label) ?></label>
                <input class="ad-input" type="number" name="<?= e($field($sub)) ?>" value="<?= e((string) $value) ?>">
            </div>
        <?php else: ?>
            <div class="ad-field">
                <label class="ad-label"><?= e($label) ?></label>
                <input class="ad-input" type="text" name="<?= e($field($sub)) ?>" value="<?= e((string) $value) ?>">
            </div>
        <?php endif;
    endforeach; ?>
</div>
