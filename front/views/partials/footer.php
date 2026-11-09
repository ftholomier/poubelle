<?php
$site = Content::site();
$products = Content::products();
[$mu, $md] = Content::emailParts();
?>
<footer class="rv-foot">
  <div class="rv-foot__g">
    <div>
      <img class="rv-foot__logo" src="/assets/img/logo.png" alt="<?= e($site['name']) ?>" width="187" height="46">
      <div class="rv-foot__about"><?= e($site['legal_name']) ?><br><?= e($site['tagline'] ?? '') ?></div>
    </div>
    <div>
      <div class="rv-foot__h">Produits</div>
      <div class="rv-foot__links">
        <?php foreach ($products as $p): ?>
          <a href="<?= e($p['path']) ?>"><?= e($p['title']) ?><i></i></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <div class="rv-foot__h">Agences</div>
      <div class="rv-foot__txt">
        <?php foreach (($site['agencies'] ?? []) as $i => $a): ?>
          <?= $i ? '<br>' : '' ?><b><?= e($a['name']) ?> :</b><br><?= e($a['street']) ?><br><?= e(trim($a['zip'] . ' ' . $a['town'] . ' ' . ($a['country'] ?? ''))) ?><br>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <div class="rv-foot__h">Contact</div>
      <a class="rv-foot__tel" href="tel:<?= e(tel($site['phone'])) ?>"><?= e($site['phone']) ?></a>
      <div class="rv-foot__txt" style="margin-top:8px;line-height:1.7">
        Fax : <?= e($site['fax']) ?><br>
        Service Technique : <a href="tel:<?= e(tel($site['sav_phone'])) ?>"><?= e($site['sav_phone']) ?></a><br>
        <a href="#" data-mail="<?= e($mu) ?>" data-domain="<?= e($md) ?>"><?= e($mu) ?>[@]<?= e(str_replace('.', '[.]', $md)) ?></a>
      </div>
      <div class="rv-foot__soc">
        <?php if (!empty($site['facebook'])): ?><a href="<?= e($site['facebook']) ?>" target="_blank" rel="noopener" aria-label="Facebook">f</a><?php endif; ?>
        <?php if (!empty($site['instagram'])): ?><a href="<?= e($site['instagram']) ?>" target="_blank" rel="noopener" aria-label="Instagram">ig</a><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="rv-foot__bot">
    <div>
      <span>Copyright <?= date('Y') ?> - <?= e($site['name']) ?></span>
      <span><a href="<?= e(Content::path('mentions')) ?>"><?= e(Content::page('mentions')['title'] ?? 'Mentions légales') ?></a><?php if (!empty($site['credit']['url'])): ?> · Propulsé par <a href="<?= e($site['credit']['url']) ?>" target="_blank" rel="noopener"><?= e($site['credit']['label']) ?></a><?php endif; ?></span>
    </div>
  </div>
</footer>
