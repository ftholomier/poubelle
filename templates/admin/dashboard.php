<?php
/**
 * Tableau de bord.
 * @var array $kpi @var int $pending @var array $rows @var array $journal
 * @var array $backups @var array $knowledge @var array $assistant  fréquentation de Régie
 */
use App\Services\I18n;
use App\Support\Icon;

$stateClass = static fn(string $s): string => match ($s) {
    'publish' => 'state-ok', 'draft' => 'state-wait', 'expired' => 'state-neutral', default => 'state-neutral',
};
$stateLabel = static fn(string $s): string => match ($s) {
    'publish' => I18n::t('admin.state_publish'),
    'draft'   => I18n::t('admin.state_draft'),
    'expired' => I18n::t('admin.state_expired'),
    default   => $s,
};
?>
<div class="admin-head">
  <div>
    <h1><?= e(I18n::t('admin.dashboard')) ?></h1>
    <p>État du site, contenus et sauvegardes.</p>
  </div>
  <a class="btn btn-coral btn-sm" href="/admin/contenus"><?= Icon::svg('plus', 15, '#fff', 2.2) ?> Nouveau contenu</a>
</div>

<?php if (($pending ?? 0) > 0): ?>
  <?php // Ce qui attend une décision passe avant les compteurs. ?>
  <div class="notice notice-wait" role="status">
    <strong><?= (int) $pending ?></strong> dépôt(s) en attente de modération.
    <a href="/admin/offres?etat=pending">Voir les annonces</a> ·
    <a href="/admin/cv?etat=pending">Voir les CV</a>
  </div>
<?php endif; ?>

<div class="kpi-grid">
  <?php foreach ([
      ['admin.kpi_jobs',  $kpi['jobs'],  '#FF4B3E'],
      ['admin.kpi_cv',    $kpi['cv'],    '#0FBFA4'],
      ['admin.kpi_regie', $kpi['regie'], '#6D4AFF'],
      ['admin.kpi_ads',   $kpi['ads'],   '#FFC531'],
  ] as [$key, $value, $color]): ?>
    <div class="kpi">
      <div class="n" style="color:<?= e($color) ?>"><?= e(number_format((int) $value, 0, ',', ' ')) ?></div>
      <div class="l"><?= e(I18n::t($key)) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="admin-card">
  <div class="admin-head" style="margin-bottom:14px">
    <h2><?= e(I18n::t('admin.contents')) ?></h2>
    <a class="btn btn-ghost btn-sm" href="/admin/contenus">Tout voir</a>
  </div>

  <?php if ($rows === []): ?>
    <p class="muted">Aucun contenu pour l'instant.</p>
  <?php else: ?>
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>Titre</th><th>Type</th><th>Langues</th><th>État</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td>
              <span class="t"><?= e($row['title']) ?></span>
              <?php if ($row['lock'] !== null): ?>
                <span class="state state-wait" style="margin-left:8px">
                  <?= e(I18n::t('admin.lock', (string) $row['lock']['user_name'], (int) $row['lock']['age_minutes'])) ?>
                </span>
              <?php endif; ?>
              <br><span class="s">/<?= e($row['slug']) ?></span>
            </td>
            <td><?= e($row['type']) ?></td>
            <td><span class="s"><?= (int) $row['langs'] ?> / <?= (int) $row['total'] ?></span></td>
            <td><span class="state <?= e($stateClass((string) $row['status'])) ?>"><?= e($stateLabel((string) $row['status'])) ?></span></td>
            <td class="actions"><a class="btn btn-ghost btn-sm" href="/admin/contenu/<?= e($row['slug']) ?>">Éditer</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<div class="admin-card">
  <h2><?= e(I18n::t('admin.journal')) ?></h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:22px;margin-top:16px">
    <div>
      <h3><?= e(I18n::t('admin.backups')) ?></h3>
      <?php if ($backups === []): ?>
        <p class="muted" style="font-size:14px">Aucun instantané.</p>
      <?php else: ?>
        <ul style="list-style:none;margin:12px 0 0;padding:0;display:flex;flex-direction:column;gap:9px">
          <?php foreach ($backups as $backup): ?>
            <li style="font-size:13px">
              <span class="t"><?= e(date('d/m/Y H:i', (int) strtotime((string) $backup['created_at']))) ?></span>
              · <span class="s"><?= e($backup['reason']) ?> · <?= e((string) round($backup['size'] / 1024)) ?> Ko</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <a class="btn btn-ghost btn-sm" style="margin-top:14px" href="/admin/sauvegardes"><?= e(I18n::t('admin.backups')) ?></a>
    </div>

    <div>
      <h3>Dernières actions</h3>
      <ul style="list-style:none;margin:12px 0 0;padding:0;display:flex;flex-direction:column;gap:8px">
        <?php foreach (array_slice($journal, 0, 8) as $entry): ?>
          <li style="font-size:13px">
            <span class="s"><?= e(date('d/m H:i', (int) strtotime((string) $entry['at']))) ?></span>
            <span class="t"><?= e((string) $entry['event']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div>
      <h3><?= e(I18n::t('admin.assistant')) ?></h3>
      <p style="font-size:13.5px;color:var(--text);margin:10px 0 0">
        <?= e(number_format((int) $assistant['month'], 0, ',', ' ')) ?> question(s) ces 30 derniers jours ·
        <?= e(number_format((int) $knowledge['chunks'], 0, ',', ' ')) ?> fragments indexés ·
        <?= (int) $knowledge['documents'] ?> document(s)
      </p>
      <a class="btn btn-ghost btn-sm" style="margin-top:14px" href="/admin/assistant">Voir les échanges</a>
      <a class="btn btn-ghost btn-sm" style="margin-top:14px" href="/admin/documents">Documents</a>
    </div>
  </div>
</div>
