<section class="section section-tight" style="max-width:680px">
  <div class="box center">
    <div style="font-size:56px;line-height:1">📭</div>
    <h1 class="h3 mt-2">Se désinscrire</h1>
    <p class="lead" style="margin-left:auto;margin-right:auto">Vous ne souhaitez plus recevoir nos emails d'information (actualités du site, conseils, bilans hebdomadaires) ?</p>
    <form method="post" action="/e/u/<?= e($token) ?>" class="mt-3">
      <input type="hidden" name="confirm" value="1">
      <button type="submit" class="btn btn-ink">Confirmer ma désinscription</button>
    </form>
    <p class="muted small mt-3">Les emails liés à votre compte (demandes de devis, messages de clients) continueront d'être envoyés. Vous pouvez aussi gérer vos préférences depuis votre espace pro.</p>
  </div>
</section>
