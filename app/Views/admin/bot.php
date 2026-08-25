<script>window.SI_BOT = { modelsUrl: <?= json_encode(url("admin/bot/modeles")) ?>, testUrl: <?= json_encode(url("admin/bot/test")) ?> };</script>
<?php
/** @var array $cfg @var array $docs @var array $chats */
$hasKey = trim((string) $cfg['api_key']) !== '';
$masked = $hasKey ? '••••••••••••' . substr((string) $cfg['api_key'], -4) : '';
$models = (array) $cfg['models'];
?>
<div class="topbar">
  <h1>Bot IA</h1>
  <div class="row">
    <span class="badge" style="color:<?= !empty($cfg['enabled']) && $hasKey ? '#35d07f' : '#8d99ae' ?>">
      <i></i><?= !empty($cfg['enabled']) && $hasKey ? 'Actif sur le site' : 'Inactif' ?>
    </span>
    <span class="badge"><?= nb($chunks) ?> fragments · <?= nb($chars) ?> caractères indexés</span>
  </div>
</div>

<form method="post" id="bot-form" data-dirty-guard>
  <?= Csrf::field() ?>

  <div class="editor" style="grid-template-columns:1fr 380px">
    <div>
      <!-- ------------------------------------------------ Connexion -->
      <div class="panel">
        <div class="panel__head">
          <h2>Connexion à Gemini</h2>
          <a class="btn btn--sm btn--ghost" href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">
            <?= icon('arrow-up-right') ?> Obtenir une clé
          </a>
        </div>

        <div class="field">
          <label for="api_key">Clé API Gemini</label>
          <small class="help">Stockée côté serveur dans <code>data/bot.json</code>, jamais transmise au navigateur des visiteurs. Laissez vide pour conserver la clé actuelle.</small>
          <input class="input" id="api_key" name="api_key" type="password" autocomplete="off" spellcheck="false"
                 placeholder="<?= $hasKey ? e($masked) : 'AIza…' ?>">
        </div>
        <?php if ($hasKey): ?>
          <label class="switch" style="margin-bottom:14px">
            <input type="checkbox" name="clear_key" value="1"><i aria-hidden="true"></i>
            <span>Supprimer la clé enregistrée</span>
          </label>
        <?php endif; ?>

        <div class="field">
          <label for="model">Modèle</label>
          <div class="row" style="gap:10px;align-items:stretch">
            <select class="select" id="model" name="model" style="flex:1">
              <?php if (!$models): ?>
                <option value="<?= e($cfg['model']) ?>"><?= e($cfg['model']) ?> (liste non chargée)</option>
              <?php else: foreach ($models as $m): ?>
                <option value="<?= e($m['id']) ?>" <?= $cfg['model'] === $m['id'] ? 'selected' : '' ?>>
                  <?= e($m['label']) ?> — <?= e(str_replace('models/', '', $m['id'])) ?><?= $m['input'] ? ' · ' . nb($m['input']) . ' jetons' : '' ?>
                </option>
              <?php endforeach; endif; ?>
            </select>
            <button class="btn btn--ghost" type="button" id="bot-refresh">Charger les modèles</button>
          </div>
          <small class="help" id="bot-models-info">
            <?= $models
                ? nb(count($models)) . ' modèle' . (count($models) > 1 ? 's' : '') . ' disponible' . (count($models) > 1 ? 's' : '') . ' · liste rafraîchie le ' . e(fr_date($cfg['models_fetched_at'] ?? '', true))
                : 'Renseignez la clé puis cliquez sur « Charger les modèles » : la liste est récupérée en direct auprès de Google.' ?>
          </small>
        </div>

        <label class="switch">
          <input type="checkbox" name="enabled" value="1" <?= !empty($cfg['enabled']) ? 'checked' : '' ?>><i aria-hidden="true"></i>
          <span>Afficher l’assistant sur le site public</span>
        </label>
      </div>

      <!-- ---------------------------------------------- Personnalité -->
      <div class="panel">
        <div class="panel__head"><h2>Personnalité</h2></div>
        <div class="grid grid--2">
          <div class="field">
            <label for="name">Prénom de l’assistant</label>
            <input class="input" id="name" name="name" value="<?= e($cfg['name']) ?>">
          </div>
          <div class="field">
            <label for="role">Rôle affiché</label>
            <input class="input" id="role" name="role" value="<?= e($cfg['role']) ?>">
          </div>
        </div>
        <div class="field">
          <label for="greeting">Message d’accueil</label>
          <textarea class="textarea" id="greeting" name="greeting" rows="2"><?= e($cfg['greeting']) ?></textarea>
        </div>
        <div class="field">
          <label for="suggestions">Questions suggérées</label>
          <small class="help">Une par ligne. Affichées sous le message d’accueil pour amorcer la conversation.</small>
          <textarea class="textarea" id="suggestions" name="suggestions" rows="4"><?= e(implode("\n", (array) $cfg['suggestions'])) ?></textarea>
        </div>
        <div class="field">
          <label for="persona">Consignes données au modèle</label>
          <small class="help"><code>{name}</code> et <code>{role}</code> sont remplacés automatiquement. C’est ici que se règle le ton et l’interdiction d’inventer.</small>
          <textarea class="textarea textarea--code" id="persona" name="persona" rows="12"><?= e($cfg['persona']) ?></textarea>
        </div>
        <div class="grid grid--2">
          <div class="field">
            <label for="temperature">Température (0 = factuel, 1 = créatif)</label>
            <input class="input" id="temperature" name="temperature" type="number" step="0.05" min="0" max="2" value="<?= e((string) $cfg['temperature']) ?>">
          </div>
          <div class="field">
            <label for="max_tokens">Longueur maximale de réponse (jetons)</label>
            <input class="input" id="max_tokens" name="max_tokens" type="number" step="50" min="64" max="4096" value="<?= e((string) $cfg['max_tokens']) ?>">
          </div>
        </div>
      </div>

      <!-- -------------------------------------- Base de connaissances -->
      <div class="panel">
        <div class="panel__head"><h2>Base de connaissances</h2></div>
        <p style="font-size:.86rem;color:var(--muted);margin-bottom:16px">
          À chaque question, les passages les plus proches sont sélectionnés parmi ces sources et transmis au modèle
          comme seule matière autorisée. Décochez une source pour l’exclure sans la supprimer.
        </p>
        <?php foreach ([
            'content' => ['Contenu du site', 'Toutes les sections éditables : accroches, avantages, métier, FAQ, parcours…'],
            'posts' => ['Actualités publiées', 'Les analyses de marché publiées dans la rubrique Actualités.'],
            'company' => ['Coordonnées et mentions légales', 'Adresse, téléphone, SIRET, délai de réponse annoncé.'],
            'documents' => ['Documents déposés', 'Les fichiers listés ci-dessous.'],
            'notes' => ['Informations complémentaires', 'Le texte libre saisi plus bas.'],
        ] as $key => [$label, $help]): ?>
          <label class="switch">
            <input type="checkbox" name="sources[<?= e($key) ?>]" value="1" <?= !empty($cfg['sources'][$key]) ? 'checked' : '' ?>><i aria-hidden="true"></i>
            <span><strong><?= e($label) ?></strong> — <span style="color:var(--muted)"><?= e($help) ?></span></span>
          </label>
        <?php endforeach; ?>

        <div class="field" style="margin-top:20px">
          <label for="notes">Informations complémentaires</label>
          <small class="help">Tout ce que le bot doit savoir et qui ne figure pas sur le site : barème réel, réponses types, secteurs ouverts, éléments à ne surtout pas dire…</small>
          <textarea class="textarea textarea--code" id="notes" name="notes" rows="12" placeholder="Ex. : Les secteurs de Maîche et Morteau sont pourvus jusqu'en septembre.&#10;Ne jamais communiquer de taux de commission par écrit ; renvoyer vers le rendez-vous stratégique."><?= e($cfg['notes']) ?></textarea>
        </div>
      </div>
    </div>

    <!-- ------------------------------------------------ Colonne droite -->
    <div>
      <div class="panel">
        <div class="panel__head"><h2>Enregistrer</h2></div>
        <button class="btn" style="width:100%" type="submit">Enregistrer la configuration</button>
        <p style="font-size:.8rem;color:var(--muted);margin-top:12px">
          Les documents s’ajoutent et se suppriment immédiatement, indépendamment de ce bouton.
        </p>
      </div>

      <div class="panel">
        <div class="panel__head"><h2>Console de test</h2></div>
        <div id="bot-console" class="bot-console" aria-live="polite">
          <p class="bot-console__empty">Posez une question pour vérifier ce que répond l’assistant avec la configuration enregistrée.</p>
        </div>
        <div class="row" style="gap:8px;margin-top:12px">
          <input class="input" id="bot-q" placeholder="Faut-il un diplôme ?" style="flex:1">
          <button class="btn btn--sm" type="button" id="bot-send">Tester</button>
        </div>
        <p style="font-size:.78rem;color:var(--muted);margin-top:10px">
          Le test utilise la configuration <strong>déjà enregistrée</strong> : pensez à sauvegarder avant.
        </p>
      </div>
    </div>
  </div>
