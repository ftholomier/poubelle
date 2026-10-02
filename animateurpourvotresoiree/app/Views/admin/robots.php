<?php
use App\Core\Url;
/** @var string $robots @var string $env */
?>
<div class="adm-head"><div><h1>robots.txt</h1><p>Laissez vide pour utiliser la version par défaut (recommandé). L'adresse du sitemap est toujours ajoutée.</p></div><a class="btn btn-sm" href="/robots.txt" target="_blank" rel="noopener">Voir le fichier actuel</a></div>
<?php if ($env !== 'production'): ?><div class="alert alert-warning mb-2">Le site n'est pas en production (APP_ENV=<?= e($env) ?>) : le robots.txt bloque tous les robots pour éviter l'indexation d'un site de test.</div><?php endif; ?>
<form class="box form" method="post" action="<?= e(Url::admin('seo/robots')) ?>"><?= csrf_field() ?>
  <div class="field"><label for="rb">Contenu personnalisé</label><textarea id="rb" name="robots" rows="14" class="code" placeholder="User-agent: *&#10;Disallow: /api/"><?= e($robots) ?></textarea></div>
  <div><button class="btn btn-coral btn-sm" type="submit">Enregistrer</button></div>
</form>
