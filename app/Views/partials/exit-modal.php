<div class="modal" id="exit-modal" role="dialog" aria-modal="true" aria-labelledby="exit-title">
  <div class="modal__box">
    <button class="modal__close" type="button" data-close aria-label="Fermer"><?= icon('close') ?></button>
    <span class="eyebrow"><?= icon('sparkle') ?> Un instant</span>
    <h2 class="h3" id="exit-title" style="margin:14px 0 10px"><?= e(settings('funnel.exit_intent_title', 'Avant de partir…')) ?></h2>
    <p class="lead" style="font-size:1rem"><?= e(settings('funnel.exit_intent_text')) ?></p>

    <form class="stack" style="--gap:14px;margin-top:22px" method="post" action="<?= e(url('api/lead')) ?>"
          data-ajax data-success="C’est noté ! Nous vous envoyons le mémo par e-mail." data-done="#exit-done">
      <?= Csrf::field() ?>
      <input type="hidden" name="origin" value="exit-intent">
      <input class="honey" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div class="field">
        <label for="exit-name">Prénom <span class="req">*</span></label>
        <input class="input" id="exit-name" name="name" type="text" autocomplete="given-name" required placeholder="Camille">
      </div>
      <div class="field">
        <label for="exit-email">E-mail <span class="req">*</span></label>
        <input class="input" id="exit-email" name="email" type="email" autocomplete="email" required placeholder="camille@exemple.fr">
      </div>
      <button class="btn btn--block btn--magnet" type="submit">Recevoir le mémo <?= icon('arrow') ?></button>
      <p class="small muted" style="text-align:center">Aucune prospection agressive. Désinscription en un clic.</p>
    </form>

    <div id="exit-done" hidden style="text-align:center;padding:12px 0">
      <p class="h3" style="margin-bottom:8px">Parfait.</p>
      <p class="muted">Le mémo part vers votre boîte mail. À très vite chez Suisse Immo.</p>
      <a class="btn" style="margin-top:18px" href="<?= e(url('candidater')) ?>" data-cta="exit-modal">Candidater maintenant <?= icon('arrow') ?></a>
    </div>
  </div>
</div>
