<?php
use App\Core\Fs;
use App\Core\Url;
use App\Services\Geo;
use App\Services\Import\Importer;
/** @var array $exts @var array $sizes @var array $counts @var array $cron @var array $backups @var array $import @var ?string $dump */
?>
<div class="adm-head"><div><h1>Maintenance <span class="serif">& import</span></h1><p>Version <?= e($version) ?> · PHP <?= e($php) ?> (<?= e($sapi) ?>) · <?= e(Fs::humanSize((float) $free)) ?> d'espace libre.</p></div></div>
<div class="adm-grid">
  <div class="box">
    <h2>Actions rapides</h2>
    <div class="stack">
      <?php foreach (['cache' => ['Vider les caches', 'Pages, données calculées, sitemaps à jour au prochain passage'], 'reindex' => ['Reconstruire les index', 'Après une restauration ou une modification manuelle des fichiers'], 'sitemaps' => ['Régénérer les sitemaps', ''], 'cron' => ['Exécuter toutes les tâches planifiées', 'Emails, statistiques, récapitulatif, nettoyage…']] as $a => [$l, $d]): ?>
        <form method="post" action="<?= e(Url::admin('maintenance/action')) ?>" class="row-wrap"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $a ?>"><button class="btn btn-sm" type="submit"><?= e($l) ?></button><?php if ($d !== ''): ?><span class="small muted"><?= e($d) ?></span><?php endif; ?></form>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="box">
    <h2>Serveur</h2>
    <div class="health"><?php foreach ($exts as $l => $ok): ?><div class="<?= $ok ? 'ok' : 'warn' ?>"><i></i><span class="small"><?= e($l) ?></span></div><?php endforeach; ?></div>
    <ul class="list-rows mt-2 small"><?php foreach ($limits as $k => $v): ?><li><code><?= e($k) ?></code><span><?= e((string) $v) ?></span></li><?php endforeach; ?></ul>
    <p class="small mt-1">Cron : <?= $cron['ok'] ? '<span class="verified">✓ actif</span>' : '<strong style="color:var(--danger)">inactif</strong>' ?><?= $cron['last'] ? ' — ' . e(ago($cron['last'])) : '' ?> · <a href="<?= e(Url::admin('reglages#cron')) ?>">configurer</a></p>
  </div>
</div>
<div class="adm-grid mt-3">
  <div class="box">
    <h2>Données</h2>
    <ul class="list-rows small"><?php foreach ($counts as $c => $n): ?><li><span><?= e($c) ?></span><b><?= nf($n) ?></b></li><?php endforeach; ?></ul>
  </div>
  <div class="box">
    <h2>Espace occupé</h2>
    <ul class="list-rows small"><?php foreach ($sizes as $k => $s): ?><li><span><?= e($k) ?></span><b><?= e(Fs::humanSize($s)) ?></b></li><?php endforeach; ?></ul>
    <form class="row-wrap mt-2" method="post" action="<?= e(Url::admin('maintenance/action')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="geo">
      <select name="dep" class="input" style="max-width:240px;min-height:38px" aria-label="Département"><?php foreach (Geo::departements() as $code => $d): ?><option value="<?= e($code) ?>"><?= e($code . ' ' . $d['name']) ?></option><?php endforeach; ?></select>
      <button class="btn btn-xs" type="submit">Mettre à jour les communes (geo.api.gouv.fr)</button></form>
  </div>
</div>

<div class="box mt-3" id="sauvegardes">
  <div class="box-head"><h2>Sauvegardes</h2>
    <?php if ($zip): ?><form method="post" action="<?= e(Url::admin('maintenance/action')) ?>" class="row-wrap"><?= csrf_field() ?><input type="hidden" name="action" value="backup"><label class="check small"><input type="checkbox" name="media" value="1"> avec les photos</label><?php if ($canEnv): ?><label class="check small"><input type="checkbox" name="env" value="1"> avec la configuration (.env)</label><?php endif; ?><button class="btn btn-sm btn-ink" type="submit"><?= icon('archive', 16) ?> Créer une sauvegarde</button></form><?php endif; ?></div>
  <p class="small muted">Une sauvegarde automatique des données est faite chaque nuit (réglage du nombre conservé dans Fonctionnement). Téléchargez-en régulièrement une copie hors du serveur.</p>
  <?php if (!$backups): ?><p class="muted small">Aucune sauvegarde.</p><?php else: ?>
  <div class="table-wrap" style="box-shadow:none"><table class="tbl"><thead><tr><th>Fichier</th><th>Date</th><th class="num">Taille</th><th>Contenu</th><th></th></tr></thead><tbody>
    <?php foreach ($backups as $b): ?><tr><td class="small"><code><?= e($b['name']) ?></code></td><td class="small"><?= e(date_fr($b['date'], 'datetime')) ?></td><td class="num"><?= e(Fs::humanSize($b['size'])) ?></td><td class="small"><?= !empty($b['meta']['media']) ? 'données + photos' : 'données' ?><?= !empty($b['meta']['env']) ? ' + .env' : '' ?></td>
      <td class="actions"><?php if ($canEnv): ?><a class="btn btn-xs" href="<?= e(Url::admin('maintenance/sauvegarde/' . $b['name'])) ?>" title="Télécharger"><?= icon('download', 12) ?></a>
        <form method="post" action="<?= e(Url::admin('maintenance/action')) ?>" style="display:inline" data-confirm="Supprimer cette sauvegarde ?"><?= csrf_field() ?><input type="hidden" name="action" value="backup-delete"><input type="hidden" name="name" value="<?= e($b['name']) ?>"><button class="btn btn-xs" type="submit"><?= icon('trash', 12) ?></button></form><details style="display:inline-block"><summary class="btn btn-xs">Restaurer</summary><form method="post" action="<?= e(Url::admin('maintenance/action')) ?>" class="row-wrap mt-1"><?= csrf_field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="name" value="<?= e($b['name']) ?>"><input class="input" style="min-height:34px;max-width:150px" type="text" name="confirm" placeholder="RESTAURER"><button class="btn btn-xs" type="submit">OK</button></form></details><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>

