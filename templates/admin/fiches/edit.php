<?php
/**
 * Masque de saisie d'une fiche. Variables : $doc, $isNew, $versions, $checks, $auto, $enStatus, $lineup, $list
 */
use App\Admin\Base;
use App\Admin\Form;
use App\Core\Auth;
use App\Data\Fiches;

$type = $doc['type'];
$id = (int) $doc['id'];
$tabs = match ($type) {
    'match' => ['infos' => 'Infos', 'compo' => 'Compo & événements', 'recit' => 'Récit', 'medias' => 'Médias', 'tableaux' => 'Tableaux', 'seo' => 'Classement & SEO', 'en' => 'Version EN', 'historique' => 'Historique'],
    'personne' => ['identite' => 'Identité', 'carriere' => 'Carrière', 'recit' => 'Récit', 'stats' => 'Statistiques', 'medias' => 'Médias', 'seo' => 'Classement & SEO', 'en' => 'Version EN', 'historique' => 'Historique'],
    'objet' => ['objet' => 'Objet', 'recit' => 'Description', 'medias' => 'Médias', 'seo' => 'Classement & SEO', 'en' => 'Version EN', 'historique' => 'Historique'],
    'moment' => ['moment' => 'Moment', 'recit' => 'Récit', 'medias' => 'Médias', 'seo' => 'Classement & SEO', 'en' => 'Version EN', 'historique' => 'Historique'],
    default => ['contenu' => 'Contenu', 'recit' => 'Texte', 'medias' => 'Médias', 'tableaux' => 'Tableaux', 'seo' => 'Classement & SEO', 'en' => 'Version EN', 'historique' => 'Historique'],
};
if ($isNew) {
    unset($tabs['historique'], $tabs['en']);
}
$counts = [
    'medias' => count($doc['gallery'] ?? []) + count($doc['videos'] ?? []),
    'historique' => count($versions),
    'tableaux' => count($doc['tables'] ?? []),
    'compo' => count($doc['match']['lineup']['rows'] ?? []),
];
$visible = Fiches::isVisible($doc);
$last = $versions[0] ?? null;
$enLabel = ['none' => 'Non traduite', 'auto' => 'Traduite (Gemini)', 'manual' => 'Relue ✓', 'stale' => 'À revoir'][$enStatus] ?? '';
?>
<form class="editor" data-json-form data-tabs-scope data-url="/admin/fiche/<?= $id ?: 0 ?>/enregistrer" data-modified="<?= e((string) ($doc['modified'] ?? '')) ?>" data-draft="fiche-<?= $id ?: 'nouvelle-' . e($type) ?>" novalidate>
  <?php if ($isNew): ?><input type="hidden" name="_type" value="<?= e($type) ?>"><?php endif; ?>
  <div class="stack">
    <?php if ($doc['status'] === 'corbeille'): ?><p class="alert alert--error">Cette fiche est à la corbeille : elle n’est pas visible sur le site.</p><?php endif; ?>
    <div class="ftabs" data-ftabs role="tablist">
      <?php foreach ($tabs as $k => $l): ?><button type="button" data-tab="<?= e($k) ?>" role="tab"><?= e($l) ?><?php if (!empty($counts[$k])): ?><em><?= (int) $counts[$k] ?></em><?php endif; ?></button><?php endforeach; ?>
    </div>

    <?php if ($type === 'match'): ?>
      <?= \App\Core\View::partial('admin/fiches/_match', ['doc' => $doc, 'lineup' => $lineup]) ?>
    <?php elseif ($type === 'personne'): ?>
      <?= \App\Core\View::partial('admin/fiches/_personne', ['doc' => $doc]) ?>
    <?php elseif ($type === 'objet'): ?>
      <?= \App\Core\View::partial('admin/fiches/_objet', ['doc' => $doc]) ?>
    <?php elseif ($type === 'moment'): ?>
      <?= \App\Core\View::partial('admin/fiches/_moment', ['doc' => $doc]) ?>
    <?php else: ?>
      <?= \App\Core\View::partial('admin/fiches/_article', ['doc' => $doc]) ?>
    <?php endif; ?>

    <?= \App\Core\View::partial('admin/fiches/_recit', ['doc' => $doc]) ?>
    <?= \App\Core\View::partial('admin/fiches/_medias', ['doc' => $doc]) ?>
    <?php if (isset($tabs['tableaux'])): ?>
      <div class="fpanel" data-panel="tableaux">
        <div class="card card--pad">
          <h2 class="card__t">Tableaux</h2>
          <p class="small muted" style="margin:0">Statistiques, compositions d’origine, classements… Collez un tableau depuis Excel ou une page web avec « Coller un tableau ».</p>
          <input type="hidden" name="tables" data-type="json" data-table-editor value="<?= e(json_encode($doc['tables'] ?? [], JSON_UNESCAPED_UNICODE)) ?>">
        </div>
      </div>
    <?php endif; ?>
    <?= \App\Core\View::partial('admin/fiches/_seo', ['doc' => $doc]) ?>
    <?php if (!$isNew): ?>
      <?= \App\Core\View::partial('admin/fiches/_en', ['doc' => $doc, 'enStatus' => $enStatus]) ?>
      <?= \App\Core\View::partial('admin/fiches/_historique', ['doc' => $doc, 'versions' => $versions]) ?>
    <?php endif; ?>
  </div>

  <aside class="editor__side">
    <div class="card card--pad pub">
      <h2 class="card__t card__t--sm">Publication</h2>
      <div class="seg" style="--n:2">
        <?php foreach (['brouillon' => 'Brouillon', 'relire' => 'À relire', 'planifie' => 'Planifié', 'publie' => 'Publié'] as $k => $l): ?>
          <label><input type="radio" name="status" value="<?= $k ?>"<?= $doc['status'] === $k || ($doc['status'] === 'corbeille' && $k === ($doc['status_before_trash'] ?? 'brouillon')) ? ' checked' : '' ?>><span><?= $l ?></span></label>
        <?php endforeach; ?>
      </div>
      <div class="f" data-show-if="status" data-show-value="planifie">
        <span class="f__k">Publication programmée le</span>
        <input type="datetime-local" name="publish_at" value="<?= e(!empty($doc['publish_at']) ? date('Y-m-d\TH:i', strtotime($doc['publish_at'])) : '') ?>">
      </div>
      <span class="saved" data-saved><?= $isNew ? 'Nouvelle fiche, pas encore enregistrée' : ($last ? 'Enregistré · v' . (int) $last['n'] . ' · ' . e($last['by']) . ' · ' . e(Base::ago($last['at'])) : 'Enregistré') ?></span>
      <div class="row">
        <button type="submit" class="btn btn--navy grow" data-save>Enregistrer</button>
        <?php if (!$isNew): ?><button type="button" class="btn" data-preview="/admin/fiche/<?= $id ?>/apercu" title="Aperçu comme sur le site (avec vos modifications en cours)">Aperçu</button><?php endif; ?>
      </div>
      <?php if (!$isNew && $visible && $doc['path']): ?><a class="linkbtn" href="<?= e($doc['path']) ?>" target="_blank" rel="noopener">Voir sur le site ↗</a><?php endif; ?>
      <?php if (!$isNew): ?><a class="linkbtn" href="/pdf/fiche/<?= $id ?>.pdf" title="Le document PDF tel que les visiteurs le téléchargent (version enregistrée)" download>Télécharger le PDF ↓</a><?php endif; ?>
      <label class="f"><span class="f__k">Note de version <i>facultatif</i></span><input name="_message" maxlength="160" placeholder="ex. score corrigé d’après L’Est républicain"></label>
      <span class="xs muted">Ctrl+S pour enregistrer · brouillon gardé automatiquement sur cet ordinateur</span>
    </div>

    <?php if ($auto): ?>
      <div class="card card--pad">
        <h2 class="card__t card__t--sm">Mis à jour automatiquement</h2>
        <div class="autolinks stack" style="gap:6px">
          <?php foreach ($auto as [$t, $href]): ?><?= $href ? '<a href="' . e($href) . '" target="_blank" rel="noopener">' . e($t) . '</a>' : '<a>' . e($t) . '</a>' ?><?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($checks): ?>
      <div class="card card--pad">
        <h2 class="card__t card__t--sm">Contrôle qualité</h2>
        <div class="checks">
          <?php foreach (array_slice($checks, 0, 8) as [$lvl, $msg]): ?><span><b class="<?= $lvl ?>"><?= $lvl === 'ok' ? '✓' : '⚠' ?></b><?= e($msg) ?></span><?php endforeach; ?>
          <?php if (count($checks) > 8): ?><span class="xs muted">… et <?= count($checks) - 8 ?> autres alertes</span><?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!$isNew): ?>
      <div class="card card--pad" style="flex-direction:row;justify-content:space-between;align-items:center">
        <h2 class="card__t card__t--sm">Version EN</h2>
        <span class="pill pill--<?= ['none' => 'brouillon', 'auto' => 'info', 'manual' => 'ok', 'stale' => 'warn'][$enStatus] ?? 'brouillon' ?>"><?= e($enLabel) ?></span>
      </div>
      <div class="card card--pad">
        <span class="xs muted">Fiche n° <?= $id ?> · type <?= e(mb_strtolower(Fiches::TYPES[$type])) ?><?= !empty($doc['legacy']['url']) ? ' · <a href="' . e($doc['legacy']['url']) . '" target="_blank" rel="noopener">page WordPress d’origine</a>' : '' ?></span>
        <?php if ($doc['status'] !== 'corbeille'): ?>
          <button type="submit" class="btn btn--sm btn--danger" form="trash-form">Mettre à la corbeille</button>
        <?php else: ?>
          <button type="submit" class="btn btn--sm" form="untrash-form">Sortir de la corbeille</button>
          <?php if (Auth::can('destroy')): ?><button type="submit" class="btn btn--sm btn--danger" form="destroy-form">Supprimer définitivement</button><?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </aside>
