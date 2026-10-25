<?php
/** @var array $c  @var string|null $theme */
use LD\View;

$posts = LD\Posts::all(true);
$themes = $c['themes'];
$bgs = ['linear-gradient(135deg,#cc1517,#e8402a)', '#161922', '#cf1d1a', '#252436', 'linear-gradient(135deg,#e8402a,#cc1517)'];
$themeBg = static function (string $th) use ($themes, $bgs): string {
    $i = array_search($th, $themes, true);
    return $bgs[$i === false ? 0 : $i % count($bgs)];
};
$active = ($theme ?? '') !== '' && in_array($theme, $themes, true) ? $theme : '*';
?>
<section class="sec">
  <div class="wrap wrap--list">
    <h1 class="list-title"><?= t($c['title']) ?></h1>
    <div class="filters filters--blog" role="toolbar" aria-label="Filtrer par thème" data-filter="blog">
      <button type="button"<?= $active === '*' ? ' class="is-on" aria-pressed="true"' : ' aria-pressed="false"' ?> data-f="*"><?= t($c['filter_all']) ?></button>
      <?php foreach ($themes as $th): ?><button type="button"<?= $active === $th ? ' class="is-on" aria-pressed="true"' : ' aria-pressed="false"' ?> data-f="<?= e($th) ?>"><?= e($th) ?></button><?php endforeach; ?>
    </div>
    <div class="af" style="--min:340px;--gap:20px" data-filter-list="blog">
      <?php if ($posts): foreach ($posts as $p): ?>
        <a class="post fitem" href="/blog/<?= e($p['slug']) ?>" data-tag="<?= e($p['theme']) ?>"<?= $active !== '*' && $p['theme'] !== $active ? ' hidden' : '' ?>>
          <div class="post__img" style="background:<?= e($themeBg($p['theme'])) ?>"><?= img_tag($p['image'] ?? '', $p['title'], ['sizes' => '(max-width: 700px) 92vw, 400px']) ?: '<span>' . e($p['theme']) . '</span>' ?></div>
          <div class="post__meta"><span class="post__theme"><?= e($p['theme']) ?></span><span><?= (int) $p['read'] ?> <?= t($c['read_suffix']) ?></span></div>
          <h2 class="post__t"><?= t($p['title']) ?></h2>
          <p class="post__x"><?= t($p['excerpt']) ?></p>
        </a>
      <?php endforeach; else: foreach ([0, 1, 2, 3, 4, 0] as $i => $ti): $th = $themes[$ti] ?? ''; ?>
        <div class="post fitem" data-tag="<?= e($th) ?>">
          <div class="post__img" style="background:<?= e($bgs[$ti % count($bgs)]) ?>"><span>Image à fournir</span></div>
          <div class="post__meta"><span class="post__theme"><?= e($th) ?></span><span><?= [5, 4, 6, 3, 7, 4][$i] ?> <?= t($c['read_suffix']) ?></span></div>
          <h2 class="post__t">Titre d'article à fournir</h2>
          <p class="post__x">Extrait de l'article à fournir.</p>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</section>
<?= View::partial('final-dark', ['title' => $c['final_title'], 'text' => null, 'cta' => $c['final_cta'], 'variant' => 'blog']) ?>
