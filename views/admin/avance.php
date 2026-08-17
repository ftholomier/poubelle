<?php
/**
 * Éditeur avancé : JSON brut + sauvegardes/restauration.
 * @var string[] $noms
 * @var string $nom
 * @var string $json
 * @var string[] $sauvegardes
 * @var string|null $erreur
 */
use App\Core\Csrf;
?>
<?php if ($erreur): ?><p class="bo-message bo-message--erreur"><?= e($erreur) ?></p><?php endif; ?>

<form method="get" action="<?= url('/admin/avance') ?>" class="bo-form" style="margin-bottom:1.4rem;">
  <div class="bo-champ" style="max-width:340px;">
    <label for="av-nom">Contenu</label>
    <select id="av-nom" name="nom" onchange="this.form.submit()">
      <?php foreach ($noms as $n): ?>
        <option value="<?= e($n) ?>"<?= $n === $nom ? ' selected' : '' ?>><?= e($n) ?>.json</option>
      <?php endforeach; ?>
    </select>
  </div>
  <noscript><button class="bo-btn bo-btn--petit" type="submit">Ouvrir</button></noscript>
</form>

<form class="bo-form" method="post" action="<?= url('/admin/avance') ?>">
  <?= Csrf::champ() ?>
  <input type="hidden" name="nom" value="<?= e($nom) ?>">
  <div class="bo-champ">
    <label for="av-json"><?= e($nom) ?>.json</label>
    <textarea id="av-json" class="code" name="json" spellcheck="false"><?= e($json) ?></textarea>
    <span class="aide">Toute modification est vérifiée (JSON valide exigé). La version précédente est sauvegardée automatiquement.</span>
  </div>
  <button class="bo-btn" type="submit">Enregistrer</button>
</form>

<div class="bo-sauvegardes">
  <h2>Sauvegardes</h2>
  <?php if ($sauvegardes === []): ?>
    <p style="color:var(--texte-2);font-size:.88rem;">Aucune sauvegarde pour ce contenu (elles se créent à chaque enregistrement).</p>
  <?php else: ?>
    <ul>
      <?php foreach ($sauvegardes as $s):
          preg_match('/-(\d{8})-(\d{6})\.json$/', $s, $m);
          $date = $m ? substr($m[1],6,2).'/'.substr($m[1],4,2).'/'.substr($m[1],0,4).' '.substr($m[2],0,2).':'.substr($m[2],2,2) : basename($s);
      ?>
        <li>
          <span><?= e($date) ?></span>
          <form method="post" action="<?= url('/admin/avance') ?>"
                onsubmit="return confirm('Restaurer cette version ? Le contenu actuel sera lui-même sauvegardé.')">
            <?= Csrf::champ() ?>
            <input type="hidden" name="nom" value="<?= e($nom) ?>">
            <input type="hidden" name="restaurer" value="<?= e($s) ?>">
            <button class="bo-btn bo-btn--contour bo-btn--petit" type="submit">Restaurer</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
