<?php
/**
 * Système › Mises à jour. Variables : $check (dernière vérification : latest, commits, files,
 * sync, error, at), $installed (version installée ou null), $available, $history, $backups,
 * $repo, $branch
 */
use App\Services\Updater;

$sha = fn (?string $s) => $s ? substr($s, 0, 7) : '—';
$when = fn (?string $d) => $d ? date('d/m/Y à H:i', strtotime($d)) : '';
$latest = $check['latest'] ?? null;
$commits = $check['commits'] ?? [];
$sync = $check['sync'] ?? null;
$differ = Updater::differing($check);           // null : pas de comparaison fichier par fichier
$newVersion = $available && Updater::isNewVersion($check);
$title = $newVersion ? 'Nouvelle version disponible' : ($available ? 'Le serveur diffère de GitHub' : 'Le site est à jour');
$button = $newVersion ? 'Appliquer la mise à jour' : 'Synchroniser avec GitHub';
?>
<div class="kpis">
  <div class="kpi<?= $installed ? '' : ' kpi--yellow' ?>"><b><?= $installed ? e($sha($installed['sha'])) : 'FTP' ?></b><span>version installée</span><small><?php if (!$installed): ?>mise en ligne par FTP : version pas encore reconnue<?php elseif (($installed['via'] ?? '') === 'reconnue'): ?>reconnue le <?= e($when($installed['at'] ?? null)) ?> (identique à GitHub)<?php else: ?><?= e($when($installed['date'] ?? null) ?: $when($installed['at'] ?? null)) ?><?php endif; ?></small></div>
  <div class="kpi<?= $newVersion ? ' kpi--yellow' : '' ?>"><b><?= e($sha($latest['sha'] ?? null)) ?></b><span>dernière version sur GitHub</span><small><?= $latest ? e($when($latest['date'] ?? null)) : 'non vérifiée' ?></small></div>
  <?php if ($differ !== null): ?>
    <div class="kpi<?= $differ ? ' kpi--yellow' : '' ?>"><b><?= (int) $differ ?></b><span>fichier<?= $differ > 1 ? 's' : '' ?> à remplacer</span><small>sur <?= (int) $sync['total'] ?> comparés un par un · <?= e(date('d/m à H:i', (int) ($sync['at'] ?? time()))) ?></small></div>
  <?php else: ?>
    <div class="kpi"><b><?= $available ? (count($commits) ?: '?') : '0' ?></b><span>changement<?= count($commits) > 1 ? 's' : '' ?> à appliquer</span><small>vérifié <?= e(date('d/m à H:i', (int) ($check['at'] ?? time()))) ?></small></div>
  <?php endif; ?>
</div>

<?php if (!empty($check['error'])): ?><p class="alert alert--error" style="margin:0">Vérification impossible : <?= e($check['error']) ?></p><?php endif; ?>

