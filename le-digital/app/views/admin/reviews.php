<?php /** @var array $reviews */ ?>
<div class="toolbar">
  <p class="intro">Des avis réels uniquement (prénom + activité). Tant qu’aucun avis n’est publié, le site affiche les emplacements « Témoignage réel à fournir ».</p>
  <a class="btn btn--red" href="/admin/avis/nouveau">+ Ajouter un avis</a>
</div>
<?php if (!$reviews): ?>
  <div class="card empty"><p>Aucun avis pour l’instant.</p><a class="btn btn--red" href="/admin/avis/nouveau">Ajouter le premier avis</a></div>
<?php else: ?>
  <div class="card card--flush">
    <table class="table">
      <thead><tr><th>Avis</th><th>Thème</th><th class="hide-sm">Accueil</th><th>Statut</th></tr></thead>
      <tbody>
        <?php foreach ($reviews as $r): ?>
          <tr data-href="/admin/avis/<?= e($r['id']) ?>">
            <td><a href="/admin/avis/<?= e($r['id']) ?>"><b><?= e($r['name']) ?></b></a> <small class="muted"><?= e($r['activity']) ?></small><br><small><?= str_repeat('★', (int) $r['rating']) ?> <?= e(mb_strimwidth($r['text'], 0, 90, '…')) ?></small></td>
            <td><small><?= e($r['tag']) ?></small></td>
            <td class="hide-sm"><?= !empty($r['home']) ? '✓' : '' ?></td>
            <td><span class="status status--<?= !empty($r['published']) ? 'done' : 'archived' ?>"><?= !empty($r['published']) ? 'Publié' : 'Masqué' ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
