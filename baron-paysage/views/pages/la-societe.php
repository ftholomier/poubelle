<?php
/**
 * Page « À propos ».
 *
 * @var array $page
 * @var App\Core\View $view
 */
?>
<?= $view->partial('hero-page', ['hero' => $page['hero']]) ?>

<?php /* Le portrait ouvre la page : l'entreprise, ici, c'est d'abord
         quelqu'un. La citation est détachée du corps du texte — c'est la
         phrase qu'on retient, elle mérite sa propre composition. */ ?>
<section class="section">
  <div class="conteneur">
    <div class="portrait">
      <figure class="portrait__media reveler">
        <img src="<?= image($page['portrait']['image']) ?>"
             alt="<?= e($page['portrait']['nom']) ?>" loading="lazy">
      </figure>

      <div class="portrait__texte reveler">
        <p class="surtitre"><?= e($page['portrait']['role']) ?></p>
        <h2 class="titre-section"><?= e($page['portrait']['nom']) ?></h2>

        <?php if (trim((string) ($page['portrait']['citation'] ?? '')) !== ''): ?>
          <blockquote class="portrait__citation">
            <p><?= e($page['portrait']['citation']) ?></p>
          </blockquote>
        <?php endif; ?>

        <?php foreach ($page['portrait']['paragraphes'] as $p): ?>
          <p><?= e($p) ?></p>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<?php if (!empty($page['reperes']['items'])): ?>
<section class="indicateurs">
  <div class="conteneur">
    <ul class="indicateurs__liste">
      <?php foreach ($page['reperes']['items'] as $i): ?>
        <li class="indicateurs__item reveler">
          <p class="indicateurs__valeur">
            <?= e($i['valeur']) ?><span class="indicateurs__unite"><?= e($i['unite']) ?></span>
          </p>
          <p class="indicateurs__libelle"><?= e($i['libelle']) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<section class="section section--teinte">
  <div class="conteneur">
    <ul class="piliers">
      <?php foreach ($page['piliers']['items'] as $pilier): ?>
        <li class="pilier reveler">
          <figure class="pilier__media">
            <img src="<?= image($pilier['image']) ?>" alt="<?= e($pilier['titre']) ?>" loading="lazy">
          </figure>
          <div class="pilier__corps">
            <p class="pilier__entete">
              <span class="pilier__numero"><?= e($pilier['numero']) ?></span>
              <span class="pilier__label"><?= e($pilier['label']) ?></span>
            </p>
            <h3 class="pilier__titre"><?= e($pilier['titre']) ?></h3>
            <p><?= e($pilier['texte']) ?></p>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="section">
  <div class="conteneur">
    <div class="section__tete reveler">
      <p class="surtitre"><?= e($page['clients']['surtitre']) ?></p>
      <h2 class="titre-section"><?= e($page['clients']['titre']) ?></h2>
      <p class="section__chapo"><?= e($page['clients']['texte']) ?></p>
    </div>

    <ul class="cartes cartes--clients">
      <?php foreach ($page['clients']['items'] as $client): ?>
        <li class="carte-client reveler">
          <span class="carte-client__icone" aria-hidden="true">
            <?= $view->partial('icones', ['nom' => $client['icone']]) ?>
          </span>
          <h3 class="carte-client__titre"><?= e($client['nom']) ?></h3>
          <p class="carte-client__texte"><?= e($client['texte']) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="section section--teinte">
  <div class="conteneur conteneur--etroit">
    <div class="section__tete reveler">
      <p class="surtitre"><?= e($page['engagement']['surtitre']) ?></p>
      <h2 class="titre-section"><?= e($page['engagement']['titre']) ?></h2>
    </div>
    <div class="bloc-texte reveler">
      <?php foreach ($page['engagement']['paragraphes'] as $p): ?>
        <p><?= e($p) ?></p>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?= $view->partial('bande-cta') ?>
