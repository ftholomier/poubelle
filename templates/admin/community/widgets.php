<?php
/**
 * Widgets & partenaires. Variables : $tab, $widgets [clé => w, cfg, describe], $planned, $partners, $keys,
 * $stats, $days, $base, $resize, $loader, $closed, $newKey, $edit, $screens (onglet Écrans : all, edit, exports, types, clubs, access, video)
 */
use App\Admin\Form;
use App\Widgets\Screen\Playlists;
use App\Widgets\Store;

$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$accessLabel = Store::ACCESS;
$pname = fn (string $id) => $id === 'libre' ? 'Sans clé (widgets libres)' : (string) ($partners[$id]['name'] ?? $id . ' (supprimé)');
?>
<?php if ($tab === 'catalogue'): ?>
<p class="alert alert--info" style="margin:0">Les widgets affichent un contenu du musée sur d’autres sites, toujours aux couleurs du musée, avec son logo, la mention « non officiel » et un lien vers le musée (impossibles à retirer). Réglez l’accès de chacun, puis copiez le code (script ou iframe) dans le configurateur. Page publique de démonstration : <a href="/widgets/" target="_blank" rel="noopener">/widgets/</a> (widgets libres seulement).<?= $closed ? ' <b>Le musée est fermé au public</b> : seuls les widgets cochés « actif même site fermé » sont servis.' : '' ?></p>

<section class="card" id="configurateur" data-widget-config="<?= e(json_encode([
    'base' => $base,
    'widgets' => array_map(fn ($x) => $x['describe'] + ['access' => $x['cfg']['access']], $widgets),
    'partners' => $keys,
    'resize' => $resize,
    'loader' => $loader,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
  <div class="card__head"><h2 class="card__t">Configurateur</h2></div>
  <div class="card__body">
    <div class="cols cols--wide" style="align-items:start">
      <div class="stack">
        <div class="row">
          <label class="f" style="flex:1 1 180px"><span class="f__k">Widget</span>
            <select class="in" data-cfg="widget"><?php foreach ($widgets as $k => $x): ?><option value="<?= e($k) ?>"><?= e($x['w']->name()) ?> (<?= e(mb_strtolower($accessLabel[$x['cfg']['access']])) ?>)</option><?php endforeach; ?></select></label>
          <label class="f"><span class="f__k">Thème</span><select class="in" data-cfg="theme"><option value="clair">Clair</option><option value="sombre">Sombre</option></select></label>
          <label class="f"><span class="f__k">Langue</span><select class="in" data-cfg="lang"><option value="fr">Français</option><option value="en">Anglais</option></select></label>
          <label class="f"><span class="f__k">Format</span><select class="in" data-cfg="format"><option value="">Standard</option><option value="compact">Compact (sans photo)</option></select></label>
        </div>
        <div class="row" data-cfg-options></div>
        <label class="f"><span class="f__k">Partenaire (sa clé est ajoutée au code ; obligatoire pour un widget réservé ou payant)</span>
          <select class="in" data-cfg="partner"><option value="">Aucun : code public (widgets libres)</option>
            <?php foreach ($keys as $k): ?><option value="<?= e($k['id']) ?>"<?= $k['status'] !== 'actif' ? ' disabled' : '' ?>><?= e($k['name']) ?><?= $k['status'] !== 'actif' ? ' (suspendu)' : ($k['paid'] ? ' · offre payante' : '') ?><?= $k['domains'] ? ' · ' . e(implode(', ', $k['domains'])) : '' ?></option><?php endforeach; ?>
          </select></label>
        <p class="small muted" data-cfg-warn style="margin:0"></p>
        <label class="f"><span class="f__k">Code script à copier (recommandé : iframe isolée, hauteur automatique, chargement quand le widget arrive à l’écran ; la ligne &lt;script&gt; une seule fois par page)</span><textarea class="in mono" rows="3" readonly data-cfg-out="script"></textarea></label>
        <div class="row"><button type="button" class="btn btn--sm btn--navy" data-cfg-copy="script">Copier le script</button></div>
        <details data-cfg-dyn><summary class="small">Changer une option depuis le site du partenaire (sans recharger sa page)</summary>
          <p class="small muted" style="margin:6px 0">Avec le code script, il suffit de modifier l’attribut (ex. <code>data-adversaire</code> avant chaque match) : le widget se recharge seul. Ou appeler <code>MuseeWidgets.reload(el)</code>. Avec l’iframe : changer <code>?adversaire=…</code> dans son adresse. Valeurs possibles : <code>/api/v1/widgets/&lt;widget&gt;/suggestions/&lt;option&gt;?q=…</code> (JSON) ; valeur inconnue : réglage automatique.</p>
          <textarea class="in mono" rows="5" readonly data-cfg-out="dynamic"></textarea></details>
        <label class="f"><span class="f__k">Ou code iframe (sans script : fonctionne partout)</span><textarea class="in mono" rows="4" readonly data-cfg-out="iframe"></textarea></label>
        <div class="row"><button type="button" class="btn btn--sm" data-cfg-copy="iframe">Copier l’iframe</button><a class="btn btn--sm" href="#" target="_blank" rel="noopener" data-cfg-open>Ouvrir le widget</a></div>
        <label class="f"><span class="f__k">Adresse de l’API (données JSON)</span><input class="in mono" readonly data-cfg-out="api"></label>
        <div class="row"><button type="button" class="btn btn--sm" data-cfg-copy="api">Copier l’adresse</button><span class="small muted">Clé aussi possible dans l’en-tête <code>X-Musee-Key</code>.</span></div>
        <details><summary class="small">Hauteur automatique du code iframe (facultatif, à donner au partenaire ; inutile avec le code script)</summary>
          <textarea class="in mono" rows="3" readonly data-cfg-out="resize"><?= e($resize) ?></textarea></details>
      </div>
      <div class="stack">
        <p class="small muted" style="margin:0">Aperçu en direct (vous êtes connecté : l’aperçu s’affiche quel que soit l’accès, sans compter dans les statistiques).</p>
        <iframe data-cfg-preview title="Aperçu du widget" style="border:0;width:100%;max-width:440px;height:520px;background:transparent"></iframe>
      </div>
    </div>
  </div>
