<?php /** @var array $reviews */ ?>
<?= LD\View::render('admin/reviews-tabs', ['on' => 'list']) ?>
<div class="toolbar">
  <p class="intro">Avis saisis à la main et avis importés depuis Google <?= google_g(13) ?>. Tant qu’aucun avis n’est publié (ni widget collé), le site affiche les emplacements « Témoignage réel à fournir ».</p>
  <a class="btn btn--red" href="/admin/avis/nouveau">+ Ajouter un avis</a>
</div>
<?php if (!$reviews): ?>
  <div class="card empty">
    <p>Aucun avis pour l’instant.</p>
    <div class="actions"><a class="btn btn--red" href="/admin/avis/google"><?= google_g(16) ?> Importer mes avis Google</a><a class="btn btn--ghost" href="/admin/avis/nouveau">Saisir un avis</a></div>
  </div>
<?php else: ?>
  <div class="card card--flush">
    <table class="table">
      <thead><tr><th>Avis</th><th>Thème</th><th class="hide-sm">Accueil</th><th>Statut</th></tr></thead>
      <tbody>
        <?php foreach ($reviews as $r): $g = LD\Reviews::isGoogle($r); ?>
          <tr data-href="/admin/avis/<?= e($r['id']) ?>">
            <td><?php if ($g): ?><span class="src-g" title="Avis Google"><?= google_g(14) ?></span><?php endif; ?><a href="/admin/avis/<?= e($r['id']) ?>"><b><?= e($r['name']) ?></b></a> <small class="muted"><?= e($r['activity'] ?? '') ?></small><br><small><?= str_repeat('★', (int) $r['rating']) ?> <?= e(mb_strimwidth((string) $r['text'], 0, 90, '…')) ?></small></td>
            <td><small><?= e(($r['tag'] ?? '') ?: '—') ?></small></td>
            <td class="hide-sm"><?= !empty($r['home']) ? '✓' : '' ?></td>
            <td><span class="status status--<?= !empty($r['published']) ? 'done' : 'archived' ?>"><?= !empty($r['published']) ? 'Publié' : 'Masqué' ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
