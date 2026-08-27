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
        <?php /* Le logo complet, dans sa version pour fond sombre : le pied
                 de page est le seul endroit où la marque est composée en
                 grand, et l'ardoise de la charte lui va bien. */ ?>
        <a class="pied__logo" href="<?= route('accueil') ?>" aria-label="<?= e($site['nom']) ?> — <?= e(t('Accueil')) ?>">
          <img class="pied__embleme"
               src="<?= asset($site['logo']['clair'] ?? 'assets/img/logo/logo-baron-clair.svg') ?>"
               alt="" width="1330" height="329" loading="lazy">
        </a>
        <p class="pied__accroche"><?= e(t('Le déplacement et les conseils sont gratuits. Parlons de votre extérieur.')) ?></p>
        <?php if ($tel !== ''): ?>
          <a class="pied__tel" href="<?= e(tel_lien($tel)) ?>"><?= e($tel) ?></a>
        <?php endif; ?>

        <?php /* Les réseaux sont repris ici, en toutes lettres : dans la
                 barre du bas ils sont réduits à leur marque, ce qui suffit à
                 les reconnaître mais pas à donner envie de les suivre. */ ?>
        <?php $suivre = array_filter([
            'facebook'  => (string) ($site['reseaux']['facebook'] ?? ''),
            'instagram' => (string) ($site['reseaux']['instagram'] ?? ''),
            'linkedin'  => (string) ($site['reseaux']['linkedin'] ?? ''),
        ]); ?>
        <?php if ($suivre !== []): ?>
          <p class="pied__suivre">
            <?php foreach ($suivre as $nom => $url): ?>
              <a href="<?= e($url) ?>" target="_blank" rel="noopener me">
                <span aria-hidden="true"><?= $view->partial('icones', ['nom' => $nom]) ?></span>
                <?= e(t('Suivre sur')) ?> <?= e(ucfirst($nom)) ?>
              </a>
            <?php endforeach; ?>
          </p>
        <?php endif; ?>
      </div>

      <nav class="pied__nav" aria-label="Navigation pied de page">
        <h2><?= e(t('Le site')) ?></h2>
        <ul class="pied__liens">
          <?php foreach ($site['menu'] as $item): ?>
            <li><a href="<?= lien($item['url']) ?>"><?= e($item['libelle']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </nav>

      <div class="pied__services">
        <h2><?= e(t('Nos prestations')) ?></h2>
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
      <?php /* Les marques remplacent leur nom écrit : à cette taille elles
               se reconnaissent plus vite qu'elles ne se lisent. Le nom reste
               dans l'intitulé, pour les lecteurs d'écran. */ ?>
      <?php if ($reseaux !== []): ?>
        <p class="pied__reseaux">
          <?php foreach ($reseaux as $nom => $url): ?>
            <a href="<?= e($url) ?>" target="_blank" rel="noopener"
               aria-label="<?= e($nom) ?>" title="<?= e($nom) ?>">
              <?= $view->partial('icones', ['nom' => mb_strtolower($nom)]) ?>
            </a>
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
