<?php

use App\View;

/** @var array<string,mixed> $page */
/** @var array<string,array<string,mixed>> $kinds */
/** @var bool $isHome */

$slug = rawurlencode((string) $page['slug']);
$total = count($page['sections']);
?>
<p class="admin__crumbs"><a class="link" href="/admin/pages">← Toutes les pages</a></p>

<h1 class="admin__title"><?= View::e($page['title'] ?? $page['slug']) ?></h1>
<p class="admin__intro">
    <code><?= View::e($page['url']) ?></code>
    · <a class="link" href="<?= View::e($page['url']) ?>" target="_blank" rel="noopener">Voir la page ↗</a>
</p>

<section class="panel panel--wide">
    <h2 class="panel__title">Réglages &amp; menu</h2>
    <form method="post" action="/admin/page/<?= $slug ?>" class="grid-form">
        <input type="hidden" name="csrf" value="<?= View::e($csrf) ?>">

        <label class="field">
            <span class="field__label">Titre de la page</span>
            <input type="text" name="title" value="<?= View::e($page['title'] ?? '') ?>" required>
            <span class="field__hint">Apparaît dans l'onglet du navigateur et les résultats de recherche.</span>
        </label>

        <label class="field">
            <span class="field__label">Entrée de menu</span>
            <input type="text" name="navLabel" value="<?= View::e($page['navLabel']) ?>">
        </label>

        <label class="field field--narrow">
            <span class="field__label">Rang dans le menu</span>
            <input type="number" name="order" value="<?= View::e((string) $page['order']) ?>" min="1" max="999">
        </label>

        <label class="field field--check">
            <input type="checkbox" name="inNav" <?= $page['inNav'] ? 'checked' : '' ?>>
            <span>Afficher dans le menu</span>
        </label>

        <label class="field field--wide">
            <span class="field__label">Description pour les moteurs de recherche</span>
            <input type="text" name="description" maxlength="300"
                   value="<?= View::e($page['meta']['description'] ?? '') ?>">
        </label>

        <div class="field field--wide">
            <button type="submit" class="button">Enregistrer les réglages</button>
        </div>
    </form>
</section>

<section class="panel panel--wide">
    <h2 class="panel__title">Sections <span class="tag"><?= $total ?></span></h2>
    <p class="panel__label">
        Les sections s'enchaînent de haut en bas. Chacune affiche son dessin en
        particules pendant qu'elle est à l'écran.
    </p>

    <table class="table">
        <thead>
            <tr><th></th><th>Section</th><th>Type</th><th>Dessin</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($page['sections'] as $rank => $section): ?>
                <?php
                $id = rawurlencode((string) $section['id']);
                $kind = (string) ($section['kind'] ?? 'statement');
                $shape = $section['shape'];
                ?>
                <tr>
                    <td class="table__move">
                        <?php /* Deux formulaires distincts : un déplacement est une
                                 écriture, il doit passer par POST et son jeton. */ ?>
                        <form method="post" action="/admin/page/<?= $slug ?>/section/<?= $id ?>/deplacer">
                            <input type="hidden" name="csrf" value="<?= View::e($csrf) ?>">
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" class="icon-button" <?= $rank === 0 ? 'disabled' : '' ?>
                                    aria-label="Monter cette section">↑</button>
                        </form>
                        <form method="post" action="/admin/page/<?= $slug ?>/section/<?= $id ?>/deplacer">
                            <input type="hidden" name="csrf" value="<?= View::e($csrf) ?>">
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" class="icon-button" <?= $rank === $total - 1 ? 'disabled' : '' ?>
                                    aria-label="Descendre cette section">↓</button>
                        </form>
                    </td>
                    <td>
                        <strong><?= View::e($section['id']) ?></strong>
                        <span class="table__note">
                            <?= View::e(mb_substr(trim(implode(' ', (array) ($section['title'] ?? $section['quote'] ?? [$section['text'] ?? $section['formula'] ?? '']))), 0, 60)) ?>
                        </span>
                    </td>
                    <td><?= View::e($kinds[$kind]['label'] ?? $kind) ?></td>
                    <td>
                        <code><?= View::e($shape['src'] ?? $shape['preset'] ?? $shape['text'] ?? '—') ?></code>
                    </td>
                    <td class="table__actions">
                        <a class="button button--small"
                           href="/admin/page/<?= $slug ?>/section/<?= $id ?>">Contenu</a>
                        <a class="button button--small"
                           href="/admin/formes?page=<?= $slug ?>&amp;section=<?= $id ?>">Dessin</a>
                        <?php /* La confirmation est un attribut, pas du JavaScript
                                 fabriqué à partir du contenu : une apostrophe dans
                                 un titre — « Page d'essai » — casserait l'appel. */ ?>
                        <form method="post" action="/admin/page/<?= $slug ?>/section/<?= $id ?>/supprimer"
                              data-confirm="Supprimer la section « <?= View::e($section['id']) ?> » ?">
                            <input type="hidden" name="csrf" value="<?= View::e($csrf) ?>">
                            <button type="submit" class="button button--small button--danger"
                                    <?= $total <= 1 ? 'disabled title="Une page doit garder au moins une section."' : '' ?>>
                                Supprimer
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel panel--wide">
    <h2 class="panel__title">Ajouter une section</h2>
    <form method="post" action="/admin/page/<?= $slug ?>/section" class="grid-form">
        <input type="hidden" name="csrf" value="<?= View::e($csrf) ?>">

        <label class="field">
            <span class="field__label">Type</span>
            <select name="kind">
                <?php foreach ($kinds as $key => $kind): ?>
                    <option value="<?= View::e($key) ?>"><?= View::e($kind['label']) ?> — <?= View::e($kind['hint']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span class="field__label">Identifiant</span>
            <input type="text" name="id" placeholder="tarifs">
            <span class="field__hint">Sert d'ancre dans l'adresse. Déduit du type si vous le laissez vide.</span>
        </label>

        <div class="field field--wide">
            <button type="submit" class="button">Ajouter</button>
        </div>
    </form>
</section>

<?php if (!$isHome): ?>
    <section class="panel panel--wide panel--danger">
        <h2 class="panel__title">Supprimer cette page</h2>
        <p class="panel__label">
            La page disparaît du site et du menu. Une copie du fichier est conservée
            dans <code>var/backups/</code>.
        </p>
        <form method="post" action="/admin/page/<?= $slug ?>/supprimer"
              data-confirm="Supprimer définitivement la page « <?= View::e($page['title'] ?? $page['slug']) ?> » ?">
            <input type="hidden" name="csrf" value="<?= View::e($csrf) ?>">
            <button type="submit" class="button button--danger">Supprimer la page</button>
        </form>
    </section>
<?php else: ?>
    <p class="panel__label">
        L'accueil ne peut pas être supprimé : c'est lui qui répond à la racine du site.
    </p>
<?php endif; ?>

<script type="module" src="<?= View::e(View::asset('assets/js/admin-sections.js')) ?>"></script>
