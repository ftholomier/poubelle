<?php
/**
 * Référentiels. Variables : $tab, $q, $filter, $rows et selon l'onglet $total, $page, $pages, $missing, $all
 */
$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$pager = function () use ($tab, $q, $filter, &$page, &$pages): string {
    if (($pages ?? 1) <= 1) {
        return '';
    }
    $h = '<nav class="pager" aria-label="Pagination">';
    for ($i = max(1, $page - 4); $i <= min($pages, $page + 4); $i++) {
        $h .= '<a class="' . ($i === $page ? 'is-on' : '') . '" href="/admin/referentiels?' . e(http_build_query(array_filter(['onglet' => $tab, 'q' => $q, 'filtre' => $filter, 'page' => $i]))) . '">' . $i . '</a>';
    }
    return $h . '<span class="xs muted" style="border:0;background:none">page ' . $page . ' / ' . $pages . '</span></nav>';
};
$osm = fn ($lat, $lng) => $lat !== null && $lat !== '' ? 'https://www.openstreetmap.org/?mlat=' . rawurlencode((string) $lat) . '&mlon=' . rawurlencode((string) $lng) . '#map=15/' . rawurlencode((string) $lat) . '/' . rawurlencode((string) $lng) : null;
?>
<?php if (in_array($tab, ['adversaires', 'stades', 'lieux'], true)): ?>
<form class="toolbar" method="get" action="/admin/referentiels">
  <input type="hidden" name="onglet" value="<?= e($tab) ?>">
  <div class="search"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher…" aria-label="Rechercher"><button type="submit" aria-label="Rechercher">→</button></div>
  <select name="filtre" aria-label="Filtre" data-autosubmit>
    <option value="">Tout</option>
    <option value="sans-coordonnees"<?= $filter === 'sans-coordonnees' ? ' selected' : '' ?>>Sans coordonnées (absents de la carte)</option>
    <?php if ($tab !== 'lieux'): ?><option value="sans-ville"<?= $filter === 'sans-ville' ? ' selected' : '' ?>>Sans ville</option><?php endif; ?>
  </select>
  <span class="small muted"><?= $fmt($total) ?> ligne<?= $total > 1 ? 's' : '' ?><?= isset($missing) ? ' · ' . $fmt($missing) . ' sans coordonnées sur ' . $fmt($all) : '' ?></span>
</form>
<?php endif; ?>

