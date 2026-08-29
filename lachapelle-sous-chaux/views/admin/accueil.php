<?php
/**
 * Page d'accueil.
 *
 * @var array $accueil
 * @var string[] $medias
 * @var App\Core\View $view
 */
use App\Core\Csrf;

$indicateurs = implode("\n", array_map(
    static fn(array $i): string => ($i['valeur'] ?? '') . ' | ' . ($i['unite'] ?? '') . ' | ' . ($i['libelle'] ?? ''),
    $accueil['indicateurs']['items'] ?? []
));
$points = implode("\n", array_map(
    static fn(array $p): string => ($p['titre'] ?? '') . ' | ' . ($p['texte'] ?? ''),
    $accueil['village']['points'] ?? []
));
$pratique = implode("\n", array_map(
    static fn(array $i): string => ($i['icone'] ?? 'horaires') . ' | ' . ($i['libelle'] ?? '')
        . ' | ' . ($i['valeur'] ?? '') . ' | ' . ($i['precision'] ?? '') . ' | ' . ($i['lien'] ?? ''),
    $accueil['pratique'] ?? []
));
$rubriques = implode("\n", array_map(
    static fn(array $r): string => ($r['icone'] ?? 'document') . ' | ' . ($r['titre'] ?? '')
        . ' | ' . ($r['texte'] ?? '') . ' | ' . ($r['lien']['url'] ?? '/'),
    $accueil['rubriques']['items'] ?? []
));
?>
<form class="bo-form" method="post" action="<?= url('/admin/accueil') ?>" enctype="multipart/form-data">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Bandeau d’accueil</legend>
    <div class="bo-champ">
      <label for="a-hsur">Sur-titre</label>
      <input id="a-hsur" type="text" name="hero_surtitre" value="<?= e($accueil['hero']['surtitre']) ?>">
    </div>
    <div class="bo-champ">
      <label for="a-htit">Titre principal (H1)</label>
      <input id="a-htit" type="text" name="hero_titre" value="<?= e($accueil['hero']['titre']) ?>">
    </div>
    <div class="bo-champ">
      <label for="a-htex">Texte d’introduction</label>
      <textarea id="a-htex" name="hero_texte" rows="3"><?= e($accueil['hero']['texte']) ?></textarea>
    </div>
    <div class="bo-champ bo-champ--court">
      <label for="a-voile">Assombrissement de la photo</label>
      <span class="bo-glissiere">
        <input id="a-voile" type="range" name="hero_voile" min="0" max="100" step="5"
               value="<?= (int) ($accueil['hero']['voile'] ?? 100) ?>" data-valeur-de="a-voile-vu">
        <output id="a-voile-vu" for="a-voile"><?= (int) ($accueil['hero']['voile'] ?? 100) ?> %</output>
      </span>
      <p class="bo-aide">0 % : photo nue. 100 % : voile complet. En dessous de 60 %,
        vérifiez que le titre blanc reste lisible sur vos photos claires.</p>
    </div>

    <div class="bo-champ">
      <label>Photo du bandeau</label>
      <p class="bo-aide">Photo de repli : elle s’affiche si le diaporama ci-dessous
        ne contient aucune vue active.</p>
      <?= $view->partial('admin/choix-photo', [
          'medias' => $medias, 'nom' => 'hero_image', 'id' => 'hero',
          'choisie' => $accueil['hero']['image'], 'vide' => '',
      ]) ?>
    </div>
  </fieldset>

  <fieldset>
    <legend>Diaporama du bandeau</legend>

    <div class="bo-champ bo-champ--court">
      <label for="a-pause">Temps de pause sur chaque photo</label>
      <input id="a-pause" type="number" name="diapo_pause" min="2" max="30" step="1"
             value="<?= (int) ($accueil['hero']['diaporama']['pause'] ?? 6) ?>">
      <p class="bo-aide">En secondes, hors fondu. Le fondu dure 1,8 s de plus.</p>
    </div>

    <div class="bo-champ bo-champ--case">
      <label for="a-alea">
        <input id="a-alea" type="checkbox" name="diapo_aleatoire" value="1"
               <?= !empty($accueil['hero']['diaporama']['aleatoire']) ? 'checked' : '' ?>>
        Ordre aléatoire
      </label>
      <p class="bo-aide">
        Les photos actives sont tirées dans un ordre différent à chaque visite,
        et le visiteur qui revient ne retombe pas sur la même première image.
        L’ordre rangé ci-dessous est conservé : décochez pour le retrouver.
      </p>
    </div>

    <div class="bo-champ">
      <label>Photos du diaporama</label>
      <p class="bo-aide">Glissez une ligne pour la déplacer, ou servez-vous des
        flèches. Une vue masquée reste dans la liste sans s’afficher sur le site.
        Un retrait n’est définitif qu’une fois la page enregistrée.</p>

      <ol class="bo-diapos" data-diapos>
        <?php foreach ($diapos as $rang => $vue): ?>
          <li class="bo-diapo" data-diapo draggable="true">
            <span class="bo-diapo__poignee" aria-hidden="true">⣿</span>
            <img src="<?= image($vue['image'], true) ?>" alt="">
            <span class="bo-diapo__nom"><?= e(basename($vue['image'])) ?></span>
            <input type="hidden" name="diapo_image[]" value="<?= e($vue['image']) ?>">
            <input type="hidden" name="diapo_etat[]" value="<?= !empty($vue['actif']) ? '1' : '0' ?>"
                   data-diapo-etat>
            <button class="bo-bascule" type="button" role="switch" data-diapo-bascule
                    aria-checked="<?= !empty($vue['actif']) ? 'true' : 'false' ?>">
              <span class="bo-bascule__piste" aria-hidden="true"></span>
              <span class="bo-bascule__nom"><?= !empty($vue['actif']) ? 'Affichée' : 'Masquée' ?></span>
            </button>
            <span class="bo-diapo__ordre">
              <button type="button" data-diapo-monter aria-label="Monter">▲</button>
              <button type="button" data-diapo-descendre aria-label="Descendre">▼</button>
            </span>
            <button class="bo-diapo__retrait" type="button" data-diapo-retirer
                    aria-label="Retirer <?= e(basename($vue['image'])) ?> du diaporama">Retirer</button>
          </li>
        <?php endforeach; ?>
      </ol>

      <?php if ($diapos === []): ?>
        <p class="bo-vide" data-diapos-vide>Aucune vue : le bandeau affichera la photo de repli.</p>
      <?php endif; ?>
    </div>

    <div class="bo-champ">
      <label>Ajouter des photos au diaporama</label>
      <p class="bo-aide">Cliquez : la vue s’ajoute aussitôt en fin de liste.</p>
      <div class="bo-choix" data-choix>
        <div class="bo-choix__barre">
          <input type="search" data-choix-filtre placeholder="Filtrer par nom de fichier…"
                 aria-label="Filtrer les photos" autocomplete="off">
          <span class="bo-choix__compte" data-choix-compte aria-live="polite"></span>
        </div>
        <div class="bo-mosaique">
          <?php foreach ($medias as $rang => $media): ?>
            <label class="bo-tuile" data-nom="<?= e(basename($media)) ?>">
              <input type="checkbox" name="diapo_ajout[]" value="<?= e($media) ?>"
                     id="diapo-<?= $rang ?>">
              <img src="<?= image($media, true) ?>" alt="" loading="lazy">
              <span class="bo-tuile__nom"><?= e(basename($media)) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </fieldset>

  <fieldset>
    <legend>Chiffres clés</legend>
    <div class="bo-champ bo-champ--large">
      <label for="a-ititre">Intitulé de la bande</label>
      <input id="a-ititre" type="text" name="indicateurs_titre" value="<?= e($accueil['indicateurs']['titre'] ?? '') ?>">
    </div>
    <div class="bo-champ">
      <label for="a-ind">Indicateurs</label>
      <textarea id="a-ind" name="indicateurs" rows="5"><?= e($indicateurs) ?></textarea>
      <p class="bo-aide">
        Un par ligne : <code>valeur | unité | libellé</code>.
        Par exemple <code>748 |  | habitants (recensement 2022)</code>. Laissez
        l’unité vide quand il n’y en a pas. Quatre chiffres tiennent sur une
        ligne à l’écran.
      </p>
    </div>
  </fieldset>

  <fieldset>
    <legend>Bandeau pratique</legend>
    <p class="bo-aide">
      Les quatre repères posés sous le bandeau : horaires, téléphone, permanence
      des élus, adresse. C’est ce qu’on cherche en haut d’un site de mairie,
      avant même les démarches.
    </p>
    <div class="bo-champ bo-champ--large">
      <label for="a-prat">Les repères</label>
      <textarea id="a-prat" name="pratique" rows="6"><?= e($pratique) ?></textarea>
      <p class="bo-aide">
        Un par ligne : <code>picto | libellé | valeur | précision | lien</code>.
        Les pictos disponibles sont <code>horaires</code>, <code>telephone</code>,
        <code>mairie</code>, <code>adresse</code>, <code>courriel</code>,
        <code>urgence</code>. La précision et le lien peuvent rester vides.
      </p>
    </div>
  </fieldset>

  <fieldset>
    <legend>Bloc « Vos démarches »</legend>
    <p class="bo-aide">Les six premières démarches publiées sont reprises ici. Elles se modifient dans <a href="<?= url('/admin/demarches') ?>">Démarches</a>.</p>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="a-dsur">Sur-titre</label>
        <input id="a-dsur" type="text" name="demarches_surtitre" value="<?= e($accueil['demarches']['surtitre'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="a-dtit">Titre</label>
        <input id="a-dtit" type="text" name="demarches_titre" value="<?= e($accueil['demarches']['titre'] ?? '') ?>">
      </div>
    </div>
    <div class="bo-champ bo-champ--large">
      <label for="a-dtex">Texte</label>
      <textarea id="a-dtex" name="demarches_texte" rows="2"><?= e($accueil['demarches']['texte'] ?? '') ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Bloc « La vie du village »</legend>
    <p class="bo-aide">
      Les trois dernières <a href="<?= url('/admin/actualites') ?>">actualités</a>
      et les trois prochains rendez-vous de l’<a href="<?= url('/admin/listes/agenda') ?>">agenda</a>
      s’affichent ici d’eux-mêmes.
    </p>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="a-vsur">Sur-titre</label>
        <input id="a-vsur" type="text" name="vie_surtitre" value="<?= e($accueil['vie']['surtitre'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="a-vtit">Titre</label>
        <input id="a-vtit" type="text" name="vie_titre" value="<?= e($accueil['vie']['titre'] ?? '') ?>">
      </div>
    </div>
    <div class="bo-champ bo-champ--large">
      <label for="a-vtex">Texte</label>
      <textarea id="a-vtex" name="vie_texte" rows="2"><?= e($accueil['vie']['texte'] ?? '') ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Bloc « Le village »</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="a-lsur">Sur-titre</label>
        <input id="a-lsur" type="text" name="village_surtitre" value="<?= e($accueil['village']['surtitre'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="a-ltit">Titre</label>
        <input id="a-ltit" type="text" name="village_titre" value="<?= e($accueil['village']['titre'] ?? '') ?>">
      </div>
    </div>
    <div class="bo-champ bo-champ--large">
      <label for="a-ltex">Texte</label>
      <textarea id="a-ltex" name="village_texte" rows="6"><?= e(implode("\n\n", (array) ($accueil['village']['paragraphes'] ?? []))) ?></textarea>
      <p class="bo-aide">Séparez les paragraphes par une ligne vide.</p>
    </div>
    <div class="bo-champ bo-champ--large">
      <label for="a-lpts">Points numérotés</label>
      <textarea id="a-lpts" name="village_points" rows="4"><?= e($points) ?></textarea>
      <p class="bo-aide">Un par ligne : <code>titre | texte</code>. Les numéros sont posés automatiquement.</p>
    </div>
    <div class="bo-champ">
      <label>Photo</label>
      <?= $view->partial('admin/choix-photo', [
          'medias' => $medias, 'nom' => 'village_image', 'id' => 'vil',
          'choisie' => $accueil['village']['image'] ?? '', 'vide' => '',
      ]) ?>
    </div>
    <div class="bo-champ bo-champ--large">
      <label for="a-lalt">Description de la photo</label>
      <input id="a-lalt" type="text" name="village_alt" value="<?= e($accueil['village']['image_alt'] ?? '') ?>">
      <p class="bo-aide">Décrivez la scène, pas le fichier : c’est ce que lit une personne aveugle.</p>
    </div>
  </fieldset>

  <fieldset>
    <legend>Bloc « Trouver autre chose »</legend>
    <p class="bo-aide">
      Les six portes d’entrée en bas de page, pour celui qui n’a pas trouvé sa
      démarche.
    </p>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="a-rsur">Sur-titre</label>
        <input id="a-rsur" type="text" name="rubriques_surtitre" value="<?= e($accueil['rubriques']['surtitre'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="a-rtit">Titre</label>
        <input id="a-rtit" type="text" name="rubriques_titre" value="<?= e($accueil['rubriques']['titre'] ?? '') ?>">
      </div>
    </div>
    <div class="bo-champ bo-champ--large">
      <label for="a-ritems">Les rubriques</label>
      <textarea id="a-ritems" name="rubriques_items" rows="8"><?= e($rubriques) ?></textarea>
      <p class="bo-aide">
        Une par ligne : <code>picto | titre | texte | adresse</code>. Six
        rubriques tiennent sur deux rangées.
      </p>
    </div>
  </fieldset>

  <fieldset>
    <legend>Référencement</legend>
    <div class="bo-champ">
      <label for="a-meta">Description affichée dans Google</label>
      <textarea id="a-meta" name="meta_description" rows="2"><?= e($accueil['meta']['description'] ?? '') ?></textarea>
      <p class="bo-aide">Entre 120 et 160 caractères.</p>
    </div>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer</button>
</form>
