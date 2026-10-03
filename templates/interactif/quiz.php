<?php
/** Quiz (maquette « Quiz »). Variables : $pool (questions), $count */
$data = array_map(fn ($q) => ['q' => $q['q'], 'a' => array_values($q['a']), 'c' => (int) $q['c'], 'fact' => $q['fact'] ?? ''], $pool);
?>
<section class="quiz" data-quiz data-count="<?= (int) $count ?>">
  <script type="application/json" data-quiz-data><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <div class="quiz__inner">
    <div class="quiz__intro" data-step="intro">
      <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e(t('Quiz supporters · {n} questions', ['n' => $count])) ?></span>
      <h1 class="quiz__title"><?= e(t('Êtes-vous un')) ?><br><span class="yellow"><?= str_replace(' ?', '&nbsp;?', e(t('vrai Lionceau ?'))) ?></span></h1>
      <p class="quiz__lead"><?= e(t("{n} questions sur l'histoire du FCSM. Un seul essai par question, pas le droit de demander à papy.", ['n' => $count])) ?></p>
      <button type="button" class="qbtn" data-quiz-start><?= e(t("C'est parti !")) ?></button>
    </div>
    <div class="quiz__q" data-step="q" hidden>
      <div class="between"><span class="quiz__num" data-quiz-num></span><span class="quiz__score" data-quiz-score></span></div>
      <div class="quiz__progress" data-quiz-progress></div>
      <h2 class="quiz__question" data-quiz-question aria-live="polite"></h2>
      <div class="quiz__answers" data-quiz-answers></div>
      <div class="quiz__verdict" data-quiz-verdict hidden>
        <div class="stack" style="gap:4px"><b data-quiz-verdict-t></b><span data-quiz-fact></span></div>
        <button type="button" class="qbtn qbtn--sm" data-quiz-next></button>
      </div>
    </div>
    <div class="quiz__result" data-step="result" hidden>
      <img src="/assets/img/logo-sochaux-retro.png" alt="" width="142" height="160" data-quiz-lion>
      <span class="quiz__big" data-quiz-final></span>
      <h2 class="quiz__rank" data-quiz-rank></h2>
      <p class="quiz__lead" data-quiz-ranktext></p>
      <p class="quiz__extra" data-quiz-extra></p>
      <div class="row" style="gap:12px;flex-wrap:wrap;justify-content:center">
        <button type="button" class="qbtn" data-quiz-share><?= e(t('Partager mon score')) ?></button>
        <button type="button" class="qbtn qbtn--ghost" data-quiz-start><?= e(t('Rejouer')) ?></button>
        <a class="qbtn qbtn--ghost" href="<?= e(url('/interactif/album/')) ?>"><?= e(t("Voir l'album")) ?></a>
      </div>
    </div>
  </div>
</section>
