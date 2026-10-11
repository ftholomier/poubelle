<?php /** Boutique fermée (pas encore ouverte par l'administrateur). */ use App\Shop\ShopPages; ?>
<?= \App\Core\View::partial('boutique/_head', ['title' => 'Boutique', 'lead' => club('La boutique {site_de} ouvre bientôt : des objets aux couleurs {colors}, personnalisés et fabriqués près de chez nous.'), 'eyebrow' => 'Bientôt', 'crumbs' => [['Boutique', ShopPages::u('/boutique/')]]]) ?>
<section class="section--tight"><div class="wrap"><p>Inscrivez-vous à la lettre d’information pour être prévenu de l’ouverture.</p></div></section>
