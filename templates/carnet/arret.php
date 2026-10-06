<?php
/** Carnet : arrêt des e-mails d'anniversaire. Variables : $ok */
use App\Core\View;
?>
<?= View::partial('carnet/_head', ['title' => t('Rappels d’anniversaire'), 'intro' => $ok ? t('C’est noté : vous ne recevrez plus l’anniversaire de vos matchs par e-mail. Votre carnet, lui, reste intact.') : t('Ce lien ne fonctionne plus. Vous pouvez régler vos rappels depuis votre carnet.'), 'crumb' => t('Mon carnet')]) ?>
<div class="wrap cnwrap"><p><a class="btn btn--yellow" href="<?= e(url('/carnet/')) ?>"><?= e(t('Mon carnet du supporter')) ?></a></p></div>
