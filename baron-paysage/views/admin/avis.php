<?php
/**
 * Avis Google : clé d'API, fiche de l'établissement, actualisation.
 *
 * @var array $reglages
 * @var bool $configure
 * @var array|null $cache
 * @var array|null $resultats  fiches trouvées par la recherche
 */
use App\Core\Csrf;

$cleEnregistree = ($reglages['cle_api'] ?? '') !== '';
?>

<?php if ($resultats !== null): ?>
  <div class="bo-bloc">
    <h2>Fiches trouvées</h2>
    <p class="bo-aide">Cliquez sur la vôtre : son identifiant sera reporté dans le formulaire ci-dessous.</p>
    <ul class="bo-liste">
      <?php foreach ($resultats as $r): ?>
        <li class="bo-ligne">
          <div class="bo-ligne__corps">
            <strong><?= e($r['nom']) ?></strong>
            <span class="bo-ligne__note"><?= e($r['adresse']) ?></span>
            <?php if ($r['total'] > 0): ?>
              <span class="bo-ligne__note">
                <?= e(number_format((float) $r['note'], 1, ',', ' ')) ?>/5 — <?= e($r['total']) ?> avis
              </span>
            <?php endif; ?>
            <code class="bo-code"><?= e($r['id']) ?></code>
          </div>
          <div class="bo-ligne__actions">
            <button class="bo-btn bo-btn--petit" type="button"
                    data-remplir="#a-place" data-valeur="<?= e($r['id']) ?>">Utiliser cette fiche</button>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form class="bo-form" method="post" action="<?= url('/admin/avis') ?>">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Affichage sur le site</legend>
    <label class="bo-case">
      <input type="checkbox" name="actif" value="1"<?= !empty($reglages['actif']) ? ' checked' : '' ?>>
      Afficher les avis Google sur le site
    </label>
    <p class="bo-aide">
      Les avis sont récupérés <strong>par le serveur</strong> et gardés en mémoire une
      demi-journée. Le navigateur de vos visiteurs ne contacte jamais Google : aucun
      cookie tiers n’est déposé, et l’affichage reste instantané même si Google est
      injoignable. Quand aucun avis n’est disponible, la section disparaît d’elle-même
      plutôt que d’afficher un bloc vide.
    </p>
  </fieldset>

  <fieldset>
    <legend>Connexion à Google</legend>

    <div class="bo-champ">
      <label for="a-cle">Clé d’API Google</label>
      <input id="a-cle" type="password" name="cle_api" autocomplete="off"
             placeholder="<?= $cleEnregistree ? 'Clé enregistrée — laisser vide pour la conserver' : 'AIza…' ?>">
      <p class="bo-aide">
        À créer sur <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">console.cloud.google.com</a> :
        créez un projet, activez l’API <strong>Places API (New)</strong>, puis générez une clé
        dans « Identifiants ». Restreignez-la à cette seule API.
        <?= $cleEnregistree ? 'Une clé est déjà enregistrée : laissez ce champ vide pour la conserver.' : '' ?>
      </p>
    </div>

    <div class="bo-champ">
      <label for="a-place">Identifiant de la fiche (Place ID)</label>
      <input id="a-place" type="text" name="place_id" value="<?= e($reglages['place_id'] ?? '') ?>"
             placeholder="ChIJ…">
      <p class="bo-aide">Identifiant de votre établissement sur Google. Utilisez la recherche ci-dessous si vous ne le connaissez pas.</p>
    </div>

    <div class="bo-champ">
      <label for="a-note">Note minimale affichée</label>
      <select id="a-note" name="note_mini">
        <?php foreach ([1, 2, 3, 4, 5] as $n): ?>
          <option value="<?= $n ?>"<?= (int) ($reglages['note_mini'] ?? 4) === $n ? ' selected' : '' ?>>
            <?= $n ?> étoile<?= $n > 1 ? 's' : '' ?> et plus
          </option>
        <?php endforeach; ?>
      </select>
      <p class="bo-aide">
        Les avis en dessous de ce seuil ne sont pas affichés. Attention : masquer les
        avis négatifs n’efface pas ceux qui figurent sur votre fiche Google publique.
      </p>
    </div>
  </fieldset>

  <fieldset>
    <legend>Présentation</legend>

    <div class="bo-champ">
      <label for="a-pause">Temps de pause entre deux avis</label>
      <input id="a-pause" type="number" name="pause" min="0" max="30" step="1"
             value="<?= e((int) ($reglages['pause'] ?? 6)) ?>">
      <p class="bo-aide">
        En secondes. Les avis défilent de la droite vers la gauche, marquent cette
        pause, puis reviennent au début une fois le dernier atteint.
        <strong>0 arrête le défilement automatique</strong> : les flèches, les
        pastilles et le glissement du doigt restent actifs.
        En dehors de 0, la valeur est ramenée entre 3 et 30 secondes — en deçà un avis
        n’a pas le temps d’être lu, au-delà le défilement ne se remarque plus.
      </p>
    </div>

    <label class="bo-case">
      <input type="checkbox" name="dates" value="1"<?= !empty($reglages['dates']) ? ' checked' : '' ?>>
      Afficher la date de parution sous le nom de l’auteur
    </label>
    <p class="bo-aide">
      Décochez si vos avis les plus élogieux datent un peu : la date reste visible sur
      votre fiche Google, mais n’attire plus l’œil sur le site.
    </p>

    <label class="bo-case">
      <input type="checkbox" name="total" value="1"<?= !empty($reglages['total']) ? ' checked' : '' ?>>
      Afficher le nombre total d’avis à côté de la note
    </label>
    <p class="bo-aide">
      Coché : « 4,8 ★★★★★ sur 27 avis Google ». Décoché : « 4,8 ★★★★★ avis Google » —
      plus aucun nombre, seule la provenance subsiste. Utile tant que les avis sont
      peu nombreux, une moyenne excellente pesant plus qu’un total modeste.
    </p>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer et récupérer les avis</button>
