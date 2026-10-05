<?php
/**
 * Boutique › Modèle : éditeur. À gauche, l'aperçu sur le produit et le fichier d'impression (on y
 * déplace les calques à la souris) ; à droite, les calques et leurs réglages. Le dessin est rendu
 * par le serveur (mêmes tracés que le PDF de l'imprimeur). Script : admin/boutique.js.
 * Variables : $model, $support, $fonts, $palette, $lists (banque de textes)
 */
$tmEx = \App\Shop\TonMatch::values('1988-06-11');
$data = ['model' => $model, 'support' => $support, 'lists' => $lists, 'tonmatch' => array_map(fn ($k, $l) => ['field' => $k, 'label' => $l, 'text' => (string) ($tmEx[$k] ?? $l)], array_keys(\App\Shop\TonMatch::FIELDS), \App\Shop\TonMatch::FIELDS), 'fonts' => array_map(fn ($f) => $f[1], $fonts), 'palette' => $palette];
?>
<link rel="stylesheet" href="<?= asset('admin/boutique.css') ?>">
<script type="application/json" id="shop-data"><?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<div class="shoped" data-shop-editor>
  <div class="shoped__bar card card--pad">
    <label class="f" style="margin:0;flex:1 1 260px"><span class="f__k">Nom du modèle</span><input class="in" data-name value="<?= e($model['name']) ?>"></label>
    <div class="f" style="margin:0"><span class="f__k">Face</span><div class="seg" data-faces></div></div>
    <div class="f" style="margin:0" data-colors-wrap><span class="f__k">Couleur du produit</span><div class="swatches" data-colors></div></div>
    <div class="shoped__acts">
      <label class="toggle"><input type="checkbox" data-active<?= $model['active'] ? ' checked' : '' ?>><span class="toggle__box"></span><span>Prêt à la vente</span></label>
      <a class="btn btn--ghost btn--sm" data-pdf href="/admin/boutique/modeles/<?= e($model['id']) ?>/pdf">PDF imprimeur</a>
      <button type="button" class="btn btn--navy" data-save>Enregistrer</button>
    </div>
  </div>
  <div class="shoped__main">
    <div class="shoped__views">
      <figure class="card shoped__fig"><figcaption>Sur le produit</figcaption><div class="shoped__mock" data-mock></div></figure>
      <figure class="card shoped__fig"><figcaption>Fichier d’impression <span class="xs muted" data-dims></span></figcaption><div class="shoped__tools" data-tools>
          <label class="shoped__tool">Grille <select class="in in--sm" data-grid><option value="0">sans</option><option value="5">5 mm</option><option value="10">10 mm</option><option value="20">20 mm</option></select></label>
          <label class="toggle"><input type="checkbox" data-snap checked><span class="toggle__box"></span><span>Aimant</span></label>
          <span class="shoped__align" data-align-btns title="Aligner l’élément choisi sur la face">
            <button type="button" class="btn btn--sm btn--ghost" data-al="left" title="Bord gauche">⇤</button><button type="button" class="btn btn--sm btn--ghost" data-al="hcenter" title="Centre (horizontal)">↔</button><button type="button" class="btn btn--sm btn--ghost" data-al="right" title="Bord droit">⇥</button>
            <button type="button" class="btn btn--sm btn--ghost" data-al="top" title="Haut">⤒</button><button type="button" class="btn btn--sm btn--ghost" data-al="vcenter" title="Milieu (vertical)">↕</button><button type="button" class="btn btn--sm btn--ghost" data-al="bottom" title="Bas">⤓</button>
          </span>
        </div>
        <div class="shoped__print" data-print></div>
        <p class="xs muted" style="margin:6px 0 0">Cliquez un élément pour le choisir, faites-le glisser pour le déplacer : l’aimant le colle aux bords, au centre, aux autres éléments et à la grille (traits roses : où il va se poser ; Alt enfoncée : sans aimant). Flèches du clavier : 1 mm, Maj : 10 mm. Pointillés : bord du produit fini ; zone grisée : fonds perdus, coupés à la fabrication.</p></figure>
      <div class="alert alert--info" data-warn hidden></div>
    </div>
    <aside class="shoped__side">
      <section class="card card--pad">
        <h2 class="card__t card__t--sm">Ajouter</h2>
        <div class="row" style="gap:6px;flex-wrap:wrap;margin-top:6px">
          <button type="button" class="btn btn--sm btn--yellow" data-add="logo">Logo</button>
          <button type="button" class="btn btn--sm btn--yellow" data-add="text">Texte</button>
          <button type="button" class="btn btn--sm btn--ghost" data-add="client" title="Le client écrit ce qu’il veut (prénom, numéro…), dans la limite de caractères">Texte libre du client</button>
        </div>
        <div class="f" style="margin:10px 0 0"><span class="f__k">Phrase au choix du client (banque de textes)</span>
          <div class="row" style="gap:6px;flex-wrap:wrap">
          <?php foreach ($lists as $lk => $li): ?>
            <?php if ($li['choices']): ?>
            <button type="button" class="btn btn--sm btn--yellow" data-add="phrase" data-list="<?= e($lk) ?>" title="Le client choisira une phrase de cette liste (<?= count($li['choices']) ?> phrases validées)">+ <?= e($li['name']) ?> <span class="xs">(<?= count($li['choices']) ?>)</span></button>
            <?php else: ?>
            <a class="btn btn--sm btn--ghost" href="/admin/boutique/textes#l-<?= e($lk) ?>" target="_blank" title="Aucune phrase validée dans cette liste : validez-en au moins une dans la banque de textes pour pouvoir l’utiliser">+ <?= e($li['name']) ?> <span class="xs">(0 validée · à valider)</span></a>
            <?php endif; ?>
          <?php endforeach; ?>
          <a class="btn btn--sm btn--ghost" href="/admin/boutique/textes" target="_blank">Gérer les listes</a>
          </div>
        </div>
        <details class="f" style="margin:10px 0 0"><summary class="f__k" style="cursor:pointer">« Ton match » : champs remplis d’après la date du client</summary>
          <div class="row" style="gap:6px;flex-wrap:wrap;margin-top:6px">
          <?php foreach (\App\Shop\TonMatch::FIELDS as $k => $l): ?><button type="button" class="btn btn--sm btn--ghost" data-add="match" data-field="<?= e($k) ?>" title="<?= e($l) ?>">+ <?= e(preg_replace('/ \(.*$/', '', $l)) ?></button><?php endforeach; ?>
          </div>
          <p class="xs muted" style="margin:4px 0 0">Avec un de ces champs, la page de l’article demande la date au client (jour, mois, année ; ou l’année seule) et le musée retrouve le match. Exemple affiché : finale de la Coupe de France 1988.</p>
        </details>
        <div class="row" style="gap:6px;flex-wrap:wrap;margin-top:10px">
          <button type="button" class="btn btn--sm btn--ghost" data-add="rect">Rectangle</button>
          <button type="button" class="btn btn--sm btn--ghost" data-add="ellipse">Rond</button>
        </div>
        <div class="f" style="margin:10px 0 0"><span class="f__k">Fond de la face</span><div data-bg></div></div>
      </section>
      <section class="card card--pad">
        <h2 class="card__t card__t--sm">Calques <span class="xs muted">(du fond vers le dessus)</span></h2>
        <ol class="layers" data-layers></ol>
      </section>
      <section class="card card--pad" data-props hidden></section>
      <section class="card card--pad" data-fields-card hidden>
        <h2 class="card__t card__t--sm">Essai des champs du client</h2>
        <div data-fields></div>
      </section>
      <section class="card card--pad" data-sale>
        <h2 class="card__t card__t--sm">Vente <span class="xs muted">(sans prix, le modèle n’apparaît pas dans la boutique)</span></h2>
        <div class="pgrid">
          <label class="f"><span class="f__k">Prix TTC (€)</span><input class="in in--sm" type="number" min="0" step="0.5" data-sale-price value="<?= e(number_format($model['sale']['price'] / 100, 2, '.', '')) ?>"></label>
          <?php $feeOn = $model['sale']['fee'] !== null; $cv = $feeOn ? number_format($model['sale']['fee'] / 100, 2, '.', '') : ($model['sale']['rate'] !== null ? rtrim(rtrim(number_format($model['sale']['rate'], 2, '.', ''), '0'), '.') : ''); ?>
          <div class="f"><span class="f__k">Commission imprimeur</span><div class="row" style="gap:6px;flex-wrap:nowrap"><input class="in in--sm" type="number" min="0" step="0.01" data-sale-comm placeholder="<?= e($support['rate'] > 0 ? rtrim(rtrim(number_format($support['rate'], 2, '.', ''), '0'), '.') . ' % (support)' : number_format($support['cost'] / 100, 2, ',', '') . ' € (support)') ?>" value="<?= e($cv) ?>"><select class="in in--sm" data-sale-comm-unit style="width:auto"><option value="pct"<?= $feeOn ? '' : ' selected' ?>>%</option><option value="eur"<?= $feeOn ? ' selected' : '' ?>>€ / article</option></select></div></div>
          <?php foreach ($support['sizes'] as $sz): if (count($support['sizes']) < 2) { break; } ?>
          <label class="f"><span class="f__k">Supplément <?= e($sz) ?> (€)</span><input class="in in--sm" type="number" min="0" step="0.5" data-sale-extra="<?= e($sz) ?>" value="<?= isset($model['sale']['extra'][$sz]) ? e(number_format($model['sale']['extra'][$sz] / 100, 2, '.', '')) : '' ?>"></label>
          <?php endforeach; ?>
        </div>
        <label class="f"><span class="f__k">Description pour la boutique</span><textarea class="in" rows="2" data-sale-desc><?= e($model['sale']['desc']) ?></textarea></label>
        <?php if (count($support['colors']) > 1): ?>
        <div class="f"><span class="f__k">Couleurs du produit proposées au client</span><div class="row" style="gap:8px;flex-wrap:wrap">
          <?php foreach ($support['colors'] as $cn => $hex): ?><label class="toggle"><input type="checkbox" data-sale-color value="<?= e($hex) ?>"<?= in_array($hex, $model['sale']['colors'], true) ? ' checked' : '' ?>><span class="toggle__box"></span><span><i class="dot" style="background:<?= e($hex) ?>"></i> <?= e($cn) ?></span></label><?php endforeach; ?>
        </div></div>
        <?php endif; ?>
        <div class="f"><span class="f__k">Couleurs des textes proposées en plus de Jaune, Bleu nuit et Blanc (le client ne voit que celles lisibles sur la couleur du produit choisie)</span><div class="row" style="gap:8px;flex-wrap:wrap">
          <?php foreach ($palette as $cn => $hex): ?><label class="toggle" title="<?= e($cn) ?>"><input type="checkbox" data-sale-tcolor value="<?= e($hex) ?>"<?= in_array($hex, $model['sale']['text_colors'], true) ? ' checked' : '' ?>><span class="toggle__box"></span><span><i class="dot" style="background:<?= e($hex) ?>"></i></span></label><?php endforeach; ?>
        </div></div>
        <label class="toggle"><input type="checkbox" data-sale-tsizes<?= $model['sale']['text_sizes'] ? ' checked' : '' ?>><span class="toggle__box"></span><span>Taille du texte au choix (petit, moyen, grand)</span></label>
        <label class="toggle"><input type="checkbox" data-sale-pos<?= $model['sale']['positions'] ? ' checked' : '' ?>><span class="toggle__box"></span><span>Position du texte au choix (haut, centre, bas : seules celles qui ne recouvrent pas le logo sont proposées)</span></label>
        <p class="xs muted" style="margin:6px 0 0">En vente quand « Prêt à la vente » est coché et le prix renseigné. Les choix du client restent dans la charte : jamais de police, de photo ni de placement libre.</p>
      </section>
      <?php if ($support['note'] !== ''): ?><p class="xs muted" style="margin:0"><b>Consignes de l’imprimeur :</b> <?= e($support['note']) ?></p><?php endif; ?>
    </aside>
  </div>
</div>
