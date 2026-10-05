<?php
/**
 * Boutique › Modèles : liste avec aperçu, création (nom + support), copie, suppression.
 * Variables : $models, $supports, $previews
 */
?>
<p class="small" style="margin:0 0 12px;max-width:95ch">Un modèle est un dessin posé sur un support : le logo de l’association, des textes (fixes, ou à remplir par le client : prénom, dédicace…) et des formes. Jamais de photo : tout est vectoriel.</p>
<form method="post" action="/admin/boutique/modeles" class="card card--pad" style="flex-direction:row;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px">
  <?= csrf_field() ?><input type="hidden" name="action" value="new">
  <label class="f" style="margin:0;flex:1 1 240px"><span class="f__k">Nom du nouveau modèle</span><input class="in" name="name" placeholder="ex. T-shirt « Jaune et bleu depuis 1928 »" required></label>
  <label class="f" style="margin:0"><span class="f__k">Support</span><select class="in" name="support"><?php foreach ($supports as $s): if (!$s['active']) continue; ?><option value="<?= e($s['key']) ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></label>
  <button class="btn btn--yellow">Créer et dessiner</button>
</form>
<?php if (!$models): ?><p class="empty">Aucun modèle pour l’instant : créez le premier ci-dessus.</p><?php endif; ?>
<div class="shopgrid">
  <?php foreach ($models as $m): $s = $supports[$m['support']] ?? null; ?>
    <article class="card shopcard">
      <a class="shopcard__img" href="/admin/boutique/modeles/<?= e($m['id']) ?>"><?= $previews[$m['id']] ?? '' ?></a>
      <div class="card--pad">
        <a href="/admin/boutique/modeles/<?= e($m['id']) ?>"><b><?= e($m['name']) ?></b></a>
        <div class="xs muted"><?= e($s['name'] ?? $m['support']) ?> · <?= \App\Shop\Catalog::sellable($m) ? '<span class="chip" style="background:#9ed7a9">en vente · ' . e(\App\Shop\Orders::money($m['sale']['price'])) . '</span>' : ($m['active'] ? '<span class="chip" style="background:#f3c9c4">pas en vente : ' . ($m['sale']['price'] <= 0 ? 'sans prix (carte « Vente » de l’éditeur)' : 'support désactivé') . '</span>' : 'brouillon') ?><?= $m['updated'] ? ' · ' . e(date_num(substr($m['updated'], 0, 10))) : '' ?></div>
        <form method="post" action="/admin/boutique/modeles" class="row" style="gap:6px;margin-top:8px;flex-wrap:wrap">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($m['id']) ?>">
          <a class="btn btn--sm btn--navy" href="/admin/boutique/modeles/<?= e($m['id']) ?>">Modifier</a>
          <a class="btn btn--sm btn--ghost" href="/admin/boutique/modeles/<?= e($m['id']) ?>/pdf">PDF imprimeur</a>
          <button class="btn btn--sm btn--ghost" name="action" value="toggle"><?= $m['active'] ? 'Mettre de côté' : 'Prêt à la vente' ?></button>
          <button class="btn btn--sm btn--ghost" name="action" value="duplicate">Copier</button>
          <button class="btn btn--sm btn--danger" name="action" value="delete" >Supprimer</button>
        </form>
      </div>
    </article>
  <?php endforeach; ?>
</div>
<style>.shopgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:16px}.shopcard{overflow:hidden}.shopcard__img{display:block;background:#f3eddf;padding:10px}.shopcard__img > svg{display:block;width:100%;height:220px}</style>