</form>

<div class="bo-bloc">
  <h2>Retrouver l’identifiant de votre fiche</h2>
  <p class="bo-aide">Indiquez le nom de la société et sa ville. La clé d’API doit être enregistrée au préalable.</p>
  <form class="bo-form bo-form--inline" method="post" action="<?= url('/admin/avis/rechercher') ?>">
    <?= Csrf::champ() ?>
    <div class="bo-champ">
      <label for="a-recherche" class="bo-visuellement-cache">Nom et ville</label>
      <input id="a-recherche" type="text" name="recherche"
             placeholder="Baron Paysage, Mathay">
    </div>
    <button class="bo-btn bo-btn--fantome" type="submit">Rechercher</button>
  </form>
</div>

<div class="bo-bloc">
  <h2>État des avis enregistrés</h2>

  <?php if (!$configure): ?>
    <p class="bo-vide">Renseignez la clé d’API et l’identifiant de la fiche pour récupérer vos avis.</p>
  <?php elseif ($cache === null): ?>
    <p class="bo-vide">Aucun avis n’a encore été récupéré.</p>
  <?php else: ?>
    <dl class="bo-infos">
      <div><dt>Établissement</dt><dd><?= e($cache['nom'] ?? '—') ?></dd></div>
      <div><dt>Note moyenne</dt>
        <dd><?= e(number_format((float) ($cache['note'] ?? 0), 1, ',', ' ')) ?>/5
            sur <?= e((int) ($cache['total'] ?? 0)) ?> avis</dd></div>
      <div><dt>Avis rédigés récupérés</dt><dd><?= e(count($cache['avis'] ?? [])) ?></dd></div>
      <div><dt>Dernière récupération</dt>
        <dd><?= e(date('d/m/Y \à H\hi', (int) ($cache['recupere'] ?? 0))) ?></dd></div>
    </dl>

    <?php if (($cache['erreur'] ?? '') !== ''): ?>
      <p class="bo-message bo-message--erreur">
        Dernière tentative en échec : <?= e($cache['erreur']) ?>
        Les avis ci-dessous restent affichés sur le site en attendant.
      </p>
    <?php endif; ?>

    <?php if (!empty($cache['avis'])): ?>
      <ul class="bo-liste">
        <?php foreach ($cache['avis'] as $a): ?>
          <li class="bo-ligne">
            <div class="bo-ligne__corps">
              <strong><?= e($a['auteur']) ?></strong>
              <span class="bo-ligne__note">
                <?= e((int) $a['note']) ?>/5
                <?php if (($a['horodatage'] ?? 0) > 0): ?>
                  — <?= e(date('d/m/Y', (int) $a['horodatage'])) ?>
                <?php endif; ?>
                <?php if ((float) $a['note'] < (float) ($reglages['note_mini'] ?? 4)): ?>
                  — <em>masqué par le seuil</em>
                <?php endif; ?>
              </span>
              <p><?= e($a['texte']) ?></p>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($configure): ?>
    <form method="post" action="<?= url('/admin/avis/actualiser') ?>">
      <?= Csrf::champ() ?>
      <button class="bo-btn bo-btn--fantome" type="submit">Actualiser maintenant</button>
    </form>
    <p class="bo-aide">
      L’actualisation est automatique toutes les douze heures. Ce bouton sert à la
      forcer, après avoir répondu à un avis par exemple.
    </p>
  <?php endif; ?>
</div>
