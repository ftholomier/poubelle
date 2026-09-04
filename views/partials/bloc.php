<?php
/**
 * Rendu d'un bloc de contenu.
 *
 * Une mairie publie une quinzaine de pages qui se ressemblent toutes : un
 * texte, une liste de liens, un tableau d'horaires, quelques contacts, des
 * PDF à télécharger. Écrire une vue par page reviendrait à recopier quinze
 * fois la même chose, et à obliger à toucher au code pour ajouter une page.
 *
 * Chaque bloc porte donc un `type`, et c'est le seul endroit qui sait le
 * rendre. Ajouter une page ne coûte plus qu'un fichier JSON ; ajouter une
 * forme de contenu ne coûte qu'un `case` ici et sa règle CSS.
 *
 * Un type inconnu n'affiche rien plutôt que de casser la page : un contenu
 * saisi pour une version ultérieure du site ne doit pas produire d'erreur
 * sur celle qui est en ligne.
 *
 * @var array $bloc
 * @var App\Core\View $view
 */
$type = (string) ($bloc['type'] ?? 'texte');

// riche() est la seule sortie non échappée de ce gabarit. Elle ne fait pas
// confiance à ce qu'elle reçoit : App\Core\TexteRiche ramène le contenu à une
// liste blanche de balises et de classes de charte à chaque affichage. Elle
// accepte aussi l'ancien tableau de paragraphes en texte brut, ce qui laisse
// valable un contenu écrit avant l'éditeur.
?>
<?php if (!empty($bloc['surtitre'])): ?>
  <p class="surtitre<?= ($bloc['fond'] ?? '') === 'sombre' ? ' surtitre--clair' : '' ?>"><?= e($bloc['surtitre']) ?></p>
<?php endif; ?>

<?php if (!empty($bloc['titre']) && !in_array($type, ['duo', 'citation'], true)): ?>
  <h2 class="titre-section"><?= e($bloc['titre']) ?></h2>
<?php endif; ?>

<?php if (!empty($bloc['chapo'])): ?>
  <p class="chapo"><?= e($bloc['chapo']) ?></p>
<?php endif; ?>

<?php switch ($type):

// -------------------------------------------------------------- texte
case 'texte': ?>
  <div class="bloc-texte">
    <?= riche($bloc['paragraphes'] ?? '') ?>
    <?php if (!empty($bloc['liste'])): ?>
      <ul class="liste-cochee">
        <?php foreach ($bloc['liste'] as $ligne): ?><li><?= e($ligne) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if (!empty($bloc['lien']['url'])): ?>
      <p><a class="lien-fleche" href="<?= lien($bloc['lien']['url']) ?>"<?= str_starts_with($bloc['lien']['url'], 'http') ? ' target="_blank" rel="noopener"' : '' ?>><?= e($bloc['lien']['libelle'] ?? '') ?></a></p>
    <?php endif; ?>
  </div>
<?php break;

// ---------------------------------------------------------------- duo
case 'duo': ?>
  <?php /* `sens` porte deux réglages : le côté de l'image, et son cadrage.
           Une photo verticale — un portrait, un clocher — perd sa tête dans le
           cadre 4/3 réservé aux paysages. */ ?>
  <div class="duo<?= ($bloc['sens'] ?? '') === 'image-droite' ? ' duo--inverse' : '' ?><?= ($bloc['cadrage'] ?? '') === 'portrait' ? ' duo--portrait' : '' ?>">
    <div class="duo__media">
      <img src="<?= image($bloc['image'] ?? '') ?>" alt="<?= e($bloc['image_alt'] ?? '') ?>" loading="lazy">
    </div>
    <div class="duo__texte">
      <?php if (!empty($bloc['titre'])): ?><h2 class="titre-section"><?= e($bloc['titre']) ?></h2><?php endif; ?>
      <?= riche($bloc['paragraphes'] ?? '') ?>

      <?php if (!empty($bloc['points'])): ?>
        <ul class="points">
          <?php foreach ($bloc['points'] as $rang => $point): ?>
            <li class="points__item">
              <span class="points__numero"><?= e($point['numero'] ?? str_pad((string) ($rang + 1), 2, '0', STR_PAD_LEFT)) ?></span>
              <div>
                <h3 class="points__titre"><?= e($point['titre'] ?? '') ?></h3>
                <p><?= e($point['texte'] ?? '') ?></p>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if (!empty($bloc['liste'])): ?>
        <ul class="liste-cochee">
          <?php foreach ($bloc['liste'] as $ligne): ?><li><?= e($ligne) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if (!empty($bloc['lien']['url'])): ?>
        <a class="lien-fleche" href="<?= lien($bloc['lien']['url']) ?>"><?= e($bloc['lien']['libelle'] ?? '') ?></a>
      <?php endif; ?>
    </div>
  </div>
