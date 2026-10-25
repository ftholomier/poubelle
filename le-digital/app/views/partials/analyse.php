<?php
$a = LD\Content::get('global')['analyse'];
?>
<div class="an" data-analyse data-btn="<?= e($a['button']) ?>" data-btn-again="<?= e($a['button_again']) ?>">
  <form class="an__l" data-analyse-form novalidate>
    <span class="kicker kicker--sm"><?= t($a['kicker']) ?></span>
    <h3 class="an__title"><?= t($a['title']) ?></h3>
    <p class="an__text"><?= t($a['text']) ?></p>
    <label class="an__f"><?= t($a['url_label']) ?><input name="url" maxlength="200" inputmode="url" placeholder="<?= e($a['url_placeholder']) ?>"></label>
    <label class="an__f"><?= t($a['job_label']) ?><input name="job" maxlength="200" placeholder="<?= e($a['job_placeholder']) ?>"></label>
    <label class="an__f"><?= t($a['pain_label']) ?><input name="pain" maxlength="300" placeholder="<?= e($a['pain_placeholder']) ?>"></label>
    <button type="submit" class="an__btn" data-analyse-btn><?= t($a['button']) ?></button>
    <p class="an__note"><?= t($a['note']) ?></p>
  </form>
  <div class="an__r" aria-live="polite">
    <div class="an__empty" data-analyse-empty><span><?= nl2br(t($a['empty']), false) ?></span></div>
    <div class="an__res" data-analyse-res hidden>
      <div class="an__group">
        <span class="an__gt"><?= t($a['constats_title']) ?></span>
        <div data-analyse-constats></div>
      </div>
      <div class="an__group">
        <span class="an__gt an__gt--lime"><?= t($a['idees_title']) ?></span>
        <div data-analyse-idees></div>
      </div>
      <a class="btn btn-white btn-an" href="/on-prend-rdv"><?= t($a['cta']) ?></a>
    </div>
  </div>
</div>
