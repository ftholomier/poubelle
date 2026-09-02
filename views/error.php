<?php use App\View; ?>
<section class="section section--center" data-section id="erreur">
    <div class="shell">
        <p class="eyebrow">Erreur <?= View::e($code ?? 500) ?></p>
        <h1 class="title title--lg" data-reveal="words"><?= View::e($message ?? '') ?></h1>
        <p class="section__body"><a class="link-arrow" href="/">Revenir à l'accueil</a></p>
    </div>
</section>
