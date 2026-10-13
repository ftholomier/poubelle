<?php
/** Page publique « Intégrez le musée sur votre site » (/widgets/). Variables : $list [w, src, code, script, api, d (describe)], $resize, $base, $loader */
$L = \App\Services\I18n::lang();
$catCount = [];
$nCustom = 0;
foreach ($list as $it) {
    foreach ($it['d']['category'] as $ck) {
        $catCount[$ck] = ($catCount[$ck] ?? 0) + 1;
    }
    $nCustom += $it['d']['customizable'] ? 1 : 0;
}
?>
<section class="wrap phead2 wdemo-head">
  <span class="eyebrow eyebrow--lg"><?= e(t('Widgets gratuits')) ?></span>
  <h1 class="h-xl cbig"><?= e(t('Intégrez le musée sur votre site')) ?></h1>
  <p class="lead" style="max-width:60ch;color:var(--muted)"><?= e(t('Copiez une ligne de code et votre site affiche, chaque jour, un contenu du musée {site} : toujours à jour, sans rien installer, aux couleurs du musée.')) ?></p>
  <ul class="wdemo-rules">
    <li><?= e(t('Gratuit pour les widgets ci-dessous, sur tout site.')) ?></li>
    <li><?= e(t('Le logo du musée, la mention « non officiel » et le lien vers le musée restent toujours visibles.')) ?></li>
    <li><?= e(t('Thème clair ou sombre, en français ou en anglais.')) ?></li>
  </ul>
