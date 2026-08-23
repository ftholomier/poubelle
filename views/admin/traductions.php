<?php
/**
 * Relecture et correction des traductions d'une langue.
 *
 * @var string $code
 * @var string $nom
 * @var bool $enLigne
 * @var array $groupes   groupe => (clé => texte français)
 * @var array $traduits  clé => traduction enregistrée
 */
use App\Core\Csrf;

$manquants = 0;
foreach ($groupes as $textes) {
    foreach (array_keys($textes) as $cle) {
        if (($traduits[$cle] ?? '') === '') {
            $manquants++;
        }
    }
}
?>

<p class="bo-intro">
  Traduction automatique puis relecture : le résultat est enregistré sur le serveur et
  servi tel quel aux visiteurs. Aucun service extérieur n'intervient à l'affichage.
  Un champ laissé vide affiche le texte français.
  <?php if (!$enLigne): ?>
    <strong>Cette langue est hors ligne</strong> — elle n'est pas encore visible par les visiteurs.
  <?php endif; ?>
</p>

<div class="bo-barre-actions">
  <form method="post" action="<?= url('/admin/langues/' . rawurlencode($code) . '/auto') ?>">
    <?= Csrf::champ() ?>
    <input type="hidden" name="portee" value="manquants">
    <button class="bo-btn" type="submit"<?= $manquants === 0 ? ' disabled' : '' ?>>
      Traduire les <?= (int) $manquants ?> textes manquants
    </button>
  </form>

  <form method="post" action="<?= url('/admin/langues/' . rawurlencode($code) . '/auto') ?>"
        onsubmit="return confirm('Tout retraduire ? Vos corrections manuelles seront écrasées.')">
    <?= Csrf::champ() ?>
    <input type="hidden" name="portee" value="tout">
    <button class="bo-lien-danger" type="submit">Tout retraduire</button>
  </form>

  <a href="<?= url('/admin/langues') ?>">← Toutes les langues</a>
</div>

<p class="aide" style="margin-bottom:1.6rem;">
  La traduction automatique peut prendre une minute sur l'ensemble du site. Elle se trompe
  souvent sur les noms propres — « Menuiserie Tréhant » ou « Tréhant » n'ont pas à être
  traduits : relisez-les en priorité.
</p>

<form class="bo-form" method="post" action="<?= url('/admin/langues/' . rawurlencode($code) . '/enregistrer') ?>">
  <?= Csrf::champ() ?>

  <?php foreach ($groupes as $titre => $textes): ?>
    <?php
      $vides = 0;
      foreach (array_keys($textes) as $c) {
          if (($traduits[$c] ?? '') === '') { $vides++; }
      }
    ?>
    <details class="bo-repli"<?= $vides > 0 ? ' open' : '' ?>>
      <summary>
        <?= e($titre) ?>
        <span class="bo-compte-groupe"><?= count($textes) ?> textes<?= $vides > 0 ? ', ' . $vides . ' à traduire' : '' ?></span>
      </summary>

      <table class="bo-trad">
        <thead>
          <tr><th>Français</th><th><?= e($nom) ?></th></tr>
        </thead>
        <tbody>
          <?php foreach ($textes as $cle => $francais): ?>
            <?php $traduction = $traduits[$cle] ?? ''; ?>
            <tr<?= $traduction === '' ? ' class="bo-trad--vide"' : '' ?>>
              <td>
                <span class="bo-trad__fr"><?= e($francais) ?></span>
                <code class="bo-trad__cle"><?= e($cle) ?></code>
              </td>
              <td>
                <?php if (mb_strlen($francais) > 90): ?>
                  <textarea name="textes[<?= e($cle) ?>]" rows="3"
                            placeholder="<?= e($francais) ?>"><?= e($traduction) ?></textarea>
                <?php else: ?>
                  <input type="text" name="textes[<?= e($cle) ?>]"
                         value="<?= e($traduction) ?>" placeholder="<?= e($francais) ?>">
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </details>
  <?php endforeach; ?>

  <button class="bo-btn" type="submit">Enregistrer les traductions</button>
</form>
