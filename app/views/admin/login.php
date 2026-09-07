<?php /** @var array $settings @var ?string $error @var string $email */
use App\Security\Csrf; $title = 'Connexion'; ?>
<!DOCTYPE html>
<html lang="fr"><head><?= App\Core\View::render('admin/partials/head', compact('settings', 'title')) ?></head>
<body class="ad-auth">
    <main class="ad-auth__card">
        <span class="ad-auth__brand"><?= icon('glasses', '', 26) ?></span>
        <h1 class="ad-auth__title">Back-office</h1>
        <p class="ad-auth__text"><?= e($settings['site']['name'] ?? '') ?></p>

        <?php if ($error): ?>
            <div class="ad-flash ad-flash--error" role="alert"><?= icon('shield', 'ad-flash__icon', 18) ?><span><?= e($error) ?></span></div>
        <?php endif; ?>

        <form method="post" action="/admin/login" autocomplete="on">
            <input type="hidden" name="_token" value="<?= e(Csrf::token('login')) ?>">
            <p class="ad-field">
                <label class="ad-label" for="email">Adresse e-mail</label>
                <input class="ad-input" id="email" name="email" type="email" required autocomplete="username"
                       value="<?= e($email) ?>" autofocus>
            </p>
            <p class="ad-field">
                <label class="ad-label" for="password">Mot de passe</label>
                <input class="ad-input" id="password" name="password" type="password" required autocomplete="current-password">
            </p>
            <button type="submit" class="ad-btn ad-btn--primary" style="width:100%">Se connecter</button>
        </form>

        <p class="ad-auth__foot"><a href="/admin/mot-de-passe-oublie">Mot de passe oublié ?</a></p>
    </main>
    <script src="<?= e(asset('/assets/js/admin.js')) ?>" defer></script>
</body></html>
