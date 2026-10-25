<?php
/** @var array $providers @var array $cfg @var array $usage @var array $features @var string $csrf */
$models = [];
foreach ($providers as $k => $p) {
    $models[$k] = array_map(static fn($m) => ['id' => $m['id'], 'name' => $m['name']], $p['models']);
}
$labels = array_map(static fn($p) => $p['label'], $providers);
$withKey = array_keys(array_filter($providers, static fn($p) => $p['has_key']));
$promptInfo = [
    'chat_system'   => ['Assistant du site — consignes (system prompt)', ''],
    'brief'         => ['Brief transmis à Fred', '{conversation}'],
    'voice_summary' => ['Résumé des vocaux', '{transcript}'],
    'devis'         => ['Démo devis', '{prompt}'],
    'analyse_system' => ['Analyse express — consignes', ''],
    'analyse'       => ['Analyse express — demande', '{url} {job} {pain} {site}'],
    'explain'       => ['« Explique-moi comme à un débutant »', '{title} {text}'],
];
$ago = static function (?int $ts): string {
    if (!$ts) {
        return '';
    }
    $d = time() - $ts;
    return $d < 3600 ? 'il y a ' . max(1, intdiv($d, 60)) . ' min' : ($d < 86400 ? 'il y a ' . intdiv($d, 3600) . ' h' : 'le ' . date('d/m/Y', $ts));
};
?>
<script type="application/json" id="ai-data"><?= json_script(['models' => $models, 'labels' => $labels, 'withKey' => $withKey]) ?></script>

<section class="card">
  <div class="card__head"><h2>Clés API des fournisseurs d’IA</h2></div>
  <p class="small">Colle ta clé : elle est vérifiée immédiatement auprès du fournisseur, puis la <b>liste de ses modèles est chargée en direct</b>. Les clés sont chiffrées sur le serveur et ne sont jamais renvoyées au navigateur.</p>
  <div class="providers">
    <?php foreach ($providers as $key => $p): ?>
      <div class="prov<?= $p['has_key'] ? ' is-on' : '' ?>" data-provider="<?= e($key) ?>">
        <div class="prov__head">
          <b><?= e($p['label']) ?></b>
          <a href="<?= e($p['console']) ?>" target="_blank" rel="noopener" class="small">Obtenir une clé ↗</a>
        </div>
        <p class="prov__status" data-status>
          <?php if ($p['has_key']): ?>
            <span class="badge badge--ok">Connectée</span> <code><?= e($p['masked']) ?></code> · <span data-count><?= count($p['models']) ?></span> modèles<?= $p['fetched_at'] ? ' · ' . e($ago((int) $p['fetched_at'])) : '' ?>
          <?php else: ?>
            <span class="badge">Non configurée</span>
          <?php endif; ?>
        </p>
        <div class="prov__form">
          <input type="password" autocomplete="off" spellcheck="false" placeholder="<?= e($p['has_key'] ? 'Nouvelle clé (facultatif)' : ($p['hint'] ?: 'Clé API')) ?>" data-key aria-label="Clé API <?= e($p['label']) ?>">
          <button type="button" class="btn btn--dark" data-save-key>Enregistrer</button>
        </div>
        <div class="prov__acts"<?= $p['has_key'] ? '' : ' hidden' ?> data-acts>
          <button type="button" class="link" data-refresh>Actualiser la liste des modèles</button>
          <button type="button" class="link-danger" data-del-key>Supprimer la clé</button>
        </div>
        <p class="prov__msg small" data-msg hidden></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<form method="post" class="ai-form">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <section class="card">
    <div class="card__head"><h2>Modèle utilisé par le site</h2></div>
    <div class="form form--cols">
      <label class="check span2"><input type="checkbox" name="enabled" value="1"<?= !empty($cfg['enabled']) ? ' checked' : '' ?>> IA activée sur le site (assistant, démos, résumés). Sans IA, le site affiche des réponses de secours.</label>
      <label>Fournisseur par défaut
        <select name="default_provider" data-prov-select data-target="default_model">
          <option value="">— Choisir —</option>
          <?php foreach ($providers as $key => $p): ?><option value="<?= e($key) ?>"<?= $cfg['default_provider'] === $key ? ' selected' : '' ?><?= $p['has_key'] ? '' : ' disabled' ?>><?= e($p['label']) ?><?= $p['has_key'] ? '' : ' (pas de clé)' ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Modèle par défaut
        <span class="model-pick"><input type="search" placeholder="Filtrer les modèles…" data-model-filter="default_model" aria-label="Filtrer les modèles"><select name="default_model" data-model-select="default_model" data-value="<?= e($cfg['default_model']) ?>"></select></span>
      </label>
      <label>Effort de réflexion (modèles Claude récents)
        <select name="anthropic_effort">
          <?php foreach (['low' => 'Faible — réponses rapides (recommandé pour un site)', 'medium' => 'Moyen', 'high' => 'Élevé — plus lent, plus approfondi', 'none' => 'Valeur par défaut du modèle'] as $v => $l): ?><option value="<?= $v ?>"<?= ($cfg['anthropic_effort'] ?? 'low') === $v ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Plafond d’appels IA par jour (0 = illimité)<input type="number" name="daily_cap" min="0" value="<?= (int) $cfg['daily_cap'] ?>"></label>
    </div>
  </section>

  <section class="card">
    <div class="card__head"><h2>Réglages par fonctionnalité</h2></div>
    <p class="small muted">Laisse « Par défaut » pour utiliser le modèle ci-dessus, ou choisis un modèle plus rapide/moins cher pour une fonctionnalité précise.</p>
    <div class="feat-table">
      <?php foreach ($features as $f => $def): $fc = $cfg['features'][$f] ?? []; ?>
        <div class="feat">
          <label class="check feat__name"><input type="checkbox" name="features[<?= $f ?>][enabled]" value="1"<?= !isset($fc['enabled']) || $fc['enabled'] ? ' checked' : '' ?>> <?= e($def['label']) ?></label>
          <select name="features[<?= $f ?>][provider]" data-prov-select data-target="f_<?= $f ?>" aria-label="Fournisseur">
            <option value="">Par défaut</option>
            <?php foreach ($providers as $key => $p): ?><option value="<?= e($key) ?>"<?= ($fc['provider'] ?? '') === $key ? ' selected' : '' ?><?= $p['has_key'] ? '' : ' disabled' ?>><?= e($p['label']) ?></option><?php endforeach; ?>
          </select>
          <select name="features[<?= $f ?>][model]" data-model-select="f_<?= $f ?>" data-value="<?= e($fc['model'] ?? '') ?>" aria-label="Modèle"></select>
          <label class="feat__tok" title="Nombre maximal de jetons de réponse">max <input type="number" name="features[<?= $f ?>][max_tokens]" min="100" max="64000" value="<?= (int) ($fc['max_tokens'] ?? $def['max_tokens']) ?>"></label>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <div class="card__head"><h2>Transcription des vocaux (facultatif)</h2></div>
    <p class="small muted">Chrome, Edge et Safari transcrivent déjà le vocal dans le navigateur. Active cette option pour transcrire aussi les vocaux enregistrés ailleurs (Firefox…), via l’API audio d’OpenAI (clé OpenAI requise).</p>
    <div class="form form--cols">
      <label class="check"><input type="checkbox" name="transcription_enabled" value="1"<?= !empty($cfg['transcription']['enabled']) ? ' checked' : '' ?>> Transcrire les vocaux côté serveur</label>
      <label>Modèle de transcription<input name="transcription_model" list="tr-models" value="<?= e($cfg['transcription']['model'] ?? 'whisper-1') ?>"></label>
      <datalist id="tr-models"><option value="whisper-1"><option value="gpt-4o-mini-transcribe"><option value="gpt-4o-transcribe"></datalist>
    </div>
  </section>

  <section class="card">
    <div class="card__head"><h2>Consignes données à l’IA (prompts)</h2></div>
    <p class="small muted">Le ton, les règles (jamais de prix, tutoiement…) et le format attendu. Les variables entre accolades sont remplacées automatiquement.</p>
    <?php foreach ($promptInfo as $k => [$label, $vars]): ?>
      <details class="details">
        <summary><?= e($label) ?><?= $vars ? ' <small class="muted">' . e($vars) . '</small>' : '' ?></summary>
        <textarea name="prompts[<?= e($k) ?>]" rows="<?= $k === 'chat_system' ? 12 : 5 ?>" class="mono"><?= e($cfg['prompts'][$k] ?? '') ?></textarea>
      </details>
    <?php endforeach; ?>
    <label class="check small"><input type="checkbox" name="reset_prompts" value="1"> Restaurer les consignes d’origine</label>
  </section>

  <div class="savebar"><span class="savebar__state">Réglages IA</span><button class="btn btn--red" type="submit">Enregistrer les réglages IA</button></div>
