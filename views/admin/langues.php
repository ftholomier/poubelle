<?php
/**
 * Langues du site.
 *
 * @var array $etatLangues  langues hors français, avec leur avancement
 * @var int $total       nombre de textes à traduire
 * @var array $proposees codes suggérés à l'ajout
 */
use App\Core\Csrf;
?>

<p class="bo-intro">Le français est la langue du site : c'est lui que vous éditez partout
  ailleurs. Chaque autre langue en est une traduction, servie sur sa propre adresse —
  <code>/en/hebergements</code> pour l'anglais. Ce qui n'est pas traduit s'affiche en
  français, jamais en blanc.</p>

<section class="bo-zone">
  <h2>Langues du site</h2>

  <div class="bo-ligne">
    <div class="bo-ligne__corps">
      <h3>Français <span class="bo-etiquette bo-etiquette--source">langue source</span></h3>
      <p>Servi à la racine du site. Ni traduisible, ni supprimable.</p>
    </div>
  </div>

  <?php foreach ($etatLangues as $code => $l): ?>
    <div class="bo-ligne<?= $l['actif'] ? '' : ' bo-ligne--hors-ligne' ?>">
      <div class="bo-ligne__corps">
        <h3>
          <?= e($l['nom']) ?> <code>/<?= e($code) ?></code>
          <?php if (!$l['actif']): ?><span class="bo-etiquette">hors ligne</span><?php endif; ?>
        </h3>
        <p><?= (int) $l['traduits'] ?> textes traduits sur <?= (int) $total ?></p>
        <div class="bo-jauge" role="img"
             aria-label="<?= (int) $l['pourcent'] ?> % traduit">
          <span style="width:<?= (int) $l['pourcent'] ?>%"></span>
        </div>
      </div>
      <div class="bo-ligne__liens">
        <a href="<?= url('/admin/langues/' . rawurlencode($code)) ?>">Traduire →</a>
        <?php if ($l['actif']): ?>
          <a href="<?= url('/' . $code) ?>" target="_blank" rel="noopener">Voir ↗</a>
        <?php endif; ?>
        <form method="post" action="<?= url('/admin/langues/' . rawurlencode($code) . '/publication') ?>">
          <?= Csrf::champ() ?>
          <button class="bo-lien-bascule" type="submit">
            <?= $l['actif'] ? 'Mettre hors ligne' : 'Mettre en ligne' ?>
          </button>
        </form>
        <form method="post" action="<?= url('/admin/langues/' . rawurlencode($code) . '/supprimer') ?>"
              onsubmit="return confirm('Supprimer <?= e($l['nom']) ?> ? Les traductions seront perdues.')">
          <?= Csrf::champ() ?>
          <button class="bo-lien-danger" type="submit">Supprimer</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</section>

<form class="bo-form" method="post" action="<?= url('/admin/langues/ajouter') ?>" style="margin-top:1.6rem;">
  <?= Csrf::champ() ?>
  <fieldset>
    <legend>Ajouter une langue</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="l-code">Code de la langue</label>
        <input id="l-code" type="text" name="code" required maxlength="3" list="codes-langues"
               placeholder="en" spellcheck="false" autocapitalize="off">
        <datalist id="codes-langues">
          <?php foreach ($proposees as $code => $l): ?>
            <option value="<?= e($code) ?>"><?= e($l['nom_fr']) ?></option>
          <?php endforeach; ?>
        </datalist>
        <span class="aide">Deux lettres : <code>en</code> anglais, <code>de</code> allemand,
          <code>nl</code> néerlandais, <code>es</code> espagnol… C'est aussi ce qui apparaîtra
          dans l'adresse.</span>
      </div>
      <div class="bo-champ">
        <label for="l-nom">Nom affiché</label>
        <input id="l-nom" type="text" name="nom" placeholder="English">
        <span class="aide">Laissez vide pour le nom usuel de la langue.</span>
      </div>
    </div>
    <button class="bo-btn" type="submit">Ajouter la langue</button>
  </fieldset>
</form>

<form class="bo-form" method="post" action="<?= url('/admin/langues/cle') ?>" style="margin-top:1.6rem;">
  <?= Csrf::champ() ?>
  <fieldset>
    <legend>Service de traduction automatique</legend>
    <p class="bo-intro">Sans clé, le bouton « Traduire automatiquement » passe par des services
      gratuits dont le quota se compte par adresse IP — celle du serveur, partagée avec les autres
      sites de l'hébergement. D'où les refus <code>HTTP 429</code> même après quelques textes.
      Une clé DeepL rattache le quota au compte : 500 000 caractères par mois en offre gratuite,
      largement de quoi traduire ce site plusieurs fois.</p>
    <div class="bo-champ">
      <label for="l-cle">Clé d'API DeepL</label>
      <input id="l-cle" type="text" name="cle_deepl" value="<?= e($cleDeepL) ?>"
             spellcheck="false" autocapitalize="off"
             placeholder="00000000-0000-0000-0000-000000000000:fx">
      <span class="aide">À créer sur <code>deepl.com/pro-api</code> (offre « Free »). Les clés
        gratuites se terminent par <code>:fx</code>. Laissez vide pour revenir aux services
        gratuits.</span>
    </div>
    <button class="bo-btn" type="submit">Enregistrer la clé</button>
  </fieldset>
</form>
