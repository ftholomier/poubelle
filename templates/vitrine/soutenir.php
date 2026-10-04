<?php
/** Nous soutenir. Variables : $p, $donations */
use App\Vitrine\Content;
use App\Vitrine\Host;
use App\Vitrine\Pages;
?>
<?= \App\Core\View::partial('vitrine/partials/page-head', ['title' => $p['title'], 'lead' => $p['lead'], 'eyebrow' => 'Rejoignez l’aventure', 'image' => Content::hasImage($p['image'] ?? null) ? $p['image'] : null, 'crumbs' => [[$p['title'], Host::url('/nous-soutenir/')]]]) ?>
<section class="section">
  <div class="wrap">
    <?= \App\Core\View::partial('vitrine/partials/support-cards') ?>
  </div>
</section>
<section class="section bg-navy">
  <div class="wrap grid-fit" style="--min:380px;--align:start">
    <div><span class="eyebrow">Pourquoi</span><h2 class="h-section mt-20"><?= e($p['why_title'] ?? '') ?></h2></div>
    <div class="prose vlead-prose"><?= Pages::rich($p['why'] ?? '') ?></div>
  </div>
</section>
<section class="vcta-band">
  <div class="wrap vcta-band__in">
    <div><h2 class="h-2">Entreprise, collectivité, média ?</h2><p>Associez votre nom à la mémoire du FC Sochaux-Montbéliard.</p></div>
    <a class="btn btn--navy" href="<?= e(Host::url('/partenaires/')) ?>#devenir-partenaire">Devenir partenaire</a>
  </div>
</section>
