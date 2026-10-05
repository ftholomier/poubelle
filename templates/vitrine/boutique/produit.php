<?php
/**
 * Boutique : un article et sa personnalisation (choix calibrés). Sans JavaScript, le formulaire
 * fonctionne ; avec, l'aperçu et le prix se mettent à jour en direct (js/boutique.js).
 * Variables : $m, $sup, $fields, $positions, $svg, $config, $count, $faces, $flash
 */
use App\Shop\Catalog;
use App\Shop\Orders;
use App\Vitrine\Host;

$s = $m['sale'];
$names = array_flip($sup['colors']);
$tnames = array_flip(\App\Shop\Vector::PALETTE);
?>
<?= \App\Core\View::partial('vitrine/boutique/_bar', ['config' => $config, 'count' => $count]) ?>
<section class="section--tight">
  <div class="wrap shopprod" data-shop-product data-model="<?= e($m['id']) ?>" data-preview="<?= e(Host::url('/boutique/apercu/')) ?>">
    <div class="shopprod__view">
      <nav class="crumbs vcrumbs"><a href="<?= e(Host::url('/boutique/')) ?>">Boutique</a><span aria-hidden="true">›</span><span><?= e($m['name']) ?></span></nav>
      <div class="shopprod__img" data-shop-svg aria-live="polite"><?= $svg ?></div>
      <?php if (count($faces) > 1): ?>
      <div class="shopseg" role="group" aria-label="Face"><?php foreach ($faces as $i => $fk): ?><button type="button" class="shopseg__b<?= $i ? '' : ' is-on' ?>" data-face="<?= e($fk) ?>"><?= e($sup['faces'][$fk]['label']) ?></button><?php endforeach; ?></div>
      <?php endif; ?>
    </div>
    <form class="shopprod__form vform" method="post" action="<?= e(Host::url('/boutique/panier/')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="do" value="add"><input type="hidden" name="model" value="<?= e($m['id']) ?>">
      <span class="eyebrow"><?= e($sup['name']) ?></span>
      <h1 class="h-1 shopprod__t"><?= e($m['name']) ?></h1>
      <p class="shopprod__price" data-shop-price><?= e(Orders::money($s['price'])) ?></p>
      <?php if ($s['desc'] !== ''): ?><p><?= nl2br(e($s['desc'])) ?></p><?php endif; ?>
      <?= \App\Core\View::partial('vitrine/partials/flash', ['flash' => $flash]) ?>

      <?php foreach ($fields as $k => $f): ?>
      <div class="field">
        <label for="f-<?= e($k) ?>"><?= e($f['label']) ?> *</label>
        <?php if ($f['list'] !== ''): ?>
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
      <?php if ($s['text_colors'] && $fields): ?>
      <fieldset class="shopopt"><legend>Couleur du texte</legend>
        <?php foreach ($s['text_colors'] as $i => $hex): ?>
        <label class="shopsw" title="<?= e($tnames[$hex] ?? $hex) ?>"><input type="radio" name="opts[tcolor]" value="<?= e($hex) ?>"<?= $i ? '' : ' checked' ?> data-shop-in><span style="background:<?= e($hex) ?>"></span><em><?= e($tnames[$hex] ?? '') ?></em></label>
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
