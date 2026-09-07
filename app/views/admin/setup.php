<?php /** @var array $settings @var ?string $error */
use App\Security\Csrf; $title = 'Installation'; ?>
<!DOCTYPE html>
<html lang="fr"><head><?= App\Core\View::render('admin/partials/head', compact('settings', 'title')) ?></head>
<body class="ad-auth">
    <main class="ad-auth__card">
        <span class="ad-auth__brand"><?= icon('spark', '', 26) ?></span>
        <h1 class="ad-auth__title">Créer le compte administrateur</h1>
        <p class="ad-auth__text">
            Premier démarrage : créez le compte qui pilotera le site. Le contenu de départ
            sera installé automatiquement.
        </p>

        <?php if ($error): ?>
            <div class="ad-flash ad-flash--error" role="alert"><?= icon('shield', 'ad-flash__icon', 18) ?><span><?= e($error) ?></span></div>
        <?php endif; ?>

        <form method="post" action="/admin/installation">
            <input type="hidden" name="_token" value="<?= e(Csrf::token('setup')) ?>">
            <p class="ad-field">
                <label class="ad-label" for="name">Votre nom</label>
                <input class="ad-input" id="name" name="name" type="text" required autofocus value="Romain Lemaire">
            </p>
            <p class="ad-field">
                <label class="ad-label" for="email">Adresse e-mail</label>
                <input class="ad-input" id="email" name="email" type="email" required autocomplete="username">
            </p>
            <p class="ad-field">
                <label class="ad-label" for="password">Mot de passe</label>
                <input class="ad-input" id="password" name="password" type="password" required minlength="10" autocomplete="new-password">
                <span class="ad-hint">10 caractères minimum, lettres et chiffres.</span>
            </p>
            <p class="ad-field">
                <label class="ad-label" for="confirm">Confirmation</label>
                <input class="ad-input" id="confirm" name="password_confirm" type="password" required minlength="10" autocomplete="new-password">
            </p>
            <button type="submit" class="ad-btn ad-btn--accent" style="width:100%">Créer le compte et installer</button>
        </form>
    </main>
</body></html>
