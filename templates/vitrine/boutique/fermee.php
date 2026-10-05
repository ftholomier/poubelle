<?php /** Boutique fermée (pas encore ouverte par l'administrateur). */ use App\Vitrine\Host; ?>
<?= \App\Core\View::partial('vitrine/partials/page-head', ['title' => 'Boutique', 'lead' => 'La boutique de Sochaux Rétro ouvre bientôt : des objets aux couleurs jaune et bleu, personnalisés et fabriqués près de chez nous.', 'eyebrow' => 'Bientôt', 'crumbs' => [['Boutique', Host::url('/boutique/')]]]) ?>
<section class="section--tight"><div class="wrap"><p>Inscrivez-vous à la lettre d’information pour être prévenu de l’ouverture.</p></div></section>
