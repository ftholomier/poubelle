<?php
/** Pages & contenus : liste des pages, onglets de langue, édition par champ. */

use App\Admin;
use App\Config;
use App\Csrf;
use App\Router;
use App\Store;
use App\Text;
use App\Translator;
use App\View;

echo View::admin('_layout_start', get_defined_vars());

$status = (string) ($page['status'] ?? 'draft');
$locked = $lockOwner !== null && $lockOwner !== (string) $user['email'];
$updatedAt = (string) ($page['updatedAt'] ?? '');
$updatedBy = (string) ($page['updatedBy'] ?? '');
?>
<div class="screen editor">
  <section class="pagelist">
    <div class="pagelist__title">PAGES · content/pages</div>
    <?php foreach (Admin::PAGES as $key => $label):
        $pageData = Store::read('pages/' . $key . '.' . $lang . '.json');
        $isPublished = ($pageData['status'] ?? 'draft') === 'published'; ?>
      <a class="pagelist__item<?= $key === $slug ? ' is-active' : '' ?>" href="<?= Text::e(Router::adminUrl('pages', ['slug' => $key, 'lang' => $lang])) ?>">
        <span><?= Text::e($label) ?></span>
        <span class="pagelist__dot<?= $isPublished ? '' : ' pagelist__dot--draft' ?>" title="<?= $isPublished ? 'Publiée' : 'Brouillon' ?>"></span>
      </a>
    <?php endforeach; ?>
  </section>

  <section class="panel">
    <form method="post" action="<?= Text::e(Router::adminUrl()) ?>">
      <?= Csrf::field('admin') ?>
      <input type="hidden" name="action" value="page-save">
      <input type="hidden" name="slug" value="<?= Text::e($slug) ?>">
      <input type="hidden" name="lang" value="<?= Text::e($lang) ?>">
      <input type="hidden" name="status" value="<?= Text::e($status) ?>">

      <div class="toolbar">
        <?php foreach (Config::LANGS as $code): ?>
          <a class="tab<?= $code === $lang ? ' is-active' : '' ?>" href="<?= Text::e(Router::adminUrl('pages', ['slug' => $slug, 'lang' => $code])) ?>"><?= Text::e(strtoupper($code)) ?></a>
        <?php endforeach; ?>
        <span class="toolbar__sep" aria-hidden="true"></span>
        <span class="badge" style="background:<?= $status === 'published' ? '#12B39A' : '#EDE5D5' ?>"><?= $status === 'published' ? 'EN LIGNE' : 'BROUILLON' ?></span>
        <a class="link-underline" href="<?= Text::e(Config::basePath() . Router::url($slug === 'home' ? 'home' : ($slug === 'spaces' ? 'spaces' : ($slug === 'offices' ? 'offices' : ($slug === 'news' ? 'news' : ($slug === 'contact' ? 'contact' : ($slug === 'privacy' ? 'privacy' : 'legal'))))), $lang)) ?>" target="_blank" rel="noopener">Voir la page</a>

        <span class="lock<?= $locked ? ' lock--taken' : '' ?>">
          <span class="lock__dot"></span>
          <span><?= $locked ? Text::e('Verrou : ' . $lockOwner . ' édite cette page') : 'Verrou : vous éditez cette page' ?></span>
        </span>
      </div>

      <div class="form-body">
        <?php if ($locked): ?>
          <div class="flash flash--error" style="margin:0 0 18px">Enregistrement bloqué : <?= Text::e((string) $lockOwner) ?> a ouvert cette page il y a moins de 10 minutes.</div>
        <?php endif; ?>

        <label class="label" for="nav">NOM DANS LE MENU</label>
        <input class="field" id="nav" type="text" name="nav" value="<?= Text::e((string) ($page['nav'] ?? Admin::PAGES[$slug] ?? '')) ?>">

        <?php foreach ($schema as $section): ?>
          <h2 style="margin:30px 0 0;font:800 19px/1 'Bricolage Grotesque',sans-serif;padding-top:18px;border-top:2px solid rgba(14,14,14,.12)"><?= Text::e((string) $section['title']) ?></h2>
          <?php foreach ((array) $section['fields'] as $field):
              $path = (string) $field['path'];
              $name = 'f' . implode('', array_map(static fn (string $k): string => '[' . $k . ']', explode('.', $path)));
              echo View::admin('_field', [
                  'field' => $field,
                  'value' => Admin::get($page, $path),
                  'name' => $name,
                  'media' => $media,
              ]);
          endforeach; ?>
        <?php endforeach; ?>

        <div class="form-actions">
          <button class="btn btn--ink" type="submit" <?= $locked ? 'disabled' : '' ?>>Enregistrer le brouillon</button>
          <button class="btn btn--yellow" type="submit" name="publish" value="1" <?= $locked ? 'disabled' : '' ?>>Publier en ligne</button>
          <span class="form-actions__note">
            <?= \count($versions) ?> version(s)<?= $updatedAt !== '' ? ' · dernière ' . Text::e(Admin::humanDate($updatedAt)) : '' ?><?= $updatedBy !== '' ? ' par ' . Text::e($updatedBy) : '' ?>
          </span>
        </div>
      </div>
    </form>

    <div class="panel__body" style="border-top:2px solid #0E0E0E;background:#FFF8EA">
      <div class="grid-2">
        <?php if ($lang !== Config::DEFAULT_LANG): ?>
          <form method="post" action="<?= Text::e(Router::adminUrl()) ?>">
            <?= Csrf::field('admin') ?>
            <input type="hidden" name="action" value="page-translate">
            <input type="hidden" name="slug" value="<?= Text::e($slug) ?>">
            <input type="hidden" name="target" value="<?= Text::e($lang) ?>">
            <button class="btn btn--outline btn--block" type="submit" <?= Translator::configured() ? '' : 'disabled' ?>>
              Traduire depuis le français (Google Traduction)
            </button>
            <div class="hint"><?= Translator::configured()
                ? 'Le résultat est écrit en brouillon : vous relisez avant de publier.'
                : 'Renseignez GOOGLE_TRANSLATE_KEY dans Réglages → Clés API pour activer ce bouton.' ?></div>
          </form>
        <?php endif; ?>

        <form method="post" action="<?= Text::e(Router::adminUrl()) ?>">
          <?= Csrf::field('admin') ?>
          <input type="hidden" name="action" value="page-restore">
          <input type="hidden" name="slug" value="<?= Text::e($slug) ?>">
          <input type="hidden" name="lang" value="<?= Text::e($lang) ?>">
          <label class="label" for="version">RESTAURER UNE VERSION</label>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <select class="field" id="version" name="version" style="flex:1;min-width:200px">
              <?php foreach ($versions as $version): ?>
                <option value="<?= Text::e((string) $version['id']) ?>"><?= Text::e((string) $version['id']) ?></option>
              <?php endforeach; ?>
              <?php if ($versions === []): ?><option value="">Aucune version enregistrée</option><?php endif; ?>
            </select>
            <button class="btn btn--outline" type="submit" <?= $versions === [] ? 'disabled' : '' ?>>Restaurer</button>
          </div>
          <div class="hint">La version courante est elle-même sauvegardée avant restauration : aucun aller-retour n'est perdu.</div>
        </form>
      </div>

      <?php if (!$locked): ?>
        <form method="post" action="<?= Text::e(Router::adminUrl()) ?>" style="margin-top:16px">
          <?= Csrf::field('admin') ?>
          <input type="hidden" name="action" value="page-lock">
          <input type="hidden" name="slug" value="<?= Text::e($slug) ?>">
          <input type="hidden" name="lang" value="<?= Text::e($lang) ?>">
          <button class="link-underline" type="submit">Libérer le verrou d'édition</button>
        </form>
      <?php endif; ?>
    </div>
  </section>
</div>
<?= View::admin('_layout_end') ?>