<div class="cols cols--wide">
  <section class="card">
    <div class="card__head"><h2 class="card__t"><?= e($title) ?></h2><span class="card__note"><?= e($repo) ?> · branche <?= e($branch) ?></span></div>
    <div class="card__body">
      <?php if ($newVersion && $commits): ?>
        <p class="small" style="margin:0">Changements depuis la version installée<?= count($commits) >= 60 ? ' (les 60 derniers)' : '' ?> :</p>
        <ul class="small" style="margin:0;padding-left:18px;max-height:340px;overflow:auto">
          <?php foreach ($commits as $c): ?><li><b><?= e($c['message'] ?: $sha($c['sha'])) ?></b> <span class="xs muted"><?= e($when($c['date'] ?? null)) ?> · <?= e($sha($c['sha'])) ?></span></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if ($differ === 0): ?>
        <p class="small" style="margin:0"><span class="ok">✓</span> Fichier par fichier, le code du serveur est identique à la version <?= e($sha($sync['sha'])) ?> de GitHub (<?= (int) $sync['total'] ?> fichiers comparés<?= !empty($sync['eol']) ? ', dont ' . (int) $sync['eol'] . ' qui ne diffèrent que par les fins de ligne, sans effet' : '' ?>).</p>
      <?php elseif ($differ): ?>
        <p class="small" style="margin:0"><b><?= (int) $differ ?> fichier<?= $differ > 1 ? 's' : '' ?> du serveur <?= $differ > 1 ? 'diffèrent' : 'diffère' ?> de la version <?= e($sha($sync['sha'])) ?> de GitHub</b> (sur <?= (int) $sync['total'] ?> comparés)<?= $newVersion ? '' : ' : envoi FTP incomplet ou fichier retouché sur le serveur' ?>. « <?= e($button) ?> » les remplace.</p>
        <details class="small"><summary>Voir <?= $differ > 1 ? 'les fichiers' : 'le fichier' ?></summary>
          <ul style="margin:6px 0 0;padding-left:18px;max-height:260px;overflow:auto">
            <?php foreach ((array) $sync['differ'] as $path => $st): ?><li><code><?= e((string) $path) ?></code> <span class="xs muted"><?= $st === 'absent' ? 'absent du serveur' : 'différent' ?></span></li><?php endforeach; ?>
            <?php if ($differ > count((array) $sync['differ'])): ?><li class="muted">… et <?= $differ - count((array) $sync['differ']) ?> autres</li><?php endif; ?>
          </ul>
        </details>
      <?php elseif (is_array($sync) && !empty($sync['error'])): ?>
        <p class="small muted" style="margin:0">Comparaison fichier par fichier impossible pour l’instant : <?= e($sync['error']) ?></p>
      <?php elseif ($available && !$commits): ?>
        <p class="small" style="margin:0">La version installée n’est pas connue (site mis en ligne par FTP) : la mise à jour installe la dernière version et ne remplace que les fichiers du code qui diffèrent.</p>
      <?php elseif (!$available): ?>
        <p class="small" style="margin:0"><span class="ok">✓</span> Le code du site correspond à la dernière version de GitHub.</p>
      <?php endif; ?>
      <?php if (!empty($sync['kept'])): ?><p class="xs muted" style="margin:0">Réglé à la main sur le serveur et gardé par les mises à jour : <?= e(implode(', ', (array) $sync['kept'])) ?>.</p><?php endif; ?>

      <div class="row" style="gap:10px">
        <?php if ($available): ?>
          <form method="post" action="/admin/mises-a-jour" data-confirm="<?= e($button) ?> ?|Le code est remplacé en quelques secondes (le site affiche « Mise à jour en cours » pendant la copie). Fiches, médias, réglages et comptes ne sont jamais touchés ; une sauvegarde permet de revenir en arrière.|<?= e($newVersion ? 'Appliquer' : 'Synchroniser') ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="appliquer">
            <button type="submit" class="btn btn--navy"><?= e($button) ?></button>
          </form>
        <?php endif; ?>
        <form method="post" action="/admin/mises-a-jour"><?= csrf_field() ?><input type="hidden" name="action" value="verifier"><button type="submit" class="btn">Vérifier maintenant</button></form>
      </div>
    </div>
  </section>
  <section class="card">
    <div class="card__head"><h2 class="card__t">Ce qu’une mise à jour change</h2></div>
    <div class="card__body small">
      <p style="margin:0"><b>Remplacé :</b> le code seulement (<code>app/</code>, <code>bin/</code>, <code>config/</code>, <code>scripts/</code>, <code>templates/</code>, <code>public/</code>), et seulement les fichiers qui ont changé.</p>
      <p style="margin:0"><b>Jamais touché :</b> les fiches, la médiathèque et les traductions (<code>data/</code>), les réglages, comptes, journaux et coûts IA (<code>storage/</code>), les photos et voix (<code>public/media/</code>).</p>
      <p style="margin:0"><b>Avec précaution :</b> les nouveaux libellés anglais de l’interface sont ajoutés sans toucher aux traductions existantes ; un <code>public/.htaccess</code> réglé à la main est gardé.</p>
      <p style="margin:0"><b>Ensuite :</b> les caches se vident et se refont seuls (sauf les relectures du correcteur, gardées). Avant chaque mise à jour, les fichiers remplacés sont sauvegardés : « Revenir à cette version » ci-dessous.</p>
      <p style="margin:0"><b>Synchronisation :</b> chaque vérification compare aussi le code du serveur, fichier par fichier, à GitHub (sans rien télécharger). Un site mis en ligne par FTP dont le code est identique voit sa version reconnue ; un fichier oublié ou retouché sur le serveur est signalé et remplacé en un clic.</p>
      <p style="margin:0">Vérification automatique toutes les 3 heures (tâche planifiée) ; le tableau de bord signale une nouvelle version ou un fichier qui diffère. Dépôt et branche : <a href="/admin/reglages?groupe=update">Réglages › Mises à jour</a>.</p>
    </div>
  </section>
