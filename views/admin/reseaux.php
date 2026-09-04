<?php
/**
 * Écran Réseaux sociaux.
 *
 * @var App\Core\Reseaux $reseaux
 * @var string $retour
 * @var list<string> $permissions
 * @var list<string> $manques
 * @var list<array{id: string, nom: string, jeton: string}> $pages
 * @var list<array<string, mixed>> $file
 * @var list<array<string, mixed>> $journal
 * @var int $retard
 * @var array{partis: int, echecs: int} $depile
 * @var string $urlTache
 * @var array<string, array{nom: string, items: list<array<string, string>>}> $sources
 * @var list<string> $photos
 * @var string $vignette
 * @var array<string, mixed> $brouillon
 */
use App\Core\Csrf;
use App\Core\Reseaux;

$connecte = $reseaux->facebookPret();

/* Les contenus repris du site voyagent dans un attribut `data-`, pas dans un
   bloc <script>. Ce n'est pas un détail de style : dans un <script>, les
   entités HTML ne sont pas décodées, donc e() y corromprait le JSON — et ne
   pas échapper du tout laisserait un titre contenant « </script> » sortir du
   bloc. Un attribut est un contexte où e() est exactement la bonne réponse. */
$contenus = (string) json_encode(
    array_map(static fn(array $g): array => $g['items'], $sources),
    JSON_UNESCAPED_UNICODE
); 
?>
<p class="bo-aide">
  Publier sur la Page Facebook et le compte Instagram de la commune, depuis ici.
  Tout part du serveur : aucun code de Meta n’est chargé sur le site, et aucun
  traceur n’est déposé chez les visiteurs.
</p>

<?php if ($depile['partis'] || $depile['echecs']): ?>
  <p class="bo-note">
    En ouvrant cet écran,
    <?= (int) $depile['partis'] ?> publication(s) programmée(s) sont parties<?php
      if ($depile['echecs']): ?>, <?= (int) $depile['echecs'] ?> ont échoué<?php endif; ?>.
  </p>
<?php endif; ?>

