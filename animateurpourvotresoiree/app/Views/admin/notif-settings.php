<?php
use App\Core\Url;
use App\Services\Notify;
/** @var array $n @var array $recipients */
?>
<div class="adm-head"><div><h1>Alertes <span class="serif">administrateur</span></h1><p>Choisissez ce qui vous est envoyé immédiatement, dans le récapitulatif quotidien, ou en notification push. Tout reste visible dans le centre de notifications.</p></div></div>
<form class="form" method="post" action="<?= e(Url::admin('reglages/notifications')) ?>">
  <?= csrf_field() ?>
  <div class="box">
    <div class="form-grid">
      <div class="field"><label for="nt-h">Heure du récapitulatif quotidien</label><select id="nt-h" name="digest_hour"><?php for ($h = 0; $h < 24; $h++): ?><option value="<?= $h ?>"<?= (int) ($n['digest_hour'] ?? 8) === $h ? ' selected' : '' ?>><?= sprintf('%02d h', $h) ?></option><?php endfor; ?></select></div>
      <div class="field"><label for="nt-e">Destinataires supplémentaires</label><input id="nt-e" type="text" name="emails" value="<?= e((string) ($n['emails'] ?? '')) ?>" placeholder="alerte@domaine.fr, autre@domaine.fr"><span class="hint">Actuellement : <?= e(implode(', ', $recipients) ?: 'aucun') ?></span></div>
    </div>
  </div>
  <div class="table-wrap">
    <table class="tbl"><thead><tr><th>Événement</th><th>Email</th><th>Push</th></tr></thead><tbody>
      <?php foreach (Notify::TYPES as $k => $label): $t = (array) ($n['types'][$k] ?? []); ?>
        <tr><td><strong><?= e($label) ?></strong></td>
          <td><select name="types[<?= e($k) ?>][email]" class="input" style="min-height:38px;max-width:260px"><?php foreach (['instant' => 'Immédiatement', 'digest' => 'Récapitulatif quotidien', 'off' => 'Jamais'] as $v => $l): ?><option value="<?= $v ?>"<?= ($t['email'] ?? 'digest') === $v ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></td>
          <td><label class="switch"><input type="checkbox" name="types[<?= e($k) ?>][push]" value="1"<?= !empty($t['push']) ? ' checked' : '' ?>></label></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </div>
  <p class="small muted">Pour recevoir les notifications push, activez-les sur chaque appareil via le menu de votre compte (en haut à droite) → « Alertes sur cet appareil ».</p>
  <div class="form-actions"><button class="btn btn-coral" type="submit">Enregistrer</button></div>
</form>
