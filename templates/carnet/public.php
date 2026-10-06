<?php
/** Carnet : page publique sous pseudo. Variables : $c, $s */
use App\Core\View;
?>
<?= View::partial('carnet/_head', ['title' => t('Le carnet de {p}', ['p' => $c['pseudo']]), 'intro' => t('Les matchs du FCSM que {p} a vus au stade.', ['p' => $c['pseudo']]), 'crumb' => t('Carnet de supporter')]) ?>
<div class="wrap cnwrap">
  <?= View::partial('carnet/_bilan', ['s' => $s, 'mine' => false]) ?>
  <section class="cncard cncard--navy"><h2 class="cncard__t"><?= e(t('Et vous ?')) ?></h2><p><?= e(t('Créez votre carnet de supporter et comparez vos bilans.')) ?></p><a class="btn btn--yellow" href="<?= e(url('/carnet/')) ?>"><?= e(t('Mon carnet du supporter')) ?></a></section>
</div>
