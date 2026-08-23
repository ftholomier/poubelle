<?php
/**
 * Pied de page.
 *
 * @var array $site
 * @var App\Core\View $view
 */
$tel = (string) ($site['contact']['telephone'] ?? '');
?>
<footer class="pied">
  <div class="conteneur">
    <div class="pied__haut">
      <div class="pied__marque">
        <a class="pied__logo" href="<?= route('accueil') ?>" aria-label="<?= e($site['nom']) ?> — Accueil">
          <img src="<?= asset($site['logo']['clair'] ?? 'assets/img/logo/logo-trehant-clair.png') ?>"
               alt="<?= e($site['nom']) ?> — <?= e($site['baseline']) ?>" width="160" height="200" loading="lazy">
        </a>
        <p class="pied__accroche"><?= e(t('Contactez-nous pour échanger sur votre projet.')) ?></p>
        <?php if ($tel !== ''): ?>
          <a class="pied__tel" href="<?= e(tel_lien($tel)) ?>"><?= e($tel) ?></a>
        <?php endif; ?>
      </div>

      <nav class="pied__nav" aria-label="Navigation pied de page">
        <h2><?= e(t('La société')) ?></h2>
        <ul class="pied__liens">
          <?php foreach ($site['menu'] as $item): ?>
            <li><a href="<?= lien($item['url']) ?>"><?= e($item['libelle']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </nav>

      <div class="pied__services">
        <h2><?= e(t('Nos gammes')) ?></h2>
        <ul class="pied__liens">
          <?php foreach ($content->publies('services') as $service): ?>
            <li><a href="<?= route('nos-services', $service['slug']) ?>"><?= e($service['nom']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="pied__contact">
        <h2><?= e(t('Nous trouver')) ?></h2>
        <address>
          <p class="pied__ligne">
            <span class="pied__icone" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'adresse']) ?></span>
            <span>
              <?= e($site['nom']) ?><br>
              <?php /* Une adresse partiellement renseignée ne doit pas laisser
                       de ligne vide ni d'espace orphelin dans le pied. */ ?>
              <?php if (($site['adresse']['rue'] ?? '') !== ''): ?>
                <?= e($site['adresse']['rue']) ?><br>
              <?php endif; ?>
              <?= e(trim(($site['adresse']['cp'] ?? '') . ' ' . ($site['adresse']['ville'] ?? ''))) ?>
            </span>
          </p>
          <?php if (($site['contact']['horaires'] ?? '') !== ''): ?>
            <p class="pied__ligne">
              <span class="pied__icone" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'horaires']) ?></span>
              <span><?= e($site['contact']['horaires']) ?></span>
            </p>
          <?php endif; ?>
          <?php if (($site['contact']['email'] ?? '') !== ''): ?>
            <p class="pied__ligne">
              <span class="pied__icone" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'courriel']) ?></span>
              <a href="mailto:<?= e($site['contact']['email']) ?>"><?= e($site['contact']['email']) ?></a>
            </p>
          <?php endif; ?>
        </address>
      </div>
    </div>

    <?php if (($site['pied']['seo'] ?? '') !== '' || ($site['pied']['proche_de'] ?? '') !== ''): ?>
      <div class="pied__seo">
        <?php if (($site['pied']['seo'] ?? '') !== ''): ?><p><?= e($site['pied']['seo']) ?></p><?php endif; ?>
        <?php if (($site['pied']['proche_de'] ?? '') !== ''): ?><p><?= e($site['pied']['proche_de']) ?></p><?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="pied__bas">
      <p><?= e(jetons($site['pied']['copyright'])) ?></p>
      <p class="pied__legal">
        <a href="<?= route('mentions-legales') ?>"><?= e(t('Mentions légales')) ?></a>
        <span aria-hidden="true"> · </span>
        <a href="#" data-cookies-reglages><?= e(t('Gestion des cookies')) ?></a>
      </p>
    </div>
  </div>
</footer>
