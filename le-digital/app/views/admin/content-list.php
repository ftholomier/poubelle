<?php /** @var array $pages */ ?>
<p class="intro">Tous les textes, listes, boutons et images du site sont modifiables ici, page par page. Les modifications sont en ligne dès l’enregistrement.</p>
<div class="tiles">
  <?php foreach ($pages as $p): ?>
    <a class="tile" href="/admin/contenus/<?= e($p['key']) ?>">
      <b><?= e($p['label']) ?></b>
      <small><?= $p['updated'] ? 'Modifié le ' . e(date('d/m/Y à H:i', $p['updated'])) : 'Textes de la maquette' ?></small>
      <span class="tile__go">Modifier →</span>
    </a>
  <?php endforeach; ?>
  <a class="tile tile--alt" href="/admin/avis"><b>Avis clients</b><small>Témoignages affichés sur l’accueil, la page Avis et la formation</small><span class="tile__go">Gérer →</span></a>
  <a class="tile tile--alt" href="/admin/blog"><b>Articles du blog</b><small>Rédaction, publication, thèmes</small><span class="tile__go">Gérer →</span></a>
</div>
