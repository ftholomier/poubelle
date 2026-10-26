<?php
/**
 * Zone « pièces jointes » d'un formulaire de ticket (création, modification, réponse) : choisir,
 * glisser-déposer ou coller (Ctrl+V) une capture d'écran. Le formulaire doit être en multipart.
 * Variables : $pjCompact (réponse : plus discret).
 */
use App\Services\TicketFiles;
$pjCompact = $pjCompact ?? false;
$accept = '.' . implode(',.', array_keys(TicketFiles::TYPES));
?>
<div class="tk-pj<?= $pjCompact ? ' tk-pj--compact' : '' ?>" data-pj data-pj-max="<?= TicketFiles::maxSize() ?>" data-pj-total="<?= TicketFiles::maxTotal() ?>" data-pj-count="<?= TicketFiles::MAX_FILES ?>">
  <label class="tk-pj__zone">
    <input type="file" name="pj[]" multiple accept="<?= e($accept) ?>" data-pj-input>
    <span class="tk-pj__ico" aria-hidden="true">📎</span>
    <span><b>Pièces jointes</b> : cliquez pour choisir, glissez des fichiers ici, ou <b>collez une capture d’écran</b> (Ctrl+V / ⌘+V) n’importe où dans le formulaire.</span>
    <span class="xs muted"><?= TicketFiles::MAX_FILES ?> fichiers au plus, <?= e(TicketFiles::human(TicketFiles::maxSize())) ?> chacun : images, PDF, textes, Word, Excel, ZIP, vidéos.</span>
  </label>
  <ul class="tk-pj__list" data-pj-list aria-live="polite"></ul>
  <p class="tk-pj__err" data-pj-err role="alert" hidden></p>
</div>
