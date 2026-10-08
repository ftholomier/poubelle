<?php
/**
 * Boutique : le livre « 100 récits du Lion », personnalisé (App\Shop\BookShop). Sans JavaScript, le
 * formulaire fonctionne (sans maillot 3D ni recherche de match) ; avec, la couverture se met à jour en
 * direct, le maillot tourne en 3D et l'extrait se feuillette (js/livre.js).
 * Variables : $config, $book, $count, $covers, $jerseys, $carnet, $old, $flash
 */
use App\Shop\BookShop;
use App\Shop\Orders;
use App\Shop\ShopPages;

$v = fn (string $k, string $d = '') => (string) ($old[$k] ?? $d);
?>
<?= \App\Core\View::partial('boutique/_bar', ['config' => $config, 'count' => $count]) ?>
<section class="section--tight">
  <div class="wrap shopprod shopbook" data-livre-shop data-upload="<?= e(ShopPages::u('/boutique/livre/fichier/')) ?>" data-3d="<?= e(asset('js/shop3d.js')) ?>"
       data-font="<?= e(asset('fonts/big-shoulders-display-normal-latin.woff2')) ?>" data-logo="<?= e(asset('img/logo-sochaux-retro-400.png')) ?>" data-jerseys="<?= e(json_encode($jerseys)) ?>"
       data-matches="<?= e(ShopPages::u('/boutique/poster/matchs/')) ?>" data-players="<?= e(ShopPages::u('/boutique/livre/joueurs/')) ?>" data-covers="<?= e(ShopPages::u('/boutique/livre/couvertures/')) ?>">
    <div class="shopprod__view">
      <nav class="crumbs vcrumbs"><a href="<?= e(ShopPages::u('/boutique/')) ?>">Boutique</a><span aria-hidden="true">›</span><span>Le livre</span></nav>
      <div class="shopprod__stage shopbook__stage">
        <div class="shopbook__cover" data-book-cover aria-live="polite"><?= BookShop::coverSvg(['nom' => $v('nom', 'Votre nom'), 'couverture' => $v('couverture')]) ?></div>
      </div>
      <div class="shopbook__jersey" data-book-jersey hidden>
        <p class="eyebrow">Ton maillot, devant et dos</p>
        <div class="shopbook__jersey-imgs"><div data-jersey-front></div><div data-jersey-back></div></div>
        <p class="shophelp">Maillot inspiré des maillots du FCSM de l’époque : une évocation libre, pas une reproduction fidèle.</p>
      </div>
      <ul class="shopbook__inside">
        <li><b>Couverture et 4<sup>e</sup></b> à votre nom, avec la photo d’archive de votre choix</li>
        <li><b>Dédicace</b> signée, « supporter depuis », numéro d’exemplaire</li>
        <li><b>Les 100 grands récits</b> du FCSM, de 1928 à aujourd’hui, illustrés par les archives du musée</li>
        <li><b>Vos pages</b> : votre maillot floqué, le jour de votre naissance, votre match, vos joueurs, votre photo, vos matchs au stade</li>
        <li><b>Un QR code par récit</b> vers sa page au musée (photos, vidéos, récit lu)</li>
      </ul>
      <p class="shopdisclaim">Aperçus indicatifs. Les récits sont ceux du musée, relus par ses historiens ; seules les pages personnelles changent d’un exemplaire à l’autre.</p>
    </div>

    <form class="shopprod__form vform" method="post" action="<?= e(ShopPages::u('/boutique/panier/')) ?>" data-book-form>
      <?= csrf_field() ?>
      <input type="hidden" name="do" value="add"><input type="hidden" name="model" value="<?= e(BookShop::MODEL) ?>">
      <span class="eyebrow">Le livre du musée</span>
      <h1 class="h-1 shopprod__t">100 récits du Lion</h1>
      <p><?= nl2br(e($book['desc'])) ?></p>
      <?= \App\Core\View::partial('vitrine/partials/flash', ['flash' => $flash]) ?>

      <fieldset class="shopopt"><legend><span class="shopbook__n">1</span> Format</legend>
        <?php if ($book['paper'] && $book['price'] > 0): ?>
        <label class="shopchoice"><input type="radio" name="livre[format]" value="papier"<?= $v('format', 'papier') === 'papier' ? ' checked' : '' ?> data-book-in> <span><b>Livre imprimé</b> · <?= e(Orders::money($book['price'])) ?><small>21 × 27 cm, environ 140 pages, livré chez vous</small></span></label>
        <?php endif; ?>
        <?php if ($book['price_pdf'] > 0): ?>
        <label class="shopchoice"><input type="radio" name="livre[format]" value="numerique"<?= $v('format') === 'numerique' || !$book['paper'] ? ' checked' : '' ?> data-book-in> <span><b>Livre numérique (PDF)</b> · <?= e(Orders::money($book['price_pdf'])) ?><small>À télécharger dès le paiement, par un lien personnel et sécurisé (5 téléchargements ; au-delà, contactez le musée)</small></span></label>
        <?php endif; ?>
      </fieldset>

      <fieldset class="shopopt"><legend><span class="shopbook__n">2</span> Couverture et dédicace</legend>
      <div class="shopbook__row">
        <div class="field"><label for="b-nom">Nom imprimé sur la couverture *</label><input id="b-nom" type="text" name="livre[nom]" maxlength="60" required value="<?= e($v('nom')) ?>" placeholder="Lucas Bertrand" data-book-in></div>
        <div class="field"><label for="b-dep">Supporter depuis (année)</label><input id="b-dep" type="number" name="livre[depuis]" min="1928" max="<?= (int) date('Y') ?>" value="<?= e($v('depuis')) ?>" placeholder="1998"></div>
      </div>
      <div class="field"><label for="b-ded">Dédicace</label><textarea id="b-ded" name="livre[dedicace]" rows="3" maxlength="600" placeholder="À mon fils, qui a découvert Bonal sur mes épaules…"><?= e($v('dedicace')) ?></textarea></div>
      <div class="field"><label for="b-sig">Signée</label><input id="b-sig" type="text" name="livre[signature]" maxlength="80" value="<?= e($v('signature')) ?>" placeholder="Papa, Noël <?= (int) date('Y') ?>"></div>

      </fieldset>

      <fieldset class="shopopt"><legend><span class="shopbook__n">3</span> Photo de couverture <small>dans les archives du musée</small></legend>
        <div class="field shopbook__coverq"><label for="b-cq">Un joueur, un match, une saison, un lieu…</label>
          <div class="shopbook__qrow"><input id="b-cq" type="search" placeholder="Paille, Bonal, 1938, finale 2007…" autocomplete="off" data-cover-q><button type="button" class="btn btn--navy" data-cover-go>Chercher</button></div>
          <small class="shophelp" data-cover-note>Le musée vous propose jusqu’à 6 photos de ses archives. Celles qui ne sont pas assez nettes pour la pleine page sont imprimées en bleu nuit, façon archive.</small>
        </div>
        <div class="shopbook__covers" data-cover-list>
          <label><input type="radio" name="livre[couverture]" value=""<?= $v('couverture') === '' ? ' checked' : '' ?> data-book-in><span class="shopbook__nophoto">Sans photo</span></label>
          <?php foreach ($covers as $c): ?>
          <label title="<?= e($c['caption']) ?>"><input type="radio" name="livre[couverture]" value="<?= e($c['rel']) ?>"<?= $v('couverture') === $c['rel'] ? ' checked' : '' ?> data-book-in data-img="<?= e(url('/media/480/' . $c['rel'] . '.webp')) ?>"><img src="<?= e(url('/media/320/' . $c['rel'] . '.webp')) ?>" alt="<?= e($c['caption']) ?>" loading="lazy"></label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <?php if ($jerseys): ?>
      <fieldset class="shopopt"><legend><span class="shopbook__n">4</span> Ton maillot floqué <small>double page, facultatif</small></legend>
        <div class="shopbook__jerseys" role="radiogroup" aria-label="Maillot inspiré de">
          <label class="shopbook__jy"><input type="radio" name="livre[maillot_style]" value=""<?= $v('maillot_style') === '' ? ' checked' : '' ?> data-book-jersey-in><span class="shopbook__jy-img"><span class="shopbook__jy-none">Pas de maillot</span></span><span class="shopbook__jy-t">&nbsp;</span></label>
          <?php foreach ($jerseys as $k => $j): [$era, $what] = array_pad(explode(' · ', $j['label'], 2), 2, ''); ?>
          <label class="shopbook__jy" title="<?= e($j['label']) ?>"><input type="radio" name="livre[maillot_style]" value="<?= e($k) ?>"<?= $v('maillot_style') === $k ? ' checked' : '' ?> data-book-jersey-in><span class="shopbook__jy-img"><?= BookShop::jerseySvg($j, 's' . $k) ?></span><span class="shopbook__jy-t"><b><?= e($era) ?></b><?= $what !== '' ? '<small>' . e($what) . '</small>' : '' ?></span></label>
          <?php endforeach; ?>
        </div>
        <div class="shopbook__row">
          <div class="field"><label for="b-mn">Nom floqué</label><input id="b-mn" type="text" name="livre[maillot_nom]" maxlength="14" value="<?= e($v('maillot_nom')) ?>" placeholder="LUCAS" data-book-jersey-in></div>
          <div class="field"><label for="b-mu">Numéro</label><input id="b-mu" type="text" name="livre[maillot_numero]" maxlength="2" inputmode="numeric" value="<?= e($v('maillot_numero')) ?>" placeholder="10" data-book-jersey-in></div>
        </div>
        <input type="hidden" name="livre[maillot_image]" data-book-token="maillot_image"><input type="hidden" name="livre[maillot_devant]" data-book-token="maillot_devant">
      </fieldset>
      <?php endif; ?>

      <fieldset class="shopopt"><legend><span class="shopbook__n">5</span> Vos pages <small>facultatif</small></legend>
        <div class="shopbook__row">
          <div class="field"><label for="b-na">Le jour de ta naissance (date)</label><input id="b-na" type="date" name="livre[naissance]" value="<?= e($v('naissance')) ?>"></div>
          <div class="field"><label for="b-nt">Titre de la page</label><input id="b-nt" type="text" name="livre[naissance_titre]" maxlength="50" value="<?= e($v('naissance_titre')) ?>" placeholder="Le jour de ta naissance"></div>
        </div>
        <div class="field shopbook__search"><label for="b-mq">Mon match</label><input id="b-mq" type="search" placeholder="Tapez une équipe, une année… (Metz 1988)" data-book-search="match" autocomplete="off"><input type="hidden" name="livre[match]" value="<?= e($v('match')) ?>"><ul data-book-results="match"></ul><p class="shophelp" data-book-picked="match"></p></div>
        <div class="field shopbook__search"><label for="b-jq">Mes joueurs (jusqu’à 3)</label><input id="b-jq" type="search" placeholder="Tapez un nom (Paille, Bazdarevic…)" data-book-search="joueurs" autocomplete="off"><input type="hidden" name="livre[joueurs]" value="<?= e($v('joueurs')) ?>"><ul data-book-results="joueurs"></ul><p class="shophelp" data-book-picked="joueurs"></p></div>
        <div class="field"><label for="b-ph">Ta photo (au stade, en tribune…)</label><input id="b-ph" type="file" accept="image/jpeg,image/png" data-book-file="photo"><input type="hidden" name="livre[photo]" data-book-token="photo"><small class="shophelp" data-book-file-note="photo">JPEG ou PNG, 670 pixels de large au moins pour une impression nette.</small></div>
        <div class="field"><label for="b-pl">Légende de ta photo</label><input id="b-pl" type="text" name="livre[photo_legende]" maxlength="120" value="<?= e($v('photo_legende')) ?>" placeholder="Avec papa à Bonal, août 2024"></div>
        <?php if ($carnet): ?>
        <label class="shopchoice"><input type="checkbox" name="livre[carnet]" value="<?= e($carnet['id']) ?>"<?= $v('carnet') !== '' ? ' checked' : '' ?>> <span><b>Mes matchs au stade</b><small>D’après votre carnet du supporter (<?= count($carnet['matches']) ?> matchs) : tampon « J’y étais » sur les récits de vos matchs et page de bilan.</small></span></label>
        <?php else: ?>
        <p class="shophelp">Vous tenez un <a href="<?= e(url('/carnet/')) ?>">carnet du supporter</a> ? Ouvrez-le sur cet appareil : les récits de vos matchs porteront le tampon « J’y étais ».</p>
        <?php endif; ?>
      </fieldset>

      <div class="shopbook__actions">
        <button type="submit" class="btn btn--ghost" formaction="<?= e(ShopPages::u('/boutique/livre/extrait/')) ?>" formtarget="_blank" formnovalidate data-book-excerpt>📖 Feuilleter un extrait</button>
        <button type="submit" class="btn btn--yellow btn--lg" data-book-add>Ajouter au panier</button>
      </div>
      <p class="shophelp" data-book-status aria-live="polite">L’extrait montre votre couverture, votre dédicace, vos pages, le sommaire de l’extrait, trois vrais récits de trois époques et la 4<sup>e</sup> de couverture.</p>
    </form>
  </div>
</section>
