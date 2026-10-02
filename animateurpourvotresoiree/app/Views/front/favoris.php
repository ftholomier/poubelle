<section class="page-head">
  <?= App\Core\View::partial('front/partials/crumbs', ['crumbs' => [['Accueil', '/'], ['Mes favoris', '/favoris/']]]) ?>
  <h1>Mes <span class="serif">favoris</span></h1>
  <p class="lead">Les pros que vous avez gardés sous le coude (enregistrés sur cet appareil). Envoyez-leur une demande groupée en un clic.</p>
</section>
<section class="section section-tight" data-favs-page>
  <div class="row-wrap"><a class="btn btn-coral hidden" data-favs-devis href="/devis/">Demander un devis à mes favoris →</a><button type="button" class="btn btn-sm btn-ghost hidden" data-favs-clear>Vider la liste</button></div>
  <div class="pro-grid" data-favs-grid></div>
  <div class="empty hidden" data-favs-empty>Aucun favori pour l'instant… Cliquez sur ♥ sur une fiche pour la retrouver ici. <a class="link" href="/recherche/">Trouver un pro</a></div>
</section>
