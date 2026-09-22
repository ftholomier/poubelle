<?php
/**
 * Alertes e-mail et transport des messages.
 *
 * @var array       $group       groupe « mail » du catalogue de réglages
 * @var array       $events      types d'alerte proposés
 * @var string[]    $recipients  adresses qui recevront réellement les alertes
 * @var string      $transport   « smtp » ou « mail »
 * @var string      $notice
 * @var string[]    $errors
 * @var array       $posted      valeurs refusées, à redonner à corriger
 * @var array|null  $test
 */
use App\Core\Csrf;
use App\Services\I18n;
use App\Services\Notifier;
use App\Services\Secrets;
?>
<div class="admin-head">
  <div>
    <h1><?= e(I18n::t('admin.alerts')) ?></h1>
    <p><?= e(I18n::t('admin.alerts_note')) ?></p>
  </div>
</div>

<?php if ($notice !== ''): ?><div class="notice notice-ok" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if ($errors !== []): ?>
  <div class="notice notice-err" role="alert" tabindex="-1" data-error-focus>
    <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="post">
  <?= Csrf::field('admin-alerts') ?>

  <div class="admin-card">
    <div class="admin-head" style="margin-bottom:12px"><h2>État de l’envoi</h2></div>

    <?php if ($test !== null): ?>
      <div class="notice <?= $test['ok'] ? 'notice-ok' : 'notice-err' ?>" role="status">
        <?= e($test['message']) ?>
      </div>
    <?php endif; ?>

    <p class="secret-help" style="margin-top:0">
      Transport actuel :
      <strong><?= $transport === 'smtp' ? 'SMTP authentifié' : 'fonction mail() de PHP' ?></strong>.
      <?php if ($transport !== 'smtp'): ?>
        Renseignez un serveur SMTP ci-dessous pour basculer : les alertes arriveront bien plus
        souvent en boîte de réception.
      <?php endif; ?>
    </p>
    <p class="secret-help">
      Destinataires des alertes :
      <?php if ($recipients === []): ?>
        <strong>aucun</strong> — renseignez une adresse, sinon rien ne partira.
      <?php else: ?>
        <strong><?= e(implode(', ', $recipients)) ?></strong>
      <?php endif; ?>
    </p>
  </div>

  <div class="admin-card">
    <div class="admin-head" style="margin-bottom:12px"><h2>Ce qui déclenche une alerte</h2></div>
    <p class="secret-help" style="margin-top:0">
      Décochez ce que vous ne voulez pas recevoir. Le corps du message ne contient que de quoi
      décider — jamais la copie d’une fiche ni les coordonnées d’un candidat. Au-delà de
      soixante alertes par heure, les suivantes sont seulement journalisées, pour qu’une vague
      de dépôts ne remplisse pas votre boîte.
    </p>
    <div class="grid-fields" style="margin-top:14px">
      <?php foreach ($events as $key => $label): ?>
        <label class="check">
          <input type="checkbox" name="events[]" value="<?= e($key) ?>"
                 id="evt-<?= e(str_replace('.', '-', $key)) ?>"
                 <?= Notifier::enabled($key) ? 'checked' : '' ?>>
          <span><?= e($label) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="admin-card">
    <div class="admin-head" style="margin-bottom:12px"><h2><?= e($group['label']) ?></h2></div>
    <p class="muted" style="font-size:14px;margin:0 0 6px"><?= e($group['intro']) ?></p>

    <?php foreach ((array) ($group['keys'] ?? []) as $key => $meta): ?>
      <?php
      $public  = !empty($meta['public']);
      // Après un refus, le champ redonne ce qui vient d'être saisi : personne
      // ne doit retaper une liste d'adresses pour une virgule de travers.
      $current = array_key_exists($key, $posted) && $public
          ? (string) $posted[$key]
          : Secrets::display($key, $public);
      $isSet   = Secrets::has($key);
      ?>
      <div class="secret-row">
        <div class="secret-head">
          <label class="label" for="f-<?= e($key) ?>"><?= e($meta['label']) ?></label>
          <span class="state <?= $isSet ? 'state-ok' : 'state-neutral' ?>">
            <?= e($isSet ? I18n::t('admin.secret_set') : I18n::t('admin.secret_empty')) ?>
          </span>
        </div>

        <p class="secret-help"><?= e($meta['help']) ?></p>

        <?php if ($isSet && !$public): ?>
          <p class="secret-current"><code><?= e($current) ?></code></p>
        <?php endif; ?>

        <div class="secret-field">
          <input class="input" type="<?= $public ? 'text' : 'password' ?>"
                 id="f-<?= e($key) ?>" name="<?= e($key) ?>"
                 value="<?= $public ? e($current) : '' ?>"
                 autocomplete="off" spellcheck="false"
                 placeholder="<?= e($meta['placeholder'] ?? ($isSet ? I18n::t('admin.secret_keep') : '')) ?>">

          <?php if ($isSet && !$public): ?>
            <label class="check secret-clear">
              <input type="checkbox" name="clear[]" value="<?= e($key) ?>">
              <span><?= e(I18n::t('admin.secret_clear')) ?></span>
            </label>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="save-bar">
    <span class="save-bar-note">
      Le mot de passe SMTP laissé vide conserve sa valeur ; il s’efface par sa case « Effacer ».
      Les autres champs s’effacent en les vidant. Le test enregistre d’abord, puis envoie un
      message à l’adresse ci-dessus.
    </span>
    <button type="submit" name="action" value="save" class="btn btn-coral">
      <?= e(I18n::t('admin.save')) ?>
    </button>
    <button type="submit" name="action" value="test" class="btn btn-ghost-light" formnovalidate>
      Enregistrer et envoyer un test
    </button>
  </div>
</form>