</form>

<!-- ------------------------------------------------------- Documents -->
<div class="panel">
  <div class="panel__head">
    <h2>Documents</h2>
    <span class="badge"><?= count($docs) ?> fichier<?= count($docs) > 1 ? 's' : '' ?></span>
  </div>

  <form class="row" method="post" action="<?= e(url('admin/bot/documents')) ?>" enctype="multipart/form-data" style="margin-bottom:18px">
    <?= Csrf::field() ?>
    <input class="input" type="file" name="document" accept=".txt,.md,.csv,.html,.htm,.json,.docx,.pdf" style="max-width:340px" required>
    <button class="btn btn--sm" type="submit">Ajouter à la base</button>
    <span style="font-size:.82rem;color:var(--muted)">
      TXT, MD, CSV, HTML, JSON, DOCX, PDF — 8 Mo maximum. Le texte est extrait à l’ajout ; un PDF scanné ne donnera rien.
    </span>
  </form>

  <?php if (!$docs): ?>
    <div class="empty"><b>Aucun document</b>Plaquette, script d’entretien, grille de rémunération, FAQ interne…</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Document</th><th>Format</th><th>Texte extrait</th><th>Aperçu</th><th>Ajouté le</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($docs as $d): ?>
            <tr>
              <td><strong><?= e($d['name'] ?? '') ?></strong></td>
              <td><span class="badge"><?= e(strtoupper((string) ($d['ext'] ?? ''))) ?></span></td>
              <td style="color:var(--muted);white-space:nowrap"><?= nb($d['chars'] ?? 0) ?> car.</td>
              <td style="color:var(--muted);max-width:340px"><?= e(mb_substr((string) ($d['preview'] ?? ''), 0, 120)) ?>…</td>
              <td style="color:var(--muted);white-space:nowrap"><?= e(fr_date($d['created_at'] ?? '')) ?></td>
              <td style="text-align:right">
                <form method="post" action="<?= e(url('admin/bot/documents/' . ($d['id'] ?? '') . '/supprimer')) ?>" onsubmit="return confirm('Retirer ce document de la base de connaissances ?')">
                  <?= Csrf::field() ?>
                  <button class="btn btn--sm btn--danger" type="submit">Retirer</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- --------------------------------------------------- Conversations -->
<div class="panel">
  <div class="panel__head"><h2>Dernières conversations</h2><span class="badge">200 derniers échanges conservés</span></div>
  <?php if (!$chats): ?>
    <div class="empty"><b>Aucun échange</b>Les questions posées sur le site apparaîtront ici.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Question</th><th>Réponse</th><th>Origine</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($chats as $c): ?>
            <tr>
              <td style="max-width:280px"><?= e(mb_substr((string) ($c['question'] ?? ''), 0, 160)) ?></td>
              <td style="color:var(--muted);max-width:420px">
                <?php if (empty($c['ok'])): ?><span class="badge" style="color:#ff8290"><i></i>échec</span> <?php endif; ?>
                <?= e(mb_substr((string) ($c['answer'] ?? ''), 0, 200)) ?>…
              </td>
              <td><span class="badge"><?= e($c['origin'] ?? '') ?></span></td>
              <td style="color:var(--muted);white-space:nowrap"><?= e(fr_date($c['created_at'] ?? '', true)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
