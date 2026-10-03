<?php
/** Éditeur générique d'une collection. Variables : $name, $schema, $data, $isDefault, $versions */
use App\Admin\Base;
use App\Admin\Form;

/** Champ d'un élément selon le schéma ($p : préfixe « @ » pour les éléments de liste, « data. » pour un objet). */
$field = function (string $p, string $k, array $spec, array $item) {
    [$label, $type, $o] = $spec;
    $v = $item[$k] ?? ($o['default'] ?? null);
    $cls = !empty($o['full']) ? 'f--full' : '';
    $html = match ($type) {
        'int' => Form::number($p . $k, $label, $v, ['min' => $o['min'] ?? null, 'max' => $o['max'] ?? null, 'class' => $cls]),
        'bool' => Form::toggle($p . $k, $label, (bool) ($item[$k] ?? ($o['default'] ?? false))),
        'select' => Form::select($p . $k, $label, (string) ($v ?? ''), $o['options'], ['strict' => true]),
        'image' => Form::image($p . $k, $label, is_string($v) ? $v : null),
        'href' => Form::text($p . $k, $label, (string) ($v ?? ''), ['placeholder' => 'Tapez le titre d’une fiche ou /adresse/', 'ac' => 'fiches', 'data' => ['ac-value' => 'url'], 'class' => $cls]),
        'latlng' => '<div class="f"><span class="f__k">' . e($label) . ' <i>latitude, longitude</i></span><div class="row" style="flex-wrap:nowrap;gap:6px">'
            . '<input type="text" inputmode="decimal"' . (str_starts_with($p, '@') ? ' data-field="' . e(substr($p, 1) . $k) . '.lat"' : ' name="' . e($p . $k) . '.lat"') . ' value="' . e((string) ($v[0] ?? '')) . '" placeholder="47.5122" aria-label="Latitude">'
            . '<input type="text" inputmode="decimal"' . (str_starts_with($p, '@') ? ' data-field="' . e(substr($p, 1) . $k) . '.lng"' : ' name="' . e($p . $k) . '.lng"') . ' value="' . e((string) ($v[1] ?? '')) . '" placeholder="6.8111" aria-label="Longitude"></div>'
            . (!empty($v[0]) ? '<a class="xs" href="https://www.openstreetmap.org/?mlat=' . e((string) $v[0]) . '&amp;mlon=' . e((string) $v[1]) . '#map=14/' . e((string) $v[0]) . '/' . e((string) $v[1]) . '" target="_blank" rel="noopener">Vérifier sur OpenStreetMap ↗</a>' : '') . '</div>',
        'answers' => '<div class="f f--full"><span class="f__k">' . e($label) . '</span><div class="fgrid">'
            . implode('', array_map(fn ($i) => '<input type="text" maxlength="120"' . (str_starts_with($p, '@') ? ' data-field="' . e(substr($p, 1) . $k) . '.' . $i . '"' : ' name="' . e($p . $k) . '.' . $i . '"') . ' value="' . e((string) (($v ?? [])[$i] ?? '')) . '" placeholder="Réponse ' . chr(65 + $i) . '" aria-label="Réponse ' . chr(65 + $i) . '">', range(0, 3))) . '</div></div>',
        default => Form::text($p . $k, $label, (string) ($v ?? ''), ['maxlength' => $o['max'] ?? 300, 'placeholder' => $o['placeholder'] ?? '', 'class' => $cls, 'proof' => !empty($o['en']) || !empty($o['proof'])]),
    };
    if (!empty($o['en'])) {
        $ve = $item[$k . '_en'] ?? null;
        $html .= $type === 'answers'
            ? '<div class="f f--full"><span class="f__k">' . e($label) . ' (EN)</span><div class="fgrid">' . implode('', array_map(fn ($i) => '<input type="text" maxlength="120"' . (str_starts_with($p, '@') ? ' data-field="' . e(substr($p, 1) . $k) . '_en.' . $i . '"' : ' name="' . e($p . $k) . '_en.' . $i . '"') . ' value="' . e((string) (($ve ?? [])[$i] ?? '')) . '" aria-label="Réponse ' . chr(65 + $i) . ' en anglais">', range(0, 3))) . '</div></div>'
            : Form::text($p . $k . '_en', $label . ' (EN)', (string) ($ve ?? ''), ['maxlength' => $o['max'] ?? 300, 'class' => $cls, 'proof' => true]);
    }
    return $html;
};
/** Bouton de traduction des champs « en » d'un élément. */
$trButton = function (array $fields, string $p = '@'): string {
    $pairs = [];
    $pre = $p === '@' ? '' : $p;
    foreach ($fields as $k => [, $type, $o]) {
        if (empty($o['en'])) {
            continue;
        }
        if ($type === 'answers') {
            foreach (range(0, 3) as $i) {
                $pairs[] = "$pre$k.$i>$pre{$k}_en.$i";
            }
        } else {
            $pairs[] = $pre . $k;
        }
    }
    return $pairs ? '<div class="f" style="justify-content:flex-end"><button type="button" class="btn btn--sm btn--ghost" data-tr="' . e(implode(',', $pairs)) . '">Traduire en anglais</button></div>' : '';
};
$renderItem = fn (array $fields, string $p = '@') => function ($it) use ($fields, $field, $trButton, $p) {
    $it = is_array($it) ? $it : [];
    $h = '<div class="fgrid">';
    foreach ($fields as $k => $spec) {
        $h .= $field($p, $k, $spec, $it);
    }
    return $h . $trButton($fields, $p) . '</div>';
};
?>
<form class="stack" data-json-form data-url="/admin/collection/<?= e($name) ?>" novalidate>
  <div class="toolbar">
    <p class="small muted grow" style="margin:0"><?= e($schema['help'] ?? '') ?></p>
    <?php if (!empty($schema['front'])): ?><a class="btn" href="<?= e($schema['front']) ?>" target="_blank" rel="noopener">Voir sur le site ↗</a><?php endif; ?>
    <?php if (empty($schema['no_tr'])): ?><button type="button" class="btn" data-tr-all>Traduire tout ce qui manque (EN)</button><?php endif; ?>
    <?php if (empty($schema['no_proof'])): ?><button type="button" class="btn" data-proofread>Vérifier l’orthographe</button><?php endif; ?>
    <button type="submit" class="btn btn--navy" data-save>Enregistrer</button>
    <span class="small muted" data-saved></span>
  </div>
  <?php if ($isDefault && $data): ?><p class="alert" style="margin:0">Contenu proposé au lancement du site, <b>à relire et valider</b> par un historien. Il devient le vôtre dès le premier enregistrement.</p><?php endif; ?>

  <?php if (!empty($schema['object'])): ?>
    <div class="card card--pad">
      <h2 class="card__t">Textes</h2>
      <?= $renderItem($schema['fields'], 'data.')($data) ?>
    </div>
    <?php foreach ($schema['lists'] ?? [] as $key => [$label, $item, $fields]): ?>
      <div class="card card--pad">
        <h2 class="card__t"><?= e($label) ?></h2>
        <?= Form::repeater('data.' . $key, '', $data[$key] ?? [], $renderItem($fields), ['add' => 'Ajouter : ' . mb_strtolower($item), 'numbered' => true]) ?>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <?= Form::repeater('items', '', $data, $renderItem($schema['fields']), ['add' => 'Ajouter : ' . mb_strtolower($schema['item']), 'numbered' => true, 'dup' => true, 'blank' => array_map(fn ($f) => $f[2]['default'] ?? null, $schema['fields'])]) ?>
  <?php endif; ?>

  <div class="row"><button type="submit" class="btn btn--navy" data-save>Enregistrer</button><span class="small muted" data-saved></span></div>
</form>
<?php if ($versions): ?>
  <div class="card">
    <div class="card__head"><h2 class="card__t card__t--sm">Dernières modifications</h2></div>
    <?php foreach ($versions as $v): ?><div class="card__row" style="grid-template-columns:60px minmax(0,1fr) auto"><span class="vers__n">v<?= (int) $v['n'] ?></span><span><?= e($v['by']) ?> · <?= e($v['message']) ?></span><span class="xs muted"><?= e(Base::ago($v['at'])) ?></span></div><?php endforeach; ?>
  </div>
<?php endif; ?>
