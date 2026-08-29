<?php
/**
 * Écran Assistant IA.
 *
 * @var array $reglages
 * @var array<int, array{id: string, nom: string}> $modeles
 * @var string $erreur
 * @var array $documents
 * @var string $notes
 * @var array $mesure
 * @var array|null $essai
 */
use App\Core\Assistant;
use App\Core\Csrf;

$actif = (bool) ($reglages['actif'] ?? false);
$cleEnregistree = trim((string) ($reglages['cle'] ?? '')) !== '';
$modeleChoisi = (string) ($reglages['modele'] ?? '');
?>
<p class="bo-aide">
  L’assistant répond aux visiteurs depuis une bulle en bas à droite du site.
  Il ne puise que dans <strong>trois sources</strong> : le contenu du site, les
  documents déposés ici, et les notes saisies ci-dessous. Il n’utilise jamais
  ses connaissances générales, et il répond « je n’ai pas cette information »
  plutôt que d’inventer.
</p>
<p class="bo-aide">
  L’appel à Google part du serveur, jamais du navigateur du visiteur : la clé
  reste privée et aucun traceur tiers n’est déposé.
</p>

<form class="bo-form" method="post" action="<?= url('/admin/assistant') ?>">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Activation</legend>
    <div class="bo-champ bo-champ--case">
      <label>
        <input type="checkbox" name="actif" value="1"<?= $actif ? ' checked' : '' ?>>
        Afficher l’assistant sur le site
      </label>
      <p class="bo-aide">Décoché, la bulle disparaît entièrement du site : aucun code n’est envoyé au visiteur.</p>
    </div>
  </fieldset>

  <fieldset>
    <legend>Clé et modèle</legend>
    <div class="bo-champ">
      <label for="ia-cle">Clé d’API Gemini</label>
      <input id="ia-cle" type="password" name="cle" autocomplete="off"
             placeholder="<?= $cleEnregistree ? '•••••••••• (enregistrée)' : 'AIza…' ?>">
      <p class="bo-aide">
        Laissez vide pour conserver la clé déjà enregistrée.
        La clé se crée sur <span class="bo-code">aistudio.google.com</span>, rubrique « API keys ».
      </p>
    </div>

    <div class="bo-champ">
      <label for="ia-modele">Modèle</label>
      <?php if ($modeles === []): ?>
        <input id="ia-modele" type="text" name="modele" value="<?= e($modeleChoisi) ?>"
               placeholder="<?= e(Assistant::MODELE_DEFAUT) ?>">
        <p class="bo-aide">
          La liste n’a pas encore pu être récupérée. Enregistrez d’abord la clé,
          puis cliquez sur « Actualiser la liste des modèles ».
          <?= $erreur !== '' ? '<br>Dernière erreur : ' . e($erreur) : '' ?>
        </p>
      <?php else: ?>
        <select id="ia-modele" name="modele">
          <option value="">Modèle par défaut (<?= e(Assistant::MODELE_DEFAUT) ?>)</option>
          <?php foreach ($modeles as $m): ?>
            <option value="<?= e($m['id']) ?>"<?= $m['id'] === $modeleChoisi ? ' selected' : '' ?>>
              <?= e($m['nom']) ?> — <?= e($m['id']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="bo-aide">
          <?= count($modeles) ?> modèles disponibles pour cette clé. La liste est
          interrogée chez Google, pas écrite dans le code : les nouveaux modèles
          y apparaissent d’eux-mêmes.
        </p>
      <?php endif; ?>
    </div>
  </fieldset>

  <fieldset>
    <legend>Apparence de la bulle</legend>
    <div class="bo-champ">
      <label for="ia-titre">Titre</label>
      <input id="ia-titre" type="text" name="titre" value="<?= e($reglages['titre'] ?? '') ?>"
             placeholder="Une question ?">
    </div>
    <div class="bo-champ">
      <label for="ia-accueil">Message d’accueil</label>
      <textarea id="ia-accueil" name="accueil" rows="2" placeholder="Posez votre question…"><?= e($reglages['accueil'] ?? '') ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Source 1 — le contenu du site</legend>
    <div class="bo-champ bo-champ--case">
      <label>
        <input type="checkbox" name="source_site" value="1"<?= ($reglages['source_site'] ?? true) ? ' checked' : '' ?>>
        Inclure le contenu des pages et des fiches
      </label>
      <p class="bo-aide">
        Textes des pages, gammes, engagements, réalisations et coordonnées, lus
        directement dans les fichiers de contenu. Une modification faite dans le
        back-office est prise en compte à la question suivante.
      </p>
    </div>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer</button>
</form>

<form class="bo-form bo-form--inline" method="post" action="<?= url('/admin/assistant/modeles') ?>">
  <?= Csrf::champ() ?>
  <button class="bo-btn bo-btn--fantome" type="submit">Actualiser la liste des modèles</button>
</form>

<div class="bo-bloc">
  <h2>Source 2 — documents</h2>
  <p class="bo-aide">
    Formats acceptés : <?= e(implode(', ', Assistant::TYPES_DOCUMENTS)) ?>.
    <?= (int) (Assistant::DOCUMENT_MAX / 1048576) ?> Mo par fichier au plus.
    Les PDF sont lus tels quels par le modèle, tableaux compris.
    Les fichiers sont rangés hors de la racine web : ils ne sont pas
    téléchargeables depuis le site.
  </p>

  <?php if ($documents === []): ?>
    <p class="bo-vide">Aucun document pour l’instant.</p>
  <?php else: ?>
    <ul class="bo-liste">
      <?php foreach ($documents as $doc): ?>
        <li class="bo-ligne">
          <div class="bo-ligne__corps">
            <strong><?= e($doc['nom']) ?></strong>
            <span class="bo-ligne__note"><?= strtoupper($doc['extension']) ?> · <?= number_format($doc['poids'] / 1024, 0, ',', ' ') ?> Ko</span>
          </div>
          <div class="bo-ligne__actions">
            <form method="post" action="<?= url('/admin/assistant/documents/retirer') ?>"
                  data-confirmer="Retirer « <?= e($doc['nom']) ?> » des sources ?">
              <?= Csrf::champ() ?>
              <input type="hidden" name="nom" value="<?= e($doc['nom']) ?>">
              <button class="bo-btn bo-btn--petit bo-btn--danger" type="submit">Retirer</button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <form class="bo-form" method="post" action="<?= url('/admin/assistant/documents') ?>" enctype="multipart/form-data">
    <?= Csrf::champ() ?>
    <div class="bo-champ">
      <label for="ia-doc">Ajouter des documents</label>
      <input id="ia-doc" type="file" name="documents[]" multiple
             accept=".pdf,.docx,.txt,.md">
    </div>
    <button class="bo-btn" type="submit">Envoyer</button>
  </form>
</div>

<div class="bo-bloc">
  <h2>Source 3 — notes de la mairie</h2>
  <p class="bo-aide">
    Tout ce qui n’est écrit nulle part ailleurs : délais courants, zones
    d’intervention, réponses aux questions qui reviennent, précisions
    techniques. C’est le moyen le plus rapide de corriger une réponse maladroite.
  </p>

  <form class="bo-form" method="post" action="<?= url('/admin/assistant/notes') ?>">
    <?= Csrf::champ() ?>
    <div class="bo-champ">
      <label for="ia-notes">Notes</label>
      <div class="bo-editeur" data-editeur>
        <div class="bo-editeur__barre" role="toolbar" aria-label="Mise en forme">
          <button type="button" data-commande="bold" title="Gras"><strong>G</strong></button>
          <button type="button" data-commande="italic" title="Italique"><em>I</em></button>
          <button type="button" data-commande="insertUnorderedList" title="Liste à puces">•—</button>
          <button type="button" data-commande="insertOrderedList" title="Liste numérotée">1.</button>
          <button type="button" data-commande="formatBlock" data-valeur="h3" title="Sous-titre">H</button>
          <button type="button" data-commande="removeFormat" title="Enlever la mise en forme">✕</button>
        </div>
        <div class="bo-editeur__zone" contenteditable="true" role="textbox" aria-multiline="true"
             aria-labelledby="ia-notes-label" data-editeur-zone><?= $notes ?></div>
        <textarea id="ia-notes" name="notes" data-editeur-champ hidden></textarea>
      </div>
      <p class="bo-aide" id="ia-notes-label">
        Seule la mise en forme est conservée à l’enregistrement : ni script, ni style collé depuis Word.
      </p>
    </div>
    <button class="bo-btn" type="submit">Enregistrer les notes</button>
  </form>
</div>

<div class="bo-bloc">
  <h2>Vérifier</h2>
  <p class="bo-aide">
    Corpus actuel : <strong><?= number_format($mesure['caracteres'], 0, ',', ' ') ?></strong> caractères
    <?= $mesure['documents'] > 0 ? 'et ' . (int) $mesure['documents'] . ' PDF joint(s)' : '' ?>.
    Posez une question ici avant d’activer l’assistant sur le site.
  </p>

  <?php if ($essai !== null): ?>
    <div class="bo-essai">
      <p class="bo-essai__question"><?= e($essai['question']) ?></p>
      <p class="bo-essai__reponse"><?= nl2br(e($essai['reponse'])) ?></p>
    </div>
  <?php endif; ?>

  <form class="bo-form bo-form--inline" method="post" action="<?= url('/admin/assistant/essai') ?>">
    <?= Csrf::champ() ?>
    <div class="bo-champ">
      <label for="ia-essai" class="bo-visuellement-cache">Question d’essai</label>
      <input id="ia-essai" type="text" name="question" placeholder="Quelles pièces faut-il apporter pour une carte d’identité ?" required>
    </div>
    <button class="bo-btn" type="submit">Poser la question</button>
  </form>
</div>