<?php if ($tab === 'adversaires' || $tab === 'stades'): ?>
  <?php $club = $tab === 'adversaires'; ?>
  <form class="stack" data-json-form data-url="/admin/referentiels/enregistrer" novalidate>
    <input type="hidden" name="kind" value="<?= e($tab) ?>">
    <div class="alert alert--info small">
      <?= $club
        ? 'Les adversaires sont créés automatiquement à partir des fiches matchs. Pour <b>regrouper</b> deux écritures d’un même club (« FC Nantes » et « Nantes »), ajoutez l’une dans les <b>variantes</b> de l’autre (séparées par « ; ») : elles fusionnent à l’enregistrement.'
        : 'Les stades sont créés à partir des fiches matchs et géolocalisés automatiquement (OpenStreetMap). Corrigez ici les coordonnées d’un stade mal placé sur la carte (clic droit sur openstreetmap.org › « Afficher l’adresse » donne la latitude et la longitude). Pour regrouper deux écritures d’un même stade, ajoutez l’une dans les variantes de l’autre.' ?>
    </div>
    <div class="table grid-edit">
      <table>
        <thead><tr><th>Nom</th><th>Variantes (séparées par « ; »)</th><th>Ville</th><?php if (!$club): ?><th>Précision</th><?php endif; ?><th>Pays</th><?php if ($club): ?><th>Logo</th><?php endif; ?><th style="width:110px">Latitude</th><th style="width:110px">Longitude</th><th>Matchs</th><th></th></tr></thead>
        <tbody data-repeater="rows">
        <?php foreach ($rows as $r): ?>
          <tr data-item>
            <td><input type="hidden" data-field="id" value="<?= e($r['id']) ?>"><input type="text" data-field="name" value="<?= e($r['name']) ?>" aria-label="Nom" style="min-width:170px;font-weight:600"></td>
            <td><input type="text" data-field="aliases" value="<?= e(implode(' ; ', $r['aliases'] ?? [])) ?>" aria-label="Variantes" style="min-width:200px"></td>
            <td><input type="text" data-field="city" value="<?= e($r['city'] ?? '') ?>" aria-label="Ville" style="min-width:110px"></td>
            <?php if (!$club): ?><td><input type="text" data-field="department" value="<?= e($r['department'] ?? '') ?>" aria-label="Précision (département, région)" style="min-width:90px"></td><?php endif; ?>
            <td><input type="text" data-field="country" value="<?= e($r['country'] ?? '') ?>" aria-label="Pays" style="width:70px"></td>
            <?php if ($club): ?>
              <td><span class="imgmini" data-image-mini><input type="hidden" data-field="logo" value="<?= e((string) ($r['logo'] ?? '')) ?>"><button type="button" data-pick title="Choisir le logo"><?= !empty($r['logo']) ? '<img src="' . e(img($r['logo'], 160)) . '" alt="">' : '+' ?></button><button type="button" data-clear class="iconbtn" title="Retirer le logo" aria-label="Retirer le logo">✕</button></span></td>
            <?php endif; ?>
            <td><input type="text" inputmode="decimal" data-field="lat" value="<?= e((string) ($r['lat'] ?? '')) ?>" aria-label="Latitude" placeholder="lat."></td>
            <td><input type="text" inputmode="decimal" data-field="lng" value="<?= e((string) ($r['lng'] ?? '')) ?>" aria-label="Longitude" placeholder="long."></td>
            <td class="t-num"><?= (int) $r['count'] ?></td>
            <td class="nowrap">
              <?php if ($club && $r['count']): ?><a class="linkbtn" href="/face-a-face/<?= e(rawurlencode($r['id'])) ?>/" target="_blank" rel="noopener" title="Page face-à-face">Bilan ↗</a><?php endif; ?>
              <?php if ($u = $osm($r['lat'] ?? null, $r['lng'] ?? null)): ?> <a class="linkbtn" href="<?= e($u) ?>" target="_blank" rel="noopener" title="Voir sur OpenStreetMap">Carte ↗</a><?php else: ?> <a class="linkbtn" href="https://www.openstreetmap.org/search?query=<?= e(rawurlencode(trim($r['name'] . ' ' . ($r['city'] ?? '')))) ?>" target="_blank" rel="noopener" title="Chercher sur OpenStreetMap">Chercher ↗</a><?php endif; ?>
              <?= empty($r['auto']) ? ' <span class="pill pill--ok" title="Saisi à la main">✓</span>' : '' ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="10" class="muted" style="padding:24px;text-align:center">Aucune ligne.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="row"><button type="submit" class="btn btn--navy" data-save>Enregistrer les modifications</button><span class="small muted" data-saved></span></div>
  </form>
  <?= $pager() ?>

<?php elseif ($tab === 'lieux'): ?>
  <form class="stack" data-json-form data-url="/admin/referentiels/enregistrer" novalidate>
    <input type="hidden" name="kind" value="lieux">
    <div class="alert alert--info small">Lieux de naissance des personnes, géolocalisés automatiquement pour la carte des origines. Corrigez une position erronée (homonymes : Valence en France ou en Espagne…) : la correction n’est plus jamais écrasée.</div>
    <div class="table grid-edit">
      <table>
        <thead><tr><th>Lieu</th><th>Pays</th><th>Personnes</th><th style="width:130px">Latitude</th><th style="width:130px">Longitude</th><th>Source</th><th></th></tr></thead>
        <tbody data-repeater="rows">
        <?php foreach ($rows as $r): ?>
          <tr data-item>
            <td><input type="hidden" data-field="key" value="<?= e($r['key']) ?>"><b><?= e($r['city']) ?></b></td>
            <td><?= e($r['country']) ?></td>
            <td class="t-num"><?= (int) $r['people'] ?></td>
            <td><input type="text" inputmode="decimal" data-field="lat" value="<?= e((string) ($r['lat'] ?? '')) ?>" aria-label="Latitude"></td>
            <td><input type="text" inputmode="decimal" data-field="lng" value="<?= e((string) ($r['lng'] ?? '')) ?>" aria-label="Longitude"></td>
            <td class="xs"><?= $r['manual'] ? '<span class="pill pill--ok">Saisie</span>' : e($r['source'] ?: '—') ?></td>
            <td><?php if ($u = $osm($r['lat'], $r['lng'])): ?><a class="linkbtn" href="<?= e($u) ?>" target="_blank" rel="noopener">Carte ↗</a><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="muted" style="padding:24px;text-align:center">Aucun lieu géolocalisé pour l’instant (la tâche planifiée « Géolocalisation » les ajoute au fil de l’eau).</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="row"><button type="submit" class="btn btn--navy" data-save>Enregistrer les modifications</button><span class="small muted" data-saved></span></div>
  </form>
  <?= $pager() ?>

