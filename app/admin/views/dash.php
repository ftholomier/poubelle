<?php
/** Tableau de bord : compteurs, dernières demandes, garde-fous. */

use App\Admin;
use App\Ai\Gemini;
use App\Config;
use App\Content;
use App\Csrf;
use App\Offices;
use App\Requests;
use App\Reviews;
use App\Router;
use App\Text;
use App\View;

echo View::admin('_layout_start', get_defined_vars());

$available = \count(Offices::filter(Offices::published(), ['status' => 'available']));
$soon = \count(Offices::filter(Offices::published(), ['status' => 'soon']));
$disabled = \count(Offices::all()) - \count(Offices::published());
$newRequests = \count(array_filter(Requests::all(), static fn (array $r): bool => ($r['status'] ?? '') === 'new'));
$missing = Content::missing();
?>
<div class="screen">
  <div class="cards">
    <div class="kpi">
      <div class="kpi__label">DEMANDES CE MOIS</div>
      <div class="kpi__value"><?= Requests::countSince('-30 days') ?></div>
      <div class="kpi__note"><?= $newRequests ?> non traitée(s)</div>
    </div>
    <div class="kpi" style="background:#FFD100">
      <div class="kpi__label">POSTES LIBRES</div>
      <div class="kpi__value"><?= $available ?></div>
      <div class="kpi__note"><?= $soon ?> bientôt libre(s) · <?= $disabled ?> désactivé(s)</div>
    </div>
    <div class="kpi">
      <div class="kpi__label">FICHIERS DE PAGE</div>
      <div class="kpi__value"><?= (int) ($pagesCount ?? 0) ?></div>
      <div class="kpi__note"><?= \count(Admin::PAGES) ?> pages × <?= \count(Config::LANGS) ?> langues</div>
    </div>
    <div class="kpi" style="background:#12B39A">
      <div class="kpi__label">EXTRAITS INDEXÉS</div>
      <div class="kpi__value"><?= (int) ($stats['count'] ?? 0) ?></div>
      <div class="kpi__note"><?= Text::e(($stats['builtAt'] ?? '') !== '' ? 'indexé ' . Admin::humanDate((string) $stats['builtAt']) : 'jamais indexé') ?></div>
    </div>
  </div>

  <div class="panels">
    <section class="panel">
      <div class="panel__head">
        <h2>Dernières demandes</h2>
        <span class="panel__file">content/requests.json</span>
      </div>
      <div class="panel__scroll">
        <?php if ($requests === []): ?>
          <div class="panel__body muted">Aucune demande pour l'instant. Les formulaires du site écrivent ici.</div>
        <?php else: ?>
          <?php foreach ($requests as $request): ?>
            <div class="row row--requests">
              <div>
                <div class="row__title"><?= Text::e((string) ($request['name'] ?? $request['email'] ?? '—')) ?></div>
                <div class="row__sub"><?= Text::e((string) ($request['email'] ?? '')) ?></div>
              </div>
              <div class="row__value"><?= Text::e((string) ($request['subject'] ?? '')) ?></div>
              <div class="row__value"><?= Text::e(Admin::humanDate((string) ($request['at'] ?? ''))) ?></div>
              <span class="badge" style="background:<?= Text::e(Requests::statusColor((string) ($request['status'] ?? 'new'))) ?>"><?= Text::e(Requests::statusLabel((string) ($request['status'] ?? 'new'))) ?></span>
              <div class="row__actions"><a class="link-underline" href="<?= Text::e(Router::adminUrl('requests')) ?>">Ouvrir</a></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="panel panel--yellow">
      <h2 style="margin:0">Garde-fous</h2>
      <p class="muted" style="margin:10px 0 18px">Ce que le back-office fait pour vous à chaque enregistrement.</p>
      <div class="stack">
        <div class="guard">
          <div class="guard__title">Écriture atomique</div>
          <div class="guard__text">Le JSON part dans un fichier temporaire, est relu et validé, puis remplace l'ancien d'un seul coup. Jamais de fichier à moitié écrit en production.</div>
        </div>
        <div class="guard">
          <div class="guard__title">Verrou d'édition</div>
          <div class="guard__text">Une page ouverte est verrouillée 10 minutes. Deux personnes ne peuvent pas écraser leurs textes.</div>
        </div>
        <div class="guard">
          <div class="guard__title">Schéma vérifié</div>
          <div class="guard__text">Un champ ajouté ou manquant ne casse rien : le front applique une valeur par défaut et signale le champ ici.</div>
        </div>
        <div class="guard">
          <div class="guard__title">Retour arrière</div>
          <div class="guard__text">Chaque enregistrement garde la version précédente (30 conservées). Restauration en un clic depuis « Pages & contenus ».</div>
        </div>
      </div>
    </section>
  </div>

  <div class="panels">
    <section class="panel panel--pad">
      <h2 style="margin:0 0 12px;font:800 19px/1 'Bricolage Grotesque',sans-serif">Raccourcis</h2>
      <div class="stack">
        <a class="btn btn--outline" href="<?= Text::e(Router::adminUrl('offices')) ?>">Mettre à jour les disponibilités</a>
        <a class="btn btn--outline" href="<?= Text::e(Router::adminUrl('media')) ?>">Ajouter des photos</a>
        <a class="btn btn--outline" href="<?= Text::e(Router::adminUrl('pages', ['slug' => 'home'])) ?>">Modifier l'accueil</a>
        <form method="post" action="<?= Text::e(Router::adminUrl()) ?>">
          <?= Csrf::field('admin') ?>
          <input type="hidden" name="action" value="ai-reindex">
          <button class="btn btn--outline btn--block" type="submit">Réindexer l'assistant</button>
        </form>
      </div>
    </section>

    <section class="panel panel--pad">
      <h2 style="margin:0 0 12px;font:800 19px/1 'Bricolage Grotesque',sans-serif">État des intégrations</h2>
      <div class="stack">
        <?php
        $integrations = [
            ['Assistant Gemini', Gemini::configured(), Gemini::configured() ? 'Clé en place — modèle ' . Gemini::model() : 'Sans clé : réponses issues de l’index local et des réponses rapides.'],
            ['Avis Google Places', Reviews::configured(), Reviews::configured() ? 'Cache 24 h actif' : 'Sans clé : les avis affichés sont ceux saisis dans content/reviews.json.'],
            ['Traduction assistée', App\Translator::configured(), App\Translator::configured() ? 'Bouton « Traduire en anglais » actif' : 'Sans clé : traduction manuelle uniquement.'],
            ['Envoi des emails', Config::has('SMTP_HOST'), Config::has('SMTP_HOST') ? 'SMTP configuré' : 'Fonction mail() de l’hébergeur.'],
        ];
        foreach ($integrations as [$label, $on, $note]): ?>
          <div class="guard">
            <div class="guard__title"><?= Text::e($label) ?> <span class="badge" style="background:<?= $on ? '#12B39A' : '#EDE5D5' ?>;margin-left:6px"><?= $on ? 'ACTIF' : 'REPLI' ?></span></div>
            <div class="guard__text"><?= Text::e($note) ?></div>
          </div>
        <?php endforeach; ?>
        <a class="btn btn--ink" href="<?= Text::e(Router::adminUrl('settings', ['tab' => 'keys'])) ?>">Configurer les clés API</a>
      </div>
    </section>
  </div>

  <?php if ($missing !== []): ?>
  <section class="panel panel--pad" style="margin-top:18px;background:#FFD100">
    <h2 style="margin:0 0 8px;font:800 19px/1 'Bricolage Grotesque',sans-serif">Champs de contenu manquants</h2>
    <p class="muted" style="margin:0 0 12px">Le front a appliqué une valeur par défaut. À compléter dans « Pages & contenus ».</p>
    <div class="tags">
      <?php foreach (\array_slice($missing, 0, 12) as $path): ?>
        <span class="tag tag--yellow" style="background:#FFF8EA"><?= Text::e($path) ?></span>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</div>
<?= View::admin('_layout_end') ?>
