<?php
use App\Core\Request;
use App\Core\Url;
use App\Services\Categories;
use App\Services\Settings;
use App\Services\Stats;

$path = Request::path();
$nums = Stats::publicNumbers();
$byCat = $nums['by_cat'];
arsort($byCat);
$topCats = array_slice(array_values(array_filter(array_keys($byCat), static fn ($s) => Categories::get((string) $s) !== null)), 0, 6);
foreach (['magicien', 'animation-enfants'] as $s) {
    if (!in_array($s, $topCats, true) && Categories::get($s)) {
        $topCats[] = $s;
    }
}
$founded = (int) Settings::get('site.founded', 2001);
$socials = array_filter((array) Settings::get('site.socials', []), static fn ($u) => is_string($u) && $u !== '');
// appel à l'action masqué là où il ferait doublon (formulaire de demande, inscription, espace pro)
$cta = !preg_match('#^/(devis|inscription-pro|espace-pro|connexion)#', $path);
$stats = array_filter([
    [(int) $nums['pros'], 'pros référencés'],
    [(int) $nums['cities'], 'villes en France'],
    [(int) $nums['requests'], 'demandes envoyées'],
    [max(1, (int) date('Y') - $founded), 'ans de fêtes'],
], static fn ($s) => $s[0] > 0);
$giant = ['animateur' => '', 'pour' => 'em', 'votresoirée' => ''];
?>
<footer class="site-footer" data-footer>
  <div class="footer-fx" aria-hidden="true"><i class="f1">✦</i><i class="f2">●</i><i class="f3">✦</i><i class="f4">▲</i><i class="f5">✦</i><i class="f6">●</i></div>
  <div class="wrap footer-inner">
    <?php if ($cta): ?>
    <div class="footer-cta reveal">
      <div>
        <p class="footer-kicker">✦ Gratuit · sans engagement</p>
        <p class="footer-title">Le bon pro pour votre <span class="rot" aria-hidden="true"><span>mariage</span><span>anniversaire</span><span>soirée d'entreprise</span><span>baptême</span><span>réveillon</span></span><span class="sr-only">événement</span></p>
        <p class="footer-lead">Décrivez votre fête en 2 minutes : les pros de votre secteur vous envoient leurs devis.</p>
      </div>
      <div class="footer-cta-btns">
        <a class="btn btn-coral" href="/devis/">📣 Déposer ma demande</a>
        <a class="btn btn-line" href="/professionnels/">Je suis un pro →</a>
      </div>
      <div class="footer-badge" aria-hidden="true"><svg viewBox="0 0 120 120"><defs><path id="footer-badge-path" d="M60,60 m-43,0 a43,43 0 1,1 86,0 a43,43 0 1,1 -86,0"/></defs><text><textPath href="#footer-badge-path" textLength="268" lengthAdjust="spacing">DEVIS GRATUIT ✦ RÉPONSE RAPIDE ✦</textPath></text></svg><span>🎉</span></div>
    </div>
    <?php endif; ?>
    <div class="footer-grid">
      <div class="footer-brand reveal">
        <a href="/" class="logo footer-logo" aria-label="Animateur pour votre soirée — accueil"><span class="logo-mark" aria-hidden="true">A</span><span class="logo-word">animateur<em>pour</em>votresoirée</span></a>
        <p><?= e(Settings::get('site.baseline')) ?>, depuis <?= $founded ?>.</p>
        <?php if ($stats): ?><ul class="footer-stats">
          <?php foreach ($stats as [$n, $label]): ?><li><strong data-count="<?= $n ?>"><?= nf($n) ?></strong><span><?= e($label) ?></span></li><?php endforeach; ?>
        </ul><?php endif; ?>
        <?php if ($socials): ?><div class="footer-socials"><?php foreach ($socials as $net => $url): ?><a href="<?= e((string) $url) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst((string) $net)) ?>"><?= icon((string) $net, 18) ?></a><?php endforeach; ?></div><?php endif; ?>
        <button type="button" class="pill-ghost install-btn" data-install><?= icon('download', 14) ?> Installer l'appli</button>
      </div>
      <nav class="footer-col reveal" aria-labelledby="footer-pros">
        <h2 class="footer-h" id="footer-pros">Trouver un pro</h2>
        <ul>
          <?php foreach ($topCats as $slug): ?><li><a class="ul" href="<?= e(Url::category((string) $slug)) ?>"><?= e(Categories::name((string) $slug)) ?></a></li><?php endforeach; ?>
          <li><a class="ul" href="/recherche/">Tous les pros, sur la carte →</a></li>
        </ul>
      </nav>
      <nav class="footer-col reveal" aria-labelledby="footer-event">
        <h2 class="footer-h" id="footer-event">Votre événement</h2>
        <ul>
          <li><a class="ul" href="/devis/">Déposer une demande de devis</a></li>
          <?php foreach (Categories::occasions() as $slug => $o): ?><li><a class="ul" href="<?= e(Url::occasion((string) $slug)) ?>"><?= e((string) $o['title']) ?></a></li><?php endforeach; ?>
          <li><a class="ul" href="/#how">Comment ça marche ?</a></li>
          <li><a class="ul" href="/blog/">Idées et conseils (blog)</a></li>
        </ul>
      </nav>
      <nav class="footer-col reveal" aria-labelledby="footer-site">
        <h2 class="footer-h" id="footer-site">Le site</h2>
        <ul>
          <li><a class="ul" href="<?= e(Url::page('qui-sommes-nous')) ?>">Qui sommes-nous ?</a></li>
          <li><a class="ul" href="<?= e(Url::page('faq')) ?>">Questions fréquentes</a></li>
          <li><a class="ul" href="<?= e(Url::page('charte-qualite')) ?>">Charte qualité</a></li>
          <li><a class="ul" href="/contact/">Contact</a></li>
          <li><a class="ul" href="/professionnels/">Inscrire mon activité</a></li>
          <li><a class="ul" href="/connexion/">Espace pro</a></li>
          <?php foreach (App\Core\Cache::remember('footer_pages', 3600, static function (): array {
              $out = [];
              foreach (App\Services\Store::pages()->iterate() as $row) {
                  if ($row['status'] === 'published' && !in_array($row['slug'], ['mentions-legales', 'cgu', 'confidentialite', 'qui-sommes-nous', 'faq', 'charte-qualite'], true)) {
                      $full = App\Services\Store::pages()->get((int) $row['id']);
                      if (!empty($full['in_footer'])) {
                          $out[] = [$row['title'], Url::page($row['slug'])];
                      }
                  }
              }
              return $out;
          }) as [$label, $href]): ?><li><a class="ul" href="<?= e($href) ?>"><?= e($label) ?></a></li><?php endforeach; ?>
        </ul>
      </nav>
    </div>
    <div class="footer-legal">
      <span>© <?= $founded ?>–<?= date('Y') ?> <?= e(Settings::siteName()) ?></span>
      <span class="legal-links">
        <a class="ul" href="/mentions-legales/">Mentions légales</a><a class="ul" href="/cgu/">CGU</a><a class="ul" href="/confidentialite/">Confidentialité</a><a class="ul" href="/plan-du-site/">Plan du site</a><button type="button" class="ul" data-consent-open>Gérer les cookies</button>
      </span>
      <a class="to-top" href="#top" data-to-top>Haut de page <span aria-hidden="true">↑</span></a>
    </div>
  </div>
  <div class="footer-giant" aria-hidden="true" data-giant><?php $k = 0; foreach ($giant as $part => $tag): ?><?= $tag ? '<em>' : '' ?><?php foreach (mb_str_split($part) as $ch): ?><span style="--i:<?= $k++ ?>"><?= e($ch) ?></span><?php endforeach; ?><?= $tag ? '</em>' : '' ?><?php endforeach; ?></div>
</footer>
