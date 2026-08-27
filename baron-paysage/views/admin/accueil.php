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
    $accueil['societe']['points'] ?? []
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
    <div class="bo-champ">
      <label for="a-ind">Indicateurs</label>
      <textarea id="a-ind" name="indicateurs" rows="5"><?= e($indicateurs) ?></textarea>
      <p class="bo-aide">
        Un par ligne : <code>valeur | unité | libellé</code>.
        Par exemple <code>99 | % | Dossiers maîtrisés</code>. Laissez l’unité vide
        pour une année. Quatre indicateurs tiennent sur une ligne à l’écran.
      </p>
    </div>
  </fieldset>

  <fieldset>
    <legend>Bloc « Nos services »</legend>
    <p class="bo-aide">Les services eux-mêmes se modifient dans <a href="<?= url('/admin/services') ?>">Services</a>.</p>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="a-ssur">Sur-titre</label>
        <input id="a-ssur" type="text" name="services_surtitre" value="<?= e($accueil['services']['surtitre']) ?>">
      </div>
      <div class="bo-champ">
        <label for="a-stit">Titre</label>
        <input id="a-stit" type="text" name="services_titre" value="<?= e($accueil['services']['titre']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="a-stex">Texte</label>
      <textarea id="a-stex" name="services_texte" rows="2"><?= e($accueil['services']['texte']) ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Bloc « La société »</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="a-csur">Sur-titre</label>
        <input id="a-csur" type="text" name="societe_surtitre" value="<?= e($accueil['societe']['surtitre']) ?>">
      </div>
      <div class="bo-champ">
        <label for="a-ctit">Titre</label>
        <input id="a-ctit" type="text" name="societe_titre" value="<?= e($accueil['societe']['titre']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="a-ctex">Texte</label>
      <textarea id="a-ctex" name="societe_texte" rows="6"><?= e(implode("\n\n", $accueil['societe']['paragraphes'])) ?></textarea>
      <p class="bo-aide">Une ligne vide sépare deux paragraphes.</p>
    </div>
    <div class="bo-champ">
      <label for="a-cpts">Points forts</label>
      <textarea id="a-cpts" name="societe_points" rows="4"><?= e($points) ?></textarea>
      <p class="bo-aide">Un par ligne : <code>titre | texte</code>. La numérotation est automatique.</p>
    </div>
    <div class="bo-champ">
      <label>Photo</label>
      <?= $view->partial('admin/choix-photo', [
          'medias' => $medias, 'nom' => 'societe_image', 'id' => 'cab',
          'choisie' => $accueil['societe']['image'], 'vide' => '',
      ]) ?>
    </div>
  </fieldset>

  <fieldset>
    <legend>Citation</legend>
    <div class="bo-champ">
      <label for="a-cittex">Phrase mise en avant</label>
      <textarea id="a-cittex" name="citation_texte" rows="3"><?= e($accueil['citation']['texte'] ?? '') ?></textarea>
      <p class="bo-aide">Videz ce champ pour retirer le bloc de la page d’accueil.</p>
    </div>
    <div class="bo-champ">
      <label for="a-citaut">Signature</label>
      <input id="a-citaut" type="text" name="citation_auteur" value="<?= e($accueil['citation']['auteur'] ?? '') ?>">
      <p class="bo-aide">Facultatif : laissé vide, la phrase est présentée sans signature.</p>
    </div>
  </fieldset>

  <fieldset>
    <legend>Bloc « Réalisations »</legend>
    <p class="bo-aide">Les photos viennent de <a href="<?= url('/admin/realisations') ?>">Réalisations</a> : les huit premières sont reprises ici.</p>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="a-rsur">Sur-titre</label>
        <input id="a-rsur" type="text" name="realisations_surtitre" value="<?= e($accueil['realisations']['surtitre'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="a-rtit">Titre</label>
        <input id="a-rtit" type="text" name="realisations_titre" value="<?= e($accueil['realisations']['titre'] ?? '') ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="a-rtex">Texte</label>
      <textarea id="a-rtex" name="realisations_texte" rows="3"><?= e($accueil['realisations']['texte'] ?? '') ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Bloc « Nos valeurs »</legend>
    <p class="bo-aide">Les valeurs elles-mêmes se modifient dans <a href="<?= url('/admin/valeurs') ?>">Valeurs</a>.</p>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="a-vsur">Sur-titre</label>
        <input id="a-vsur" type="text" name="valeurs_surtitre" value="<?= e($accueil['valeurs']['surtitre']) ?>">
      </div>
      <div class="bo-champ">
        <label for="a-vtit">Titre</label>
        <input id="a-vtit" type="text" name="valeurs_titre" value="<?= e($accueil['valeurs']['titre']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="a-vtex">Texte</label>
      <textarea id="a-vtex" name="valeurs_texte" rows="2"><?= e($accueil['valeurs']['texte']) ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Bloc « Sérénité »</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="a-zsur">Sur-titre</label>
        <input id="a-zsur" type="text" name="serenite_surtitre" value="<?= e($accueil['serenite']['surtitre']) ?>">
      </div>
      <div class="bo-champ">
        <label for="a-ztit">Titre</label>
        <input id="a-ztit" type="text" name="serenite_titre" value="<?= e($accueil['serenite']['titre']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="a-ztex">Texte</label>
      <textarea id="a-ztex" name="serenite_texte" rows="3"><?= e($accueil['serenite']['texte']) ?></textarea>
    </div>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="a-zchi">Chiffre mis en avant</label>
        <input id="a-zchi" type="text" name="serenite_chiffre" value="<?= e($accueil['serenite']['chiffre']) ?>">
      </div>
      <div class="bo-champ">
        <label for="a-zleg">Légende</label>
        <input id="a-zleg" type="text" name="serenite_legende" value="<?= e($accueil['serenite']['legende']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="a-zarg">Arguments</label>
      <textarea id="a-zarg" name="serenite_arguments" rows="4"><?= e(implode("\n", $accueil['serenite']['arguments'])) ?></textarea>
      <p class="bo-aide">Un argument par ligne.</p>
    </div>
    <div class="bo-champ">
      <label>Photo</label>
      <?= $view->partial('admin/choix-photo', [
          'medias' => $medias, 'nom' => 'serenite_image', 'id' => 'ser',
          'choisie' => $accueil['serenite']['image'], 'vide' => '',
      ]) ?>
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
