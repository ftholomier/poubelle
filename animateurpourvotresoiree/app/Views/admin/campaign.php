<?php
use App\Controllers\Admin\MailingController;
use App\Core\Url;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Pros;

/** @var ?array $c @var array $segment @var string $preview @var array $statuses */
$id = (int) ($c['id'] ?? 0);
$editable = !$c || in_array($c['status'], ['draft', 'scheduled'], true);
$st = (array) ($c['stats'] ?? []);
?>
<p><a class="link small" href="<?= e(Url::admin('emailing')) ?>">← Emailing</a></p>
<div class="adm-head">
  <div><h1><?= e($c['name'] ?? 'Nouvelle campagne') ?></h1><?php if ($c): ?><p><span class="status-pill st-<?= e($c['status']) ?>"><?= e($statuses[$c['status']] ?? $c['status']) ?></span><?= !empty($c['scheduled_at']) ? ' · programmée le ' . e(date_fr((string) $c['scheduled_at'], 'datetime')) : '' ?><?= !empty($c['launched_at']) ? ' · lancée ' . e(ago((string) $c['launched_at'])) : '' ?></p><?php endif; ?></div>
  <?php if ($c): ?><div class="row-wrap">
    <form method="post" action="<?= e(Url::admin('emailing/' . $id . '/action')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="duplicate"><button class="btn btn-sm" type="submit">Dupliquer</button></form>
    <?php if (in_array($c['status'], ['scheduled', 'sending'], true)): ?><form method="post" action="<?= e(Url::admin('emailing/' . $id . '/action')) ?>" data-confirm="Arrêter cette campagne ?"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><button class="btn btn-sm" type="submit">Arrêter</button></form><?php endif; ?>
    <?php if ($c['status'] !== 'sending'): ?><form method="post" action="<?= e(Url::admin('emailing/' . $id . '/action')) ?>" data-confirm="Supprimer cette campagne ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-sm" type="submit">Supprimer</button></form><?php endif; ?>
  </div><?php endif; ?>
</div>
<?php if ($c && !$editable): ?>
  <div class="kpis mb-2">
    <div class="kpi"><span>Destinataires</span><b><?= nf((int) ($st['total'] ?? 0)) ?></b></div>
    <div class="kpi"><span>Envoyés</span><b><?= nf((int) ($st['sent'] ?? 0)) ?></b><?php if (!empty($st['total'])): ?><span class="bar-mini" style="width:100%"><i style="width:<?= (int) round(($st['sent'] ?? 0) / $st['total'] * 100) ?>%"></i></span><?php endif; ?></div>
    <div class="kpi"><span>Échecs</span><b><?= nf((int) ($st['failed'] ?? 0)) ?></b></div>
    <div class="kpi"><span>Ouvertures</span><b><?= nf((int) ($st['opens'] ?? 0)) ?></b><?= !empty($st['sent']) ? '<em class="up">' . round(($st['opens'] ?? 0) / $st['sent'] * 100) . ' %</em>' : '' ?></div>
    <div class="kpi"><span>Clics</span><b><?= nf((int) ($st['clicks'] ?? 0)) ?></b><?= !empty($st['sent']) ? '<em class="up">' . round(($st['clicks'] ?? 0) / $st['sent'] * 100) . ' %</em>' : '' ?></div>
  </div>
