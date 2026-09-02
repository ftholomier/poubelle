<?php use App\View; ?>
<div class="login">
    <h1 class="login__title">Back-office</h1>

    <?php if (!$configured): ?>
        <div class="notice notice--warn">
            <p><strong>Aucun mot de passe n'est défini.</strong></p>
            <p>
                Ouvrez un terminal à la racine du projet et lancez :
                <code>php tools/admin-password.php votre@adresse.fr</code>
            </p>
            <p class="notice__aside">
                Le compte ne peut pas être créé depuis cette page : sur un site
                fraîchement mis en ligne, le premier visiteur venu pourrait s'en emparer.
            </p>
        </div>
    <?php else: ?>
        <?php if ($error !== null): ?>
            <p class="notice notice--error" role="alert"><?= View::e($error) ?></p>
        <?php endif; ?>

        <form method="post" action="/admin/connexion" class="login__form">
            <input type="hidden" name="csrf" value="<?= View::e($csrf) ?>">

            <label class="field">
                <span class="field__label">Adresse électronique</span>
                <input
                    type="email"
                    name="email"
                    value="<?= View::e($email ?? '') ?>"
                    autocomplete="username"
                    spellcheck="false"
                    required
                    <?= ($email ?? '') === '' ? 'autofocus' : '' ?>>
            </label>

            <label class="field">
                <span class="field__label">Mot de passe</span>
                <input
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                    <?= ($email ?? '') !== '' ? 'autofocus' : '' ?>>
            </label>

            <button type="submit" class="button">Entrer</button>
        </form>
    <?php endif; ?>

    <p class="login__back"><a href="/">← Retour au site</a></p>
</div>
