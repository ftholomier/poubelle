<?php
/** @var array $c contenu de la page blog  @var array $post  @var array $s */
[$html, $toc] = LD\Html::withToc((string) $post['content']);
$related = LD\Posts::related($post, 3);
$themes = $c['themes'];
$bgs = ['linear-gradient(135deg,#cc1517,#e8402a)', '#161922', '#cf1d1a', '#252436', 'linear-gradient(135deg,#e8402a,#cc1517)'];
$themeBg = static function (string $th) use ($themes, $bgs): string {
    $i = array_search($th, $themes, true);
    return $bgs[$i === false ? 0 : $i % count($bgs)];
};
?>
<article class="art">
  <header class="art-hero">
    <div class="dots dots--top" aria-hidden="true"></div>
    <div class="art-hero__in">
      <a class="art-back" href="/blog">← <?= t($c['title']) ?></a>
      <div class="post__meta"><a class="post__theme" href="/blog?theme=<?= e(rawurlencode($post['theme'])) ?>"><?= e($post['theme']) ?></a><span><?= (int) $post['read'] ?> <?= t($c['read_suffix']) ?></span></div>
      <h1 class="art-title"><?= t($post['title']) ?></h1>
      <?php if (trim((string) ($post['excerpt'] ?? '')) !== ''): ?><p class="art-lead"><?= t($post['excerpt']) ?></p><?php endif; ?>
      <div class="art-author">
        <span class="art-author__av"><img src="<?= e($s['site']['avatar'] ?? '/assets/img/fred-avatar.webp') ?>" alt="" width="52" height="52"></span>
        <span class="art-author__id"><b><?= t($c['author_name']) ?></b><span><?= t($c['author_role']) ?> · <time datetime="<?= e(substr((string) $post['date'], 0, 10)) ?>"><?= e(date_fr((string) $post['date'])) ?></time></span></span>
      </div>
    </div>
  </header>

  <div class="art-cover<?= empty($post['image']) ? ' art-cover--empty' : '' ?>" style="background:<?= e($themeBg($post['theme'])) ?>">
    <?= img_tag($post['image'] ?? '', $post['title'], ['eager' => true, 'sizes' => '(max-width: 1100px) 94vw, 1100px']) ?: '<span>' . e($post['theme']) . '</span>' ?>
  </div>

  <div class="art-body<?= count($toc) < 2 ? ' art-body--notoc' : '' ?>">
    <?php if (count($toc) >= 2): ?>
      <nav class="art-toc" aria-label="<?= e($c['toc_title']) ?>">
        <span class="art-toc__t"><?= t($c['toc_title']) ?></span>
        <ol><?php foreach ($toc as $item): ?><li><a href="#<?= e($item['id']) ?>"><?= e($item['text']) ?></a></li><?php endforeach; ?></ol>
      </nav>
    <?php endif; ?>
    <div class="prose"><?= $html ?></div>
  </div>

  <aside class="art-cta" aria-label="Prendre rendez-vous">
    <div class="art-cta__in">
      <h2><?= t($c['article_cta_title']) ?></h2>
      <a class="btn btn-red btn-xl btn-red-lt" href="/on-prend-rdv"><?= t($c['article_cta']) ?></a>
    </div>
  </aside>
</article>

<?php if ($related): ?>
<section class="sec">
  <div class="wrap wrap--related">
    <h2 class="h2 h2--related"><?= t($c['related_title']) ?></h2>
    <div class="af" style="--min:300px;--gap:20px">
      <?php foreach ($related as $p): ?>
        <a class="post" href="/blog/<?= e($p['slug']) ?>">
          <div class="post__img" style="background:<?= e($themeBg($p['theme'])) ?>"><?= img_tag($p['image'] ?? '', $p['title'], ['sizes' => '(max-width: 700px) 92vw, 380px']) ?: '<span>' . e($p['theme']) . '</span>' ?></div>
          <div class="post__meta"><span class="post__theme"><?= e($p['theme']) ?></span><span><?= (int) $p['read'] ?> <?= t($c['read_suffix']) ?></span></div>
          <h3 class="post__t"><?= t($p['title']) ?></h3>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
