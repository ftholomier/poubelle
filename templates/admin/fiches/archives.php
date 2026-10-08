<?php
/**
 * Contenus › Archives à ranger. Variables : $list, $count, $page, $pages, $status, $type, $summary, $admin, $known, $recits
 */
use App\Services\Catalogue as C;

$qs = fn (array $p) => '/admin/archives?' . http_build_query(array_filter(['etat' => $status, 'type' => $type, 'page' => null] + $p, fn ($v) => $v !== null && $v !== ''));
$tabs = ['recu' => 'À ranger', 'range' => 'Rangées', 'ecarte' => 'Écartées', 'attente' => 'Pas encore déposées', 'tout' => 'Tout'];
$rightsTone = ['club' => 'ok', 'peugeot' => 'info', 'presse' => 'warn', 'photographe' => 'warn', 'inconnu' => 'brouillon'];
?>
<p class="alert alert--info" style="margin:0">Les <b><?= (int) $summary['total'] ?> images d’archives</b> confiées au musée (photos, pages de la publication de 1978, documents du musée Peugeot) ont été lues une à une : type, date, légende, personnes, match et origine des droits. Déposez les zip ci-dessous : chaque image est reconnue <b>par son contenu</b> (peu importe son nom) et arrive ici avec sa légende. Vérifiez-la, cochez les fiches où la ranger (match, joueurs) puis <b>Ranger</b> : elle rejoint leur galerie. Rien n’est publié sans cette validation.</p>

<div class="kpis">
  <div class="kpi kpi--yellow"><b><?= (int) $summary['recu'] ?></b><span>à ranger</span><small>déposées, à valider</small></div>
  <div class="kpi"><b><?= (int) $summary['range'] ?></b><span>rangées</span><small>dans les galeries des fiches</small></div>
  <div class="kpi"><b><?= (int) $summary['ecarte'] ?></b><span>écartées</span></div>
  <div class="kpi"><b><?= (int) $summary['attente'] ?></b><span>pas encore déposées</span><small>sur <?= (int) $summary['total'] ?></small></div>
</div>

<?php if ($admin): ?>
<section class="card card--pad stack" data-arc data-csrf="<?= e(\App\Core\Session::csrfToken()) ?>" data-known="<?= e(implode(',', $known)) ?>">
  <h2 class="card__t" style="margin:0">Déposer les archives</h2>
  <p class="small" style="margin:0">Choisissez les zip reçus (dossier_1, livre_2, archives_musee_peugeot_3…) ou directement des images : le navigateur les ouvre et envoie les images une par une. On peut déposer plusieurs fois les mêmes : une image déjà reçue n’est jamais doublée.</p>
  <div class="row" style="gap:10px;flex-wrap:wrap;align-items:center">
    <input type="file" multiple accept=".zip,image/jpeg,image/png" data-arc-input>
    <span class="small" data-arc-status></span>
  </div>
  <div style="height:8px;border:2px solid var(--navy);background:var(--paper)"><i data-arc-bar style="display:block;height:100%;width:0;background:var(--yellow)"></i></div>
</section>
<?php endif; ?>

