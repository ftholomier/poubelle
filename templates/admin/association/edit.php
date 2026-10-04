<?php
/** Éditeur d'un contenu du site de l'association (liste ou objet). Variables : $name, $schema, $data, $isDefault, $versions */
use App\Admin\Association;
use App\Admin\Base;
use App\Admin\Form;

$render = fn (array $fields, string $p = '@') => function ($it) use ($fields, $p) {
    $it = is_array($it) ? $it : [];
    $h = '<div class="fgrid">';
    foreach ($fields as $k => $spec) {
        $h .= Association::field($p, $k, $spec, $it);
    }
    return $h . '</div>';
};
$title = $schema['title'] ?? 'title';
?>
<form class="stack" data-json-form data-url="/admin/association/contenus/<?= e($name) ?>" data-lock="ecran:asso-<?= e($name) ?>" data-lock-what="ce contenu" novalidate>
  <div class="toolbar">
    <p class="small muted grow" style="margin:0"><?= e($schema['help'] ?? '') ?></p>
    <?php if (!empty($schema['front'])): ?><a class="btn" href="/apercu-association<?= e($schema['front']) ?>" target="_blank" rel="noopener">Aperçu ↗</a><?php endif; ?>
    <button type="button" class="btn" data-proofread>Vérifier l’orthographe</button>
    <button type="submit" class="btn btn--navy" data-save>Enregistrer</button>
    <span class="small muted" data-saved></span>
  </div>
  <?php if ($isDefault): ?><p class="alert" style="margin:0">Contenu livré avec le site (mis à jour avec le code). Il devient le vôtre dès le premier enregistrement.</p><?php endif; ?>

  <?php if (!empty($schema['object'])): ?>
    <?php if ($schema['fields']): ?>
      <div class="card card--pad"><?= $render($schema['fields'], 'data.')($data) ?></div>
    <?php endif; ?>
    <?php foreach ($schema['lists'] ?? [] as $key => [$label, $item, $fields]): ?>
      <div class="card card--pad">
        <h2 class="card__t"><?= e($label) ?></h2>
        <?= Form::repeater('data.' . $key, '', $data[$key] ?? [], $render($fields), ['add' => 'Ajouter : ' . mb_strtolower($item), 'numbered' => true, 'dup' => true]) ?>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <?= Form::repeater('items', '', $data, $render($schema['fields']), ['add' => 'Ajouter : ' . mb_strtolower($schema['item']), 'numbered' => true, 'dup' => true, 'blank' => array_map(fn ($f) => $f[2]['default'] ?? null, $schema['fields'])]) ?>
  <?php endif; ?>

  <div class="row"><button type="submit" class="btn btn--navy" data-save>Enregistrer</button><span class="small muted" data-saved></span></div>
</form>
<?php if (!$isDefault): ?>
  <form method="post" action="/admin/association/contenus/<?= e($name) ?>/depart" data-confirm="Revenir au contenu de départ ?|Le contenu livré avec le site remplace le vôtre (votre version reste dans l’historique ci-dessous).|Revenir au départ|danger" style="margin:0">
    <?= csrf_field() ?><button type="submit" class="btn btn--sm btn--ghost">Revenir au contenu de départ</button>
  </form>
<?php endif; ?>
<?php if ($versions): ?>
  <div class="card">
    <div class="card__head"><h2 class="card__t card__t--sm">Dernières modifications</h2></div>
    <?php foreach ($versions as $v): ?><div class="card__row" style="grid-template-columns:60px minmax(0,1fr) auto"><span class="vers__n">v<?= (int) $v['n'] ?></span><span><?= e($v['by']) ?> · <?= e($v['message']) ?></span><span class="xs muted"><?= e(Base::ago($v['at'])) ?></span></div><?php endforeach; ?>
  </div>
<?php endif; ?>
