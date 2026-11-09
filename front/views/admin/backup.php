<div class="cols">
  <div class="card">
    <h2>Sauvegarde du contenu</h2>
    <p class="bo-muted">Télécharge en un fichier JSON tout le contenu modifiable : pages, produits, actus, avis, réalisations, menu, réglages, demandes (sans les comptes ni les clés secrètes).</p>
    <a class="b b--y" href="/admin/sauvegarde.json">Télécharger la sauvegarde</a>
    <h2 style="margin-top:26px">Restaurer</h2>
    <form class="bo-form" data-api="backup.restore" data-confirm="Remplacer le contenu actuel par celui de la sauvegarde ?">
      <div class="f"><input type="file" name="file" accept="application/json,.json" required></div>
      <button class="b b--l" type="submit">Restaurer ce fichier</button>
    </form>
  </div>
  <div class="card">
    <h2>Contenu d'origine</h2>
    <p class="bo-muted">Remet une rubrique dans l'état du site renovest.fr d'origine (les modifications faites ici sont perdues).</p>
    <div class="quick">
      <?php foreach (['pages' => 'Pages', 'products' => 'Produits', 'posts' => 'Actualités', 'gallery' => 'Photos produits', 'realisations' => 'Réalisations', 'reviews' => 'Avis', 'menu' => 'Menu', 'site' => 'Coordonnées', 'ui' => 'Conversion'] as $k => $l): ?>
        <button class="b b--l" type="button" data-action="content.reset" data-name="<?= e($k) ?>" data-confirm="Remettre « <?= e($l) ?> » à l'origine ?"><?= e($l) ?></button>
      <?php endforeach; ?>
    </div>
    <h2 style="margin-top:26px">Tâche planifiée</h2>
    <p class="bo-muted">À programmer toutes les heures chez l'hébergeur (tâche « cron » ou appel d'URL) : synchronisation Facebook et vérification des mises à jour.</p>
    <pre class="pre"><?= e(Http::abs('/api/cron') . '?key=' . Api::cronKey()) ?></pre>
  </div>
</div>
