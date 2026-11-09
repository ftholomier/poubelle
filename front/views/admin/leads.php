<?php
/** @var array $leads */
$sel = (string) ($_GET['id'] ?? '');
$type = (string) ($_GET['type'] ?? '');
$status = (string) ($_GET['statut'] ?? '');
$q = mb_strtolower(trim((string) ($_GET['q'] ?? '')));
$list = array_values(array_filter($leads, function ($l) use ($type, $status, $q) {
    if ($type !== '' && $l['type'] !== $type) return false;
    if ($status !== '' && $l['status'] !== $status) return false;
    if ($q !== '' && !str_contains(mb_strtolower(implode(' ', [$l['nom'] ?? '', $l['tel'] ?? '', $l['email'] ?? '', $l['msg'] ?? '', $l['cp'] ?? ''])), $q)) return false;
    return true;
}));
$cur = $sel !== '' ? Leads::find($sel) : null;
?>
<form class="filters card" method="get" action="/admin/demandes">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher (nom, téléphone, e-mail…)">
  <select name="type"><option value="">Tous les types</option><?php foreach (Leads::TYPES as $k => $v): ?><option value="<?= e($k) ?>"<?= $type === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
  <select name="statut"><option value="">Tous les statuts</option><?php foreach (Leads::STATUS as $k => $v): ?><option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
  <button class="b b--l" type="submit">Filtrer</button>
  <a class="b b--l" href="/admin/demandes.csv">Exporter (CSV)</a>
</form>

