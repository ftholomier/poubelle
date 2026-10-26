<?php
/**
 * Pièces jointes affichées (demande ou réponse) : aperçu des images, lien pour le reste.
 * Variables : $files (liste), $tid (n° du ticket), $pjRemove (modification : cases « Retirer »).
 */
use App\Services\TicketFiles;
use App\Services\Tickets;
$pjRemove = $pjRemove ?? false;
if (!$files) {
    return;
}
?>
<ul class="tk-files">
  <?php foreach ($files as $f): $url = Tickets::fileUrl((int) $tid, $f); ?>
    <li class="tk-file<?= TicketFiles::isImage($f) ? ' tk-file--img' : '' ?>">
      <?php if (TicketFiles::isImage($f)): ?>
        <a href="<?= e($url) ?>" target="_blank" rel="noopener" class="tk-file__thumb"><img src="<?= e($url) ?>" alt="<?= e($f['name']) ?>" loading="lazy"></a>
      <?php endif; ?>
      <span class="tk-file__meta">
        <a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= TicketFiles::isImage($f) ? '' : '📄 ' ?><?= e($f['name']) ?></a>
        <span class="xs muted"><?= e(TicketFiles::human((int) $f['size'])) ?> · <a href="<?= e($url) ?>?telecharger=1">télécharger</a></span>
        <?php if ($pjRemove): ?><label class="xs"><input type="checkbox" name="pj_retirer[]" value="<?= e($f['id']) ?>"> retirer</label><?php endif; ?>
      </span>
    </li>
  <?php endforeach; ?>
</ul>
