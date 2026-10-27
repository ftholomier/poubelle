<?php
/** @var array $c  @var array $s */
// Coordonnées reprises des Paramètres (toujours à jour) ; une ligne « Libellé : {x} » sans valeur est masquée.
$email = trim((string) ($s['contact']['email'] ?? ''));
$phone = trim((string) ($s['contact']['phone'] ?? ''));
$ia = LD\Ai::resolve('chat');
$vars = [
    '{site}'      => preg_replace('#^https?://#', '', rtrim(abs_url('/'), '/')),
    '{email}'     => $email,
    '{telephone}' => $phone,
    '{contact}'   => $email !== '' ? 'par e-mail à ' . $email : 'via le formulaire de la page « On prend RdV ? »',
    '{ia}'        => $ia ? 'le fournisseur d’intelligence artificielle ' . (LD\Ai::PROVIDERS[$ia[0]]['label'] ?? $ia[0]) : 'le fournisseur d’intelligence artificielle utilisé',
];
$fill = static function (string $text) use ($vars): string {
    $keep = [];
    foreach (explode("\n", $text) as $line) {
        if (preg_match('/\{(email|telephone)\}\s*$/u', $line, $m) && $vars['{' . $m[1] . '}'] === '') {
            continue;
        }
        $keep[] = $line;
    }
    return strtr(implode("\n", $keep), $vars);
};
// Adresses e-mail et sites cités : cliquables.
$links = static fn(string $html): string => (string) preg_replace_callback(
    '~\b([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})\b|\b((?:https?://|www\.)[^\s<&]+[^\s<&.,;:)])~i',
    static fn($m) => $m[1] !== '' ? '<a href="mailto:' . $m[1] . '">' . $m[1] . '</a>' : '<a href="' . (str_starts_with($m[2], 'www.') ? 'https://' . $m[2] : $m[2]) . '" target="_blank" rel="noopener">' . $m[2] . '</a>',
    $html
);
$rubriques = array_values(array_filter($c['rubriques'] ?? [], static fn($r) => trim((string) ($r['text'] ?? '')) !== ''));
?>
<section class="legal">
  <h1 class="legal__title"><?= t($c['title']) ?></h1>
  <?php if (trim((string) ($c['intro'] ?? '')) !== ''): ?><p class="legal__intro"><?= t($c['intro']) ?></p><?php endif; ?>
  <nav class="legal__toc" aria-label="Sommaire">
    <?php foreach ($rubriques as $r): ?><a href="#<?= e($r['id'] ?? slugify($r['title'])) ?>"><?= t($r['title']) ?></a><?php endforeach; ?>
  </nav>
  <?php foreach ($rubriques as $r): ?>
    <h2 id="<?= e($r['id'] ?? slugify($r['title'])) ?>"><?= t($r['title']) ?></h2>
    <?php foreach (preg_split('/\n\s*\n/', $fill((string) $r['text'])) as $para): ?>
      <p><?= $links(tp(trim($para))) ?></p>
    <?php endforeach; ?>
    <?php if (($r['id'] ?? '') === 'cookies' && LD\Consent::enabled()): ?><p><button type="button" class="cc-btn cc-btn--dark" data-cookie-open>Gérer mes cookies</button></p><?php endif; ?>
  <?php endforeach; ?>
  <?php if (trim((string) ($c['updated'] ?? '')) !== ''): ?><p class="legal__updated"><?= t($c['updated']) ?></p><?php endif; ?>
</section>
