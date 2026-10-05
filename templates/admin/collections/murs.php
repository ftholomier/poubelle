<?php
/**
 * Interactif › Murs de photos. Variables : $stats (clé de PhotoWall::REASONS ou « montrees » =>
 * nombre), $walls, $excluded, $isDefault, $hits, $credits, $photographers, $decades, $ready, $total
 */
use App\Services\PhotoWall;

$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$why = [
    'retiree' => 'case « Jamais sur les murs de photos » cochée dans la médiathèque',
    'sans-credit' => 'le crédit est vide : complétez-le dans la médiathèque',
    'dr' => '« DR », « droits réservés », auteur inconnu',
    'exclu' => 'un mot de la liste ci-dessous (agences, presse nationale, télévision, sites web)',
    'sans-auteur' => 'le crédit n’est qu’une date ou une légende (« Saison 1980-1981 », « Sochaux-Metz ») : à corriger',
    'petite' => 'moins de ' . PhotoWall::MIN_SIDE . ' px sur le petit côté : floue en grand',
    'non-publiee' => 'elle n’illustre aucune fiche publiée (pas encore relue)',
    'absent' => 'fichier introuvable sur le serveur',
    'pas-photo' => 'document PDF ou SVG',
];
?>
<p class="small" style="margin:0;max-width:95ch">Quatre murs de la rubrique <b>Interactif</b> tirent au hasard, à chaque visite, des photos de la médiathèque : <?php foreach (array_values($walls) as $i => $w): ?><?= $i ? ($i === count($walls) - 1 ? ' et ' : ', ') : '' ?><a href="<?= e($w[0]) ?>" target="_blank" rel="noopener"><?= e($w[1]) ?> ↗</a><?php endforeach; ?>. Seules les photos sûres y vont : crédit renseigné, ni « DR » ni crédit à risque, illustrant une fiche publiée, assez grandes. Le crédit est toujours affiché sous la photo.</p>

<div class="kpis">
  <a class="kpi kpi--yellow" href="/admin/medias?murs=montrees"><b><?= $fmt($total) ?></b><span>photos sur les murs</span><small><?= count($photographers) ?> photographes ou sources dans le filtre · <?= count($decades) ?> décennies</small></a>
  <a class="kpi" href="/admin/medias?murs=sans-auteur"><b><?= $fmt($stats['sans-auteur'] ?? 0) ?></b><span>crédits à corriger</span><small>une date ou une légende à la place du crédit</small></a>
  <a class="kpi" href="/admin/medias?filtre=sans-credit"><b><?= $fmt($stats['sans-credit'] ?? 0) ?></b><span>photos sans crédit</span><small>un crédit renseigné les ajoute aux murs</small></a>
  <a class="kpi" href="#vignettes" id="kpi-vignettes"><b><?= $total ? (int) floor(100 * min($ready) / $total) : 100 ?> %</b><span>vignettes prêtes</span><small>préparées d’avance par la tâche planifiée</small></a>
</div>

