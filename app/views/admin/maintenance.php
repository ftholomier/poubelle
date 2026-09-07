<?php
/** @var array $files @var array $health @var array $logs @var array $settings @var array $user */
use App\Core\View; use App\Security\Csrf;
$title = 'Sauvegardes & maintenance';
echo View::render('admin/partials/shell-open', compact('settings', 'user', 'active', 'newLeads', 'flash', 'title'));

$checks = [
    'data_writable'    => 'Dossier de données inscriptible',
    'backup_writable'  => 'Dossier de sauvegardes inscriptible',
    'uploads_writable' => 'Dossier de téléversements inscriptible',
    'data_outside_web' => 'Données stockées hors de la racine web',
    'https'            => 'Connexion chiffrée (HTTPS)',
    'app_key'          => 'Clé applicative définie dans .env',
    'debug_off'        => 'Mode debug désactivé',
    'mail'             => 'Transport e-mail réel configuré',
];
?>
<header class="ad-head">
    <div>
        <h1 class="ad-head__title">Sauvegardes &amp; maintenance</h1>
        <p class="ad-head__sub">
            Chaque enregistrement archive automatiquement la version précédente.
            Vous pouvez revenir en arrière fichier par fichier, sans toucher au reste du site.
        </p>
    </div>
    <div class="ad-head__actions">
        <form method="post" class="ad-inline">
            <input type="hidden" name="_token" value="<?= e(Csrf::token('maintenance')) ?>">
            <input type="hidden" name="action" value="export">
            <button type="submit" class="ad-btn ad-btn--primary">Télécharger une sauvegarde complète</button>
        </form>
    </div>
</header>

<section class="ad-panel">
    <div class="ad-panel__head"><h2 class="ad-panel__title">Contrôles de sécurité</h2></div>
    <div class="ad-grid ad-grid--2">
        <?php foreach ($checks as $key => $label): $ok = !empty($health[$key]); ?>
            <div class="ad-list__item">
                <span class="ad-tag ad-tag--<?= $ok ? 'ok' : 'warn' ?>">
                    <?= icon($ok ? 'check' : 'clock', '', 13) ?><?= $ok ? 'OK' : 'À vérifier' ?>
                </span>
                <span><?= e($label) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="ad-panel">
    <div class="ad-panel__head">
        <div>
            <h2 class="ad-panel__title">Outils</h2>
            <p class="ad-panel__hint">À utiliser après une restauration, une copie de fichiers ou une mise à jour.</p>
        </div>
    </div>
    <div class="ad-grid ad-grid--3">
        <?php foreach ([
            ['rebuild-index', 'Reconstruire l’index des pages', 'Répare le menu et le plan de site à partir des fichiers réellement présents.'],
            ['rebuild-kb',    'Réindexer l’assistant',          'Recalcule les extraits utilisés par l’assistant IA.'],
            ['clear-cache',   'Vider les caches',               'Supprime le cache des avis et de la base de connaissance.'],
        ] as [$action, $label, $hint]): ?>
            <form method="post" class="ad-panel" style="margin:0;background:var(--ad-bg)">
                <input type="hidden" name="_token" value="<?= e(Csrf::token('maintenance')) ?>">
                <input type="hidden" name="action" value="<?= e($action) ?>">
                <h3><?= e($label) ?></h3>
                <p class="ad-hint" style="margin-bottom:14px"><?= e($hint) ?></p>
                <button type="submit" class="ad-btn ad-btn--ghost ad-btn--sm">Lancer</button>
            </form>
        <?php endforeach; ?>
    </div>
</section>

<section class="ad-panel">
    <div class="ad-panel__head">
        <div>
            <h2 class="ad-panel__title">Versions conservées</h2>
            <p class="ad-panel__hint">Jusqu’à 30 versions par fichier. La restauration archive d’abord l’état courant.</p>
        </div>
    </div>
    <div class="ad-scroll">
        <table class="ad-table">
            <thead><tr><th>Fichier</th><th>Taille</th><th>Modifié</th><th>Versions</th><th>Restaurer</th></tr></thead>
            <tbody>
            <?php foreach ($files as $label => $file): ?>
                <tr>
                    <td><strong><?= e($label) ?></strong></td>
                    <td class="ad-nowrap"><?= $file['exists'] ? e(number_format($file['size'] / 1024, 1, ',', ' ')) . ' Ko' : '—' ?></td>
                    <td class="ad-nowrap"><?= e($file['modified']) ?></td>
                    <td><span class="ad-tag"><?= count($file['revisions']) ?></span></td>
                    <td>
                        <?php if ($file['revisions']): ?>
                            <form method="post" class="ad-inline" data-confirm="Restaurer cette version ? L’état actuel sera archivé avant remplacement.">
                                <input type="hidden" name="_token" value="<?= e(Csrf::token('maintenance')) ?>">
                                <input type="hidden" name="action" value="restore">
                                <input type="hidden" name="file" value="<?= e($file['key']) ?>">
                                <select class="ad-input" name="revision" style="min-width:190px;display:inline-block;width:auto">
                                    <?php foreach (array_slice($file['revisions'], 0, 30) as $revision): ?>
                                        <option value="<?= e($revision['name']) ?>"><?= e($revision['date']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="ad-btn ad-btn--ghost ad-btn--sm">Restaurer</button>
                            </form>
                        <?php else: ?>
                            <span class="ad-hint">Aucune version antérieure.</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="ad-panel">
    <div class="ad-panel__head"><h2 class="ad-panel__title">Journal des actions</h2></div>
    <?php if (empty($logs)): ?>
        <p class="ad-empty">Aucune action enregistrée pour l’instant.</p>
    <?php else: ?>
        <div class="ad-log"><?php foreach ($logs as $line) { echo e($line) . "\n"; } ?></div>
    <?php endif; ?>
</section>

<?= View::render('admin/partials/shell-close') ?>
