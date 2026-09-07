<?php
/** @var array $available @var string $editing @var array $reference @var array $strings
 *  @var array $settings @var array $languages @var array $user */
use App\Core\View; use App\I18n\Translator; use App\Security\Csrf;
$title = 'Langues & traductions';
echo View::render('admin/partials/shell-open', compact('settings', 'user', 'active', 'newLeads', 'flash', 'title'));
$keys = array_unique(array_merge(array_keys($reference), array_keys($strings)));
sort($keys);
?>
<header class="ad-head">
    <div>
        <h1 class="ad-head__title">Langues &amp; traductions</h1>
        <p class="ad-head__sub">
            Les contenus se traduisent dans chaque page. Ici, vous réglez les langues du site
            et les libellés d’interface (boutons, formulaires, messages).
        </p>
    </div>
</header>

<section class="ad-panel">
    <form method="post">
        <input type="hidden" name="_token" value="<?= e(Csrf::token('translations')) ?>">
        <input type="hidden" name="action" value="languages">

        <div class="ad-panel__head"><h2 class="ad-panel__title">Langues du site</h2></div>

        <div class="ad-field">
            <label class="ad-label" for="default">Langue par défaut (sans préfixe d’URL)</label>
            <select class="ad-input" id="default" name="default" style="max-width:280px">
                <?php foreach ($available as $code): ?>
                    <option value="<?= e($code) ?>"<?= $settings['i18n']['default'] === $code ? ' selected' : '' ?>>
                        <?= e(Translator::label($code)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="ad-field">
            <label class="ad-label">Langues publiées</label>
            <div class="ad-grid ad-grid--4">
                <?php foreach ($available as $code): ?>
                    <label class="ad-check">
                        <input type="checkbox" name="enabled[]" value="<?= e($code) ?>"
                            <?= in_array($code, $settings['i18n']['enabled'], true) ? ' checked' : '' ?>>
                        <span><?= e(Translator::label($code)) ?> <code>/<?= e($code) ?>/</code></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="ad-hint">
                Chaque langue publiée obtient son préfixe d’URL, sa balise <code>hreflang</code> et son entrée
                dans le plan de site. Un contenu non traduit reprend automatiquement la langue par défaut.
            </p>
        </div>

        <div class="ad-field">
            <label class="ad-switch">
                <input type="hidden" name="google_translate" value="0">
                <input type="checkbox" name="google_translate" value="1"<?= !empty($settings['i18n']['google_translate']) ? ' checked' : '' ?>>
                <span class="ad-switch__track"></span><span>Traduction automatique Google en complément</span>
            </label>
            <span class="ad-hint">
                Utile pour les langues sans traduction saisie : le widget Google Traduction prend le relais.
            </span>
        </div>

        <button type="submit" class="ad-btn ad-btn--primary">Enregistrer les langues</button>
    </form>
</section>

<section class="ad-panel">
    <div class="ad-panel__head">
        <div>
            <h2 class="ad-panel__title">Libellés d’interface</h2>
            <p class="ad-panel__hint">Colonne de gauche : la version de référence. Colonne de droite : votre traduction.</p>
        </div>
        <div>
            <?php foreach ($available as $code): ?>
                <a class="ad-btn ad-btn--<?= $code === $editing ? 'primary' : 'ghost' ?> ad-btn--sm"
                   href="/admin/traductions?lang=<?= e($code) ?>"><?= e(strtoupper($code)) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <form method="post">
        <input type="hidden" name="_token" value="<?= e(Csrf::token('translations')) ?>">
        <input type="hidden" name="action" value="strings">
        <input type="hidden" name="lang" value="<?= e($editing) ?>">

        <div class="ad-scroll">
            <table class="ad-table">
                <thead><tr><th style="width:22%">Clé</th><th style="width:34%">Référence</th><th>Traduction (<?= e(strtoupper($editing)) ?>)</th></tr></thead>
                <tbody>
                <?php foreach ($keys as $key): ?>
                    <tr>
                        <td><code style="font-size:.78rem"><?= e($key) ?></code></td>
                        <td style="color:var(--ad-muted)"><?= e((string) ($reference[$key] ?? '')) ?></td>
                        <td><input class="ad-input" name="strings[<?= e($key) ?>]"
                                   value="<?= e((string) ($strings[$key] ?? '')) ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <button type="submit" class="ad-btn ad-btn--primary ad-mt">Enregistrer les libellés</button>
    </form>
</section>

<?= View::render('admin/partials/shell-close') ?>
