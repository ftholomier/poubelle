<?php $products = Content::products(true); ?>
<div class="bo-actions" style="margin-bottom:16px"><a class="b b--y" href="/admin/produits/nouveau">+ Nouveau produit</a><a class="b b--l" href="/admin/pages/produits">Page « Nos produits »</a><a class="b b--l" href="/admin/photos">Photos produits</a></div>
<div class="card card--flush">
  <table class="tbl" data-sortable="product.order">
    <thead><tr><th style="width:70px">Ordre</th><th>Produit</th><th>Adresse</th><th>Statut</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($products as $p): ?>
        <tr data-id="<?= e($p['id']) ?>">
          <td><button class="b b--icon" type="button" data-move="-1" title="Monter">↑</button><button class="b b--icon" type="button" data-move="1" title="Descendre">↓</button></td>
          <td><div class="row-media"><img src="<?= e($p['image']) ?>" alt=""><b><?= e($p['title']) ?></b></div></td>
          <td><code><?= e($p['path']) ?></code></td>
          <td><?= !empty($p['visible']) ? '<span class="st st--traite">En ligne</span>' : '<span class="st st--archive">Masqué</span>' ?></td>
          <td class="r"><a class="b b--l" href="<?= e($p['path']) ?>" target="_blank" rel="noopener">Voir ↗</a> <a class="b b--l" href="/admin/produits/<?= e($p['id']) ?>">Modifier</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
