<?php
/**
 * Accueil du site de l'association.
 * Variables : $p (textes), $figures, $actions, $news, $events, $partners, $teaser, $days, $centenary
 */
use App\Vitrine\Content;
use App\Vitrine\Host;

$museum = Host::museum('/');
?>
<section class="vhero">
  <?php if (Content::hasImage($p['hero_image'] ?? null)): ?>
  <div class="vhero__bg" aria-hidden="true"><img src="<?= e(img($p['hero_image'], 1600)) ?>" srcset="<?= e(srcset($p['hero_image'], [800, 1200, 1600])) ?>" sizes="100vw" alt="" fetchpriority="high"></div>
  <?php endif; ?>
  <div class="wrap vhero__in<?= $teaser ? '' : ' vhero__in--solo' ?>">
    <div class="vhero__text">
      <span class="eyebrow eyebrow--yellow eyebrow--lg"><?= e($p['hero_eyebrow'] ?? '') ?></span>
      <h1 class="vhero__title"><?= e($p['hero_title'] ?? '') ?></h1>
      <p class="vhero__lead"><?= e($p['hero_lead'] ?? '') ?></p>
      <div class="row gap-14">
        <a class="btn btn--yellow" href="<?= e($museum) ?>" target="_blank" rel="noopener">Entrer dans le musée ↗</a>
        <a class="btn btn--ghost-light" href="<?= e(Host::url('/nous-soutenir/adherer/')) ?>">Adhérer à l’association</a>
      </div>
    </div>
    <?php if ($teaser): ?>
    <figure class="vhero__video">
      <video controls preload="none" playsinline poster="<?= e(Host::url('/video/teaser.jpg')) ?>" aria-label="Teaser du musée en ligne (1 min 55)">
        <source src="<?= e(Host::url('/video/teaser.mp4')) ?>" type="video/mp4">
      </video>
      <figcaption>Le musée en ligne en 1 min 55</figcaption>
    </figure>
    <?php endif; ?>
  </div>
</section>

<section class="vfigures" aria-label="Sochaux Rétro en chiffres">
  <div class="wrap vfigures__in">
    <?php foreach ($figures as $f): ?>
      <div class="vfigure" data-reveal>
        <b class="num" data-count><?= e($f['n']) ?></b>
        <span class="vfigure__l"><?= e($f['label']) ?></span>
        <span class="vfigure__t"><?= e($f['text']) ?></span>
      </div>
    <?php endforeach; ?>
    <div class="vfigure vfigure--cent" data-reveal>
      <b class="num">J-<span data-count><?= (int) $days ?></span></b>
      <span class="vfigure__l">avant les 100 ans</span>
      <span class="vfigure__t">du FC Sochaux-Montbéliard</span>
    </div>
  </div>
</section>

<section class="section vintro">
  <div class="wrap grid-fit" style="--min:380px;--align:start">
    <div data-reveal>
      <span class="eyebrow">Qui sommes-nous</span>
      <h2 class="h-section mt-20"><?= e($p['intro_title'] ?? '') ?></h2>
    </div>
    <div class="stack gap-20" data-reveal>
      <div class="prose vlead-prose"><?= \App\Vitrine\Pages::rich($p['intro_text'] ?? '') ?></div>
      <a class="link-under" href="<?= e(Host::url('/association/')) ?>">Découvrir l’association</a>
    </div>
  </div>
</section>

<section class="section bg-sand vactions-sec">
  <div class="wrap">
    <div class="vsec-head">
      <div><span class="eyebrow">Nos actions</span><h2 class="h-section mt-20">Ce que nous faisons</h2></div>
      <a class="link-under" href="<?= e(Host::url('/nos-actions/')) ?>">Toutes nos actions</a>
    </div>
    <div class="vactions">
      <?php foreach ($actions as $a): ?>
        <?= \App\Core\View::partial('vitrine/partials/action-card', ['a' => $a]) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="vmuseum">
  <div class="wrap vmuseum__in">
    <div class="vmuseum__text" data-reveal>
      <span class="eyebrow eyebrow--yellow">Notre grand projet</span>
      <h2 class="h-section"><?= e($p['museum_title'] ?? '') ?></h2>
      <p class="vmuseum__lead"><?= e($p['museum_text'] ?? '') ?></p>
      <ul class="vticks">
        <li>Des milliers de fiches de matchs et de joueurs, depuis 1928</li>
        <li>Rétro-Direct, quiz, album, Fil jaune, carte, frise</li>
        <li>Gratuit, ouvert à tous, participatif</li>
      </ul>
      <a class="btn btn--yellow" href="<?= e($museum) ?>" target="_blank" rel="noopener">Entrer dans le musée ↗</a>
    </div>
    <a class="vmuseum__shot" href="<?= e($museum) ?>" target="_blank" rel="noopener" aria-label="Le musée en ligne" data-reveal>
      <span class="vbrowser"><span class="vbrowser__bar"><i></i><i></i><i></i><span><?= e(parse_url($museum, PHP_URL_HOST) ?: 'musee') ?></span></span>
        <img src="/assets/img/vitrine/musee-accueil.webp" alt="Page d’accueil du musée en ligne Sochaux Rétro" width="1200" height="750" loading="lazy">
      </span>
    </a>
  </div>
