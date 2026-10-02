<?php
use App\Core\Url;
use App\Services\Categories;
use App\Services\Leads;
use App\Services\Pros;

/** @var array $r @var array $details @var array $candidates @var array $recipients @var array $same @var int $max */
$id = (int) $r['id'];
$spam = (array) ($r['spam'] ?? []);
$score = (int) ($spam['score'] ?? 0);
$states = App\Controllers\Pro\DashboardController::proStates();
?>
<p><a class="link small" href="<?= e(Url::admin('demandes')) ?>">← Demandes</a></p>
<div class="adm-head">
  <div><h1>Demande <span class="serif">n° <?= $id ?></span></h1>
    <p><?= e(date_fr((string) ($r['created_at'] ?? ''), 'datetime')) ?> · <span class="status-pill st-<?= e($r['status']) ?>"><?= e(Leads::REQUEST_STATUSES[$r['status']] ?? $r['status']) ?></span> · origine : <?= e((string) ($r['source'] ?? 'form')) ?><?= !empty($r['moderated_by']) ? ' · modérée par ' . e((string) $r['moderated_by']) : '' ?></p></div>
</div>
<div class="adm-cols">
  <div>
    <div class="box">
      <h2>Contenu</h2>
      <dl class="dl"><?php foreach ($details as $label => $value): if ($value === '' || $value === null) { continue; } ?><dt><?= e($label) ?></dt><dd><?= nl2br(e($value)) ?></dd><?php endforeach; ?></dl>
    </div>
    <form class="box" method="post" action="<?= e(Url::admin('demandes/' . $id)) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="diffuse">
      <div class="box-head"><h2><?= $r['status'] === 'diffused' ? 'Envoyer à d\'autres pros' : 'Diffuser la demande' ?></h2><span class="small muted"><?= count($candidates) ?> pros du secteur</span></div>
      <p class="small">Les pros cochés recevront la demande par email (et notification). Par défaut : les <?= (int) $max ?> plus proches pour les métiers demandés.</p>
      <div class="recip">
        <?php $i = 0; foreach ($candidates as $pid => $p): $already = isset($recipients[$pid]); ?>
          <label><input type="checkbox" name="pros[]" value="<?= (int) $pid ?>"<?= !$already && $i < $max ? ' checked' : '' ?><?= $already ? ' disabled' : '' ?>>
            <span><a href="<?= e(Url::admin('pros/' . $pid)) ?>" target="_blank"><?= e($p['name']) ?></a> — <?= e($p['city']) ?> (<?= e($p['dep']) ?>)<?= $p['distance'] !== null ? ' · ' . (int) $p['distance'] . ' km' : '' ?> · <span class="muted"><?= e(implode(', ', array_map([Categories::class, 'name'], array_slice($p['cats'], 0, 2)))) ?></span><?= $already ? ' · <em>déjà destinataire</em>' : '' ?></span></label>
        <?php if (!$already) { $i++; } endforeach; ?>
        <?php if (!$candidates): ?><p class="muted small">Aucun pro trouvé dans le secteur pour ces métiers : la diffusion automatique élargira à tous les métiers.</p><?php endif; ?>
      </div>
      <div class="row-wrap mt-2">
        <button class="btn btn-sm btn-lime" type="submit">✅ <?= $r['status'] === 'diffused' ? 'Envoyer aux pros cochés' : 'Valider et diffuser' ?></button>
        <?php if ($r['status'] !== 'diffused'): ?><span class="small muted">Sans case cochée : sélection automatique.</span><?php endif; ?>
      </div>
    </form>
    <?php if ($recipients): ?>
    <div class="box">
      <h2>Pros destinataires (<?= count($recipients) ?>)</h2>
      <div class="table-wrap" style="box-shadow:none"><table class="tbl"><thead><tr><th>Pro</th><th>Ville</th><th>Vue</th><th>Suivi du pro</th></tr></thead><tbody>
        <?php foreach ($recipients as $pid => $p): ?><tr><td><a class="t-main" href="<?= e(Url::admin('pros/' . $pid)) ?>"><?= e($p['name'] ?? ('#' . $pid)) ?></a></td><td class="small"><?= e((string) ($p['city'] ?? '')) ?></td><td class="small"><?= $p['seen'] ? e(ago($p['seen'])) : '<span class="muted">non</span>' ?></td><td class="small"><?= $p['state'] ? e($states[$p['state']] ?? $p['state']) : '—' ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </div>
    <?php endif; ?>
  </div>
  <aside>
    <div class="box">
      <h2>Modération</h2>
      <p class="small">Score anti-spam : <span class="score <?= $score >= 70 ? 'hi' : ($score >= 30 ? 'mid' : 'lo') ?>"><?= $score ?></span> · décision : <?= e((string) ($spam['decision'] ?? '—')) ?></p>
      <?php if (!empty($spam['reasons'])): ?><ul class="small" style="padding-left:18px;margin:6px 0"><?php foreach ((array) $spam['reasons'] as $why): ?><li><?= e((string) $why) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <?php if (!empty($spam['ai'])): ?><p class="small">IA : spam <?= e((string) round(((float) ($spam['ai']['spam'] ?? 0)) * 100)) ?> %, qualité <?= e((string) round(((float) ($spam['ai']['quality'] ?? 0)) * 100)) ?> % — <?= e((string) ($spam['ai']['reason'] ?? '')) ?></p><?php endif; ?>
      <div class="stack mt-2">
        <form method="post" action="<?= e(Url::admin('demandes/' . $id)) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><button class="btn btn-sm btn-block" type="submit">Refuser (sans suite)</button></form>
        <form method="post" action="<?= e(Url::admin('demandes/' . $id)) ?>" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="spam">
          <label class="check small"><input type="checkbox" name="block_email" value="1"> Bloquer cet email</label>
          <label class="check small"><input type="checkbox" name="block_domain" value="1"> Bloquer tout le domaine</label>
          <button class="btn btn-sm btn-block" type="submit">🚫 C'est du spam</button></form>
        <?php if ($r['status'] === 'diffused'): ?><form method="post" action="<?= e(Url::admin('demandes/' . $id)) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="close"><button class="btn btn-sm btn-block" type="submit">Clôturer</button></form><?php endif; ?>
        <?php if (in_array($r['status'], ['rejected', 'spam', 'closed'], true)): ?><form method="post" action="<?= e(Url::admin('demandes/' . $id)) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="pending"><button class="btn btn-sm btn-block" type="submit">Remettre en modération</button></form><?php endif; ?>
        <form method="post" action="<?= e(Url::admin('demandes/' . $id)) ?>" data-confirm="Supprimer définitivement cette demande ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-block" type="submit">Supprimer</button></form>
      </div>
    </div>
    <div class="box">
      <h2>Client</h2>
      <?php $c = $r['client']; ?>
      <p><strong><?= e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?></strong><?= !empty($c['company']) ? '<br>' . e($c['company']) : '' ?><br>
        <a href="mailto:<?= e((string) ($c['email'] ?? '')) ?>"><?= e((string) ($c['email'] ?? '')) ?></a><?= !empty($c['phone']) ? '<br><a href="tel:' . e(preg_replace('/[^\d+]/', '', (string) $c['phone'])) . '">' . e($c['phone']) . '</a>' : '' ?></p>
      <?php if ($same): ?><p class="small muted">Autres demandes de ce client :</p><ul class="list-rows"><?php foreach ($same as $o): ?><li><a href="<?= e(Url::admin('demandes/' . $o['id'])) ?>">n° <?= (int) $o['id'] ?> — <?= e($o['city']) ?></a><span class="muted"><?= e(date_fr($o['created'], 'short')) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
    <form class="box" method="post" action="<?= e(Url::admin('demandes/' . $id)) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="note">
      <h2>Note interne</h2><textarea class="input" name="admin_notes" rows="3"><?= e((string) ($r['admin_notes'] ?? '')) ?></textarea><button class="btn btn-xs mt-1" type="submit">Enregistrer</button></form>
  </aside>
</div>
