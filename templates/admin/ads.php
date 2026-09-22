<?php
/**
 * Emplacements publicitaires.
 * @var array $slots @var string $client @var string $mode
 * @var string $snippet @var array|null $parsed @var string $notice
 */
use App\Core\Csrf;
use App\Services\I18n;
?>
<div class="admin-head">
  <div>
    <h1><?= e(I18n::t('admin.ads')) ?></h1>
    <p>Compte AdSense : <?= $client !== ''
        ? e($client)
        : '<em>non configuré — <a href="/admin/cles-api">Clés d’API</a></em>' ?></p>
  </div>
</div>

<?php // Une lecture de code a son propre retour, juste sous le champ collé. ?>
<?php if ($notice !== '' && $parsed === null): ?>
  <div class="notice notice-ok" role="status"><?= e($notice) ?></div>
<?php endif; ?>

<form method="post" class="admin-card">
  <?= Csrf::field('admin-ads') ?>
  <input type="hidden" name="action" value="snippet">

  <h2 style="margin-top:0">Coller le code AdSense</h2>
  <p class="s">
    Le plus direct : copiez le bloc que Google vous donne dans
    <em>AdSense → Annonces</em> et collez-le ici. Le site en lit l’identifiant éditeur, l’unité
    et son format, puis pose lui-même le code sur les emplacements — l’unité trouvée sert
    <strong>tous les blocs</strong>. Collez l’extrait complet, ou seulement la ligne
    <code>&lt;script …&gt;</code> pour les annonces automatiques.
  </p>
  <p class="s" style="opacity:.75">
    Le code collé n’est pas réinjecté tel quel : un <code>&lt;script&gt;</code> inline forcerait
    à relâcher la politique de sécurité de la page. Il est relu, puis réécrit proprement.
  </p>

  <label class="field" style="margin-top:12px">
    <span class="visually-hidden">Code AdSense</span>
    <textarea class="input" name="snippet" rows="9" spellcheck="false"
              style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12.5px"
              placeholder="&lt;script async src=&quot;https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-…&quot; crossorigin=&quot;anonymous&quot;&gt;&lt;/script&gt;"><?= e($snippet) ?></textarea>
  </label>

  <?php if ($parsed !== null): ?>
    <div class="notice <?= $parsed['client'] !== '' ? 'notice-ok' : 'notice-err' ?>" role="status"
         style="margin-top:14px">
      <strong>Lu dans votre code :</strong>
      identifiant éditeur <?= $parsed['client'] !== '' ? '<code>' . e($parsed['client']) . '</code>' : '—' ?>,
      unité <?= $parsed['slot'] !== '' ? '<code>' . e($parsed['slot']) . '</code>' : 'aucune' ?><?php
        if ($parsed['format'] !== ''): ?>, format <code><?= e($parsed['format']) ?></code><?php endif; ?>.
      <?php foreach ($parsed['notes'] as $note): ?>
        <br><?= e($note) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div style="margin-top:16px">
    <button type="submit" class="btn btn-coral">Lire ce code et l’appliquer</button>
  </div>
</form>
<?php if ($client === ''): ?>
  <div class="notice notice-wait">
    Sans identifiant éditeur, les emplacements affichent le cadre en pointillés de la maquette.
    Il se saisit dans <a href="/admin/cles-api">Clés d’API</a>. Les réglages ci-dessous restent actifs.
  </div>
<?php endif; ?>

