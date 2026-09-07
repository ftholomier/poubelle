<?php
/** @var array $leads @var array $months @var string $month @var array $settings @var array $user */
use App\Core\View; use App\Security\Csrf;
$title = 'Demandes reçues';
echo View::render('admin/partials/shell-open', compact('settings', 'user', 'active', 'newLeads', 'flash', 'title'));
$statuses = ['new' => 'Nouvelle', 'processing' => 'En cours', 'done' => 'Traitée', 'archived' => 'Archivée'];
?>
<header class="ad-head">
    <div>
        <h1 class="ad-head__title">Demandes reçues</h1>
        <p class="ad-head__sub">Formulaire de contact, fenêtre de sortie et assistant. Les adresses IP ne sont jamais conservées en clair.</p>
    </div>
    <div class="ad-head__actions">
        <form method="get" class="ad-inline">
            <label class="sr-only" for="mois">Mois</label>
            <select class="ad-input" id="mois" name="mois" onchange="this.form.submit()">
                <?php foreach ($months as $value): ?>
                    <option value="<?= e($value) ?>"<?= $value === $month ? ' selected' : '' ?>><?= e($value) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <form method="post" class="ad-inline">
            <input type="hidden" name="_token" value="<?= e(Csrf::token('leads')) ?>">
            <input type="hidden" name="action" value="export">
            <button type="submit" class="ad-btn ad-btn--ghost">Exporter en CSV</button>
        </form>
    </div>
</header>

<section class="ad-panel">
    <?php if (empty($leads)): ?>
        <p class="ad-empty">Aucune demande sur cette période.</p>
    <?php else: ?>
        <div class="ad-scroll">
            <table class="ad-table">
                <thead><tr><th>Date</th><th>Contact</th><th>Sujet</th><th>Message</th><th>Statut</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($leads as $lead): ?>
                    <tr>
                        <td class="ad-nowrap">
                            <?= e(date('d/m/Y', strtotime((string) $lead['created_at']) ?: time())) ?><br>
                            <small style="color:var(--ad-muted)"><?= e(date('H:i', strtotime((string) $lead['created_at']) ?: time())) ?></small>
                        </td>
                        <td>
                            <strong><?= e($lead['name'] !== '' ? $lead['name'] : '—') ?></strong><br>
                            <a href="mailto:<?= e($lead['email']) ?>"><?= e($lead['email']) ?></a>
                            <?php if ($lead['phone'] !== ''): ?><br><small><?= e($lead['phone']) ?></small><?php endif; ?>
                            <?php if ($lead['company'] !== ''): ?><br><small style="color:var(--ad-muted)"><?= e($lead['company']) ?></small><?php endif; ?>
                        </td>
                        <td>
                            <span class="ad-tag"><?= e($lead['subject'] !== '' ? $lead['subject'] : $lead['source']) ?></span><br>
                            <small style="color:var(--ad-muted)"><?= e($lead['source']) ?> · <?= e(strtoupper($lead['lang'])) ?></small>
                        </td>
                        <td style="max-width:340px"><?= nl2br(e(mb_substr($lead['message'], 0, 400))) ?></td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="_token" value="<?= e(Csrf::token('leads')) ?>">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="id" value="<?= e($lead['id']) ?>">
                                <select class="ad-input" name="status" onchange="this.form.submit()" style="min-width:130px">
                                    <?php foreach ($statuses as $value => $label): ?>
                                        <option value="<?= e($value) ?>"<?= $lead['status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <div class="ad-table__actions">
                                <a class="ad-btn ad-btn--ghost ad-btn--sm"
                                   href="mailto:<?= e($lead['email']) ?>?subject=<?= e(rawurlencode('Votre demande — Le Comptable à Lunettes')) ?>">Répondre</a>
                                <form method="post" class="ad-inline" data-confirm="Supprimer définitivement cette demande ?">
                                    <input type="hidden" name="_token" value="<?= e(Csrf::token('leads')) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e($lead['id']) ?>">
                                    <button type="submit" class="ad-btn ad-btn--danger ad-btn--sm">✕</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?= View::render('admin/partials/shell-close') ?>
