<?php $c = (array) settings('company'); $ct = (array) content('contact'); ?>
<?php partial('page-hero', ['eyebrow' => 'Contact', 'title' => $ct['title'] ?? '', 'lead' => $ct['lead'] ?? '']); ?>

<section class="section" style="padding-top:0">
  <div class="container">
    <div class="grid" style="grid-template-columns:minmax(0,1fr) minmax(0,.8fr);gap:clamp(20px,3vw,48px);align-items:start">
      <div class="funnel-card" data-reveal="left">
        <form class="stack" style="--gap:18px" method="post" action="<?= e(url('api/lead')) ?>"
              data-ajax data-success="Message envoyé, nous revenons vers vous rapidement." data-done="#contact-done">
          <?= Csrf::field() ?>
          <input type="hidden" name="origin" value="contact">
          <input class="honey" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">

          <div class="two-col">
            <div class="field">
              <label for="c-name">Nom et prénom <span class="req">*</span></label>
              <input class="input" id="c-name" name="name" type="text" required autocomplete="name" placeholder="Camille Martin">
            </div>
            <div class="field">
              <label for="c-phone">Téléphone</label>
              <input class="input" id="c-phone" name="phone" type="tel" autocomplete="tel" placeholder="06 12 34 56 78">
            </div>
          </div>
          <div class="field">
            <label for="c-email">E-mail <span class="req">*</span></label>
            <input class="input" id="c-email" name="email" type="email" required autocomplete="email" placeholder="camille@exemple.fr">
          </div>
          <div class="field">
            <label for="c-message">Votre message <span class="req">*</span></label>
            <textarea class="textarea" id="c-message" name="message" required rows="5"
                      placeholder="Votre question sur le métier, le statut, la rémunération ou votre secteur…"></textarea>
          </div>
          <button class="btn btn--lg btn--magnet" type="submit">Envoyer le message <?= icon('arrow') ?></button>
          <p class="small muted"><?= icon('lock') ?> Vos données ne sont jamais cédées à des tiers.</p>
        </form>

        <div id="contact-done" hidden style="text-align:center;padding:20px 0">
          <span class="chip chip--mint"><span class="dot"></span> Message reçu</span>
          <h2 class="h3" style="margin:18px 0 10px">Merci, c’est envoyé.</h2>
          <p class="muted">Nous revenons vers vous sous <?= e(settings('funnel.response_delay', '48 h')) ?>.</p>
        </div>
      </div>

      <aside class="stack" style="--gap:14px" data-reveal="right">
        <div class="card">
          <span class="benefit__icon"><?= icon('phone') ?></span>
          <h3 class="h4" style="margin:14px 0 6px">Par téléphone</h3>
          <a class="link-arrow" href="tel:<?= e($c['phone_link'] ?? '') ?>"><?= e($c['phone'] ?? '') ?></a>
        </div>
        <div class="card">
          <span class="benefit__icon"><?= icon('mail') ?></span>
          <h3 class="h4" style="margin:14px 0 6px">Par e-mail</h3>
          <a class="link-arrow" href="mailto:<?= e($c['email'] ?? '') ?>"><?= e($c['email'] ?? '') ?></a>
        </div>
        <div class="card">
          <span class="benefit__icon"><?= icon('pin') ?></span>
          <h3 class="h4" style="margin:14px 0 6px">Siège social</h3>
          <p class="muted small"><?= e($c['address'] ?? '') ?><br><?= e($c['zip'] ?? '') ?> <?= e($c['city'] ?? '') ?></p>
        </div>
        <div class="card" style="background:linear-gradient(150deg,rgba(230,47,67,.18),rgba(255,138,61,.06))">
          <h3 class="h4" style="margin-bottom:8px">Vous préférez aller droit au but ?</h3>
          <p class="muted small" style="margin-bottom:16px">Déposez votre candidature : nous vous rappelons sous <?= e(settings('funnel.response_delay', '48 h')) ?>.</p>
          <a class="btn btn--block btn--magnet" href="<?= e(url('candidater')) ?>" data-cta="contact-aside">Candidater <?= icon('arrow') ?></a>
        </div>
      </aside>
    </div>
  </div>
</section>

<?php partial('reviews'); ?>
