<?php
$posts = Content::posts(true);
$src = (string) ($_GET['source'] ?? '');
if ($src !== '') { $posts = array_values(array_filter($posts, fn($p) => ($p['source'] ?? 'site') === $src)); }
?>
<div class="bo-actions" style="margin-bottom:16px">
  <a class="b b--y" href="/admin/actus/nouveau">+ Nouvelle actualité</a>
  <?php if (Facebook::configured()): ?><button class="b b--l" type="button" data-action="facebook.sync">Importer depuis Facebook</button><?php elseif (Auth::isAdmin()): ?><a class="b b--l" href="/admin/facebook">Brancher Facebook</a><?php endif; ?>
  <a class="b b--l<?= $src === '' ? ' is-on' : '' ?>" href="/admin/actus">Toutes</a>
  <a class="b b--l<?= $src === 'site' ? ' is-on' : '' ?>" href="/admin/actus?source=site">Site</a>
  <a class="b b--l<?= $src === 'facebook' ? ' is-on' : '' ?>" href="/admin/actus?source=facebook">Facebook</a>
</div>
<div class="card card--flush">
  <table class="tbl">
    <thead><tr><th>Date</th><th>Actualité</th><th>Catégorie</th><th>Origine</th><th>Statut</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($posts as $p): $img = Content::postImage($p); ?>
        <tr>
          <td><?= e(date('d/m/Y', strtotime($p['date']))) ?></td>
          <td><div class="row-media"><?php if ($img): ?><img src="<?= e($img) ?>" alt=""><?php endif; ?><b><?= e(plain((string) $p['title'])) ?></b></div></td>
          <td><?= e($p['cat'] ?? '') ?></td>
          <td><?= ($p['source'] ?? 'site') === 'facebook' ? 'Facebook' : 'Site' ?></td>
          <td><?= !empty($p['visible']) ? ($p['date'] > date('Y-m-d') ? '<span class="st st--encours">Programmée</span>' : '<span class="st st--traite">En ligne</span>') : '<span class="st st--archive">Masquée</span>' ?></td>
          <td class="r"><a class="b b--l" href="<?= e($p['path']) ?>" target="_blank" rel="noopener">Voir ↗</a> <a class="b b--l" href="/admin/actus/<?= e($p['id']) ?>">Modifier</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$posts): ?><tr><td colspan="6" class="bo-muted" style="padding:22px">Aucune actualité.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<p class="bo-muted">Sur le site, les actus créées ici et celles importées de Facebook sont mélangées par date, sans distinction visuelle.</p>