<?php if ($manques !== []): ?>
  <div class="bo-manques">
    <h2>Ce qu’il reste à faire</h2>
    <ul>
      <?php foreach ($manques as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php /* ---------------------------------------------------------------- */ ?>
<h2 class="bo-titre-section">L’application Meta</h2>
<p class="bo-aide">
  Meta exige qu’une <strong>application</strong> soit déclarée sur
  <span class="bo-code">developers.facebook.com</span>, et qu’elle passe une
  <strong>revue</strong> avant de pouvoir publier. Tant que la revue n’est pas
  accordée, seuls les comptes déclarés « testeurs » dans l’application peuvent
  publier — c’est normal, et cela permet déjà de tout essayer.
</p>
<div class="bo-bloc">
  <p class="bo-aide">
    Dans l’application, réglez l’<strong>URI de redirection autorisée</strong> sur
    exactement cette adresse :
  </p>
  <p class="bo-code bo-code--bloc"><?= e($retour) ?></p>
  <p class="bo-aide">
    Et demandez ces permissions à la revue :
    <?php foreach ($permissions as $i => $p): ?><span class="bo-code"><?= e($p) ?></span><?= $i < count($permissions) - 1 ? ' ' : '' ?><?php endforeach; ?>
  </p>
</div>

<form class="bo-form" method="post" action="<?= url('/admin/reseaux/application') ?>">
  <?= Csrf::champ() ?>
  <div class="bo-champ">
    <label for="r-app">Identifiant de l’application</label>
    <input id="r-app" type="text" name="application" inputmode="numeric"
           value="<?= e($reseaux->identifiantApplication()) ?>" placeholder="1234567890123456">
  </div>
  <div class="bo-champ">
    <label for="r-secret">Clé secrète de l’application</label>
    <input id="r-secret" type="password" name="secret" autocomplete="off"
           placeholder="<?= $reseaux->applicationPrete() ? '•••••••••• (enregistrée)' : 'la clé secrète' ?>">
    <p class="bo-aide">Laissez vide pour conserver la clé déjà enregistrée. Elle n’est jamais réaffichée.</p>
  </div>
  <button class="bo-btn" type="submit">Enregistrer l’application</button>
</form>

<?php /* ---------------------------------------------------------------- */ ?>
<h2 class="bo-titre-section">Les comptes connectés</h2>

<?php if ($pages !== []): ?>
  <form class="bo-form" method="post" action="<?= url('/admin/reseaux/page') ?>">
    <?= Csrf::champ() ?>
    <p class="bo-aide">Ce compte administre plusieurs Pages. Laquelle est celle de la commune ?</p>
    <?php foreach ($pages as $p): ?>
      <div class="bo-champ bo-champ--case">
        <label>
          <input type="radio" name="page_id" value="<?= e($p['id']) ?>" required>
          <?= e($p['nom']) ?> <span class="bo-ligne__note"><?= e($p['id']) ?></span>
        </label>
      </div>
    <?php endforeach; ?>
    <button class="bo-btn" type="submit">Connecter cette Page</button>
  </form>
<?php endif; ?>

<div class="bo-bloc">
  <?php if ($connecte): ?>
    <p class="bo-connecte">
      <strong>Facebook</strong> — Page « <?= e($reseaux->pageNom()) ?> »
      <span class="bo-ligne__note"><?= e($reseaux->pageId()) ?></span>
    </p>
    <p class="bo-connecte">
      <strong>Instagram</strong> —
      <?= $reseaux->instagramPret()
          ? 'compte « ' . e($reseaux->instagramNom()) . ' »'
          : '<span class="bo-vide">aucun compte professionnel rattaché à cette Page</span>' ?>
    </p>
    <form class="bo-form bo-form--inline" method="post" action="<?= url('/admin/reseaux/deconnexion') ?>">
      <?= Csrf::champ() ?>
      <button class="bo-btn bo-btn--fantome" type="submit">Déconnecter</button>
    </form>
  <?php else: ?>
    <p class="bo-vide">Aucun compte connecté.</p>
    <form class="bo-form bo-form--inline" method="post" action="<?= url('/admin/reseaux/connexion') ?>">
      <?= Csrf::champ() ?>
      <button class="bo-btn" type="submit"<?= $reseaux->applicationPrete() ? '' : ' disabled' ?>>
        Connecter Facebook
      </button>
    </form>
    <?php if (!$reseaux->applicationPrete()): ?>
      <p class="bo-aide">Renseignez d’abord l’application ci-dessus.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php /* ---------------------------------------------------------------- */ ?>
<h2 class="bo-titre-section">Publier</h2>

<?php if ($vignette !== ''): ?>
  <p class="bo-note"><?= e($vignette) ?> Les publications Instagram sans photo ne seront pas possibles.</p>
<?php endif; ?>

<form class="bo-form" method="post" action="<?= url('/admin/reseaux/publier') ?>" data-reseaux
      data-reseaux-max="<?= Reseaux::TEXTE_MAX ?>"
      data-reseaux-max-instagram="<?= Reseaux::TEXTE_MAX_INSTAGRAM ?>">
  <?= Csrf::champ() ?>

  <?php if ($sources !== []): ?>
    <div class="bo-champ">
      <label for="r-source">Reprendre un contenu du site</label>
      <select id="r-source" data-reseaux-source>
        <option value="">— écrire un message libre —</option>
        <?php foreach ($sources as $cle => $groupe): ?>
          <optgroup label="<?= e($groupe['nom']) ?>">
            <?php foreach ($groupe['items'] as $i => $item): ?>
              <option value="<?= e($cle . ':' . $i) ?>"><?= e($item['titre']) ?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
      <p class="bo-aide">
        Le titre, le texte, la photo et le lien sont recopiés dans les champs
        ci-dessous. Rien n’est envoyé avant que vous les ayez relus.
      </p>
      <?php /* Pas d'aller-retour serveur pour remplir les champs : l'écran
               reste immédiat, et ces contenus sont déjà publics sur le site. */ ?>
      <div hidden data-reseaux-contenus="<?= e($contenus) ?>"></div>
    </div>
  <?php endif; ?>

  <div class="bo-champ">
    <label for="r-titre">Titre</label>
    <input id="r-titre" type="text" name="titre" maxlength="160"
           value="<?= e((string) ($brouillon['titre'] ?? '')) ?>">
  </div>

  <div class="bo-champ">
    <label for="r-texte">Message</label>
    <textarea id="r-texte" name="texte" rows="5"
              data-reseaux-texte><?= e((string) ($brouillon['texte'] ?? '')) ?></textarea>
    <p class="bo-aide">
      <!-- Le compteur mesure ce qui partira : le titre et le lien en font
           partie. Compter le seul corps du texte affichait « 1 990 / 2 000 »
           sur un message que Facebook allait couper. -->
      <span data-reseaux-compteur>0</span> / <span data-reseaux-limite><?= Reseaux::TEXTE_MAX ?></span>
      caractères, titre et lien compris.
      Instagram n’affiche pas les liens cliquables : l’adresse y est ajoutée en clair.
    </p>
  </div>

  <div class="bo-champ">
    <label for="r-lien">Lien vers la page du site</label>
    <input id="r-lien" type="url" name="lien" placeholder="https://…"
           value="<?= e((string) ($brouillon['lien'] ?? '')) ?>">
  </div>

  <div class="bo-champ">
    <label for="r-image">Photo</label>
    <select id="r-image" name="image" data-reseaux-image>
      <option value="">— aucune : une image sera fabriquée pour Instagram —</option>
      <?php foreach ($photos as $photo): ?>
        <option value="<?= e($photo) ?>"<?= ($brouillon['image'] ?? '') === $photo ? ' selected' : '' ?>>
          <?= e(basename($photo)) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <p class="bo-aide">
      Sans photo, une image carrée aux couleurs de la commune est fabriquée avec
      le blason et le titre : Instagram n’accepte aucune publication sans image.
    </p>
  </div>

  <div class="bo-champ">
    <span class="bo-legende-champ">Où publier</span>
    <div class="bo-champ bo-champ--case">
      <label>
        <input type="checkbox" name="reseaux[]" value="facebook" checked
               <?= $reseaux->facebookPret() ? '' : 'disabled' ?>>
        Facebook<?= $reseaux->facebookPret() ? ' — ' . e($reseaux->pageNom()) : ' (non connecté)' ?>
      </label>
    </div>
    <div class="bo-champ bo-champ--case">
      <label>
        <input type="checkbox" name="reseaux[]" value="instagram"
               <?= $reseaux->instagramPret() ? '' : 'disabled' ?>>
        Instagram<?= $reseaux->instagramPret() ? ' — ' . e($reseaux->instagramNom()) : ' (non connecté)' ?>
      </label>
    </div>
  </div>

  <div class="bo-champ">
    <span class="bo-legende-champ">Quand</span>
    <div class="bo-champ bo-champ--case">
      <label><input type="radio" name="moment" value="maintenant" checked data-reseaux-moment> Tout de suite</label>
    </div>
    <div class="bo-champ bo-champ--case">
      <label><input type="radio" name="moment" value="programme" data-reseaux-moment> À une date</label>
    </div>
    <input type="datetime-local" name="quand" data-reseaux-quand hidden
           value="<?= e(date('Y-m-d\TH:i', time() + 3600)) ?>">
  </div>

  <button class="bo-btn" type="submit"<?= $connecte ? '' : ' disabled' ?>>Publier</button>
  <?php if (!$connecte): ?>
    <p class="bo-aide">Connectez d’abord une Page Facebook.</p>
  <?php endif; ?>
</form>

<?php /* ---------------------------------------------------------------- */ ?>
<h2 class="bo-titre-section">La tâche planifiée</h2>
<p class="bo-aide">
  Les publications programmées partent par une <strong>tâche planifiée</strong>
  (cron), à régler une fois dans le panneau de l’hébergeur. Une fois par heure
  suffit. Sans elle, elles partent quand même — dès que quelqu’un ouvre le
  back-office —, mais avec le retard que cela suppose.
</p>
<p class="bo-code bo-code--bloc">*/15 * * * * wget -q -O /dev/null "<?= e($urlTache) ?>"</p>
<p class="bo-aide">
  Cette adresse contient une clé : elle vaut mot de passe, et n’a pas à être
  partagée. Elle ne fait rien d’autre que dépiler la file.
</p>

<?php if ($file !== []): ?>
  <h2 class="bo-titre-section">
    En attente
    <?php if ($retard > 0): ?>
      <span class="bo-etiquette bo-etiquette--alerte"><?= (int) $retard ?> en retard</span>
    <?php endif; ?>
  </h2>
  <ul class="bo-liste">
    <?php foreach ($file as $p): ?>
      <li class="bo-ligne">
        <div class="bo-ligne__corps">
          <strong><?= e((string) ($p['titre'] ?? '(sans titre)')) ?></strong>
          <span class="bo-ligne__note">
            <?= e(date('d/m/Y à H\hi', (int) ($p['quand'] ?? 0))) ?>
            · <?= e(implode(' et ', (array) ($p['reseaux'] ?? []))) ?>
            <?php if ((int) ($p['essais'] ?? 0) > 0): ?>
              · <?= (int) $p['essais'] ?> essai(s) — <?= e((string) ($p['dernier_motif'] ?? '')) ?>
              <?php /* L'heure du prochain essai, sans quoi une publication en
                       échec paraît abandonnée : le recul entre deux essais va
                       jusqu'à deux heures, et rien ne le disait. */ ?>
              <?php $reprise = (int) ($p['reprise'] ?? 0); ?>
              <?php if ($reprise > time()): ?>
                · nouvel essai vers <?= e(date('H\hi', $reprise)) ?>
              <?php endif; ?>
            <?php endif; ?>
          </span>
        </div>
        <form method="post" action="<?= url('/admin/reseaux/annuler') ?>">
          <?= Csrf::champ() ?>
          <input type="hidden" name="id" value="<?= e((string) ($p['id'] ?? '')) ?>">
          <button class="bo-lien-danger" type="submit">Retirer</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php /* ---------------------------------------------------------------- */ ?>
<h2 class="bo-titre-section">Ce qui est parti</h2>
<?php if ($journal === []): ?>
  <p class="bo-vide">Rien n’a encore été publié.</p>
<?php else: ?>
  <ul class="bo-liste">
    <?php foreach ($journal as $j): ?>
      <li class="bo-ligne<?= ($j['succes'] ?? false) ? '' : ' bo-ligne--hors-ligne' ?>">
        <div class="bo-ligne__corps">
          <strong><?= e((string) ($j['titre'] ?? '(sans titre)')) ?></strong>
          <span class="bo-ligne__note">
            <?= e(date('d/m/Y à H\hi', (int) ($j['le'] ?? 0))) ?>
            <?php foreach ((array) ($j['posts'] ?? []) as $reseau => $id): ?>
              · <a href="<?= e($reseaux->lienPublication((string) $reseau, (string) $id)) ?>"
                   target="_blank" rel="noopener noreferrer"><?= e(ucfirst((string) $reseau)) ?></a>
            <?php endforeach; ?>
            <?php if (!($j['succes'] ?? false)): ?>
              · <?= e((string) ($j['motif'] ?? 'échec')) ?>
            <?php elseif (($j['motif'] ?? '') !== ''): ?>
              · en partie : <?= e((string) $j['motif']) ?>
            <?php endif; ?>
          </span>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
  <form class="bo-form bo-form--inline" method="post" action="<?= url('/admin/reseaux/journal/vider') ?>">
    <?= Csrf::champ() ?>
    <button class="bo-btn bo-btn--fantome" type="submit">Vider le journal</button>
  </form>
<?php endif; ?>
