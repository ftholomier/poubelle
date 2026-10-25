<?php
$q = LD\Content::get('accompagnement')['quiz'];
$first = $q['questions'][0] ?? ['t' => '', 'options' => []];
$cfg = ['questions' => $q['questions'], 'results' => $q['results']];
?>
<div class="quiz" data-quiz="<?= json_attr($cfg) ?>">
  <div class="quiz__deco" aria-hidden="true"></div>
  <div class="quiz__top">
    <span class="kicker kicker--lt kicker--sm"><?= t($q['kicker']) ?></span>
    <div class="quiz__dots" data-quiz-dots><?php foreach ($q['questions'] as $i => $_): ?><span class="<?= $i === 0 ? 'is-cur' : '' ?>"></span><?php endforeach; ?></div>
  </div>
  <div class="quiz__ask" data-quiz-ask>
    <h3 class="quiz__q" data-quiz-q><?= t($first['t']) ?></h3>
    <div class="quiz__opts" data-quiz-opts>
      <?php foreach ($first['options'] as $o): ?><button type="button"><?= t($o['t']) ?></button><?php endforeach; ?>
    </div>
  </div>
  <div class="quiz__res" data-quiz-res hidden>
    <span class="quiz__intro"><?= t($q['result_intro']) ?></span>
    <h3 class="quiz__rt" data-quiz-rt></h3>
    <p class="quiz__rd" data-quiz-rd></p>
    <div class="quiz__actions">
      <a class="btn btn-red btn-quiz" href="/on-prend-rdv"><?= t($q['cta']) ?></a>
      <a class="btn btn-ghost-dark btn-quiz2" href="/accompagnement" data-quiz-href><?= t($q['more']) ?></a>
      <button type="button" class="quiz__restart" data-quiz-restart><?= t($q['restart']) ?></button>
    </div>
  </div>
</div>
