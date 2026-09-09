<?php
/**
 * Rend un champ du schéma d'édition.
 * @var array  $field  définition (path, label, type, hint…)
 * @var mixed  $value  valeur courante
 * @var string $name   préfixe de nom HTML, ex. f[hero][title1]
 * @var array  $media  photothèque, pour les champs de type media
 */

use App\Config;
use App\Text;
use App\View;

$type = (string) ($field['type'] ?? 'text');
$label = (string) ($field['label'] ?? '');
$hint = (string) ($field['hint'] ?? '');
$id = 'f-' . preg_replace('/[^a-z0-9]+/i', '-', $name);
$basePath = Config::basePath();
?>
<div style="margin-top:18px">
  <label class="label" for="<?= Text::e($id) ?>"><?= Text::e(mb_strtoupper($label)) ?></label>

  <?php if ($type === 'text'): ?>
    <input class="field" id="<?= Text::e($id) ?>" type="text" name="<?= Text::e($name) ?>" value="<?= Text::e(\is_scalar($value) ? (string) $value : '') ?>">

  <?php elseif ($type === 'color'): ?>
    <div style="display:flex;gap:10px;align-items:center">
      <input class="field" id="<?= Text::e($id) ?>" type="text" name="<?= Text::e($name) ?>" value="<?= Text::e(\is_scalar($value) ? (string) $value : '') ?>" style="max-width:160px" data-color-text>
      <input type="color" value="<?= Text::e(preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $value) === 1 ? (string) $value : '#FFD100') ?>" data-color-picker aria-label="Choisir la couleur" style="width:52px;height:52px;border:2px solid #0E0E0E;border-radius:12px;background:none;cursor:pointer;padding:2px">
    </div>

  <?php elseif ($type === 'textarea'): ?>
    <textarea class="field" id="<?= Text::e($id) ?>" name="<?= Text::e($name) ?>" rows="<?= (int) ($field['rows'] ?? 3) ?>"><?= Text::e(\is_scalar($value) ? (string) $value : '') ?></textarea>

  <?php elseif ($type === 'rich'): ?>
    <?= View::admin('_wysiwyg', ['name' => $name, 'value' => \is_scalar($value) ? (string) $value : '', 'id' => $id]) ?>

  <?php elseif ($type === 'list'): ?>
    <textarea class="field" id="<?= Text::e($id) ?>" name="<?= Text::e($name) ?>" rows="<?= max(3, \count((array) $value) + 1) ?>"><?= Text::e(implode("\n", array_map('strval', (array) $value))) ?></textarea>
    <div class="hint">Un élément par ligne. Une ligne vide est ignorée.</div>

  <?php elseif ($type === 'media'):
      $paths = \is_array($value) ? array_values(array_filter(array_map('strval', $value))) : array_values(array_filter([(string) $value]));
      $max = (int) ($field['max'] ?? 0); ?>
    <input type="hidden" name="<?= Text::e($name) ?>" value="<?= Text::e(implode(',', $paths)) ?>" data-media-value data-media-max="<?= $max ?>">
    <div class="media-picker" data-media-picker>
      <?php foreach ((array) ($media ?? []) as $item):
          $path = (string) ($item['path'] ?? '');
          $position = array_search($path, $paths, true); ?>
        <button class="media-pick<?= $position !== false ? ' is-picked' : '' ?>" type="button" data-media-path="<?= Text::e($path) ?>" title="<?= Text::e((string) ($item['name'] ?? '')) ?>">
          <img src="<?= Text::e($basePath . $path) ?>" alt="" loading="lazy">
          <?php if ($position !== false): ?><span class="media-pick__n"><?= (int) $position + 1 ?></span><?php endif; ?>
        </button>
      <?php endforeach; ?>
    </div>
    <div class="hint">Cliquez pour sélectionner ou retirer. <?= $max === 1 ? 'Une seule photo.' : 'L’ordre de sélection est l’ordre d’affichage.' ?></div>

  <?php elseif ($type === 'repeat'):
      $rows = \is_array($value) ? array_values($value) : [];
      $rows[] = []; // une ligne vide toujours disponible pour ajouter un élément
      ?>
    <div class="hint" style="margin-bottom:10px">Videz tous les champs d’un bloc pour le supprimer. Le bloc vide en bas sert à en ajouter un.</div>
    <?php foreach ($rows as $index => $row): ?>
      <div class="repeat-item">
        <div class="repeat-item__head">
          <span class="repeat-item__title"><?= Text::e(mb_strtoupper($label)) ?> #<?= $index + 1 ?><?= $row === [] ? ' — NOUVEAU' : '' ?></span>
        </div>
        <?php foreach ((array) ($field['fields'] ?? []) as $sub):
            $subPath = (string) $sub['path'];
            $subValue = $row;
            foreach (explode('.', $subPath) as $key) {
                $subValue = \is_array($subValue) && \array_key_exists($key, $subValue) ? $subValue[$key] : null;
            }
            $subName = $name . '[' . $index . ']' . implode('', array_map(static fn (string $k): string => '[' . $k . ']', explode('.', $subPath)));
            echo View::admin('_field', ['field' => $sub, 'value' => $subValue, 'name' => $subName, 'media' => $media ?? []]);
        endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($hint !== '' && $type !== 'media' && $type !== 'list'): ?>
    <div class="hint"><?= Text::e($hint) ?></div>
  <?php endif; ?>
</div>
