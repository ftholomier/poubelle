<?php /** @var int $status @var string $message */ ?>
<section class="error-page">
  <div class="code"><?= (int) $status ?></div>
  <h1 class="h2 mt-3"><?= $status === 404 ? 'Oups, cette page a quitté la fête' : e(App\Core\App::label($status)) ?></h1>
  <p class="lead" style="margin-left:auto;margin-right:auto"><?= $message !== '' ? e($message) : ($status === 404 ? 'Elle a peut-être changé d\'adresse. Cherchez un pro ou revenez à l\'accueil.' : 'Une erreur est survenue. Réessayez dans un instant.') ?></p>
  <form class="search-box" action="/recherche/" method="get" style="margin:32px auto 0">
    <label class="search-field"><span>Que cherchez-vous ?</span><input type="text" name="q" placeholder="DJ, magicien, karaoké…"></label>
    <button class="btn-go" type="submit">Chercher</button>
  </form>
  <p class="mt-3"><a class="btn btn-ink" href="/">Retour à l'accueil</a></p>
</section>
