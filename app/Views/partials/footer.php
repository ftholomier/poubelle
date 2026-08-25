<?php $c = (array) settings('company'); ?>
<footer class="footer">
  <div class="container">
    <div class="footer__grid">
      <div>
        <a class="brand" href="<?= e(url('/')) ?>">
          <svg class="brand__mark" viewBox="0 0 48 48" fill="none" aria-hidden="true">
            <rect width="48" height="48" rx="13" fill="url(#bgf)"/>
            <path d="M14 30V20.5L24 13l10 7.5V30" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M19.5 35v-9h9v9" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
            <defs><linearGradient id="bgf" x1="0" y1="0" x2="48" y2="48"><stop stop-color="#E62F43"/><stop offset="1" stop-color="#FF8A3D"/></linearGradient></defs>
          </svg>
          <span class="brand__text">
            <span class="brand__name">Suisse Immo</span>
            <span class="brand__sub">Recrutement</span>
          </span>
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
        <a href="<?= e(url('admin')) ?>">Espace admin</a>
      </span>
    </div>
    <p class="footer__legal">
      Les informations sur les risques auxquels les biens proposés par Suisse Immo sont exposés sont disponibles sur le site Géorisques :
      <a href="<?= e($c['georisques'] ?? '#') ?>" rel="noopener nofollow" target="_blank">www.georisques.gouv.fr</a>
    </p>
  </div>
</footer>