<div class="cols" style="align-items:start">
  <section class="card" id="raisons">
    <div class="card__head"><h2 class="card__t">Photos écartées</h2><span class="card__note">par raison · cliquez pour les voir dans la médiathèque</span></div>
    <div class="table" style="border:0">
      <table style="min-width:0">
        <tbody>
        <?php foreach (PhotoWall::REASONS as $k => $label): if (empty($stats[$k])) { continue; } ?>
          <tr>
            <td><a class="rowlink" href="/admin/medias?murs=<?= e($k) ?>"><b><?= e(ucfirst($label)) ?></b></a><br><span class="xs muted"><?= e($why[$k] ?? '') ?></span></td>
            <td class="right t-num"><?= $fmt($stats[$k]) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card" id="exclus">
    <div class="card__head"><h2 class="card__t">Crédits exclus</h2><span class="card__note"><?= $isDefault ? 'liste de départ' : 'liste modifiée' ?> · <?= count($excluded) ?> crédits</span></div>
    <form method="post" action="/admin/murs-photos" class="card__body stack" style="gap:12px">
      <?= csrf_field() ?><input type="hidden" name="action" value="exclus">
      <p class="small muted" style="margin:0">Un crédit qui contient l’un de ces mots ou noms est écarté des murs (majuscules et accents ignorés ; « Equipe » écarte « L’Équipe » et « Photo L’Equipe »). Une adresse de site (« .com », « .fr »…) l’est toujours. Une ligne par crédit.</p>
      <textarea name="exclus" rows="12" class="in" spellcheck="false" style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:14px"><?= e(implode("\n", $excluded)) ?></textarea>
      <div class="row"><button type="submit" class="btn btn--navy">Enregistrer la liste</button></div>
      <?php if ($hits): ?>
        <details><summary class="small" style="cursor:pointer">Ce que retire chaque ligne (<?= $fmt(array_sum($hits)) ?> photos)</summary>
          <p class="small" style="margin:8px 0 0"><?= e(implode(' · ', array_map(fn ($w, $n) => $w . ' : ' . $n, array_keys($hits), $hits))) ?></p>
        </details>
      <?php endif; ?>
    </form>
    <?php if (!$isDefault): ?>
      <form method="post" action="/admin/murs-photos" class="card__foot" data-confirm="Rétablir la liste de départ ?|Vos ajouts et retraits seront perdus.|Rétablir">
        <?= csrf_field() ?><input type="hidden" name="action" value="defaut"><button type="submit" class="btn btn--sm">Rétablir la liste de départ</button>
      </form>
    <?php endif; ?>
  </section>
</div>

<section class="card" id="credits">
  <div class="card__head"><h2 class="card__t">Crédits montrés</h2><span class="card__note"><?= count($credits) ?> crédits différents · un crédit étrange (une légende, un nom de match) se corrige dans la médiathèque</span></div>
  <div class="table" style="border:0;max-height:440px;overflow:auto">
    <table>
      <thead><tr><th>Crédit affiché</th><th>Photographe ou source (filtre)</th><th class="right">Photos</th></tr></thead>
      <tbody>
      <?php foreach ($credits as $c): ?>
        <tr>
          <td><a class="rowlink" href="/admin/medias?murs=montrees&amp;q=<?= e(rawurlencode($c['who'])) ?>">© <?= e($c['name']) ?></a></td>
          <td class="small"><?= e($c['who']) ?></td>
          <td class="right t-num"><?= $fmt($c['n']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card" id="vignettes">
  <div class="card__head"><h2 class="card__t">Vignettes des murs</h2><span class="card__note">petites images préparées d’avance : la première visite d’un mur n’attend pas</span></div>
  <form method="post" action="/admin/murs-photos" class="card__body stack" style="gap:10px" data-busy="Préparation en cours…">
    <?= csrf_field() ?><input type="hidden" name="action" value="vignettes">
    <?php foreach ($ready as $w => $n): ?>
      <div class="row" style="gap:10px;align-items:center"><span class="small nowrap" style="width:150px"><?= (int) $w ?> px de large</span><span class="meter" style="flex:1;max-width:420px"><i style="width:<?= $total ? round(100 * $n / $total, 1) : 100 ?>%"></i></span><span class="small nowrap"><?= $fmt($n) ?> / <?= $fmt($total) ?></span></div>
    <?php endforeach; ?>
    <p class="small muted" style="margin:0">La tâche planifiée « Murs de photos » les prépare toutes les 5 minutes, jusqu’à ce que tout soit prêt. Les photos dont la vignette est prête passent en premier dans les tirages.</p>
    <?php if (min($ready) < $total): ?><div class="row"><button type="submit" class="btn">Préparer maintenant (20 secondes)</button></div><?php endif; ?>
  </form>
</section>
