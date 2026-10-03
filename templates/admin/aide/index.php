<?php
/** Aide : accueil du guide. Variables : $chapters, $q, $results, $faq */
use App\Admin\Help;

$num = 0;
$start = array_values(array_filter([$chapters['bienvenue'] ?? null, $chapters['prise-en-main'] ?? null, $chapters['matchs'] ?? null]));
?>
<section class="aide-hero">
  <div class="aide-hero__txt">
    <span class="aide-kicker">Guide d’utilisation du back-office</span>
    <h2>Tout ce qu’il faut savoir pour faire vivre le musée</h2>
    <p>Ce guide explique, écran par écran, comment saisir et publier les contenus du musée en ligne : matchs, joueurs, articles, photos, pages d’accueil… Il s’adresse à celles et ceux qui alimentaient l’ancien site WordPress : chaque chapitre indique ce qui change et ce que le site fait désormais tout seul.</p>
    <form class="aide-search" method="get" action="/admin/aide" role="search">
      <label class="sr-only" for="aide-q">Rechercher dans l’aide</label>
      <input id="aide-q" type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher : composition, crédit photo, publier plus tard…">
      <button class="btn btn--navy" type="submit">Rechercher</button>
    </form>
  </div>
  <div class="aide-hero__files">
    <a class="aide-file" href="/admin/aide/fichier/guide-sochaux-retro.pdf" target="_blank"><b>Guide complet</b><span>PDF illustré, à lire ou imprimer</span></a>
    <a class="aide-file" href="/admin/aide/fichier/memo-sochaux-retro.pdf" target="_blank"><b>Mémo</b><span>PDF de 2 pages à garder sous la main</span></a>
    <a class="aide-file aide-file--light" href="/admin/aide/imprimer" target="_blank"><b>Version imprimable</b><span>toujours à jour, depuis le navigateur</span></a>
  </div>
</section>

<?php if ($q !== ''): ?>
  <section class="card">
    <div class="card__head"><h2 class="card__t">Résultats pour « <?= e($q) ?> »</h2><a class="btn btn--sm" href="/admin/aide">Effacer</a></div>
    <?php foreach ($results as $r): ?>
      <a class="aide-result" href="/admin/aide/<?= e($r['chapter']['slug']) ?>#<?= e($r['section']['id']) ?>">
        <span class="aide-result__where"><?= e($r['chapter']['title']) ?></span>
        <b><?= e($r['section']['title']) ?></b>
        <span><?= e($r['snippet']) ?></span>
      </a>
    <?php endforeach; ?>
    <?php if (!$results): ?><div class="empty" style="border:0">Aucun passage ne correspond. Essayez un autre mot (par exemple « photo », « joueur », « publier »).</div><?php endif; ?>
  </section>
<?php endif; ?>

<section>
  <h2 class="aide-h2">Par où commencer ?</h2>
  <div class="aide-start">
    <?php foreach ($start as $i => $c): ?>
      <a class="aide-step" href="/admin/aide/<?= e($c['slug']) ?>"><em><?= $i + 1 ?></em><b><?= e($c['title']) ?></b><span><?= e($c['summary']) ?></span></a>
    <?php endforeach; ?>
  </div>
</section>

<section>
  <h2 class="aide-h2">Tous les chapitres</h2>
  <div class="aide-grid">
    <?php foreach ($chapters as $c): $num++; ?>
      <a class="aide-card" href="/admin/aide/<?= e($c['slug']) ?>">
        <span class="aide-card__n"><?= sprintf('%02d', $num) ?></span>
        <b><?= e($c['title']) ?></b>
        <span><?= e($c['summary']) ?></span>
        <small><?= count($c['sections']) ?> partie<?= count($c['sections']) > 1 ? 's' : '' ?></small>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($faq): ?>
<section class="card">
  <div class="card__head"><h2 class="card__t">Comment faire pour… ?</h2><a class="btn btn--sm" href="/admin/aide/faq">Toutes les réponses</a></div>
  <div class="aide-faq">
    <?php foreach ($faq['sections'] as $s): ?><a href="/admin/aide/faq#<?= e($s['id']) ?>"><?= e($s['title']) ?></a><?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
