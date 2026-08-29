<?php
/**
 * Suite de blocs de contenu, avec l'alternance des fonds.
 *
 * L'alternance est calculée, pas saisie. La règle du système de design — jamais
 * deux sections de même fond à la suite — se casse dès qu'on la confie à celui
 * qui écrit le contenu : il ajoute une section, oublie de retourner le fond de
 * la suivante, et la page se met à sembler interminable. Un bloc peut imposer
 * son fond (`fond: "sombre"`), et le calcul reprend après lui.
 *
 * `$depart` dit quel fond précède le premier bloc, pour que la page enchaîne
 * sans redite sur ce que la vue a déjà posé au-dessus.
 *
 * @var array  $sections
 * @var string $depart   'blanc', 'teinte' ou 'sombre'
 * @var App\Core\View $view
 */
// Un bloc large — cartes, contacts, tableau, documents — respire mal dans la
// colonne étroite réservée au texte suivi.
$larges = ['cartes', 'contacts', 'documents', 'liens', 'duo', 'chiffres', 'photo', 'carte', 'etapes'];

$precedent = $depart ?? 'sombre';
foreach ($sections as $bloc):
    $type = (string) ($bloc['type'] ?? 'texte');
    $fond = (string) ($bloc['fond'] ?? '');
    if ($fond === '') {
        $fond = $precedent === 'blanc' ? 'teinte' : 'blanc';
    }
    $precedent = $fond;

    $classe = 'section';
    if ($fond === 'teinte') {
        $classe .= ' section--teinte';
    } elseif ($fond === 'sombre') {
        $classe .= ' section--sombre';
    }
    $conteneur = in_array($type, $larges, true) ? 'conteneur' : 'conteneur conteneur--etroit';
?>
  <section class="<?= $classe ?>"<?= !empty($bloc['id']) ? ' id="' . e($bloc['id']) . '"' : '' ?>>
    <div class="<?= $conteneur ?> reveler">
      <?= $view->partial('bloc', ['bloc' => $bloc + ['fond' => $fond]]) ?>
    </div>
  </section>
<?php endforeach; ?>
