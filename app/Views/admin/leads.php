<div class="topbar"><h1>Messages &amp; captures</h1><span class="badge"><?= count($rows) ?></span></div>

<div class="panel">
  <?php if (!$rows): ?>
    <div class="empty"><b>Aucun message</b>Les envois du formulaire de contact et de la pop-in de sortie arrivent ici.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Contact</th><th>Origine</th><th>Message</th><th>Date</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $l): ?>
            <tr>
              <td>
                <strong><?= e($l['name'] ?? '') ?></strong>
                <div style="font-size:.8rem;color:var(--muted)">
                  <a href="mailto:<?= e($l['email'] ?? '') ?>"><?= e($l['email'] ?? '') ?></a>
                  <?= !empty($l['phone']) ? ' · ' . e($l['phone']) : '' ?>
                </div>
              </td>
              <td>
                <span class="badge" style="color:<?= ($l['origin'] ?? '') === 'exit-intent' ? '#b071ff' : '#5b8cff' ?>">
                  <i></i><?= ($l['origin'] ?? '') === 'exit-intent' ? 'Pop-in de sortie' : 'Formulaire contact' ?>
                </span>
              </td>
              <td style="color:var(--muted);max-width:380px"><?= e(excerpt((string) ($l['message'] ?? ''), 120)) ?: '—' ?></td>
              <td style="color:var(--muted);white-space:nowrap"><?= e(fr_date($l['created_at'] ?? '', true)) ?></td>
              <td style="text-align:right">
                <form method="post" action="<?= e(url('admin/messages/' . $l['id'] . '/supprimer')) ?>" onsubmit="return confirm('Supprimer ce message ?')">
                  <?= Csrf::field() ?>
                  <button class="btn btn--sm btn--danger" type="submit">Supprimer</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
