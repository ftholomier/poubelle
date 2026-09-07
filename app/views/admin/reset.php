<?php /** @var array $settings @var string $token @var ?string $error @var bool $done */
use App\Security\Csrf; $title = 'Nouveau mot de passe'; ?>
<!DOCTYPE html>
<html lang="fr"><head><?= App\Core\View::render('admin/partials/head', compact('settings', 'title')) ?></head>
<body class="ad-auth">
    <main class="ad-auth__card">
        <span class="ad-auth__brand"><?= icon('shield', '', 26) ?></span>
        <h1 class="ad-auth__title">Nouveau mot de passe</h1>

        <?php if ($done): ?>
            <div class="ad-flash ad-flash--success" role="status">
                <?= icon('check', 'ad-flash__icon', 18) ?><span>Mot de passe modifié. Vous pouvez vous connecter.</span>
            </div>
            <p class="ad-auth__foot"><a class="ad-btn ad-btn--primary" href="/admin/login">Se connecter</a></p>
        <?php else: ?>
            <p class="ad-auth__text">Au moins 10 caractères, mêlant lettres et chiffres.</p>
            <?php if ($error): ?>
                <div class="ad-flash ad-flash--error" role="alert"><?= icon('shield', 'ad-flash__icon', 18) ?><span><?= e($error) ?></span></div>
            <?php endif; ?>
            <form method="post" action="/admin/reinitialisation">
                <input type="hidden" name="_token" value="<?= e(Csrf::token('reset')) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <p class="ad-field">
                    <label class="ad-label" for="password">Nouveau mot de passe</label>
                    <input class="ad-input" id="password" name="password" type="password" required
                           minlength="10" autocomplete="new-password" autofocus>
                </p>
                <p class="ad-field">
                    <label class="ad-label" for="confirm">Confirmation</label>
                    <input class="ad-input" id="confirm" name="password_confirm" type="password" required
                           minlength="10" autocomplete="new-password">
                </p>
                <button type="submit" class="ad-btn ad-btn--primary" style="width:100%">Enregistrer</button>
            </form>
        <?php endif; ?>
    </main>
</body></html>
