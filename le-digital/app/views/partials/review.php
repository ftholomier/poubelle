<?php
/** @var array|null $r avis réel  @var string $tag  @var string $who  @var string $variant ('home'|'page')  @var int $delay */
$variant ??= 'home';
$delay ??= 0;
$real = !empty($r);
$google = $real && LD\Reviews::isGoogle($r);
$rating = $real ? max(1, min(5, (int) ($r['rating'] ?? 5))) : 5;
$tagLabel = $real ? ($r['tag'] ?? '') : ($tag ?? '');
if ($real) {
    $activity = (string) ($r['activity'] ?? '');
    if ($activity === '' && $google) {
        $ts = strtotime((string) ($r['date'] ?? ''));
        $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $activity = 'Avis Google' . ($ts ? ' · ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts) : '');
    }
    $owner = strtok((string) LD\Settings::get('site.owner', ''), ' ') ?: 'Fred';
}
?>
<figure class="rv rv--<?= e($variant) ?><?= $google ? ' rv--google' : '' ?>"<?= $variant === 'home' ? ' data-reveal data-delay="' . (int) $delay . '"' : '' ?>>
  <div class="rv__top">
    <span class="rv__stars" role="img" aria-label="<?= $rating ?> étoiles sur 5"><?= str_repeat('★', $rating) ?><span class="rv__off"><?= str_repeat('★', 5 - $rating) ?></span></span>
    <?php if ($tagLabel !== ''): ?><span class="rv__tag"><?= e($tagLabel) ?></span><?php endif; ?>
  </div>
  <?php if ($real): ?>
    <blockquote class="rv__q"<?= $google ? ' data-clamp' : '' ?>><?= tp($r['text'] ?? '') ?></blockquote>
    <?php if ($google && $variant === 'page' && !empty($r['reply'])): ?>
      <div class="rv__reply"><b>Réponse de <?= e($owner) ?></b><p data-clamp><?= tp($r['reply']) ?></p></div>
    <?php endif; ?>
  <?php else: ?>
    <blockquote class="rv__q rv__q--todo"><?= e($empty ?? ('Témoignage réel à fournir' . (!empty($who) ? ' : ' . $who : '') . '.')) ?></blockquote>
  <?php endif; ?>
  <figcaption class="rv__who">
    <span class="rv__av"><?php if ($google && !empty($r['author_photo'])): ?><img src="<?= e($r['author_photo']) ?>" alt="" width="44" height="44" loading="lazy" decoding="async" referrerpolicy="no-referrer"><?php elseif ($real && !empty($r['photo'])): ?><?= img_tag($r['photo'], '', ['sizes' => '44px']) ?><?php elseif ($real && !empty($r['name'])): ?><?= e(mb_strtoupper(mb_substr($r['name'], 0, 1))) ?><?php endif; ?></span>
    <span class="rv__id">
      <?php if ($google && !empty($r['author_url'])): ?>
        <b><a href="<?= e($r['author_url']) ?>" target="_blank" rel="noopener nofollow"><?= e($r['name']) ?></a></b>
      <?php else: ?>
        <b><?= e($real ? ($r['name'] ?? '') : 'Prénom') ?></b>
      <?php endif; ?>
      <span><?= e($real ? $activity : 'Activité') ?></span>
    </span>
    <?php if ($google): ?>
      <?php $gUrl = (string) (($r['google_uri'] ?? '') ?: (LD\GoogleReviews::badge()['url'] ?? '')); ?>
      <?php if ($gUrl !== ''): ?>
        <a class="rv__g" href="<?= e($gUrl) ?>" target="_blank" rel="noopener nofollow" aria-label="Voir l’avis de <?= e($r['name']) ?> sur Google" title="Avis publié sur Google"><?= google_g(20) ?></a>
      <?php else: ?>
        <span class="rv__g" title="Avis publié sur Google"><?= google_g(20) ?></span>
      <?php endif; ?>
    <?php endif; ?>
  </figcaption>
</figure>
