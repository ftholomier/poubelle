<?php /** @var array $posts @var array $themes */ ?>
<?= LD\View::render('admin/blog-tabs', ['on' => 'posts']) ?>
<div class="toolbar">
  <p class="intro">Thèmes : <?= e(implode(' · ', $themes)) ?> <a href="/admin/contenus/blog">(modifier)</a></p>
  <span class="toolbar__btns"><a class="btn btn--ghost" href="/admin/blog/ia#idees">💡 Idées d’articles</a> <a class="btn btn--dark" href="/admin/blog/ia">✨ Écrire avec l’IA</a> <a class="btn btn--red" href="/admin/blog/nouveau">+ Nouvel article</a></span>
</div>
<?php if (!$posts): ?>
  <div class="card empty"><p>Aucun article. Tant qu’il n’y en a pas, la page Blog affiche les emplacements de la maquette.</p><a class="btn btn--red" href="/admin/blog/nouveau">Écrire le premier article</a></div>
<?php else: ?>
  <div class="card card--flush">
    <table class="table">
      <thead><tr><th>Article</th><th class="hide-sm">Thème</th><th>Date</th><th>Statut</th></tr></thead>
      <tbody>
        <?php foreach ($posts as $p): $live = !empty($p['published']) && substr($p['date'], 0, 10) <= date('Y-m-d'); ?>
          <tr data-href="/admin/blog/<?= e($p['id']) ?>">
            <td><a href="/admin/blog/<?= e($p['id']) ?>"><b><?= e($p['title']) ?></b></a><br><small class="muted">/blog/<?= e($p['slug']) ?> · <?= (int) $p['read'] ?> min</small></td>
            <td class="hide-sm"><small><?= e($p['theme']) ?></small></td>
            <td><small><?= e(date('d/m/Y', strtotime($p['date']))) ?></small></td>
            <td><span class="status status--<?= $live ? 'done' : (!empty($p['published']) ? 'progress' : 'archived') ?>"><?= $live ? 'En ligne' : (!empty($p['published']) ? 'Programmé' : 'Brouillon') ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
