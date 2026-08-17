<?php
/**
 * Page « Nos valeurs ».
 *
 * Les blocs alternent image à gauche et image à droite : l'alternance est
 * portée par le rang, pas par une classe écrite dans le contenu — réordonner
 * les valeurs dans le back-office ne casse donc pas la mise en page.
 *
 * @var array $page
 * @var array $items
 * @var App\Core\View $view
 */
?>
<?= $view->partial('hero-page', ['hero' => $page['hero']]) ?>

<?php foreach ($page['sections'] ?? [] as $sec): ?>
<section class="section">
  <div class="conteneur conteneur--etroit">
    <div class="bloc-texte reveler">
      <?php if (!empty($sec['titre'])): ?><h2 class="titre-section"><?= e($sec['titre']) ?></h2><?php endif; ?>
      <?php foreach ($sec['paragraphes'] ?? [] as $p): ?>
        <p><?= e($p) ?></p>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endforeach; ?>

<section class="section section--teinte valeurs">
  <div class="conteneur">
    <?php foreach ($items as $rang => $valeur): ?>
      <article class="valeur<?= $rang % 2 === 1 ? ' valeur--inversee' : '' ?> reveler">
        <figure class="valeur__media">
          <img src="<?= image($valeur['image']) ?>" alt="<?= e($valeur['nom']) ?>" loading="lazy">
        </figure>

        <div class="valeur__texte">
          <p class="valeur__numero"><?= e(str_pad((string) ($rang + 1), 2, '0', STR_PAD_LEFT)) ?></p>
          <h2 class="valeur__titre"><?= e($valeur['nom']) ?></h2>
          <?php foreach ($valeur['paragraphes'] as $p): ?>
            <p><?= e($p) ?></p>
          <?php endforeach; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<?= $view->partial('avis') ?>

<?= $view->partial('bande-cta') ?>
