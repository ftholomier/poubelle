<?php
/**
 * Apparence : couleur de la commune, disposition du menu, taille du logo.
 *
 * Chaque disposition est illustrée par un schéma en CSS pur plutôt que par
 * une capture d'écran : on voit tout de suite la différence, et le schéma ne
 * se périme pas quand le site évolue.
 *
 * L'aperçu du logo, lui, montre le vrai fichier à sa vraie taille, dans une
 * barre à la vraie hauteur. Un nombre en pixels ne veut rien dire tant qu'on
 * ne l'a pas vu : c'est l'aperçu qui rend le réglage utilisable, et c'est lui
 * qui montre ce que « la barre suit le logo » coûte en hauteur avant qu'on
 * enregistre.
 *
 * @var string $couleur
 * @var array $palette
 * @var array $menus
 * @var string $courant
 * @var int $logo
 * @var bool $deborde
 * @var array $bornes
 * @var string $logoSrc
 */
use App\Core\Csrf;

/**
 * Ce que le réglage donnera sur le site, avec les formules de site.css :
 * --entete-h-suivie quand la barre suit, sa hauteur d'origine et le plancher
 * --logo-air-mini quand le logo déborde. Le script redit la même chose à
 * chaque mouvement du curseur ; ici c'est l'état de départ, et le seul
 * affiché si le JavaScript ne se charge pas.
 */
