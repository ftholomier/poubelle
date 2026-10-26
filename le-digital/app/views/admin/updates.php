<?php
/** @var ?array $check @var ?array $installed @var bool $available @var array $history @var array $backups @var array $cfg @var array $env @var string $csrf */
use LD\Updater;

$ago = static function (int|string|null $t): string {
    $ts = is_int($t) ? $t : ($t ? strtotime((string) $t) : 0);
    if (!$ts) {
        return '';
    }
    $d = time() - $ts;
    return $d < 60 ? 'à l’instant' : ($d < 3600 ? 'il y a ' . intdiv($d, 60) . ' min' : ($d < 86400 ? 'il y a ' . intdiv($d, 3600) . ' h' : 'le ' . date('d/m/Y à H:i', $ts)));
};
$latest = $check['latest'] ?? null;
$differ = Updater::differing($check);
$newVersion = $latest && ($latest['sha'] ?? '') !== ($installed['sha'] ?? '');
$commits = $check['commits'] ?? [];
$sync = $check['sync'] ?? null;
$envOk = $env['curl'] && $env['zip'] && $env['writable'];
?>
<section class="card">
  <div class="card__head">
    <h2>Version du site</h2>
    <?php if ($cfg['repo'] !== ''): ?><button type="button" class="btn btn--ghost" data-maj-check>Vérifier maintenant</button><?php endif; ?>
  </div>

  <?php if (!$envOk): ?>
    <p class="alert alert--err">
      Le serveur ne permet pas encore les mises à jour en un clic :
      <?= !$env['curl'] ? 'extension PHP cURL absente. ' : '' ?><?= !$env['zip'] ? 'extension PHP Zip absente. ' : '' ?><?= !$env['writable'] ? 'les dossiers app/, public/ et storage/ doivent être modifiables par PHP.' : '' ?>
    </p>
  <?php endif; ?>

  <?php if ($cfg['repo'] === ''): ?>
    <p class="intro">Renseigne le dépôt GitHub du site dans les réglages ci-dessous : les nouvelles versions apparaîtront ici et s’installeront en un clic.</p>
  <?php else: ?>
    <div class="maj-versions">
      <div><span class="small muted">Installée sur le serveur</span><b><?= e(Updater::label($installed)) ?></b><?php if (!empty($installed['message'])): ?><small><?= e($installed['message']) ?></small><?php endif; ?></div>
      <div><span class="small muted">Dernière version sur GitHub</span><b><?= e($latest ? Updater::label($latest) : '—') ?></b><?php if (!empty($latest['message'])): ?><small><?= e($latest['message']) ?></small><?php endif; ?></div>
    </div>

    <?php if (!empty($check['error'])): ?>
      <p class="alert alert--err"><?= e($check['error']) ?></p>
    <?php elseif (!empty($sync['error'])): ?>
      <p class="alert alert--err">Comparaison impossible : <?= e($sync['error']) ?></p>
    <?php elseif ($available): ?>
      <div class="maj-box">
        <?php if ($newVersion): ?>
          <p class="maj-box__title"><span class="status status--new">Nouvelle version</span> <?= $installed ? count($commits) . ' changement' . (count($commits) > 1 ? 's' : '') . ' depuis la version installée' : 'Dernières modifications publiées sur GitHub' ?><?= $differ !== null ? ' · ' . $differ . ' fichier' . ($differ > 1 ? 's' : '') . ' à remplacer' : '' ?></p>
          <?php if ($commits): ?>
            <ul class="maj-commits">
              <?php foreach ($commits as $c): ?>
                <li>
                  <b><?= e($c['message']) ?></b> <small class="muted"><?= e(date('d/m/Y H:i', strtotime($c['date']))) ?> · <?= e(substr($c['sha'], 0, 7)) ?></small>
                  <?php if (!empty($c['details'])): ?><ul><?php foreach ($c['details'] as $d): ?><li><?= e($d) ?></li><?php endforeach; ?></ul><?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        <?php else: ?>
          <p class="maj-box__title"><span class="status status--progress">À synchroniser</span> <?= (int) $differ ?> fichier<?= $differ > 1 ? 's' : '' ?> du serveur diffère<?= $differ > 1 ? 'nt' : '' ?> de la version GitHub (fichier oublié ou modifié à la main).</p>
        <?php endif; ?>
        <?php if (!empty($sync['differ'])): ?>
          <details class="details"><summary>Fichiers concernés</summary>
            <ul class="maj-files small"><?php foreach ($sync['differ'] as $f => $st): ?><li><code><?= e($f) ?></code> <span class="muted"><?= $st === 'absent' ? 'nouveau' : 'modifié' ?></span></li><?php endforeach; ?></ul>
          </details>
        <?php endif; ?>
        <div class="actions">
          <button type="button" class="btn btn--red btn--lg" data-maj-apply<?= $envOk ? '' : ' disabled' ?>><?= $newVersion ? 'Mettre à jour maintenant' : 'Synchroniser avec GitHub' ?></button>
          <span class="small muted">Sauvegarde automatique avant la copie ; contenus, demandes, clés et images ne sont jamais touchés.</span>
        </div>
      </div>
    <?php elseif ($latest): ?>
      <p class="maj-ok">✓ Le site est à jour.</p>
    <?php endif; ?>

    <?php if (!empty($sync['kept'])): ?>
      <p class="small muted">Gardé<?= count($sync['kept']) > 1 ? 's' : '' ?> car modifié<?= count($sync['kept']) > 1 ? 's' : '' ?> sur le serveur : <?= implode(', ', array_map(static fn($f) => '<code>' . e($f) . '</code>', $sync['kept'])) ?>.</p>
    <?php endif; ?>
    <p class="small muted">
      <?= $check ? 'Dernière vérification ' . e($ago((int) ($check['at'] ?? 0))) . ' · ' : '' ?>Dépôt <code><?= e($cfg['repo']) ?></code>, branche <code><?= e($cfg['branch']) ?></code><?= $cfg['path'] !== '' ? ', dossier <code>' . e($cfg['path']) . '/</code>' : '' ?>
      <?= !empty($cfg['auto_check']) ? ' · vérification automatique toutes les 3 h (tâche planifiée)' : '' ?>
    </p>
  <?php endif; ?>
