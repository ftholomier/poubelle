<?php
/** Classement (rubriques, à la une), adresse et référencement. Variables : $doc */
use App\Admin\Fiches;
use App\Admin\Form;
use App\Data\Categories;

$opts = Fiches::catOptions();
$sel = $doc['categories'] ?? [];
$roots = [];
foreach ($opts as $slug => $label) {
    $roots[explode(' › ', $label)[0]][$slug] = $label;
}
$site = parse_url(base_url(), PHP_URL_HOST) ?: 'fcsochauxretro.com';
?>
<div class="fpanel" data-panel="seo">
  <div class="card card--pad">
    <h2 class="card__t">Rubriques</h2>
    <p class="small muted" style="margin:0">La fiche apparaît dans les mosaïques des rubriques cochées (et dans leurs rubriques parentes).</p>
    <?php foreach ($roots as $root => $items): ?>
      <details<?= array_intersect(array_keys($items), $sel) ? ' open' : '' ?>>
        <summary class="d" style="font-weight:800;text-transform:uppercase;cursor:pointer;padding:4px 0"><?= e($root) ?> <span class="xs muted">(<?= count(array_intersect(array_keys($items), $sel)) ?> cochée<?= count(array_intersect(array_keys($items), $sel)) > 1 ? 's' : '' ?>)</span></summary>
        <div class="checklist" style="margin:8px 0 6px">
          <?php foreach ($items as $slug => $label): ?>
            <label><input type="checkbox" name="categories" data-multi data-value="<?= e($slug) ?>"<?= in_array($slug, $sel, true) ? ' checked' : '' ?>> <?= e(mb_substr($label, mb_strlen($root) + 3) ?: $label) ?></label>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endforeach; ?>
    <input type="hidden" name="categories__present" value="1">
    <?= Form::toggle('a_la_une', 'À la une (slider de l’accueil, tirage aléatoire)', !empty($doc['a_la_une'])) ?>
  </div>
  <div class="cols">
    <div class="card card--pad">
      <h2 class="card__t">Référencement</h2>
      <?= Form::text('seo.title', 'Titre (Google & partage)', $doc['seo']['title'] ?? '', ['count' => 60, 'placeholder' => $doc['title'] ?? '', 'class' => 'f--full', 'proof' => 'title']) ?>
      <?= Form::text('seo.description', 'Description', $doc['seo']['description'] ?? '', ['class' => 'f--full', 'proof' => true, 'maxlength' => 320, 'count' => 160, 'placeholder' => 'Résumé de la fiche en une ou deux phrases.']) ?>
      <?= Form::text('path', 'Adresse de la page', $doc['path'] ?? '', ['hint' => 'automatique', 'help' => 'Calculée à partir du titre tant que la fiche n’a jamais été publiée. Si vous la changez ensuite, l’ancienne adresse est redirigée automatiquement (301).', 'class' => 'f--full']) ?>
      <?= Form::text('date', 'Date de publication', !empty($doc['date']) ? date('Y-m-d\TH:i', strtotime($doc['date'])) : '', ['type' => 'datetime-local']) ?>
    </div>
    <div class="stack" style="gap:12px">
      <span class="f__k">Aperçu Google</span>
      <div class="serp"><div class="serp__url"><?= e($site) ?> › <?= e(trim(str_replace('/', ' › ', trim((string) ($doc['path'] ?? ''), '/')))) ?></div><div class="serp__t"><?= e(($doc['seo']['title'] ?: $doc['title']) ?: 'Titre de la fiche') ?> | Sochaux rétro</div><div class="serp__d"><?= e($doc['seo']['description'] ?: \App\Data\Index::summary($doc + ['id' => $doc['id'] ?: 0])['excerpt']) ?></div></div>
      <?php if (!empty($doc['id'])): ?>
        <span class="f__k">Image de partage générée</span>
        <img class="sharep" src="/partage/<?= (int) $doc['id'] ?>.png?v=<?= e(substr(md5((string) ($doc['modified'] ?? '')), 0, 6)) ?>" alt="Image de partage" loading="lazy">
      <?php endif; ?>
    </div>
  </div>
</div>
