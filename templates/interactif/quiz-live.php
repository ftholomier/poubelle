<?php
/** Quiz du club-house : la page du joueur (téléphone). Variables : $code (pré-rempli par le QR code) */
$i18n = [
    'wait' => t('Regardez le grand écran…'), 'lobby' => t('C’est noté ! La partie va commencer.'), 'ready' => t('Prêt ?'),
    'sent' => t('Réponse envoyée !'), 'good' => t('Bonne réponse !'), 'bad' => t('Raté…'),
    'none' => t('Pas de réponse'), 'pts' => t('+{n} points'),
    'score' => t('{n} points'), 'qn' => t('Question {i} / {n}'), 'board' => t('Classement à l’écran'),
    'end' => t('Partie terminée !'), 'gone' => t('Vous n’êtes plus dans cette partie.'),
    'error' => t('Connexion perdue, nouvelle tentative…'),
];
?>
<section class="ql" data-ql data-lang="<?= e(\App\Services\I18n::lang()) ?>">
  <script type="application/json" id="ql-i18n"><?= json_encode($i18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <div class="ql__inner">
    <div class="ql__join" data-step="join">
      <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e(t('Quiz du club-house')) ?></span>
      <h1 class="ql__title"><?= e(t('Rejoindre la partie')) ?></h1>
      <p class="ql__lead"><?= e(t('Saisissez le code affiché sur le grand écran et choisissez un pseudo. Les questions s’affichent à l’écran, vous répondez ici.')) ?></p>
      <form class="ql__form" data-ql-form>
        <label for="ql-code"><?= e(t('Code de la partie')) ?></label>
        <input id="ql-code" name="code" inputmode="numeric" pattern="\d{5}" maxlength="5" autocomplete="off" required value="<?= e($code) ?>" placeholder="12345">
        <label for="ql-name"><?= e(t('Votre pseudo')) ?></label>
        <input id="ql-name" name="name" maxlength="20" minlength="2" autocomplete="nickname" required placeholder="<?= e(t('ex. Lionceau25')) ?>">
        <button type="submit" class="qbtn"><?= e(t('C’est parti !')) ?></button>
        <p class="ql__err" data-ql-err role="alert" hidden></p>
      </form>
      <p class="ql__note"><?= e(t('Pseudo visible de toute la salle : restez fair-play. Aucune inscription, rien n’est gardé après la partie.')) ?></p>
    </div>
    <div class="ql__play" data-step="play" hidden>
      <div class="ql__bar"><b data-ql-name></b><span data-ql-score></span></div>
      <p class="ql__num" data-ql-num></p>
      <h2 class="ql__q" data-ql-q aria-live="polite"></h2>
      <div class="ql__timer" data-ql-timer hidden><span></span></div>
      <div class="ql__answers" data-ql-answers></div>
      <div class="ql__msg" data-ql-msg aria-live="polite"></div>
    </div>
  </div>
</section>
