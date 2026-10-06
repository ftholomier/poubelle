<?php
/**
 * Quiz du club-house : la page du joueur (téléphone). Variables : $code (pré-rempli par le QR code),
 * $account (compte supporter ouvert sur ce téléphone), $me (pseudo et rang au championnat, ou null)
 */
$i18n = [
    'wait' => t('Regardez le grand écran…'), 'lobby' => t('C’est noté ! La partie va commencer.'), 'ready' => t('Prêt ?'),
    'sent' => t('Réponse envoyée !'), 'good' => t('Bonne réponse !'), 'bad' => t('Raté…'),
    'none' => t('Pas de réponse'), 'pts' => t('+{n} points'),
    'score' => t('{n} points'), 'qn' => t('Question {i} / {n}'), 'board' => t('Classement à l’écran'),
    'end' => t('Partie terminée !'), 'gone' => t('Vous n’êtes plus dans cette partie.'),
    'error' => t('Connexion perdue, nouvelle tentative…'),
    'mailed' => t('C’est fait : ce téléphone vous reconnaît, vos points comptent au championnat. Le lien envoyé à votre adresse vous retrouvera sur un autre appareil.'),
    'pending' => t('En attente du lien de l’e-mail : ouvrez-le sur ce téléphone et cette partie comptera au championnat.'),
    'member' => t('Championnat'), 'guestEnd' => t('Vous avez joué en invité. Pour compter au championnat la prochaine fois, laissez votre e-mail en rejoignant la partie.'),
    'champLink' => t('Voir le championnat'),
];
$champ = url('/interactif/quiz-live/championnat/');
?>
<section class="ql" data-ql data-lang="<?= e(\App\Services\I18n::lang()) ?>">
  <script type="application/json" id="ql-i18n"><?= json_encode($i18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <div class="ql__inner">
    <div class="ql__join" data-step="join">
      <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e(t('Quiz du club-house')) ?></span>
      <h1 class="ql__title"><?= e(t('Rejoindre la partie')) ?></h1>
      <p class="ql__lead"><?= e(t('Saisissez le code affiché sur le grand écran et choisissez un pseudo. Les questions s’affichent à l’écran, vous répondez ici.')) ?></p>
      <form class="ql__form" data-ql-form data-member="<?= $me ? '1' : '0' ?>" data-account="<?= $account ? '1' : '0' ?>">
        <label for="ql-code"><?= e(t('Code de la partie')) ?></label>
        <input id="ql-code" name="code" inputmode="numeric" pattern="\d{5}" maxlength="5" autocomplete="off" required value="<?= e($code) ?>" placeholder="12345">
        <?php if ($me): ?>
        <p class="ql__me" data-ql-me><?= e(t('Vous jouez sous le nom')) ?> <b><?= e($me['pseudo']) ?></b><?php if ($me['rank']): ?> · <?= e(t('{r} du championnat', ['r' => ordinal($me['rank']['rank'])])) ?><?php endif; ?>
          <button type="button" class="ql__link" data-ql-guest><?= e(t('Jouer en invité sous un autre nom')) ?></button></p>
        <?php endif; ?>
        <div class="ql__namebox" data-ql-namebox<?= $me ? ' hidden' : '' ?>>
          <label for="ql-name"><?= e(t('Votre pseudo')) ?></label>
          <input id="ql-name" name="name" maxlength="20" minlength="2" autocomplete="nickname"<?= $me ? '' : ' required' ?> placeholder="<?= e(t('ex. Lionceau25')) ?>">
          <?php if ($account && !$me): ?><p class="ql__hint"><?= e(t('Ce sera aussi votre pseudo au championnat du club-house.')) ?></p><?php endif; ?>
        </div>
        <?php if (!$account): ?>
        <div class="ql__mailbox" data-ql-mailbox>
          <label for="ql-email"><?= e(t('Votre e-mail')) ?> <i><?= e(t('facultatif, pour le championnat')) ?></i></label>
          <input id="ql-email" name="email" type="email" maxlength="120" autocomplete="email" placeholder="<?= e(t('prenom@exemple.fr')) ?>">
          <p class="ql__hint"><?= e(t('Avec votre e-mail, vos points comptent au championnat de la saison. Vous recevez un lien sécurisé qui garde votre place sur ce téléphone et vous la rend sur un autre. Sans e-mail, vous jouez en invité.')) ?></p>
        </div>
        <?php endif; ?>
        <button type="submit" class="qbtn"><?= e(t('C’est parti !')) ?></button>
        <p class="ql__err" data-ql-err role="alert" hidden></p>
      </form>
      <p class="ql__note"><?= e(t('Pseudo visible de toute la salle : restez fair-play.')) ?> <a href="<?= e($champ) ?>"><?= e(t('Voir le championnat')) ?></a></p>
    </div>
    <div class="ql__play" data-step="play" hidden>
      <div class="ql__bar"><b data-ql-name></b><span data-ql-score></span></div>
      <p class="ql__num" data-ql-num></p>
      <h2 class="ql__q" data-ql-q aria-live="polite"></h2>
      <div class="ql__timer" data-ql-timer hidden><span></span></div>
      <div class="ql__answers" data-ql-answers></div>
      <div class="ql__msg" data-ql-msg aria-live="polite"></div>
      <p class="ql__info" data-ql-info hidden></p>
      <a class="qbtn qbtn--sm ql__champ" href="<?= e($champ) ?>" data-ql-champ hidden><?= e(t('Voir le championnat')) ?></a>
    </div>
  </div>
</section>
