<?php
/**
 * Liste de fiches. Variables : $slug, $conf, $rows, $total, $counts, $status, $q, $page, $pages, $sort,
 * $quality, $totals, $geo, $seasons, $comps, $query
 */
use App\Admin\Base;
use App\Admin\FicheForm;
use App\Data\Fiches;
use App\Data\Categories;

$qs = function (array $over) use ($query): string {
    $p = array_filter(array_merge($query, $over), fn ($v) => $v !== null && $v !== '');
    unset($p['page']);
    if (isset($over['page'])) {
        $p['page'] = $over['page'];
    }
    return $p ? '?' . http_build_query($p) : '';
};
$base = '/admin/' . $slug;
$chips = ['' => ['Tous', $counts['tous'] ?? 0], 'publie' => ['Publiés', $counts['publie'] ?? 0], 'brouillon' => ['Brouillons', $counts['brouillon'] ?? 0], 'relire' => ['À relire', $counts['relire'] ?? 0], 'planifie' => ['Planifiés', $counts['planifie'] ?? 0]];
$roleLabel = ['joueur' => 'Joueur', 'entraineur' => 'Entraîneur', 'dirigeant' => 'Dirigeant', 'personnage' => 'Personnage'];
?>
<div class="toolbar">
  <div class="chips">
    <?php foreach ($chips as $k => [$l, $n]): ?><a class="chip<?= $status === $k ? ' is-on' : '' ?>" href="<?= e($base . $qs(['statut' => $k ?: null])) ?>"><?= e($l) ?> <em>· <?= (int) $n ?></em></a><?php endforeach; ?>
  </div>
  <span class="grow"></span>
  <a class="btn btn--yellow" href="/admin/fiche/nouvelle/<?= e($conf['new']) ?>">+ Nouvelle fiche</a>
  <?php if ($slug === 'articles'): ?><a class="btn" href="/admin/fiche/nouvelle/page">+ Nouvelle page</a><?php endif; ?>
</div>

<form class="toolbar" method="get" action="<?= e($base) ?>">
  <?php if ($status !== ''): ?><input type="hidden" name="statut" value="<?= e($status) ?>"><?php endif; ?>
  <div class="search"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher dans <?= e(mb_strtolower($conf['title'])) ?>…" aria-label="Rechercher"><button type="submit" aria-label="Rechercher">→</button></div>
  <?php if ($slug === 'matchs'): ?>
    <select name="saison" aria-label="Saison" data-autosubmit><option value="">Toutes les saisons</option><?php foreach ($seasons as $s): ?><option<?= ($query['saison'] ?? '') === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select>
    <select name="comp" aria-label="Compétition" data-autosubmit><option value="">Toutes compétitions</option><?php foreach ($comps as $c): ?><option<?= ($query['comp'] ?? '') === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
  <?php elseif ($slug === 'personnes'): ?>
    <select name="role" aria-label="Rubrique" data-autosubmit><option value="">Toutes les rubriques</option><?php foreach (FicheForm::ROLES as $k => $l): ?><option value="<?= e($k) ?>"<?= ($query['role'] ?? '') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <select name="filtre" aria-label="Filtre" data-autosubmit><option value="">Toutes</option><option value="sans-naissance"<?= ($query['filtre'] ?? '') === 'sans-naissance' ? ' selected' : '' ?>>Sans lieu de naissance</option><option value="album"<?= ($query['filtre'] ?? '') === 'album' ? ' selected' : '' ?>>Dans l’album</option><option value="legendes"<?= ($query['filtre'] ?? '') === 'legendes' ? ' selected' : '' ?>>Légendes</option></select>
  <?php elseif ($slug === 'articles'): $cats = array_filter(\App\Data\Categories::all(), fn ($c) => empty($c['technical']) && empty($c['season'])); uasort($cats, fn ($a, $b) => strcmp(\App\Data\Categories::label($a['slug']), \App\Data\Categories::label($b['slug']))); ?>
    <select name="cat" aria-label="Rubrique" data-autosubmit><option value="">Toutes les rubriques</option><?php foreach ($cats as $c): ?><option value="<?= e($c['slug']) ?>"<?= ($query['cat'] ?? '') === $c['slug'] ? ' selected' : '' ?>><?= e(\App\Data\Categories::label($c['slug'])) ?></option><?php endforeach; ?></select>
  <?php endif; ?>
  <select name="tri" aria-label="Tri" data-autosubmit>
    <?php foreach (($slug === 'matchs' ? ['date' => 'Date (récent)', 'date-asc' => 'Date (ancien)', 'modifie' => 'Dernière modification', 'titre' => 'Titre'] : ($slug === 'personnes' ? ['nom' => 'Nom', 'matchs' => 'Nombre de matchs', 'modifie' => 'Dernière modification'] : ['modifie' => 'Dernière modification', 'titre' => 'Titre', 'date' => 'Date de publication'])) as $k => $l): ?>
      <option value="<?= e($k) ?>"<?= $sort === $k ? ' selected' : '' ?>><?= e($l) ?></option>
    <?php endforeach; ?>
  </select>
  <span class="small muted"><?= number_format($total, 0, ',', ' ') ?> fiche<?= $total > 1 ? 's' : '' ?><?= $q !== '' ? ' pour « ' . e($q) . ' »' : '' ?></span>
