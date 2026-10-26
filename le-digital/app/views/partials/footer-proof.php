<?php
/** Preuve sociale en haut du pied de page (toutes les pages) : note Google, extraits d'avis réels, liens. */
$badge = LD\GoogleReviews::badge();
$pub = LD\Reviews::all(true);
if (!$badge && !$pub) {
    return;
}
if ($badge) {
    [$rating, $count, $label, $url] = [$badge['rating'], $badge['count'], 'avis Google', $badge['url'] ?: '/avis'];
} else {
    $rating = round(array_sum(array_map(static fn($r) => (int) ($r['rating'] ?? 5), $pub)) / count($pub), 1);
    [$count, $label, $url] = [count($pub), count($pub) > 1 ? 'avis clients' : 'avis client', '/avis'];
}
$note = number_format((float) $rating, 1, ',', ' ');
$full = (int) round((float) $rating);
// Extraits : bonnes notes au texte court, ceux mis en avant puis les plus récents (Google d'abord).
$quotes = array_values(array_filter($pub, static fn($r) => (int) ($r['rating'] ?? 5) >= 4 && mb_strlen(trim((string) ($r['text'] ?? ''))) >= 30));
usort($quotes, static fn($a, $b) => [!empty($b['home']), LD\Reviews::isGoogle($b), (string) ($b['date'] ?? $b['created_at'] ?? '')] <=> [!empty($a['home']), LD\Reviews::isGoogle($a), (string) ($a['date'] ?? $a['created_at'] ?? '')]);
$quotes = array_slice($quotes, 0, 4);
$short = static function (string $name): string {
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    return count($parts) > 1 ? $parts[0] . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.' : trim($name);
};
$external = str_starts_with($url, 'http');
?>
<div class="fproof" data-fproof>
  <a class="fproof__score" href="<?= e($url) ?>"<?= $external ? ' target="_blank" rel="noopener"' : '' ?> aria-label="Note : <?= e($note) ?> sur 5, <?= (int) $count ?> <?= e($label) ?>">
    <?php if ($badge): ?><span class="fproof__g"><?= google_g(26) ?></span><?php endif; ?>
    <b><?= e($note) ?></b>
    <span class="fproof__meta"><span class="fproof__stars" aria-hidden="true"><?= str_repeat('★', $full) ?><span><?= str_repeat('★', 5 - $full) ?></span></span><span><?= (int) $count ?>&nbsp;<?= e($label) ?></span></span>
  </a>
  <?php if ($quotes): ?>
    <div class="fproof__quotes" aria-live="off">
      <?php foreach ($quotes as $i => $q): ?>
        <figure class="fproof__q<?= $i === 0 ? ' is-on' : '' ?>"<?= $i ? ' aria-hidden="true"' : '' ?>>
          <blockquote>« <?= t(mb_strimwidth(trim((string) $q['text']), 0, 170, '…')) ?> »</blockquote>
          <figcaption><?= e($short((string) $q['name'])) ?><?= trim((string) ($q['activity'] ?? '')) !== '' ? ' · ' . e($q['activity']) : '' ?><?= LD\Reviews::isGoogle($q) ? ' · sur Google' : '' ?></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div class="fproof__acts">
    <a class="fproof__all" href="/avis">Lire les avis →</a>
    <?php if ($badge && $badge['write'] !== ''): ?><a class="fproof__write" href="<?= e($badge['write']) ?>" target="_blank" rel="noopener">Laisser un avis Google ↗</a><?php endif; ?>
  </div>
</div>