</section>

<?php if ($history): ?>
  <section class="card card--flush">
    <div class="card__head maj-pad"><h2>Historique</h2></div>
    <table class="table">
      <thead><tr><th>Date</th><th>Version</th><th class="hide-sm">Fichiers</th></tr></thead>
      <tbody>
        <?php foreach ($history as $h): ?>
          <tr>
            <td><small><?= e(date('d/m/Y H:i', strtotime((string) $h['at']))) ?><br><span class="muted"><?= e((string) ($h['by'] ?? '')) ?></span></small></td>
            <td><?= !empty($h['error']) ? '<span class="status status--new">Échec</span> ' : '' ?><?= e((string) ($h['message'] ?? '')) ?><?php if (!empty($h['to'])): ?> <small class="muted"><?= e(substr((string) $h['to'], 0, 7)) ?></small><?php endif; ?>
              <?php if (!empty($h['kept'])): ?><br><small class="muted">Gardés : <?= e(implode(', ', (array) $h['kept'])) ?></small><?php endif; ?></td>
            <td class="hide-sm"><small><?= (int) ($h['updated'] ?? 0) ?> remplacé<?= ($h['updated'] ?? 0) > 1 ? 's' : '' ?><?= !empty($h['deleted']) ? ', ' . (int) $h['deleted'] . ' supprimé' . ($h['deleted'] > 1 ? 's' : '') : '' ?></small></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
<?php endif; ?>

<?php if ($backups): ?>
  <section class="card">
    <div class="card__head"><h2>Sauvegardes</h2><span class="small muted">les <?= count($backups) ?> dernières</span></div>
    <p class="small">Chaque mise à jour sauvegarde d’abord les fichiers qu’elle remplace. En cas de souci, reviens à la version d’avant en un clic.</p>
    <ul class="maj-backups">
      <?php foreach ($backups as $b): $from = $b['meta']['from'] ?? null; ?>
        <li>
          <span><b><?= e(date('d/m/Y H:i', strtotime((string) ($b['meta']['at'] ?? 'now')))) ?></b> <small class="muted">avant la mise à jour vers <?= e(substr((string) ($b['meta']['to'] ?? ''), 0, 7)) ?> · <?= (int) $b['files'] ?> fichier<?= $b['files'] > 1 ? 's' : '' ?></small><br><small>Version d’avant : <?= e(Updater::label(is_array($from) ? $from : null)) ?></small></span>
          <button type="button" class="btn btn--ghost" data-maj-rollback="<?= e($b['name']) ?>">Revenir à cette version</button>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<form method="post" action="/admin/mises-a-jour/reglages" class="settings-form">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <section class="card">
    <div class="card__head"><h2>Réglages GitHub</h2></div>
    <div class="form form--cols">
      <label>Dépôt (propriétaire/nom)<input name="repo" value="<?= e($cfg['repo']) ?>" placeholder="ftholomier/poubelle" spellcheck="false"></label>
      <label>Branche<input name="branch" value="<?= e($cfg['branch']) ?>" placeholder="main" spellcheck="false"></label>
      <label>Dossier du site dans le dépôt<input name="path" value="<?= e($cfg['path']) ?>" placeholder="vide si le site est à la racine du dépôt" spellcheck="false"></label>
      <label>Jeton d’accès GitHub<input type="password" name="token" autocomplete="new-password" spellcheck="false" placeholder="<?= e($cfg['token'] !== '' ? 'Enregistré : ' . $cfg['token'] . ' (laisser vide pour garder)' : 'github_pat_…') ?>"></label>
      <label class="check"><input type="checkbox" name="auto_check" value="1"<?= !empty($cfg['auto_check']) ? ' checked' : '' ?>> Vérifier automatiquement (toutes les 3 h)</label>
      <?php if ($cfg['token'] !== ''): ?><label class="check small"><input type="checkbox" name="token_clear" value="1"> Supprimer le jeton</label><?php endif; ?>
    </div>
    <details class="details">
      <summary>Créer le jeton GitHub (2 minutes, lecture seule)</summary>
      <ol class="small steps">
        <li>Sur GitHub : <a href="https://github.com/settings/personal-access-tokens/new" target="_blank" rel="noopener"><i>Settings › Developer settings › Personal access tokens › Fine-grained tokens</i> › Generate new token</a>.</li>
        <li>Nom : « Site LE-DIGITAL », expiration : 1 an. <i>Repository access</i> : <b>Only select repositories</b> › le dépôt du site.</li>
        <li><i>Permissions › Repository permissions › Contents</i> : <b>Read-only</b> (rien d’autre).</li>
        <li>Génère le jeton, colle-le ici et enregistre. Il est chiffré sur le serveur ; il ne permet que de lire le code.</li>
      </ol>
    </details>
  </section>
  <div class="actions sticky-actions"><button class="btn btn--red" type="submit">Enregistrer les réglages</button></div>
</form>