</section>

<h2 class="h3" style="margin:24px 0 10px">Catalogue</h2>
<?php $catCount = []; $nCustom = 0; foreach ($widgets as $x) { foreach ($x['w']->category() as $ck) { $catCount[$ck] = ($catCount[$ck] ?? 0) + 1; } $nCustom += $x['w']->options() ? 1 : 0; } ?>
<div class="row wcat-filter" data-cat-filter role="group" aria-label="Filtrer par catégorie" style="margin-bottom:12px">
  <button type="button" class="chip is-on" data-cat="" aria-pressed="true">Tous <small><?= count($widgets) ?></small></button>
  <?php foreach (\App\Widgets\Registry::CATEGORIES as $ck => [$cl]): if (empty($catCount[$ck])) continue; ?><button type="button" class="chip" data-cat="<?= e($ck) ?>" aria-pressed="false"><?= e($cl) ?> <small><?= (int) $catCount[$ck] ?></small></button><?php endforeach; ?>
  <button type="button" class="chip" data-cat="personnalisables" aria-pressed="false">Personnalisables <small><?= $nCustom ?></small></button>
</div>
<div class="wcat" data-masonry>
  <?php foreach ($widgets as $k => $x): $w = $x['w']; $cfg = $x['cfg']; $optNames = array_map(fn ($o) => $o['label'], $w->options()); ?>
  <article class="card" id="w-<?= e($k) ?>" data-cats="<?= e(implode(' ', $w->category()) . ($optNames ? ' personnalisables' : '')) ?>">
    <div class="card__head"><h3 class="card__t"><?= e($w->name()) ?><?php if ($optNames): ?> <span class="wbadge" title="Options : <?= e(mb_strtolower(implode(', ', $optNames))) ?>">Personnalisable</span><?php endif; ?></h3><span class="chip<?= $cfg['access'] === 'libre' ? ' is-on' : '' ?>" style="cursor:default"><?= e($accessLabel[$cfg['access']]) ?></span></div>
    <div class="card__body stack">
      <p class="small" style="margin:0"><?= e($w->description()) ?></p>
      <iframe src="/w/<?= e($k) ?>?apercu=1" title="Aperçu : <?= e($w->name()) ?>" loading="lazy" style="border:0;width:100%;height:<?= (int) $w->height() ?>px"></iframe>
      <form method="post" action="/admin/widgets" class="stack" style="gap:8px">
        <?= csrf_field() ?><input type="hidden" name="action" value="acces"><input type="hidden" name="widget" value="<?= e($k) ?>">
        <label class="f"><span class="f__k">Accès</span><select class="in" name="access"><?php foreach ($accessLabel as $a => $label): ?><option value="<?= e($a) ?>"<?= $cfg['access'] === $a ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <?php foreach ($w->settings() as $sk => $so): ?>
        <label class="f"><span class="f__k"><?= e($so['label']) ?></span><select class="in" name="settings[<?= e($sk) ?>]"><?php foreach ($so['choices'] as $v => $label): ?><option value="<?= e($v) ?>"<?= ($cfg['settings'][$sk] ?? '') === $v ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?php if (!empty($so['help'])): ?><small class="muted"><?= e($so['help']) ?></small><?php endif; ?></label>
        <?php endforeach; ?>
        <label class="small"><input type="checkbox" name="closed_ok" value="1"<?= $cfg['closed_ok'] ? ' checked' : '' ?>> Actif même site fermé (page d’attente, mot de passe d’accès)</label>
        <div class="row"><button class="btn btn--sm btn--navy">Enregistrer</button><button type="button" class="btn btn--sm btn--ghost" data-cfg-pick="<?= e($k) ?>">Configurer ↑</button></div>
      </form>
    </div>
  </article>
  <?php endforeach; ?>
