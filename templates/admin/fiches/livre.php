<?php
/**
 * Contenus › Livre des récits. Variables : $last (bilan de la dernière composition ou null), $decades, $count
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

<form class="card card--pad stack" method="post" action="/admin/livre">
  <?= csrf_field() ?>
  <h2 class="card__t" style="margin:0">Composer un exemplaire</h2>
  <div class="row" style="gap:12px;flex-wrap:wrap">
    <label class="f"><span class="f__k">Exemplaire de (nom imprimé sur la couverture)</span><input name="nom" maxlength="60" placeholder="Lucas Bertrand"></label>
    <label class="f"><span class="f__k">Numéro d’exemplaire</span><input name="numero" maxlength="12" placeholder="0042"></label>
  </div>
  <label class="f"><span class="f__k">Dédicace (facultative)</span><textarea name="dedicace" rows="3" maxlength="600" placeholder="À mon fils, qui a découvert Bonal sur mes épaules…"></textarea></label>
  <label class="f"><span class="f__k">Signature de la dédicace</span><input name="signature" maxlength="80" placeholder="Papa, Noël 2026"></label>
  <div class="f"><span class="f__k">Décennies (aucune cochée : tout le livre)</span>
    <div class="row" style="gap:12px;flex-wrap:wrap">
      <?php foreach ($decades as $d): ?><label class="row" style="gap:6px"><input type="checkbox" name="decennies[]" value="<?= (int) $d ?>"> <?= (int) $d ?></label><?php endforeach; ?>
    </div>
  </div>
  <label class="row" style="gap:6px"><input type="checkbox" name="relire" value="1" checked> Inclure les récits « À relire » (marqués d’un bandeau rouge : épreuve de travail, pas pour l’impression)</label>
  <div><button class="btn btn--primary" type="submit">Composer le PDF</button> <span class="small muted">Environ une minute pour le livre entier.</span></div>
</form>

<?php if ($last && $last['sans_photo']): ?>
<section class="card card--pad stack">
  <h2 class="card__t" style="margin:0">Récits sans aucune photo assez définie (<?= count($last['sans_photo']) ?>)</h2>
  <p class="small" style="margin:0">Ajoutez-leur des images en bonne définition (scans à 300 dpi, originaux des photographes), puis recomposez le livre.</p>
  <ul class="small" style="columns:2;margin:0"><?php foreach ($last['sans_photo'] as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>
