<?php
/**
 * Contenus › Livre des récits. Variables : $last (bilan de la dernière composition ou null), $decades, $count,
 * $covers (photos proposées en couverture), $suggest (photos des récits assez définies pour la couverture)
 */
use App\Pdf\Livre;
?>
<p class="alert alert--info" style="margin:0">Le livre <b>« 100 récits du Lion »</b> reprend les <a href="/admin/archives">grands récits</a> dans l’ordre chronologique : couverture, dédicace, sommaire, une ouverture par décennie, puis chaque récit en deux colonnes avec ses photos. Format <b><?= Livre::TRIM_W ?> × <?= Livre::TRIM_H ?> mm</b>, fonds perdus de <?= Livre::BLEED ?> mm, nombre de pages multiple de 4 : le PDF est prêt pour l’imprimeur.<br>
<b>Photos :</b> une image n’est imprimée que si sa définition suffit à la taille choisie (pleine page et bandeau : <?= Livre::DPI['page'] ?> dpi ; pleine largeur : <?= Livre::DPI['large'] ?> ; colonne : <?= Livre::DPI['colonne'] ?>). Sinon elle est essayée plus petite, ou laissée de côté. Jamais d’image de presse.</p>

<div class="kpis">
  <div class="kpi kpi--yellow"><b><?= (int) $count['publie'] ?></b><span>récits publiés</span><small>toujours dans le livre</small></div>
  <div class="kpi"><b><?= (int) $count['relire'] ?></b><span>récits à relire</span><small>seulement si la case est cochée</small></div>
  <?php if ($last): ?>
    <div class="kpi"><b><?= (int) $last['pages'] ?></b><span>pages</span><small>dernière composition, <?= e(date_fr($last['at'])) ?></small></div>
    <div class="kpi"><b><?= (int) ($last['page'] + $last['bandeau'] + $last['large'] + $last['colonne']) ?></b><span>photos imprimées</span><small><?= (int) $last['ecartees'] ?> laissée(s) de côté (définition)</small></div>
  <?php endif; ?>
</div>

<form class="card card--pad stack" method="post" action="/admin/livre" enctype="multipart/form-data" data-livre data-font="/assets/fonts/big-shoulders-display-normal-latin.woff2" data-jerseys="<?= e(json_encode(Livre::JERSEYS)) ?>">
  <input type="file" name="maillot_image" accept="image/png" hidden><input type="file" name="maillot_devant" accept="image/png" hidden>
  <?= csrf_field() ?>
  <h2 class="card__t" style="margin:0">Composer un exemplaire</h2>
  <div class="row" style="gap:12px;flex-wrap:wrap">
    <label class="f"><span class="f__k">Exemplaire de (nom imprimé sur la couverture)</span><input name="nom" maxlength="60" placeholder="Lucas Bertrand"></label>
    <label class="f"><span class="f__k">Numéro d’exemplaire</span><input name="numero" maxlength="12" placeholder="0042"></label>
  </div>
  <label class="f"><span class="f__k">Dédicace (facultative)</span><textarea name="dedicace" rows="3" maxlength="600" placeholder="À mon fils, qui a découvert Bonal sur mes épaules…"></textarea></label>
  <div class="row" style="gap:12px;flex-wrap:wrap">
    <label class="f"><span class="f__k">Signature de la dédicace</span><input name="signature" maxlength="80" placeholder="Papa, Noël 2026"></label>
    <label class="f"><span class="f__k">Supporter depuis (année)</span><input name="depuis" type="number" min="1928" max="<?= (int) date('Y') ?>" placeholder="1998"></label>
  </div>
  <div class="row" style="gap:12px;flex-wrap:wrap">
    <label class="f"><span class="f__k">Option « Mon match » : n° de la fiche du match</span><input name="match" inputmode="numeric" placeholder="14188"></label>
    <label class="f"><span class="f__k">Option « Mes joueurs » : n° de 1 à 3 fiches joueurs</span><input name="joueurs" placeholder="10258, 4901, 9357"></label>
  </div>
  <div class="row" style="gap:12px;flex-wrap:wrap">
    <label class="f"><span class="f__k">« Le jour de ta naissance » : date</span><input type="date" name="naissance"></label>
    <label class="f"><span class="f__k">Titre de cette page</span><input name="naissance_titre" maxlength="50" placeholder="Le jour de ta naissance"></label>
    <label class="f"><span class="f__k">Carnet du supporter (pseudo ou adresse de sa page)</span><input name="carnet" placeholder="lucas-b"></label>
  </div>
  <div class="row" style="gap:12px;flex-wrap:wrap">
    <label class="f"><span class="f__k">Sa photo (JPEG ou PNG, 670 px de large au moins)</span><input type="file" name="photo" accept="image/jpeg,image/png"></label>
    <label class="f"><span class="f__k">Légende de sa photo</span><input name="photo_legende" maxlength="120" placeholder="Avec papa à Bonal, août 2024"></label>
  </div>
  <div class="row" style="gap:12px;flex-wrap:wrap">
    <label class="f"><span class="f__k">Maillot : nom floqué</span><input name="maillot_nom" maxlength="14" placeholder="LUCAS"></label>
    <label class="f"><span class="f__k">Numéro</span><input name="maillot_numero" maxlength="2" inputmode="numeric" placeholder="10" style="width:80px"></label>
    <label class="f"><span class="f__k">Maillot inspiré de</span><select name="maillot_style"><?php $valid = Livre::jerseysValid(); foreach (Livre::JERSEYS as $k => $j): ?><option value="<?= e($k) ?>"><?= e($j['label']) ?><?= isset($valid[$k]) ? '' : ' (à vérifier : épreuve seulement)' ?></option><?php endforeach; ?></select> <a class="xs" href="/admin/maillots">Valider les maillots</a></label>
  </div>
  <label class="row" style="gap:6px"><input type="checkbox" name="qr" value="1" checked> QR code de chaque récit vers sa page au musée</label>
  <div class="f"><span class="f__k">Photo de couverture</span>
    <div class="row" style="gap:10px;flex-wrap:wrap">
      <label class="row" style="gap:6px"><input type="radio" name="couverture" value="" checked> Couverture graphique (sans photo)</label>
      <?php foreach ($covers as $c): if (($c['dpi'] ?? 0) < Livre::DPI['page']) continue; ?>
        <label class="row" style="gap:6px"><input type="radio" name="couverture" value="<?= e($c['rel']) ?>"> <img src="/media/160/<?= e($c['rel']) ?>.webp" alt="" style="width:60px;height:40px;object-fit:cover"></label>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="f"><span class="f__k">Épreuve partielle : décennies (aucune cochée : le livre entier, seul vendu)</span>
    <div class="row" style="gap:12px;flex-wrap:wrap">
      <?php foreach ($decades as $d): ?><label class="row" style="gap:6px"><input type="checkbox" name="decennies[]" value="<?= (int) $d ?>"> <?= (int) $d ?></label><?php endforeach; ?>
    </div>
  </div>
  <label class="row" style="gap:6px"><input type="checkbox" name="relire" value="1" checked> Inclure les récits « À relire » (marqués d’un bandeau rouge : épreuve de travail, pas pour l’impression)</label>
  <div><button class="btn btn--primary" type="submit">Composer le PDF</button> <span class="small muted" data-livre-note>Environ une minute pour le livre entier.</span></div>
