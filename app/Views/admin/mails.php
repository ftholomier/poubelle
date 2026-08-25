<div class="topbar">
  <h1>E-mails envoyés</h1>
  <span class="badge">100 derniers</span>
</div>

<div class="panel">
  <p style="font-size:.86rem;color:var(--muted);margin-bottom:16px">
    Chaque notification est journalisée ici, qu’elle soit partie ou non. Si la colonne « Envoi » indique un échec,
    c’est que la fonction <code>mail()</code> n’est pas configurée sur le serveur — le contenu reste consultable.
  </p>
  <?php if (!$rows): ?>
    <div class="empty"><b>Aucun e-mail</b>Rien n’a encore été envoyé depuis le site.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Destinataire</th><th>Objet</th><th>Envoi</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $m): ?>
            <tr>
              <td><?= e($m['to'] ?? '') ?></td>
              <td style="color:var(--muted)"><?= e($m['subject'] ?? '') ?></td>
              <td><span class="badge" style="color:<?= !empty($m['sent']) ? '#35d07f' : '#e8a13a' ?>"><i></i><?= !empty($m['sent']) ? 'Envoyé' : 'Non envoyé' ?></span></td>
              <td style="color:var(--muted);white-space:nowrap"><?= e(fr_date($m['created_at'] ?? '', true)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
