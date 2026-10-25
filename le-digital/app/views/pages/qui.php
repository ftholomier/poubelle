<?php
/** @var array $c */
$h = $c['hero'];
?>
<section class="sec sec--rose">
  <div class="wrap qui-hero">
    <div class="qui-hero__txt">
      <h1 class="qui-title"><?= t($h['title']) ?> <span class="grad-text"><?= t($h['title_accent']) ?></span></h1>
      <blockquote class="qui-quote"><?= t($h['quote']) ?></blockquote>
      <div class="qui-stat"><span class="qui-stat__v"><span data-count-to="<?= e($h['stat_value']) ?>"><?= e($h['stat_value']) ?></span>+</span><span><?= t($h['stat_label']) ?></span></div>
      <a class="btn btn-red btn-xl self-start" href="/on-prend-rdv"><?= t($h['cta']) ?></a>
    </div>
    <div class="qui-photo"><?= slot($h['photo'], 'Grand portrait de Frédéric — à fournir', $h['photo_alt'], ['eager' => true, 'sizes' => '(max-width: 600px) 92vw, 560px']) ?></div>
  </div>
</section>

<section class="sec">
  <div class="qui-story">
    <?php if (empty($c['story_validated']) && trim((string) $c['story_todo']) !== ''): ?><p class="todo todo--story" data-reveal><?= t($c['story_todo']) ?></p><?php endif; ?>
    <?php foreach ($c['story'] as $p): ?>
      <p data-reveal<?= ($p['kind'] ?? 'p') === 'big' ? ' class="qui-big"' : '' ?>><?= t($p['text']) ?></p>
    <?php endforeach; ?>
  </div>
</section>

<section class="sec qui-photos-sec">
  <div class="qui-photos ag" style="--min:300px">
    <?php foreach ($c['photos'] as $i => $ph): ?>
      <div class="qui-ph" data-reveal<?= $i ? ' data-delay="' . ($i * 100) . '"' : '' ?>><?= slot($ph['image'], $ph['placeholder'], $ph['alt'], ['sizes' => '(max-width: 700px) 92vw, 400px']) ?></div>
    <?php endforeach; ?>
  </div>
</section>
