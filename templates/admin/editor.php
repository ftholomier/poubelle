<?php
/**
 * Éditeur WYSIWYG + base de connaissance.
 * @var array $page @var array|null $lock @var array|null $lockHolder
 * @var string $notice @var array $translations @var array $documents
 */
use App\Core\Csrf;
use App\Services\I18n;
use App\Support\Icon;

$locked = $lock === null;
$stateClass = ['source' => 'state-neutral', 'fresh' => 'state-ok', 'stale' => 'state-wait', 'missing' => 'state-err'];
$stateLabel = [
    'source'  => I18n::t('admin.state_source'),
    'fresh'   => I18n::t('admin.state_fresh'),
    'stale'   => I18n::t('admin.state_stale'),
    'missing' => I18n::t('admin.state_missing'),
];
?>
<div class="admin-head">
  <div>
    <h1><?= e($page['title']) ?></h1>
    <p>/<?= e($page['slug']) ?> · version <?= (int) $page['revision'] ?></p>
  </div>
  <?php if ($locked && $lockHolder !== null): ?>
    <span class="lock-pill">
      <?= Icon::svg('lock', 14, 'currentColor', 2) ?>
      <?= e(I18n::t('admin.lock', (string) $lockHolder['user_name'], (int) $lockHolder['age_minutes'])) ?>
    </span>
  <?php elseif ($lock !== null): ?>
    <span class="lock-pill is-mine">
      <?= Icon::svg('lock', 14, 'currentColor', 2) ?>
      <?= e(I18n::t('admin.lock', 'vous', (int) ($lock['age_minutes'] ?? 0))) ?>
    </span>
  <?php endif; ?>
</div>

<?php if ($notice !== ''): ?>
  <div class="notice <?= str_contains($notice, 'verrou') || str_contains($notice, 'session') ? 'notice-err' : 'notice-ok' ?>"
       role="status"><?= e($notice) ?></div>
<?php endif; ?>

<form method="post" class="editor-grid" data-editor>
  <?= Csrf::field('admin-editor') ?>

  <div class="admin-card" style="margin:0">
    <label class="field">
      <span class="label">Titre</span>
      <input class="input" type="text" name="title" value="<?= e($page['title']) ?>" <?= $locked ? 'disabled' : '' ?>>
    </label>

    <div class="editor-toolbar" role="toolbar" aria-label="Mise en forme" style="margin-top:18px">
      <button type="button" data-cmd="bold" title="Gras"><strong>B</strong></button>
      <button type="button" data-cmd="italic" title="Italique"><em>I</em></button>
      <button type="button" data-cmd="underline" title="Souligné"><u>U</u></button>
      <span class="sep"></span>
      <button type="button" data-cmd="formatBlock" data-value="h2">H2</button>
      <button type="button" data-cmd="formatBlock" data-value="h3">H3</button>
      <button type="button" data-cmd="formatBlock" data-value="blockquote">&ldquo;&rdquo;</button>
      <span class="sep"></span>
      <button type="button" data-cmd="insertUnorderedList" title="Liste à puces">•</button>
      <button type="button" data-cmd="insertOrderedList" title="Liste numérotée">1.</button>
      <span class="sep"></span>
      <button type="button" data-cmd="createLink" title="Lien"><?= Icon::svg('arrow-r', 14, '#17123A', 2) ?></button>
      <button type="button" data-cmd="insertImage" title="Image"><?= Icon::svg('file', 14, '#17123A', 2) ?></button>
      <span class="sep"></span>
      <button type="button" data-cmd="removeFormat" title="Effacer la mise en forme">⌫</button>
    </div>

    <div class="editor-area" contenteditable="<?= $locked ? 'false' : 'true' ?>" data-editor-area
         role="textbox" aria-multiline="true" aria-label="Contenu"><?= $page['body'] ?></div>
    <textarea name="body" class="visually-hidden" data-editor-input aria-hidden="true"><?= e($page['body']) ?></textarea>

    <div class="grid-fields" style="margin-top:18px">
      <label class="field">
        <span class="label">Titre SEO <span class="opt">(facultatif)</span></span>
        <input class="input" type="text" name="seo_title" value="<?= e($page['seo']['title'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?>>
      </label>
      <label class="field">
        <span class="label">Description SEO <span class="opt">(facultatif)</span></span>
        <input class="input" type="text" name="seo_description" value="<?= e($page['seo']['description'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?>>
      </label>
    </div>

    <div class="editor-foot">
      <span class="status" data-editor-status>
        <?= e(I18n::t('admin.autosave', 0, (int) $page['revision'])) ?>
      </span>
      <div style="display:flex;gap:9px;flex-wrap:wrap">
        <button type="submit" name="action" value="draft" class="btn btn-ghost btn-sm" <?= $locked ? 'disabled' : '' ?>>
          <?= e(I18n::t('admin.draft')) ?>
        </button>
        <button type="submit" name="action" value="publish" class="btn btn-coral btn-sm" <?= $locked ? 'disabled' : '' ?>>
          <?= e(I18n::t('admin.publish')) ?>
        </button>
      </div>
    </div>
  </div>

  <aside style="display:flex;flex-direction:column;gap:18px">
    <div class="admin-card" style="margin:0">
      <h3><?= e(I18n::t('admin.translations')) ?></h3>
      <ul style="list-style:none;margin:14px 0 0;padding:0;display:flex;flex-direction:column;gap:9px">
        <?php foreach ($translations as $translation): ?>
          <li style="display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:13.5px">
            <span><?= e($translation['name']) ?></span>
            <span class="state <?= e($stateClass[$translation['state']] ?? 'state-neutral') ?>">
              <?= e($stateLabel[$translation['state']] ?? $translation['state']) ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
      <a class="btn btn-ghost btn-sm btn-block" style="margin-top:16px" href="/admin/traductions">
        <?= e(I18n::t('admin.translate_all')) ?>
      </a>
    </div>

    <div class="admin-card kb-card" style="margin:0">
      <h3><?= Icon::svg('robot', 17, '#FFC531', 2) ?> Base de connaissance</h3>
      <?php if ($documents === []): ?>
        <p style="font-size:13px;color:rgba(255,255,255,.62);margin:12px 0 0">Aucun document indexé.</p>
      <?php else: ?>
        <div style="margin-top:12px">
          <?php foreach (array_slice($documents, 0, 6) as $doc): ?>
            <div class="doc">
              <span class="kind"><?= e($doc['kind']) ?></span>
              <span class="name"><?= e($doc['title']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <a class="btn btn-yellow btn-sm btn-block" style="margin-top:16px" href="/admin/documents">
        <?= Icon::svg('plus', 14, '#17123A', 2.2) ?> Ajouter un document
      </a>
    </div>
  </aside>
</form>
