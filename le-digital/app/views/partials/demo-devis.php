<?php
$d = LD\Content::get('global')['demo_devis'];
?>
<div class="dd" data-devis data-default="<?= e($d['default_prompt']) ?>" data-btn="<?= e($d['button']) ?>" data-btn-again="<?= e($d['button_again']) ?>">
  <div class="dd__l">
    <div class="dd__deco" aria-hidden="true"></div>
    <span class="kicker kicker--lt kicker--sm"><?= t($d['kicker']) ?></span>
    <h3 class="dd__title"><?= t($d['title']) ?></h3>
    <p class="dd__text"><?= t($d['text']) ?></p>
    <div class="dd__ex">
      <?php foreach ($d['examples'] as $ex): ?><button type="button" data-devis-ex="<?= e($ex['prompt']) ?>"><?= t($ex['label']) ?></button><?php endforeach; ?>
    </div>
    <label class="sr-only" for="devis-prompt">Décris ta prestation</label>
    <textarea id="devis-prompt" rows="4" maxlength="600" placeholder="<?= e($d['placeholder']) ?>" data-devis-prompt></textarea>
    <button type="button" class="dd__btn" data-devis-go><?= t($d['button']) ?></button>
    <p class="dd__note"><?= t($d['note']) ?></p>
  </div>
  <div class="dd__r">
    <div class="dd__dots" aria-hidden="true"></div>
    <div class="paper" aria-live="polite">
      <div class="paper__head">
        <div class="paper__id"><span class="paper__h">DEVIS</span><span class="paper__ref" data-devis-ref>En attente</span></div>
        <span class="paper__chip" data-devis-chip>Brouillon</span>
      </div>
      <div class="paper__body" data-devis-body hidden>
        <div class="paper__client"><b data-devis-client></b><br><span data-devis-objet></span></div>
        <div class="paper__lines" data-devis-lines></div>
        <div class="paper__tot" data-devis-tot hidden>
          <div class="paper__ht"><span>Total HT</span><span data-devis-ht></span></div>
          <div class="paper__ttc"><span>Total TTC</span><span data-devis-ttc></span></div>
          <p class="paper__cond" data-devis-cond></p>
        </div>
      </div>
      <div class="paper__empty" data-devis-empty>
        <div style="width:60%"></div><div style="width:80%"></div><div style="width:70%"></div><div style="width:50%"></div>
        <p><?= t($d['empty']) ?></p>
      </div>
    </div>
  </div>
</div>
