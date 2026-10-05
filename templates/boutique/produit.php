<?php
/**
 * Boutique : un article et sa personnalisation (choix calibrés). Sans JavaScript, le formulaire
 * fonctionne ; avec, l'aperçu et le prix se mettent à jour en direct (js/boutique.js).
 * Variables : $m, $sup, $fields, $positions, $svg, $config, $count, $faces, $flash
 */
use App\Shop\Catalog;
use App\Shop\Orders;
use App\Shop\ShopPages;

$s = $m['sale'];
$names = array_flip($sup['colors']);
$tnames = array_flip(\App\Shop\Vector::PALETTE);
?>
<?= \App\Core\View::partial('boutique/_bar', ['config' => $config, 'count' => $count]) ?>
<section class="section--tight">
  <div class="wrap shopprod" data-shop-product data-model="<?= e($m['id']) ?>" data-color="<?= e($m['color']) ?>" data-preview="<?= e(ShopPages::u('/boutique/apercu/')) ?>" data-3d="<?= e(asset('js/shop3d.js')) ?>" data-anecdote="<?= e(ShopPages::u('/boutique/anecdote/')) ?>">
    <div class="shopprod__view">
      <nav class="crumbs vcrumbs"><a href="<?= e(ShopPages::u('/boutique/')) ?>">Boutique</a><span aria-hidden="true">›</span><span><?= e($m['name']) ?></span></nav>
      <div class="shopprod__stage">
        <div class="shopprod__img" data-shop-svg aria-live="polite"><?= $svg ?></div>
        <div class="shopprod__3d" data-shop-3d hidden><p class="shopprod__3dhint" data-3d-hint>Chargement de la 3D…</p></div>
      </div>
      <div class="shopviewbar">
        <?php if (count($faces) > 1): ?>
        <div class="shopseg" role="group" aria-label="Face" data-faces><?php foreach ($faces as $i => $fk): ?><button type="button" class="shopseg__b<?= $i ? '' : ' is-on' ?>" data-face="<?= e($fk) ?>"><?= e($sup['faces'][$fk]['label']) ?></button><?php endforeach; ?></div>
        <?php endif; ?>
        <button type="button" class="shop3dbtn" data-3d-toggle aria-pressed="false"><span aria-hidden="true">⟳</span> <b>Voir en 3D</b></button>
      </div>
      <?php if ($cr = ShopPages::MODELS_3D[ShopPages::kind3d($sup)] ?? null): ?><p class="shopcredit" data-3d-credit hidden>Modèle 3D : <a href="<?= e($cr['url']) ?>" target="_blank" rel="noopener">« <?= e($cr['title']) ?> »</a> par <a href="<?= e($cr['authorUrl']) ?>" target="_blank" rel="noopener"><?= e($cr['author']) ?></a>, licence <a href="https://creativecommons.org/licenses/by/4.0/deed.fr" target="_blank" rel="noopener">CC BY 4.0</a> (allégé et teinté).</p><?php endif; ?>
      <p class="shopdisclaim">Aperçus et rendu 3D indicatifs, non contractuels : couleurs, proportions et placement du marquage peuvent légèrement varier sur le produit fabriqué.</p>
    </div>
    <form class="shopprod__form vform" method="post" action="<?= e(ShopPages::u('/boutique/panier/')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="do" value="add"><input type="hidden" name="model" value="<?= e($m['id']) ?>">
      <span class="eyebrow"><?= e($sup['name']) ?></span>
      <h1 class="h-1 shopprod__t"><?= e($m['name']) ?></h1>
      <p class="shopprod__price" data-shop-price><?= e(Orders::money($s['price'])) ?></p>
      <?php if ($s['desc'] !== ''): ?><p><?= nl2br(e($s['desc'])) ?></p><?php endif; ?>
      <?= \App\Core\View::partial('vitrine/partials/flash', ['flash' => $flash]) ?>

      <?php if (\App\Shop\TonMatch::isFor($m)): ?>
      <fieldset class="shopopt shopdate"><legend>La date de votre match *</legend>
        <p class="shophelp" style="width:100%;margin:0 0 6px">Naissance, mariage, premier match à Bonal… Le jour et le mois sont facultatifs : avec l’année seule, on choisit le grand match de l’année.</p>
        <div class="shopdate__in">
          <label><span>Jour</span><select name="opts[d]" data-shop-in><option value="">—</option><?php for ($i = 1; $i <= 31; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></label>
          <label><span>Mois</span><select name="opts[mo]" data-shop-in><option value="">—</option><?php foreach (['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'] as $i => $mn): ?><option value="<?= $i + 1 ?>"><?= $mn ?></option><?php endforeach; ?></select></label>
          <label><span>Année *</span><input type="number" name="opts[y]" min="1928" max="<?= date('Y') ?>" placeholder="1988" required data-shop-in></label>
        </div>
        <p class="shopnote" data-shop-note aria-live="polite"></p>
      </fieldset>
      <?php endif; ?>
      <?php foreach ($fields as $k => $f): if ($f['auto']) { continue; } ?>
      <div class="field">
        <label for="f-<?= e($k) ?>"><?= e($f['label']) ?> *</label>
        <?php if ($f['gen']): ?>
        <div class="shopanec" data-anec="<?= e($k) ?>">
          <p class="shopanec__t" id="f-<?= e($k) ?>" data-anec-text aria-live="polite">Cliquez sur « Une anecdote » : le musée en tire une rien que pour vous, d’après sa base de matchs et de chiffres.</p>
          <input type="hidden" name="values[<?= e($k) ?>]" value="" data-shop-in data-anec-val>
          <input type="hidden" name="values[_sig_<?= e($k) ?>]" value="" data-anec-sig>
          <button type="button" class="shopanec__b" data-anec-btn><span aria-hidden="true">↻</span> <b>Une anecdote</b></button>
          <small class="shophelp">Une autre, puis une autre… jusqu’à celle qui vous plaît. Une anecdote vendue n’est plus jamais proposée : la vôtre sera unique.</small>
        </div>
        <?php elseif ($f['list'] !== ''): ?>
        <select id="f-<?= e($k) ?>" name="values[<?= e($k) ?>]" required data-shop-in>
          <option value="">Choisissez…</option>
          <?php foreach ($f['choices'] as $c): ?><option><?= e($c) ?></option><?php endforeach; ?>
        </select>
        <?php else: ?>
        <input type="text" id="f-<?= e($k) ?>" name="values[<?= e($k) ?>]" required maxlength="<?= (int) $f['max'] ?>" placeholder="<?= e($f['default']) ?>" data-shop-in>
        <small class="shophelp"><?= (int) $f['max'] ?> caractères au plus.</small>
        <?php endif; ?>
        <small class="shoperr" data-shop-err="<?= e($k) ?>" hidden></small>
      </div>
      <?php endforeach; ?>

      <?php if (count($s['colors']) > 1): ?>
      <fieldset class="shopopt"><legend>Couleur</legend>
        <?php foreach ($s['colors'] as $i => $hex): ?>
        <label class="shopsw" title="<?= e($names[$hex] ?? $hex) ?>"><input type="radio" name="opts[color]" value="<?= e($hex) ?>"<?= $hex === $m['color'] ? ' checked' : '' ?> data-shop-in><span style="background:<?= e($hex) ?>"></span><em><?= e($names[$hex] ?? '') ?></em></label>
        <?php endforeach; ?>
      </fieldset>
      <?php endif; ?>
      <?php if ($fields): $tchoices = Catalog::textChoices($m); $tdef = ''; foreach ($tchoices as $hex) { if (Catalog::readable($hex, $m['color'])) { $tdef = $hex; break; } } ?>
      <fieldset class="shopopt"><legend>Couleur du texte</legend>
        <?php foreach ($tchoices as $hex): ?>
        <label class="shopsw" title="<?= e($tnames[$hex] ?? $hex) ?>"><input type="radio" name="opts[tcolor]" value="<?= e($hex) ?>"<?= $hex === $tdef ? ' checked' : '' ?> data-shop-in><span style="background:<?= e($hex) ?>"></span><em><?= e($tnames[$hex] ?? '') ?></em></label>
        <?php endforeach; ?>
      </fieldset>
      <?php endif; ?>
      <?php if ($s['text_sizes'] && $fields): ?>
      <fieldset class="shopopt"><legend>Taille du texte</legend>
        <?php foreach (Catalog::TEXT_SIZES as $k => [, $label]): ?><label class="shoppill"><input type="radio" name="opts[tsize]" value="<?= $k ?>"<?= $k === 'm' ? ' checked' : '' ?> data-shop-in><span><?= e($label) ?></span></label><?php endforeach; ?>
      </fieldset>
      <?php endif; ?>
      <?php if ($positions): ?>
      <fieldset class="shopopt"><legend>Position du texte</legend>
        <label class="shoppill"><input type="radio" name="opts[pos]" value="" checked data-shop-in><span>Comme sur le modèle</span></label>
        <?php foreach ($positions as $k => $label): ?><label class="shoppill"><input type="radio" name="opts[pos]" value="<?= e($k) ?>" data-shop-in><span><?= e($label) ?></span></label><?php endforeach; ?>
      </fieldset>
      <?php endif; ?>

      <div class="vgrid2">
        <?php if ($sup['sizes']): ?>
        <div class="field"><label for="f-size">Taille *</label><select id="f-size" name="size" required data-shop-in>
          <?php foreach ($sup['sizes'] as $i => $sz): ?><option value="<?= e($sz) ?>"<?= $sz === 'M' || (count($sup['sizes']) === 1) ? ' selected' : '' ?>><?= e($sz) ?><?= isset($s['extra'][$sz]) ? ' (+' . e(Orders::money($s['extra'][$sz])) . ')' : '' ?></option><?php endforeach; ?>
        </select></div>
        <?php endif; ?>
        <div class="field"><label for="f-qty">Quantité</label><input type="number" id="f-qty" name="qty" value="1" min="1" max="20" data-shop-in></div>
      </div>
      <button type="submit" class="btn btn--navy btn--block">Ajouter au panier</button>
      <p class="shophelp">Fabriqué à la demande pour vous : article personnalisé, ni repris ni échangé, sauf défaut (il est alors refait ou remboursé).</p>
    </form>
  </div>
</section>
