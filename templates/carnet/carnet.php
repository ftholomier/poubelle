<?php
/** Carnet du supporter : bilan personnel. Variables : $c (carnet), $s (Carnet::stats), $welcome, $poster (adresse du poster en boutique ou null) */
use App\Core\View;
$public = $c['public'] ? url('/carnet/p/' . $c['slug'] . '/') : '';
?>
<?= View::partial('carnet/_head', ['title' => t('Mon carnet du supporter'), 'intro' => '', 'crumb' => t('Mon carnet')]) ?>
<div class="wrap cnwrap" data-cn-page>
  <?php if ($welcome): ?><p class="alert alert--ok cnwelcome"><?= e(t('Votre carnet est ouvert sur cet appareil.')) ?></p><?php endif; ?>
  <?php if (!$c['confirmed']): ?><p class="alert cnwelcome"><?= e(t('Pensez à ouvrir le lien reçu par e-mail ({e}) : c’est lui qui vous permettra de retrouver votre carnet sur un autre appareil.', ['e' => $c['email']])) ?></p><?php endif; ?>
  <div class="cnactions">
    <a class="btn btn--yellow" href="<?= e(url('/carnet/saisons/')) ?>"><?= e(t('Ajouter des matchs')) ?></a>
    <?php if ($s['n']): ?><a class="btn btn--ghost" href="<?= e(url('/carnet/carte.png')) ?>?telecharger=1"><?= e(t('Télécharger ma carte')) ?></a><?php endif; ?>
    <?php if (!empty($poster) && $s['n'] >= \App\Shop\CarnetPoster::MIN): ?><a class="btn btn--navy" href="<?= e($poster) ?>"><?= e(t('Mon poster « Ma vie en jaune et bleu »')) ?></a><?php endif; ?>
  </div>

  <?php if (!$s['n']): ?>
    <section class="cncard"><p><?= e(t('Votre carnet est vide pour l’instant. Ajoutez vos matchs saison par saison, ou touchez « J’y étais ! » sur la fiche d’un match.')) ?></p></section>
  <?php else: ?>
    <?= View::partial('carnet/_bilan', ['s' => $s, 'mine' => true]) ?>
    <section class="cncard cnshare">
      <h2 class="cncard__t"><?= e(t('Partager')) ?></h2>
      <img class="cnshare__img" src="<?= e(url('/carnet/carte.png')) ?>?v=<?= (int) $s['n'] ?>" alt="<?= e(t('Ma carte de supporter')) ?>" width="600" height="315" loading="lazy">
      <p class="cnnote"><?= e(t('Téléchargez votre carte pour la publier où vous voulez. Vous pouvez aussi ouvrir une page publique, sous un pseudo : seuls vos matchs et votre bilan y apparaissent, jamais votre e-mail.')) ?></p>
      <form class="cnpub" data-cn-public>
        <label for="cn-pseudo"><?= e(t('Pseudo')) ?></label>
        <input id="cn-pseudo" name="pseudo" maxlength="30" value="<?= e($c['pseudo']) ?>" placeholder="<?= e(t('ex. Lionceau88')) ?>">
        <label class="cncheck"><input type="checkbox" name="on" <?= $c['public'] ? 'checked' : '' ?>> <?= e(t('Page publique ouverte')) ?></label>
        <button class="btn btn--navy btn--sm" type="submit"><?= e(t('Enregistrer')) ?></button>
        <p class="cnmail__msg" data-cn-msg role="status"><?php if ($public): ?><a href="<?= e($public) ?>"><?= e($public) ?></a><?php endif; ?></p>
      </form>
    </section>
  <?php endif; ?>

  <section class="cncard cnremind" data-cn-remind>
    <h2 class="cncard__t"><?= e(t('L’anniversaire de vos matchs')) ?></h2>
    <p><?= e(t('Le jour anniversaire d’un match de votre carnet, le musée vous le rappelle : « Il y a 30 ans jour pour jour, vous étiez au stade ».')) ?></p>
    <label class="cncheck"><input type="checkbox" data-cn-remind-email <?= !empty($c['remind_email']) ? 'checked' : '' ?>> <?= e(t('Par e-mail, à {e}', ['e' => $c['email']])) ?></label>
    <?php if (!$c['confirmed']): ?><p class="cnnote"><?= e(t('Les e-mails partiront dès que vous aurez ouvert une fois le lien reçu à cette adresse.')) ?></p><?php endif; ?>
    <div class="cnremind__push">
      <button type="button" class="btn btn--ghost btn--sm" data-cn-remind-push><?= e(t('Aussi en notification sur cet appareil')) ?></button>
      <?php $np = count((array) ($c['remind_push'] ?? [])); ?>
      <span class="cnnote" data-cn-remind-n><?= $np ? e(t('Notifications : {n} appareil(s)', ['n' => $np])) : '' ?></span>
      <?php if ($np): ?><button type="button" class="linkbtn cnnote" data-cn-remind-off><?= e(t('Arrêter les notifications')) ?></button><?php endif; ?>
    </div>
    <p class="cnmail__msg" data-cn-msg role="status"></p>
  </section>

  <details class="cnzone">
    <summary><?= e(t('Mon carnet et mes données')) ?></summary>
    <p class="cnnote"><?= e(t('Carnet relié à {e}. Pour l’ouvrir sur un autre appareil : page « Mon carnet » de cet appareil, « J’ai déjà un carnet », et un lien arrive à cette adresse.', ['e' => $c['email']])) ?></p>
    <button type="button" class="btn btn--ghost btn--sm" data-cn-delete><?= e(t('Supprimer mon carnet')) ?></button>
  </details>
</div>
