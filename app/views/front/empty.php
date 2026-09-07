<?php /** État initial : aucune page n'existe encore. */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Site en préparation</title>
    <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
</head>
<body class="page">
<main class="main">
    <section class="section section--dark section--loose">
        <div class="shell shell--narrow text-center">
            <h1 class="section__title section__title--light">Le site est prêt à être rempli</h1>
            <p class="section__text section__text--light">
                Connectez-vous au back-office pour créer votre première page.
            </p>
            <a class="btn btn--accent btn--slide" href="/admin"><span class="btn__label">Accéder au back-office</span></a>
        </div>
    </section>
</main>
</body>
</html>
