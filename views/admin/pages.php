<?php

use App\View;

/** @var list<array<string,mixed>> $pages */
?>
<h1 class="admin__title">Pages &amp; menu</h1>
<p class="admin__intro">
    L'ordre ci-dessous est celui du menu du site. Une page retirée du menu reste
    accessible par son adresse.
</p>

<form method="post" action="/admin/pages/ordre" class="panel panel--wide">
    <input type="hidden" name="csrf" value="<?= View::e($csrf) ?>">

    <table class="table">
        <thead>
            <tr>
                <th>Rang</th>
                <th>Page</th>
                <th>Adresse</th>
                <th>Sections</th>
                <th>Menu</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pages as $rank => $page): ?>
                <tr>
                    <td>
                        <?php /* Un simple numéro : le serveur trie les pages dessus,
                                 puis les renumérote de 1 à n. Aucun script requis. */ ?>
                        <label class="sr-only" for="rang-<?= View::e($page['slug']) ?>">
                            Rang de <?= View::e($page['title'] ?? $page['slug']) ?>
                        </label>
                        <input
                            class="table__rank"
                            type="number"
                            id="rang-<?= View::e($page['slug']) ?>"
                            name="rang[<?= View::e($page['slug']) ?>]"
                            value="<?= View::e((string) $page['order']) ?>"
                            min="1" max="999">
                    </td>
                    <td>
                        <strong><?= View::e($page['title'] ?? $page['slug']) ?></strong>
                        <?php if ($page['slug'] === \App\Content::HOME): ?>
                            <span class="tag tag--muted">accueil</span>
                        <?php endif; ?>
                        <span class="table__note">Menu : <?= View::e($page['navLabel']) ?></span>
                    </td>
                    <td><code><?= View::e($page['url']) ?></code></td>
                    <td><?= count($page['sections']) ?></td>
                    <td><?= $page['inNav'] ? 'affichée' : '<span class="tag tag--muted">masquée</span>' ?></td>
                    <td class="table__actions">
                        <a class="button button--small" href="/admin/page/<?= View::e(rawurlencode($page['slug'])) ?>">Modifier</a>
                        <a class="link" href="<?= View::e($page['url']) ?>" target="_blank" rel="noopener">Voir ↗</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p class="panel__label">
        Changez les numéros puis enregistrez : le menu suivra cet ordre.
    </p>
    <button type="submit" class="button button--small">Enregistrer l'ordre du menu</button>
</form>

<section class="panel panel--wide">
    <h2 class="panel__title">Ajouter une page</h2>
    <form method="post" action="/admin/pages" class="grid-form">
        <input type="hidden" name="csrf" value="<?= View::e($csrf) ?>">

        <label class="field">
            <span class="field__label">Titre</span>
            <input type="text" name="title" required placeholder="Nos réalisations">
            <span class="field__hint">L'adresse en est déduite : « Nos réalisations » donne /nos-realisations.</span>
        </label>

        <label class="field">
            <span class="field__label">Entrée de menu</span>
            <input type="text" name="navLabel" placeholder="Réalisations">
            <span class="field__hint">Facultatif : le titre est repris si vous le laissez vide.</span>
        </label>

        <label class="field field--narrow">
            <span class="field__label">Rang dans le menu</span>
            <input type="number" name="order" value="<?= count($pages) + 1 ?>" min="1" max="999">
        </label>

        <label class="field">
            <span class="field__label">Première section</span>
            <select name="kind">
                <?php foreach ($kinds as $key => $kind): ?>
                    <option value="<?= View::e($key) ?>" <?= $key === 'hero' ? 'selected' : '' ?>>
                        <?= View::e($kind['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field field--wide">
            <span class="field__label">Description pour les moteurs de recherche</span>
            <input type="text" name="description" maxlength="300">
        </label>

        <label class="field field--check">
            <input type="checkbox" name="inNav" checked>
            <span>Afficher dans le menu</span>
        </label>

        <div class="field field--wide">
            <button type="submit" class="button">Créer la page</button>
        </div>
    </form>
</section>
