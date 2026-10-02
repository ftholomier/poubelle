<?php
use App\Core\Url;
use App\Services\Settings;
?>
<footer class="site-footer">
  <div class="wrap">
    <span class="wordmark">animateur<em>pour</em>votresoirée</span>
    <div class="footer-links">
      <a href="<?= Url::category('dj') ?>">DJ</a>
      <a href="<?= Url::category('magicien') ?>">Magiciens</a>
      <a href="<?= Url::category('animation-enfants') ?>">Animateurs enfants</a>
      <a href="/devis/">Déposer une demande de devis</a>
      <a href="/blog/">Blog</a>
      <a href="/contact/">Contact</a>
      <a href="/mentions-legales/">Mentions légales</a>
      <?php foreach (App\Core\Cache::remember('footer_pages', 3600, static function (): array {
          $out = [];
          foreach (App\Services\Store::pages()->iterate() as $row) {
              if ($row['status'] === 'published' && !in_array($row['slug'], ['mentions-legales', 'cgu', 'confidentialite'], true)) {
                  $full = App\Services\Store::pages()->get((int) $row['id']);
                  if (!empty($full['in_footer'])) {
                      $out[] = [$row['title'], Url::page($row['slug'])];
                  }
              }
          }
          return $out;
      }) as [$label, $href]): ?><a href="<?= e($href) ?>"><?= e($label) ?></a><?php endforeach; ?>
    </div>
    <div class="footer-legal">
      <span>© <?= (int) Settings::get('site.founded', 2001) ?>–<?= date('Y') ?> <?= e(Settings::siteName()) ?> · <?= e(Settings::get('site.baseline')) ?></span>
      <span class="row-wrap">
        <a href="/cgu/">CGU</a> · <a href="/confidentialite/">Confidentialité</a> · <a href="/plan-du-site/">Plan du site</a> · <a href="/connexion/">Espace pro</a> · <button type="button" data-consent-open>Gérer les cookies</button>
        <button type="button" class="pill-ghost install-btn" data-install><?= icon('download', 14) ?> Installer l'appli</button>
      </span>
    </div>
  </div>
</footer>
