<?php
/**
 * Configuration JS + widget Google Traduction (déclaration de langue).
 * @var array $settings
 * @var string $lang
 * @var array $languages
 */

use App\Content\Settings;
use App\Core\Config;
use App\Security\Csrf;

$googleTranslate = Settings::bool('i18n.google_translate', true);
$available = Config::arr('i18n.available', ['fr', 'en']);
$analytics = Settings::str('seo.analytics_id');
?>
<script type="application/json" id="app-config">
<?= json_encode([
    'lang'       => $lang,
    'default'    => Settings::str('i18n.default', 'fr'),
    'languages'  => $languages,
    'token'      => Csrf::token('public'),
    'endpoints'  => [
        'contact' => '/api/contact',
        'lead'    => '/api/lead',
        'chat'    => '/api/chat',
        'reviews' => '/api/reviews',
        'search'  => '/api/search',
    ],
    'strings' => [
        'sending'     => __('form.sending'),
        'send'        => __('form.send'),
        'error'       => __('form.error'),
        'required'    => __('form.required'),
        'sources'     => __('chat.sources'),
        'unavailable' => __('chat.unavailable'),
        'menuOpen'    => __('nav.open'),
        'menuClose'   => __('nav.close'),
    ],
    'motion' => (bool) (Settings::get('brand.motion', true)),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>
</script>

<?php if ($googleTranslate): ?>
    <?php /* Traduction automatique pour les langues sans traduction native saisie. */ ?>
    <div id="google_translate_element" class="gtranslate" aria-hidden="true"></div>
    <script>
        window.lcalGoogleTranslateInit = function () {
            if (!window.google || !window.google.translate) { return; }
            new google.translate.TranslateElement({
                pageLanguage: <?= json_encode(Settings::str('i18n.default', 'fr')) ?>,
                includedLanguages: <?= json_encode(implode(',', $available)) ?>,
                autoDisplay: false,
                layout: google.translate.TranslateElement.InlineLayout.SIMPLE
            }, 'google_translate_element');
        };
    </script>
    <script src="https://translate.google.com/translate_a/element.js?cb=lcalGoogleTranslateInit" defer></script>
<?php endif; ?>

<script src="<?= e(asset('/assets/js/app.js')) ?>" defer></script>

<?php if ($analytics !== '' && preg_match('/^G-[A-Z0-9]+$/', $analytics)): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($analytics) ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){ dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', <?= json_encode($analytics) ?>, { anonymize_ip: true });
    </script>
<?php endif; ?>