<?php break;

// ------------------------------------------------------------- cartes
case 'cartes': ?>
  <ul class="cartes cartes--rubriques">
    <?php foreach ($bloc['items'] ?? [] as $item): ?>
      <?php $url = (string) ($item['lien']['url'] ?? ''); ?>
      <li class="carte-rubrique">
        <?php if ($url !== ''): ?><a href="<?= lien($url) ?>"><?php else: ?><div><?php endif; ?>
          <span class="carte-rubrique__icone" aria-hidden="true">
            <?= $view->partial('icones', ['nom' => $item['icone'] ?? 'document']) ?>
          </span>
          <h3 class="carte-rubrique__titre"><?= e($item['titre'] ?? '') ?></h3>
          <p class="carte-rubrique__texte"><?= e($item['texte'] ?? '') ?></p>
          <?php if ($url !== ''): ?>
            <span class="carte-rubrique__lien lien-fleche"><?= e($item['lien']['libelle'] ?? t('En savoir plus')) ?></span>
          <?php endif; ?>
        <?php if ($url !== ''): ?></a><?php else: ?></div><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php break;

// -------------------------------------------------------------- liens
case 'liens': ?>
  <ul class="liens-utiles">
    <?php foreach ($bloc['items'] ?? [] as $item): ?>
      <?php
      $url = (string) ($item['url'] ?? '');
      // Un lien vers un autre site s'ouvre dans un nouvel onglet et l'annonce :
      // sans cela, l'administré perd le site de sa commune sans comprendre
      // qu'il vient de changer de domaine.
      $externe = str_starts_with($url, 'http');
      ?>
      <li class="liens-utiles__item">
        <a href="<?= $externe ? e($url) : lien($url) ?>"<?= $externe ? ' target="_blank" rel="noopener"' : '' ?>>
          <span class="liens-utiles__titre">
            <?= e($item['titre'] ?? '') ?>
            <?php if ($externe): ?>
              <span class="liens-utiles__externe" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'lien-externe']) ?></span>
              <span class="sr-only"> — <?= e(t('ouvre un nouvel onglet')) ?></span>
            <?php endif; ?>
          </span>
          <?php if (!empty($item['texte'])): ?>
            <span class="liens-utiles__texte"><?= e($item['texte']) ?></span>
          <?php endif; ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php break;

// ----------------------------------------------------------- contacts
case 'contacts': ?>
  <ul class="fiches-contact">
    <?php foreach ($bloc['items'] ?? [] as $item): ?>
      <li class="fiche-contact">
        <?php if (!empty($item['role'])): ?><p class="surtitre"><?= e($item['role']) ?></p><?php endif; ?>
        <h3 class="fiche-contact__nom"><?= e($item['nom'] ?? '') ?></h3>
        <?php if (!empty($item['texte'])): ?><p class="fiche-contact__texte"><?= e($item['texte']) ?></p><?php endif; ?>
        <?php if (!empty($item['adresse'])): ?>
          <address class="fiche-contact__adresse"><?= nl2br(e($item['adresse'])) ?></address>
        <?php endif; ?>
        <ul class="fiche-contact__liens">
          <?php if (!empty($item['tel'])): ?>
            <li><a href="<?= e(tel_lien($item['tel'])) ?>">
              <span aria-hidden="true"><?= $view->partial('icones', ['nom' => 'telephone']) ?></span><?= e($item['tel']) ?>
            </a></li>
          <?php endif; ?>
          <?php if (!empty($item['email'])): ?>
            <li><a href="mailto:<?= e($item['email']) ?>">
              <span aria-hidden="true"><?= $view->partial('icones', ['nom' => 'courriel']) ?></span><?= e($item['email']) ?>
            </a></li>
          <?php endif; ?>
          <?php /* Le protocole est vérifié à l'affichage : la saisie vient du
                   back-office, mais un « javascript: » recopié dans le champ
                   ferait de la fiche de contact un vecteur d'exécution. */ ?>
          <?php if (preg_match('~^https?://~i', (string) ($item['site'] ?? '')) === 1): ?>
            <li><a href="<?= e($item['site']) ?>" target="_blank" rel="noopener">
              <span aria-hidden="true"><?= $view->partial('icones', ['nom' => 'lien-externe']) ?></span><?= e(t('Site internet')) ?>
              <span class="sr-only"> — <?= e($item['nom'] ?? '') ?>, <?= e(t('ouvre un nouvel onglet')) ?></span>
            </a></li>
          <?php endif; ?>
        </ul>
      </li>
    <?php endforeach; ?>
  </ul>
<?php break;

// ---------------------------------------------------------- documents
case 'documents': ?>
  <?= $view->partial('documents', ['documents' => $bloc['items'] ?? []]) ?>