<form method="post" class="admin-card">
  <?= Csrf::field('admin-ads') ?>

  <h2 style="margin-top:0">Mode de diffusion</h2>
  <label class="check" style="align-items:flex-start;margin:10px 0">
    <input type="radio" name="mode" value="auto" <?= $mode === 'auto' ? 'checked' : '' ?>>
    <span>
      <strong>Annonces automatiques</strong> — rien à créer chez AdSense au-delà du compte.
      L’identifiant éditeur suffit : Google choisit lui-même où placer les annonces dans la page.
      Les sept emplacements ci-dessous ne sont alors pas posés.
      <br><span class="s">À activer aussi côté Google : AdSense → Annonces → Par site → votre
      site → <em>Annonces automatiques</em>.</span>
    </span>
  </label>
  <label class="check" style="align-items:flex-start;margin:10px 0 20px">
    <input type="radio" name="mode" value="slots" <?= $mode === 'slots' ? 'checked' : '' ?>>
    <span>
      <strong>Emplacements du site</strong> — les sept emplacements de la maquette, chacun servi
      par une unité AdSense. Placement maîtrisé et statistiques par emplacement, mais il faut
      créer les unités et recopier leur identifiant dans <a href="/admin/cles-api">Clés d’API</a>.
    </span>
  </label>

  <div class="table-scroll"<?= $mode === 'auto' ? ' style="opacity:.55"' : '' ?>>
    <table class="admin-table">
      <thead><tr><th>Emplacement</th><th>Format</th><th>Identifiant</th><th>Diffusion</th><th>Actif</th></tr></thead>
      <tbody>
        <?php foreach ($slots as $name => $slot): ?>
          <tr>
            <td><span class="t"><?= e($slot['label']) ?></span><br><span class="s"><?= e($name) ?></span></td>
            <td class="s"><?= e($slot['format']) ?></td>
            <td><?php
              if ($slot['slot'] === '') {
                  echo '<span class="state state-neutral">—</span>';
              } else {
                  echo '<span class="state state-ok">' . e($slot['slot']) . '</span>';
                  if ($slot['inherited']) {
                      echo '<br><span class="s">unité par défaut</span>';
                  }
              }
            ?></td>
            <td class="s"><?php
              // Ce que le site contrôle. Le remplissage, lui, dépend d'AdSense.
              if (!$slot['enabled']) {
                  echo '<span class="state state-neutral">désactivé ici</span>';
              } elseif ($client === '') {
                  echo '<span class="state state-wait">identifiant éditeur manquant</span>';
              } elseif ($slot['slot'] === '') {
                  echo '<span class="state state-wait">aucune unité, ni propre ni par défaut</span>';
              } else {
                  echo '<span class="state state-ok">code posé</span>';
              }
            ?></td>
            <td>
              <label class="check">
                <input type="checkbox" name="slot_<?= e($name) ?>" value="1" <?= $slot['enabled'] ? 'checked' : '' ?>>
                <span class="visually-hidden"><?= e($slot['label']) ?></span>
              </label>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <button type="submit" class="btn btn-coral" style="margin-top:18px"><?= e(I18n::t('admin.save')) ?></button>
</form>

<div class="admin-card" style="margin-top:22px">
  <h2 style="margin-top:0">Ce que le site fait, et ce qui dépend de Google</h2>

  <table class="admin-table" style="margin-top:10px">
    <tbody>
      <tr>
        <td><span class="t">Identifiant éditeur</span></td>
        <td><?= $client !== ''
            ? '<span class="state state-ok">' . e($client) . '</span>'
            : '<span class="state state-err">absent</span> — à saisir dans <a href="/admin/cles-api">Clés d’API</a>' ?></td>
      </tr>
      <tr>
        <td><span class="t">Mode</span></td>
        <td class="s"><?= $mode === 'auto'
            ? 'Annonces automatiques : le script est chargé, Google place les annonces.'
            : 'Emplacements du site : chaque bloc actif porte son unité.' ?></td>
      </tr>
      <tr>
        <td>
          <span class="t">Fichier ads.txt</span><br>
          <span class="s">exigé par AdSense à la racine du domaine</span>
        </td>
        <td class="s">
          <?php if ($client !== ''): ?>
            <span class="state state-ok">servi</span>
            <code>google.com, <?= e(str_starts_with($client, 'ca-') ? substr($client, 3) : $client) ?>, DIRECT, f08c47fec0942fa0</code><br>
            <a href="/ads.txt" target="_blank" rel="noopener">Vérifier /ads.txt</a>
          <?php else: ?>
            <span class="state state-wait">en attente de l’identifiant éditeur</span>
          <?php endif; ?>
        </td>
      </tr>
    </tbody>
  </table>

  <h3 style="margin:22px 0 6px">Un bloc reste vide malgré tout ?</h3>
  <p class="s">
    Lorsqu’AdSense n’a rien à servir, l’emplacement se replie de lui-même : la page ne garde pas
    d’espace vide. Quatre conditions restent du côté de Google, et aucune ne se règle ici :
  </p>
  <ol class="s" style="margin:10px 0 0; padding-left:20px; line-height:1.7">
    <li><strong>Le site doit être ajouté et validé</strong> dans <em>AdSense → Sites</em>. Tant
        qu’il est « en cours d’examen », rien n’est diffusé, où que soit posé le code.</li>
    <li><strong>Le consentement du visiteur.</strong> Avant son clic sur « Tout accepter », aucun
        script publicitaire n’est chargé. Pour tester : effacez les cookies du site, rechargez,
        acceptez.</li>
    <li><strong>Une unité neuve met du temps à se remplir</strong> — de quelques heures à 48 h.</li>
    <li><strong>Un bloqueur de publicité</strong> suffit à tout masquer : testez en navigation
        privée, extensions désactivées.</li>
  </ol>
</div>
