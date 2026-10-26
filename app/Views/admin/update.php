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
        <p style="color:#ffc79a">
          <?= $config['repo'] === ''
              ? 'Le champ « Dépôt GitHub » est vide : renseignez-le ci-dessous.'
              : 'Le dépôt « ' . e((string) $config['repo']) . ' » n’est pas au format attendu (proprietaire/depot).' ?>
        </p>
      <?php elseif (empty($distante['ok'])): ?>
        <p style="color:#ffa9b3"><?= e((string) ($distante['error'] ?? 'Impossible de joindre GitHub.')) ?></p>
      <?php else: ?>
        <p style="margin:0 0 6px"><code><?= e(substr($shaDistant, 0, 7)) ?></code> — <?= e((string) $distante['message']) ?></p>
        <p class="txt-mini"><?= e((string) $distante['auteur']) ?>, le <?= e(fr_date((string) $distante['date'], true)) ?></p>
        <?php if (!empty($distante['verifiee_le'])): ?>
          <p class="txt-mini">Vérifié le <?= e(fr_date((string) $distante['verifiee_le'], true)) ?></p>
        <?php endif; ?>
        <?php if (!empty($distante['avertissement'])): ?>
          <p class="txt-mini" style="color:#ffc79a">
            Information datée : la dernière tentative a échoué (<?= e((string) $distante['avertissement']) ?>).
          </p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <p class="txt-mini" style="margin-top:18px;padding-top:14px;border-top:1px solid var(--line)">
    Témoin de déploiement : <strong><?= e(Updater::TEMOIN) ?></strong>
    <br>Cette ligne change à chaque version : c'est le moyen le plus simple de vérifier
    qu'une mise à jour a bien été appliquée.
  </p>

  <?php if (Updater::configure()): ?>
    <?php // Le bouton de vérification est le seul commun aux deux états. ?>
    <?php
    $boutonVerifier = '<form method="post">' . Csrf::field()
        . '<input type="hidden" name="action" value="check">'
        . '<button class="btn btn--ghost" type="submit">' . icon('sparkle') . ' Vérifier maintenant</button>'
        . '</form>';
    ?>

    <?php if ($aJour): ?>
      <?php // Rien à installer : proposer une réinstallation ne ferait que
            // semer le doute sur un site qui va bien. ?>
      <div class="maj-etat maj-etat--ok">
        <span class="maj-etat__icone"><?= icon('check-circle') ?></span>
        <div>
          <b>Votre site est à jour.</b>
          <p>Le code installé correspond au dernier commit de la branche suivie<?php
            if (!empty($distante['verifiee_le'])): ?>, vérifié le <?= e(fr_date((string) $distante['verifiee_le'], true)) ?><?php
            endif; ?>. Rien à faire.</p>
        </div>
        <?= $boutonVerifier ?>
      </div>

    <?php elseif (!empty($distante['ok'])): ?>
      <div class="maj-etat maj-etat--maj">
        <span class="maj-etat__icone"><?= icon('download') ?></span>
        <div>
          <b>Une nouvelle version est disponible.</b>
          <p>
            Le site reste en ligne pendant l’opération, qui prend quelques secondes.
            La version remplacée est sauvegardée et restaurable en un clic.
          </p>
        </div>
        <div class="row" style="gap:12px;flex-wrap:wrap">
          <form method="post" data-confirmer="Remplacer le code du site par la dernière version de GitHub ? Une sauvegarde est créée avant.">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="apply">
            <button class="btn" type="submit"><?= icon('download') ?> Mettre à jour maintenant</button>
          </form>
          <?= $boutonVerifier ?>
        </div>
      </div>

    <?php else: ?>
      <div class="row" style="margin-top:22px"><?= $boutonVerifier ?></div>
    <?php endif; ?>

    <small class="help" style="display:block;margin-top:12px">
      La version disponible est relue au plus toutes les dix minutes ; le bouton force la vérification.
    </small>
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
        <input class="input" id="repo" name="repo" placeholder="proprietaire/depot" required
               value="<?= e((string) $config['repo']) ?>">
      </div>
      <div class="field">
        <label for="branch">Branche</label>
        <input class="input" id="branch" name="branch" placeholder="main" required
               value="<?= e((string) $config['branch']) ?>">
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
    <?php if ($config['token'] !== '' && !empty($distante['ok'])): ?>
      <p class="txt-mini" style="color:#ffc79a">
        Un jeton est enregistré. Si le dépôt est public, il est inutile : vous pouvez l’effacer.
      </p>
    <?php endif; ?>

    <div class="row" style="gap:12px;margin-top:6px">
      <button class="btn" type="submit">Enregistrer le dépôt</button>
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