</div>

<?php if ($planned): ?>
<h2 class="h3" style="margin:24px 0 10px">À venir</h2>
<div class="table"><table><thead><tr><th>Widget</th><th>Ce qu’il montre</th><th class="t-num">Phase</th></tr></thead><tbody>
  <?php foreach ($planned as $k => [$name, $desc, $phase]): ?><tr class="muted"><td><?= e($name) ?> <small>(<?= e($k) ?>)</small></td><td><?= e($desc) ?></td><td class="t-num"><?= (int) $phase ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<form method="post" action="/admin/widgets" class="row" style="margin-top:16px"><?= csrf_field() ?><input type="hidden" name="action" value="cache"><button class="btn btn--sm">Vider le cache des widgets</button><span class="small muted">Les widgets se mettent à jour seuls (au plus tard en une heure, et à minuit) ; videz le cache pour voir tout de suite une correction.</span></form>

<?php elseif ($tab === 'ecrans'):
    $S = $screens;
    $dur = fn (int $sec) => ($sec >= 60 ? intdiv($sec, 60) . ' min ' : '') . ($sec % 60 ? ($sec % 60) . ' s' : '');
    // Panneau « Contrôle » (générateur de lien et éditeur) : slides affichées ou sautées, durée réelle.
    $controle = fn (string $id) => '<details class="card" data-check="' . $id . '" style="margin:0"><summary class="card__head" style="cursor:pointer"><b class="card__t">Contrôle avant diffusion</b></summary><div class="card__body stack">'
        . '<p class="small muted" style="margin:0">Pour les réglages choisis (adversaire, langue) et le jour du match, la liste des slides : <b>✓ affichée</b> ou <b>⚠ sautée</b> (et pourquoi : aucun match ce jour-là, boutique fermée…), puis la durée réelle d’une boucle. Une slide sautée ne laisse pas d’écran vide : la suivante prend sa place. Le jour ne change que « Ce jour-là » ; quiz, photo et chiffre sont ceux d’aujourd’hui.</p>'
        . '<div class="row"><label class="f"><span class="f__k">Jour du match</span><input class="in" type="date" data-check-date value="' . e(date('Y-m-d')) . '"></label><button type="button" class="btn btn--sm btn--navy" data-check-run>Contrôler</button></div>'
        . '<div data-check-out aria-live="polite"></div></div></details>';
    $optLabel = ['adversaire' => 'Adversaire (vide : celui de la playlist)', 'cat' => 'Classement', 'n' => 'Variante (1, 2, 3…)', 'saison' => 'Saison (AAAA-AAAA)'];
?>
<p class="alert alert--info" style="margin:0">Le <b>mode écran</b> affiche le musée en plein écran sur les écrans du stade, les télés d’une buvette ou une borne : de très gros caractères, des slides qui s’enchaînent seules, le logo du musée, la mention « non officiel » et l’adresse du musée toujours en bas. Une playlist est une suite de slides (les données des widgets, mises en page pour l’écran). Accès du mode écran (widget « Playlist écran ») : <b><?= e(Store::ACCESS[$S['access']]) ?></b> — <a href="/admin/widgets#w-playlist">modifier</a>. Réservé (par défaut) : l’adresse donnée à la régie contient la clé d’un partenaire.</p>

