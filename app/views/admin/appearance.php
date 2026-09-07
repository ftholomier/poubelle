<?php
/** @var array $settings @var array $defaults @var array $user */
use App\Core\View; use App\Security\Csrf;
$title = 'Charte graphique';
echo View::render('admin/partials/shell-open', compact('settings', 'user', 'active', 'newLeads', 'flash', 'title'));

$labels = [
    'ink' => 'Encre (fonds sombres, textes)', 'ink_soft' => 'Encre adoucie',
    'primary' => 'Couleur principale', 'primary_dark' => 'Principale foncée',
    'accent' => 'Accent (boutons d’action)', 'accent_dark' => 'Accent foncé',
    'mint' => 'Accent secondaire', 'bg' => 'Fond des pages',
    'surface' => 'Fond des sections claires', 'border' => 'Bordures', 'muted' => 'Texte secondaire',
];
$colors = $settings['brand']['colors'];
?>
<form method="post">
    <input type="hidden" name="_token" value="<?= e(Csrf::token('appearance')) ?>">

    <header class="ad-head">
        <div>
            <h1 class="ad-head__title">Charte graphique</h1>
            <p class="ad-head__sub">Ces valeurs alimentent directement les variables CSS du site : la modification est immédiate.</p>
        </div>
        <div class="ad-head__actions"><button type="submit" class="ad-btn ad-btn--primary">Enregistrer</button></div>
    </header>

    <section class="ad-panel">
        <div class="ad-panel__head"><h2 class="ad-panel__title">Couleurs</h2></div>
        <div class="ad-colors">
            <?php foreach ($labels as $key => $label):
                $value = $colors[$key] ?? ($defaults['brand']['colors'][$key] ?? '#000000'); ?>
                <div class="ad-color">
                    <input type="color" value="<?= e($value) ?>" aria-label="<?= e($label) ?>">
                    <span class="ad-color__meta">
                        <span class="ad-color__name"><?= e($label) ?></span>
                        <input class="ad-color__hex" type="text" name="color_<?= e($key) ?>"
                               value="<?= e($value) ?>" pattern="#[0-9A-Fa-f]{6}">
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="ad-hint">Format hexadécimal à six chiffres (ex. <code>#1B4F91</code>). Une valeur invalide revient au réglage d’origine.</p>
    </section>

    <section class="ad-panel">
        <div class="ad-panel__head"><h2 class="ad-panel__title">Typographie &amp; formes</h2></div>
        <div class="ad-cols">
            <div class="ad-field">
                <label class="ad-label" for="font_heading">Police des titres</label>
                <input class="ad-input" id="font_heading" name="font_heading" value="<?= e($settings['brand']['fonts']['heading']) ?>">
            </div>
            <div class="ad-field">
                <label class="ad-label" for="font_body">Police du texte</label>
                <input class="ad-input" id="font_body" name="font_body" value="<?= e($settings['brand']['fonts']['body']) ?>">
            </div>
        </div>
        <div class="ad-field">
            <label class="ad-label" for="font_google">Feuille de style Google Fonts</label>
            <input class="ad-input" id="font_google" name="font_google" value="<?= e($settings['brand']['fonts']['google']) ?>">
            <span class="ad-hint">Doit commencer par <code>https://fonts.googleapis.com/</code>.</span>
        </div>
        <div class="ad-cols">
            <div class="ad-field">
                <label class="ad-label" for="radius">Arrondi des cartes (px)</label>
                <input class="ad-input" id="radius" name="radius" type="number" min="0" max="40"
                       value="<?= (int) $settings['brand']['radius'] ?>">
            </div>
            <div class="ad-field">
                <label class="ad-switch">
                    <input type="hidden" name="motion" value="0">
                    <input type="checkbox" name="motion" value="1"<?= !empty($settings['brand']['motion']) ? ' checked' : '' ?>>
                    <span class="ad-switch__track"></span><span>Animations activées</span>
                </label>
                <span class="ad-hint">Les visiteurs ayant demandé moins d’animations en sont exemptés d’office.</span>
            </div>
        </div>
    </section>

    <section class="ad-panel">
        <div class="ad-panel__head"><h2 class="ad-panel__title">Logo &amp; favicon</h2></div>
        <div class="ad-cols">
            <div class="ad-field">
                <label class="ad-label" for="logo">Logo (URL)</label>
                <input class="ad-input" id="logo" name="logo" value="<?= e($settings['site']['logo']) ?>">
            </div>
            <div class="ad-field">
                <label class="ad-label" for="favicon">Favicon (URL)</label>
                <input class="ad-input" id="favicon" name="favicon" value="<?= e($settings['site']['favicon']) ?>">
            </div>
        </div>
        <p class="ad-hint">Téléversez d’abord le fichier dans <a href="/admin/medias">Médias</a>, puis collez son adresse ici.</p>
    </section>

    <button type="submit" class="ad-btn ad-btn--primary">Enregistrer la charte</button>
</form>

<?= View::render('admin/partials/shell-close') ?>