</form>

<section class="card">
  <div class="card__head"><h2>Tester un modèle</h2></div>
  <div class="form form--cols" data-ai-test>
    <label>Fournisseur
      <select data-prov-select data-target="test_model" data-test-provider>
        <?php foreach ($providers as $key => $p): ?><option value="<?= e($key) ?>"<?= $cfg['default_provider'] === $key ? ' selected' : '' ?><?= $p['has_key'] ? '' : ' disabled' ?>><?= e($p['label']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Modèle<select data-model-select="test_model" data-value="<?= e($cfg['default_model']) ?>" data-test-model></select></label>
    <label class="span2">Message de test<input data-test-prompt value="Je perds du temps sur mes devis, tu peux m’aider ?"></label>
    <div class="span2"><button type="button" class="btn btn--dark" data-test-go>Envoyer</button></div>
    <div class="span2 test-out" data-test-out hidden></div>
  </div>
</section>

<section class="card">
  <div class="card__head"><h2>Consommation (14 derniers jours)</h2></div>
  <?php if (!$usage): ?>
    <p class="muted">Aucun appel pour l’instant.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Jour</th><th>Appels</th><th>Erreurs</th><th class="hide-sm">Jetons envoyés</th><th class="hide-sm">Jetons reçus</th></tr></thead>
      <tbody>
        <?php foreach (array_reverse($usage, true) as $day => $u): ?>
          <tr><td><?= e(date('d/m', strtotime($day))) ?></td><td><?= (int) $u['calls'] ?></td><td><?= (int) $u['errors'] ?></td><td class="hide-sm"><?= number_format((int) $u['in'], 0, ',', ' ') ?></td><td class="hide-sm"><?= number_format((int) $u['out'], 0, ',', ' ') ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  <p class="muted small">Le détail de chaque appel (durée, erreurs) est consigné dans <code>storage/logs/ai.log</code>.</p>
</section>
