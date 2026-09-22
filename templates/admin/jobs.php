<?php
/**
 * Offres d'emploi.
 * @var array  $items @var array $counts @var string $filter
 * @var int    $undated   annonces publiées sans date de fin
 * @var int    $graceEnd  horodatage de fin du délai de grâce
 */
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Services\Auth;
use App\Services\I18n;

$state = ['publish' => ['state-ok', 'admin.state_publish'], 'draft' => ['state-wait', 'admin.state_draft'],
          'pending' => ['state-wait', 'admin.state_pending'],
          'expired' => ['state-neutral', 'admin.state_expired'],
          'spam'    => ['state-err', 'admin.state_spam']];
?>
<div class="admin-head">
  <div><h1><?= e(I18n::t('admin.jobs')) ?></h1><p><?= count($items) ?> annonce(s)</p></div>
</div>

<?php if (($notice = Session::flash('notice')) !== null): ?>
  <div class="notice notice-ok" role="status"><?= e((string) $notice) ?></div>
<?php endif; ?>

<?php if (($undated ?? 0) > 0): ?>
  <?php // Les annonces reprises de l'ancien site n'ont pas de date de fin.
        // Plutôt que de les archiver dans le dos de l'exploitant, on annonce
        // l'échéance et on laisse le choix. ?>
  <div class="notice notice-wait" role="status">
    <strong><?= (int) $undated ?></strong> annonce(s) reprises de l’ancien site n’ont pas de date de fin.
    Elles restent en ligne jusqu’au <strong><?= e(format_date(date('c', (int) $graceEnd))) ?></strong>,
    puis passeront en archive. Prolongez celles qui valent la peine, ou tranchez maintenant :
    <form method="post" style="display:inline-flex;gap:8px;margin-left:8px">
      <?= Csrf::field('admin-jobs') ?>
      <button type="submit" name="bulk" value="date" class="btn btn-ghost btn-sm">
        Inscrire les dates
      </button>
      <button type="submit" name="bulk" value="archive" class="btn btn-ghost btn-sm"
              data-confirm="Archiver maintenant toutes les annonces sans date de fin ? Elles quitteront les listes ; chacune reste prolongeable.">
        Tout archiver
      </button>
    </form>
  </div>
<?php endif; ?>

<?= View::partial('admin/partials-state-tabs', [
      'counts' => $counts ?? [],
      'filter' => $filter ?? '',
      'labels' => [
        'pending' => I18n::t('admin.state_pending'),
        'publish' => I18n::t('admin.state_publish'),
        'expired' => I18n::t('admin.state_expired'),
        'draft'   => I18n::t('admin.state_draft'),
        'spam'    => I18n::t('admin.state_spam'),
      ],
    ]) ?>

<div class="admin-card">
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>Intitulé</th><th>Structure</th><th>Lieu</th><th>Publiée</th><th>En ligne jusqu’au</th><th>État</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($items as $job): $s = $state[$job['status']] ?? ['state-neutral', 'admin.state_draft']; ?>
          <tr>
            <td><span class="t"><?= e(str_excerpt((string) $job['title'], 64)) ?></span></td>
            <td class="s"><?= e($job['company']['name'] ?: '—') ?></td>
            <td class="s"><?= e($job['location']['city'] ?: ($job['location']['region'] ?: '—')) ?></td>
            <td class="s"><?= e(format_date((string) ($job['published_at'] ?: $job['created_at'])) ?: '—') ?></td>
            <td class="s"><?= e(format_date((string) ($job['expires_at'] ?? '')) ?: '—') ?></td>
            <td><span class="state <?= e($s[0]) ?>"><?= e(I18n::t($s[1])) ?></span></td>
            <td class="actions">
              <a class="btn btn-ghost btn-sm" href="<?= e(I18n::url('/offre/' . $job['slug'])) ?>" target="_blank" rel="noopener">Voir</a>
              <form method="post" style="display:inline">
                <?= Csrf::field('admin-jobs') ?>
                <input type="hidden" name="id" value="<?= e($job['id']) ?>">
                <button type="submit" name="action" value="<?= $job['status'] === 'publish' ? 'unpublish' : 'publish' ?>"
                        class="btn btn-ghost btn-sm"><?= $job['status'] === 'publish' ? 'Dépublier' : 'Publier' ?></button>
              </form>
              <?php if ($job['status'] === 'expired'): ?>
                <?php // Remet l'annonce en ligne pour une durée entière, sans
                      // avoir à rouvrir la fiche. ?>
                <form method="post" style="display:inline">
                  <?= Csrf::field('admin-jobs') ?>
                  <input type="hidden" name="id" value="<?= e($job['id']) ?>">
                  <button type="submit" name="action" value="extend" class="btn btn-ghost btn-sm">Prolonger</button>
                </form>
              <?php endif; ?>
              <?php if (Auth::isAdmin()): ?>
                <form method="post" style="display:inline">
                  <?= Csrf::field('admin-jobs') ?>
                  <input type="hidden" name="id" value="<?= e($job['id']) ?>">
                  <button type="submit" name="action" value="delete" class="btn btn-ghost btn-sm"
                          data-confirm="Supprimer définitivement cette annonce ? Un instantané est créé avant.">Supprimer</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
