<?php
/** @var array $user @var string $active @var int $newLeads @var array $settings */
use App\Security\Csrf;

$items = [
    ['url' => '/admin',               'key' => 'dashboard',    'icon' => 'chart',    'label' => 'Tableau de bord'],
    ['url' => '/admin/pages',         'key' => 'pages',        'icon' => 'document', 'label' => 'Pages & contenus'],
    ['url' => '/admin/demandes',      'key' => 'leads',        'icon' => 'mail',     'label' => 'Demandes', 'badge' => $newLeads],
    ['url' => '/admin/avis',          'key' => 'reviews',      'icon' => 'star',     'label' => 'Avis Google'],
    ['url' => '/admin/assistant',     'key' => 'assistant',    'icon' => 'chat',     'label' => 'Assistant IA'],
    ['url' => '/admin/medias',        'key' => 'media',        'icon' => 'spark',    'label' => 'Médias'],
    ['url' => '/admin/documents',     'key' => 'documents',    'icon' => 'document', 'label' => 'Documents IA'],
    ['url' => '/admin/traductions',   'key' => 'translations', 'icon' => 'globe',    'label' => 'Langues'],
    ['url' => '/admin/apparence',     'key' => 'appearance',   'icon' => 'glasses',  'label' => 'Charte graphique'],
    ['url' => '/admin/reglages',      'key' => 'settings',     'icon' => 'compass',  'label' => 'Réglages'],
    ['url' => '/admin/utilisateurs',  'key' => 'users',        'icon' => 'shield',   'label' => 'Comptes'],
    ['url' => '/admin/maintenance',   'key' => 'maintenance',  'icon' => 'clock',    'label' => 'Sauvegardes'],
];
?>
<aside class="ad-sidebar" id="ad-sidebar">
    <a class="ad-brand" href="/admin">
        <span class="ad-brand__mark"><?= icon('glasses', '', 22) ?></span>
        <span class="ad-brand__text">
            <strong>Back-office</strong>
            <span><?= e($settings['site']['name'] ?? '') ?></span>
        </span>
    </a>

    <nav class="ad-nav" aria-label="Navigation du back-office">
        <?php foreach ($items as $item): ?>
            <a class="ad-nav__link<?= ($active ?? '') === $item['key'] || (($active ?? '') === 'page-edit' && $item['key'] === 'pages') ? ' is-active' : '' ?>"
               href="<?= e($item['url']) ?>">
                <?= icon($item['icon'], 'ad-nav__icon', 18) ?>
                <span><?= e($item['label']) ?></span>
                <?php if (!empty($item['badge'])): ?>
                    <span class="ad-badge"><?= (int) $item['badge'] ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="ad-sidebar__foot">
        <a class="ad-nav__link" href="/" target="_blank" rel="noopener">
            <?= icon('arrow-right', 'ad-nav__icon', 18) ?><span>Voir le site</span>
        </a>
        <div class="ad-user">
            <span class="ad-user__avatar"><?= e(mb_strtoupper(mb_substr((string) $user['name'], 0, 1))) ?></span>
            <span class="ad-user__meta">
                <strong><?= e((string) $user['name']) ?></strong>
                <span><?= e((string) $user['role']) ?></span>
            </span>
            <form method="post" action="/admin/logout">
                <input type="hidden" name="_token" value="<?= e(Csrf::token('logout')) ?>">
                <button type="submit" class="ad-user__out" aria-label="Se déconnecter"><?= icon('close', '', 16) ?></button>
            </form>
        </div>
    </div>
</aside>
