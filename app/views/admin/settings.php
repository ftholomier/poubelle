<?php
/** @var array $settings @var array $languages @var array $user */
use App\Core\View;
use App\Security\Csrf;
$title = 'Réglages';
echo View::render('admin/partials/shell-open', compact('settings', 'user', 'active', 'newLeads', 'flash', 'title'));
$site = $settings['site']; $cta = $settings['cta']; $popup = $settings['exit_popup'];
?>
<form method="post">
    <input type="hidden" name="_token" value="<?= e(Csrf::token('settings')) ?>">

    <header class="ad-head">
        <div>
            <h1 class="ad-head__title">Réglages du site</h1>
            <p class="ad-head__sub">Coordonnées, appels à l’action, pop-up de sortie, référencement, mentions légales.</p>
        </div>
        <div class="ad-head__actions"><button type="submit" class="ad-btn ad-btn--primary">Enregistrer</button></div>
    </header>

    <section class="ad-panel">
        <div class="ad-panel__head"><h2 class="ad-panel__title">Identité &amp; coordonnées</h2></div>
        <div class="ad-cols">
            <div class="ad-field">
                <label class="ad-label" for="site_name">Nom du site</label>
                <input class="ad-input" id="site_name" name="site_name" value="<?= e($site['name']) ?>">
            </div>
            <div class="ad-field">
                <label class="ad-label" for="legal_name">Raison sociale / signature</label>
                <input class="ad-input" id="legal_name" name="legal_name" value="<?= e($site['legal_name']) ?>">
            </div>
        </div>

        <?= View::render('admin/partials/multilang', ['name' => 'tagline', 'value' => $site['tagline'],
            'label' => 'Accroche', 'languages' => $languages, 'type' => 'textarea', 'rows' => 2]) ?>

        <div class="ad-cols">
            <div class="ad-field">
                <label class="ad-label" for="site_email">E-mail public</label>
                <input class="ad-input" id="site_email" name="site_email" type="email" value="<?= e($site['email']) ?>">
            </div>
            <div class="ad-field">
                <label class="ad-label" for="notify_email">E-mail de réception des demandes</label>
                <input class="ad-input" id="notify_email" name="notify_email" type="email"
                       value="<?= e($settings['forms']['notify_email']) ?>" placeholder="identique à l’e-mail public si vide">
            </div>
        </div>
        <div class="ad-cols">
            <div class="ad-field">
                <label class="ad-label" for="site_phone">Téléphone (technique)</label>
                <input class="ad-input" id="site_phone" name="site_phone" value="<?= e($site['phone']) ?>" placeholder="+33612345678">
            </div>
            <div class="ad-field">
                <label class="ad-label" for="site_phone_display">Téléphone (affiché)</label>
                <input class="ad-input" id="site_phone_display" name="site_phone_display"
                       value="<?= e($site['phone_display']) ?>" placeholder="06 12 34 56 78">
            </div>
        </div>
        <div class="ad-cols">
            <div class="ad-field">
                <label class="ad-label" for="site_address">Adresse</label>
                <input class="ad-input" id="site_address" name="site_address" value="<?= e($site['address']) ?>">
            </div>
            <div class="ad-field">
                <label class="ad-label" for="map_query">Adresse pour la carte</label>
                <input class="ad-input" id="map_query" name="map_query" value="<?= e($site['map_query']) ?>">
            </div>
        </div>
        <div class="ad-cols">
            <div class="ad-field">
                <label class="ad-label" for="site_zip">Code postal</label>
                <input class="ad-input" id="site_zip" name="site_zip" value="<?= e($site['zip']) ?>">
            </div>
            <div class="ad-field">
                <label class="ad-label" for="site_city">Ville</label>
                <input class="ad-input" id="site_city" name="site_city" value="<?= e($site['city']) ?>">
            </div>
        </div>

        <?= View::render('admin/partials/multilang', ['name' => 'hours', 'value' => $site['hours'],
            'label' => 'Horaires', 'languages' => $languages, 'type' => 'text']) ?>
    </section>

    <section class="ad-panel">
        <div class="ad-panel__head"><h2 class="ad-panel__title">Réseaux sociaux</h2></div>
        <div class="ad-grid ad-grid--2">
            <?php foreach (['instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'facebook' => 'Facebook',
                            'youtube' => 'YouTube', 'tiktok' => 'TikTok'] as $key => $label): ?>
                <div class="ad-field">
                    <label class="ad-label" for="social_<?= e($key) ?>"><?= e($label) ?></label>
                    <input class="ad-input" id="social_<?= e($key) ?>" name="social_<?= e($key) ?>" type="url"
                           value="<?= e($settings['social'][$key] ?? '') ?>" placeholder="https://…">
                </div>
            <?php endforeach; ?>
        </div>
        <p class="ad-hint">Seules les adresses en <code>https://</code> sont acceptées. Laissez vide pour masquer le lien.</p>
    </section>

    <section class="ad-panel">
        <div class="ad-panel__head">
            <div>
                <h2 class="ad-panel__title">Barre d’action collée en bas d’écran</h2>
                <p class="ad-panel__hint">Visible sur toutes les pages, centrée en bas de la fenêtre.</p>
            </div>
        </div>

        <div class="ad-field">
            <label class="ad-switch">
                <input type="hidden" name="sticky_enabled" value="0">
                <input type="checkbox" name="sticky_enabled" value="1"<?= !empty($cta['sticky_enabled']) ? ' checked' : '' ?>>
                <span class="ad-switch__track"></span><span>Afficher la barre</span>
            </label>
        </div>

        <div class="ad-cols">
            <div>
                <?= View::render('admin/partials/multilang', ['name' => 'cta_contact_label', 'value' => $cta['contact']['label'],
                    'label' => 'Bouton 1 — libellé', 'languages' => $languages, 'type' => 'text']) ?>
                <div class="ad-field">
                    <label class="ad-label" for="cta_contact_url">Bouton 1 — lien</label>
                    <input class="ad-input" id="cta_contact_url" name="cta_contact_url" value="<?= e($cta['contact']['url']) ?>">
                </div>
            </div>
            <div>
                <?= View::render('admin/partials/multilang', ['name' => 'cta_coaching_label', 'value' => $cta['coaching']['label'],
                    'label' => 'Bouton 2 — libellé', 'languages' => $languages, 'type' => 'text']) ?>
                <div class="ad-field">
                    <label class="ad-label" for="cta_coaching_url">Bouton 2 — lien</label>
                    <input class="ad-input" id="cta_coaching_url" name="cta_coaching_url" value="<?= e($cta['coaching']['url']) ?>">
                </div>
                <div class="ad-field">
                    <label class="ad-switch">
                        <input type="hidden" name="cta_halo" value="0">
                        <input type="checkbox" name="cta_halo" value="1"<?= !empty($cta['coaching']['halo']) ? ' checked' : '' ?>>
                        <span class="ad-switch__track"></span><span>Halo animé sur ce bouton</span>
                    </label>
                </div>
            </div>
        </div>

        <?= View::render('admin/partials/multilang', ['name' => 'cta_note', 'value' => $cta['note'],
            'label' => 'Mention à côté des boutons', 'languages' => $languages, 'type' => 'text']) ?>
    </section>

    <section class="ad-panel">
        <div class="ad-panel__head">
            <div>
                <h2 class="ad-panel__title">Fenêtre d’intention de sortie</h2>
                <p class="ad-panel__hint">S’affiche quand le visiteur s’apprête à quitter le site.</p>
            </div>
        </div>

        <div class="ad-field">
            <label class="ad-switch">
                <input type="hidden" name="popup_enabled" value="0">
                <input type="checkbox" name="popup_enabled" value="1"<?= !empty($popup['enabled']) ? ' checked' : '' ?>>
                <span class="ad-switch__track"></span><span>Activer la fenêtre</span>
            </label>
        </div>

        <div class="ad-grid ad-grid--3">
            <div class="ad-field">
                <label class="ad-label" for="popup_delay">Délai avant armement (ms)</label>
                <input class="ad-input" id="popup_delay" name="popup_delay" type="number" value="<?= (int) $popup['delay_ms'] ?>">
            </div>
            <div class="ad-field">
                <label class="ad-label" for="popup_frequency">Ne pas réafficher pendant (jours)</label>
                <input class="ad-input" id="popup_frequency" name="popup_frequency" type="number" value="<?= (int) $popup['frequency_days'] ?>">
            </div>
            <div class="ad-field">
                <label class="ad-label" for="popup_mobile">Déclenchement mobile (secondes)</label>
                <input class="ad-input" id="popup_mobile" name="popup_mobile" type="number" value="<?= (int) $popup['mobile_timeout'] ?>">
            </div>
        </div>

        <?= View::render('admin/partials/multilang', ['name' => 'popup_eyebrow', 'value' => $popup['eyebrow'], 'label' => 'Sur-titre', 'languages' => $languages, 'type' => 'text']) ?>
        <?= View::render('admin/partials/multilang', ['name' => 'popup_title', 'value' => $popup['title'], 'label' => 'Titre', 'languages' => $languages, 'type' => 'text']) ?>
        <?= View::render('admin/partials/multilang', ['name' => 'popup_text', 'value' => $popup['text'], 'label' => 'Texte', 'languages' => $languages, 'type' => 'textarea']) ?>
        <?= View::render('admin/partials/multilang', ['name' => 'popup_cta_label', 'value' => $popup['cta_label'], 'label' => 'Libellé du bouton', 'languages' => $languages, 'type' => 'text']) ?>
        <?= View::render('admin/partials/multilang', ['name' => 'popup_dismiss', 'value' => $popup['dismiss'], 'label' => 'Lien de refus', 'languages' => $languages, 'type' => 'text']) ?>

        <div class="ad-cols">
            <div class="ad-field">
                <label class="ad-label" for="popup_cta_url">Lien du bouton</label>
                <input class="ad-input" id="popup_cta_url" name="popup_cta_url" value="<?= e($popup['cta_url']) ?>">
            </div>
            <div class="ad-field">
                <label class="ad-switch">
                    <input type="hidden" name="popup_capture" value="0">
                    <input type="checkbox" name="popup_capture" value="1"<?= !empty($popup['capture_email']) ? ' checked' : '' ?>>
                    <span class="ad-switch__track"></span><span>Capturer l’e-mail dans la fenêtre</span>
                </label>
            </div>
        </div>
    </section>

    <section class="ad-panel">
        <div class="ad-panel__head"><h2 class="ad-panel__title">Formulaires</h2></div>
        <?= View::render('admin/partials/multilang', ['name' => 'success_message', 'value' => $settings['forms']['success_message'],
            'label' => 'Message de confirmation', 'languages' => $languages, 'type' => 'textarea', 'rows' => 2]) ?>
    </section>

    <section class="ad-panel">
        <div class="ad-panel__head"><h2 class="ad-panel__title">Référencement</h2></div>
        <div class="ad-cols">
            <div class="ad-field">
                <label class="ad-label" for="title_suffix">Suffixe des titres</label>
                <input class="ad-input" id="title_suffix" name="title_suffix" value="<?= e($settings['seo']['title_suffix']) ?>">
            </div>
            <div class="ad-field">
                <label class="ad-label" for="analytics_id">Identifiant Google Analytics</label>
                <input class="ad-input" id="analytics_id" name="analytics_id" value="<?= e($settings['seo']['analytics_id']) ?>" placeholder="G-XXXXXXX">
            </div>
        </div>
        <?= View::render('admin/partials/multilang', ['name' => 'seo_description', 'value' => $settings['seo']['description'],
            'label' => 'Description par défaut', 'languages' => $languages, 'type' => 'textarea']) ?>
        <div class="ad-cols">
            <div class="ad-field">
                <label class="ad-label" for="og_image">Image de partage par défaut</label>
                <input class="ad-input" id="og_image" name="og_image" value="<?= e($settings['seo']['og_image']) ?>">
            </div>
            <div class="ad-field">
                <label class="ad-switch">
                    <input type="hidden" name="indexable" value="0">
                    <input type="checkbox" name="indexable" value="1"<?= !str_contains($settings['seo']['robots'], 'noindex') ? ' checked' : '' ?>>
                    <span class="ad-switch__track"></span><span>Autoriser l’indexation par les moteurs</span>
                </label>
            </div>
        </div>
    </section>

    <section class="ad-panel">
        <div class="ad-panel__head"><h2 class="ad-panel__title">Mentions légales</h2></div>
        <div class="ad-grid ad-grid--2">
            <?php foreach (['director' => 'Directeur de la publication', 'siret' => 'SIRET', 'rcs' => 'RCS',
                            'tva' => 'N° de TVA', 'order' => 'Inscription à l’Ordre', 'host' => 'Hébergeur'] as $key => $label): ?>
                <div class="ad-field">
                    <label class="ad-label" for="legal_<?= e($key) ?>"><?= e($label) ?></label>
                    <input class="ad-input" id="legal_<?= e($key) ?>" name="legal_<?= e($key) ?>"
                           value="<?= e($settings['legal'][$key] ?? '') ?>">
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <button type="submit" class="ad-btn ad-btn--primary">Enregistrer les réglages</button>
</form>

<?= View::render('admin/partials/shell-close') ?>