<datalist id="dl-clubs"><?php foreach ($S['clubs'] as $k => $l): ?><option value="<?= e((string) $k) ?>"><?= e($l) ?></option><?php endforeach; ?></datalist>
<section class="card" id="lien-regie" data-screen-link="<?= e(json_encode(['base' => $base, 'partners' => $keys, 'playlists' => array_map(fn ($p) => ['format' => $p['format'], 'lang' => $p['lang'], 'adversaire' => $p['adversaire']], $S['all'])], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
  <div class="card__head"><h2 class="card__t">Lien à donner à la régie</h2></div>
  <div class="card__body stack">
    <div class="row">
      <label class="f" style="flex:1 1 180px"><span class="f__k">Playlist</span><select class="in" data-sl="id"><?php foreach ($S['all'] as $id => $p): ?><option value="<?= e($id) ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></label>
      <label class="f" style="flex:1 1 220px"><span class="f__k">Partenaire (sa clé est ajoutée)</span><select class="in" data-sl="partner"><option value="">Aucun (accès libre seulement)</option><?php foreach ($keys as $k): ?><option value="<?= e($k['id']) ?>"<?= $k['status'] !== 'actif' ? ' disabled' : '' ?>><?= e($k['name']) ?><?= $k['status'] !== 'actif' ? ' (suspendu)' : '' ?></option><?php endforeach; ?></select></label>
      <label class="f"><span class="f__k">Format</span><select class="in" data-sl="format"><option value="">Celui de la playlist</option><?php foreach (Playlists::FORMATS as $f => $l): ?><option value="<?= e($f) ?>"><?= e($l) ?></option><?php endforeach; ?></select></label>
      <label class="f"><span class="f__k">Langue</span><select class="in" data-sl="lang"><option value="">Celle de la playlist</option><option value="fr">Français</option><option value="en">Anglais</option></select></label>
      <label class="f" style="flex:1 1 220px"><span class="f__k">Adversaire du jour</span><input class="in" data-sl="adversaire" list="dl-clubs" maxlength="40" autocomplete="off" placeholder="Celui de la playlist — ou tapez un nom"></label>
      <label class="f"><span class="f__k">Horloge</span><select class="in" data-sl="horloge"><option value="">Selon la playlist</option><option value="1">Affichée</option><option value="0">Masquée</option></select></label>
    </div>
    <label class="f"><span class="f__k">Adresse à ouvrir en plein écran sur l’écran (navigateur en mode kiosque, touche F11)</span><input class="in mono" readonly data-sl-out></label>
    <div class="row"><button type="button" class="btn btn--sm btn--navy" data-sl-copy>Copier l’adresse</button><a class="btn btn--sm" href="#" target="_blank" rel="noopener" data-sl-open>Aperçu plein écran ↗</a><span class="small muted" data-sl-warn></span></div>
    <p class="small muted" style="margin:0"><b>Adversaire du jour</b> : choisissez dans la liste un club déjà affronté, ou tapez le nom de n’importe quel club en toutes lettres (« Paris FC ») : s’il n’a jamais été affronté, la slide « Histoire du duel » devient « Première rencontre ». Vérifiez avec le <b>Contrôle</b> ci-dessous avant de donner l’adresse.</p>
    <?= $controle('sl') ?>
    <p class="small muted" style="margin:0">Une clé limitée à des domaines fonctionne aussi pour l’écran (une page ouverte directement n’a pas de site d’origine) : préférez une clé propre à la régie, à régénérer si l’adresse circule. La page se recharge seule (selon la playlist, toutes les 30 minutes par défaut) et continue de tourner pendant une coupure de réseau.</p>
  </div>
</section>

