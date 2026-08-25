<?php
/**
 * Éditeur de contenu généré à partir de ContentSchema.
 * @var array $schema @var string $section @var array $spec @var mixed $value
 */

/** Rendu récursif d'un champ. */
function render_field(string $name, array $spec, mixed $val, string $idPrefix = 'f'): void
{
    $type = $spec['type'] ?? 'text';
    $id = $idPrefix . '-' . preg_replace('/[^a-z0-9]/i', '-', $name);
    $label = $spec['label'] ?? $name;
    $help = $spec['help'] ?? '';

    if ($type === 'list') {
        $rows = is_array($val) ? $val : [];
        echo '<div class="field"><label>' . e($label) . '</label>';
        if ($help) { echo '<small class="help">' . e($help) . '</small>'; }
        echo '<div class="repeat" data-repeat data-name="' . e($name) . '">';
        foreach ($rows as $i => $row) {
            render_repeat_item($name, $spec['item'], is_array($row) ? $row : [], $i);
        }
        echo '</div>';
        echo '<template data-repeat-template>';
        render_repeat_item($name, $spec['item'], [], '__INDEX__');
        echo '</template>';
        echo '<button class="btn btn--ghost btn--sm" type="button" data-repeat-add style="margin-top:10px;justify-self:start">+ Ajouter un élément</button>';
        echo '</div>';
        return;
    }

    if ($type === 'fields') {
        echo '<fieldset style="border:1px solid var(--line);border-radius:14px;padding:16px;margin-bottom:16px">';
        echo '<legend style="padding:0 8px;font-size:.8rem;color:var(--muted)">' . e($label) . '</legend>';
        foreach ($spec['item'] as $k => $sub) {
            render_field($name . '[' . $k . ']', $sub, is_array($val) ? ($val[$k] ?? null) : null, $id . '-' . $k);
        }
        echo '</fieldset>';
        return;
    }

    echo '<div class="field"><label for="' . e($id) . '">' . e($label) . '</label>';
    if ($help) { echo '<small class="help">' . e($help) . '</small>'; }

    switch ($type) {
        case 'textarea':
            echo '<textarea class="textarea" id="' . e($id) . '" name="' . e($name) . '" rows="3">' . e((string) $val) . '</textarea>';
            break;
        case 'number':
            echo '<input class="input" type="number" step="' . e($spec['step'] ?? '1') . '" id="' . e($id) . '" name="' . e($name) . '" value="' . e((string) ($val ?? 0)) . '">';
            break;
        case 'tags':
            echo '<small class="help">Une valeur par ligne.</small>';
            echo '<textarea class="textarea" id="' . e($id) . '" name="' . e($name) . '" rows="4">' . e(implode("\n", is_array($val) ? $val : [])) . '</textarea>';
            break;
        case 'textlist':
            echo '<small class="help">Séparez les paragraphes par une ligne vide.</small>';
            echo '<textarea class="textarea" id="' . e($id) . '" name="' . e($name) . '" rows="8">' . e(implode("\n\n", is_array($val) ? $val : [])) . '</textarea>';
            break;
        case 'select':
            echo '<select class="select" id="' . e($id) . '" name="' . e($name) . '">';
            foreach ($spec['options'] ?? [] as $opt) {
                echo '<option' . ((string) $val === (string) $opt ? ' selected' : '') . '>' . e($opt) . '</option>';
            }
            echo '</select>';
            break;
        default:
            echo '<input class="input" type="text" id="' . e($id) . '" name="' . e($name) . '" value="' . e((string) $val) . '">';
    }
    echo '</div>';
}

function render_repeat_item(string $name, array $itemSpec, array $row, int|string $index): void
{
    echo '<div class="repeat__item" data-repeat-item>';
    echo '<span class="repeat__n">Élément</span>';
    echo '<span class="repeat__drop">';
    echo '<button class="repeat__btn" type="button" data-repeat-up title="Monter">↑</button>';
    echo '<button class="repeat__btn" type="button" data-repeat-down title="Descendre">↓</button>';
    echo '<button class="repeat__btn" type="button" data-repeat-remove title="Supprimer">✕</button>';
    echo '</span>';
    foreach ($itemSpec as $k => $sub) {
        render_field($name . '[' . $index . '][' . $k . ']', $sub, $row[$k] ?? null, 'r' . $index . '-' . $k);
    }
    echo '</div>';
}
?>

<div class="topbar">
  <h1>Contenu du site</h1>
  <div class="row">
    <a class="btn btn--sm btn--ghost" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><?= icon('arrow-up-right') ?> Voir le site</a>
  </div>
</div>

<div class="editor">
  <nav class="editor__nav">
    <?php foreach ($schema as $key => $s): ?>
      <a class="<?= $key === $section ? 'is-active' : '' ?>" href="<?= e(url('admin/contenu/' . $key)) ?>"><?= e($s['label']) ?></a>
    <?php endforeach; ?>
  </nav>

  <form class="panel" method="post" data-dirty-guard>
    <?= Csrf::field() ?>
    <div class="panel__head">
      <h2><?= e($spec['label']) ?></h2>
      <button class="btn btn--sm" type="submit">Enregistrer</button>
    </div>
    <?php if (!empty($spec['help'])): ?>
      <p style="font-size:.86rem;color:var(--muted);margin-bottom:18px"><?= e($spec['help']) ?></p>
    <?php endif; ?>

    <?php
    if (isset($spec['root'])) {
        render_field('data', $spec['root'], $value);
    } else {
        foreach ($spec['fields'] as $key => $fieldSpec) {
            render_field('data[' . $key . ']', $fieldSpec, is_array($value) ? ($value[$key] ?? null) : null, 'f-' . $key);
        }
    }
    ?>

    <div class="row" style="margin-top:22px;padding-top:18px;border-top:1px solid var(--line)">
      <button class="btn" type="submit">Enregistrer les modifications</button>
      <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/contenu/' . $section)) ?>">Annuler</a>
    </div>
  </form>
</div>
