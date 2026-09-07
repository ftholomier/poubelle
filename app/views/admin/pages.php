<?php
/** @var array $pages @var array $settings @var array $user */
use App\Security\Csrf;
$title = 'Pages & contenus';
echo App\Core\View::render('admin/partials/shell-open', compact('settings', 'user', 'active', 'newLeads', 'flash', 'title'));
?>
<header class="ad-head">
    <div>
        <h1 class="ad-head__title">Pages &amp; contenus</h1>
        <p class="ad-head__sub">Chaque page est un fichier JSON autonome, sauvegardé à chaque modification.</p>
    </div>
    <div class="ad-head__actions">
        <form method="post" class="ad-inline">
            <input type="hidden" name="_token" value="<?= e(Csrf::token('pages')) ?>">
            <input type="hidden" name="action" value="rebuild">
            <button type="submit" class="ad-btn ad-btn--ghost">Reconstruire l’index</button>
        </form>
        <a class="ad-btn ad-btn--primary" href="/admin/pages/nouvelle"><?= icon('plus', '', 16) ?> Nouvelle page</a>
    </div>
</header>

<section class="ad-panel">
    <div class="ad-scroll">
        <table class="ad-table">
            <thead>
                <tr><th>Titre</th><th>Adresse</th><th>Type</th><th>Menu</th><th>Statut</th><th>Modifiée</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (empty($pages)): ?>
                <tr><td colspan="7" class="ad-empty">Aucune page. Créez la première.</td></tr>
            <?php endif; ?>
            <?php foreach ($pages as $page): ?>
                <tr>
                    <td>
                        <a href="/admin/pages/<?= e($page['slug']) ?>"><strong><?= tr($page['title']) ?></strong></a>
                        <?php if (!empty($page['home'])): ?> <span class="ad-tag ad-tag--info">Accueil</span><?php endif; ?>
                    </td>
                    <td><code>/<?= e($page['slug']) ?></code></td>
                    <td><span class="ad-tag"><?= $page['type'] === 'post' ? 'Article' : 'Page' ?></span></td>
                    <td>
                        <?php if (!empty($page['in_menu'])): ?><span class="ad-tag ad-tag--info">Principal</span><?php endif; ?>
                        <?php if (!empty($page['in_footer'])): ?><span class="ad-tag">Pied</span><?php endif; ?>
                    </td>
                    <td><span class="ad-tag ad-tag--<?= $page['status'] === 'published' ? 'ok' : 'warn' ?>">
                        <?= $page['status'] === 'published' ? 'Publiée' : 'Brouillon' ?></span></td>
                    <td class="ad-nowrap"><?= e($page['updated_at'] ? date('d/m/Y H:i', strtotime((string) $page['updated_at']) ?: time()) : '—') ?></td>
                    <td>
                        <div class="ad-table__actions">
                            <a class="ad-btn ad-btn--ghost ad-btn--sm" href="/<?= e($page['slug']) ?>" target="_blank" rel="noopener">Voir</a>
                            <form method="post" class="ad-inline">
                                <input type="hidden" name="_token" value="<?= e(Csrf::token('pages')) ?>">
                                <input type="hidden" name="action" value="duplicate">
                                <input type="hidden" name="slug" value="<?= e($page['slug']) ?>">
                                <button type="submit" class="ad-btn ad-btn--ghost ad-btn--sm">Dupliquer</button>
                            </form>
                            <?php if (empty($page['home'])): ?>
                                <form method="post" class="ad-inline" data-confirm="Supprimer définitivement « <?= e(trRaw($page['title'])) ?> » ? Une sauvegarde restera disponible dans Sauvegardes.">
                                    <input type="hidden" name="_token" value="<?= e(Csrf::token('pages')) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="slug" value="<?= e($page['slug']) ?>">
                                    <button type="submit" class="ad-btn ad-btn--danger ad-btn--sm">Supprimer</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="ad-panel">
    <div class="ad-panel__head">
        <div>
            <h2 class="ad-panel__title">Ordre du menu</h2>
            <p class="ad-panel__hint">Numérotez les pages : les plus petits nombres apparaissent en premier.</p>
        </div>
    </div>
    <form method="post">
        <input type="hidden" name="_token" value="<?= e(Csrf::token('pages')) ?>">
        <input type="hidden" name="action" value="reorder">
        <div class="ad-list">
            <?php foreach ($pages as $index => $page): ?>
                <div class="ad-list__item">
                    <span class="ad-tag"><?= $index + 1 ?></span>
                    <span style="flex:1"><?= tr($page['nav_label'] ?? $page['title']) ?></span>
                    <input type="hidden" name="order[]" value="<?= e($page['slug']) ?>">
                    <code><?= (int) $page['order'] ?></code>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="ad-hint">L’ordre s’édite dans chaque page (champ « Position »). Ce bouton renumérote proprement.</p>
        <button type="submit" class="ad-btn ad-btn--primary ad-mt">Renuméroter le menu</button>
    </form>
</section>

<?= App\Core\View::render('admin/partials/shell-close') ?>
