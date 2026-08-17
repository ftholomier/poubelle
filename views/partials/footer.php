<?php
/**
 * Pied de page noir & or.
 *
 * @var array $site
 * @var App\Core\View $view
 */
$resa = $site['reservation'];
?>
<footer class="pied">
  <div class="conteneur">
    <div class="pied__haut">
      <div class="pied__marque">
        <div class="pied__logo">
          <img src="<?= asset('assets/img/logo/logo-etang-fourchu.svg') ?>"
               alt="<?= e($site['nom']) ?> — <?= e($site['baseline']) ?>" loading="lazy">
        </div>

        <div class="pied__resa">
          <h3>Réservation</h3>
          <a class="btn btn--or" href="<?= e($resa['hebergement']['url']) ?>" target="_blank" rel="noopener"><?= e($resa['hebergement']['libelle']) ?></a>
          <a class="btn btn--contour" href="<?= e($resa['etang']['url']) ?>" target="_blank" rel="noopener"><?= e($resa['etang']['libelle']) ?></a>
        </div>
      </div>

      <nav aria-label="Navigation pied de page">
        <h3>Le domaine</h3>
        <ul class="pied__liens">
          <?php foreach ($site['menu'] as $item): ?>
            <li><a href="<?= url($item['url']) ?>"><?= e($item['libelle']) ?></a></li>
          <?php endforeach; ?>
          <li><a href="<?= route('reglement') ?>">Règlement général</a></li>
        </ul>
      </nav>

      <div>
        <h3>Contact</h3>
        <address>
          <?= e($site['nom']) ?><br>
          <?= e($site['adresse']['rue']) ?><br>
          <?= e($site['adresse']['cp']) ?> <?= e($site['adresse']['ville']) ?><br>
          <a href="tel:+33<?= e(substr(preg_replace('/\D+/', '', $site['contact']['telephone']), 1)) ?>"><?= e($site['contact']['telephone']) ?></a>
        </address>
      </div>
    </div>

    <div class="pied__seo">
      <p><?= e($site['pied']['seo']) ?></p>
      <p><?= e($site['pied']['proche_de']) ?></p>
    </div>

    <div class="pied__bas">
      <p><?= e($site['pied']['copyright']) ?></p>
      <p><a href="<?= route('mentions-legales') ?>">Mentions légales</a></p>
    </div>
  </div>
</footer>
