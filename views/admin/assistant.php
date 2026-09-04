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
 * @var App\Core\Bulle $bulle
 */
use App\Core\Assistant;
use App\Core\Bulle;
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
    <legend>Textes de l’assistant</legend>
    <div class="bo-champ">
      <label for="ia-titre">Titre</label>
      <input id="ia-titre" type="text" name="titre" value="<?= e($reglages['titre'] ?? '') ?>"
             placeholder="Une question ?">
      <p class="bo-aide">Affiché en haut du panneau de discussion, et sur le bouton si vous ne lui donnez pas de libellé propre.</p>
    </div>
    <div class="bo-champ">
      <label for="ia-accueil">Message d’accueil</label>
      <textarea id="ia-accueil" name="accueil" rows="2" placeholder="Posez votre question…"><?= e($reglages['accueil'] ?? '') ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Le bouton sur le site</legend>
    <p class="bo-aide">
      C’est le bouton en bas à droite de chaque page, celui qui ouvre la
      discussion. L’aperçu ci-dessous montre exactement ce que le visiteur verra.
    </p>

    <div class="bo-champ">
      <span class="bo-legende-champ">Forme</span>
      <div class="bo-formes">
        <?php foreach (Bulle::FORMES as $id => $f): ?>
          <label class="bo-forme">
            <input type="radio" name="bulle_forme" value="<?= e($id) ?>"
                   data-bulle-forme<?= $id === $bulle->forme() ? ' checked' : '' ?>>
            <span class="bo-forme__corps">
              <span class="bo-forme__dessin bo-forme__dessin--<?= e($id) ?>" aria-hidden="true">
                <span class="bo-forme__picto"></span>
              </span>
              <span class="bo-forme__nom"><?= e($f['nom']) ?></span>
              <span class="bo-forme__resume"><?= e($f['resume']) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="bo-champ">
      <span class="bo-legende-champ">Animation d’appel</span>
      <p class="bo-aide">
        Un bouton immobile dans un coin passe inaperçu. Celles-ci font un
        mouvement bref, <strong>trois fois de suite après l’arrivée sur la
        page, puis plus jamais</strong> — et elles s’arrêtent net dès que le
        visiteur survole le bouton ou l’a déjà ouvert. Un visiteur dont le
        système demande moins d’animations n’en voit aucune.
      </p>
      <div class="bo-formes">
        <?php foreach (Bulle::ANIMATIONS as $id => $a): ?>
          <label class="bo-forme">
            <input type="radio" name="bulle_animation" value="<?= e($id) ?>"
                   data-bulle-animation<?= $id === $bulle->animation() ? ' checked' : '' ?>>
            <span class="bo-forme__corps bo-forme__corps--anim">
              <span class="bo-forme__nom"><?= e($a['nom']) ?></span>
              <span class="bo-forme__resume"><?= e($a['resume']) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="bo-champ">
      <label for="ia-bulle-libelle">Intitulé</label>
      <input id="ia-bulle-libelle" type="text" name="bulle_libelle" data-bulle-libelle
             maxlength="<?= Bulle::LIBELLE_MAX ?>"
             value="<?= e((string) ($reglages['bulle']['libelle'] ?? '')) ?>"
             placeholder="<?= e($bulle->libelle()) ?>">
      <?php /* La liste des formes qui n'affichent pas l'intitulé est tirée de
               Bulle::FORMES et non écrite ici : une phrase recopiée devient
               fausse à la première forme ajoutée, et personne ne la relit. */
        $muettes = array_values(array_map(
            static fn(array $f): string => '« ' . $f['nom'] . ' »',
            array_filter(Bulle::FORMES, static fn(array $f): bool => !$f['libelle'])
        ));
        $derniere = array_pop($muettes);
        $liste = $muettes === [] ? (string) $derniere
               : implode(', ', $muettes) . ' et ' . $derniere;
      ?>
      <p class="bo-aide">
        <?= Bulle::LIBELLE_MAX ?> caractères au plus. Laissez vide pour reprendre le titre
        de l’assistant. Les formes <?= e($liste) ?> ne l’affichent pas,
        mais le conservent pour les lecteurs d’écran et pour l’infobulle.
      </p>
    </div>

    <div class="bo-champ">
      <label for="ia-bulle-taille">Taille du bouton</label>
      <?php /* Même partage que pour la taille du logo : le nombre est le
               champ et fonctionne sans JavaScript, le curseur n'est que du
               confort et reste caché tant que le script ne l'a pas révélé. */ ?>
      <div class="bo-logo-saisie">
        <input type="range" id="ia-bulle-curseur" hidden data-bulle-curseur
               min="<?= Bulle::TAILLE_MIN ?>" max="<?= Bulle::TAILLE_MAX ?>"
               step="<?= Bulle::TAILLE_PAS ?>" value="<?= $bulle->taille() ?>"
               aria-hidden="true" tabindex="-1">
        <input type="number" id="ia-bulle-taille" name="bulle_taille" data-bulle-taille
               min="<?= Bulle::TAILLE_MIN ?>" max="<?= Bulle::TAILLE_MAX ?>"
               step="<?= Bulle::TAILLE_PAS ?>" value="<?= $bulle->taille() ?>">
        <span class="bo-logo-unite">px</span>
      </div>
      <p class="bo-aide">
        De <?= Bulle::TAILLE_MIN ?> à <?= Bulle::TAILLE_MAX ?> pixels. Le plancher n’est pas
        un goût : sous <?= Bulle::TAILLE_MIN ?> px, le bouton devient trop petit pour être
        touché au pouce sans le manquer.
      </p>
    </div>

    <div class="bo-champ bo-champ--case">
      <label>
        <input type="checkbox" name="bulle_fond_commune" data-bulle-suivre value="1"
               <?= $bulle->suitLaCommune() ? ' checked' : '' ?>>
        Utiliser la couleur de la commune comme fond
      </label>
      <p class="bo-aide">
        Cochée, la bulle suit la couleur réglée dans <strong>Apparence</strong> et
        change avec elle. Décochez pour lui donner une couleur à part — un rouge
        d’alerte, par exemple.
      </p>
    </div>

    <div class="bo-couleurs">
      <label class="bo-couleur-champ<?= $bulle->suitLaCommune() ? ' bo-couleur-champ--inactif' : '' ?>"
             data-bulle-champ-fond data-bulle-commune="<?= e($bulle->fondCommune()) ?>">
        <span>Fond du bouton</span>
        <input type="color" name="bulle_fond" data-bulle-fond value="<?= e($bulle->fond()) ?>">
        <output data-bulle-fond-hex><?= e($bulle->fond()) ?></output>
      </label>
      <label class="bo-couleur-champ">
        <span>Couleur du texte</span>
        <input type="color" name="bulle_texte" data-bulle-texte value="<?= e($bulle->texteChoisi()) ?>">
        <output data-bulle-texte-hex><?= e($bulle->texteChoisi()) ?></output>
      </label>
    </div>

    <?php /* L'aperçu et le rapport mesuré sont rendus par le serveur : sans
             JavaScript, l'écran montre l'état enregistré, exact. Le script ne
             fait que les rafraîchir à chaque mouvement. */ ?>
    <div class="bo-apercu-bulle <?= e($bulle->classe()) ?>" data-bulle-apercu
         style="<?= e($bulle->style()) ?>">
      <span class="bo-apercu-bulle__scene">
        <button type="button" class="assistant__bulle" tabindex="-1" aria-hidden="true">
          <span class="assistant__bulle-icone"></span>
          <span class="assistant__bulle-texte" data-bulle-apercu-texte><?= e($bulle->libelle()) ?></span>
        </button>
      </span>
      <?php /* L'animation ne se joue que trois fois : sans ce bouton, la
               mairie devrait recharger l'écran pour la revoir. Il n'apparaît
               que si le script est là — sans lui, il ne ferait rien. */ ?>
      <p class="bo-apercu-bulle__rejouer">
        <button type="button" class="bo-btn bo-btn--fantome" data-bulle-rejouer hidden>
          Rejouer l’animation
        </button>
      </p>
      <p class="bo-apercu-bulle__note">
        Contraste du libellé sur son fond :
        <strong data-bulle-rapport><?= number_format($bulle->contraste(), 2, ',', ' ') ?>:1</strong>
        — minimum exigé <?= number_format(Bulle::CONTRASTE_MINI, 1, ',', ' ') ?>:1.
      </p>
      <p class="bo-apercu-bulle__corrige"<?= $bulle->corrigee() ? '' : ' hidden' ?> data-bulle-corrige>
        La couleur de texte choisie ne tenait pas ce minimum sur ce fond : sa
        teinte est conservée, sa clarté a été ajustée jusqu’au seuil. Choisissez
        un fond plus sombre ou plus clair pour retrouver la couleur exacte.
      </p>
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
        <?php /* Les attributs d'édition sont posés par editeur.js : sans lui,
                 cette zone reste un simple aperçu et c'est la textarea qui
                 sert. */ ?>
        <div class="bo-editeur__zone" aria-labelledby="ia-notes-label" data-editeur-zone><?= $notes ?></div>
        <?php /* La textarea porte les notes et n'est pas cachée d'avance :
                 c'est le script qui la masque. Vide et cachée comme elle
                 l'était, un enregistrement sans JavaScript effaçait les notes
                 au lieu de les conserver. */ ?>
        <textarea id="ia-notes" name="notes" rows="10" data-editeur-champ><?= e($notes) ?></textarea>
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
