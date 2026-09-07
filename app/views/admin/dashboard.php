<?php
/** @var array $stats @var array $recentLeads @var array $recentPages @var array $kb
 *  @var bool $aiReady @var array $health @var array $settings @var array $user */
$title = 'Tableau de bord';
echo App\Core\View::render('admin/partials/shell-open', compact('settings', 'user', 'active', 'newLeads', 'flash', 'title'));

$checks = [
    'data_writable'    => 'Dossier de données inscriptible',
    'backup_writable'  => 'Sauvegardes inscriptibles',
    'uploads_writable' => 'Téléversements inscriptibles',
    'data_outside_web' => 'Données hors racine web',
    'https'            => 'Connexion HTTPS',
    'mail'             => 'Envoi d’e-mails configuré',
    'ai_key'           => 'Clé Gemini configurée',
    'reviews_key'      => 'Clé Google Places configurée',
    'app_key'          => 'Clé applicative définie',
    'debug_off'        => 'Mode debug désactivé',
];
?>
<header class="ad-head">
    <div>
        <h1 class="ad-head__title">Bonjour <?= e(explode(' ', (string) $user['name'])[0]) ?> 👋</h1>
        <p class="ad-head__sub">Voici l’état du site aujourd’hui.</p>
    </div>
    <div class="ad-head__actions">
        <a class="ad-btn ad-btn--ghost" href="/" target="_blank" rel="noopener">Voir le site</a>
        <a class="ad-btn ad-btn--primary" href="/admin/pages/nouvelle"><?= icon('plus', '', 16) ?> Nouvelle page</a>
    </div>
</header>

<div class="ad-grid ad-grid--4">
    <?php
    $tiles = [
        ['icon' => 'document', 'value' => $stats['pages'],     'label' => 'pages (' . $stats['drafts'] . ' brouillon·s)', 'url' => '/admin/pages'],
        ['icon' => 'mail',     'value' => $stats['new_leads'], 'label' => 'nouvelles demandes ce mois', 'url' => '/admin/demandes'],
        ['icon' => 'star',     'value' => $stats['reviews'],   'label' => 'avis enregistrés', 'url' => '/admin/avis'],
        ['icon' => 'chat',     'value' => $kb['chunks'],       'label' => 'extraits indexés pour l’IA', 'url' => '/admin/assistant'],
    ];
    foreach ($tiles as $tile): ?>
        <a class="ad-stat" href="<?= e($tile['url']) ?>" style="text-decoration:none;color:inherit">
            <span class="ad-stat__icon"><?= icon($tile['icon'], '', 18) ?></span>
            <div class="ad-stat__value"><?= (int) $tile['value'] ?></div>
            <div class="ad-stat__label"><?= e($tile['label']) ?></div>
        </a>
    <?php endforeach; ?>
</div>

<div class="ad-cols ad-mt">
    <section class="ad-panel">
        <div class="ad-panel__head">
            <h2 class="ad-panel__title">Dernières demandes</h2>
            <a class="ad-btn ad-btn--ghost ad-btn--sm" href="/admin/demandes">Tout voir</a>
        </div>
        <?php if (empty($recentLeads)): ?>
            <p class="ad-empty">Aucune demande ce mois-ci.</p>
        <?php else: ?>
            <div class="ad-list">
                <?php foreach ($recentLeads as $lead): ?>
                    <div class="ad-list__item">
                        <span class="ad-tag ad-tag--<?= $lead['status'] === 'new' ? 'warn' : 'ok' ?>"><?= e($lead['source']) ?></span>
                        <span style="flex:1;min-width:0">
                            <strong><?= e($lead['name'] !== '' ? $lead['name'] : $lead['email']) ?></strong><br>
                            <small style="color:var(--ad-muted)"><?= e(mb_substr($lead['message'], 0, 70)) ?></small>
                        </span>
                        <small class="ad-nowrap" style="color:var(--ad-muted)">
                            <?= e(date('d/m H:i', strtotime((string) $lead['created_at']) ?: time())) ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="ad-panel">
        <div class="ad-panel__head">
            <h2 class="ad-panel__title">État du système</h2>
        </div>
        <div class="ad-list">
            <?php foreach ($checks as $key => $label):
                $ok = !empty($health[$key]); ?>
                <div class="ad-list__item">
                    <span class="ad-tag ad-tag--<?= $ok ? 'ok' : 'warn' ?>">
                        <?= icon($ok ? 'check' : 'clock', '', 13) ?><?= $ok ? 'OK' : 'À faire' ?>
                    </span>
                    <span><?= e($label) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (!$aiReady): ?>
            <p class="ad-hint ad-mt">
                Sans clé <code>GEMINI_API_KEY</code>, l’assistant répond quand même :
                il cite le meilleur extrait trouvé dans vos pages et documents.
            </p>
        <?php endif; ?>
    </section>
</div>

<section class="ad-panel">
    <div class="ad-panel__head">
        <div>
            <h2 class="ad-panel__title">Pages du site</h2>
            <p class="ad-panel__hint">Ordre du menu, statut et dernière modification.</p>
        </div>
        <a class="ad-btn ad-btn--ghost ad-btn--sm" href="/admin/pages">Gérer les pages</a>
    </div>
    <div class="ad-scroll">
        <table class="ad-table">
            <thead><tr><th>Page</th><th>Adresse</th><th>Statut</th><th>Modifiée</th></tr></thead>
            <tbody>
            <?php foreach ($recentPages as $page): ?>
                <tr>
                    <td><a href="/admin/pages/<?= e($page['slug']) ?>"><strong><?= tr($page['title']) ?></strong></a>
                        <?php if (!empty($page['home'])): ?><span class="ad-tag ad-tag--info">Accueil</span><?php endif; ?></td>
                    <td><code>/<?= e($page['slug']) ?></code></td>
                    <td><span class="ad-tag ad-tag--<?= $page['status'] === 'published' ? 'ok' : 'warn' ?>">
                        <?= $page['status'] === 'published' ? 'Publiée' : 'Brouillon' ?></span></td>
                    <td class="ad-nowrap"><?= e($page['updated_at'] ? date('d/m/Y H:i', strtotime((string) $page['updated_at']) ?: time()) : '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?= App\Core\View::render('admin/partials/shell-close') ?>
