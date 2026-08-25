<?php $a = (array) content('apply'); ?>
<section class="section" style="padding-top:clamp(150px,20vh,210px);text-align:center">
  <span class="glow glow--red" style="width:700px;height:700px;top:-260px;left:50%;transform:translateX(-50%);opacity:.35" aria-hidden="true"></span>
  <div class="container container--narrow" style="position:relative;z-index:2">
    <span class="chip chip--mint" data-reveal><span class="dot"></span> Candidature enregistrée</span>
    <h1 class="h1" style="margin:24px 0 18px" data-reveal><?= e($a['success_title'] ?? 'Merci !') ?></h1>
    <p class="lead" style="margin-inline:auto" data-reveal><?= e($a['success_text'] ?? '') ?></p>

    <div class="timeline" style="margin-top:52px;text-align:left">
      <div class="tl-item">
        <span class="tl-dot"><?= icon('check') ?></span>
        <div class="tl-body">
          <h3 class="h3">Votre dossier est reçu</h3>
          <p>Un accusé de réception vient de partir vers votre boîte mail.</p>
        </div>
      </div>
      <div class="tl-item">
        <span class="tl-dot">2</span>
        <div class="tl-body">
          <span class="tl-duration"><?= icon('clock') ?> Sous <?= e(settings('funnel.response_delay', '48 h')) ?></span>
          <h3 class="h3">Nous vous appelons</h3>
          <p>Un collaborateur vous contacte pour fixer votre rendez-vous stratégique et vérifier la disponibilité de votre secteur.</p>
        </div>
      </div>
      <div class="tl-item">
        <span class="tl-dot">3</span>
        <div class="tl-body">
          <h3 class="h3">On construit votre lancement</h3>
          <p>Statut, formation, outils, objectifs : tout est cadré avant votre première prospection.</p>
        </div>
      </div>
    </div>

    <div class="cluster" style="justify-content:center;margin-top:44px">
      <a class="btn btn--ghost" href="<?= e(url('actualites')) ?>">Lire nos analyses du marché</a>
      <a class="btn btn--ghost" href="<?= e(url('le-metier')) ?>">Découvrir le métier en détail</a>
    </div>

    <p class="small muted" style="margin-top:28px">
      Une urgence ? Appelez-nous au <a class="link-arrow" href="tel:<?= e(settings('company.phone_link')) ?>"><?= e(settings('company.phone')) ?></a>
    </p>
  </div>
</section>
