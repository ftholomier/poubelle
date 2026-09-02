<?php

use App\View;

/**
 * Rend un champ d'après le schéma de la section.
 *
 * @var string              $name   nom du champ
 * @var array<string,mixed> $field  description issue de SectionSchema
 * @var mixed               $value  valeur actuelle
 * @var string              $prefix préfixe du nom HTML, pour les listes imbriquées
 */

$prefix = $prefix ?? 'champ';
$htmlName = $prefix . '[' . $name . ']';
$id = preg_replace('/[^a-z0-9]+/i', '-', $htmlName);
$label = $field['label'] ?? $name;
$hint = $field['hint'] ?? null;
?>

<?php if ($field['type'] === 'repeater'): ?>
    <fieldset class="repeater" data-repeater>
        <legend class="field__label"><?= View::e($label) ?></legend>

        <div class="repeater__items" data-repeater-items>
            <?php foreach (((array) ($value ?? [])) as $index => $entry): ?>
                <div class="repeater__item" data-repeater-item>
                    <div class="repeater__head">
                        <span class="repeater__rank"></span>
                        <button type="button" class="repeater__remove" data-repeater-remove
                                aria-label="Supprimer cette <?= View::e($field['single'] ?? 'entrée') ?>">Supprimer</button>
                    </div>
                    <?php foreach ($field['fields'] as $subName => $subField): ?>
                        <?= View::capture('admin/partials/field', [
                            'name'   => $subName,
                            'field'  => $subField,
                            'value'  => $entry[$subName] ?? null,
                            'prefix' => $htmlName . '[' . $index . ']',
                        ]) ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php /* Modèle cloné par le script pour ajouter une entrée. L'indice
                 « __i__ » est remplacé au moment du clonage. */ ?>
        <template data-repeater-template>
            <div class="repeater__item" data-repeater-item>
                <div class="repeater__head">
                    <span class="repeater__rank"></span>
                    <button type="button" class="repeater__remove" data-repeater-remove
                            aria-label="Supprimer cette <?= View::e($field['single'] ?? 'entrée') ?>">Supprimer</button>
                </div>
                <?php foreach ($field['fields'] as $subName => $subField): ?>
                    <?= View::capture('admin/partials/field', [
                        'name'   => $subName,
                        'field'  => $subField,
                        'value'  => null,
                        'prefix' => $htmlName . '[__i__]',
                    ]) ?>
                <?php endforeach; ?>
            </div>
        </template>

        <button type="button" class="button button--small" data-repeater-add>
            Ajouter une <?= View::e($field['single'] ?? 'entrée') ?>
        </button>
    </fieldset>

<?php elseif ($field['type'] === 'lines'): ?>
    <label class="field">
        <span class="field__label"><?= View::e($label) ?></span>
        <textarea name="<?= View::e($htmlName) ?>" id="<?= View::e($id) ?>" rows="<?= max(2, count((array) $value) + 1) ?>"
        ><?= View::e(implode("\n", (array) ($value ?? []))) ?></textarea>
        <?php if ($hint): ?><span class="field__hint"><?= View::e($hint) ?></span><?php endif; ?>
    </label>

<?php elseif ($field['type'] === 'textarea'): ?>
    <label class="field">
        <span class="field__label"><?= View::e($label) ?></span>
        <textarea name="<?= View::e($htmlName) ?>" id="<?= View::e($id) ?>" rows="3"><?= View::e((string) ($value ?? '')) ?></textarea>
        <?php if ($hint): ?><span class="field__hint"><?= View::e($hint) ?></span><?php endif; ?>
    </label>

<?php elseif ($field['type'] === 'number'): ?>
    <label class="field field--narrow">
        <span class="field__label"><?= View::e($label) ?></span>
        <input
            type="number"
            name="<?= View::e($htmlName) ?>"
            id="<?= View::e($id) ?>"
            value="<?= $value === null ? '' : View::e((string) $value) ?>"
            <?= isset($field['min']) ? 'min="' . View::e((string) $field['min']) . '"' : '' ?>
            <?= isset($field['max']) ? 'max="' . View::e((string) $field['max']) . '"' : '' ?>
            step="<?= View::e((string) ($field['step'] ?? 1)) ?>">
        <?php if ($hint): ?><span class="field__hint"><?= View::e($hint) ?></span><?php endif; ?>
    </label>

<?php else: ?>
    <label class="field">
        <span class="field__label"><?= View::e($label) ?></span>
        <input type="text" name="<?= View::e($htmlName) ?>" id="<?= View::e($id) ?>"
               value="<?= View::e((string) ($value ?? '')) ?>">
        <?php if ($hint): ?><span class="field__hint"><?= View::e($hint) ?></span><?php endif; ?>
    </label>
<?php endif; ?>
