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
    <div class="qui-photo"><?= slot($h['photo'], 'Grand portrait de Frédéric', $h['photo_alt'], ['eager' => true, 'sizes' => '(max-width: 600px) 92vw, 560px']) ?></div>
  </div>
</section>

<section class="sec">
  <div class="qui-story">
    <?php foreach ($c['story'] as $p): ?>
      <?php if (($p['kind'] ?? 'p') === 'quote'): ?>
        <figure class="qui-cite" data-reveal>
          <blockquote><p>«&nbsp;<?= t($p['text']) ?>&nbsp;»</p></blockquote>
          <figcaption><img src="<?= e(site_avatar()) ?>" alt="" width="44" height="44" loading="lazy"><span><b><?= e($s['site']['owner'] ?? 'Frédéric Tholomier') ?></b><small>Ma citation, ma façon de voir le digital</small></span></figcaption>
        </figure>
      <?php else: ?>
        <p data-reveal<?= ($p['kind'] ?? 'p') === 'big' ? ' class="qui-big"' : '' ?>><?= t($p['text']) ?></p>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</section>
