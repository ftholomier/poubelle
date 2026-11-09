<?php
/** Formulaire générique décrit par un schéma. */
?>
<?php if (!empty($before)): ?><?php View::render($before) ?><?php endif; ?>
<form class="bo-form card" data-api="<?= e($action) ?>" data-dirty>
  <?php foreach ($hidden as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>"><?php endforeach; ?>
  <?php if ($intro !== ''): ?><p class="bo-intro"><?= e($intro) ?></p><?php endif; ?>
  <div class="f-grid"><?= Form::render($schema, $values) ?></div>
  <?php if ($action === 'post.save' && empty($values['fb_id']) && Facebook::configured()): ?>
    <div class="f"><input type="hidden" name="data[_publish_fb]" value="0"><label class="f-check"><input type="checkbox" name="data[_publish_fb]" value="1"<?= Settings::get('fb_publish_default') && empty($values['id']) ? ' checked' : '' ?>> <span>Publier aussi sur la page Facebook à l'enregistrement</span></label></div>
  <?php elseif ($action === 'post.save' && !empty($values['fb_id'])): ?>
    <p class="bo-muted">Lien avec Facebook : publication <?= e((string) $values['fb_id']) ?>.</p>
  <?php endif; ?>
  <div class="bo-actions">
    <button class="b b--y" type="submit">Enregistrer</button>
    <?php if ($view !== ''): ?><a class="b b--l" href="<?= e($view) ?>" target="_blank" rel="noopener">Voir la page ↗</a><?php endif; ?>
    <?php if ($deleteAction !== ''): ?>
      <button class="b b--danger" type="button" data-action="<?= e($deleteAction) ?>" data-confirm="Supprimer définitivement ?" <?php foreach ($hidden as $k => $v): ?>data-<?= e($k) ?>="<?= e((string) $v) ?>" <?php endforeach; ?>>Supprimer</button>
    <?php endif; ?>
  </div>
</form>
<?php if ($extra !== ''): ?><?php View::render($extra) ?><?php endif; ?>