<?php if ($admin && $recits): $missing = count(array_filter($recits, fn ($r) => !$r['fiche'])); ?>
<section class="card card--pad stack">
  <div class="row" style="justify-content:space-between;gap:12px;flex-wrap:wrap">
    <h2 class="card__t" style="margin:0">Grands récits</h2>
    <div class="row" style="gap:8px">
      <?php if ($missing): ?><form method="post" action="/admin/archives"><?= csrf_field() ?><input type="hidden" name="action" value="recits"><button class="btn btn--primary btn--sm" type="submit">Créer les <?= (int) $missing ?> récit(s) manquant(s)</button></form><?php endif; ?>
      <?php if ($missing < count($recits)): ?><form method="post" action="/admin/archives"><?= csrf_field() ?><input type="hidden" name="action" value="illustrer"><button class="btn btn--sm" type="submit">Illustrer les récits</button></form><?php endif; ?>
    </div>
  </div>
  <p class="small" style="margin:0">Récits illustrés de la rubrique « Grands récits », écrits d’après les feuilles de match et les archives. Une fois créés, ils apparaissent dans les fiches proposées pour ranger les images (photos des finales, de la Coupe UEFA, du record de 1976, de la tournée de 1965…). Chaque récit reçoit d’office jusqu’à 8 photos : celles des matchs qu’il cite, les portraits des joueurs nommés et les images d’archives de la même époque (jamais la presse). Après un nouveau dépôt d’archives, <b>Illustrer les récits</b> complète les galeries sans rien retirer. Un récit déjà créé n’est jamais réécrit : retouchez-le librement. Créez-les <b>après l’import des feuilles de match</b> (Système › Feuilles de match) : les récits se relient alors aux fiches des matchs qu’ils racontent.</p>
  <ul class="small" style="margin:0;padding-left:18px"><?php foreach ($recits as $r): ?><li><?= e($r['title']) ?> — <?= $r['fiche'] ? '<a href="/admin/fiche/' . (int) $r['fiche'] . '">créé (n° ' . (int) $r['fiche'] . ')</a>' : '<span class="muted">pas encore créé</span>' ?></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>

<nav class="tabs" style="display:flex;gap:6px;flex-wrap:wrap">
  <?php foreach ($tabs as $k => $l): ?><a class="btn btn--sm<?= $status === $k ? ' btn--primary' : ' btn--ghost' ?>" href="<?= e($qs(['etat' => $k])) ?>"><?= e($l) ?></a><?php endforeach; ?>
  <span style="flex:1"></span>
  <form method="get" action="/admin/archives"><input type="hidden" name="etat" value="<?= e($status) ?>">
    <select name="type" class="input" onchange="this.form.submit()"><option value="">Tous les types</option><?php foreach (C::TYPES as $k => $l): ?><option value="<?= e($k) ?>"<?= $type === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
  </form>
</nav>
<p class="small muted" style="margin:0"><?= (int) $count ?> image(s)<?= $pages > 1 ? ' · page ' . (int) $page . ' / ' . (int) $pages : '' ?></p>

<?php if (!$list): ?>
  <section class="card card--pad"><p class="muted" style="margin:0"><?= $status === 'recu' ? 'Aucune image à ranger.' . ($admin ? ' Déposez les zip ci-dessus.' : '') : 'Rien ici pour l’instant.' ?></p></section>
<?php endif; ?>

