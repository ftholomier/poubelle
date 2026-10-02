<?php
use App\Core\Str;
use App\Core\Url;
use App\Core\View;
use App\Services\Ads;
use App\Services\AntiSpam;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Leads;
use App\Services\Pros;
use App\Services\Settings;

/** @var array $pro @var ?array $cat @var ?array $commune @var ?array $dep @var array $reviews @var array $similar @var array $crumbs @var string $cityName */
$name = Pros::displayName($pro);
$color = $cat['color'] ?? '#ffd23f';
$photos = array_values((array) ($pro['photos'] ?? []));
$main = $photos[0] ?? null;
$rating = (float) ($pro['rating']['avg'] ?? 0);
$nbReviews = (int) ($pro['rating']['count'] ?? 0);
$zones = array_values(array_filter(array_map(static fn ($z) => Geo::dep((string) $z), (array) ($pro['zones'] ?? []))));
$rel = (string) Settings::get('links.pro_website_rel', 'noopener');
$videos = [];
foreach ((array) ($pro['videos'] ?? []) as $v) {
    if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_\-]{6,15})~', (string) $v, $m)) {
        $videos[] = ['src' => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1&rel=0', 'label' => 'Vidéo YouTube'];
    } elseif (preg_match('~vimeo\.com/(?:video/)?(\d{5,12})~', (string) $v, $m)) {
        $videos[] = ['src' => 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1&dnt=1', 'label' => 'Vidéo Vimeo'];
    }
}
$socials = array_filter((array) ($pro['socials'] ?? []));
$mapData = isset($pro['lat']) && $pro['lat'] !== null ? ['lat' => (float) $pro['lat'], 'lng' => (float) $pro['lng'], 'color' => $color, 'letter' => mb_strtoupper(mb_substr($name, 0, 1)), 'zoom' => ($pro['geo_precision'] ?? 'commune') === 'commune' ? 9 : 8, 'radius' => 30] : null;
$devisUrl = Url::devis(array_filter(['cat' => $cat['slug'] ?? null, 'insee' => $pro['insee'] ?? null, 'dep' => $pro['dep'] ?? null]));
?>
<div class="pro-hero" style="--c:<?= e($color) ?>">
  <?= View::partial('front/partials/crumbs', ['crumbs' => $crumbs]) ?>
  <div class="pro-top">
    <div class="gallery" data-gallery>
      <div class="gallery-main">
        <?php if ($main): ?>
          <img src="<?= e(Pros::photo($main, 'lg')) ?>" alt="<?= e($main['alt'] ?? $name) ?>" width="<?= (int) ($main['w'] ?? 1200) ?>" height="<?= (int) ($main['h'] ?? 800) ?>" data-gallery-main fetchpriority="high">
        <?php else: ?>
          <span class="initials" aria-hidden="true"><?= e(Str::initials($name)) ?></span>
        <?php endif; ?>
        <button type="button" class="fav" data-fav="<?= (int) $pro['id'] ?>" aria-label="Ajouter <?= e($name) ?> aux favoris" aria-pressed="false">♥</button>
        <?php if (!empty($pro['stats']['hot'])): ?><span class="hot">🔥 Très demandé</span><?php endif; ?>
      </div>
      <?php if (count($photos) > 1): ?>
        <div class="gallery-thumbs" role="list">
          <?php foreach ($photos as $i => $ph): ?>
            <button type="button" role="listitem" class="<?= $i === 0 ? 'is-on' : '' ?>" data-gallery-thumb data-src="<?= e(Pros::photo($ph, 'lg')) ?>" data-full="<?= e(Pros::photo($ph, 'lg')) ?>" data-alt="<?= e($ph['alt'] ?? $name) ?>" aria-label="Photo <?= $i + 1 ?>">
              <img src="<?= e(Pros::photo($ph, 'sm')) ?>" alt="" loading="lazy" width="92" height="70">
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="pro-id">
      <span class="tag" style="background:<?= e($color) ?>"><?= e(($cat['emoji'] ?? '') . ' ' . ($cat['name'] ?? 'Animation')) ?></span>
      <h1><?= e($name) ?></h1>
      <?php if (!empty($pro['tagline'])): ?><p class="tagline"><?= e($pro['tagline']) ?></p><?php endif; ?>
      <div class="pro-facts">
        <?php if ($cityName !== ''): ?><span>📍 <?= e($cityName) ?><?= $dep ? ' (' . e($dep['code']) . ')' : '' ?></span><?php endif; ?>
        <?php if ($nbReviews > 0): ?><a href="#avis" class="rating">★ <?= e(number_format($rating, 1, ',', '')) ?> · <?= $nbReviews ?> avis</a><?php else: ?><span class="tag" style="background:var(--lime)">Nouveau sur l'annuaire</span><?php endif; ?>
        <?php if (!empty($pro['all_france'])): ?><span>🇫🇷 Se déplace dans toute la France</span><?php elseif ($zones): ?><span>🚐 Intervient dans <?= count($zones) ?> département<?= count($zones) > 1 ? 's' : '' ?></span><?php endif; ?>
      </div>
      <?php if (!empty($pro['categories']) && count($pro['categories']) > 1): ?>
        <div class="row-wrap mt-2">
          <?php foreach (array_slice($pro['categories'], 1) as $cs): $cc = Categories::get($cs); if (!$cc) { continue; } ?>
            <a class="pill-ghost" href="<?= e(Url::category($cs)) ?>"><?= e($cc['emoji'] . ' ' . $cc['name']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="pro-cta">
        <?php if (!empty($pro['settings']['vacation'])): ?><div class="alert alert-info small"><?= icon('calendar', 16) ?><span><strong>En congés</strong><?= !empty($pro['settings']['vacation_until']) ? ' jusqu\'au ' . e(date_fr((string) $pro['settings']['vacation_until'], 'long')) : '' ?> : votre message lui sera transmis, la réponse peut prendre un peu plus de temps.</span></div><?php endif; ?>
        <div class="price-big"><?php if (!empty($pro['price_from'])): ?>dès <strong><?= nf((int) $pro['price_from']) ?> €</strong><?= !empty($pro['price_note']) ? ' · ' . e($pro['price_note']) : '' ?><?php else: ?><strong style="font-size:24px">Tarif sur devis</strong><?php endif; ?></div>
        <div class="row">
          <a class="btn btn-coral" href="#contact">Demander un devis</a>
          <?php if (!empty($pro['phone']) && Settings::get('features.phone_reveal', true)): ?>
            <button type="button" class="btn" data-reveal-phone="<?= (int) $pro['id'] ?>">📞 Afficher le numéro</button>
          <?php endif; ?>
        </div>
        <div class="row-wrap small">
          <?php if (!empty($pro['website'])): ?><a href="<?= e($pro['website']) ?>" target="_blank" rel="<?= e($rel) ?>" data-track="site" data-pro="<?= (int) $pro['id'] ?>"><?= icon('globe', 15) ?> <?= e(Str::domain((string) $pro['website'])) ?></a><?php endif; ?>
          <?php foreach ($socials as $net => $url): ?><a href="<?= e($url) ?>" target="_blank" rel="nofollow noopener" aria-label="<?= e(ucfirst($net)) ?>" data-track="site" data-pro="<?= (int) $pro['id'] ?>"><?= icon($net, 17) ?></a><?php endforeach; ?>
          <button type="button" class="link" data-share="<?= e(Url::abs(Url::pro($pro))) ?>" style="color:var(--ink)"><?= icon('share', 15) ?> Partager</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="pro-body">
  <div>
    <h2 class="section-title">Présentation</h2>
    <div class="prose"><?= $pro['description'] ?: '<p>' . e($name) . ' n\'a pas encore rédigé sa présentation. Contactez-le pour en savoir plus sur ses prestations.</p>' ?></div>

    <?php if (!empty($pro['tags'])): ?>
      <h2 class="section-title">En quelques mots</h2>
      <div class="row-wrap"><?php foreach ($pro['tags'] as $t): ?><a class="pill-ghost" href="<?= e(Url::search(['q' => $t])) ?>"><?= e($t) ?></a><?php endforeach; ?></div>
    <?php endif; ?>

    <?php if ($videos): ?>
      <h2 class="section-title">Vidéos</h2>
      <div class="videos">
        <?php foreach ($videos as $v): ?><div class="video"><button type="button" data-video="<?= e($v['src']) ?>" aria-label="<?= e($v['label']) ?>"><span>▶ Lire la <?= e(mb_strtolower($v['label'])) ?></span></button></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h2 class="section-title">Zone d'intervention</h2>
    <?php if (!empty($pro['all_france'])): ?><p>Ce professionnel se déplace <strong>partout en France</strong>.</p><?php endif; ?>
    <?php if ($zones): ?>
      <div class="zones">
        <?php foreach ($zones as $z): ?><a class="pill-ghost" href="<?= e(Url::dep($cat['slug'] ?? null, $z['code'])) ?>"><?= e($z['name'] . ' (' . $z['code'] . ')') ?></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($mapData): ?><div class="map-mini mt-3" data-mini-map="<?= ej($mapData) ?>" role="region" aria-label="Carte de la zone de <?= e($name) ?>"></div><?php endif; ?>

    <h2 class="section-title" id="avis">Avis clients</h2>
    <?php if ($nbReviews > 0): ?>
      <div class="rating-summary mb-2"><span class="big"><?= e(number_format($rating, 1, ',', '')) ?></span><span><span class="c-coral" style="font-size:22px;letter-spacing:2px"><?= str_repeat('★', (int) round($rating)) . str_repeat('☆', 5 - (int) round($rating)) ?></span><br><span class="muted"><?= $nbReviews ?> avis vérifié<?= $nbReviews > 1 ? 's' : '' ?></span></span></div>
      <div class="reviews">
        <?php foreach ($reviews as $r): ?>
          <article class="review">
            <div class="row-wrap"><span class="stars"><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></span><?php if (!empty($r['verified_client'])): ?><span class="verified">✓ Client vérifié</span><?php endif; ?></div>
            <?php if (!empty($r['title'])): ?><h3 style="font-size:18px;margin-top:8px"><?= e($r['title']) ?></h3><?php endif; ?>
            <p style="margin:8px 0 0"><?= nl2br(e($r['body'])) ?></p>
            <div class="who"><?= e($r['author_name']) ?> · <?= e(date_fr((string) $r['created_at'], 'month')) ?><?= !empty($r['event_type']) ? ' · ' . e(Leads::EVENT_TYPES[$r['event_type']] ?? '') : '' ?></div>
            <?php if (!empty($r['reply']['body'])): ?><div class="reply"><strong>Réponse de <?= e($name) ?> :</strong> <?= nl2br(e($r['reply']['body'])) ?></div><?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="muted">Aucun avis publié pour le moment. Vous avez fait appel à <?= e($name) ?> ? Soyez le premier à partager votre expérience.</p>
    <?php endif; ?>
    <?php if (Settings::get('features.reviews', true)): ?>
      <details class="box mt-3" id="avis-form">
        <summary style="cursor:pointer;font-weight:800;font-size:18px">✍️ Laisser un avis</summary>
        <form class="form mt-2" method="post" action="/pro/<?= (int) $pro['id'] ?>/avis" data-ajax data-protect="review" novalidate>
          <?= AntiSpam::fields('review') ?>
          <fieldset class="field">
            <legend class="label">Votre note <span class="req">*</span></legend>
            <div class="star-input">
              <?php for ($s = 5; $s >= 1; $s--): ?><input type="radio" id="star<?= $s ?>" name="rating" value="<?= $s ?>"<?= $s === 5 ? ' required' : '' ?>><label for="star<?= $s ?>" title="<?= $s ?> étoile<?= $s > 1 ? 's' : '' ?>">★</label><?php endfor; ?>
            </div>
          </fieldset>
          <div class="form-grid">
            <div class="field"><label for="rv-name">Prénom (affiché) <span class="req">*</span></label><input id="rv-name" type="text" name="name" maxlength="60" required autocomplete="given-name"></div>
            <div class="field"><label for="rv-email">Email (non publié) <span class="req">*</span></label><input id="rv-email" type="email" name="email" maxlength="120" required autocomplete="email"></div>
            <div class="field"><label for="rv-type">Événement</label><select id="rv-type" name="event_type"><option value="">—</option><?php foreach (Leads::EVENT_TYPES as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="rv-date">Mois de l'événement</label><input id="rv-date" type="month" name="event_date" max="<?= date('Y-m') ?>"></div>
          </div>
          <div class="field"><label for="rv-title">Titre</label><input id="rv-title" type="text" name="title" maxlength="90" placeholder="Une soirée inoubliable !"></div>
          <div class="field"><label for="rv-body">Votre avis <span class="req">*</span></label><textarea id="rv-body" name="body" maxlength="3000" required data-charcount="3000" placeholder="Ambiance, ponctualité, écoute, rapport qualité-prix…"></textarea></div>
          <label class="check field"><input type="checkbox" name="consent" value="1" required> <span>Je certifie avoir fait appel à ce professionnel et que mon avis est sincère.</span></label>
          <div class="row-wrap"><button type="submit" class="btn btn-ink">Publier mon avis</button><span class="pow-status"></span></div>
          <div data-form-result></div>
        </form>
      </details>
    <?php endif; ?>
  </div>

  <aside>
    <div class="box" id="contact">
      <h2>Contacter <?= e($name) ?></h2>
      <form class="form" method="post" action="/pro/<?= (int) $pro['id'] ?>/contact" data-ajax data-protect="contact" novalidate>
        <?= AntiSpam::fields('contact') ?>
        <div class="field"><label for="ct-name">Nom <span class="req">*</span></label><input id="ct-name" type="text" name="name" value="<?= e(old('name')) ?>" maxlength="80" required autocomplete="name"><?= field_error('name') ?></div>
        <div class="field"><label for="ct-email">Email <span class="req">*</span></label><input id="ct-email" type="email" name="email" value="<?= e(old('email')) ?>" maxlength="120" required autocomplete="email"><?= field_error('email') ?></div>
        <div class="field"><label for="ct-phone">Téléphone</label><input id="ct-phone" type="tel" name="phone" value="<?= e(old('phone')) ?>" maxlength="30" autocomplete="tel"></div>
        <div class="form-grid">
          <div class="field"><label for="ct-type">Événement</label><select id="ct-type" name="event_type"><option value="">—</option><?php foreach (Leads::EVENT_TYPES as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label for="ct-date">Date</label><input id="ct-date" type="date" name="date" min="<?= date('Y-m-d') ?>"></div>
        </div>
        <div class="form-grid">
          <div class="field"><label for="ct-place">Lieu</label><input id="ct-place" type="text" name="place" maxlength="120" placeholder="Ville, salle…"></div>
          <div class="field"><label for="ct-guests">Invités</label><input id="ct-guests" type="text" name="guests" maxlength="30" placeholder="80"></div>
        </div>
        <div class="field"><label for="ct-msg">Votre message <span class="req">*</span></label><textarea id="ct-msg" name="message" maxlength="4000" required placeholder="Bonjour, je recherche…"><?= e(old('message')) ?></textarea><?= field_error('message') ?></div>
        <label class="check field"><input type="checkbox" name="consent" value="1" required> <span class="small">J'accepte que mes coordonnées soient transmises à ce professionnel pour qu'il me réponde (<a href="/confidentialite/">confidentialité</a>).</span></label>
        <button type="submit" class="btn btn-coral btn-block">Envoyer ma demande</button>
        <span class="pow-status"></span>
        <div data-form-result></div>
      </form>
    </div>
    <?= Ads::slot('pro_side', 'ad-side') ?>
    <div class="box box-cream small">
      <strong>Plusieurs devis en un clic</strong>
      <p class="mt-1" style="margin-bottom:10px">Envoyez votre demande à tous les pros <?= $dep ? e($dep['of']) : 'de votre secteur' ?>.</p>
      <a class="btn btn-sm btn-ink" href="<?= e($devisUrl) ?>">Demande groupée →</a>
    </div>
  </aside>
</div>

<?= Ads::slot('pro_bottom') ?>

<?php if ($similar): ?>
<section class="section section-tight">
  <h2 class="h2">D'autres pros <span class="serif"><?= $cityName !== '' ? e(Geo::inCity($cityName)) : 'à découvrir' ?></span></h2>
  <div class="pro-grid"><?php foreach ($similar as $p): ?><?= View::partial('front/partials/pro-card', ['p' => $p]) ?><?php endforeach; ?></div>
</section>
<?php endif; ?>
