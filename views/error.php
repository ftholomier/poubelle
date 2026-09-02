<?php use App\View; ?>
<section class="section section--center" data-section id="erreur">
    <div class="shell">
        <p class="eyebrow" data-reveal>Erreur <?= View::e($code ?? 500) ?></p>
        <h1 class="title title--lg">
            <span class="title__line" data-reveal="words"><?= View::e($message ?? '') ?></span>
        </h1>
        <p class="section__body section__body--center" data-reveal>
            <a class="link-arrow" href="/" data-internal>Revenir à l'accueil</a>
        </p>
    </div>
</section>
