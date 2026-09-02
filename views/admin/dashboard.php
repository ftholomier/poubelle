<?php use App\View; ?>
<h1 class="admin__title">Tableau de bord</h1>

<div class="cards-grid">
    <section class="panel">
        <h2 class="panel__title">Contenu</h2>
        <p class="panel__figure"><?= count($pages) ?></p>
        <p class="panel__label">page<?= count($pages) > 1 ? 's' : '' ?>, <?= (int) $sectionCount ?> sections</p>
        <p><a class="link" href="/admin/pages">Voir les pages et leurs dessins →</a></p>
    </section>

    <section class="panel">
        <h2 class="panel__title">Couleur dominante</h2>
        <p class="panel__swatches">
            <span style="background: <?= View::e($site['theme']['accent']) ?>"></span>
            <span style="background: <?= View::e($site['theme']['accent2']) ?>"></span>
            <span style="background: <?= View::e($site['theme']['accent3']) ?>"></span>
        </p>
        <p class="panel__label">
            <?= View::e($site['theme']['dominant']) ?> ·
            harmonie « <?= View::e($site['theme']['harmony']) ?> »
        </p>
        <p><a class="link" href="/admin/theme">Changer la couleur du site →</a></p>
    </section>

    <section class="panel">
        <h2 class="panel__title">Atelier de formes</h2>
        <p class="panel__label">
            Composez un dessin en particules, réglez-le en direct, puis affectez-le
            à la section de votre choix.
        </p>
        <p><a class="link" href="/admin/formes">Ouvrir l'atelier →</a></p>
    </section>
</div>

<section class="panel panel--wide">
    <h2 class="panel__title">Ajouter une page</h2>
    <p>
        Déposez un fichier <code>content/pages/mapage.json</code>. Il apparaîtra
        aussitôt dans le menu du site et dans ce back-office. La clé
        <code>order</code> fixe sa position dans le menu, <code>inNav: false</code>
        la garde hors du menu.
    </p>
</section>
