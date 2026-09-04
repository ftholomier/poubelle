<?php
/**
 * Page « Écrire à la mairie » : le formulaire, et ce qui se passe après.
 *
 * Les étapes posées au-dessus du formulaire répondent à la question qui
 * retient la main sur le bouton d'envoi : « et ensuite, il se passe quoi ? ».
 * Un formulaire seul laisse cette question sans réponse, et l'administré
 * rappelle le lendemain pour savoir si sa demande est arrivée.
 *
 * L'objet est une liste fermée plutôt qu'un champ libre : c'est lui qui décide
 * du service qui traitera la demande, et une formulation libre oblige le
 * secrétariat à deviner.
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
$sujets = (array) ($page['sujets'] ?? []);
?>
<?= $view->partial('hero-page', ['hero' => $page['hero']]) ?>

<?php if (!empty($page['etapes'])): ?>
<section class="section">
  <div class="conteneur">
    <div class="section__tete reveler">
      <h2 class="titre-section"><?= e($page['introduction']['titre'] ?? '') ?></h2>
      <p class="section__chapo"><?= e($page['introduction']['texte'] ?? '') ?></p>
    </div>
    <ol class="etapes">
      <?php foreach ($page['etapes'] as $rang => $etape): ?>
        <li class="etapes__item reveler">
          <p class="etapes__numero"><?= e($etape['numero'] ?? str_pad((string) ($rang + 1), 2, '0', STR_PAD_LEFT)) ?></p>
          <h3 class="etapes__titre"><?= e($etape['titre'] ?? '') ?></h3>
          <p class="etapes__texte"><?= e($etape['texte'] ?? '') ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
<?php endif; ?>

<section class="section section--teinte" id="formulaire">
  <div class="conteneur conteneur--etroit">
    <div class="contact__formulaire reveler">
      <h2 class="formulaire__titre"><?= e($page['formulaire']['titre'] ?? t('Votre demande')) ?></h2>
      <?php if (($page['formulaire']['texte'] ?? '') !== ''): ?>
        <p class="formulaire__intro"><?= e($page['formulaire']['texte']) ?></p>
      <?php endif; ?>

      <?php if (isset($erreurs['envoi'])): ?>
        <p class="alerte alerte--erreur" role="alert"><?= e($erreurs['envoi']) ?></p>
      <?php endif; ?>

      <form class="formulaire" method="post" action="<?= route('demande') ?>#formulaire" novalidate>
        <div class="champ champ--large">
          <label for="f-sujet"><?= e(t('Objet de votre demande')) ?> <span class="champ__requis" aria-hidden="true">*</span></label>
          <select id="f-sujet" name="sujet" required aria-required="true"
                  <?= isset($erreurs['sujet']) ? 'aria-invalid="true" aria-describedby="e-sujet"' : '' ?>>
            <option value=""><?= e(t('Choisissez dans la liste…')) ?></option>
            <?php foreach ($sujets as $sujet): ?>
              <option value="<?= e($sujet) ?>"<?= $v('sujet') === $sujet ? ' selected' : '' ?>><?= e($sujet) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($erreurs['sujet'])): ?>
            <p class="champ__erreur" id="e-sujet"><?= e($erreurs['sujet']) ?></p>
          <?php endif; ?>
        </div>

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

        <div class="champ champ--large">
          <label for="f-adresse"><?= e(t('Votre adresse dans la commune')) ?></label>
          <input id="f-adresse" type="text" name="adresse" value="<?= e($v('adresse')) ?>"
                 autocomplete="street-address"
                 placeholder="<?= e(t('rue et numéro — utile pour un signalement ou un dossier d’urbanisme')) ?>">
        </div>

        <div class="champ champ--large">
          <label for="f-message"><?= e(t('Détail de votre demande')) ?> <span class="champ__requis" aria-hidden="true">*</span></label>
          <textarea id="f-message" name="message" rows="8" required aria-required="true"
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
            <?= e(t('J’accepte que ces informations soient utilisées par le secrétariat de mairie pour traiter ma demande.')) ?>
            <span class="champ__requis" aria-hidden="true">*</span>
          </label>
          <?php if (isset($erreurs['consentement'])): ?>
            <p class="champ__erreur" id="e-consentement"><?= e($erreurs['consentement']) ?></p>
          <?php endif; ?>
        </div>

        <?php /* Turnstile, s'il a reçu ses clés dans Paramètres ; rien sinon,
                 les barrières natives protégeant seules. */ ?>
        <?= $antispam->widget() ?>

        <?php if (($page['formulaire']['mention'] ?? '') !== ''): ?>
          <p class="formulaire__mention"><?= e($page['formulaire']['mention']) ?></p>
        <?php endif; ?>

        <div class="formulaire__actions">
          <button class="btn btn--bleu" type="submit"><?= e(t('Envoyer ma demande')) ?></button>
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

<?= $view->partial('sections', ['sections' => $page['sections'] ?? [], 'depart' => 'teinte']) ?>

<?= $view->partial('bande-cta') ?>
