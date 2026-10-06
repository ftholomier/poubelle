<?php
/** Boutique : catalogue. Variables : $cards [m, sup, img, from], $config, $count */
use App\Shop\Orders;
use App\Shop\ShopPages;
?>
<?= \App\Core\View::partial('boutique/_head', ['title' => 'La boutique', 'lead' => 'Des objets jaune et bleu à votre nom, fabriqués à la demande par un imprimeur local. Choisissez, personnalisez, on s’occupe du reste.', 'eyebrow' => 'Sochaux Rétro', 'crumbs' => [['Boutique', ShopPages::u('/boutique/')]]]) ?>
<?= \App\Core\View::partial('boutique/_bar', ['config' => $config, 'count' => $count]) ?>
<section class="section--tight">
  <div class="wrap">
    <?php if (!$cards): ?><p>Les premiers articles arrivent très bientôt.</p><?php endif; ?>
    <?php if (array_filter($cards, fn ($c) => \App\Shop\Catalog::unique($c['m']))): ?>
    <aside class="shopuniqbar"><span class="shopuniq shopuniq--lg" aria-hidden="true">★ Pièce unique</span><p><b>Nouveau : les pièces uniques.</b> Sur les articles marqués d’une étoile, le musée tire pour vous une anecdote de l’histoire du FCSM (une fois vendue, elle n’est plus jamais proposée), compose le poster de votre match, dédicacé à votre nom et numéroté, ou imprime votre carte de supporter d’après votre carnet : votre objet n’existera qu’en un seul exemplaire.</p></aside>
    <?php endif; ?>
    <div class="shopgrid">
      <?php foreach ($cards as $c): ?>
      <a class="shopcard" href="<?= e(ShopPages::u('/boutique/' . $c['m']['id'] . '/')) ?>" data-reveal>
        <div class="shopcard__img"><img src="<?= e($c['img']) ?>" alt="" loading="lazy" decoding="async"><?php if (\App\Shop\Catalog::unique($c['m'])): ?><span class="shopuniq" title="Un seul exemplaire : anecdote tirée pour vous, poster dédicacé et numéroté ou carte de votre carnet">★ Pièce unique</span><?php endif; ?></div>
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