<div class="box mt-3" id="import">
  <h2>Données de l'ancien site</h2>
  <?php if ($importDone): ?>
    <div class="alert alert-success small mb-2"><div>Les données de l'ancien site sont en place depuis le <?= e(date_fr((string) $importDone['at'], 'long')) ?> : fiches, demandes de devis, messages, historique et photos. <strong>Aucune base de données ni fichier SQL n'est nécessaire</strong> : tout est enregistré dans des fichiers.</div></div>
  <?php else: ?>
    <p class="small">Aucune donnée de l'ancien site n'est en place. Le plus simple : déposez par FTP les archives de données livrées avec le site (<code>donnees….zip</code>) dans <code>storage/install/</code>, elles s'installent toutes seules. Sinon, l'outil ci-dessous convertit une fois pour toutes l'export de l'ancienne base en fichiers.</p>
  <?php endif; ?>
  <?php if (!$canEnv): ?><p class="muted small">Réservé au super-administrateur.</p><?php else: ?>
  <details<?= $importDone ? '' : ' open' ?>>
  <summary class="small"><strong>Reconvertir un export de l'ancienne base</strong> (facultatif<?= $importDone ? ', remplace les données actuelles' : '' ?>)</summary>
  <div class="adm-grid mt-2">
    <div>
      <p class="small"><strong>1. Déposer l'export SQL</strong> de l'ancienne base (<?= e((string) ini_get('upload_max_filesize')) ?> max par envoi ; sinon déposez-le par FTP dans <code>storage/import/</code>).</p>
      <form method="post" action="<?= e(Url::admin('maintenance/import')) ?>" enctype="multipart/form-data" class="row-wrap"><?= csrf_field() ?><input type="file" name="dump" accept=".sql" class="small"><button class="btn btn-xs btn-ink" type="submit">Envoyer</button></form>
      <p class="small mt-1">Fichier détecté : <?= $dump ? '<code>' . e(basename($dump)) . '</code> (' . e(Fs::humanSize((int) filesize($dump))) . ')' : '<strong>aucun</strong>' ?></p>
    </div>
    <div class="small muted">Les mots de passe des pros sont chiffrés (Argon2id) pendant l'import ; ils devront en choisir un nouveau à leur première connexion. Les tables historiques (factures, statistiques…) sont archivées en lecture seule.</div>
  </div>
  <?php if ($dump): ?>
  <div class="mt-2" data-runner="<?= e(Url::admin('maintenance/import')) ?>">
    <p class="small"><strong>2. Lancer la conversion</strong> (quelques minutes, ne fermez pas la page) :</p>
    <ul class="steps-run">
      <?php $i = 1; foreach (Importer::STEPS as $step => [$label]): $st = $import['steps'][$step] ?? null; ?>
        <li data-step="<?= e($step) ?>" class="<?= !empty($st['done']) ? 'done' : '' ?>"><span class="st"><?= $i++ ?></span><span><?= e($label) ?><br><small><?= e((string) ($st['message'] ?? '')) ?></small></span></li>
      <?php endforeach; ?>
    </ul>
    <div class="progress mt-1"><i></i></div>
    <button type="button" class="btn btn-sm btn-coral mt-2" data-runner-start data-confirm-text="Lancer la conversion complète ? Les données actuelles (fiches, demandes, messages) seront remplacées par celles de l'export.">Lancer la conversion</button>
    <pre class="log mt-1" data-runner-log style="max-height:200px"></pre>
  </div>
  <?php endif; ?>
  <div class="mt-3" data-runner="<?= e(Url::admin('maintenance/import')) ?>">
    <p class="small"><strong>3. Rapatrier les photos</strong> depuis l'ancien site (<?= e((string) env('OLD_SITE_URL')) ?>), seulement après une reconversion : les fiches déjà traitées sont ignorées. <?= nf($photosTodo) ?> fiche(s) en ligne sans photo (la plupart n'en avaient pas sur l'ancien site non plus).</p>
    <ul class="steps-run"><li data-step="photos"><span class="st">📷</span><span>Photos des pros en ligne<br><small></small></span></li></ul>
    <div class="progress mt-1"><i></i></div>
    <button type="button" class="btn btn-sm mt-1" data-runner-start>Lancer</button>
    <pre class="log mt-1" data-runner-log style="max-height:160px"></pre>
  </div>
  </details>
  <?php endif; ?>
</div>
