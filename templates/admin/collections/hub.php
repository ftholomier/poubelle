<?php
/** Hub « Interactif ». Variables : $cards, $voters, $album, $momentsOpen, $stades, $stadesMissing, $retro */
$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
?>
<div class="cols">
  <?php foreach ($cards as $c): ?>
    <a class="card card--pad" href="/admin/collection/<?= e($c['name']) ?>" style="color:var(--navy)">
      <div class="row" style="justify-content:space-between;align-items:baseline"><h2 class="card__t"><?= e($c['label']) ?></h2><span class="d" style="font-weight:900;font-size:32px;line-height:1"><?= (int) $c['count'] ?></span></div>
      <span class="small"><?= e(mb_strtolower($c['item'])) ?>(s)<?= $c['default'] ? ' · <span class="warn">contenu de départ, à valider</span>' : '' ?></span>
      <span class="small"><?= $c['todo'] ? '<span class="warn">⚠ ' . (int) $c['todo'] . ' à valider</span>' : '<span class="ok">✓ tout est validé</span>' ?><?= $c['noEn'] ? ' · <span class="muted">' . (int) $c['noEn'] . ' sans anglais</span>' : '' ?></span>
      <span class="linkbtn">Modifier →</span>
    </a>
  <?php endforeach; ?>
  <a class="card card--pad" href="/admin/onze" style="color:var(--navy)">
    <div class="row" style="justify-content:space-between;align-items:baseline"><h2 class="card__t">Onze de légende</h2><span class="d" style="font-weight:900;font-size:32px;line-height:1"><?= $fmt($voters) ?></span></div>
    <span class="small">votes du public · résultats et date de dévoilement</span><span class="linkbtn">Voir →</span>
  </a>
  <a class="card card--pad" href="/admin/album" style="color:var(--navy)">
    <div class="row" style="justify-content:space-between;align-items:baseline"><h2 class="card__t">Album du centenaire</h2><span class="d" style="font-weight:900;font-size:32px;line-height:1"><?= (int) $album ?></span></div>
    <span class="small"><?= $album ? 'cartes choisies par les historiens' : 'proposition automatique en ligne (à valider)' ?></span><span class="linkbtn">Composer →</span>
  </a>
  <a class="card card--pad" href="/admin/retro-direct" style="color:var(--navy)">
    <div class="row" style="justify-content:space-between;align-items:baseline"><h2 class="card__t">Rétro-Direct</h2><span class="d" style="font-weight:900;font-size:32px;line-height:1"><?= (int) $retro ?></span></div>
    <span class="small">direct(s) à venir · grands matchs rejoués le jour anniversaire</span><span class="linkbtn">Programmer →</span>
  </a>
  <a class="card card--pad" href="/admin/moments" style="color:var(--navy)">
    <div class="row" style="justify-content:space-between;align-items:baseline"><h2 class="card__t">100 moments</h2><span class="d" style="font-weight:900;font-size:32px;line-height:1"><?= (int) $momentsOpen ?></span></div>
    <span class="small">moments révélés sur 100</span><span class="linkbtn">Calendrier →</span>
  </a>
  <a class="card card--pad" href="/admin/referentiels?onglet=stades" style="color:var(--navy)">
    <div class="row" style="justify-content:space-between;align-items:baseline"><h2 class="card__t">Carte des stades</h2><span class="d" style="font-weight:900;font-size:32px;line-height:1"><?= (int) $stades ?></span></div>
    <span class="small"><?= $stadesMissing ? '<span class="warn">' . (int) $stadesMissing . ' sans coordonnées</span>' : '<span class="ok">✓ tous placés</span>' ?> · lieux de naissance dans « Référentiels »</span><span class="linkbtn">Corriger →</span>
  </a>
</div>
