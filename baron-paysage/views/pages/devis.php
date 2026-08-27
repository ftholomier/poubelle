<?php
/**
 * Page « Demander un devis » : le formulaire, et ce qui se passe après.
 *
 * Les quatre étapes posées au-dessus du formulaire répondent à la question
 * qui retient la main sur le bouton d'envoi : « et ensuite, il se passe
 * quoi ? ». Un formulaire seul laisse cette question sans réponse.
 *
 * @var array $page
 * @var array $erreurs
 * @var array $valeurs   valeurs ressaisies après une erreur
 * @var App\Core\Antispam $antispam
 * @var App\Core\Content $content
 * @var App\Core\View $view
 */
$site = $content->load('site');
$tel  = (string) ($site['contact']['telephone'] ?? '');
$v    = static fn(string $cle): string => (string) ($valeurs[$cle] ?? '');
?>
<?= $view->partial('hero-page', ['hero' => $page['hero']]) ?>

<?php if (!empty($page['etapes'])): ?>
<section class="section">
  <div class="conteneur">
    <div class="section__tete reveler">
      <h2 class="titre-section"><?= e($page['introduction']['titre']) ?></h2>
      <p class="section__chapo"><?= e($page['introduction']['texte']) ?></p>
    </div>
    <ol class="etapes">
      <?php foreach ($page['etapes'] as $etape): ?>
        <li class="etapes__item reveler">
          <p class="etapes__numero"><?= e($etape['numero']) ?></p>
          <h3 class="etapes__titre"><?= e($etape['titre']) ?></h3>
          <p class="etapes__texte"><?= e($etape['texte']) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
<?php endif; ?>

