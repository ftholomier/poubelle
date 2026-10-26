<?php
/**
 * @var array $config @var array $locale @var array $distante
 * @var array $sauvegardes @var array $journal
 */
$shaLocal = (string) ($locale['sha'] ?? '');
$shaDistant = (string) ($distante['sha'] ?? '');
$aJour = $shaLocal !== '' && $shaDistant !== '' && $shaLocal === $shaDistant;
?>
<div class="topbar">
  <h1>Mise à jour</h1>
  <?php if (Updater::configure() && !empty($distante['ok'])): ?>
    <span class="badge" style="color:<?= $aJour ? '#35d07f' : '#e8a13a' ?>"><i></i><?= $aJour ? 'À jour' : 'Version disponible' ?></span>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="panel__head"><h2>Version</h2></div>
  <p class="panel__intro">
    Le code est récupéré directement depuis GitHub : plus besoin de transférer les fichiers un à un
    par FTP. Seuls les dossiers de code sont remplacés (<code>app</code>, <code>public</code>,
    <code>outils</code>). <strong>Le dossier <code>data</code> n’est jamais touché</strong> :
    contenu, réglages, candidatures et CV survivent à la mise à jour.
  </p>

  <div class="grid grid--2">
    <div>
      <h3 class="h4" style="margin-bottom:10px">Installée ici</h3>
      <?php if ($shaLocal === ''): ?>
        <p class="txt-attenue">Aucune mise à jour n’a encore été appliquée depuis cet écran.</p>
      <?php else: ?>
        <p style="margin:0 0 6px"><code><?= e(substr($shaLocal, 0, 7)) ?></code> — <?= e((string) ($locale['message'] ?? '')) ?></p>
        <p class="txt-mini">Installée le <?= e(fr_date((string) ($locale['installee_le'] ?? ''), true)) ?>
          depuis la branche <code><?= e((string) ($locale['branche'] ?? '')) ?></code></p>
      <?php endif; ?>
    </div>
    <div>
      <h3 class="h4" style="margin-bottom:10px">Disponible sur GitHub</h3>
      <?php if (!Updater::configure()): ?>
        <p class="txt-attenue">Renseignez le dépôt ci-dessous.</p>
      <?php elseif (empty($distante['ok'])): ?>
        <p style="color:#ffa9b3"><?= e((string) ($distante['error'] ?? 'Impossible de joindre GitHub.')) ?></p>
      <?php else: ?>
        <p style="margin:0 0 6px"><code><?= e(substr($shaDistant, 0, 7)) ?></code> — <?= e((string) $distante['message']) ?></p>
        <p class="txt-mini"><?= e((string) $distante['auteur']) ?>, le <?= e(fr_date((string) $distante['date'], true)) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <?php if (Updater::configure() && !empty($distante['ok'])): ?>
    <form method="post" style="margin-top:22px" data-confirmer="Remplacer le code du site par la dernière version de GitHub ? Une sauvegarde est créée avant.">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="apply">
      <button class="btn" type="submit">
        <?= icon('download') ?>
        <?= $aJour ? 'Réinstaller cette version' : 'Mettre à jour maintenant' ?>
      </button>
      <small class="help" style="display:block;margin-top:10px">
        Le site reste en ligne pendant l’opération, qui prend quelques secondes.
      </small>
    </form>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="panel__head"><h2>Dépôt suivi</h2></div>
  <form method="post">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="config">
    <div class="grid grid--2">
      <div class="field">
        <label for="repo">Dépôt GitHub</label>
        <small class="help">Au format <code>proprietaire/depot</code>.</small>
        <input class="input" id="repo" name="repo" placeholder="ftholomier/poubelle" value="<?= e((string) $config['repo']) ?>">
      </div>
      <div class="field">
        <label for="branch">Branche</label>
        <input class="input" id="branch" name="branch" placeholder="main" value="<?= e((string) $config['branch']) ?>">
      </div>
      <div class="field col-pleine">
        <label for="token">Jeton d’accès <span class="muted small">(obligatoire si le dépôt est privé)</span></label>
        <small class="help">
          À créer sur GitHub : <em>Settings → Developer settings → Personal access tokens</em>, avec le seul
          droit de lecture sur ce dépôt. Il est stocké dans <code>data/settings.json</code>, hors racine web,
          et n’est jamais transmis au navigateur.
        </small>
        <input class="input" id="token" name="token" type="password" autocomplete="off"
               placeholder="<?= $config['token'] !== '' ? '•••••••• (inchangé si laissé vide)' : 'github_pat_…' ?>" value="">
      </div>
    </div>
    <div class="row" style="gap:12px;margin-top:6px">
      <button class="btn btn--ghost" type="submit">Enregistrer le dépôt</button>
      <?php if ($config['token'] !== ''): ?>
        <button class="btn btn--ghost" type="submit" name="clear_token" value="1"
                data-confirmer="Effacer le jeton d’accès enregistré ?">Effacer le jeton</button>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php if ($sauvegardes): ?>
<div class="panel">
  <div class="panel__head"><h2>Sauvegardes</h2></div>
  <p class="panel__intro">
    Chaque mise à jour conserve la version remplacée. Les <?= (int) Updater::SAUVEGARDES_GARDEES ?> dernières
    sont gardées, les plus anciennes sont supprimées automatiquement.
  </p>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Sauvegarde</th><th>Créée le</th><th class="txt-droite">Action</th></tr></thead>
      <tbody>
        <?php foreach ($sauvegardes as $s): ?>
          <tr>
            <td><code><?= e($s['nom']) ?></code></td>
            <td class="txt-attenue nowrap"><?= e(fr_date(date('c', $s['date']), true)) ?></td>
            <td class="txt-droite">
              <form method="post" data-confirmer="Remettre en place cette version ? Le code actuel sera remplacé.">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="restore">
                <input type="hidden" name="sauvegarde" value="<?= e($s['nom']) ?>">
                <button class="btn btn--sm btn--ghost" type="submit">Restaurer</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($journal): ?>
<div class="panel">
  <div class="panel__head"><h2>Journal</h2></div>
  <ul style="list-style:none;padding:0;display:grid;gap:8px">
    <?php foreach ($journal as $ligne): ?>
      <li class="txt-mini"><?= e($ligne) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