</div>

<section class="card">
  <div class="card__head"><h2 class="card__t">Historique</h2><span class="card__note">10 dernières opérations</span></div>
  <?php if (!$history): ?>
    <div class="card__body"><p class="small muted" style="margin:0">Aucune mise à jour appliquée depuis cet écran pour l’instant.</p></div>
  <?php else: ?>
  <div class="table" style="border:0">
    <table>
      <thead><tr><th>Date</th><th>Par</th><th>Version</th><th>Fichiers</th><th>Note</th></tr></thead>
      <tbody>
        <?php foreach ($history as $h): ?>
          <tr>
            <td class="nowrap"><?= e($when($h['at'] ?? null)) ?></td>
            <td><?= e($h['by'] ?? '') ?></td>
            <td class="nowrap"><?= e($sha($h['from'] ?? null)) ?> → <b><?= e($sha($h['to'] ?? null)) ?></b></td>
            <td><?= (int) ($h['updated'] ?? 0) ?> remplacé(s)<?= !empty($h['deleted']) ? ', ' . (int) $h['deleted'] . ' supprimé(s)' : '' ?><?= !empty($h['merged']) ? ', ' . (int) $h['merged'] . ' libellé(s)' : '' ?></td>
            <td class="small"><?= e($h['message'] ?? '') ?><?= !empty($h['kept']) ? ' · gardé : ' . e(implode(', ', (array) $h['kept'])) : '' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<section class="card">
  <div class="card__head"><h2 class="card__t">Sauvegardes avant mise à jour</h2><span class="card__note">les 5 dernières</span></div>
  <?php if (!$backups): ?>
    <div class="card__body"><p class="small muted" style="margin:0">Aucune sauvegarde : elles sont créées au moment d’appliquer une mise à jour.</p></div>
  <?php else: ?>
  <div class="table" style="border:0">
    <table>
      <thead><tr><th>Créée</th><th>Avant la mise à jour vers</th><th>Fichiers gardés</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($backups as $b): $m = $b['meta']; ?>
          <tr>
            <td class="nowrap"><?= e($when($m['at'] ?? null)) ?></td>
            <td class="nowrap"><?= e($sha($m['to'] ?? null)) ?> <span class="xs muted">(version d’avant : <?= e(isset($m['from']['sha']) ? $sha($m['from']['sha']) : 'FTP') ?>)</span></td>
            <td><?= (int) $b['files'] ?></td>
            <td>
              <form method="post" action="/admin/mises-a-jour" data-confirm="Revenir à la version d’avant cette mise à jour ?|Les fichiers sauvegardés sont remis en place et ceux ajoutés par la mise à jour retirés.|Revenir en arrière">
                <?= csrf_field() ?><input type="hidden" name="action" value="restaurer"><input type="hidden" name="sauvegarde" value="<?= e($b['name']) ?>">
                <button type="submit" class="btn btn--sm btn--ghost">Revenir à cette version</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
