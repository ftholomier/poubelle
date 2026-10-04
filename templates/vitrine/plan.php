<?php
/** Plan du site. Variables : $actions, $news, $events */
use App\Vitrine\Host;

$sec = [
    'L’association' => [['Qui sommes-nous', '/association/'], ['L’équipe', '/association/equipe/'], ['Statuts et documents', '/association/statuts-et-documents/'], ['Partenaires', '/partenaires/'], ['Presse', '/presse/']],
    'Nos actions' => array_merge([['Toutes nos actions', '/nos-actions/']], array_map(fn ($a) => [$a['title'], '/nos-actions/' . $a['slug'] . '/'], $actions)),
    'Actualités et agenda' => array_merge([['Toutes les actualités', '/actualites/'], ['L’agenda', '/agenda/']], array_map(fn ($n) => [$n['title'], '/actualites/' . $n['slug'] . '/'], array_slice($news, 0, 12))),
    'Nous soutenir' => [['Nous soutenir', '/nous-soutenir/'], ['Adhérer', '/nous-soutenir/adherer/'], ['Devenir bénévole', '/nous-soutenir/benevolat/'], ['Contact', '/contact/']],
    'Informations' => [['Mentions légales', '/mentions-legales/'], ['Confidentialité', '/confidentialite/'], ['Cookies', '/cookies/']],
];
?>
<?= \App\Core\View::partial('vitrine/partials/page-head', ['title' => 'Plan du site', 'lead' => 'Toutes les pages du site de l’association.', 'crumbs' => [['Plan du site', Host::url('/plan-du-site/')]]]) ?>
<section class="section">
  <div class="wrap vplan">
    <?php foreach ($sec as $title => $links): ?>
      <div><h2 class="h-3"><?= e($title) ?></h2><ul><?php foreach ($links as [$l, $h]): ?><li><a href="<?= e(Host::url($h)) ?>"><?= e($l) ?></a></li><?php endforeach; ?></ul></div>
    <?php endforeach; ?>
    <div><h2 class="h-3">Le musée en ligne</h2><ul><li><a href="<?= e(Host::museum('/')) ?>">Accueil du musée ↗</a></li><li><a href="<?= e(Host::museum('/faire-un-don/')) ?>">Faire un don ↗</a></li><li><a href="<?= e(Host::museum('/contribuer/')) ?>">Contribuer ↗</a></li></ul></div>
  </div>
</section>
