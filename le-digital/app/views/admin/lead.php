<?php
/** @var array $lead @var ?array $booking @var string $csrf */
use LD\Leads;

$rows = Leads::rows($lead);
?>
<p><a href="/admin/demandes" class="back">← Toutes les demandes</a></p>
<div class="grid2 grid2--wide">
  <section class="card">
    <div class="card__head">
      <h2><span class="pill pill--<?= e($lead['type']) ?>"><?= e(Leads::TYPES[$lead['type']] ?? $lead['type']) ?></span> <?= e($lead['name'] ?: 'Anonyme') ?></h2>
      <small class="muted">Reçue le <?= e(date('d/m/Y à H:i', strtotime($lead['created_at']))) ?></small>
    </div>
    <?php if (!empty($lead['data']['audio'])): ?>
      <div class="audio">
        <audio controls preload="metadata" src="/admin/vocal/<?= e(rawurlencode($lead['data']['audio'])) ?>"></audio>
        <a href="/admin/vocal/<?= e(rawurlencode($lead['data']['audio'])) ?>?dl=1">Télécharger le vocal</a>
      </div>
    <?php endif; ?>
    <dl class="dl">
      <?php foreach ($rows as $label => $value): ?>
        <dt><?= e($label) ?></dt><dd><?= nl2br(e($value)) ?></dd>
      <?php endforeach; ?>
    </dl>
    <?php if ($booking): ?>
      <p class="muted small">Rendez-vous <?= $booking['status'] === 'confirmed' ? 'confirmé' : 'annulé' ?><?= !empty($booking['reminded']) ? ' · rappel envoyé' : '' ?>.</p>
    <?php endif; ?>
    <div class="actions">
      <?php if ($lead['phone']): ?><a class="btn btn--dark" href="<?= e(tel_href($lead['phone'])) ?>">Appeler <?= e($lead['phone']) ?></a><?php endif; ?>
      <?php if ($lead['email']): ?><a class="btn btn--ghost" href="mailto:<?= e($lead['email']) ?>?subject=<?= rawurlencode('Suite à ta demande · LE-DIGITAL.com') ?>">Répondre par e-mail</a><?php endif; ?>
    </div>
  </section>

  <section class="card">
    <div class="card__head"><h2>Suivi</h2></div>
    <form method="post" class="form">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <label>Statut
        <select name="status"><?php foreach (Leads::STATUSES as $k => $label): ?><option value="<?= e($k) ?>"<?= $lead['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      </label>
      <label>Notes internes<textarea name="notes" rows="7" placeholder="Ce qu’on s’est dit, prochaine étape…"><?= e($lead['notes'] ?? '') ?></textarea></label>
      <button class="btn btn--red" type="submit">Enregistrer</button>
    </form>
    <form method="post" action="/admin/demandes/<?= e($lead['id']) ?>/supprimer" class="danger" data-confirm="Supprimer définitivement cette demande ?">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <button class="link-danger" type="submit">Supprimer la demande</button>
    </form>
    <p class="muted small">Page d’origine : <?= e($lead['meta']['page'] ?? '—') ?></p>
  </section>
</div>
