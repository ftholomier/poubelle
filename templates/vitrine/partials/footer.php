<?php
/** Pied de page du site de l'association : appel à rejoindre, plan, newsletter, réseaux, mentions. */

use App\Vitrine\Content;
use App\Vitrine\Host;
use App\Vitrine\Site;

$social = Site::social();
$actions = Content::actions();
$email = Site::email();
?>
<footer class="vf">
  <div class="vf__join">
    <div class="wrap vf__join-in">
      <p class="vf__join-t">Écrivons ensemble la suite de l’histoire.</p>
      <div class="row gap-14">
        <a class="btn btn--navy" href="<?= e(Host::url('/nous-soutenir/adherer/')) ?>">Adhérer</a>
        <a class="btn btn--ghost" href="<?= e(Host::url('/nous-soutenir/benevolat/')) ?>">Devenir bénévole</a>
        <a class="btn btn--ghost" href="<?= e(Host::museum('/faire-un-don/')) ?>" target="_blank" rel="noopener">♥ Faire un don</a>
      </div>
    </div>
  </div>
  <div class="vf__main wrap">
    <div class="vf__brand">
      <a href="<?= e(Host::url('/')) ?>" class="vf__logo"><img src="/assets/img/logo-sochaux-retro.png" alt="" width="70" height="79" loading="lazy"><span><b><?= e(Site::name()) ?></b><small>L’association</small></span></a>
      <?php if (($tag = Site::tagline()) !== ''): ?><p class="vf__tag"><?= e($tag) ?></p><?php endif; ?>
      <?php if ($social): ?>
      <div class="vsocial" aria-label="Réseaux sociaux">
        <?php foreach ($social as $k => [$label, $href]): ?>
          <a href="<?= e($href) ?>" rel="noopener" target="_blank" aria-label="<?= e($label) ?>" title="<?= e($label) ?>"><?= \App\Core\View::partial('vitrine/partials/icon', ['name' => $k]) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <nav class="vf__col" aria-label="L’association">
      <h2>L’association</h2>
      <a href="<?= e(Host::url('/association/')) ?>">Qui sommes-nous</a>
      <a href="<?= e(Host::url('/association/equipe/')) ?>">L’équipe</a>
      <a href="<?= e(Host::url('/association/statuts-et-documents/')) ?>">Statuts et documents</a>
      <a href="<?= e(Host::url('/actualites/')) ?>">Actualités</a>
      <a href="<?= e(Host::url('/agenda/')) ?>">Agenda</a>
      <a href="<?= e(Host::url('/partenaires/')) ?>">Partenaires</a>
      <a href="<?= e(Host::url('/presse/')) ?>">Presse</a>
    </nav>
    <nav class="vf__col" aria-label="Nos actions">
      <h2>Nos actions</h2>
      <?php foreach ($actions as $a): ?><a href="<?= e(Host::url('/nos-actions/' . $a['slug'] . '/')) ?>"><?= e($a['menu'] ?? $a['title']) ?></a><?php endforeach; ?>
    </nav>
    <div class="vf__col vf__news">
      <h2>La newsletter</h2>
      <p>« Ce jour-là » : chaque semaine, les matchs du passé et les nouvelles du musée.</p>
      <?= \App\Core\View::partial('vitrine/partials/newsletter-form', ['id' => 'nl-foot', 'compact' => true]) ?>
      <p class="vf__contact"><a href="<?= e(Host::url('/contact/')) ?>">Nous contacter →</a><?php if ($email !== ''): ?><br><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php endif; ?></p>
    </div>
  </div>
  <div class="vf__bar">
    <span>© <?= date('Y') ?> <?= e(Site::name()) ?></span>
    <a href="<?= e(Host::url('/mentions-legales/')) ?>">Mentions légales</a>
    <a href="<?= e(Host::url('/confidentialite/')) ?>">Confidentialité</a>
    <a href="<?= e(Host::url('/cookies/')) ?>">Cookies</a>
    <a href="<?= e(Host::url('/plan-du-site/')) ?>">Plan du site</a>
    <a class="vf__museum" href="<?= e(Host::museum('/')) ?>" target="_blank" rel="noopener">Le musée en ligne ↗</a>
  </div>
</footer>
