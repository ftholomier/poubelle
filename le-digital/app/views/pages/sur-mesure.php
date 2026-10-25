<?php
/** @var array $c  @var array $g */
use LD\View;

$services = $g['services'] ?? [];
$bl = $c['blocks'];
?>
<section class="hero-dark" aria-labelledby="sm-title">
  <div class="grid-lines" aria-hidden="true"></div>
  <div class="glow glow--top" aria-hidden="true"></div>
  <div class="hero-dark__in">
    <span class="pill-dk"><?= t($c['hero']['tag']) ?></span>
    <h1 class="hero-dark__title" id="sm-title"><?= t($c['hero']['title']) ?> <span class="grad-anim grad-anim--lt"><?= t($c['hero']['title_accent']) ?></span></h1>
    <p class="hero-dark__p"><?= t($c['hero']['text']) ?></p>
    <a class="btn btn-white btn-xl btn-hover-lime" href="/on-prend-rdv"><?= t($c['hero']['cta']) ?></a>
    <nav class="toc-pills" aria-label="Les prestations">
      <?php foreach ($services as $i => $sv): ?><a href="#<?= e($sv['id']) ?>"><span><?= sprintf('%02d', $i + 1) ?></span><?= t($sv['title']) ?></a><?php endforeach; ?>
    </nav>
  </div>
</section>

<section class="sec">
  <div class="wrap wrap--blocks">
    <?php foreach ($services as $i => $sv): $odd = $i % 2 === 1; ?>
      <article class="pblock<?= $odd ? ' pblock--rev' : '' ?>" id="<?= e($sv['id']) ?>">
        <div class="pblock__txt" data-reveal>
          <span class="pblock__n" aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></span>
          <h2 class="pblock__t"><?= t($sv['title']) ?></h2>
          <p class="pblock__p"><?= t($sv['text']) ?></p>
          <?php if (trim((string) $sv['ia']) !== ''): ?>
            <div class="pblock__ia"><span class="pblock__iah"><span class="badge-ia badge-ia--sm">IA</span><?= t($bl['ia_title']) ?></span><p><?= t($sv['ia']) ?></p></div>
          <?php endif; ?>
          <div class="explain" data-explain-box hidden><span><?= t($bl['explain_title']) ?></span><p data-explain-text></p></div>
          <div class="pblock__acts">
            <button type="button" class="btn-explain" data-explain="<?= e($sv['id']) ?>" data-label="<?= e($bl['explain_label']) ?>" data-loading="<?= e($bl['explain_loading']) ?>" data-hide="<?= e($bl['explain_hide']) ?>" data-fallback="<?= e($bl['explain_fallback']) ?>"><?= t($bl['explain_label']) ?></button>
            <a class="link-u" href="/on-prend-rdv"><?= t($bl['talk']) ?></a>
          </div>
        </div>
        <div class="pblock__mock pblock__mock--<?= $odd ? 'ink' : 'rose' ?>" data-reveal="scale">
          <div class="dots dots--mock" aria-hidden="true"></div>
          <div class="win">
            <div class="win__bar"><i></i><i></i><i></i><span><?= t($sv['window']) ?></span></div>
            <div class="win__body">
              <div class="win__scan" aria-hidden="true"></div>
              <?php foreach ($sv['rows'] as $row): ?>
                <div class="win__row"><span class="win__ic" aria-hidden="true"><?= icon('check16', 14, '#6f8d12', 2.4) ?></span><span class="win__t"><?= t($row['t']) ?></span><span class="win__tag"><?= t($row['tag']) ?></span></div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="sec sec--rose" id="demo">
  <div class="wrap stack20">
    <?= ask($c['demo']['ask']) ?>
    <h2 class="h2 h2--demo" data-reveal><?= t($c['demo']['title']) ?> <span class="accent"><?= t($c['demo']['title_accent']) ?></span> <?= t($c['demo']['title_end']) ?></h2>
    <div data-reveal="scale"><?= View::partial('analyse') ?></div>
    <div data-reveal="scale"><?= View::partial('demo-devis') ?></div>
  </div>
</section>

<section class="sec sec--rose">
  <div class="wrap">
    <h2 class="h2 mb48" data-reveal><?= t($c['principles']['title']) ?></h2>
    <div class="ag" style="--min:340px">
      <?php foreach ($c['principles']['items'] as $i => $p): ?>
        <div class="princ" data-reveal data-delay="<?= ($i % 3) * 90 ?>"><span class="princ__bar" aria-hidden="true"></span><h3><?= t($p['t']) ?></h3><p><?= t($p['d']) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec" id="diagnostic">
  <div class="wrap">
    <?php $dg = $c['diagnostic']; ?>
    <div class="intro2 intro2--44" data-reveal>
      <h2 class="h2"><?= t($dg['title']) ?></h2>
      <p class="lead"><?= t($dg['text']) ?></p>
    </div>
    <div data-reveal="scale"><?= View::partial('simulator') ?></div>
    <div class="ag mt20" style="--min:440px">
      <div class="card-r" data-reveal>
        <h3><?= t($dg['card_title']) ?></h3>
        <p><?= t($dg['card_text']) ?></p>
        <a class="btn btn-red btn-md2" href="/on-prend-rdv"><?= t($dg['card_cta']) ?></a>
      </div>
      <div class="card-o" data-reveal data-delay="100">
        <h3><?= t($dg['price_title']) ?></h3>
        <p><?= t($dg['price_text']) ?></p>
      </div>
    </div>
  </div>
</section>

<?= View::partial('final-grad', ['title' => $c['final']['title'], 'text' => $c['final']['text'], 'cta' => $c['final']['cta']]) ?>
