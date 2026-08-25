<?php $c = (array) settings('company'); ?>
<footer class="footer">
  <div class="container">
    <div class="footer__grid">
      <div>
        <a class="brand" href="<?= e(url('/')) ?>" aria-label="Suisse Immo — accueil">
          <?php partial('logo', ['class' => 'brand__logo']); ?>
          <span class="brand__tag">recrutement</span>
        </a>
        <p class="footer__about">
          Suisse Immo est un réseau d’agences immobilières situé à <?= e(implode(', ', (array) content('network.cities', []))) ?>.
          Devenez agent commercial immobilier au sein d’une agence à taille humaine, en pleine croissance, avec un accompagnement sur mesure
          dans les domaines juridique, commercial et digital.
        </p>
      </div>

      <div>
        <h4>Navigation</h4>
        <ul>
          <li><a href="<?= e(url('/')) ?>">Accueil</a></li>
          <li><a href="<?= e(url('le-reseau')) ?>">Le réseau</a></li>
          <li><a href="<?= e(url('le-metier')) ?>">Le métier</a></li>
          <li><a href="<?= e(url('actualites')) ?>">Actualités</a></li>
          <li><a href="<?= e(url('contact')) ?>">Contact</a></li>
        </ul>
      </div>

      <div>
        <h4>Rejoindre</h4>
        <ul>
          <li><a href="<?= e(url('candidater')) ?>">Candidater</a></li>
          <li><a href="<?= e(url('/')) ?>#simulateur">Simuler mes revenus</a></li>
          <li><a href="<?= e(url('/')) ?>#faq">Questions fréquentes</a></li>
          <li><a href="<?= e($c['main_site'] ?? '#') ?>" rel="noopener" target="_blank">Site Suisse Immo</a></li>
        </ul>
      </div>

      <div>
        <h4>Contact</h4>
        <ul>
          <li><a href="tel:<?= e($c['phone_link'] ?? '') ?>"><?= e($c['phone'] ?? '') ?></a></li>
          <li><a href="mailto:<?= e($c['email'] ?? '') ?>"><?= e($c['email'] ?? '') ?></a></li>
          <li><span class="muted"><?= e($c['address'] ?? '') ?><br><?= e($c['zip'] ?? '') ?> <?= e($c['city'] ?? '') ?></span></li>
        </ul>
        <a class="btn btn--ghost" style="margin-top:18px" href="<?= e(url('candidater')) ?>" data-cta="footer">Candidater <?= icon('arrow') ?></a>
      </div>
    </div>

    <div class="footer__bottom">
      <span>© <?= date('Y') ?> Recrutement Suisse Immo — Tous droits réservés</span>
      <span class="cluster">
        <a href="<?= e(url('mentions-legales')) ?>">Mentions légales</a>
        <a href="<?= e(url('politique-de-confidentialite')) ?>">Confidentialité</a>
      </span>
    </div>
    <p class="footer__legal">
      Les informations sur les risques auxquels les biens proposés par Suisse Immo sont exposés sont disponibles sur le site Géorisques :
      <a href="<?= e($c['georisques'] ?? '#') ?>" rel="noopener nofollow" target="_blank">www.georisques.gouv.fr</a>
    </p>
  </div>
</footer>