<div class="cols cols--leads">
  <div class="card card--flush">
    <table class="tbl tbl--click">
      <thead><tr><th>Date</th><th>Type</th><th>Nom</th><th>Téléphone</th><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach ($list as $l): ?>
        <tr class="<?= $l['status'] === 'nouveau' ? 'is-new' : '' ?><?= $sel === $l['id'] ? ' is-sel' : '' ?>" data-href="/admin/demandes?id=<?= e($l['id']) ?>&amp;type=<?= e($type) ?>&amp;statut=<?= e($status) ?>&amp;q=<?= e($q) ?>">
          <td><?= e(date('d/m/Y H:i', strtotime($l['at']))) ?></td>
          <td><span class="tag tag--<?= e($l['type']) ?>"><?= e(Leads::TYPES[$l['type']] ?? $l['type']) ?></span></td>
          <td><b><?= e($l['nom'] ?: '—') ?></b><?php if (!empty($l['types'])): ?><br><small><?= e(implode(', ', $l['types'])) ?></small><?php endif; ?><?php if (($l['type'] ?? '') === 'vocal'): ?><br><small>🎙 Message vocal<?= !empty($l['duration']) ? ' · ' . e($l['duration']) : '' ?></small><?php endif; ?></td>
          <td><?= e($l['tel'] ?? '') ?></td>
          <td><span class="st st--<?= e($l['status']) ?>"><?= e(Leads::STATUS[$l['status']] ?? $l['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="5" class="bo-muted" style="padding:22px">Aucune demande.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div>
    <?php if ($cur): ?>
      <div class="card lead">
        <div class="lead__h">
          <span class="tag tag--<?= e($cur['type']) ?>"><?= e(Leads::TYPES[$cur['type']] ?? $cur['type']) ?></span>
          <span class="bo-muted"><?= e(date('d/m/Y à H:i', strtotime($cur['at']))) ?></span>
        </div>
        <h2><?= e($cur['nom'] ?: 'Sans nom') ?></h2>
        <dl class="dl">
          <?php if (!empty($cur['tel'])): ?><dt>Téléphone</dt><dd><a href="tel:<?= e(tel($cur['tel'])) ?>"><?= e($cur['tel']) ?></a></dd><?php endif; ?>
          <?php if (!empty($cur['email'])): ?><dt>E-mail</dt><dd><a href="mailto:<?= e($cur['email']) ?>"><?= e($cur['email']) ?></a></dd><?php endif; ?>
          <?php if (!empty($cur['types'])): ?><dt>Produits</dt><dd><?= e(implode(', ', $cur['types'])) ?></dd><?php endif; ?>
          <?php if (!empty($cur['qty'])): ?><dt>Éléments</dt><dd><?= e($cur['qty']) ?></dd><?php endif; ?>
          <?php if (!empty($cur['delai'])): ?><dt>Échéance</dt><dd><?= e($cur['delai']) ?></dd><?php endif; ?>
          <?php if (!empty($cur['cp'])): ?><dt>Code postal</dt><dd><?= e($cur['cp']) ?></dd><?php endif; ?>
          <?php if (!empty($cur['duration'])): ?><dt>Durée</dt><dd><?= e($cur['duration']) ?></dd><?php endif; ?>
          <?php if (!empty($cur['page'])): ?><dt>Envoyée depuis</dt><dd><?= e($cur['page']) ?></dd><?php endif; ?>
        </dl>
        <?php if (!empty($cur['audio'])): ?>
          <div class="lead__voice">
            <?php if (Voice::file((string) $cur['audio'])): ?>
              <audio controls preload="metadata" src="/admin/vocal/<?= e($cur['audio']) ?>"></audio>
              <a class="b b--l" href="/admin/vocal/<?= e($cur['audio']) ?>" download="vocal-<?= e(slugify($cur['nom'] ?: 'client')) ?>.<?= e(pathinfo((string) $cur['audio'], PATHINFO_EXTENSION)) ?>">Télécharger</a>
            <?php else: ?><p class="bo-muted">Fichier audio introuvable.</p><?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($cur['summary'])): ?><div class="lead__sum"><b>Résumé IA</b><?= nl2br(e($cur['summary'])) ?></div><?php endif; ?>
        <?php if (!empty($cur['msg'])): ?><div class="lead__msg"><?php if (($cur['type'] ?? '') === 'vocal'): ?><b class="lead__lbl">Transcription</b><?php endif; ?><?= nl2br(e($cur['msg'])) ?></div><?php elseif (($cur['type'] ?? '') === 'vocal'): ?><p class="bo-muted">Pas de transcription (navigateur sans reconnaissance vocale et pas de clé Gemini) : écoutez le message.</p><?php endif; ?>
        <?php if (!empty($cur['mail_error'])): ?><p class="bo-warn">E-mail de notification non envoyé : <?= e($cur['mail_error']) ?></p><?php endif; ?>
        <div class="quick" style="margin:16px 0">
          <?php foreach (Leads::STATUS as $k => $v): ?>
            <button class="b <?= $cur['status'] === $k ? 'b--k' : 'b--l' ?>" type="button" data-action="lead.update" data-id="<?= e($cur['id']) ?>" data-status="<?= e($k) ?>"><?= e($v) ?></button>
          <?php endforeach; ?>
        </div>
        <?php foreach (($cur['notes'] ?? []) as $n): ?>
          <div class="note"><b><?= e($n['by']) ?></b> · <?= e(date('d/m/Y H:i', strtotime($n['at']))) ?><br><?= nl2br(e($n['text'])) ?></div>
        <?php endforeach; ?>
        <form data-api="lead.update" class="bo-form">
          <input type="hidden" name="id" value="<?= e($cur['id']) ?>">
          <div class="f"><label class="f-label" for="n-t">Ajouter une note</label><textarea id="n-t" name="note" rows="2"></textarea></div>
          <div class="bo-actions"><button class="b b--y" type="submit">Ajouter la note</button>
          <button class="b b--danger" type="button" data-action="lead.delete" data-id="<?= e($cur['id']) ?>" data-confirm="Supprimer cette demande ?">Supprimer</button></div>
        </form>
      </div>
    <?php else: ?>
      <div class="card bo-muted">Sélectionnez une demande pour voir le détail, changer son statut ou ajouter une note.</div>
    <?php endif; ?>
  </div>
</div>
