<?php use App\Services\AntiSpam; ?>
<section class="page-head" style="max-width:900px">
  <?= App\Core\View::partial('front/partials/crumbs', ['crumbs' => [['Accueil', '/'], ['Contact', '/contact/']]]) ?>
  <h1>On vous <span class="serif">écoute</span></h1>
  <p class="lead">Une question sur le site, votre fiche, un partenariat ? Écrivez-nous. Pour contacter un pro, utilisez directement le formulaire de sa fiche, ou <a href="/devis/">déposez une demande de devis</a>.</p>
</section>
<section class="section section-tight" style="max-width:900px">
  <form class="form form-card" method="post" action="/contact/" data-ajax data-protect="site_contact" novalidate>
    <?= AntiSpam::fields('site_contact') ?>
    <div class="choice-grid">
      <?php foreach (['visiteur' => 'Je cherche un pro', 'pro' => 'Je suis un pro', 'partenariat' => 'Partenariat / publicité', 'presse' => 'Presse', 'rgpd' => 'Mes données (RGPD)'] as $k => $l): ?>
        <label class="choice"><input type="radio" name="kind" value="<?= $k ?>"<?= $k === 'visiteur' ? ' checked' : '' ?>><span><?= e($l) ?></span></label>
      <?php endforeach; ?>
    </div>
    <div class="form-grid">
      <div class="field"><label for="c-name">Nom <span class="req">*</span></label><input id="c-name" type="text" name="name" required maxlength="80" autocomplete="name"></div>
      <div class="field"><label for="c-email">Email <span class="req">*</span></label><input id="c-email" type="email" name="email" required maxlength="120" autocomplete="email"></div>
    </div>
    <div class="field"><label for="c-subject">Sujet</label><input id="c-subject" type="text" name="subject" maxlength="120"></div>
    <div class="field"><label for="c-msg">Message <span class="req">*</span></label><textarea id="c-msg" name="message" required maxlength="5000"></textarea></div>
    <div class="row-wrap"><button class="btn btn-coral" type="submit">Envoyer</button><span class="pow-status"></span></div>
    <div data-form-result></div>
  </form>
</section>
