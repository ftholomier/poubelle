<section class="section" style="padding-top:clamp(160px,22vh,230px);text-align:center">
  <span class="glow glow--red" style="width:600px;height:600px;top:-200px;left:50%;transform:translateX(-50%);opacity:.3" aria-hidden="true"></span>
  <div class="container container--narrow" style="position:relative;z-index:2">
    <div class="display grad-text" style="font-size:clamp(5rem,18vw,11rem);line-height:.9">404</div>
    <h1 class="h2" style="margin:20px 0 16px">Cette page n’existe plus.</h1>
    <p class="lead" style="margin-inline:auto">Le site a été refondu : certaines anciennes adresses ont changé. Reprenez par l’accueil, ou allez droit au but.</p>
    <div class="cluster" style="justify-content:center;margin-top:32px">
      <a class="btn btn--lg btn--magnet" href="<?= e(url('candidater')) ?>" data-cta="404">Candidater <?= icon('arrow') ?></a>
      <a class="btn btn--ghost btn--lg" href="<?= e(url('/')) ?>">Retour à l’accueil</a>
    </div>
  </div>
</section>
