<?php
/** Interactif › Mon album. Variables : $stats, $set, $name, $albums (type => nombre d'albums) */
use App\Front\AlbumBook;

$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
?>
<p class="small" style="margin:0;max-width:90ch">Sur la page <a href="<?= e(AlbumBook::PATH) ?>#mon-album" target="_blank" rel="noopener"><?= e($name) ?> ↗</a>, chaque visiteur colle ses vignettes : une pochette gratuite par jour (tirée par le serveur), des vignettes bonus en visitant les fiches des joueurs ou en réussissant le défi du jour, des doubles à échanger par code. Sans compte (cookie anonyme et code de sauvegarde) ou dans le carnet du supporter. Aucune donnée personnelle ; collections inactives effacées après 18 mois.</p>

<div class="cols">
  <?php foreach ([['Collectionneurs', $stats['collectors'], $fmt($stats['active']) . ' actifs ces 30 derniers jours'], ['Pochettes ouvertes', $stats['packs'], $fmt($stats['stickers']) . ' vignettes tirées, dont ' . $fmt($stats['bonus']) . ' en bonus'], ['Échanges', $stats['trades'], $fmt($stats['offers']) . ' code(s) en attente ou récents'], ['Pages complétées', $stats['pages'], $fmt($stats['pool']) . ' vignettes à collectionner']] as [$t, $n, $d]): ?>
    <div class="card card--pad"><div class="row" style="justify-content:space-between;align-items:baseline"><h2 class="card__t"><?= e($t) ?></h2><span class="d" style="font-weight:900;font-size:32px;line-height:1"><?= $fmt($n) ?></span></div><span class="small"><?= e($d) ?></span></div>
  <?php endforeach; ?>
</div>

<section class="card">
  <div class="card__head"><h2 class="card__t">Réglages</h2></div>
  <form method="post" action="/admin/mon-album" class="card__body stack" style="gap:12px;max-width:640px">
    <?= csrf_field() ?>
    <label class="row" style="gap:8px"><input type="checkbox" name="enabled" value="1"<?= $set['enabled'] ? ' checked' : '' ?>> <span><b>Activer « Mon album »</b> (décoché : la section affiche « fermé », les collections sont gardées)</span></label>
    <div class="f"><span class="f__k">Pochettes gratuites par jour</span><input class="in in--sm" type="number" name="packs" min="0" max="10" value="<?= (int) $set['packs'] ?>"></div>
    <div class="f"><span class="f__k">Vignettes par pochette</span><input class="in in--sm" type="number" name="size" min="1" max="10" value="<?= (int) $set['size'] ?>"></div>
    <div class="f"><span class="f__k">Chance d’une brillante <i>% par vignette · légendes exclues, top buteurs, fidèles, internationaux</i></span><input class="in in--sm" type="number" name="shiny" min="0" max="60" value="<?= (int) $set['shiny'] ?>"></div>
    <div class="f"><span class="f__k">Chance d’une dorée <i>% par vignette · les légendes</i></span><input class="in in--sm" type="number" name="gold" min="0" max="20" value="<?= (int) $set['gold'] ?>"></div>
    <div class="f"><span class="f__k">Bonus « fiche visitée » <i>% de chance de gagner la vignette du joueur à la première visite de sa fiche</i></span><input class="in in--sm" type="number" name="fiche" min="0" max="100" value="<?= (int) $set['fiche'] ?>"></div>
    <div class="f"><span class="f__k">Bonus « fiche visitée » par jour au plus</span><input class="in in--sm" type="number" name="fiche_max" min="0" max="20" value="<?= (int) $set['fiche_max'] ?>"></div>
    <div class="f"><span class="f__k">Vignettes offertes pour le défi du jour réussi <i>5 bonnes réponses au moins, une fois par jour</i></span><input class="in in--sm" type="number" name="defi" min="0" max="10" value="<?= (int) $set['defi'] ?>"></div>
    <button type="submit" class="btn btn--navy">Enregistrer</button>
  </form>
</section>

<section class="card">
  <div class="card__head"><h2 class="card__t">Albums calculés</h2><span class="card__note">d’après les compositions des fiches matchs · rien à saisir</span></div>
  <div class="card__body"><ul class="small" style="margin:0"><?php foreach ($albums as $t => $n): ?><li><?= e(AlbumBook::typeLabel($t)) ?> : <?= (int) $n ?> album<?= $n > 1 ? 's' : '' ?></li><?php endforeach; ?></ul></div>
</section>