<?php foreach ($list as $it): $tone = $rightsTone[$it['rights'] ?? 'inconnu'] ?? 'brouillon'; ?>
  <form method="post" action="/admin/archives" class="card" style="display:grid;grid-template-columns:minmax(160px,260px) 1fr minmax(180px,240px);gap:16px;padding:14px 18px;align-items:start">
    <?= csrf_field() ?>
    <input type="hidden" name="md5" value="<?= e($it['md5']) ?>"><input type="hidden" name="etat" value="<?= e($status) ?>"><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="page" value="<?= (int) $page ?>">
    <div>
      <?php if ($it['media']): ?><a href="<?= e(img($it['media'], 1600)) ?>" target="_blank" rel="noopener"><img src="<?= e(img($it['media'], 480)) ?>" alt="" loading="lazy" style="width:100%;height:auto;border:2px solid var(--navy);background:var(--paper)"></a>
      <?php else: ?><div class="muted xs" style="border:2px dashed var(--line,#d8d2c0);padding:24px 10px;text-align:center">Pas encore déposée<br><?= e(($it['lot'] ?? '') . ' / ' . ($it['file'] ?? '')) ?></div><?php endif; ?>
    </div>
    <div class="stack" style="gap:8px;min-width:0">
      <b><?= e((string) $it['title']) ?></b>
      <span class="xs"><span class="pill pill--info"><?= e(C::TYPES[$it['type'] ?? 'autre'] ?? 'Autre') ?></span> <span class="pill pill--<?= e($tone) ?>"><?= e(C::RIGHTS[$it['rights'] ?? 'inconnu'] ?? '') ?></span>
        <?= !empty($it['date']) ? '· ' . e((string) $it['date']) . (empty($it['date_sure']) ? ' (à vérifier)' : '') : (!empty($it['decade']) ? '· années ' . e((string) $it['decade']) : '') ?> <?= !empty($it['publication']) ? '· ' . e((string) $it['publication']) : '' ?></span>
      <?php if ($it['status'] === 'recu'): ?>
        <textarea class="input" name="caption" rows="2" style="width:100%"><?= e((string) $it['caption']) ?></textarea>
      <?php else: ?><p class="small" style="margin:0"><?= e((string) $it['caption']) ?></p><?php endif; ?>
      <?php if (!empty($it['summary'])): ?><p class="xs muted" style="margin:0">Contenu : <?= e((string) $it['summary']) ?></p><?php endif; ?>
      <?php if (!empty($it['facts'])): ?><p class="xs muted" style="margin:0">Faits lus : <?= e(implode(' · ', (array) $it['facts'])) ?></p><?php endif; ?>
      <?php if (!empty($it['persons'])): ?><p class="xs muted" style="margin:0">Personnes : <?= e(implode(', ', (array) $it['persons'])) ?></p><?php endif; ?>
      <?php if (($it['rights'] ?? '') === 'presse'): ?><p class="xs" style="margin:0"><b>Presse :</b> ne publiez qu’avec l’autorisation du journal ; sinon gardez-la comme source et écartez-la.</p><?php endif; ?>
      <span class="xs muted"><?= e(($it['lot'] ?? '') . ' / ' . ($it['file'] ?? '')) ?></span>
    </div>
    <div class="stack" style="gap:6px">
      <?php if ($it['status'] === 'recu'): ?>
        <span class="xs"><b>Ranger dans :</b></span>
        <?php foreach ($it['suggest'] as $s): ?>
          <label class="xs" style="display:flex;gap:6px;align-items:flex-start"><input type="checkbox" name="fiches[]" value="<?= (int) $s['id'] ?>"<?= $s['checked'] ? ' checked' : '' ?>> <span><?= e($s['kind']) ?> · <a href="/admin/fiche/<?= (int) $s['id'] ?>" target="_blank" rel="noopener"><?= e($s['title']) ?></a></span></label>
        <?php endforeach; ?>
        <?php if (!$it['suggest']): ?><span class="xs muted">Aucune fiche trouvée d’office.</span><?php endif; ?>
        <input class="input" name="autre" placeholder="Autre fiche : son numéro" style="font-size:13px">
        <button class="btn btn--primary btn--sm" type="submit" name="action" value="ranger">Ranger</button>
        <button class="btn btn--ghost btn--sm" type="submit" name="action" value="ecarter">Écarter</button>
      <?php elseif ($it['status'] === 'range'): ?>
        <span class="xs">Rangée dans : <?= implode(', ', array_map(fn ($id) => '<a href="/admin/fiche/' . (int) $id . '">n° ' . (int) $id . '</a>', (array) $it['fiches'])) ?></span>
        <button class="btn btn--ghost btn--sm" type="submit" name="action" value="retablir">Ranger ailleurs aussi</button>
      <?php elseif ($it['status'] === 'ecarte'): ?>
        <button class="btn btn--ghost btn--sm" type="submit" name="action" value="retablir">Remettre à ranger</button>
      <?php endif; ?>
    </div>
  </form>
<?php endforeach; ?>

<?php if ($pages > 1): ?>
<nav class="row" style="gap:6px;flex-wrap:wrap"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="btn btn--sm<?= $i === $page ? ' btn--primary' : ' btn--ghost' ?>" href="<?= e($qs(['page' => $i])) ?>"><?= $i ?></a><?php endfor; ?></nav>
<?php endif; ?>