</section>
<section class="wrap wdemo">
  <?php if (!$list): ?>
    <p class="lead"><?= e(t('Aucun widget n’est ouvert à tous pour le moment.')) ?></p>
  <?php endif; ?>
  <?php if ($list): ?>
  <div class="wdemo-filter" data-cat-filter role="group" aria-label="<?= e(($L === 'en' ? 'Filter by category' : 'Filtrer par catégorie')) ?>">
    <button type="button" class="wdemo-chip is-on" data-cat="" aria-pressed="true"><?= e(($L === 'en' ? 'All' : 'Tous')) ?> <small><?= count($list) ?></small></button>
    <?php foreach (\App\Widgets\Registry::CATEGORIES as $ck => $cl): if (empty($catCount[$ck])) continue; ?><button type="button" class="wdemo-chip" data-cat="<?= e($ck) ?>" aria-pressed="false"><?= e(\App\Widgets\Registry::categoryLabel($ck, $L)) ?> <small><?= (int) $catCount[$ck] ?></small></button><?php endforeach; ?>
    <?php if ($nCustom): ?><button type="button" class="wdemo-chip" data-cat="personnalisables" aria-pressed="false"><?= e($L === 'en' ? 'Customisable' : 'Personnalisables') ?> <small><?= $nCustom ?></small></button><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php foreach ($list as $it): $w = $it['w']; $d = $it['d']; $k = $w->key(); ?>
  <article class="wdemo-item" id="<?= e($k) ?>" data-cats="<?= e(implode(' ', $d['category']) . ($d['customizable'] ? ' personnalisables' : '')) ?>"
    data-wcfg="<?= e(json_encode(['key' => $k, 'name' => $d['name'], 'height' => $d['height'], 'options' => $d['options'], 'base' => $base, 'loader' => $loader, 'lang' => $L, 'site' => \App\Core\Club::s('site')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
    <div class="wdemo-item__txt">
      <h2 class="h-3"><?= e($d['name']) ?><?php if ($d['customizable']): ?> <span class="wdemo-badge" title="<?= e(($L === 'en' ? 'Options:' : 'Options :') . ' ' . mb_strtolower(implode(', ', array_column($d['options'], 'label')))) ?>"><?= e($L === 'en' ? 'Customisable' : 'Personnalisable') ?></span><?php endif; ?></h2>
      <p><?= e($d['description']) ?></p>
      <?php if ($d['options']): ?>
      <fieldset class="wdemo-opts">
        <legend><?= e(($L === 'en' ? 'Set up the widget' : 'Réglez le widget')) ?></legend>
        <?php foreach ($d['options'] as $on => $o): $fid = 'o-' . $k . '-' . $on; ?>
        <label class="wdemo-opt" for="<?= e($fid) ?>"><span><?= e($o['label']) ?></span>
          <?php if ($o['type'] === 'select'): ?>
            <select id="<?= e($fid) ?>" data-opt="<?= e($on) ?>"><?php foreach ($o['choices'] as $v => $l): ?><option value="<?= e((string) $v) ?>"<?= (string) $v === $o['default'] ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
          <?php elseif ($o['type'] === 'search'): ?>
            <input id="<?= e($fid) ?>" type="search" data-opt="<?= e($on) ?>" list="dl-<?= e($fid) ?>" autocomplete="off" placeholder="<?= e((string) ($o['choices']['auto'] ?? ($L === 'en' ? 'Type a name' : 'Tapez un nom'))) ?>"<?= $o['total'] > count($o['choices']) ? ' data-suggest="' . e($o['suggest']) . '"' : '' ?>>
            <datalist id="dl-<?= e($fid) ?>"><?php foreach ($o['choices'] as $v => $l): if ($v === 'auto') continue; ?><option value="<?= e((string) $v) ?>"><?= e($l) ?></option><?php endforeach; ?></datalist>
          <?php elseif ($o['type'] === 'date'): ?>
            <input id="<?= e($fid) ?>" type="date" data-opt="<?= e($on) ?>"<?= isset($o['min']) ? ' min="' . e((string) $o['min']) . '"' : '' ?><?= ($o['format'] ?? '') === 'md' ? ' data-md' : '' ?>>
          <?php elseif ($o['type'] === 'number'): ?>
            <input id="<?= e($fid) ?>" type="number" data-opt="<?= e($on) ?>" min="<?= (int) $o['min'] ?>" max="<?= (int) $o['max'] ?>" value="<?= e($o['default']) ?>">
          <?php else: ?>
            <input id="<?= e($fid) ?>" type="text" data-opt="<?= e($on) ?>" maxlength="80" placeholder="<?= e((string) ($o['placeholder'] ?? '')) ?>">
          <?php endif; ?>
          <?php if (!empty($o['help'])): ?><small><?= e($o['help']) ?></small><?php endif; ?>
        </label>
        <?php endforeach; ?>
      </fieldset>
      <?php endif; ?>
      <label class="wdemo-label" for="script-<?= e($w->key()) ?>"><?= e(t('Code à copier (script : recommandé)')) ?></label>
      <textarea id="script-<?= e($w->key()) ?>" class="wdemo-code" readonly rows="3" data-code="<?= e($w->key()) ?>"><?= e($it['script']) ?></textarea>
      <label class="wdemo-label" for="code-<?= e($w->key()) ?>"><?= e(t('Ou code iframe (sans script)')) ?></label>
      <textarea id="code-<?= e($w->key()) ?>" class="wdemo-code" readonly rows="3" data-code="<?= e($w->key()) ?>"><?= e($it['code']) ?></textarea>
      <p class="wdemo-btns">
        <button type="button" class="btn btn--sm" data-copy="script-<?= e($w->key()) ?>"><?= e(t('Copier le script')) ?></button>
        <button type="button" class="btn btn--sm" data-copy="code-<?= e($w->key()) ?>"><?= e(t('Copier l’iframe')) ?></button>
        <button type="button" class="btn btn--sm btn--ghost" data-theme-toggle="<?= e($w->key()) ?>"><?= e(t('Voir en sombre')) ?></button>
      </p>
      <?php if ($d['options']): $first = array_key_first($d['options']); ?>
      <details class="wdemo-dyn"><summary><?= e(($L === 'en' ? 'Change the option from your site' : 'Changer l’option depuis votre site')) ?></summary>
        <p><?= e(($L === 'en' ? 'With the script code, just change the attribute (for instance data-' . $first . ' before each match): the widget reloads itself. With the iframe, change ?' . $first . '=… in its address. An unknown value gives the automatic setting.' : 'Avec le code script, modifiez simplement l’attribut (par exemple data-' . $first . ' avant chaque match) : le widget se recharge seul. Avec l’iframe, changez ?' . $first . '=… dans son adresse. Une valeur inconnue donne le réglage automatique.')) ?></p>
        <textarea class="wdemo-code" readonly rows="4" data-dyn>document.querySelector('[data-musee-widget="<?= e($k) ?>"]').setAttribute('data-<?= e($first) ?>', '…');</textarea>
      </details>
      <?php endif; ?>
      <p class="wdemo-api"><?= e(t('Données brutes (JSON) :')) ?> <a href="<?= e($it['api']) ?>" rel="nofollow" target="_blank" data-api><code><?= e(preg_replace('#^https?://#', '', $it['api'])) ?></code></a></p>
    </div>
    <div class="wdemo-item__view">
      <iframe src="<?= e($it['src'] . (str_contains($it['src'], '?') ? '&' : '?') . 'apercu=1') ?>" title="<?= e($d['name']) ?>" data-musee="<?= e($w->key()) ?>" width="100%" height="<?= (int) $w->height() ?>" loading="lazy"></iframe>
    </div>
  </article>
  <?php endforeach; ?>
  <aside class="wdemo-more">
    <h2 class="h-3"><?= e(t('Script ou iframe ?')) ?></h2>
    <p><?= e(t('Le code « script » est le plus simple : plusieurs widgets sur la même page avec une seule balise script, chargés quand ils arrivent à l’écran, toujours à la bonne hauteur, isolés de votre site (ni cookie ni style partagés).')) ?></p>
    <p><?= e(t('Le code iframe fonctionne partout, même là où les scripts sont interdits. Pour qu’il prenne lui aussi la hauteur de son contenu, ajoutez une fois ces quelques lignes dans votre page :')) ?></p>
    <textarea id="code-resize" class="wdemo-code" readonly rows="3"><?= e($resize) ?></textarea>
    <p class="wdemo-btns"><button type="button" class="btn btn--sm" data-copy="code-resize"><?= e(t('Copier le code')) ?></button></p>
  </aside>
  <aside class="wdemo-partner wdemo-screens" id="ecrans">
    <h2 class="h-3"><?= e(t('Pour les écrans de stade')) ?></h2>
    <p><?= e(t('Écran géant, télés des buvettes et des loges, borne d’accueil : le mode écran affiche le musée en plein écran, en très gros caractères, avec des slides qui s’enchaînent seules (histoire du duel contre l’adversaire du jour, ce jour-là, légendes, chiffres, quiz avec QR code, photos d’archives, records, frise…).')) ?></p>
    <ul class="wdemo-rules">
      <li><?= e(t('Trois programmes prêts : avant-match, mi-temps, buvette en continu ; ou un programme sur mesure.')) ?></li>
      <li><?= e(t('Paysage 16:9 ou vertical 9:16, en français ou en anglais, sans son, et qui continue de tourner pendant une coupure de réseau.')) ?></li>
      <li><?= e(t('Pour les régies qui ne lisent pas de page web : les images de chaque slide, ou une vidéo.')) ?></li>
    </ul>
    <p><a class="btn" href="<?= e(url('/contact/?objet=partenariat')) ?>"><?= e(t('Parlons de vos écrans')) ?> →</a></p>
  </aside>
  <aside class="wdemo-partner">
    <h2 class="h-3"><?= e(t('Devenir partenaire')) ?></h2>
    <p><?= e(t('Club, média, office de tourisme, association : d’autres widgets, l’accès aux données brutes et les écrans de stade sont réservés aux partenaires du musée, avec une clé.')) ?></p>
    <p><a class="btn" href="<?= e(url('/contact/?objet=partenariat')) ?>"><?= e(t('Nous contacter')) ?> →</a></p>
  </aside>
</section>
