<?php
/** Boutique : catalogue. Variables : $cards [m, sup, svg, from], $config, $count */
use App\Shop\Orders;
use App\Vitrine\Host;
?>
<?= \App\Core\View::partial('vitrine/partials/page-head', ['title' => 'La boutique', 'lead' => 'Des objets jaune et bleu à votre nom, fabriqués à la demande par un imprimeur local. Choisissez, personnalisez, on s’occupe du reste.', 'eyebrow' => 'Sochaux Rétro', 'crumbs' => [['Boutique', Host::url('/boutique/')]]]) ?>
<?= \App\Core\View::partial('vitrine/boutique/_bar', ['config' => $config, 'count' => $count]) ?>
<section class="section--tight">
  <div class="wrap">
    <?php if (!$cards): ?><p>Les premiers articles arrivent très bientôt.</p><?php endif; ?>
    <div class="shopgrid">
      <?php foreach ($cards as $c): ?>
      <a class="shopcard" href="<?= e(Host::url('/boutique/' . $c['m']['id'] . '/')) ?>" data-reveal>
        <div class="shopcard__img"><?= $c['svg'] ?></div>
        <div class="shopcard__txt">
          <span class="eyebrow"><?= e($c['sup']['name']) ?></span>
          <h2 class="shopcard__t"><?= e($c['m']['name']) ?></h2>
          <span class="shopcard__price"><?= $c['m']['sale']['extra'] ? 'à partir de ' : '' ?><?= e(Orders::money($c['from'])) ?></span>
          <?php if (\App\Shop\Catalog::fields($c['m'])): ?><span class="shopcard__tag">Personnalisable</span><?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
