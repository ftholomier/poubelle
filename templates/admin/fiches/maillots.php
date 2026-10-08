<?php
/**
 * Contenus › Maillots du livre. Variables : $jerseys (Livre::JERSEYS), $status (validations)
 */
$n = count(array_filter($status, fn ($s) => ($s['status'] ?? '') === 'valide'));
?>
<p class="alert alert--info" style="margin:0">Les maillots que le lecteur peut choisir pour la double page « Ton maillot » du livre. Chacun a été relevé sur une photo du musée (à gauche), puis reproduit en 3D (devant et dos) : couleurs et motifs seulement, <b>sans sponsor ni logo de marque</b>. Comparez avec la photo et, si le modèle est fidèle, <b>validez-le</b> ; sinon, renvoyez-le en écrivant ce qui ne va pas (col, manches, couleur, motif…). <b>Seuls les maillots validés sont proposés</b>.</p>

<div class="kpis">
  <div class="kpi kpi--yellow"><b><?= (int) $n ?></b><span>maillots validés</span><small>sur <?= count($jerseys) ?></small></div>
</div>

<form data-livre data-font="/assets/fonts/big-shoulders-display-normal-latin.woff2" data-jerseys="<?= e(json_encode($jerseys)) ?>" hidden></form>

<div class="stack" style="gap:16px">
<?php foreach ($jerseys as $k => $j): $s = $status[$k] ?? null; ?>
  <section class="card card--pad" id="m-<?= e($k) ?>" style="display:grid;grid-template-columns:240px 1fr;gap:16px;align-items:start">
    <div class="stack" style="gap:6px">
      <?php if ($j['ref']): ?><a href="/media/full/<?= e($j['ref']) ?>" target="_blank" rel="noopener"><img src="/media/480/<?= e($j['ref']) ?>.webp" alt="" style="width:240px;height:180px;object-fit:cover;border:2px solid var(--navy)"></a>
      <span class="xs muted">Photo de référence (cliquez pour l’agrandir)</span><?php else: ?><span class="xs muted">Modèle générique, sans photo de référence.</span><?php endif; ?>
    </div>
    <div class="stack" style="gap:10px">
      <div class="row" style="gap:10px;align-items:center;flex-wrap:wrap">
        <h2 class="card__t" style="margin:0"><?= e($j['label']) ?></h2>
        <?php if (($s['status'] ?? '') === 'valide'): ?><span class="pill pill--ok">Validé</span>
        <?php elseif (($s['status'] ?? '') === 'revoir'): ?><span class="pill pill--ko">À revoir</span>
        <?php else: ?><span class="pill pill--relire">À vérifier</span><?php endif; ?>
        <?php if ($s): ?><span class="xs muted">par <?= e($s['by']) ?>, le <?= e(date_fr($s['at'])) ?></span><?php endif; ?>
      </div>
      <?php if (!empty($s['note'])): ?><p class="small" style="margin:0"><b>Remarque :</b> <?= e($s['note']) ?></p><?php endif; ?>
      <div class="row" style="gap:8px" data-jersey-3d="<?= e($k) ?>">
        <div style="width:200px;height:222px;background:#F3EDDF;display:grid;place-items:center" class="xs muted">devant…</div>
        <div style="width:200px;height:222px;background:#F3EDDF;display:grid;place-items:center" class="xs muted">dos…</div>
      </div>
      <form method="post" action="/admin/maillots" class="row" style="gap:8px;flex-wrap:wrap;align-items:end">
        <?= csrf_field() ?><input type="hidden" name="key" value="<?= e($k) ?>">
        <label class="f" style="flex:1;min-width:260px"><span class="f__k">Remarque (si à revoir)</span><input name="note" maxlength="500" placeholder="Le col était blanc, les manches longues…"></label>
        <button class="btn btn--primary" name="status" value="valide" type="submit">Valider</button>
        <button class="btn" name="status" value="revoir" type="submit">À revoir</button>
      </form>
    </div>
  </section>
<?php endforeach; ?>
</div>
