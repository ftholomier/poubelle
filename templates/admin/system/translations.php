<?php
/**
 * Traductions anglaises. Variables : $tab, $gemini, $auto ; interface : $rows, $total, $page, $pages, $q, $filter, $count, $empty ;
 * fiches : $counts, $list, $state, $fails
 */
use App\Core\Auth;
use App\Data\Fiches;

$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$stLabel = ['none' => ['Non traduite', 'ko'], 'auto' => ['Traduite (Gemini)', 'info'], 'manual' => ['Relue ✓', 'ok'], 'stale' => ['À revoir', 'warn']];
?>
<?php if (!$gemini): ?><p class="alert" style="margin:0">La traduction automatique nécessite une clé Gemini<?= Auth::isAdmin() ? ' (<a href="/admin/reglages?groupe=ai">Réglages › Assistant IA</a>)' : '' ?>. Les traductions saisies à la main fonctionnent sans clé.</p><?php endif; ?>

<?php if ($tab === 'interface'): ?>
  <div class="toolbar">
    <p class="small muted grow" style="margin:0">Libellés de l’interface du site (menus, boutons, titres…). <?= $fmt($count) ?> libellés, dont <b><?= $fmt($empty) ?></b> sans traduction. Les libellés manquants sont relevés automatiquement quand une page anglaise les affiche.</p>
    <?php if ($gemini && $empty): ?><form method="post" action="/admin/traductions"><?= csrf_field() ?><button type="submit" name="action" value="gemini-interface" class="btn btn--yellow">Traduire les <?= $fmt(min(150, $empty)) ?> manquants avec Gemini</button></form><?php endif; ?>
  </div>
  <form class="toolbar" method="get" action="/admin/traductions">
    <div class="search"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher en français ou en anglais…" aria-label="Rechercher"><button type="submit">→</button></div>
    <select name="filtre" aria-label="Filtre" onchange="this.form.submit()"><option value="">Tous les libellés</option><option value="manquants"<?= $filter === 'manquants' ? ' selected' : '' ?>>Sans traduction</option></select>
    <span class="small muted"><?= $fmt($total) ?> résultat<?= $total > 1 ? 's' : '' ?></span>
  </form>
  <form class="stack" data-json-form data-url="/admin/traductions" novalidate>
    <input type="hidden" name="action" value="enregistrer">
    <div class="table grid-edit">
      <table>
        <thead><tr><th style="width:50%">Français</th><th>Anglais</th></tr></thead>
        <tbody data-repeater="rows">
        <?php foreach ($rows as $r): ?>
          <tr data-item>
            <td class="small" style="white-space:pre-wrap"><input type="hidden" data-field="fr" value="<?= e($r['fr']) ?>"><?= e($r['fr']) ?></td>
            <td><input type="text" data-field="en" value="<?= e($r['en']) ?>" aria-label="Traduction de « <?= e(mb_substr($r['fr'], 0, 60)) ?> »"<?= $r['en'] === '' ? ' style="background:var(--butter)"' : '' ?>></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="2" class="muted" style="padding:24px;text-align:center">Aucun libellé.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="row"><button type="submit" class="btn btn--navy" data-save>Enregistrer les traductions</button><span class="small muted" data-saved></span></div>
  </form>
  <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Pagination"><?php for ($i = max(1, $page - 4); $i <= min($pages, $page + 4); $i++): ?><a class="<?= $i === $page ? 'is-on' : '' ?>" href="/admin/traductions?<?= e(http_build_query(array_filter(['q' => $q, 'filtre' => $filter, 'page' => $i]))) ?>"><?= $i ?></a><?php endfor; ?><span class="xs muted" style="border:0;background:none">page <?= $page ?> / <?= $pages ?></span></nav>
  <?php endif; ?>
<?php else: ?>
  <?php $sum = max(1, array_sum($counts)); ?>
  <div class="kpis">
    <?php foreach ($stLabel as $k => [$l, $c]): ?>
      <a class="kpi<?= $state === $k ? ' is-on' : '' ?>" href="/admin/traductions?onglet=fiches&amp;etat=<?= $k ?>"><b><?= $fmt($counts[$k]) ?></b><span><?= e($l) ?></span><small><?= round(100 * $counts[$k] / $sum) ?> % des fiches publiées</small></a>
    <?php endforeach; ?>
  </div>
  <div class="bar bar--navy" style="height:14px"><i style="width:<?= round(100 * ($counts['auto'] + $counts['manual']) / $sum) ?>%"></i></div>
  <div class="toolbar">
    <p class="small muted grow" style="margin:0">Le français fait foi. <?= $auto ? 'La tâche planifiée traduit automatiquement les nouvelles fiches publiées (priorité : « À la une », légendes, fiches récentes).' : 'La traduction automatique est désactivée (Réglages › Traduction).' ?> Une fiche relue garde sa traduction même si le français change (elle passe « À revoir »). Les fiches non traduites restent visibles en anglais, en français, sans être proposées à Google.<?= $fails ? ' ' . (int) $fails . ' fiche(s) en échec seront réessayées plus tard.' : '' ?></p>
    <?php if ($gemini): ?><form method="post" action="/admin/traductions"><?= csrf_field() ?><input type="hidden" name="n" value="10"><button type="submit" name="action" value="gemini-fiches" class="btn btn--yellow">Traduire 10 fiches maintenant</button></form><?php endif; ?>
  </div>
  <div class="table">
    <table>
      <thead><tr><th>Fiche</th><th>Type</th><th>Traduction</th><th>Modifiée</th></tr></thead>
      <tbody>
      <?php foreach ($list as $r): [$l, $c] = $stLabel[$r['st']]; ?>
        <tr data-href="/admin/fiche/<?= (int) $r['id'] ?>#en">
          <td><a class="rowlink" href="/admin/fiche/<?= (int) $r['id'] ?>#en"><?= e($r['title']) ?></a></td>
          <td class="small"><?= e(Fiches::TYPES[$r['type']] ?? $r['type']) ?></td>
          <td><span class="pill pill--<?= $c ?>"><?= e($l) ?></span></td>
          <td class="xs muted"><?= e(\App\Admin\Base::ago($r['modified'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="4" class="muted" style="padding:24px;text-align:center">Aucune fiche.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
