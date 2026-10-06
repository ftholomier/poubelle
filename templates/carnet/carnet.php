<?php
/** Carnet du supporter : bilan personnel. Variables : $c (carnet), $s (Carnet::stats), $welcome, $poster (adresse du poster en boutique ou null) */
use App\Core\View;
$public = $c['public'] ? \App\Services\Carnet::publicUrl($c) : '';
?>
<?= View::partial('carnet/_head', ['title' => t('Mon carnet du supporter'), 'intro' => '', 'crumb' => t('Mon carnet')]) ?>
<div class="wrap cnwrap" data-cn-page>
  <?php if ($welcome): ?><p class="alert alert--ok cnwelcome"><?= e(t('Votre carnet est ouvert sur cet appareil.')) ?></p><?php endif; ?>
  <?php if (!$c['confirmed']): ?><p class="alert cnwelcome"><?= e(t('Pensez à ouvrir le lien reçu par e-mail ({e}) : c’est lui qui vous permettra de retrouver votre carnet sur un autre appareil.', ['e' => $c['email']])) ?></p><?php endif; ?>
  <div class="cnactions">
    <a class="btn btn--yellow" href="<?= e(url('/carnet/saisons/')) ?>"><?= e(t('Ajouter des matchs')) ?></a>
    <?php if ($s['n']): ?><a class="btn btn--ghost" href="#ma-carte"><?= e(t('Télécharger ma carte')) ?></a><?php endif; ?>
    <?php if ($public): ?><a class="btn btn--ghost" href="<?= e($public) ?>" target="_blank" rel="noopener"><?= e(t('Voir ma page publique')) ?> ↗</a><?php endif; ?>
    <?php if (!empty($poster) && $s['n'] >= \App\Shop\CarnetPoster::MIN): ?><a class="btn btn--navy" href="<?= e($poster) ?>"><?= e(t('Mon poster « Ma vie en jaune et bleu »')) ?></a><?php endif; ?>
    <?php if ($qr = \App\Services\QuizChampionship::rankOf($c['id'])): ?><a class="btn btn--ghost" href="<?= e(url('/interactif/quiz-live/championnat/')) ?>">★ <?= e(t('Championnat du club-house : {r}', ['r' => ordinal($qr['rank'])])) ?></a><?php endif; ?>
  </div>

  <?php if (!$s['n']): ?>
    <section class="cncard"><p><?= e(t('Votre carnet est vide pour l’instant. Ajoutez vos matchs saison par saison, ou touchez « J’y étais ! » sur la fiche d’un match.')) ?></p></section>
  <?php else: ?>
    <?= View::partial('carnet/_bilan', ['s' => $s, 'mine' => true]) ?>
    <section class="cncard cnshare" id="ma-carte">
      <h2 class="cncard__t"><?= e(t('Ma carte de supporter')) ?></h2>
      <p class="cnnote"><?= e(t('Deux designs au choix, en PDF (format carte postale, 14,8 × 10,5 cm) : à imprimer, à envoyer, à publier où vous voulez.')) ?></p>
      <?php $v = substr(md5(implode(',', array_keys($c['matches'])) . '|' . \App\Shop\CarnetCard::pseudo($c)), 0, 8); ?>
      <div class="cncards">
        <?php foreach (['a' => t('Charte du musée'), 'b' => t('Billet de match')] as $st => $lab): ?>
          <a class="cncards__one" href="<?= e(url('/carnet/carte-' . $st . '.pdf')) ?>" download>
            <img src="<?= e(url('/carnet/carte-' . $st . '.svg')) ?>?v=<?= e($v) ?>" alt="<?= e(t('Ma carte de supporter')) ?> · <?= e($lab) ?>" width="592" height="420" loading="lazy">
            <span class="btn btn--navy btn--sm"><?= e($lab) ?> · <?= e(t('Télécharger le PDF')) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <h3 class="cncard__t cncard__t--sub"><?= e(t('Ma page publique')) ?></h3>
      <p class="cnnote"><?= e(t('Ouvrez une page publique, sous un pseudo : seuls vos matchs et votre bilan y apparaissent, jamais votre e-mail. Le pseudo figure aussi sur votre carte.')) ?></p>
      <form class="cnpub" data-cn-public>
        <label for="cn-pseudo"><?= e(t('Pseudo')) ?></label>
        <input id="cn-pseudo" name="pseudo" maxlength="30" value="<?= e($c['pseudo']) ?>" placeholder="<?= e(t('ex. Lionceau88')) ?>">
        <label class="cncheck"><input type="checkbox" name="on" <?= $c['public'] ? 'checked' : '' ?>> <?= e(t('Page publique ouverte')) ?></label>
        <button class="btn btn--navy btn--sm" type="submit"><?= e(t('Enregistrer')) ?></button>
        <p class="cnmail__msg" data-cn-msg role="status"><?php if ($public): ?><a href="<?= e($public) ?>" target="_blank" rel="noopener"><?= e(t('Voir ma page publique')) ?> ↗</a><?php endif; ?></p>
      </form>
    </section>
  <?php endif; ?>

  <section class="cncard cnremind" data-cn-remind>
    <h2 class="cncard__t"><?= e(t('Jour pour jour : vos matchs d’il y a 10, 20, 30 ans')) ?></h2>
    <p><?= e(t('Le jour anniversaire d’un match de votre carnet, le musée vous le rappelle : « Il y a 30 ans jour pour jour, vous étiez au stade ». Et le jour de votre anniversaire, un petit mot avec votre plus beau souvenir au stade.')) ?></p>
    <?php [$bm, $bd] = isset($c['birthday']) ? array_map('intval', explode('-', (string) $c['birthday'])) : [0, 0]; ?>
    <form class="cnbday" data-cn-bday>
      <span class="cnbday__l"><?= e(t('Mon anniversaire')) ?></span>
      <select name="day" aria-label="<?= e(t('Jour')) ?>"><option value="0">—</option><?php for ($i = 1; $i <= 31; $i++): ?><option value="<?= $i ?>"<?= $i === $bd ? ' selected' : '' ?>><?= $i ?></option><?php endfor; ?></select>
      <select name="month" aria-label="<?= e(t('Mois')) ?>"><option value="0">—</option><?php foreach (\App\Services\I18n::isEn() ? ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] : ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'] as $k => $mn): ?><option value="<?= $k + 1 ?>"<?= $k + 1 === $bm ? ' selected' : '' ?>><?= e($mn) ?></option><?php endforeach; ?></select>
      <button class="btn btn--navy btn--sm" type="submit"><?= e(t('Enregistrer')) ?></button>
      <span class="cnnote"><?= e(t('Le jour et le mois seulement, jamais l’année. Le message part par e-mail ou notification, comme les rappels de vos matchs.')) ?></span>
    </form>
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
    <?php $others = \App\Services\Carnet::otherAccess($c); ?>
    <p class="cnnote" data-cn-others><?= $others
        ? e(tn($others, 'Ce carnet est aussi accessible depuis {n} autre appareil ou lien envoyé par e-mail. Un doute (téléphone perdu, carnet ouvert par quelqu’un d’autre avec votre adresse) ? Déconnectez-les : seul cet appareil gardera l’accès.', 'Ce carnet est aussi accessible depuis {n} autres appareils ou liens envoyés par e-mail. Un doute (téléphone perdu, carnet ouvert par quelqu’un d’autre avec votre adresse) ? Déconnectez-les : seul cet appareil gardera l’accès.'))
        : e(t('Ce carnet n’est ouvert que sur cet appareil.')) ?></p>
    <?php if ($others): ?><button type="button" class="btn btn--ghost btn--sm" data-cn-logout><?= e(t('Se déconnecter des autres appareils')) ?></button><?php endif; ?>
    <button type="button" class="btn btn--ghost btn--sm" data-cn-delete><?= e(t('Supprimer mon carnet')) ?></button>
  </details>
</div>
