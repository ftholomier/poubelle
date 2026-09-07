<?php
/** @var array $manual @var array $live @var array $settings @var array $user */
use App\Core\View; use App\Security\Csrf;
$title = 'Avis Google';
echo View::render('admin/partials/shell-open', compact('settings', 'user', 'active', 'newLeads', 'flash', 'title'));
$config = $settings['reviews'];
?>
<header class="ad-head">
    <div>
        <h1 class="ad-head__title">Avis Google</h1>
        <p class="ad-head__sub">
            En mode automatique, les avis proviennent de l’API Google Places. Les avis saisis ci-dessous
            servent de repli : le site affiche toujours quelque chose.
        </p>
    </div>
    <div class="ad-head__actions">
        <form method="post" class="ad-inline">
            <input type="hidden" name="_token" value="<?= e(Csrf::token('reviews')) ?>">
            <input type="hidden" name="action" value="refresh">
            <button type="submit" class="ad-btn ad-btn--ghost">Vider le cache Google</button>
        </form>
    </div>
</header>

<div class="ad-panel">
    <div class="ad-panel__head">
        <h2 class="ad-panel__title">Affichage actuel sur le site</h2>
        <span class="ad-tag ad-tag--<?= ($live['source'] ?? '') === 'google' ? 'ok' : 'info' ?>">
            Source : <?= ($live['source'] ?? '') === 'google' ? 'API Google' : 'saisie manuelle' ?>
        </span>
    </div>
    <p>
        Note moyenne affichée : <strong><?= e(number_format((float) ($live['rating'] ?? 0), 1, ',', ' ')) ?>/5</strong>
        sur <strong><?= (int) ($live['total'] ?? 0) ?></strong> avis.
    </p>
    <?php if ((string) \App\Core\Config::get('reviews.api_key', '') === ''): ?>
        <p class="ad-hint">
            Aucune clé <code>GOOGLE_PLACES_API_KEY</code> définie dans le fichier <code>.env</code> :
            seuls les avis saisis ci-dessous sont utilisés.
        </p>
    <?php endif; ?>
</div>

<form method="post">
    <input type="hidden" name="_token" value="<?= e(Csrf::token('reviews')) ?>">

    <section class="ad-panel">
        <div class="ad-panel__head"><h2 class="ad-panel__title">Paramètres</h2></div>

        <div class="ad-field">
            <label class="ad-switch">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1"<?= !empty($config['enabled']) ? ' checked' : '' ?>>
                <span class="ad-switch__track"></span><span>Afficher le bloc d’avis sur le site</span>
            </label>
        </div>

        <div class="ad-grid ad-grid--2">
            <div class="ad-field">
                <label class="ad-label" for="mode">Mode</label>
                <select class="ad-input" id="mode" name="mode">
                    <option value="auto"<?= $config['mode'] === 'auto' ? ' selected' : '' ?>>Automatique (API Google, repli manuel)</option>
                    <option value="manual"<?= $config['mode'] === 'manual' ? ' selected' : '' ?>>Manuel uniquement</option>
                </select>
            </div>
            <div class="ad-field">
                <label class="ad-label" for="place_id">Identifiant Google Place</label>
                <input class="ad-input" id="place_id" name="place_id" value="<?= e($config['place_id']) ?>"
                       placeholder="ChIJ…">
            </div>
            <div class="ad-field">
                <label class="ad-label" for="min_rating">Note minimale affichée</label>
                <input class="ad-input" id="min_rating" name="min_rating" type="number" min="1" max="5"
                       value="<?= (int) $config['min_rating'] ?>">
            </div>
            <div class="ad-field">
                <label class="ad-label" for="max_items">Nombre maximum d’avis</label>
                <input class="ad-input" id="max_items" name="max_items" type="number" min="1" max="20"
                       value="<?= (int) $config['max_items'] ?>">
            </div>
        </div>

        <div class="ad-field">
            <label class="ad-label" for="profile_url">Lien « Voir tous les avis »</label>
            <input class="ad-input" id="profile_url" name="profile_url" type="url"
                   value="<?= e($config['profile_url']) ?>" placeholder="https://g.page/…">
        </div>
    </section>

    <section class="ad-panel">
        <div class="ad-panel__head">
            <div>
                <h2 class="ad-panel__title">Avis saisis manuellement</h2>
                <p class="ad-panel__hint">Utilisés en repli, ou seuls en mode manuel.</p>
            </div>
        </div>

        <div class="ad-repeat" id="rep-reviews">
            <?php
            $render = static function (array $review, $index): void { ?>
                <div class="ad-repeat__item">
                    <button type="button" class="ad-repeat__remove" data-repeat-remove aria-label="Supprimer">
                        <?= icon('close', '', 15) ?>
                    </button>
                    <input type="hidden" name="items[<?= e((string) $index) ?>][id]" value="<?= e((string) ($review['id'] ?? '')) ?>">
                    <div class="ad-cols">
                        <div class="ad-field">
                            <label class="ad-label">Auteur</label>
                            <input class="ad-input" name="items[<?= e((string) $index) ?>][author]" value="<?= e((string) ($review['author'] ?? '')) ?>">
                        </div>
                        <div class="ad-field">
                            <label class="ad-label">Fonction / secteur</label>
                            <input class="ad-input" name="items[<?= e((string) $index) ?>][role]" value="<?= e((string) ($review['role'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="ad-cols">
                        <div class="ad-field">
                            <label class="ad-label">Note (1 à 5)</label>
                            <input class="ad-input" type="number" min="1" max="5"
                                   name="items[<?= e((string) $index) ?>][rating]" value="<?= (int) ($review['rating'] ?? 5) ?>">
                        </div>
                        <div class="ad-field">
                            <label class="ad-label">Date affichée</label>
                            <input class="ad-input" name="items[<?= e((string) $index) ?>][date]"
                                   value="<?= e((string) ($review['date'] ?? '')) ?>" placeholder="il y a 2 mois">
                        </div>
                    </div>
                    <div class="ad-field">
                        <label class="ad-label">Avis</label>
                        <textarea class="ad-input" rows="3" name="items[<?= e((string) $index) ?>][text]"><?= e((string) ($review['text'] ?? '')) ?></textarea>
                    </div>
                </div>
            <?php };

            foreach ($manual as $i => $review) { $render($review, $i); } ?>

            <template><?php $render([], '__INDEX__'); ?></template>
        </div>

        <button type="button" class="ad-btn ad-btn--ghost ad-btn--sm ad-mt" data-repeat-add="rep-reviews">+ Ajouter un avis</button>
    </section>

    <button type="submit" class="ad-btn ad-btn--primary">Enregistrer</button>
</form>

<?= View::render('admin/partials/shell-close') ?>
