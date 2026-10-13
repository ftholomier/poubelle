<?php
/** Page publique « Intégrez le musée sur votre site » (/widgets/). Variables : $list [w, src, code, script, api], $resize */
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
  <?php foreach ($list as $it): $w = $it['w']; ?>
  <article class="wdemo-item" id="<?= e($w->key()) ?>">
    <div class="wdemo-item__txt">
      <h2 class="h-3"><?= e($w->name(\App\Services\I18n::lang())) ?></h2>
      <p><?= e($w->description(\App\Services\I18n::lang())) ?></p>
      <label class="wdemo-label" for="script-<?= e($w->key()) ?>"><?= e(t('Code à copier (script : recommandé)')) ?></label>
      <textarea id="script-<?= e($w->key()) ?>" class="wdemo-code" readonly rows="3" data-code="<?= e($w->key()) ?>"><?= e($it['script']) ?></textarea>
      <label class="wdemo-label" for="code-<?= e($w->key()) ?>"><?= e(t('Ou code iframe (sans script)')) ?></label>
      <textarea id="code-<?= e($w->key()) ?>" class="wdemo-code" readonly rows="3" data-code="<?= e($w->key()) ?>"><?= e($it['code']) ?></textarea>
      <p class="wdemo-btns">
        <button type="button" class="btn btn--sm" data-copy="script-<?= e($w->key()) ?>"><?= e(t('Copier le script')) ?></button>
        <button type="button" class="btn btn--sm" data-copy="code-<?= e($w->key()) ?>"><?= e(t('Copier l’iframe')) ?></button>
        <button type="button" class="btn btn--sm btn--ghost" data-theme-toggle="<?= e($w->key()) ?>"><?= e(t('Voir en sombre')) ?></button>
      </p>
      <p class="wdemo-api"><?= e(t('Données brutes (JSON) :')) ?> <a href="<?= e($it['api']) ?>" rel="nofollow" target="_blank"><code><?= e(preg_replace('#^https?://#', '', $it['api'])) ?></code></a></p>
    </div>
    <div class="wdemo-item__view">
      <iframe src="<?= e($it['src'] . (str_contains($it['src'], '?') ? '&' : '?') . 'apercu=1') ?>" title="<?= e($w->name(\App\Services\I18n::lang())) ?>" data-musee="<?= e($w->key()) ?>" width="100%" height="<?= (int) $w->height() ?>" loading="lazy"></iframe>
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
  <aside class="wdemo-partner">
    <h2 class="h-3"><?= e(t('Devenir partenaire')) ?></h2>
    <p><?= e(t('Club, média, office de tourisme, association : d’autres widgets, l’accès aux données brutes et les écrans de stade sont réservés aux partenaires du musée, avec une clé.')) ?></p>
    <p><a class="btn" href="<?= e(url('/contact/?objet=partenariat')) ?>"><?= e(t('Nous contacter')) ?> →</a></p>
  </aside>
</section>