</form>

<form method="post" action="/admin/fiches/lot" data-bulk-form data-confirm="Appliquer l’action aux fiches sélectionnées ?||Appliquer">
  <?= csrf_field() ?>
  <input type="hidden" name="back" value="<?= e($base . $qs([])) ?>">
  <div class="bulk" data-bulk-bar hidden>
    <span data-bulk-count></span>
    <button type="submit" name="action" value="publie">Publier</button>
    <button type="submit" name="action" value="relire">À relire</button>
    <button type="submit" name="action" value="brouillon">Brouillon</button>
    <button type="submit" name="action" value="traduire">Traduire (EN)</button>
    <button type="submit" name="action" value="corbeille">Corbeille</button>
  </div>
  <div class="table">
    <table>
      <thead><tr>
        <th style="width:44px"><input type="checkbox" class="check" data-check-all aria-label="Tout sélectionner"></th>
        <?php if ($slug === 'matchs'): ?><th>Date</th><th>Match</th><th>Compétition</th><th>Score</th>
        <?php elseif ($slug === 'personnes'): ?><th></th><th>Nom</th><th>Rubrique</th><th>Poste</th><th>Naissance</th><th>Matchs</th><th>Album</th>
        <?php else: ?><th></th><th>Titre</th><th>Rubrique</th><th>Type</th>
        <?php endif; ?>
        <th>Statut</th><th>Qualité</th><th>EN</th><th>Modifié</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $id => $s): ?>
        <?php $al = $quality[(int) $s['id']] ?? []; $hi = count(array_filter($al, fn ($a) => $a['sev'] === 'haute')); ?>
        <tr data-href="/admin/fiche/<?= (int) $s['id'] ?>">
          <td><input type="checkbox" class="check" name="ids[]" value="<?= (int) $s['id'] ?>" aria-label="Sélectionner"></td>
          <?php if ($slug === 'matchs'): $m = $s['m']; ?>
            <td class="t-num"><?= e(date_num($m['date'])) ?></td>
            <td><a class="rowlink" href="/admin/fiche/<?= (int) $s['id'] ?>"><?= e($m['home'] && $m['away'] ? $m['home'] . ' – ' . $m['away'] : $s['title']) ?></a><?= $m['event'] ? ' <span class="xs muted">' . e($m['event']) . '</span>' : '' ?></td>
            <?php $lbl = (string) ($m['label'] ?: $m['competition']); $rd = trim((string) ($m['round'] ?? '')); ?>
            <td class="ellipsis"><?= e(trim($lbl . (mb_strtolower($rd) !== mb_strtolower($lbl) ? ' ' . $rd : ''))) ?></td>
            <td class="t-num"><?= is_array($m['sh_score']) ? (int) $m['sh_score'][0] . '-' . (int) $m['sh_score'][1] : '—' ?></td>
          <?php elseif ($slug === 'personnes'): $p = $s['p']; ?>
            <td><?php if ($s['image']): ?><img class="thumb" src="<?= e(img($s['image'], 160)) ?>" alt="" loading="lazy"><?php endif; ?></td>
            <td><a class="rowlink" href="/admin/fiche/<?= (int) $s['id'] ?>"><?= e($p['name']) ?></a><?= $p['legend'] ? ' <span class="pill pill--yellow">Légende</span>' : '' ?></td>
            <td><?= e(implode(', ', array_map(fn ($r) => $roleLabel[$r] ?? $r, $p['roles']))) ?></td>
            <td class="ellipsis" style="max-width:180px"><?= e((string) ($p['position'] ?? '')) ?></td>
            <td><?= $p['birth_place'] ? e($p['birth_place']) : '<span class="warn">⚠ inconnu</span>' ?></td>
            <td class="t-num"><?= (int) ($totals[(int) $s['id']]['matches'] ?? 0) ?></td>
            <td><?= !empty($p['album']['in']) ? '<span class="pill pill--yellow">N° ' . (int) ($p['album']['number'] ?? 0) . '</span>' : '' ?></td>
          <?php else: ?>
            <td><?php if ($s['image']): ?><img class="thumb" src="<?= e(img($s['image'], 160)) ?>" alt="" loading="lazy"><?php endif; ?></td>
            <td class="ellipsis"><a class="rowlink" href="/admin/fiche/<?= (int) $s['id'] ?>"><?= e($s['title']) ?></a></td>
            <td class="ellipsis" style="max-width:220px"><?= e(($c = Categories::primaryOf($s['categories'])) ? Categories::label($c) : '—') ?></td>
            <td><?= e(Fiches::TYPES[$s['type']] ?? $s['type']) ?></td>
          <?php endif; ?>
          <td><span class="pill pill--<?= e($s['status']) ?>"><?= e(Fiches::STATUSES[$s['status']] ?? $s['status']) ?></span><?= $s['status'] === 'planifie' && $s['publish_at'] ? '<br><span class="xs muted">' . e(date('d/m H:i', strtotime($s['publish_at']))) . '</span>' : '' ?><?php if ($lk = $locks[(int) $s['id']] ?? null): ?><br><span class="lockpill<?= $lk['uid'] === $me ? ' lockpill--me' : '' ?>" title="<?= e($lk['uid'] === $me ? 'Vous avez cette fiche ouverte' : $lk['name'] . ' modifie cette fiche en ce moment') ?>">✎ <?= e($lk['uid'] === $me ? 'vous' : explode(' ', $lk['name'])[0]) ?></span><?php endif; ?></td>
          <td><?= $al ? '<span class="' . ($hi ? 'ko' : 'warn') . '" title="' . e(implode("\n", array_column($al, 'msg'))) . '">⚠ ' . count($al) . '</span>' : '<span class="ok">✓</span>' ?></td>
          <td><?= $s['has_en'] ? '<span class="ok">EN</span>' : '<span class="muted">—</span>' ?></td>
          <td class="xs muted nowrap"><?= e(Base::ago($s['modified'] ?? null)) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="12" style="padding:28px;text-align:center" class="muted">Aucune fiche ne correspond.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</form>
<?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Pagination">
    <?php if ($page > 1): ?><a href="<?= e($base . $qs(['page' => $page - 1])) ?>">←</a><?php endif; ?>
    <?php for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++): ?><a class="<?= $i === $page ? 'is-on' : '' ?>" href="<?= e($base . $qs(['page' => $i])) ?>"><?= $i ?></a><?php endfor; ?>
    <?php if ($page < $pages): ?><a href="<?= e($base . $qs(['page' => $page + 1])) ?>">→</a><?php endif; ?>
    <span class="xs muted" style="border:0;background:none">page <?= $page ?> / <?= $pages ?></span>
  </nav>
<?php endif; ?>
<p class="xs muted" style="margin:0"><a class="linkbtn" href="/admin/corbeille">Voir la corbeille</a></p>
