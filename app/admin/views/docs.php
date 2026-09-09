<?php
/** Assistant IA : documents indexés, prompt système, questions sans réponse. */

use App\Admin;
use App\Ai\Docs;
use App\Ai\Gemini;
use App\Config;
use App\Csrf;
use App\Router;
use App\Text;
use App\View;

echo View::admin('_layout_start', get_defined_vars());

$stateColors = ['indexed' => '#12B39A', 'pending' => '#FFD100', 'error' => '#EDE5D5'];
$stateLabels = ['indexed' => 'Indexé', 'pending' => 'En attente', 'error' => 'Illisible'];
?>
<div class="screen">
  <div class="panels">
    <section class="panel">
      <div class="panel__head">
        <h2>Documents indexés</h2>
        <span class="panel__file">storage/docs · hors racine web</span>
      </div>
      <div class="panel__scroll">
        <?php if ($docs === []): ?>
          <div class="panel__body muted">Aucun document. L'assistant s'appuie pour l'instant sur les pages du site et le catalogue.</div>
        <?php else: ?>
          <?php foreach ($docs as $doc):
              $state = (string) ($doc['state'] ?? 'pending'); ?>
            <div class="row row--docs">
              <div>
                <div class="row__title"><?= Text::e((string) ($doc['name'] ?? '')) ?></div>
                <div class="row__sub">
                  <?= Text::e(strtoupper((string) ($doc['ext'] ?? ''))) ?> ·
                  <?= Text::e(Docs::humanSize((int) ($doc['bytes'] ?? 0))) ?> ·
                  <?= Text::e(Admin::humanDate((string) ($doc['at'] ?? ''))) ?> ·
                  <?= !empty($doc['public']) ? 'citable dans les réponses' : 'interne' ?>
                </div>
              </div>
              <span class="row__value"><?= (int) ($doc['chunks'] ?? 0) ?> extraits</span>
              <span class="badge" style="background:<?= Text::e($stateColors[$state] ?? '#EDE5D5') ?>"><?= Text::e($stateLabels[$state] ?? $state) ?></span>
              <div class="row__actions">
                <form method="post" action="<?= Text::e(Router::adminUrl()) ?>" data-confirm="Supprimer ce document et le retirer de l'index ?">
                  <?= Csrf::field('admin') ?>
                  <input type="hidden" name="action" value="doc-delete">
                  <input type="hidden" name="id" value="<?= Text::e((string) ($doc['id'] ?? '')) ?>">
                  <button class="btn btn--sm btn--danger" type="submit">Supprimer</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <div class="panel__body">
          <form method="post" action="<?= Text::e(Router::adminUrl()) ?>" enctype="multipart/form-data">
            <?= Csrf::field('admin') ?>
            <input type="hidden" name="action" value="doc-upload">
            <div class="dropzone">
              <div class="dropzone__title">Déposez un PDF, DOCX ou TXT</div>
              <div class="dropzone__text">10 Mo max · type MIME vérifié · nom de fichier assaini · stocké hors du dossier /public, jamais servi en direct.</div>
              <input type="file" name="doc" accept=".pdf,.docx,.txt,.md" required>
            </div>
            <div class="grid-3" style="margin-top:14px">
              <div>
                <label class="label" for="d-lang">LANGUE</label>
                <select class="field" id="d-lang" name="lang">
                  <?php foreach (Config::LANGS as $code): ?>
                    <option value="<?= Text::e($code) ?>"><?= Text::e(strtoupper($code)) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div style="display:flex;align-items:flex-end"><label class="check"><input type="checkbox" name="public" value="1" checked><span>Citable dans les réponses</span></label></div>
              <div style="display:flex;align-items:flex-end"><button class="btn btn--ink btn--block" type="submit">Ajouter et indexer</button></div>
            </div>
          </form>

          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-top:18px">
            <form method="post" action="<?= Text::e(Router::adminUrl()) ?>">
              <?= Csrf::field('admin') ?>
              <input type="hidden" name="action" value="ai-reindex">
              <button class="btn btn--outline" type="submit">Réindexer maintenant</button>
            </form>
            <span class="muted">
              <?= (int) ($stats['count'] ?? 0) ?> extraits ·
              <?= Text::e(($stats['builtAt'] ?? '') !== '' ? Admin::humanDate((string) $stats['builtAt']) : 'jamais') ?>
            </span>
          </div>
        </div>
      </div>
    </section>

    <div style="display:flex;flex-direction:column;gap:18px">
      <section class="panel panel--ink">
        <h2 style="margin:0;font:800 19px/1 'Bricolage Grotesque',sans-serif;color:#FFD100">Prompt système de l'assistant</h2>
        <form method="post" action="<?= Text::e(Router::adminUrl()) ?>">
          <?= Csrf::field('admin') ?>
          <input type="hidden" name="action" value="ai-prompt">
          <textarea class="field" name="prompt" rows="7" style="margin-top:14px;background:rgba(255,248,234,.07);color:#FFF8EA;border-color:rgba(255,248,234,.3)"><?= Text::e(Gemini::systemPrompt()) ?></textarea>
          <div class="tags">
            <span class="tag tag--yellow"><?= Text::e(Gemini::model()) ?></span>
            <span class="tag">température 0,2</span>
            <span class="tag">400 jetons max</span>
            <span class="tag">20 questions/h par IP</span>
            <span class="tag" style="background:<?= Gemini::configured() ? '#12B39A' : 'rgba(255,248,234,.12)' ?>;color:<?= Gemini::configured() ? '#0E0E0E' : '#FFF8EA' ?>">
              <?= Gemini::configured() ? 'clé API en place' : 'sans clé : index local' ?>
            </span>
          </div>
          <label class="check" style="margin-top:16px;color:#FFF8EA"><input type="checkbox" name="enabled" value="1" <?= !empty($settings['ai']['enabled']) ? 'checked' : '' ?>><span>Afficher l'assistant sur le site</span></label>

          <?php foreach (Config::LANGS as $code): ?>
            <label class="label" style="margin-top:16px;color:#FFF8EA;opacity:.7" for="sug-<?= Text::e($code) ?>">SUGGESTIONS <?= Text::e(strtoupper($code)) ?> (3 LIGNES)</label>
            <textarea class="field" id="sug-<?= Text::e($code) ?>" name="suggestions[<?= Text::e($code) ?>]" rows="3" style="background:rgba(255,248,234,.07);color:#FFF8EA;border-color:rgba(255,248,234,.3)"><?= Text::e(implode("\n", array_map('strval', (array) ($settings['ai']['suggestions'][$code] ?? [])))) ?></textarea>
          <?php endforeach; ?>

          <button class="btn btn--yellow" type="submit" style="margin-top:16px">Enregistrer l'assistant</button>
        </form>
      </section>

      <section class="panel panel--yellow">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
          <h2 style="margin:0;font:800 19px/1 'Bricolage Grotesque',sans-serif">Questions sans réponse</h2>
          <?php if ($misses !== []): ?>
            <form method="post" action="<?= Text::e(Router::adminUrl()) ?>">
              <?= Csrf::field('admin') ?>
              <input type="hidden" name="action" value="ai-misses-clear">
              <button class="link-underline" type="submit">Vider</button>
            </form>
          <?php endif; ?>
        </div>
        <p class="muted" style="margin:10px 0 16px">À transformer en contenu : c'est votre feuille de route éditoriale.</p>
        <div class="stack">
          <?php if ($misses === []): ?>
            <div class="guard"><div class="guard__text">Aucune question restée sans réponse pour l'instant.</div></div>
          <?php else: ?>
            <?php foreach ($misses as $miss): ?>
              <div class="miss">
                <span class="miss__q"><?= Text::e((string) ($miss['q'] ?? '')) ?></span>
                <span class="miss__n"><?= (int) ($miss['n'] ?? 1) ?>×</span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <section class="panel panel--pad">
        <h2 style="margin:0 0 10px;font:800 19px/1 'Bricolage Grotesque',sans-serif">Sources indexées</h2>
        <div class="stack">
          <?php foreach ((array) ($stats['bySource'] ?? []) as $label => $count): ?>
            <div class="miss" style="background:#FFF8EA">
              <span class="miss__q"><?= Text::e((string) $label) ?></span>
              <span class="miss__n"><?= (int) $count ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    </div>
  </div>
</div>
<?= View::admin('_layout_end') ?>