</section>

<section class="section vhome-news">
  <div class="wrap vhome-news__in">
    <div class="vhome-news__col">
      <div class="vsec-head"><div><span class="eyebrow">Actualités</span><h2 class="h-2 mt-20">Les dernières nouvelles</h2></div><a class="link-under" href="<?= e(Host::url('/actualites/')) ?>">Toutes les actualités</a></div>
      <?php if ($news): ?>
        <div class="vnews-grid">
          <?php foreach ($news as $i => $n): ?><?= \App\Core\View::partial('vitrine/partials/news-card', ['n' => $n, 'big' => $i === 0]) ?><?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="muted">Les premières actualités arrivent très bientôt.</p>
      <?php endif; ?>
    </div>
    <aside class="vhome-news__agenda">
      <div class="vsec-head"><div><span class="eyebrow">Agenda</span><h2 class="h-2 mt-20">À venir</h2></div></div>
      <?php if ($events): ?>
        <div class="vevents"><?php foreach ($events as $e): ?><?= \App\Core\View::partial('vitrine/partials/event-row', ['e' => $e]) ?><?php endforeach; ?></div>
      <?php else: ?>
        <p class="muted">Aucun rendez-vous programmé pour le moment.</p>
      <?php endif; ?>
      <a class="link-under mt-20" href="<?= e(Host::url('/agenda/')) ?>">Tout l’agenda</a>
      <div class="vagenda-sub">
        <p>Les rendez-vous de l’association et les Rétro-Direct du musée, directement dans votre agenda.</p>
        <a class="btn btn--ghost btn--sm" href="<?= e(Host::url('/agenda/agenda.ics')) ?>">S’abonner à l’agenda</a>
      </div>
    </aside>
  </div>
</section>

<section class="vcent">
  <div class="wrap vcent__in">
    <div class="vcent__text" data-reveal>
      <span class="eyebrow">Centenaire · <?= e(date_fr($centenary)) ?></span>
      <h2 class="h-section"><?= e($p['centenary_title'] ?? '') ?></h2>
      <p class="vcent__lead"><?= e($p['centenary_text'] ?? '') ?></p>
      <div class="row gap-14">
        <a class="btn btn--navy" href="<?= e(Host::url('/nos-actions/centenaire-2028/')) ?>">Le centenaire avec nous</a>
        <a class="btn btn--ghost" href="<?= e(Host::museum('/centenaire/')) ?>" target="_blank" rel="noopener">Voter pour le Onze de légende ↗</a>
      </div>
    </div>
    <div class="vcent__cd" data-reveal><?= countdown_html($centenary) ?></div>
  </div>
</section>

<section class="section vsupport-sec">
  <div class="wrap">
    <div class="vsec-head"><div><span class="eyebrow">Nous soutenir</span><h2 class="h-section mt-20"><?= e($p['support_title'] ?? '') ?></h2></div></div>
    <p class="lead" style="max-width:60ch;margin-bottom:36px"><?= e($p['support_text'] ?? '') ?></p>
    <?= \App\Core\View::partial('vitrine/partials/support-cards') ?>
  </div>
</section>

<?php if ($partners): ?>
<section class="section--tight bg-cream bt vpartners-sec">
  <div class="wrap">
    <h2 class="eyebrow center" style="display:block;margin-bottom:24px">Ils nous soutiennent</h2>
    <?= \App\Core\View::partial('vitrine/partials/partners', ['partners' => $partners]) ?>
  </div>
</section>
<?php endif; ?>

<section class="vnewsletter">
  <div class="wrap vnewsletter__in">
    <div>
      <h2 class="h-2"><?= e($p['newsletter_title'] ?? '') ?></h2>
      <p class="vnewsletter__t"><?= e($p['newsletter_text'] ?? '') ?></p>
    </div>
    <?= \App\Core\View::partial('vitrine/partials/newsletter-form', ['id' => 'nl-home']) ?>
  </div>
</section>