<section class="section section--teinte" id="formulaire">
  <div class="conteneur conteneur--etroit">
    <div class="contact__formulaire reveler">
      <h2 class="formulaire__titre"><?= e($page['formulaire']['titre']) ?></h2>
      <p class="formulaire__intro"><?= e($page['formulaire']['texte']) ?></p>

      <?php if (isset($erreurs['envoi'])): ?>
        <p class="alerte alerte--erreur" role="alert"><?= e($erreurs['envoi']) ?></p>
      <?php endif; ?>

      <form class="formulaire" method="post" action="<?= route('devis') ?>#formulaire" novalidate>
        <div class="champ">
          <label for="f-prenom"><?= e(t('Prénom')) ?></label>
          <input id="f-prenom" type="text" name="prenom" value="<?= e($v('prenom')) ?>" autocomplete="given-name">
        </div>

        <div class="champ">
          <label for="f-nom"><?= e(t('Nom')) ?> <span class="champ__requis" aria-hidden="true">*</span></label>
          <input id="f-nom" type="text" name="nom" value="<?= e($v('nom')) ?>" autocomplete="family-name"
                 required aria-required="true"
                 <?= isset($erreurs['nom']) ? 'aria-invalid="true" aria-describedby="e-nom"' : '' ?>>
          <?php if (isset($erreurs['nom'])): ?>
            <p class="champ__erreur" id="e-nom"><?= e($erreurs['nom']) ?></p>
          <?php endif; ?>
        </div>

        <div class="champ">
          <label for="f-email"><?= e(t('Adresse électronique')) ?> <span class="champ__requis" aria-hidden="true">*</span></label>
          <input id="f-email" type="email" name="email" value="<?= e($v('email')) ?>" autocomplete="email"
                 required aria-required="true"
                 <?= isset($erreurs['email']) ? 'aria-invalid="true" aria-describedby="e-email"' : '' ?>>
          <?php if (isset($erreurs['email'])): ?>
            <p class="champ__erreur" id="e-email"><?= e($erreurs['email']) ?></p>
          <?php endif; ?>
        </div>

        <div class="champ">
          <label for="f-tel"><?= e(t('Téléphone')) ?></label>
          <input id="f-tel" type="tel" name="tel" value="<?= e($v('tel')) ?>" autocomplete="tel">
        </div>

        <?php /* La localité décide du déplacement : c'est la première chose
                 qu'on demanderait au téléphone, elle est donc demandée ici
                 plutôt que devinée après coup. */ ?>
        <div class="champ champ--large">
          <label for="f-localite"><?= e(t('Localité ou ville du chantier')) ?> <span class="champ__requis" aria-hidden="true">*</span></label>
          <input id="f-localite" type="text" name="localite" value="<?= e($v('localite')) ?>"
                 autocomplete="address-level2" placeholder="<?= e(t('Mathay, Montbéliard, Belfort…')) ?>"
                 required aria-required="true"
                 <?= isset($erreurs['localite']) ? 'aria-invalid="true" aria-describedby="e-localite"' : '' ?>>
          <?php if (isset($erreurs['localite'])): ?>
            <p class="champ__erreur" id="e-localite"><?= e($erreurs['localite']) ?></p>
          <?php endif; ?>
        </div>

        <div class="champ champ--large">
          <label for="f-societe"><?= e(t('Société ou structure')) ?></label>
          <input id="f-societe" type="text" name="societe" value="<?= e($v('societe')) ?>" autocomplete="organization">
        </div>

        <div class="champ champ--large">
          <label for="f-sujet"><?= e(t('Objet de votre demande')) ?></label>
          <select id="f-sujet" name="sujet">
            <option value=""><?= e(t('Choisissez un objet')) ?></option>
            <?php foreach ($page['formulaire']['objets'] as $objet): ?>
              <option value="<?= e($objet) ?>"<?= $v('sujet') === $objet ? ' selected' : '' ?>><?= e($objet) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="champ champ--large">
          <label for="f-message"><?= e(t('Votre projet')) ?> <span class="champ__requis" aria-hidden="true">*</span></label>
          <textarea id="f-message" name="message" rows="7" required aria-required="true"
                    <?= isset($erreurs['message']) ? 'aria-invalid="true" aria-describedby="e-message"' : '' ?>><?= e($v('message')) ?></textarea>
          <?php if (isset($erreurs['message'])): ?>
            <p class="champ__erreur" id="e-message"><?= e($erreurs['message']) ?></p>
          <?php endif; ?>
        </div>

        <?php /* Piège à robots et jeton d'horloge : le module les pose, et
                 c'est lui qui les relit à l'envoi. */ ?>
        <?= $antispam->champs() ?>

        <div class="champ champ--large champ--case">
          <input id="f-consentement" type="checkbox" name="consentement" value="1"
                 required aria-required="true"
                 <?= $v('consentement') !== '' ? 'checked' : '' ?>
                 <?= isset($erreurs['consentement']) ? 'aria-invalid="true" aria-describedby="e-consentement"' : '' ?>>
          <label for="f-consentement">
            <?= e(t('J’accepte que ces informations soient utilisées pour traiter ma demande.')) ?>
            <span class="champ__requis" aria-hidden="true">*</span>
          </label>
          <?php if (isset($erreurs['consentement'])): ?>
            <p class="champ__erreur" id="e-consentement"><?= e($erreurs['consentement']) ?></p>
          <?php endif; ?>
        </div>

        <?php /* Turnstile, s'il a reçu ses clés dans Paramètres ; rien
                 sinon, les barrières natives protégeant seules. */ ?>
        <?= $antispam->widget() ?>

        <p class="formulaire__mention"><?= e($page['formulaire']['mention']) ?></p>

        <div class="formulaire__actions">
          <button class="btn btn--vert" type="submit"><?= e(t('Envoyer ma demande')) ?></button>
          <?php if ($tel !== ''): ?>
            <a class="btn btn--contour" href="<?= e(tel_lien($tel)) ?>">
              <?= e(t('Ou appelez le')) ?> <?= e($tel) ?>
            </a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</section>

<section class="section">
  <div class="conteneur conteneur--etroit">
    <div class="encadre reveler">
      <h3><?= e(t('Une question avant de demander un devis ?')) ?></h3>
      <p><?= e(t('Budget, délais, urbanisme, crédit d’impôt : les questions qui reviennent le plus souvent ont leur page.')) ?></p>
      <a class="btn btn--contour" href="<?= route('faq') ?>"><?= e(t('Voir les questions fréquentes')) ?></a>
    </div>
  </div>
</section>
