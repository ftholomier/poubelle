<?php
use App\Controllers\Admin\AiController;
use App\Core\Url;
use App\Services\Chart;
/** @var array $ai @var bool $configured @var array $usage @var array $today */
?>
<div class="adm-head"><div><h1>Intelligence <span class="serif">artificielle</span></h1><p>Google Gemini, activable et désactivable à tout moment, rôle par rôle, avec un budget quotidien.</p></div><a class="btn btn-sm" href="<?= e(Url::admin('ia/conversations')) ?>"><?= icon('chat', 16) ?> Conversations (<?= nf($chats) ?>)</a></div>
<?php if (!$configured): ?><div class="alert alert-warning mb-2"><?= icon('alert', 18) ?><div>Aucune clé API Gemini : ajoutez-la dans <a href="<?= e(Url::admin('reglages')) ?>">Configuration</a> (GEMINI_API_KEY). Elle se crée gratuitement sur aistudio.google.com.</div></div><?php endif; ?>
<div class="kpis mb-2">
  <div class="kpi"><span>Appels aujourd'hui</span><b><?= nf((int) $today['calls']) ?></b><span class="small">/ <?= nf((int) ($ai['daily_limit'] ?? 1500)) ?> autorisés</span></div>
  <div class="kpi"><span>Jetons consommés aujourd'hui</span><b><?= nf((int) $today['in'] + (int) $today['out']) ?></b></div>
  <div class="kpi<?= $today['errors'] ? ' alert' : '' ?>"><span>Erreurs aujourd'hui</span><b><?= nf((int) $today['errors']) ?></b></div>
  <div class="kpi"><span>Modèles</span><b style="font-size:16px"><?= e($model) ?></b><span class="small"><?= e($fast) ?></span></div>
</div>
<div class="adm-cols">
  <form class="form" method="post" action="<?= e(Url::admin('ia')) ?>">
    <?= csrf_field() ?>
    <div class="box">
      <label class="switch"><input type="checkbox" name="enabled" value="1"<?= !empty($ai['enabled']) ? ' checked' : '' ?>> <strong>IA activée</strong> (interrupteur général)</label>
      <div class="stack mt-2">
        <?php foreach (AiController::ROLES as $k => [$l, $d]): ?><label class="check"><input type="checkbox" name="roles[<?= $k ?>]" value="1"<?= !empty($ai['roles'][$k]) ? ' checked' : '' ?>> <span><strong><?= e($l) ?></strong><br><span class="small muted"><?= e($d) ?></span></span></label><?php endforeach; ?>
      </div>
      <div class="form-grid mt-2">
        <div class="field"><label for="ai-t">Créativité (température)</label><input id="ai-t" type="number" step="0.1" min="0" max="1.5" name="temperature" value="<?= e((string) ($ai['temperature'] ?? 0.5)) ?>"></div>
        <div class="field"><label for="ai-l">Limite d'appels par jour</label><input id="ai-l" type="number" min="10" name="daily_limit" value="<?= (int) ($ai['daily_limit'] ?? 1500) ?>"><span class="hint">Au-delà, l'IA se met en pause jusqu'à minuit (protection du budget).</span></div>
      </div>
    </div>
    <div class="box">
      <h2>Assistant des visiteurs</h2>
      <div class="form-grid"><div class="field"><label for="ai-n">Nom</label><input id="ai-n" type="text" name="assistant_name" maxlength="30" value="<?= e((string) ($ai['assistant_name'] ?? 'Confetti')) ?>"></div></div>
      <div class="field"><label for="ai-g">Message d'accueil</label><input id="ai-g" type="text" name="assistant_greeting" maxlength="300" value="<?= e((string) ($ai['assistant_greeting'] ?? '')) ?>"></div>
      <div class="field"><label for="ai-p">Consignes complémentaires</label><textarea id="ai-p" name="assistant_prompt" rows="5" placeholder="Ex. : mettez en avant les demandes de devis groupées ; ne parlez pas de prix précis…"><?= e((string) ($ai['assistant_prompt'] ?? '')) ?></textarea><span class="hint">Ajoutées aux règles de base (l'assistant n'invente jamais de pro ni de prix et cherche dans l'annuaire réel).</span></div>
    </div>
    <div class="form-actions"><button class="btn btn-coral" type="submit">Enregistrer</button></div>
  </form>
  <aside>
    <div class="box">
      <h2>Essayer</h2>
      <div class="field"><label for="pg-prompt">Message</label><textarea id="pg-prompt" name="prompt" rows="4" placeholder="Je cherche un DJ pour un mariage près de Lyon en juin"></textarea></div>
      <div class="field"><label for="pg-mode">Mode</label><select id="pg-mode" name="mode"><option value="assistant">Comme l'assistant des visiteurs</option><option value="raw">Gemini brut</option></select></div>
      <button type="button" class="btn btn-sm btn-ink" data-ajax-action="<?= e(Url::admin('ia/test')) ?>" data-fields="#pg-prompt,#pg-mode" data-output="#pg-out">Envoyer</button>
      <div id="pg-out" class="mt-2"></div>
    </div>
    <div class="box" data-runner="<?= e(Url::admin('ia/lot')) ?>">
      <h2>Reclasser tous les pros en ligne</h2>
      <p class="small muted">L'IA relit chaque fiche publiée et propose ses métiers (environ 1 appel par fiche). <?= nf($unclassified) ?> fiche(s) n'ont que la catégorie par défaut.</p>
      <ul class="steps-run"><li data-step="classify"><span class="st">1</span><span>Classement<br><small></small></span></li></ul>
      <div class="progress mt-1"><i></i></div>
      <button type="button" class="btn btn-sm mt-1" data-runner-start data-confirm-text="Lancer le classement de toutes les fiches en ligne ?">Lancer</button>
      <pre class="log mt-1" data-runner-log style="max-height:160px"></pre>
    </div>
    <div class="box"><h2>Utilisation (14 jours)</h2><?= Chart::bars(array_map(static fn ($u) => (int) $u['calls'], $usage), ['height' => 160, 'color' => '#c8f560']) ?></div>
  </aside>
</div>