<h2 class="h3" style="margin:24px 0 10px">Playlists</h2>
<?php if ($S['video']): ?><p class="alert alert--error small" style="margin:0 0 12px"><b>Vidéo MP4 indisponible sur ce serveur.</b> <?= e($S['video']) ?></p><?php endif; ?>
<div class="table"><table><thead><tr><th>Playlist</th><th class="t-num">Slides</th><th class="t-num">Une boucle</th><th>Format, langue</th><th>Exports</th><th></th></tr></thead><tbody>
<?php foreach ($S['all'] as $id => $p): $x = $S['exports'][$id]; $v = $x['video']; ?>
  <tr id="e-<?= e($id) ?>">
    <td><b><?= e($p['name']) ?></b> <?= !empty($p['preset']) ? '<span class="pill">' . (!empty($p['modified']) ? 'prête à l’emploi · modifiée' : 'prête à l’emploi') . '</span>' : '' ?><?php if (!empty($p['note'])): ?><br><small class="muted"><?= e($p['note']) ?></small><?php endif; ?><br><small class="mono muted">/ecran/<?= e($id) ?></small></td>
    <td class="t-num"><?= count($p['slides']) ?></td>
    <td class="t-num"><?= e($dur(Playlists::duration($p))) ?></td>
    <td class="small"><?= e(Playlists::FORMATS[$p['format']] ?? $p['format']) ?>, <?= $p['lang'] === 'en' ? 'anglais' : 'français' ?><?php $t = $p['adversaire'] !== '' ? \App\Widgets\Items\HistoireDuDuel::target((string) $p['adversaire']) : null; ?><?= $t ? '<br>adversaire : ' . e($t['name']) . ($t['club'] === null ? ' (première rencontre)' : '') : '' ?></td>
    <td class="small">
      <a class="btn btn--sm" href="/admin/widgets/ecrans/zip?id=<?= e(rawurlencode($id)) ?>">Images PNG (ZIP)</a>
      <?php if ($v && $v['status'] === 'pret'): ?>
        <a class="btn btn--sm btn--navy" href="/admin/widgets/ecrans/fichier?f=<?= e(rawurlencode((string) $v['file'])) ?>">Vidéo MP4 (<?= e(number_format(((int) $v['size']) / 1048576, 1, ',', ' ')) ?> Mo)</a><br><small class="muted">faite le <?= e(date('d/m à H:i', (int) $v['at'])) ?></small>
      <?php elseif ($v && in_array($v['status'], ['attente', 'en-cours'], true)): ?>
        <span class="pill">vidéo en préparation…</span>
      <?php elseif ($v && $v['status'] === 'erreur'): ?>
        <br><small style="color:var(--red)">Vidéo : <?= e((string) $v['error']) ?></small>
      <?php endif; ?>
      <?php if (!$S['video'] && (!$v || !in_array($v['status'], ['attente', 'en-cours'], true))): ?>
        <form method="post" action="/admin/widgets" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="ecran-video"><input type="hidden" name="id" value="<?= e($id) ?>"><button class="btn btn--sm"><?= $v && $v['status'] === 'pret' ? 'Refaire la vidéo' : 'Fabriquer la vidéo MP4' ?></button></form>
      <?php endif; ?>
    </td>
    <td class="row" style="flex-wrap:nowrap">
      <a class="btn btn--sm" href="/ecran/<?= e($id) ?>" target="_blank" rel="noopener">Aperçu ↗</a>
      <a class="btn btn--sm" href="/admin/widgets?onglet=ecrans&amp;ecran=<?= e(rawurlencode($id)) ?>#editeur">Modifier</a>
      <?php if (!empty($p['preset']) && !empty($p['modified'])): ?>
        <form method="post" action="/admin/widgets" data-confirm="Rétablir la version d’origine ?|Vos modifications de cette playlist seront perdues (l’adresse d’écran ne change pas).|Rétablir"><?= csrf_field() ?><input type="hidden" name="action" value="ecran-supprimer"><input type="hidden" name="id" value="<?= e($id) ?>"><button class="btn btn--sm btn--ghost">Rétablir l’original</button></form>
      <?php elseif (empty($p['preset'])): ?>
        <form method="post" action="/admin/widgets" data-confirm="Supprimer cette playlist ?|Son adresse d’écran ne fonctionnera plus.|Supprimer"><?= csrf_field() ?><input type="hidden" name="action" value="ecran-supprimer"><input type="hidden" name="id" value="<?= e($id) ?>"><button class="btn btn--sm btn--danger">Supprimer</button></form>
      <?php endif; ?>
      <form method="post" action="/admin/widgets"><?= csrf_field() ?><input type="hidden" name="action" value="ecran-dupliquer"><input type="hidden" name="id" value="<?= e($id) ?>"><button class="btn btn--sm btn--ghost">Dupliquer</button></form>
    </td>
  </tr>
<?php endforeach; ?>
</tbody></table></div>
<p class="row" style="margin-top:12px"><a class="btn btn--navy" href="/admin/widgets?onglet=ecrans&amp;ecran=nouveau#editeur">+ Nouvelle playlist</a><span class="small muted">Toutes les playlists se modifient, y compris les trois prêtes à l’emploi (« Rétablir l’original » les remet comme au départ) ; « Dupliquer » en fait une variante.</span></p>

