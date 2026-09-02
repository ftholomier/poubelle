<?php use App\View; ?>
<h1 class="admin__title">Pages &amp; sections</h1>
<p class="admin__intro">
    Chaque section affiche un dessin en particules pendant qu'elle est à l'écran.
    « Régler le dessin » ouvre l'atelier directement sur la section concernée.
</p>

<?php foreach ($pages as $page): ?>
    <section class="panel panel--wide">
        <header class="panel__head">
            <h2 class="panel__title">
                <?= View::e($page['title'] ?? $page['slug']) ?>
                <span class="tag"><?= View::e($page['url']) ?></span>
                <?php if (!$page['inNav']): ?><span class="tag tag--muted">hors menu</span><?php endif; ?>
            </h2>
            <a class="link" href="<?= View::e($page['url']) ?>" target="_blank" rel="noopener">Voir ↗</a>
        </header>

        <table class="table">
            <thead>
                <tr>
                    <th>Section</th>
                    <th>Type</th>
                    <th>Dessin</th>
                    <th>Particules</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($page['sections'] as $section): ?>
                    <?php $shape = $section['shape']; ?>
                    <tr>
                        <td>
                            <strong><?= View::e($section['id']) ?></strong>
                            <span class="tag tag--muted"><?= View::e($section['kind'] ?? 'statement') ?></span>
                        </td>
                        <td><?= View::e($shape['type']) ?></td>
                        <td>
                            <code><?= View::e($shape['src'] ?? $shape['preset'] ?? $shape['text'] ?? '—') ?></code>
                            <?php if (!empty($shape['label'])): ?>
                                <span class="table__note"><?= View::e($shape['label']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= number_format((int) $shape['count'], 0, ',', ' ') ?></td>
                        <td class="table__actions">
                            <a
                                class="button button--small"
                                href="/admin/formes?page=<?= View::e(rawurlencode($page['slug'])) ?>&amp;section=<?= View::e(rawurlencode((string) $section['id'])) ?>">
                                Régler le dessin
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endforeach; ?>
