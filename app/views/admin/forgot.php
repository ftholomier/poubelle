<?php /** @var array $settings @var bool $sent @var ?string $error */
use App\Security\Csrf; $title = 'Mot de passe oublié'; ?>
<!DOCTYPE html>
<html lang="fr"><head><?= App\Core\View::render('admin/partials/head', compact('settings', 'title')) ?></head>
<body class="ad-auth">
    <main class="ad-auth__card">
        <span class="ad-auth__brand"><?= icon('shield', '', 26) ?></span>
        <h1 class="ad-auth__title">Mot de passe oublié</h1>
        <p class="ad-auth__text">Indiquez votre adresse e-mail : vous recevrez un lien de réinitialisation valable une heure.</p>

        <?php if ($error): ?>
            <div class="ad-flash ad-flash--error" role="alert"><?= icon('shield', 'ad-flash__icon', 18) ?><span><?= e($error) ?></span></div>
        <?php endif; ?>

        <?php if ($sent): ?>
            <div class="ad-flash ad-flash--success" role="status">
                <?= icon('check', 'ad-flash__icon', 18) ?>
                <span>Si un compte correspond à cette adresse, un e-mail vient d’être envoyé.</span>
            </div>
        <?php else: ?>
            <form method="post" action="/admin/mot-de-passe-oublie">
                <input type="hidden" name="_token" value="<?= e(Csrf::token('forgot')) ?>">
                <p class="ad-field">
                    <label class="ad-label" for="email">Adresse e-mail</label>
                    <input class="ad-input" id="email" name="email" type="email" required autocomplete="username" autofocus>
                </p>
                <button type="submit" class="ad-btn ad-btn--primary" style="width:100%">Envoyer le lien</button>
            </form>
        <?php endif; ?>

        <p class="ad-auth__foot"><a href="/admin/login">Retour à la connexion</a></p>
    </main>
</body></html>
