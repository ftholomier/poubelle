<?php
/** @var array $c */
use LD\View;

$in = $c['intensiti'];
$fo = $c['formaliance'];
$demo = '/on-prend-rdv';
?>
<section class="sec sec--rose">
  <div class="wrap wrap--sol-hero">
    <h1 class="sol-title"><?= t($c['hero']['title']) ?> <span class="grad-text"><?= t($c['hero']['title_accent']) ?></span></h1>
    <p class="sol-p"><?= t($c['hero']['text']) ?></p>
  </div>
</section>

<section class="sec sec--ink sol-in" id="intensiti">
  <div class="wrap sol-grid">
    <div class="sol-txt" data-reveal>
      <span class="sol-name"><?= t($in['name']) ?></span>
      <h2 class="sol-tag"><?= t($in['tagline']) ?></h2>
      <p class="sol-p2"><?= t($in['text']) ?></p>
      <div class="chips"><?php foreach ($in['strengths'] as $st): ?><span class="chip-dk"><?= t($st) ?></span><?php endforeach; ?></div>
      <div class="btns mt6">
        <a class="btn btn-white btn-sol btn-hover-lime"<?= link_attrs($in['url'] ?: '#') ?>><?= t($in['cta']) ?></a>
        <a class="btn btn-ghost-dark btn-sol2" href="<?= $demo ?>"><?= t($in['demo_cta']) ?></a>
      </div>
      <?php if (trim((string) $in['url']) === ''): ?><p class="sol-todo"><?= t($c['links_todo']) ?></p><?php endif; ?>
    </div>
    <div class="screen" data-reveal="scale">
      <div class="screen__frame"><div class="screen__in"><?= slot($in['image'], 'Capture Intensiti — à fournir', 'Capture d’écran Intensiti', ['sizes' => '(max-width: 600px) 90vw, 560px']) ?></div></div>
      <div class="screen__neck" aria-hidden="true"></div>
      <div class="screen__foot" aria-hidden="true"></div>
    </div>
  </div>
  <div class="sol-marquee" aria-label="Ce qu'on peut afficher">
    <div class="sol-marquee__track"><?php $media = $in['media']; foreach (array_merge($media, $media) as $i => $m): ?><span<?= $i >= count($media) ? ' aria-hidden="true"' : '' ?>><?= t($m) ?></span><?php endforeach; ?></div>
  </div>
</section>

<section class="sec sec--rose2" id="formaliance">
  <div class="wrap sol-grid">
    <div class="sol-shot" data-reveal="scale"><?= slot($fo['image'], 'Capture Formaliance — à fournir', 'Capture d’écran Formaliance', ['sizes' => '(max-width: 600px) 90vw, 560px']) ?></div>
    <div class="sol-txt sol-txt--20" data-reveal>
      <span class="sol-name"><?= t($fo['name']) ?></span>
      <?php if (trim((string) $fo['tagline']) !== ''): ?><h2 class="sol-tag sol-tag--red"><?= t($fo['tagline']) ?></h2><?php endif; ?>
      <?php if (trim((string) $fo['text']) !== ''): ?><p class="sol-p2 sol-p2--ink"><?= t($fo['text']) ?></p><?php else: ?><p class="todo todo--sol">À fournir : accroche, puis à quoi ça sert, pour qui, ce que ça change.</p><?php endif; ?>
      <?php if (!empty($fo['strengths'])): ?><div class="chips"><?php foreach ($fo['strengths'] as $st): ?><span class="chip-lt"><?= t($st) ?></span><?php endforeach; ?></div><?php else: ?><p class="todo todo--sol">À fournir : 3 à 5 points forts.</p><?php endif; ?>
      <div class="btns mt6">
        <a class="btn btn-red btn-sol"<?= link_attrs($fo['url'] ?: '#') ?>><?= t($fo['cta']) ?></a>
        <a class="btn btn-outline btn-sol2" href="<?= $demo ?>"><?= t($fo['demo_cta']) ?></a>
      </div>
    </div>
  </div>
</section>

<?= View::partial('final-dark', ['title' => $c['final']['title'], 'text' => $c['final']['text'], 'cta' => $c['final']['cta'], 'variant' => 'sol']) ?>