</form>

<?php if ($last && $last['sans_photo']): ?>
<section class="card card--pad stack">
  <h2 class="card__t" style="margin:0">Récits sans aucune photo assez définie (<?= count($last['sans_photo']) ?>)</h2>
  <p class="small" style="margin:0">Ajoutez-leur des images en bonne définition (scans à 300 dpi, originaux des photographes), puis recomposez le livre.</p>
  <ul class="small" style="columns:2;margin:0"><?php foreach ($last['sans_photo'] as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>

<section class="card card--pad stack">
  <h2 class="card__t" style="margin:0">Photos proposées en couverture (<?= count($covers) ?>)</h2>
  <p class="small" style="margin:0">Le client choisit sa couverture parmi ces photos. Seules les photos assez définies pour la couverture sont acceptées (<?= Livre::DPI['page'] ?> dpi pour le haut de couverture, soit environ 2 130 × 1 670 pixels au moins), jamais la presse. Légende et crédit sont imprimés au verso de la couverture.</p>
  <?php if ($covers): ?>
  <div class="row" style="gap:12px;flex-wrap:wrap">
    <?php foreach ($covers as $c): ?>
      <form method="post" action="/admin/livre" class="stack" style="gap:4px;width:180px">
        <?= csrf_field() ?><input type="hidden" name="action" value="couv-retirer"><input type="hidden" name="rel" value="<?= e($c['rel']) ?>">
        <img src="/media/320/<?= e($c['rel']) ?>.webp" alt="" style="width:180px;height:120px;object-fit:cover">
        <span class="xs"><?= e(mb_strimwidth($c['caption'], 0, 70, '…')) ?></span>
        <span class="xs muted"><?= $c['dpi'] !== null ? (int) $c['dpi'] . ' dpi' : 'introuvable' ?></span>
        <button class="btn btn--ghost btn--sm" type="submit">Retirer</button>
      </form>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <form method="post" action="/admin/livre" class="row" style="gap:8px;flex-wrap:wrap;align-items:end">
    <?= csrf_field() ?><input type="hidden" name="action" value="couv-ajouter">
    <label class="f" style="flex:1;min-width:280px"><span class="f__k">Ajouter une photo de la médiathèque (chemin ou adresse de l’image)</span><input name="rel" placeholder="2025/03/scan-bonal-1976.jpg"></label>
    <button class="btn" type="submit">Proposer en couverture</button>
  </form>
  <?php if ($suggest): ?>
    <h3 class="small" style="margin:8px 0 0">Suggestions : photos des récits assez définies</h3>
    <div class="row" style="gap:12px;flex-wrap:wrap">
      <?php foreach ($suggest as $c): ?>
        <form method="post" action="/admin/livre" class="stack" style="gap:4px;width:180px">
          <?= csrf_field() ?><input type="hidden" name="action" value="couv-ajouter"><input type="hidden" name="rel" value="<?= e($c['rel']) ?>">
          <img src="/media/320/<?= e($c['rel']) ?>.webp" alt="" style="width:180px;height:120px;object-fit:cover">
          <span class="xs"><?= e(mb_strimwidth($c['caption'], 0, 70, '…')) ?></span>
          <span class="xs muted"><?= (int) $c['dpi'] ?> dpi</span>
          <button class="btn btn--sm" type="submit">Proposer</button>
        </form>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="xs muted" style="margin:0">Aucune photo des récits n’a encore la définition d’une couverture : ajoutez des scans ou des originaux en haute définition.</p>
  <?php endif; ?>
</section>
