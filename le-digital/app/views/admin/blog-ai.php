<?php
/**
 * Rédaction d'articles par l'IA : brief → article complet + photo Nano Banana → validation → publication.
 * @var array $themes @var array $set @var array $drafts @var bool $canWrite @var bool $canImage @var ?array $writer @var string $csrf
 */
use LD\Ai;
use LD\BlogWriter;

$rate = LD\AiCosts::rate();
?>
<?= LD\View::render('admin/blog-tabs', ['on' => 'ai']) ?>

<?php if (!$canWrite): ?>
  <div class="maj-banner maj-banner--err"><span class="status status--new">IA</span> <b>Aucun modèle d’IA n’est configuré pour la rédaction.</b> <a href="/admin/ia">Ajoute une clé API dans IA & clés API →</a></div>
<?php endif; ?>

<div class="bw" data-blog-writer>
  <form class="card bw__form" data-bw-form>
    <div class="card__head"><h2>✨ Ton brief</h2><?php if ($writer): ?><span class="small muted">Rédigé par <?= e(Ai::PROVIDERS[$writer[0]]['label'] ?? $writer[0]) ?> · <?= e($writer[1]) ?></span><?php endif; ?></div>
    <div class="form">
      <label>Sujet de l’article <small class="muted">(une phrase suffit)</small>
        <input name="sujet" required maxlength="300" placeholder="Ex. : Pourquoi un CRM sur mesure fait gagner 5 h par semaine à un artisan">
      </label>
      <div class="form form--cols">
        <label>Thème du blog
          <select name="theme"><?php foreach ($themes as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select>
        </label>
        <label>Longueur
          <select name="longueur"><?php foreach (BlogWriter::LENGTHS as $k => $l): ?><option value="<?= e($k) ?>"<?= $k === '1000' ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
        </label>
      </div>
      <label>Ton explication, ton angle, les idées à faire passer
        <textarea name="angle" rows="5" maxlength="3000" placeholder="Ex. : partir du constat que les artisans passent leurs soirées sur Excel ; expliquer ce qu’un CRM métier change (devis, relances, suivi de chantier) ; insister sur le fait qu’on construit l’outil ensemble, sans usine à gaz."></textarea>
      </label>
      <div class="form form--cols">
        <label>Mots-clés SEO <small class="muted">(le premier est le principal)</small>
          <input name="mots_cles" maxlength="300" placeholder="CRM sur mesure, logiciel artisan, gain de temps">
        </label>
        <label>Public visé
          <input name="public" maxlength="200" placeholder="Artisans du bâtiment, TPE de 1 à 10 salariés">
        </label>
      </div>
      <fieldset class="bw__img"<?= $canImage ? '' : ' disabled' ?>>
        <legend><label class="check"><input type="checkbox" name="with_image" value="1"<?= $canImage ? ' checked' : '' ?>> Générer la photo de l’article avec Google Nano Banana</label></legend>
        <?php if (!$canImage): ?><p class="small muted">Ajoute ta clé API Google Gemini dans <a href="/admin/ia">IA & clés API</a> pour générer les photos.</p><?php endif; ?>
        <div class="form form--cols">
          <label>Modèle d’image
            <select name="model"><?php foreach (Ai::IMAGE_MODELS as $k => $l): ?><option value="<?= e($k) ?>"<?= $k === $set['image_model'] ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
          </label>
          <label>Style de la photo
            <input name="style" maxlength="200" value="<?= e((string) $set['image_style']) ?>">
          </label>
        </div>
        <input type="hidden" name="size" value="<?= e((string) $set['image_size']) ?>">
      </fieldset>
    </div>
    <div class="actions">
      <button class="btn btn--red" type="submit" data-bw-go<?= $canWrite ? '' : ' disabled' ?>>✨ Rédiger l’article</button>
      <span class="small muted">L’article est enregistré en brouillon : rien n’est publié sans ta validation.</span>
    </div>
  </form>

  <section class="card bw__out" data-bw-out hidden>
    <div class="bw__steps" data-bw-steps>
      <span data-step="text" data-run="Rédaction de l’article… (30 s à 1 min)" data-ok="Article rédigé" data-ko="Rédaction impossible">Rédaction de l’article…</span>
      <span data-step="image" data-wait="Photo ensuite" data-run="Création de la photo…" data-ok="Photo créée" data-ko="Photo impossible">Création de la photo…</span>
    </div>
    <p class="bw__err" data-bw-err hidden></p>
    <article class="bw__preview" data-bw-preview hidden>
      <div class="bw__photo" data-bw-photo></div>
      <p class="bw__meta" data-bw-meta></p>
      <h2 class="bw__title" data-bw-title></h2>
      <p class="bw__excerpt" data-bw-excerpt></p>
      <div class="bw__seo" data-bw-seo></div>
      <div class="bw__body prose-admin" data-bw-body></div>
    </article>
    <div class="actions sticky-actions" data-bw-actions hidden>
      <form method="post" data-bw-publish><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="btn btn--red" type="submit">✓ Valider et publier sur le blog</button></form>
      <a class="btn btn--dark" data-bw-edit href="#">Modifier avant de publier</a>
      <button class="btn btn--ghost" type="button" data-bw-reimage<?= $canImage ? '' : ' hidden' ?>>↻ Nouvelle photo</button>
      <span class="small muted" data-bw-cost></span>
    </div>
  </section>

  <?php if ($drafts): ?>
    <section class="card card--flush">
      <div class="card__head"><h2>Articles écrits avec l’IA</h2></div>
      <table class="table">
        <thead><tr><th>Article</th><th class="hide-sm">Coût IA</th><th>Statut</th></tr></thead>
        <tbody>
          <?php foreach ($drafts as $d): $ai = (array) $d['ai']; $c = ((float) ($ai['cost_text'] ?? 0) + (float) ($ai['cost_image'] ?? 0)) * $rate; ?>
            <tr data-href="/admin/blog/<?= e($d['id']) ?>">
              <td><a href="/admin/blog/<?= e($d['id']) ?>"><b><?= e($d['title']) ?></b></a><br><small class="muted"><?= e(date('d/m/Y', strtotime((string) ($ai['at'] ?? $d['date'])))) ?> · <?= e($d['theme']) ?><?= $d['image'] ? ' · avec photo' : '' ?></small></td>
              <td class="hide-sm"><small><?= e(number_format($c, 3, ',', ' ')) ?> €</small></td>
              <td><?= !empty($d['published']) ? '<span class="status status--done">En ligne</span>' : '<span class="status status--archived">Brouillon</span>' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endif; ?>
</div>
