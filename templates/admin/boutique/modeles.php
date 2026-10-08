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
<form method="post" action="/admin/boutique/modeles" class="row" style="gap:8px;flex-wrap:wrap;align-items:center;margin:-6px 0 16px">
  <?= csrf_field() ?><input type="hidden" name="action" value="ready">
  <span class="small"><b>Modèles prêts à l’emploi</b> (la carte du carnet du supporter, en poster A4, A3 ou A2) :</span>
  <button class="btn btn--sm btn--ghost" name="style" value="a">+ Poster « Ma carte de supporter » · Charte du musée</button>
  <button class="btn btn--sm btn--ghost" name="style" value="b">+ Poster « Ma carte de supporter » · Billet de match</button>
</form>
<?php $bk = \App\Shop\BookShop::config(); $bkOn = \App\Shop\BookShop::sellable(); ?>
<?php if (!$models): ?><p class="empty">Aucun modèle pour l’instant : créez le premier ci-dessus.</p><?php endif; ?>
<?php if (count($models) > 1): ?><p class="xs muted" style="margin:0 0 8px">Glissez une carte par sa poignée jaune ✥ pour la déplacer : la boutique présente les produits dans cet ordre (enregistré aussitôt).</p><?php endif; ?>
<div class="shopgrid" data-sortable data-model-order>
  <article class="shopcard shopcard--book">
    <span class="shopcard__st shopcard__st--<?= $bkOn ? 'on' : ($bk['active'] ? 'warn' : 'off') ?>"><?= $bkOn ? 'En vente · ' . e(\App\Shop\Orders::money($bk['paper'] ? min($bk['price'], $bk['price_pdf'] ?: $bk['price']) : $bk['price_pdf'])) . ($bk['paper'] ? '' : ' (PDF)') : ($bk['active'] ? 'Pas en vente : sans prix' : 'Pas en vente') ?></span>
    <span class="shopcard__uniq" title="Nom en couverture, dédicace, maillot, match… : chaque exemplaire est unique">★ Personnalisé</span>
    <a class="shopcard__img" href="/admin/livre"><?= \App\Shop\BookShop::coverSvg(['nom' => 'Votre nom']) ?></a>
    <div class="shopcard__body">
      <a class="shopcard__t" href="/admin/livre">Livre « 100 récits du Lion »</a>
      <p class="shopcard__meta"><?= $bk['paper'] ? 'Livre imprimé et numérique' : 'Livre numérique (PDF)' ?> · toujours en tête de la boutique</p>
    </div>
    <div class="shopcard__foot">
      <a class="btn btn--sm btn--navy" href="/admin/livre">Modifier</a>
      <?php if ($bkOn): ?><a class="btn btn--sm btn--ghost" href="<?= e(\App\Shop\ShopPages::u('/boutique/livre/')) ?>" target="_blank" rel="noopener">Voir en boutique</a><?php endif; ?>
    </div>
  </article>
  <?php foreach ($models as $m): $s = $supports[$m['support']] ?? null; $u = '/admin/boutique/modeles/' . e($m['id']);
    $st = \App\Shop\Catalog::sellable($m) ? ['on', 'En vente · ' . \App\Shop\Orders::money($m['sale']['price'])] : ($m['active'] ? ['warn', $m['sale']['price'] <= 0 ? 'Pas en vente : sans prix' : 'Pas en vente : support désactivé'] : ['off', 'Brouillon']); ?>
    <article class="shopcard" data-sort-item data-id="<?= e($m['id']) ?>">
      <span class="shopcard__grip" data-handle role="button" tabindex="0" title="Glisser pour déplacer" aria-label="Déplacer « <?= e($m['name']) ?> »">✥</span>
      <span class="shopcard__st shopcard__st--<?= $st[0] ?>"><?= e($st[1]) ?></span>
      <?php if (\App\Shop\Catalog::unique($m)): ?><span class="shopcard__uniq" title="Anecdote tirée pour un seul client, poster dédicacé et numéroté ou carte du carnet du supporter : chaque exemplaire est unique">★ Pièce unique</span><?php endif; ?>
      <a class="shopcard__img" href="<?= $u ?>" draggable="false"><?= $previews[$m['id']] ?? '' ?></a>
      <div class="shopcard__body">
        <a class="shopcard__t" href="<?= $u ?>"><?= e($m['name']) ?></a>
        <p class="shopcard__meta"><?= e($s['name'] ?? $m['support']) ?><?= $m['updated'] ? ' · modifié le ' . e(date_num(substr($m['updated'], 0, 10))) : '' ?></p>
      </div>
      <div class="shopcard__foot">
        <a class="btn btn--sm btn--navy" href="<?= $u ?>">Modifier</a>
        <a class="btn btn--sm btn--ghost" href="<?= $u ?>/pdf">PDF</a>
        <form method="post" action="/admin/boutique/modeles" class="shopcard__tools">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($m['id']) ?>">
          <button class="shopcard__ico" name="action" value="toggle" title="<?= $m['active'] ? 'Mettre de côté (retirer de la boutique)' : 'Prêt à la vente' ?>" aria-label="<?= $m['active'] ? 'Mettre de côté' : 'Prêt à la vente' ?>"><?= $m['active'] ? '⏸' : '▶' ?></button>
          <button class="shopcard__ico" name="action" value="duplicate" title="Copier ce modèle" aria-label="Copier">⧉</button>
        </form>
        <form method="post" action="/admin/boutique/modeles" data-confirm="Supprimer ce modèle ?|« <?= e($m['name']) ?> » sera supprimé définitivement.|Supprimer|danger">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($m['id']) ?>">
          <button class="shopcard__ico shopcard__ico--danger" name="action" value="delete" title="Supprimer" aria-label="Supprimer">🗑</button>
        </form>
      </div>
    </article>
  <?php endforeach; ?>
