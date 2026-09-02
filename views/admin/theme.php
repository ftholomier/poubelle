<?php use App\View; ?>
<h1 class="admin__title">Couleur du site</h1>
<p class="admin__intro">
    Une seule couleur pilote l'ensemble : fond, textes, bordures, halos, dégradé
    des particules et poussière d'ambiance. L'aperçu se met à jour en direct.
</p>

<?php if ($saved): ?>
    <p class="notice notice--ok" role="status">Couleur enregistrée. Rechargez le site pour la voir.</p>
<?php endif; ?>
<?php if ($error !== null): ?>
    <p class="notice notice--error" role="alert"><?= View::e($error) ?></p>
<?php endif; ?>

<form method="post" action="/admin/theme" class="theme" id="theme-form">
    <input type="hidden" name="csrf" value="<?= View::e($csrf) ?>">

    <div class="theme__controls">
        <label class="field">
            <span class="field__label">Couleur dominante</span>
            <span class="theme__picker">
                <input
                    type="color"
                    id="dominant-color"
                    value="<?= View::e($site['theme']['dominant']) ?>"
                    aria-label="Sélecteur de couleur">
                <input
                    type="text"
                    name="dominant"
                    id="dominant-hex"
                    value="<?= View::e($site['theme']['dominant']) ?>"
                    pattern="#?[0-9a-fA-F]{6}"
                    spellcheck="false"
                    required>
            </span>
        </label>

        <label class="field">
            <span class="field__label">Harmonie</span>
            <select name="harmony" id="harmony">
                <?php foreach ($harmonies as $key => $label): ?>
                    <option
                        value="<?= View::e($key) ?>"
                        <?= $site['theme']['harmony'] === $key ? 'selected' : '' ?>>
                        <?= View::e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="field">
            <span class="field__label">Suggestions</span>
            <div class="theme__presets">
                <?php foreach (['#7b01f7', '#0089f7', '#00b894', '#ff6b00', '#e91e63', '#f5c400', '#ff2d55', '#8d8d8d'] as $preset): ?>
                    <button
                        type="button"
                        class="theme__preset"
                        data-color="<?= View::e($preset) ?>"
                        style="background: <?= View::e($preset) ?>"
                        aria-label="Choisir <?= View::e($preset) ?>"></button>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" class="button">Enregistrer</button>
    </div>

    <div class="theme__preview" id="theme-preview">
        <p class="field__label">Aperçu</p>
        <div class="preview">
            <div class="preview__swatches">
                <span data-role="accent"></span>
                <span data-role="accent2"></span>
                <span data-role="accent3"></span>
            </div>
            <div class="preview__scene">
                <p class="preview__eyebrow">Aperçu</p>
                <p class="preview__title">Accompagnement</p>
                <p class="preview__body">Le fond, les textes et les particules suivent la dominante.</p>
                <span class="preview__button">On collabore !</span>
            </div>
            <dl class="preview__values"></dl>
        </div>
    </div>
</form>

<script type="application/json" id="harmonies-data"><?= View::json(array_keys($harmonies)) ?></script>
<script type="module" src="<?= View::e(View::asset('assets/js/admin-theme.js')) ?>"></script>