<?php break;

// ------------------------------------------------------------- étapes
case 'etapes': ?>
  <ol class="etapes">
    <?php foreach ($bloc['items'] ?? [] as $rang => $etape): ?>
      <li class="etapes__item">
        <span class="etapes__numero"><?= str_pad((string) ($rang + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <h3 class="etapes__titre"><?= e($etape['titre'] ?? '') ?></h3>
        <p class="etapes__texte"><?= e($etape['texte'] ?? '') ?></p>
      </li>
    <?php endforeach; ?>
  </ol>
<?php break;

// ------------------------------------------------------------ tableau
case 'tableau': ?>
  <?php /* Le tableau déborde par construction sur un téléphone : c'est lui qui
           défile dans son cadre, jamais la page. */ ?>
  <div class="tableau-cadre" tabindex="0" role="group" aria-label="<?= e($bloc['titre'] ?? t('Tableau')) ?>">
    <table class="tableau">
      <?php if (!empty($bloc['entetes'])): ?>
        <thead><tr><?php foreach ($bloc['entetes'] as $th): ?><th scope="col"><?= e($th) ?></th><?php endforeach; ?></tr></thead>
      <?php endif; ?>
      <tbody>
        <?php foreach ($bloc['lignes'] ?? [] as $ligne): ?>
          <tr>
            <th scope="row"><?= e($ligne['libelle'] ?? '') ?></th>
            <?php foreach ((array) ($ligne['valeurs'] ?? [$ligne['valeur'] ?? '']) as $v): ?>
              <td><?= e($v) ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php break;

// ------------------------------------------------------------ encadré
case 'encadre': ?>
  <aside class="encadre encadre--<?= e($bloc['ton'] ?? 'info') ?>">
    <span class="encadre__picto" aria-hidden="true">
      <?= $view->partial('icones', ['nom' => ($bloc['ton'] ?? 'info') === 'alerte' ? 'alerte' : 'information']) ?>
    </span>
    <div class="encadre__corps">
      <?php /* Un h2, et non un h3 : l'encadré est souvent le premier bloc
               d'une page, et son titre suit alors directement le h1 du
               bandeau. Un h3 y créerait un saut de niveau — relevé par
               l'auditeur de mise en page sur la fiche « Carte d'identité ». */ ?>
      <?php if (!empty($bloc['intitule'])): ?><h2 class="encadre__titre"><?= e($bloc['intitule']) ?></h2><?php endif; ?>
      <?= riche($bloc['paragraphes'] ?? '') ?>
      <?php if (!empty($bloc['lien']['url'])): ?>
        <a class="lien-fleche" href="<?= lien($bloc['lien']['url']) ?>"<?= str_starts_with($bloc['lien']['url'], 'http') ? ' target="_blank" rel="noopener"' : '' ?>><?= e($bloc['lien']['libelle'] ?? '') ?></a>
      <?php endif; ?>
    </div>
  </aside>
<?php break;

// ----------------------------------------------------------- citation
case 'citation': ?>
  <figure class="citation__bloc">
    <span class="citation__feuille" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'clocher']) ?></span>
    <blockquote class="citation__texte"><p><?= e($bloc['texte'] ?? '') ?></p></blockquote>
    <?php if (!empty($bloc['auteur'])): ?>
      <figcaption class="citation__auteur"><?= e($bloc['auteur']) ?></figcaption>
    <?php endif; ?>
  </figure>
<?php break;

// ----------------------------------------------------------- chiffres
case 'chiffres': ?>
  <ul class="indicateurs__liste">
    <?php foreach ($bloc['items'] ?? [] as $i): ?>
      <li class="indicateurs__item">
        <p class="indicateurs__valeur">
          <?= e($i['valeur'] ?? '') ?><span class="indicateurs__unite"><?= e($i['unite'] ?? '') ?></span>
        </p>
        <p class="indicateurs__libelle"><?= e($i['libelle'] ?? '') ?></p>
      </li>
    <?php endforeach; ?>
  </ul>
<?php break;

// -------------------------------------------------------------- photo
case 'photo': ?>
  <figure class="figure-large">
    <img src="<?= image($bloc['image'] ?? '') ?>" alt="<?= e($bloc['image_alt'] ?? '') ?>" loading="lazy">
    <?php if (!empty($bloc['legende'])): ?>
      <figcaption class="figure-large__legende"><?= e($bloc['legende']) ?></figcaption>
    <?php endif; ?>
  </figure>
<?php break;

// -------------------------------------------------------------- carte
case 'carte': ?>
  <?= $view->partial('carte', ['implantation' => $bloc['implantation'] ?? []]) ?>
<?php break;

endswitch; ?>
