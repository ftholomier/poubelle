<?php
/** @var ?array $req */
$n = $req ? count($req['recipients'] ?? []) : 0;
$diffused = $req && $req['status'] === 'diffused';
?>
<section class="error-page" style="padding-top:70px">
  <div style="font-size:84px;line-height:1">🎉</div>
  <h1 class="h2 mt-3">Demande envoyée !</h1>
  <p class="lead" style="margin-left:auto;margin-right:auto">
    <?php if ($diffused && $n > 0): ?>Votre demande a été transmise à <strong><?= $n ?> professionnel<?= $n > 1 ? 's' : '' ?></strong> de votre secteur. Ils vont vous contacter directement.
    <?php else: ?>Merci ! Votre demande va être transmise aux professionnels de votre secteur après une rapide vérification.<?php endif; ?>
    Un email de confirmation vient de vous être envoyé.
  </p>
  <div class="steps" style="text-align:left;margin-top:44px">
    <div class="step"><span class="num" style="--n:var(--white);font-size:64px">01</span><h3>Surveillez vos emails</h3><p>Les réponses arrivent en général en moins de 24 h (pensez aux courriers indésirables).</p></div>
    <div class="step"><span class="num" style="--n:var(--yellow);font-size:64px">02</span><h3>Comparez</h3><p>Prix, formules, avis, matériel : prenez le temps d'échanger avec chacun.</p></div>
    <div class="step"><span class="num" style="--n:var(--coral);font-size:64px">03</span><h3>Réservez</h3><p>Signez un devis ou un contrat clair, et profitez de la fête !</p></div>
  </div>
  <div class="row-wrap mt-4" style="justify-content:center"><a class="btn btn-ink" href="/recherche/">Parcourir les pros</a><a class="btn" href="/blog/">Nos conseils d'organisation</a></div>
</section>