<?php if ($S['edit']): $E = $S['edit']; $ro = false; ?>
<form class="card stack" id="editeur" data-json-form data-url="/admin/widgets/ecrans" novalidate>
  <div class="card__head"><h2 class="card__t"><?= $E['id'] === '' ? 'Nouvelle playlist' : ($ro ? 'Playlist « ' . e($E['name']) . ' » (prête à l’emploi : lecture seule)' : 'Modifier « ' . e($E['name']) . ' »') ?></h2></div>
  <div class="card__body stack">
    <?= Form::hidden('id', $ro ? '' : $E['id']) ?>
    <div class="fgrid">
      <?= Form::text('name', 'Nom', $E['name'], ['maxlength' => 60, 'required' => true, 'class' => 'f--2', 'placeholder' => 'Ex. : Écran géant — avant-match']) ?>
      <?= Form::select('format', 'Format', $E['format'], Playlists::FORMATS) ?>
      <?= Form::select('lang', 'Langue', $E['lang'], ['fr' => 'Français', 'en' => 'Anglais']) ?>
      <?= Form::text('adversaire', 'Adversaire du jour (histoire du duel) : liste ou nom libre', $E['adversaire'], ['list' => 'dl-clubs', 'maxlength' => 40, 'class' => 'f--2', 'placeholder' => 'Vide : celui du dernier match fiché', 'autocomplete' => 'off']) ?>
      <?= Form::number('reload', 'Rechargement (minutes)', $E['reload']) ?>
      <?= Form::toggle('clock', 'Horloge en bas à droite', !empty($E['clock'])) ?>
    </div>
    <p class="small muted" style="margin:0">Slides dans l’ordre (glisser-déposer avec la poignée, ou flèches ↑ ↓). Durée en secondes (<?= Playlists::MIN ?> à <?= Playlists::MAX ?>). Une slide sans rien à montrer ce jour-là (aucun match « ce jour-là »…) est sautée.</p>
    <?= Form::repeater('slides', '', $E['slides'], function ($sl) use ($S, $optLabel) {
        $h = '<div class="ec-row">' . Form::select('@type', 'Slide', $sl['type'] ?? 'chiffre-du-jour', array_map(fn ($t) => $t['name'], $S['types']), ['class' => 'f--2', 'data' => ['slide-type' => '1']])
            . Form::number('@duration', 'Durée (s)', $sl['duration'] ?? 10);
        // Options de chaque type de slide : celles du widget du même nom (listes réelles du musée).
        $names = array_values(array_unique(array_merge(...array_map(fn ($t) => $t['options'], array_values($S['types'])))));
        foreach ($names as $o) {
            $types = array_keys(array_filter($S['types'], fn ($t) => in_array($o, $t['options'], true)));
            // Une option commune à plusieurs types (« n ») : un seul champ, montré pour chacun d'eux.
            foreach (in_array($o, ['n', 'adversaire'], true) ? [$types[0]] : $types as $ty) {
                $for = in_array($o, ['n', 'adversaire'], true) ? implode(' ', $types) : $ty;
                $val = (string) ($sl['opt'][$o] ?? '');
                $wo = \App\Widgets\Registry::get($ty)?->describe()['options'][$o] ?? null;
                $field = match (true) {
                    $o === 'adversaire' => Form::text('@opt.adversaire', $optLabel[$o], $val, ['list' => 'dl-clubs', 'maxlength' => 40, 'placeholder' => 'Celui de la playlist — ou un nom', 'autocomplete' => 'off']),
                    $o === 'n' => Form::number('@opt.n', $optLabel[$o], $val ?: '1'),
                    $wo !== null && $wo['type'] === 'search' => Form::text('@opt.' . $o, $wo['label'] . ' (tapez un nom ; vide : automatique)', $val, ['list' => 'dl-' . $ty . '-' . $o, 'maxlength' => 80]),
                    $wo !== null && isset($wo['choices']) => Form::select('@opt.' . $o, $wo['label'], $val !== '' ? $val : $wo['default'], $wo['choices']),
                    default => '',
                };
                $h .= '<div data-opt-for="' . e($for) . '" class="f--2">' . $field . '</div>';
            }
        }
        return $h . '</div>';
    }, ['compact' => true, 'numbered' => true, 'add' => 'Ajouter une slide', 'blank' => ['type' => 'chiffre-du-jour', 'duration' => 10, 'opt' => []]]) ?>
  </div>
  <?php foreach ($S['types'] as $ty => $t): foreach ($t['options'] as $o): $wo = \App\Widgets\Registry::get($ty)?->describe()['options'][$o] ?? null; if ($wo && $wo['type'] === 'search'): ?>
  <datalist id="dl-<?= e($ty . '-' . $o) ?>"><?php foreach (\App\Widgets\Registry::get($ty)->optionChoices($o) as $v => $l): if ($v === 'auto') continue; ?><option value="<?= e((string) $v) ?>"><?= e($l) ?></option><?php endforeach; ?></datalist>
  <?php endif; endforeach; endforeach; ?>
  <div class="card__body"><?= $controle('ed') ?></div>
  <div class="card__foot row">
    <?php if ($ro): ?><span class="small muted">Prête à l’emploi : dupliquez-la (bouton « Dupliquer » ci-dessus) pour la modifier.</span>
    <?php else: ?><button type="submit" class="btn btn--yellow">Enregistrer</button><span class="small muted" data-saved></span><?php endif; ?>
    <?php if ($E['id'] !== ''): ?><a class="btn btn--ghost" href="/ecran/<?= e($E['id']) ?>" target="_blank" rel="noopener">Aperçu plein écran ↗</a><?php endif; ?>
  </div>
</form>
<?php endif; ?>

