<?php
/**
 * Clés d'API et identifiants.
 * @var array $catalog @var array $slots @var string $notice @var array|null $test
 */
use App\Core\Csrf;
use App\Services\I18n;
use App\Services\Secrets;
use App\Support\Icon;
?>
<div class="admin-head">
  <div>
    <h1><?= e(I18n::t('admin.settings')) ?></h1>
    <p><?= e(I18n::t('admin.settings_note')) ?></p>
  </div>
</div>

<?php if ($notice !== ''): ?><div class="notice notice-ok" role="status"><?= e($notice) ?></div><?php endif; ?>

<form method="post">
  <?= Csrf::field('admin-settings') ?>

  <?php foreach ($catalog as $groupKey => $group): ?>
    <div class="admin-card">
      <div class="admin-head" style="margin-bottom:12px">
        <h2><?= e($group['label']) ?></h2>
        <button type="submit" name="action" value="test" class="btn btn-ghost btn-sm"
                formnovalidate onclick="this.form.group.value='<?= e($groupKey) ?>'">
          <?= e(I18n::t('admin.test')) ?>
        </button>
      </div>

      <?php if (($group['intro'] ?? '') !== ''): ?>
        <p class="muted" style="font-size:14px;margin:0 0 6px"><?= e($group['intro']) ?></p>
      <?php endif; ?>

      <?php if (!empty($group['doc'])): ?>
        <p style="font-size:13.5px;margin:0 0 18px">
          <?= Icon::svg('arrow-r', 14, '#FF4B3E', 2) ?>
          <a href="<?= e($group['doc'][1]) ?>" rel="noopener noreferrer" target="_blank"><?= e($group['doc'][0]) ?></a>
        </p>
      <?php endif; ?>

      <?php if ($test !== null && $test['group'] === $groupKey): ?>
        <div class="notice <?= $test['ok'] ? 'notice-ok' : 'notice-err' ?>" role="status">
          <?= e($test['message']) ?>
        </div>
      <?php endif; ?>

      <?php foreach ((array) ($group['keys'] ?? []) as $key => $meta): ?>
        <?php
        $public  = !empty($meta['public']);
        $current = Secrets::display($key, $public);
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

          <?php if (!empty($meta['doc'])): ?>
            <p class="secret-help">
              <a href="<?= e($meta['doc'][1]) ?>" rel="noopener noreferrer" target="_blank">
                <?= Icon::svg('arrow-r', 12, '#FF4B3E', 2) ?> <?= e($meta['doc'][0]) ?>
              </a>
            </p>
          <?php endif; ?>

          <?php if ($isSet): ?>
            <p class="secret-current"><code><?= e($current) ?></code></p>
          <?php endif; ?>

          <div class="secret-field">
            <input class="input" type="<?= $public ? 'text' : 'password' ?>"
                   id="f-<?= e($key) ?>" name="<?= e($key) ?>"
                   value="<?= $public ? e($current) : '' ?>"
                   autocomplete="off" spellcheck="false"
                   placeholder="<?= e($meta['placeholder'] ?? ($isSet ? I18n::t('admin.secret_keep') : '')) ?>">

            <?php if (!empty($meta['generate'])): ?>
              <button type="submit" name="action" value="generate" class="btn btn-ghost btn-sm" formnovalidate>
                <?= e(I18n::t('admin.generate')) ?>
              </button>
            <?php endif; ?>

            <?php if ($isSet && !$public): ?>
              <label class="check secret-clear">
                <input type="checkbox" name="clear[]" value="<?= e($key) ?>">
                <span><?= e(I18n::t('admin.secret_clear')) ?></span>
              </label>
            <?php endif; ?>
          </div>

          <?php if (!$public && $isSet): ?>
            <p class="secret-help"><?= e(I18n::t('admin.secret_keep')) ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php if (!empty($group['slots'])): ?>
        <div class="secret-row">
          <div class="secret-head">
            <span class="label">Identifiants des sept emplacements</span>
          </div>
          <p class="secret-help">
            AdSense → Annonces → Par unité publicitaire. Chaque bloc créé porte un identifiant
            numérique à recopier ici. Un emplacement laissé vide reprend l’unité par défaut
            ci-dessus ; si elle est vide elle aussi, l’emplacement affiche le cadre de la maquette.
            Vider un champ efface bien son identifiant.
          </p>
          <div class="grid-fields" style="margin-top:12px">
            <?php foreach ($slots as $name => $slot): ?>
              <label class="field">
                <span class="label" style="font-weight:600">
                  <?= e($slot['label']) ?> <span class="opt"><?= e($slot['format']) ?></span>
                </span>
                <input class="input" type="text" name="adsense_slots[<?= e($name) ?>]"
                       value="<?= e($slot['own']) ?>" autocomplete="off"
                       placeholder="<?= $slot['inherited'] ? 'unité par défaut' : e($name) ?>">
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <input type="hidden" name="group" value="">

  <p class="secret-help" style="margin:18px 0 0">
    Les clés sont écrites dans <code>data/private/secrets.json</code>, hors racine web,
    en permissions 0600. Le journal retient quelles clés ont changé, jamais leur valeur.
  </p>

  <div class="save-bar">
    <span class="save-bar-note">
      Une clé masquée laissée vide conserve sa valeur — elle s’efface par sa case « Effacer ».
      Une valeur affichée en clair s’efface en vidant son champ.
    </span>
    <button type="submit" name="action" value="save" class="btn btn-coral">
      <?= e(I18n::t('admin.save')) ?>
    </button>
  </div>
</form>
