<?php
/**
 * Page contact : coordonnées, formulaire, questions fréquentes.
 *
 * @var array $page
 * @var array $erreurs
 * @var array $valeurs   valeurs ressaisies après une erreur
 * @var App\Core\Content $content
 * @var App\Core\View $view
 */
$site = $content->load('site');
$tel  = (string) ($site['contact']['telephone'] ?? '');
$v    = static fn(string $cle): string => (string) ($valeurs[$cle] ?? '');
?>
<?= $view->partial('hero-page', ['hero' => $page['hero']]) ?>

<section class="section">
  <div class="conteneur">
    <div class="contact">
      <div class="contact__infos reveler">
        <h2 class="titre-section"><?= e($page['introduction']['titre']) ?></h2>
        <p class="section__chapo"><?= e($page['introduction']['texte']) ?></p>

        <ul class="coordonnees">
          <?php if ($tel !== ''): ?>
            <li class="coordonnees__item">
              <span class="coordonnees__icone" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'telephone']) ?></span>
              <div>
                <p class="coordonnees__label"><?= e(t('Téléphone')) ?></p>
                <a class="coordonnees__valeur" href="<?= e(tel_lien($tel)) ?>"><?= e($tel) ?></a>
              </div>
            </li>
          <?php endif; ?>

          <li class="coordonnees__item">
            <span class="coordonnees__icone" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'adresse']) ?></span>
            <div>
              <p class="coordonnees__label"><?= e($page['adresse']['titre']) ?></p>
              <address class="coordonnees__valeur">
                <?= e($site['adresse']['rue']) ?><br>
                <?= e($site['adresse']['cp']) ?> <?= e($site['adresse']['ville']) ?>
              </address>
              <?php if (($page['adresse']['complement'] ?? '') !== ''): ?>
                <p class="coordonnees__note"><?= e($page['adresse']['complement']) ?></p>
              <?php endif; ?>
              <?php if (($page['adresse']['carte']['url'] ?? '') !== ''): ?>
                <a class="lien-fleche" href="<?= e($page['adresse']['carte']['url']) ?>"
                   target="_blank" rel="noopener"><?= e($page['adresse']['carte']['libelle']) ?></a>
              <?php endif; ?>
            </div>
          </li>

          <?php if (($site['contact']['horaires'] ?? '') !== ''): ?>
            <li class="coordonnees__item">
              <span class="coordonnees__icone" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'horaires']) ?></span>
              <div>
                <p class="coordonnees__label"><?= e(t('Horaires du cabinet')) ?></p>
                <p class="coordonnees__valeur"><?= e($site['contact']['horaires']) ?></p>
              </div>
            </li>
          <?php endif; ?>

          <?php if (($site['contact']['email'] ?? '') !== ''): ?>
            <li class="coordonnees__item">
              <span class="coordonnees__icone" aria-hidden="true"><?= $view->partial('icones', ['nom' => 'courriel']) ?></span>
              <div>
                <p class="coordonnees__label"><?= e(t('Adresse électronique')) ?></p>
                <a class="coordonnees__valeur" href="mailto:<?= e($site['contact']['email']) ?>"><?= e($site['contact']['email']) ?></a>
              </div>
            </li>
          <?php endif; ?>
        </ul>
      </div>

      <div class="contact__formulaire reveler">
        <h2 class="formulaire__titre"><?= e($page['formulaire']['titre']) ?></h2>
        <p class="formulaire__intro"><?= e($page['formulaire']['texte']) ?></p>

        <?php if (isset($erreurs['envoi'])): ?>
          <p class="alerte alerte--erreur" role="alert"><?= e($erreurs['envoi']) ?></p>
        <?php endif; ?>

        <form class="formulaire" method="post" action="<?= route('contact') ?>" novalidate>
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

          <div class="champ champ--large">
            <label for="f-societe"><?= e(t('Société ou structure')) ?></label>
            <input id="f-societe" type="text" name="societe" value="<?= e($v('societe')) ?>" autocomplete="organization">
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
            <label for="f-sujet"><?= e(t('Objet de votre demande')) ?></label>
            <select id="f-sujet" name="sujet">
              <option value=""><?= e(t('Choisissez un objet')) ?></option>
              <?php foreach ($page['formulaire']['objets'] as $objet): ?>
                <option value="<?= e($objet) ?>"<?= $v('sujet') === $objet ? ' selected' : '' ?>><?= e($objet) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="champ champ--large">
            <label for="f-message"><?= e(t('Votre message')) ?> <span class="champ__requis" aria-hidden="true">*</span></label>
            <textarea id="f-message" name="message" rows="7" required aria-required="true"
                      <?= isset($erreurs['message']) ? 'aria-invalid="true" aria-describedby="e-message"' : '' ?>><?= e($v('message')) ?></textarea>
            <?php if (isset($erreurs['message'])): ?>
              <p class="champ__erreur" id="e-message"><?= e($erreurs['message']) ?></p>
            <?php endif; ?>
          </div>

          <?php /* Piège à robots : visible d'eux seuls, il doit rester vide. */ ?>
          <div class="piege" aria-hidden="true">
            <label for="f-site">Ne pas remplir</label>
            <input id="f-site" type="text" name="site" tabindex="-1" autocomplete="off">
          </div>

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

          <p class="formulaire__mention"><?= e($page['formulaire']['mention']) ?></p>

          <button class="btn btn--magenta" type="submit"><?= e(t('Envoyer ma demande')) ?></button>
        </form>
      </div>
    </div>
  </div>
</section>

<?php if (!empty($page['faq']['items'])): ?>
<section class="section section--teinte">
  <div class="conteneur conteneur--etroit">
    <div class="section__tete reveler">
      <p class="surtitre"><?= e($page['faq']['surtitre']) ?></p>
      <h2 class="titre-section"><?= e($page['faq']['titre']) ?></h2>
    </div>

    <div class="faq reveler">
      <?php foreach ($page['faq']['items'] as $i => $question): ?>
        <details class="faq__item"<?= $i === 0 ? ' open' : '' ?>>
          <summary class="faq__question"><?= e($question['question']) ?></summary>
          <div class="faq__reponse"><p><?= e($question['reponse']) ?></p></div>
        </details>
      <?php endforeach; ?>
    </div>

    <?php if (!empty($page['faq']['relance']['titre'])): ?>
      <div class="encadre reveler">
        <h3><?= e($page['faq']['relance']['titre']) ?></h3>
        <p><?= e($page['faq']['relance']['texte']) ?></p>
        <?php if ($tel !== ''): ?>
          <a class="btn btn--magenta" href="<?= e(tel_lien($tel)) ?>">
            <?= e(t('Appeler le')) ?> <?= e($tel) ?>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>
