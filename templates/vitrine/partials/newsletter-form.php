<?php
/**
 * Inscription à la newsletter « Ce jour-là » (celle du musée). Sans session : le résultat revient
 * dans l'adresse (?nl=code#ancre). Variables : $id (« nl-home », « nl-foot »…), $compact
 */
use App\Vitrine\Forms;
use App\Vitrine\Host;

$id = $id ?? 'nl-foot';
$code = (string) ($_GET['nl'] ?? '');
$msg = isset(Forms::NL_MESSAGES[$code]) && ($_GET['f'] ?? '') === $id ? Forms::NL_MESSAGES[$code] : null;
?>
<form class="vnl<?= !empty($compact) ? ' vnl--compact' : '' ?>" id="<?= e($id) ?>" method="post" action="<?= e(Host::url('/newsletter/')) ?>" data-protect>
  <input type="hidden" name="_ts" value="<?= e(form_ts()) ?>">
  <input type="hidden" name="back" value="<?= e(\App\Vitrine\Site::path()) ?>">
  <input type="hidden" name="anchor" value="<?= e($id) ?>">
  <div class="hp" aria-hidden="true"><label for="<?= e($id) ?>-w">Ne pas remplir</label><input type="text" id="<?= e($id) ?>-w" name="website" tabindex="-1" autocomplete="off"></div>
  <label class="sr-only" for="<?= e($id) ?>-e">Votre adresse e-mail</label>
  <input class="input" type="email" id="<?= e($id) ?>-e" name="email" required autocomplete="email" placeholder="Votre adresse e-mail">
  <button type="submit" class="btn btn--yellow btn--sm">Je m’inscris</button>
  <?php if ($msg): ?><p class="vnl__msg vnl__msg--<?= e($msg[0]) ?>" role="status"><?= e($msg[1]) ?></p><?php endif; ?>
</form>