</div>
<style>
.shopgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px}
.shopcard{position:relative;display:flex;flex-direction:column;background:#fffdf7;border:2px solid #0e1f4d;box-shadow:4px 4px 0 #0e1f4d;overflow:hidden}
.shopcard__grip{position:absolute;top:10px;left:10px;z-index:2;display:grid;place-items:center;width:32px;height:32px;border:2px solid #0e1f4d;background:#f6c400;color:#0e1f4d;font-size:17px;cursor:grab;touch-action:none;user-select:none}
.shopcard__grip:active{cursor:grabbing}
.shopcard__st{position:absolute;top:10px;right:10px;z-index:2;padding:4px 8px;font:700 11px/1 var(--display, sans-serif);letter-spacing:.06em;text-transform:uppercase;border:2px solid #0e1f4d;background:#fff}
.shopcard__uniq{position:absolute;top:44px;right:10px;z-index:2;padding:4px 8px;font:700 11px/1 var(--display, sans-serif);letter-spacing:.06em;text-transform:uppercase;border:2px solid #0e1f4d;background:#f6c400;color:#0e1f4d;box-shadow:2px 2px 0 #0e1f4d;transform:rotate(-3deg)}
.shopcard__st--on{background:#9ed7a9}.shopcard__st--warn{background:#f3c9c4}.shopcard__st--off{background:#e6e1d4;color:#5a6070}
.shopcard__img{display:block;background:#f3eddf;padding:44px 12px 10px;border-bottom:2px solid #0e1f4d}
.shopcard__img > svg{display:block;width:100%;height:200px}
.shopcard__body{padding:12px 14px 6px;flex:1}
.shopcard__t{display:block;font-weight:700;font-size:16px;color:#0e1f4d;text-decoration:none;line-height:1.25}
.shopcard__t:hover{text-decoration:underline}
.shopcard__meta{margin:4px 0 0;font-size:12.5px;color:#5a6070}
.shopcard__foot{display:flex;align-items:center;gap:6px;padding:10px 14px 12px;border-top:1px solid rgba(14,31,77,.12)}
.shopcard__foot form{margin:0;display:flex;gap:4px}
.shopcard__tools{margin-left:auto !important}
.shopcard__ico{width:30px;height:30px;display:grid;place-items:center;border:1.5px solid rgba(14,31,77,.35);background:#fff;color:#0e1f4d;font-size:14px;cursor:pointer}
.shopcard__ico:hover{border-color:#0e1f4d;background:#f6c400}
.shopcard__ico--danger:hover{background:#f3c9c4}
.shopcard.is-sorting-src{opacity:.35}
</style>
<script nonce="<?= e(csp_nonce()) ?>">
document.querySelector('[data-model-order]')?.addEventListener('sorted', async e => {
  const ids = [...e.currentTarget.querySelectorAll(':scope > [data-sort-item]')].map(c => c.dataset.id);
  const r = await BO.post('/admin/boutique/modeles/ordre', { ids });
  BO.toast(r.ok ? 'Ordre enregistré : la boutique suit cet ordre.' : (r.error || 'Ordre non enregistré.'), !r.ok);
});
</script>