<?php elseif ($tab === 'partenaires'): ?>
<p class="alert alert--info" style="margin:0">Chaque partenaire reçoit une clé, à ajouter au code (<code>?key=…</code>) ou à envoyer dans l’en-tête <code>X-Musee-Key</code>. Avec des <b>domaines autorisés</b>, la clé ne fonctionne que sur ces sites (et leurs sous-domaines) ; sans domaine, elle fonctionne partout, y compris depuis un serveur. <b>Suspendu</b> : la clé est refusée. <b>Offre payante</b> en cours (échéance non dépassée) : nécessaire pour les widgets « payants » ; aucun paiement en ligne pour l’instant, l’offre se règle à la main ici.</p>
<?php if ($newKey): ?>
  <div class="card" style="border-color:var(--yellow)"><div class="card__body stack">
    <p style="margin:0"><b>Clé de « <?= e((string) ($partners[$newKey['id']]['name'] ?? '')) ?> »</b> à transmettre au partenaire :</p>
    <div class="row"><input class="in mono" readonly value="<?= e((string) $newKey['key']) ?>" id="newkey" style="flex:1"><button type="button" class="btn btn--sm btn--navy" data-copy-input="newkey">Copier</button></div>
  </div></div>
<?php endif; ?>
<div class="cols cols--wide" style="align-items:start">
  <div class="table"><table><thead><tr><th>Partenaire</th><th>Domaines</th><th>Statut</th><th>Offre</th><th>Clé</th><th></th></tr></thead><tbody>
    <?php if (!$partners): ?><tr><td colspan="6" class="muted">Aucun partenaire pour l’instant.</td></tr><?php endif; ?>
    <?php foreach ($partners as $id => $p): ?>
    <tr id="p-<?= e($id) ?>">
      <td><b><?= e($p['name']) ?></b><?php if ($p['contact'] !== ''): ?><br><small class="muted"><?= e($p['contact']) ?></small><?php endif; ?></td>
      <td class="small"><?= $p['domains'] ? e(implode(', ', $p['domains'])) : '<span class="muted">partout</span>' ?></td>
      <td><?= $p['status'] === 'actif' ? 'Actif' : '<b style="color:var(--red)">Suspendu</b>' ?></td>
      <td class="small"><?= $p['offer'] === 'payante' ? 'Payante' . ($p['until'] !== '' ? ' jusqu’au ' . e(date('d/m/Y', strtotime($p['until']))) : '') . (Store::paid($p) ? '' : ' <b style="color:var(--red)">(échue)</b>') : 'Gratuite' ?></td>
      <td class="small mono">…<?= e((string) ($p['key_end'] ?? '')) ?></td>
      <td class="row" style="flex-wrap:nowrap">
        <a class="btn btn--sm" href="/admin/widgets?onglet=partenaires&amp;partenaire=<?= e($id) ?>#formulaire">Modifier</a>
        <form method="post" action="/admin/widgets" data-confirm="Régénérer la clé ?|L’ancienne clé cessera aussitôt de fonctionner chez « <?= e($p['name']) ?> ».|Régénérer"><?= csrf_field() ?><input type="hidden" name="action" value="cle"><input type="hidden" name="id" value="<?= e($id) ?>"><button class="btn btn--sm">Nouvelle clé</button></form>
        <form method="post" action="/admin/widgets" data-confirm="Supprimer ce partenaire ?|Sa clé ne fonctionnera plus. Ses statistiques passées restent.|Supprimer"><?= csrf_field() ?><input type="hidden" name="action" value="supprimer"><input type="hidden" name="id" value="<?= e($id) ?>"><button class="btn btn--sm btn--danger">Supprimer</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <form class="card" method="post" action="/admin/widgets" id="formulaire">
    <?= csrf_field() ?><input type="hidden" name="action" value="partenaire"><input type="hidden" name="id" value="<?= e((string) ($edit['id'] ?? '')) ?>">
    <div class="card__head"><h2 class="card__t"><?= $edit ? 'Modifier « ' . e($edit['name']) . ' »' : 'Ajouter un partenaire' ?></h2></div>
    <div class="card__body stack">
      <label class="f"><span class="f__k">Nom</span><input class="in" name="name" required maxlength="80" value="<?= e((string) ($edit['name'] ?? '')) ?>" placeholder="Ex. : Office de tourisme du Pays de Montbéliard"></label>
      <label class="f"><span class="f__k">Contact (personne, e-mail ou téléphone)</span><input class="in" name="contact" maxlength="160" value="<?= e((string) ($edit['contact'] ?? '')) ?>"></label>
      <label class="f"><span class="f__k">Domaines autorisés (un par ligne ; vide : partout)</span><textarea class="in" name="domains" rows="3" placeholder="exemple.fr"><?= e(implode("\n", (array) ($edit['domains'] ?? []))) ?></textarea></label>
      <div class="row">
        <label class="f"><span class="f__k">Statut</span><select class="in" name="status"><option value="actif">Actif</option><option value="suspendu"<?= ($edit['status'] ?? '') === 'suspendu' ? ' selected' : '' ?>>Suspendu</option></select></label>
        <label class="f"><span class="f__k">Offre</span><select class="in" name="offer"><option value="gratuite">Gratuite</option><option value="payante"<?= ($edit['offer'] ?? '') === 'payante' ? ' selected' : '' ?>>Payante</option></select></label>
        <label class="f"><span class="f__k">Échéance de l’offre</span><input class="in" type="date" name="until" value="<?= e((string) ($edit['until'] ?? '')) ?>"></label>
      </div>
      <label class="f"><span class="f__k">Note interne</span><textarea class="in" name="note" rows="2" maxlength="500"><?= e((string) ($edit['note'] ?? '')) ?></textarea></label>
    </div>
    <div class="card__foot row"><button class="btn btn--navy"><?= $edit ? 'Enregistrer' : 'Ajouter et créer la clé' ?></button><?php if ($edit): ?><a class="btn btn--ghost" href="/admin/widgets?onglet=partenaires">Annuler</a><?php endif; ?></div>
  </form>
