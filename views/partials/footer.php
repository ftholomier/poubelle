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
        <a class="pied__logo marque marque--clair" href="<?= route('accueil') ?>" aria-label="<?= e($site['nom']) ?> — Accueil">
          <img class="marque__embleme"
               src="<?= asset($site['logo']['principal'] ?? 'assets/img/logo/logo-trehant.png') ?>"
               alt="" aria-hidden="true" width="500" height="454" loading="lazy">
          <span class="marque__mots">
            <span class="marque__nom"><?= e($site['nom']) ?></span>
            <span class="marque__baseline"><?= e($site['baseline']) ?></span>
          </span>
        </a>
        <p class="pied__accroche"><?= e(t('Contactez-nous pour échanger sur votre projet.')) ?></p>
        <?php if ($tel !== ''): ?>
          <a class="pied__tel" href="<?= e(tel_lien($tel)) ?>"><?= e($tel) ?></a>
        <?php endif; ?>
        <?php /* Le visuel « Fabriqué en France / RGE » est celui de
                 l'entreprise, repris tel quel. */ ?>
        <img class="pied__france" src="<?= asset('assets/img/logo/fabrique-en-france.png') ?>"
             alt="<?= e(t('Fabriqué en France — entreprise RGE')) ?>"
             width="114" height="110" loading="lazy">
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
          <?php /* L'entreprise tient trois ateliers : le siège vient de
                   adresse, les autres de adresses_secondaires. Une adresse
                   partiellement renseignée ne doit laisser ni ligne vide ni
                   espace orphelin. */ ?>
          <?php
          $sites = array_merge([$site['adresse']], $site['adresses_secondaires'] ?? []);
          ?>
          <p class="pied__ligne">
            <span class="pied__icone" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'adresse']) ?></span>
            <span>
              <?= e($site['nom']) ?><br>
              <?php foreach ($sites as $i => $lieu): ?>
                <?php if ($i > 0): ?><span class="pied__separateur"></span><?php endif; ?>
                <?php if (($lieu['rue'] ?? '') !== ''): ?><?= e($lieu['rue']) ?><br><?php endif; ?>
                <?= e(trim(($lieu['cp'] ?? '') . ' ' . ($lieu['ville'] ?? ''))) ?><br>
              <?php endforeach; ?>
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
      <?php $reseaux = array_filter([
          'Facebook'  => (string) ($site['reseaux']['facebook'] ?? ''),
          'Instagram' => (string) ($site['reseaux']['instagram'] ?? ''),
          'LinkedIn'  => (string) ($site['reseaux']['linkedin'] ?? ''),
      ]); ?>
      <?php if ($reseaux !== []): ?>
        <p class="pied__reseaux">
          <?php foreach ($reseaux as $nom => $url): ?>
            <a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($nom) ?></a>
          <?php endforeach; ?>
        </p>
      <?php endif; ?>
      <p class="pied__legal">
        <a href="<?= route('mentions-legales') ?>"><?= e(t('Mentions légales')) ?></a>
        <span aria-hidden="true"> · </span>
        <a href="#" data-cookies-reglages><?= e(t('Gestion des cookies')) ?></a>
      </p>
    </div>
  </div>
</footer>