$resumeBarre = static function (int $h, bool $deborde): string {
    if (!$deborde) {
        return 'Barre de ' . ($h + 44) . ' px de haut.';
    }
    $depassement = (int) round(max(12, (96 - $h) / 2) + $h - 96);
    return $depassement > 0
        ? 'Barre de 96 px de haut ; le logo la dépasse de ' . $depassement . ' px.'
        : 'Barre de 96 px de haut ; le logo y tient encore.';
};
?>
<form class="bo-form" method="post" action="<?= url('/admin/apparence') ?>">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Couleur de la commune</legend>
    <p class="bo-aide">
      Vous choisissez une <strong>teinte</strong>, pas une palette : le site en
      déduit lui-même les cinq tons dont il a besoin, en assombrissant ou en
      éclaircissant votre couleur juste ce qu’il faut pour que chaque texte
      reste lisible sur le fond où il est posé. Vous ne pouvez donc pas rendre
      une page illisible, quelle que soit la couleur retenue.
    </p>
    <div class="bo-couleur">
      <div class="bo-champ bo-couleur__choix">
        <label for="couleur">Teinte</label>
        <input type="color" id="couleur" name="couleur" value="<?= e($couleur) ?>"
               data-couleur>
        <output class="bo-couleur__hex" data-couleur-hex><?= e($couleur) ?></output>
      </div>

      <?php /* L'aperçu montre la palette réellement dérivée, jeton par jeton,
               et le script la recalcule à chaque mouvement du sélecteur — avec
               la même formule que Charte.php, pour que ce qu'on voit ici soit
               ce qui sera enregistré. Sans JavaScript, il montre la palette
               enregistrée : jamais rien de faux, seulement pas d'aperçu
               immédiat. */ ?>
      <div class="bo-palette" data-palette>
        <?php
        $tons = [
            'bleu'        => 'Couleur de marque — aplats, filets, pictogrammes',
            'bleu-fonce'  => 'Survols et bande « En ce moment »',
            'bleu-texte'  => 'Petits libellés sur fond teinté',
            'bleu-clair'  => 'Accents sur les sections sombres',
            'bleu-barre'  => 'Survols de la barre translucide',
            'ardoise'     => 'Sections sombres et pied de page',
        ];
        foreach ($tons as $cle => $role): ?>
          <div class="bo-palette__ton">
            <span class="bo-palette__pastille" data-ton="<?= e($cle) ?>"
                  style="background: <?= e($palette[$cle]) ?>"></span>
            <span class="bo-palette__nom" data-ton-hex="<?= e($cle) ?>"><?= e($palette[$cle]) ?></span>
            <span class="bo-palette__role"><?= e($role) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <p class="bo-aide">
      Le blason et les photos ne changent pas : seuls les aplats, les filets et
      les fonds suivent. Pour revenir au bleu ardoise tiré du blason, choisissez
      <code>#456d8a</code>.
    </p>
  </fieldset>

  <fieldset>
    <legend>Disposition du menu</legend>
    <p class="bo-aide">
      Le changement s’applique immédiatement sur tout le site. Vous pouvez
      revenir en arrière à tout moment : les deux dispositions affichent les
      mêmes rubriques, réglées dans
      <a href="<?= url('/admin/site') ?>">Coordonnées &amp; menu</a>.
    </p>

    <div class="bo-options">
      <?php foreach ($menus as $cle => $menu): ?>
        <label class="bo-option<?= $courant === $cle ? ' bo-option--active' : '' ?>">
          <input type="radio" name="menu" value="<?= e($cle) ?>"<?= $courant === $cle ? ' checked' : '' ?>>

          <span class="bo-apercu bo-apercu--<?= e($cle) ?>" aria-hidden="true">
            <?php if ($cle === 'lateral'): ?>
              <span class="bo-apercu__barre">
                <span class="bo-apercu__burger"><i></i><i></i><i></i></span>
                <span class="bo-apercu__logo"></span>
                <span class="bo-apercu__cta"></span>
              </span>
              <span class="bo-apercu__panneau">
                <i></i><i></i><i></i><i></i>
              </span>
            <?php else: ?>
              <span class="bo-apercu__barre">
                <span class="bo-apercu__logo bo-apercu__logo--gauche"></span>
                <span class="bo-apercu__liens"><i></i><i></i><i></i><i></i></span>
                <span class="bo-apercu__cta"></span>
              </span>
            <?php endif; ?>
          </span>

          <span class="bo-option__corps">
            <strong><?= e($menu['nom']) ?></strong>
            <span class="bo-option__resume"><?= e($menu['resume']) ?></span>
            <span class="bo-option__detail"><?= e($menu['detail']) ?></span>
          </span>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <fieldset>
    <legend>Taille du logo dans l’en-tête</legend>
    <p class="bo-aide">
      La hauteur du logo en haut de page, sur ordinateur. Sur tablette et sur
      téléphone il se réduit tout seul, dans les mêmes proportions.
    </p>

    <div class="bo-logo-reglage">
      <div class="bo-champ">
        <label for="a-logo">Hauteur du logo</label>
        <div class="bo-logo-saisie">
          <?php /* Le nombre est le champ, le curseur n'est que du confort :
                   sans JavaScript on saisit la valeur, et tout fonctionne.
                   Le curseur est donc caché tant que le script ne l'a pas
                   révélé — un curseur seul, sans nombre affiché, ne dit pas
                   ce qu'on est en train de régler. */ ?>
          <input type="range" id="a-logo-curseur" hidden data-logo-curseur
                 min="<?= (int) $bornes['min'] ?>" max="<?= (int) $bornes['max'] ?>"
                 step="<?= (int) $bornes['pas'] ?>" value="<?= (int) $logo ?>"
                 aria-hidden="true" tabindex="-1">
          <input type="number" id="a-logo" name="logo" data-logo-nombre
                 min="<?= (int) $bornes['min'] ?>" max="<?= (int) $bornes['max'] ?>"
                 step="<?= (int) $bornes['pas'] ?>" value="<?= (int) $logo ?>">
          <span class="bo-logo-unite">px</span>
        </div>
        <p class="aide" data-logo-resume><?= e($resumeBarre((int) $logo, $deborde)) ?></p>
      </div>

      <div class="bo-champ bo-champ--case">
        <label for="a-logo-deborde">
          <input type="checkbox" id="a-logo-deborde" name="logo_deborde"
                 data-logo-deborde<?= $deborde ? ' checked' : '' ?>>
          Laisser le logo déborder de la barre
        </label>
        <p class="aide">
          Décoché, la barre s’épaissit pour suivre le logo : c’est net, et
          rien ne se chevauche. Coché, elle garde sa hauteur et un grand logo
          la dépasse par le bas, sur la photo du bandeau. C’est l’effet le
          plus marquant, et il évite une barre trop épaisse qui mangerait le
          haut de la page. Le débordement s’arrête au premier défilement, où
          la barre devient opaque.
        </p>
      </div>
    </div>

    <?php /* aria-hidden : l'aperçu ne dit rien de plus que les deux champs
             qu'il illustre, et son détail — burger, liens, faux paragraphes —
             n'aurait aucun sens lu à voix haute. */ ?>
    <div class="bo-logo-apercu<?= $deborde ? ' bo-logo-apercu--deborde' : '' ?>"
         style="--ap-logo: <?= (int) $logo ?>px"
         data-logo-apercu aria-hidden="true">
      <div class="bo-logo-apercu__barre">
        <span class="bo-logo-apercu__burger"><i></i><i></i><i></i></span>
        <img class="bo-logo-apercu__marque" src="<?= asset($logoSrc) ?>" alt="">
        <span class="bo-logo-apercu__liens"><i></i><i></i><i></i><i></i></span>
      </div>
      <div class="bo-logo-apercu__page">
        <span class="bo-logo-apercu__titre"></span>
        <span class="bo-logo-apercu__ligne"></span>
        <span class="bo-logo-apercu__ligne bo-logo-apercu__ligne--courte"></span>
      </div>
    </div>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer l’apparence</button>
</form>
