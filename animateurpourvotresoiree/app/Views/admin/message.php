<?php
use App\Core\Url;
use App\Services\Leads;
use App\Services\Pros;
/** @var array $m @var ?array $pro */
$id = (int) $m['id'];
$spam = (array) ($m['spam'] ?? []);
?>
<p><a class="link small" href="<?= e(Url::admin('messages')) ?>">← Messages</a></p>
<div class="adm-head"><div><h1>Message <span class="serif">n° <?= $id ?></span></h1><p><?= e(date_fr((string) ($m['created_at'] ?? ''), 'datetime')) ?> · <span class="status-pill st-<?= e($m['status']) ?>"><?= e(Leads::MESSAGE_STATUSES[$m['status']] ?? $m['status']) ?></span><?= !empty($m['read_at']) ? ' · lu par le pro ' . e(ago((string) $m['read_at'])) : '' ?></p></div></div>
<div class="adm-cols">
  <div class="box">
    <h2>De <?= e($m['name']) ?></h2>
    <p class="small"><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a><?= !empty($m['phone']) ? ' · ' . e($m['phone']) : '' ?></p>
    <div class="prose"><?= nl2br(e((string) $m['message'])) ?></div>
    <dl class="dl mt-2"><?php foreach (['Événement' => Leads::EVENT_TYPES[$m['event_type'] ?? ''] ?? '', 'Date' => !empty($m['event_date']) ? date_fr((string) $m['event_date'], 'long') : '', 'Lieu' => $m['place'] ?? '', 'Invités' => $m['guests'] ?? ''] as $l => $v): if ($v === '') { continue; } ?><dt><?= e($l) ?></dt><dd><?= e($v) ?></dd><?php endforeach; ?></dl>
  </div>
  <aside>
    <div class="box">
      <h2>Destinataire</h2>
      <?php if ($pro): ?><p><a class="t-main" href="<?= e(Url::admin('pros/' . $pro['id'])) ?>"><?= e(Pros::displayName($pro)) ?></a><br><span class="small muted"><?= e((string) ($pro['city'] ?? '')) ?> · <?= e(Pros::STATUSES[$pro['status']] ?? '') ?></span></p><?php else: ?><p class="muted">Fiche introuvable.</p><?php endif; ?>
    </div>
    <div class="box">
      <h2>Modération</h2>
      <p class="small">Score anti-spam : <span class="score <?= ($spam['score'] ?? 0) >= 70 ? 'hi' : (($spam['score'] ?? 0) >= 30 ? 'mid' : 'lo') ?>"><?= (int) ($spam['score'] ?? 0) ?></span></p>
      <?php if (!empty($spam['reasons'])): ?><ul class="small" style="padding-left:18px"><?php foreach ((array) $spam['reasons'] as $why): ?><li><?= e((string) $why) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <div class="stack">
        <?php if ($m['status'] !== 'delivered'): ?><form method="post" action="<?= e(Url::admin('messages/' . $id)) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="deliver"><button class="btn btn-sm btn-block btn-lime" type="submit">✅ Transmettre au pro</button></form><?php endif; ?>
        <?php if ($m['status'] === 'pending'): ?><form method="post" action="<?= e(Url::admin('messages/' . $id)) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><button class="btn btn-sm btn-block" type="submit">Refuser</button></form><?php endif; ?>
        <form method="post" action="<?= e(Url::admin('messages/' . $id)) ?>" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="spam"><label class="check small"><input type="checkbox" name="block_email" value="1"> Bloquer cet email</label><button class="btn btn-sm btn-block" type="submit">🚫 Spam</button></form>
        <form method="post" action="<?= e(Url::admin('messages/' . $id)) ?>" data-confirm="Supprimer ce message ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-block" type="submit">Supprimer</button></form>
      </div>
    </div>
  </aside>
</div>