<?php elseif ($tab === 'competitions'): ?>
  <div class="alert alert--info small">Compétitions telles qu’elles sont saisies dans les fiches matchs. Pour corriger une orthographe ou regrouper deux écritures, renommez-la : toutes les fiches concernées sont mises à jour (chaque fiche garde son historique).</div>
  <form class="toolbar" method="get" action="/admin/referentiels"><input type="hidden" name="onglet" value="competitions"><div class="search"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher une compétition…" aria-label="Rechercher"><button type="submit">→</button></div></form>
  <div class="table">
    <table>
      <thead><tr><th>Compétition</th><th>Intitulés affichés</th><th>Matchs</th><th>V · N · D</th><th>Période</th><th>Renommer partout</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="t-strong"><a class="rowlink" href="/admin/matchs?comp=<?= e(rawurlencode($r['name'])) ?>"><?= e($r['name']) ?></a></td>
          <td class="small"><?= e(implode(' · ', array_keys($r['labels']))) ?: '<span class="muted">—</span>' ?></td>
          <td class="t-num"><?= (int) $r['count'] ?></td>
          <td class="t-num"><?= (int) $r['v'] ?> · <?= (int) $r['n'] ?> · <?= (int) $r['d'] ?></td>
          <td class="nowrap"><?= e($r['from'] . ($r['to'] && $r['to'] !== $r['from'] ? '–' . $r['to'] : '')) ?></td>
          <td>
            <form class="row" style="gap:4px;flex-wrap:nowrap" method="post" action="/admin/referentiels/enregistrer" data-confirm="Renommer la compétition ?|Les <?= (int) $r['count'] ?> fiche(s) match « <?= e($r['name']) ?> » seront modifiées.|Renommer">
              <?= csrf_field() ?><input type="hidden" name="kind" value="competition"><input type="hidden" name="from" value="<?= e($r['name']) ?>">
              <input class="in in--sm" type="text" name="to" placeholder="Nouveau nom" aria-label="Nouveau nom de <?= e($r['name']) ?>" style="width:170px" required>
              <button type="submit" class="btn btn--sm">OK</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted" style="padding:24px;text-align:center">Aucune compétition.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

<?php else: ?>
  <div class="alert alert--info small">Chaque saison a sa page (calendrier, résultats, effectif, buteurs) calculée à partir des fiches matchs. Le texte et l’image d’en-tête se modifient dans la rubrique de la saison ; le bilan est un article de la rubrique.</div>
  <div class="table">
    <table>
      <thead><tr><th>Saison</th><th>Division</th><th>Matchs fichés</th><th>V · N · D</th><th>Rubrique</th><th>Bilan</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="t-num"><?= e($r['season']) ?></td>
          <td><?= e((string) ($r['division'] ?? '')) ?: '<span class="muted">—</span>' ?></td>
          <td class="t-num"><a href="/admin/matchs?saison=<?= e($r['season']) ?>"><?= (int) $r['count'] ?></a></td>
          <td class="t-num"><?= (int) $r['res']['V'] ?> · <?= (int) $r['res']['N'] ?> · <?= (int) $r['res']['D'] ?></td>
          <td><?= $r['cat'] ? '<a href="/admin/rubriques?rubrique=' . e($r['cat']['slug']) . '">' . e($r['cat']['label'] ?: $r['cat']['name']) . '</a>' : '<span class="warn">Pas de rubrique</span>' ?></td>
          <td><?= $r['bilan'] ? '<a href="/admin/fiche/' . (int) $r['bilan']['id'] . '">' . e($r['bilan']['title']) . '</a>' : '<span class="muted">—</span>' ?></td>
          <td><a class="linkbtn" href="/matchs/<?= e($r['season']) ?>/" target="_blank" rel="noopener">Page ↗</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
