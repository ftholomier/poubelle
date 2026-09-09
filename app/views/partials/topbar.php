<?php
/** Bandeau haut : arguments + sélecteur de langue. */

use App\Config;
use App\Content;
use App\I18n;
use App\Router;
use App\Text;

$lang = I18n::lang();
$top = (array) ($settings['top'] ?? []);
$line1 = Content::i18n($top, 'line1', $lang);
$line2 = Content::i18n($top, 'line2', $lang);
?>
<div class="topbar">
  <div class="shell topbar__inner">
    <div class="topbar__facts">
      <?php if ($line1 !== ''): ?><span><?= Text::e($line1) ?></span><?php endif; ?>
      <?php if ($line1 !== '' && $line2 !== ''): ?><span class="topbar__sep">/</span><?php endif; ?>
      <?php if ($line2 !== ''): ?><span><?= Text::e($line2) ?></span><?php endif; ?>
    </div>
    <?php if (\count(Config::LANGS) > 1): ?>
    <div class="topbar__langs">
      <span class="topbar__langLabel"><?= Text::e(I18n::t('top.langLabel')) ?></span>
      <?php foreach (Config::LANGS as $code): ?>
        <a class="lang<?= $code === $lang ? ' is-active' : '' ?>"
           href="<?= Text::e(Router::url($route ?? 'home', $code, $params ?? [])) ?>"
           hreflang="<?= Text::e($code) ?>"
           lang="<?= Text::e($code) ?>"
           rel="alternate"
           <?= $code === $lang ? 'aria-current="true"' : '' ?>><?= Text::e(strtoupper($code)) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if (!I18n::isDefault()): ?>
<div class="translate-banner">
  <div class="shell translate-banner__inner">
    <span class="translate-banner__dot"></span>
    <span><?= Text::e(I18n::t('top.autoTranslate')) ?></span>
  </div>
</div>
<?php endif; ?>
