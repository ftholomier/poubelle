<?php
/** Textes d'une page du site de l'association. Variables : $key, $label, $fields, $data, $isDefault, $versions, $front */
use App\Admin\Association;
use App\Admin\Base;
use App\Admin\Form;

$sub = fn (array $subFields) => function ($it) use ($subFields) {
    $it = is_array($it) ? $it : [];
    $h = '<div class="fgrid">';
    foreach ($subFields as $k => $spec) {
        $h .= Association::field('@', $k, $spec, $it);
    }
    return $h . '</div>';
};
?>
<form class="stack" data-json-form data-url="/admin/association/page/<?= e($key) ?>" data-lock="ecran:asso-page-<?= e($key) ?>" data-lock-what="cette page" novalidate>
  <div class="toolbar">
    <p class="small muted grow" style="margin:0"><a href="/admin/association/contenus/pages">← Toutes les pages</a></p>
    <a class="btn" href="/apercu-association<?= e($front) ?>" target="_blank" rel="noopener">Aperçu ↗</a>
    <button type="button" class="btn" data-proofread>Vérifier l’orthographe</button>
    <button type="submit" class="btn btn--navy" data-save>Enregistrer</button>
    <span class="small muted" data-saved></span>
  </div>
  <?php if ($isDefault): ?><p class="alert" style="margin:0">Texte livré avec le site (mis à jour avec le code). Il devient le vôtre dès le premier enregistrement.</p><?php endif; ?>
  <div class="card card--pad">
    <div class="fgrid">
      <?php foreach ($fields as $k => [$flabel, $type, $subFields]): if ($type === 'list' || $type === 'lines') continue; ?>
        <?= Association::field('data.', $k, [$flabel, $type, ['full' => true, 'max' => $type === 'long' ? 600 : 300]], $data) ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php foreach ($fields as $k => [$flabel, $type, $subFields]): if ($type !== 'list') continue; ?>
    <div class="card card--pad">
      <h2 class="card__t"><?= e($flabel) ?></h2>
      <?= Form::repeater('data.' . $k, '', $data[$k] ?? [], $sub($subFields), ['add' => 'Ajouter', 'numbered' => true]) ?>
    </div>
  <?php endforeach; ?>
  <?php if (isset($fields['a_verifier'])): ?>
    <div class="card card--pad" style="border-left:8px solid var(--yellow)">
      <?= Association::field('data.', 'a_verifier', $fields['a_verifier'], $data) ?>
      <p class="xs muted" style="margin:0">Chaque point apparaît dans le tableau de bord du pavé. Retirez-le une fois vérifié.</p>
    </div>
  <?php endif; ?>
  <div class="row"><button type="submit" class="btn btn--navy" data-save>Enregistrer</button><span class="small muted" data-saved></span></div>
</form>
<?php if (!$isDefault): ?>
  <form method="post" action="/admin/association/page/<?= e($key) ?>/depart" data-confirm="Revenir au texte de départ ?|Le texte livré avec le site remplace le vôtre (votre version reste dans l’historique ci-dessous).|Revenir au départ|danger" style="margin:0">
    <?= csrf_field() ?><button type="submit" class="btn btn--sm btn--ghost">Revenir au texte de départ</button>
  </form>
<?php endif; ?>
<?php if ($versions): ?>
  <div class="card">
    <div class="card__head"><h2 class="card__t card__t--sm">Dernières modifications</h2></div>
    <?php foreach ($versions as $v): ?><div class="card__row" style="grid-template-columns:60px minmax(0,1fr) auto"><span class="vers__n">v<?= (int) $v['n'] ?></span><span><?= e($v['by']) ?> · <?= e($v['message']) ?></span><span class="xs muted"><?= e(Base::ago($v['at'])) ?></span></div><?php endforeach; ?>
  </div>
<?php endif; ?>