<?php endif; ?>
<div class="adm-cols">
  <form class="form" method="post" action="<?= e(Url::admin($c ? 'emailing/' . $id : 'emailing/nouvelle')) ?>" data-dirty-check>
    <?= csrf_field() ?>
    <div class="box">
      <div class="form-grid">
        <div class="field"><label for="c-name">Nom interne</label><input id="c-name" type="text" name="name" maxlength="120" value="<?= e((string) old('name', $c['name'] ?? '')) ?>" placeholder="Relance fiches sans photo – octobre"<?= $editable ? '' : ' disabled' ?>></div>
        <div class="field"><label for="c-subject">Objet de l'email *</label><input id="c-subject" type="text" name="subject" maxlength="160" required value="<?= e((string) old('subject', $c['subject'] ?? '')) ?>" placeholder="{{prenom}}, 3 astuces pour recevoir plus de demandes"<?= $editable ? '' : ' disabled' ?>></div>
      </div>
      <div class="field"><label for="c-body">Contenu</label><textarea id="c-body" name="body" data-editor="full" data-upload="<?= e(Url::admin('upload')) ?>" rows="16"<?= $editable ? '' : ' disabled' ?>><?= e((string) old('body', $c['body'] ?? '')) ?></textarea></div>
      <div class="vars"><span class="small muted">Insérer :</span><?php foreach (MailingController::VARIABLES as $k => $l): ?><button type="button" data-insert="{{<?= e($k) ?>}}" data-target="#c-body" title="<?= e($l) ?>">{{<?= e($k) ?>}}</button><?php endforeach; ?></div>
      <p class="small muted mt-1">Le lien de désinscription et le suivi des ouvertures/clics sont ajoutés automatiquement.</p>
    </div>
    <div class="box" data-segment>
      <h2>Destinataires <span class="tag"><b data-audience>…</b>&nbsp;pros</span></h2>
      <fieldset class="field"><legend class="label">Statut des fiches</legend><div class="choice-grid"><?php foreach (Pros::STATUSES as $k => $l): if ($k === 'deleted') { continue; } ?><label class="choice"><input type="checkbox" name="segment[status][]" value="<?= e($k) ?>"<?= in_array($k, (array) ($segment['status'] ?? []), true) ? ' checked' : '' ?>><span><?= e($l) ?></span></label><?php endforeach; ?></div></fieldset>
      <div class="form-grid">
        <div class="field"><label>Métiers</label><select name="segment[cats][]" multiple data-multi aria-label="Métiers"><?php foreach (Categories::all(true) as $s => $cat): ?><option value="<?= e($s) ?>"<?= in_array($s, (array) ($segment['cats'] ?? []), true) ? ' selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Régions</label><select name="segment[regions][]" multiple data-multi aria-label="Régions"><?php foreach (Geo::regions() as $code => $r): ?><option value="<?= e($code) ?>"<?= in_array((string) $code, (array) ($segment['regions'] ?? []), true) ? ' selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Départements</label><select name="segment[deps][]" multiple data-multi aria-label="Départements"><?php foreach (Geo::departements() as $code => $d): ?><option value="<?= e($code) ?>"<?= in_array((string) $code, (array) ($segment['deps'] ?? []), true) ? ' selected' : '' ?>><?= e($code . ' ' . $d['name']) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="form-grid">
        <div class="field"><label for="sg-ph">Photos</label><select id="sg-ph" name="segment[photos]"><option value="">Indifférent</option><option value="none"<?= ($segment['photos'] ?? '') === 'none' ? ' selected' : '' ?>>Sans photo</option><option value="some"<?= ($segment['photos'] ?? '') === 'some' ? ' selected' : '' ?>>Avec photos</option></select></div>
        <div class="field"><label for="sg-lg">Connexion</label><select id="sg-lg" name="segment[login]"><option value="">Indifférent</option><option value="never"<?= ($segment['login'] ?? '') === 'never' ? ' selected' : '' ?>>Jamais connecté au nouveau site</option><option value="90"<?= ($segment['login'] ?? '') === '90' ? ' selected' : '' ?>>Pas connecté depuis 90 jours</option></select></div>
        <div class="field"><label for="sg-sc">Complétude maximale (%)</label><input id="sg-sc" type="number" name="segment[score_max]" min="0" max="100" value="<?= (int) ($segment['score_max'] ?? 100) ?>"></div>
      </div>
      <p class="small muted">Les pros désinscrits et les adresses invalides sont exclus ; une adresse ne reçoit qu'un exemplaire.</p>
    </div>
    <?php if ($editable): ?>
    <div class="form-actions">
      <button class="btn btn-sm" type="submit" name="next" value="save">Enregistrer le brouillon</button>
      <button class="btn btn-sm" type="submit" name="next" value="test">M'envoyer un test</button>
      <span class="row-wrap"><input type="datetime-local" name="scheduled_at" class="input" style="width:auto;min-height:40px" aria-label="Date d'envoi" value="<?= e(!empty($c['scheduled_at']) ? date('Y-m-d\TH:i', (int) strtotime((string) $c['scheduled_at'])) : '') ?>"><button class="btn btn-sm" type="submit" name="next" value="schedule">Programmer</button></span>
      <button class="btn btn-sm btn-coral" type="submit" name="next" value="send" data-confirm-click="Lancer l'envoi maintenant à tous les destinataires ?">🚀 Envoyer maintenant</button>
    </div>
    <?php endif; ?>
  </form>
  <aside>
    <div class="box"><h2>Aperçu</h2>
      <?php if ($preview !== ''): ?><template id="cp-preview"><?= $preview ?></template><iframe class="preview-frame" title="Aperçu de l'email" data-srcdoc-from="#cp-preview" sandbox></iframe><?php else: ?><p class="muted small">Enregistrez le brouillon pour voir l'aperçu.</p><?php endif; ?>
    </div>
  </aside>
</div>
