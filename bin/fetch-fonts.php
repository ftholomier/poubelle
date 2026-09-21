#!/usr/bin/env php
<?php
/**
 * Auto-hébergement des polices (exigence du handoff) : récupère Bricolage Grotesque
 * et Plus Jakarta Sans depuis Google Fonts, écrit les woff2 dans
 * public/assets/fonts/ et génère fonts.css avec des URL locales.
 *
 *   php bin/fetch-fonts.php
 *
 * Sans réseau, le site reste lisible : fonts.css est optionnel et la pile
 * système prend le relais (voir la variable --font-* dans app.css).
 */
declare(strict_types=1);

$dir = dirname(__DIR__) . '/public/assets/fonts';
@mkdir($dir, 0775, true);

$source = 'https://fonts.googleapis.com/css2'
        . '?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,700;12..96,800'
        . '&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap';

$agent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36';

$fetch = static function (string $url) use ($agent): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_USERAGENT      => $agent,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200 && is_string($body) ? $body : '';
};

$css = $fetch($source);
if ($css === '') {
    fwrite(STDERR, "Google Fonts injoignable. fonts.css inchangé.\n");
    exit(1);
}

preg_match_all('#https://fonts\.gstatic\.com/[^)\s]+\.woff2#', $css, $matches);
$urls = array_values(array_unique($matches[0]));
printf("%d fichier(s) de police à récupérer\n", count($urls));

$map = [];
foreach ($urls as $url) {
    // Nom stable : famille + empreinte courte de l'URL d'origine.
    $family = preg_match('#/s/([a-z0-9-]+)/#', $url, $m) ? $m[1] : 'font';
    $name = $family . '-' . substr(sha1($url), 0, 8) . '.woff2';
    $target = $dir . '/' . $name;

    if (!is_file($target)) {
        $binary = $fetch($url);
        if ($binary === '') {
            fwrite(STDERR, "   échec : $url\n");
            continue;
        }
        file_put_contents($target, $binary);
    }
    $map[$url] = './' . $name;
    printf("   %s  (%d Ko)\n", $name, (int) (filesize($target) / 1024));
}

$local = strtr($css, $map);
$local = "/* Généré par bin/fetch-fonts.php — ne pas éditer à la main. */\n" . $local;
file_put_contents($dir . '/fonts.css', $local);

printf("fonts.css écrit (%d octets).\n", strlen($local));
