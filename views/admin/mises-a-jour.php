<?php
/**
 * Mises à jour du site depuis le dépôt git.
 *
 * @var array $etat
 * @var array|null $version
 * @var string $branche
 * @var string $depotUrl
 * @var array|null $verification
 * @var string|null $journal
 * @var array $sauvegardes
 */
use App\Core\Csrf;

$dateFr = static function (string $iso): string {
    $t = strtotime($iso);
    return $t ? date('d/m/Y à H:i', $t) : $iso;
};
?>

<?php if (!$etat['ok']): ?>
  <div class="bo-message bo-message--erreur"><?= e($etat['message']) ?></div>

  <?php if (!$etat['depot'] && $etat['git']): ?>
    <section class="bo-zone">
      <header class="bo-zone__tete">
        <h2>Installer le site depuis git</h2>
        <p>À faire une seule fois, en SSH. Ensuite les mises à jour se font depuis cet écran.</p>
      </header>
      <p class="bo-aide-bloc">Depuis le dossier parent du site, récupérez le dépôt dans un dossier
        temporaire, déplacez le dossier <code>.git</code> dans le site existant, puis
        alignez le code :</p>
      <pre class="bo-code">git clone --branch main VOTRE_DEPOT depot-temporaire
mv depot-temporaire/.git .
rm -rf depot-temporaire
git checkout -- $(git ls-files | grep -v '^data/' | grep -v '^public/assets/img/site/')</pre>
      <p class="bo-aide-bloc">Le contenu déjà en place (<code>data/</code>, photos) reste intact :
        il apparaîtra simplement comme modifié vis-à-vis du dépôt, ce qui est normal
        et sans conséquence.</p>
    </section>
  <?php endif; ?>
<?php else: ?>

  <section class="bo-zone">
    <header class="bo-zone__tete">
      <h2>Version installée</h2>
      <p>Dépôt <code><?= e($depotUrl) ?></code> — branche <code><?= e($branche) ?></code></p>
    </header>

    <?php if ($version): ?>
      <dl class="bo-version">
        <div><dt>Référence</dt><dd><code><?= e($version['sha']) ?></code></dd></div>
        <div><dt>Date</dt><dd><?= e($dateFr($version['date'])) ?></dd></div>
        <div><dt>Dernier changement</dt><dd><?= e($version['sujet']) ?></dd></div>
      </dl>
    <?php endif; ?>

    <form method="post" action="<?= url('/admin/mises-a-jour/verifier') ?>">
      <?= Csrf::champ() ?>
      <button class="bo-btn bo-btn--contour" type="submit">Vérifier les mises à jour</button>
    </form>
  </section>

  <?php if ($verification !== null): ?>
    <section class="bo-zone">
      <header class="bo-zone__tete">
        <h2><?= $verification['retard'] === 0
              ? 'Le site est à jour'
              : $verification['retard'] . ' mise(s) à jour disponible(s)' ?></h2>
        <?php if ($verification['retard'] > 0): ?>
          <p>Seuls les fichiers de code seront remplacés. Le contenu, les photos
             et vos réglages ne sont pas concernés.</p>
        <?php endif; ?>
      </header>

      <?php if ($verification['retard'] > 0): ?>
        <ul class="bo-commits">
          <?php foreach ($verification['commits'] as $c): ?>
            <li>
              <code><?= e($c['sha']) ?></code>
              <span class="sujet"><?= e($c['sujet']) ?></span>
              <span class="date"><?= e($dateFr($c['date'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>

        <?php if ($verification['touche_contenu']): ?>
          <p class="bo-message bo-message--attention">
            Cette mise à jour modifie aussi des fichiers de contenu dans le dépôt.
            Ces modifications ne seront <strong>pas</strong> appliquées, pour ne pas
            écraser ce qui a été saisi ici. Si un nouveau champ est attendu,
            ajoutez-le depuis l’Éditeur avancé.
          </p>
        <?php endif; ?>

        <form method="post" action="<?= url('/admin/mises-a-jour/appliquer') ?>"
              onsubmit="return confirm('Appliquer la mise à jour ? Une sauvegarde du contenu est créée avant toute modification.')">
          <?= Csrf::champ() ?>
          <button class="bo-btn" type="submit">Appliquer la mise à jour</button>
        </form>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($journal): ?>
    <section class="bo-zone">
      <header class="bo-zone__tete"><h2>Compte rendu</h2></header>
      <pre class="bo-code"><?= e($journal) ?></pre>
    </section>
  <?php endif; ?>
<?php endif; ?>

<section class="bo-zone">
  <header class="bo-zone__tete">
    <h2>Sauvegardes du contenu</h2>
    <p>Créées automatiquement avant chaque mise à jour. Contiennent
       <code>data/</code> et les photos envoyées.</p>
  </header>

  <form method="post" action="<?= url('/admin/mises-a-jour/sauvegarder') ?>" style="margin-bottom:1.2rem;">
    <?= Csrf::champ() ?>
    <button class="bo-btn bo-btn--contour" type="submit">Sauvegarder maintenant</button>
  </form>

  <?php if ($sauvegardes === []): ?>
    <p class="bo-zone__vide">Aucune sauvegarde pour l’instant.</p>
  <?php else: ?>
    <ul class="bo-sauvegardes-liste">
      <?php foreach ($sauvegardes as $s): ?>
        <li>
          <span class="date"><?= e($s['date']) ?></span>
          <span class="taille"><?= e(number_format($s['taille'] / 1048576, 1, ',', ' ')) ?> Mo</span>
          <form method="post" action="<?= url('/admin/mises-a-jour/restaurer') ?>"
                onsubmit="return confirm('Restaurer le contenu et les photos de cette sauvegarde ? L’état actuel sera lui-même sauvegardé avant.')">
            <?= Csrf::champ() ?>
            <input type="hidden" name="archive" value="<?= e($s['fichier']) ?>">
            <button class="bo-btn bo-btn--contour bo-btn--petit" type="submit">Restaurer</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