</form>
<?php if (!$isNew): ?>
  <?php /* Formulaires secondaires hors du formulaire principal (les formulaires imbriqués sont interdits en HTML). */ ?>
  <form id="translate-form" method="post" action="/admin/fiche/<?= $id ?>/traduire" data-confirm="Traduire avec Gemini ?|La version anglaise actuelle sera remplacée par une nouvelle traduction automatique.|Traduire"><?= csrf_field() ?></form>
  <form id="restore-form" method="post" action="/admin/fiche/<?= $id ?>" data-confirm="Restaurer cette version ?|Rien n’est perdu : la restauration crée une nouvelle version, elle-même réversible.|Restaurer"><?= csrf_field() ?></form>
  <form id="trash-form" method="post" action="/admin/fiche/<?= $id ?>/corbeille" data-confirm="Mettre à la corbeille ?|La fiche disparaît du site. Vous pourrez la récupérer depuis la corbeille.|Mettre à la corbeille|danger"><?= csrf_field() ?></form>
  <form id="untrash-form" method="post" action="/admin/fiche/<?= $id ?>/sortir-corbeille"><?= csrf_field() ?></form>
  <form id="destroy-form" method="post" action="/admin/fiche/<?= $id ?>/supprimer" data-confirm="Supprimer définitivement ?|Cette action est irréversible (une copie reste dans l’historique des versions).|Supprimer|danger"><?= csrf_field() ?></form>
<?php endif; ?>
