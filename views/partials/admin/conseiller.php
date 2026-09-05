<?php
/**
 * Pastille du conseiller, en bas à droite de chaque écran d'administration.
 *
 * Le fragment se retire de lui-même quand le conseiller est éteint ou qu'il
 * n'a pas de clé : aucune condition à écrire dans le gabarit qui l'appelle.
 *
 * L'échange passe par /admin/conseiller, sur ce domaine et derrière la même
 * protection que le reste du back-office. Le navigateur ne contacte jamais
 * Google : c'est le serveur qui appelle, et la clé reste chez lui.
 *
 * @var App\Core\Conseiller|null $conseiller
 */
if (!isset($conseiller) || !$conseiller->actif()) {
    return;
}

use App\Core\Csrf;
?>
<?php /* Les adresses viennent de url(), qui sait ajouter le préfixe d'une
         implantation en sous-dossier. Le script ne les invente pas : c'est la
         même règle que le fragment de l'assistant public, dont le JS lit
         l'attribut action du formulaire plutôt que d'écrire /api/assistant. */ ?>
<div class="bo-conseil" data-conseil data-conseil-jeton="<?= e(Csrf::jeton()) ?>"
     data-conseil-adresse="<?= e(url('/admin/conseiller')) ?>"
     data-conseil-adresse-bilan="<?= e(url('/admin/conseiller/bilan')) ?>">
  <?php /* Le libellé reste dans le document même quand la pastille est
           réduite : masqué en CSS, il est lu par les lecteurs d'écran et
           donne au bouton son nom accessible. Un aria-label en plus le
           remplacerait au lieu de s'y ajouter. */ ?>
  <button type="button" class="bo-conseil__pastille" data-conseil-ouvrir
          aria-expanded="false" aria-controls="bo-conseil-panneau">
    <span class="bo-conseil__pastille-icone" aria-hidden="true">✦</span>
    <span class="bo-conseil__pastille-nom">Conseiller</span>
  </button>

  <section class="bo-conseil__panneau" id="bo-conseil-panneau"
           aria-labelledby="bo-conseil-titre" hidden>
    <header class="bo-conseil__entete">
      <h2 class="bo-conseil__titre" id="bo-conseil-titre">Conseiller</h2>
      <?php /* Un bilan fait couramment huit recommandations, soit huit cents
               pixels de texte : il se lisait par une fenêtre de trois cent
               cinquante. Le panneau s'agrandit donc à la demande, plutôt que
               de choisir une taille qui serait trop grande pour une question
               courte et trop petite pour un bilan. */ ?>
      <button type="button" class="bo-conseil__agrandir" data-conseil-agrandir
              aria-pressed="false">
        <span class="bo-conseil__agrandir-nom">Agrandir</span>
      </button>
      <button type="button" class="bo-conseil__fermer" data-conseil-fermer
              aria-label="Fermer le conseiller">✕</button>
    </header>

    <div class="bo-conseil__onglets" role="tablist" aria-label="Conseiller">
      <button type="button" class="bo-conseil__onglet est-actif" role="tab"
              id="bo-conseil-onglet-echange" aria-selected="true"
              aria-controls="bo-conseil-echange" data-conseil-onglet="echange">
        Poser une question
      </button>
      <button type="button" class="bo-conseil__onglet" role="tab"
              id="bo-conseil-onglet-bilan" aria-selected="false"
              aria-controls="bo-conseil-bilan" data-conseil-onglet="bilan">
        Bilan du site
      </button>
    </div>

    <?php /* -------------------------------------------------- conversation */ ?>
    <div class="bo-conseil__vue" id="bo-conseil-echange" role="tabpanel"
         aria-labelledby="bo-conseil-onglet-echange" data-conseil-vue="echange">
      <div class="bo-conseil__fil" data-conseil-fil aria-live="polite" tabindex="0">
        <p class="bo-conseil__accueil">
          Je vois le contenu du site, ses chiffres de fréquentation et les
          questions que les administrés posent à l’assistant. Demandez-moi
          par quoi commencer, comment reformuler une fiche, ou ce qui manque.
        </p>
      </div>

      <?php /* L'étiquette est lue mais pas affichée, et le bouton se place à
               côté du champ plutôt qu'en dessous : les trois lignes de chrome
               d'origine mangeaient cent soixante-dix pixels sur cinq cent
               quarante, c'est-à-dire l'essentiel de la place où l'on lit. */ ?>
      <form class="bo-conseil__saisie bo-conseil__saisie--ligne" data-conseil-form>
        <label class="bo-visuellement-cache" for="bo-conseil-question">Votre question</label>
        <textarea id="bo-conseil-question" name="question" rows="1"
                  data-conseil-question
                  placeholder="Par quoi devrais-je commencer ?"></textarea>
        <button class="bo-btn bo-btn--petit" type="submit" data-conseil-envoyer>Envoyer</button>
      </form>
    </div>

    <?php /* --------------------------------------------------------- bilan */ ?>
    <div class="bo-conseil__vue" id="bo-conseil-bilan" role="tabpanel"
         aria-labelledby="bo-conseil-onglet-bilan" data-conseil-vue="bilan" hidden>
      <div class="bo-conseil__bilan" data-conseil-bilan aria-live="polite">
        <p class="bo-conseil__accueil">
          Une revue complète du site : ce qui manque, ce qui est mal dit, ce
          que personne ne lit, et par quoi commencer. Elle prend une minute et
          n’est lancée que lorsque vous le demandez.
        </p>
      </div>
      <?php /* Le bouton est en pleine largeur tant qu'aucun bilan n'existe :
               c'est alors la seule action de l'écran. Une fois le bilan rendu,
               le script le passe en retrait et le renomme — ce qui compte
               devient la liste, pas le bouton qui l'a produite. */ ?>
      <div class="bo-conseil__saisie bo-conseil__saisie--action">
        <button class="bo-btn bo-btn--petit" type="button" data-conseil-lancer>
          Faire le bilan
        </button>
      </div>
    </div>
  </section>
</div>
