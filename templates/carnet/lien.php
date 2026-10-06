<?php
/** Carnet : lien invalide ou remplacé. */
use App\Core\View;
?>
<?= View::partial('carnet/_head', ['title' => t('Ce lien ne fonctionne plus'), 'intro' => t('Il a peut-être été copié en partie, ou le carnet a été supprimé. Recevez un nouveau lien à l’adresse de votre carnet.'), 'crumb' => t('Mon carnet')]) ?>
<div class="wrap cnwrap"><section class="cncard"><?= View::partial('carnet/_email', ['mode' => 'renvoyer', 'mid' => 0]) ?></section></div>
