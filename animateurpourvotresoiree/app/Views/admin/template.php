<?php
use App\Core\Url;
/** @var string $key @var array $t @var array $preview @var array $default */
preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $default[1] . ' ' . $default[2], $m);
$vars = array_values(array_unique(array_merge($m[1], ['site', 'date'])));
?>
<p><a class="link small" href="<?= e(Url::admin('emailing/modeles')) ?>">← Modèles</a></p>
<div class="adm-head"><div><h1><?= e($t['label']) ?></h1><p><code class="mono"><?= e($key) ?></code><?= $t['custom'] ? ' · personnalisé' : ' · version d\'origine' ?></p></div></div>
<div class="adm-cols">
  <form class="form" method="post" action="<?= e(Url::admin('emailing/modeles/' . $key)) ?>" data-dirty-check>
    <?= csrf_field() ?>
    <div class="box">
      <div class="field"><label for="t-subject">Objet</label><input id="t-subject" type="text" name="subject" maxlength="200" value="<?= e($t['subject']) ?>"></div>
      <div class="field"><label for="t-body">Contenu</label><textarea id="t-body" name="body" data-editor="full" rows="12"><?= e($t['body']) ?></textarea></div>
      <div class="vars"><span class="small muted">Variables :</span><?php foreach ($vars as $v): ?><button type="button" data-insert="{{<?= e($v) ?>}}" data-target="#t-body">{{<?= e($v) ?>}}</button><?php endforeach; ?></div>
      <p class="small muted mt-1"><code>{{bouton}}</code> affiche le bouton d'action, <code>{{details}}</code> le tableau récapitulatif : conservez-les si le modèle d'origine les contient.</p>
    </div>
    <div class="form-actions">
      <button class="btn btn-coral btn-sm" type="submit" name="action" value="save">Enregistrer</button>
      <button class="btn btn-sm" type="submit" name="action" value="test">Enregistrer et m'envoyer un test</button>
      <?php if ($t['custom']): ?><button class="btn btn-sm" type="submit" name="action" value="reset" data-confirm-click="Revenir au texte d'origine ?">Rétablir l'original</button><?php endif; ?>
    </div>
  </form>
  <aside><div class="box"><h2>Aperçu (données d'exemple)</h2><p class="small"><strong>Objet :</strong> <?= e($preview['subject']) ?></p><template id="tp-preview"><?= $preview['html'] ?></template><iframe class="preview-frame" title="Aperçu" data-srcdoc-from="#tp-preview" sandbox></iframe></div></aside>
</div>
