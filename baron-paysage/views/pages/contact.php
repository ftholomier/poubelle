<?php
/**
 * Page contact : coordonnées, plans d'accès, et le formulaire de question.
 *
 * Deux formulaires sur le site, parce que deux intentions : celui du devis
 * engage un projet et demande la localité du chantier, l'objet, la société ;
 * celui-ci pose une question et ne demande que de quoi rappeler. Poser ici
 * les questions de l'autre découragerait celui qui veut juste écrire.
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
$mail = (string) ($site['contact']['email'] ?? '');
$reseaux = array_filter((array) ($site['reseaux'] ?? []));
$formulaire = (array) ($page['formulaire'] ?? []);
$v = static fn(string $cle): string => (string) ($valeurs[$cle] ?? '');
?>
<?= $view->partial('hero-page', ['hero' => $page['hero']]) ?>

<section class="section">
  <div class="conteneur">
    <div class="section__tete reveler">
      <h2 class="titre-section"><?= e($page['introduction']['titre']) ?></h2>
      <p class="section__chapo"><?= e($page['introduction']['texte']) ?></p>
    </div>

    <?php /* Les deux implantations côte à côte, chacune avec son plan : c'est
             la question posée le plus souvent, « laquelle est la plus proche
             de chez moi ». Une seule adresse en tête de page obligeait à
             chercher la seconde. */ ?>
    <div class="implantations">
      <?php foreach ($page['implantations'] ?? [] as $implantation): ?>
        <?= $view->partial('carte', ['implantation' => $implantation]) ?>
      <?php endforeach; ?>
    </div>

    <ul class="coordonnees coordonnees--rangee">
      <?php if ($tel !== ''): ?>
        <li class="coordonnees__item reveler">
          <span class="coordonnees__icone" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'telephone']) ?></span>
          <div>
            <p class="coordonnees__label"><?= e(t('Téléphone')) ?></p>
            <a class="coordonnees__valeur" href="<?= e(tel_lien($tel)) ?>"><?= e($tel) ?></a>
          </div>
        </li>
      <?php endif; ?>

      <?php if ($mail !== ''): ?>
        <li class="coordonnees__item reveler">
          <span class="coordonnees__icone" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'courriel']) ?></span>
          <div>
            <p class="coordonnees__label"><?= e(t('Adresse électronique')) ?></p>
            <a class="coordonnees__valeur" href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a>
          </div>
        </li>
      <?php endif; ?>

      <?php if (($site['contact']['horaires'] ?? '') !== ''): ?>
        <li class="coordonnees__item reveler">
          <span class="coordonnees__icone" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'horaires']) ?></span>
          <div>
            <p class="coordonnees__label"><?= e(t('Horaires d’ouverture')) ?></p>
            <p class="coordonnees__valeur"><?= e($site['contact']['horaires']) ?></p>
          </div>
        </li>
      <?php endif; ?>

      <?php if ($reseaux !== []): ?>
        <li class="coordonnees__item reveler">
          <span class="coordonnees__icone" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'instagram']) ?></span>
          <div>
            <p class="coordonnees__label"><?= e(t('Nous suivre')) ?></p>
            <p class="coordonnees__reseaux">
              <?php foreach ($reseaux as $nom => $url): ?>
                <a href="<?= e($url) ?>" target="_blank" rel="noopener me">
                  <span aria-hidden="true"><?= $view->partial('icones', ['nom' => $nom]) ?></span>
                  <?= e(ucfirst($nom)) ?>
                </a>
              <?php endforeach; ?>
            </p>
          </div>
        </li>
      <?php endif; ?>
    </ul>
  </div>
</section>

<?php /* Le formulaire ferme la page : on a d'abord donné l'adresse et le
         numéro, qui répondent seuls à la plupart des visites. Celui qui
         descend jusqu'ici est celui qui préfère écrire. */ ?>
<section class="section section--teinte" id="formulaire">
  <div class="conteneur conteneur--etroit">
    <div class="contact__formulaire reveler">
      <h2 class="formulaire__titre"><?= e($formulaire['titre'] ?? t('Écrivez-nous')) ?></h2>
      <?php if (($formulaire['texte'] ?? '') !== ''): ?>
        <p class="formulaire__intro"><?= e($formulaire['texte']) ?></p>
      <?php endif; ?>

      <?php if (isset($erreurs['envoi'])): ?>
        <p class="alerte alerte--erreur" role="alert"><?= e($erreurs['envoi']) ?></p>
      <?php endif; ?>

      <form class="formulaire" method="post" action="<?= route('contact') ?>#formulaire" novalidate>
        <div class="champ">
          <label for="f-prenom"><?= e(t('Prénom')) ?> <span class="champ__requis" aria-hidden="true">*</span></label>
          <input id="f-prenom" type="text" name="prenom" value="<?= e($v('prenom')) ?>" autocomplete="given-name"
                 required aria-required="true"
                 <?= isset($erreurs['prenom']) ? 'aria-invalid="true" aria-describedby="e-prenom"' : '' ?>>
          <?php if (isset($erreurs['prenom'])): ?>
            <p class="champ__erreur" id="e-prenom"><?= e($erreurs['prenom']) ?></p>
          <?php endif; ?>
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
          <label for="f-tel"><?= e(t('Téléphone')) ?></label>
          <input id="f-tel" type="tel" name="tel" value="<?= e($v('tel')) ?>" autocomplete="tel">
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

        <div class="champ champ--large">
          <label for="f-message"><?= e(t('Votre message')) ?> <span class="champ__requis" aria-hidden="true">*</span></label>
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

        <?php /* Turnstile, s'il a reçu ses clés dans Paramètres ; rien sinon,
                 les barrières natives protégeant seules. */ ?>
        <?= $antispam->widget() ?>

        <?php if (($formulaire['mention'] ?? '') !== ''): ?>
          <p class="formulaire__mention"><?= e($formulaire['mention']) ?></p>
        <?php endif; ?>

        <div class="formulaire__actions">
          <button class="btn btn--vert" type="submit"><?= e(t('Envoyer mon message')) ?></button>
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

<?php if (($page['relance']['titre'] ?? '') !== ''): ?>
<section class="section">
  <div class="conteneur conteneur--etroit">
    <div class="encadre reveler">
      <h3><?= e($page['relance']['titre']) ?></h3>
      <p><?= e($page['relance']['texte']) ?></p>
      <?php if (($page['relance']['lien']['url'] ?? '') !== ''): ?>
        <a class="btn btn--vert" href="<?= lien($page['relance']['lien']['url']) ?>">
          <?= e($page['relance']['lien']['libelle']) ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?= $view->partial('bande-cta') ?>
