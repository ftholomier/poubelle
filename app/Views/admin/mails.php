<div class="topbar">
  <h1>E-mails envoyés</h1>
  <span class="badge"><?= e((string) ($pager['total'] ?? 0)) ?> au total</span>
</div>

<div class="panel">
  <p style="font-size:.86rem;color:var(--muted);margin-bottom:16px">
    Chaque notification est journalisée ici, qu’elle soit partie ou non, avec le transport utilisé et l’erreur renvoyée.
    Un échec en <code>mail()</code> signifie qu’aucun agent local n’est configuré : renseignez un serveur SMTP dans
    <a href="<?= e(url('admin/reglages')) ?>" style="text-decoration:underline">Réglages</a>. Le contenu des envois récents reste consultable.
  </p>
  <?php if (!$rows): ?>
    <div class="empty"><b>Aucun e-mail</b>Rien n’a encore été envoyé depuis le site.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Destinataire</th><th>Objet</th><th>Envoi</th><th>Transport</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $m): ?>
            <tr>
              <td><?= e($m['to'] ?? '') ?></td>
              <td style="color:var(--muted)"><?= e($m['subject'] ?? '') ?></td>
              <td>
                <span class="badge" style="color:<?= !empty($m['sent']) ? '#35d07f' : '#e8a13a' ?>"><i></i><?= !empty($m['sent']) ? 'Envoyé' : 'Non envoyé' ?></span>
                <?php if (($m['error'] ?? '') !== ''): ?>
                  <small class="help" style="display:block;margin-top:4px"><?= e($m['error']) ?></small>
                <?php endif; ?>
              </td>
              <td style="color:var(--muted);white-space:nowrap"><?= e($m['transport'] ?? '—') ?></td>
              <td style="color:var(--muted);white-space:nowrap"><?= e(fr_date($m['created_at'] ?? '', true)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php partial('pagination', ['pager' => $pager ?? []]); ?>
