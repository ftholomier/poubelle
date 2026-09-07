<?php
/** @var array $page @var ?string $original @var array $catalog @var array $icons
 *  @var array $revisions @var array $languages @var array $settings @var array $user */

use App\Content\Blocks;
use App\Core\View;
use App\Security\Csrf;

$title = $original === null ? 'Nouvelle page' : 'Modifier : ' . trRaw($page['title']);
echo View::render('admin/partials/shell-open', compact('settings', 'user', 'active', 'newLeads', 'flash', 'title'));
?>
<form method="post" data-page-form>
    <input type="hidden" name="_token" value="<?= e(Csrf::token('page')) ?>">

    <header class="ad-head">
        <div>
            <h1 class="ad-head__title"><?= e($title) ?></h1>
            <p class="ad-head__sub">
                <?php if ($original !== null): ?>
                    Adresse publique : <a href="/<?= e($page['slug']) ?>" target="_blank" rel="noopener">/<?= e($page['slug']) ?></a>
                <?php else: ?>
                    Renseignez le titre : l’adresse se remplit automatiquement.
                <?php endif; ?>
            </p>
        </div>
        <div class="ad-head__actions">
            <a class="ad-btn ad-btn--ghost" href="/admin/pages">Annuler</a>
            <button type="submit" class="ad-btn ad-btn--primary">Enregistrer</button>
        </div>
    </header>

    <div class="ad-cols" style="grid-template-columns:1fr 340px;align-items:start">
        <div>
            <!-- ----------------------------------------- Informations -->
            <section class="ad-panel">
                <div class="ad-panel__head"><h2 class="ad-panel__title">Informations</h2></div>

                <?= View::render('admin/partials/multilang', [
                    'name' => 'title', 'value' => $page['title'], 'label' => 'Titre de la page',
                    'languages' => $languages, 'type' => 'text', 'attrs' => 'data-slug-source required',
                ]) ?>

                <div class="ad-cols">
                    <div class="ad-field">
                        <label class="ad-label" for="slug">Adresse (slug)</label>
                        <input class="ad-input" id="slug" name="slug" type="text" data-slug
                               value="<?= e($page['slug']) ?>" pattern="[a-z0-9\-]+" required>
                        <span class="ad-hint">Lettres minuscules, chiffres et tirets uniquement.</span>
                    </div>
                    <div class="ad-field">
                        <label class="ad-label" for="order">Position dans le menu</label>
                        <input class="ad-input" id="order" name="order" type="number" value="<?= (int) $page['order'] ?>">
                    </div>
                </div>

                <?= View::render('admin/partials/multilang', [
                    'name' => 'nav_label', 'value' => $page['nav_label'], 'label' => 'Libellé dans le menu',
                    'languages' => $languages, 'type' => 'text',
                    'hint' => 'Vide = le titre de la page est utilisé.',
                ]) ?>

                <?= View::render('admin/partials/multilang', [
                    'name' => 'excerpt', 'value' => $page['excerpt'], 'label' => 'Résumé (listes d’articles)',
                    'languages' => $languages, 'type' => 'textarea',
                ]) ?>
            </section>

            <!-- ------------------------------------------------ Blocs -->
            <section class="ad-panel">
                <div class="ad-panel__head">
                    <div>
                        <h2 class="ad-panel__title">Blocs de la page</h2>
                        <p class="ad-panel__hint">Glissez la poignée pour réordonner. Cliquez sur un bandeau pour déplier.</p>
                    </div>
                    <div>
                        <label class="sr-only" for="add-block">Ajouter un bloc</label>
                        <select class="ad-input" id="add-block" data-block-add style="min-width:220px">
                            <option value="">+ Ajouter un bloc…</option>
                            <?php foreach ($catalog as $type => $meta): ?>
                                <option value="<?= e($type) ?>"><?= e($meta['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="ad-blocks" data-blocks>
                    <?php foreach ($page['blocks'] as $index => $block):
                        $type = (string) $block['type'];
                        if (!Blocks::exists($type)) { continue; }
                        $prefix = 'blocks[' . $index . ']';
                        ?>
                        <article class="ad-block">
                            <div class="ad-block__bar">
                                <span class="ad-block__handle" title="Déplacer"><?= icon('menu', '', 16) ?></span>
                                <span class="ad-block__type">
                                    <?= e(Blocks::labelFor($type)) ?>
                                    <small>Bloc n° <span data-block-position><?= $index + 1 ?></span>
                                        <?= trRaw($block['data']['title'] ?? '') !== '' ? ' — ' . e(mb_substr(trRaw($block['data']['title']), 0, 40)) : '' ?>
                                    </small>
                                </span>
                                <div class="ad-block__tools">
                                    <label class="ad-switch" title="Afficher ce bloc sur le site">
                                        <input type="hidden" name="<?= e($prefix) ?>[enabled]" value="0">
                                        <input type="checkbox" name="<?= e($prefix) ?>[enabled]" value="1"<?= !empty($block['enabled']) ? ' checked' : '' ?>>
                                        <span class="ad-switch__track"></span>
                                    </label>
                                    <button type="button" class="ad-btn ad-btn--ghost ad-btn--sm" data-block-move="up" aria-label="Monter">↑</button>
                                    <button type="button" class="ad-btn ad-btn--ghost ad-btn--sm" data-block-move="down" aria-label="Descendre">↓</button>
                                    <button type="button" class="ad-btn ad-btn--danger ad-btn--sm" data-block-remove aria-label="Supprimer">✕</button>
                                </div>
                            </div>

                            <div class="ad-block__body">
                                <input type="hidden" name="<?= e($prefix) ?>[id]" value="<?= e($block['id']) ?>">
                                <input type="hidden" name="<?= e($prefix) ?>[type]" value="<?= e($type) ?>">

                                <div class="ad-cols">
                                    <div class="ad-field">
                                        <label class="ad-label">Fond du bloc</label>
                                        <select class="ad-input" name="<?= e($prefix) ?>[theme]">
                                            <?php foreach (['light' => 'Blanc', 'surface' => 'Gris clair', 'dark' => 'Sombre'] as $value => $label): ?>
                                                <option value="<?= e($value) ?>"<?= $block['theme'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="ad-field">
                                        <label class="ad-label">Ancre (lien interne)</label>
                                        <input class="ad-input" type="text" name="<?= e($prefix) ?>[anchor]"
                                               value="<?= e($block['anchor']) ?>" placeholder="prestations">
                                    </div>
                                </div>

                                <?= View::render('admin/partials/block-fields', [
                                    'schema'    => Blocks::schemaFor($type),
                                    'data'      => $block['data'],
                                    'prefix'    => $prefix . '[data]',
                                    'languages' => $languages,
                                    'icons'     => $icons,
                                ]) ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($page['blocks'])): ?>
                    <p class="ad-empty">Aucun bloc pour l’instant. Choisissez un type ci-dessus pour commencer.</p>
                <?php endif; ?>
            </section>
        </div>

        <!-- ------------------------------------------------- Colonne -->
        <div>
            <section class="ad-panel">
                <div class="ad-panel__head"><h2 class="ad-panel__title">Publication</h2></div>

                <div class="ad-field">
                    <label class="ad-label" for="status">Statut</label>
                    <select class="ad-input" id="status" name="status">
                        <option value="published"<?= $page['status'] === 'published' ? ' selected' : '' ?>>Publiée</option>
                        <option value="draft"<?= $page['status'] !== 'published' ? ' selected' : '' ?>>Brouillon</option>
                    </select>
                </div>

                <div class="ad-field">
                    <label class="ad-label" for="type">Type</label>
                    <select class="ad-input" id="type" name="type">
                        <option value="page"<?= $page['type'] === 'page' ? ' selected' : '' ?>>Page</option>
                        <option value="post"<?= $page['type'] === 'post' ? ' selected' : '' ?>>Article d’actualité</option>
                    </select>
                </div>

                <?php foreach ([
                    'home'      => 'Page d’accueil du site',
                    'in_menu'   => 'Afficher dans le menu principal',
                    'in_footer' => 'Afficher dans le pied de page',
                ] as $field => $label): ?>
                    <div class="ad-field">
                        <label class="ad-switch">
                            <input type="hidden" name="<?= e($field) ?>" value="0">
                            <input type="checkbox" name="<?= e($field) ?>" value="1"<?= !empty($page[$field]) ? ' checked' : '' ?>>
                            <span class="ad-switch__track"></span><span><?= e($label) ?></span>
                        </label>
                    </div>
                <?php endforeach; ?>

                <div class="ad-field">
                    <label class="ad-label" for="cover">Image de couverture (URL)</label>
                    <input class="ad-input" id="cover" name="cover" type="text" value="<?= e($page['cover']) ?>">
                </div>

                <button type="submit" class="ad-btn ad-btn--primary" style="width:100%">Enregistrer la page</button>
            </section>

            <section class="ad-panel">
                <div class="ad-panel__head"><h2 class="ad-panel__title">Référencement</h2></div>

                <?= View::render('admin/partials/multilang', [
                    'name' => 'seo_title', 'value' => $page['seo']['title'], 'label' => 'Titre pour les moteurs',
                    'languages' => $languages, 'type' => 'text', 'hint' => 'Vide = titre de la page. 60 caractères conseillés.',
                ]) ?>
                <?= View::render('admin/partials/multilang', [
                    'name' => 'seo_description', 'value' => $page['seo']['description'], 'label' => 'Description',
                    'languages' => $languages, 'type' => 'textarea', 'hint' => '150 à 160 caractères.',
                ]) ?>

                <div class="ad-field">
                    <label class="ad-label" for="og_image">Image de partage (URL)</label>
                    <input class="ad-input" id="og_image" name="og_image" type="text" value="<?= e($page['seo']['og_image']) ?>">
                </div>
                <div class="ad-field">
                    <label class="ad-switch">
                        <input type="hidden" name="noindex" value="0">
                        <input type="checkbox" name="noindex" value="1"<?= !empty($page['seo']['noindex']) ? ' checked' : '' ?>>
                        <span class="ad-switch__track"></span><span>Exclure des moteurs de recherche</span>
                    </label>
                </div>
            </section>

            <?php if ($revisions): ?>
                <section class="ad-panel">
                    <div class="ad-panel__head">
                        <div>
                            <h2 class="ad-panel__title">Versions précédentes</h2>
                            <p class="ad-panel__hint">Chaque enregistrement crée une version restaurable.</p>
                        </div>
                    </div>
                    <div class="ad-list">
                        <?php foreach (array_slice($revisions, 0, 10) as $revision): ?>
                            <div class="ad-list__item">
                                <span style="flex:1"><?= e($revision['date']) ?></span>
                                <button type="submit" name="action" value="restore"
                                        class="ad-btn ad-btn--ghost ad-btn--sm"
                                        onclick="return confirm('Restaurer cette version ? Le contenu actuel sera archivé avant remplacement.')">
                                    Restaurer
                                </button>
                                <input type="hidden" name="revision" value="<?= e($revision['name']) ?>" disabled
                                       data-revision="<?= e($revision['name']) ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="ad-hint">
                        Sélectionnez d’abord la version, puis validez : la restauration archive l’état courant.
                    </p>
                </section>
            <?php endif; ?>
        </div>
    </div>
</form>

<script>
/* Active le champ « revision » du bouton cliqué (un seul envoyé au serveur). */
document.addEventListener('click', function (event) {
    var button = event.target.closest('button[value="restore"]');
    if (!button) { return; }
    document.querySelectorAll('input[name="revision"]').forEach(function (input) { input.disabled = true; });
    var sibling = button.parentElement.querySelector('input[name="revision"]');
    if (sibling) { sibling.disabled = false; }
});
</script>

<?= View::render('admin/partials/shell-close') ?>