</div>

<?php else: ?>
<div class="row" style="margin-bottom:12px"><?php foreach ([7, 30, 90, 365] as $d): ?><a class="chip<?= $days === $d ? ' is-on' : '' ?>" href="/admin/widgets?onglet=statistiques&amp;jours=<?= $d ?>"><?= $d ?> jours</a><?php endforeach; ?></div>
<div class="kpis">
  <div class="kpi kpi--yellow"><b><?= $fmt($stats['total']['vues']) ?></b><span>affichages</span><small>widgets (iframe) en <?= $days ?> jours</small></div>
  <div class="kpi"><b><?= $fmt($stats['total']['api']) ?></b><span>appels de l’API</span><small>données JSON</small></div>
  <div class="kpi"><b><?= $fmt($stats['total']['clics']) ?></b><span>clics vers le musée</span><small><?= $stats['total']['vues'] ? number_format(100 * $stats['total']['clics'] / $stats['total']['vues'], 1, ',', ' ') . ' % des affichages' : '—' ?></small></div>
</div>
<p class="small muted">Comptés par jour, sans adresse IP ni cookie : le widget, et le partenaire quand une clé est utilisée. Un widget gardé quelques minutes par le navigateur n’est compté qu’une fois.</p>
<div class="cols">
  <div class="table"><table><thead><tr><th>Widget</th><th class="t-num">Affichages</th><th class="t-num">API</th><th class="t-num">Clics</th></tr></thead><tbody>
    <?php if (!$stats['widgets']): ?><tr><td colspan="4" class="muted">Aucun affichage sur la période.</td></tr><?php endif; ?>
    <?php foreach ($stats['widgets'] as $k => $s): ?><tr><td><?= e(isset($widgets[$k]) ? $widgets[$k]['w']->name() : $k) ?></td><td class="t-num"><?= $fmt($s['vues']) ?></td><td class="t-num"><?= $fmt($s['api']) ?></td><td class="t-num"><?= $fmt($s['clics']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <div class="table"><table><thead><tr><th>Partenaire</th><th class="t-num">Affichages</th><th class="t-num">API</th><th class="t-num">Clics</th></tr></thead><tbody>
    <?php if (!$stats['partners']): ?><tr><td colspan="4" class="muted">Aucun affichage sur la période.</td></tr><?php endif; ?>
    <?php foreach ($stats['partners'] as $id => $s): ?><tr><td><?= e($pname((string) $id)) ?></td><td class="t-num"><?= $fmt($s['vues']) ?></td><td class="t-num"><?= $fmt($s['api']) ?></td><td class="t-num"><?= $fmt($s['clics']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php $max = max(1, max(array_map(fn ($d) => $d['vues'] + $d['api'], $stats['days']))); ?>
<div class="card" style="margin-top:16px"><div class="card__head"><h2 class="card__t">Jour par jour</h2></div><div class="card__body">
  <div class="wbars" role="img" aria-label="Affichages et appels par jour">
    <?php foreach ($stats['days'] as $day => $d): ?><span title="<?= e(date('d/m', strtotime($day))) ?> : <?= $fmt($d['vues']) ?> affichage(s), <?= $fmt($d['api']) ?> appel(s), <?= $fmt($d['clics']) ?> clic(s)" style="height:<?= max(2, (int) round(100 * ($d['vues'] + $d['api']) / $max)) ?>%"></span><?php endforeach; ?>
  </div>
</div></div>
<?php endif; ?>
