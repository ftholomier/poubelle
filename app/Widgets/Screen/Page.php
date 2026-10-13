<?php
declare(strict_types=1);

namespace App\Widgets\Screen;

use App\Core\Club;
use App\Services\Qr;
use App\Widgets\Context;
use App\Widgets\Frame;
use App\Widgets\Items\CarteStades;

/**
 * Page plein écran d'une playlist (/ecran/<playlist>) : une scène fixe de 1920 × 1080 (ou
 * 1080 × 1920 en vertical) mise à l'échelle de l'écran par js/ecran.js, très gros caractères,
 * fort contraste (thème sombre aux couleurs du club), ni souris ni défilement, slides enchaînées
 * avec un fondu sobre et une barre de progression ; logo du musée, mention « non officiel » et
 * adresse du musée toujours visibles en bas ; horloge facultative ; pas de son.
 * Hors connexion brève : la page reste en mémoire et continue de tourner ; le service worker
 * /ecran/sw.js en garde la dernière version (et ses images) pour un redémarrage sans réseau.
 */
final class Page
{
    /**
     * @param list<array> $slides modèles (Slides::build)
     * @param array{format:string,clock:bool,reload:int,name:string,id:string} $o
     */
    public static function render(array $slides, Context $c, array $o): string
    {
        $vertical = $o['format'] === 'vertical';
        $html = '';
        foreach ($slides as $i => $s) {
            $html .= self::slide($s, $c, $vertical);
        }
        if ($html === '') {
            $html = self::slide(['layout' => 'quote', 'eyebrow' => Club::s('site'), 'text' => $c->tr('Le musée revient dans un instant.', 'The museum will be back in a moment.'), 'duration' => 30, 'type' => 'vide'], $c, $vertical);
        }
        return self::shell($c, $o, $html);
    }

    /** Message plein écran à la place de la playlist (accès refusé, playlist inconnue…). */
    public static function notice(string $msg, Context $c, string $format = 'paysage'): string
    {
        $html = self::slide(['layout' => 'quote', 'eyebrow' => Club::s('site'), 'text' => $msg, 'duration' => 3600, 'type' => 'message'], $c, $format === 'vertical');
        return self::shell($c, ['format' => $format, 'clock' => false, 'reload' => 5, 'name' => '', 'id' => ''], $html);
    }

    private static function shell(Context $c, array $o, string $slides): string
    {
        $vertical = $o['format'] === 'vertical';
        $logo = Context::abs((string) (Club::s('logo_400') ?: Club::s('logo_png')));
        return '<!DOCTYPE html><html lang="' . ($c->en() ? 'en' : 'fr') . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
            . '<meta name="theme-color" content="' . e(Club::color('navy')) . '">'
            . '<title>' . e(($o['name'] !== '' ? $o['name'] . ' · ' : '') . Club::s('site')) . '</title>'
            . '<link rel="stylesheet" href="' . e(Context::abs(asset('css/fonts.css'))) . '">'
            . '<style>' . self::css() . '</style></head>'
            . '<body class="ec ec--' . ($vertical ? 'vertical' : 'paysage') . '" data-reload="' . (int) $o['reload'] . '" data-sw="' . e(Context::abs('/ecran/sw.js')) . '">'
            . '<div class="ec-stage" data-w="' . ($vertical ? 1080 : 1920) . '" data-h="' . ($vertical ? 1920 : 1080) . '">'
            . '<div class="ec-slides">' . $slides . '</div>'
            . '<div class="ec-progress" aria-hidden="true"><i></i></div>'
            . '<footer class="ec-foot"><img src="' . e($logo) . '" alt=""><div class="ec-foot__id"><b>' . e(Club::s('site')) . '</b><span>' . e(Frame::mention($c)) . '</span></div>'
            . '<span class="ec-url">' . e(Context::host()) . '</span>' . ($o['clock'] ? '<span class="ec-clock" data-clock>' . date('H:i') . '</span>' : '') . '</footer>'
            . '</div><script src="' . e(Context::abs(asset('js/ecran.js'))) . '" defer></script></body></html>';
    }

    /** Une slide en HTML (échappé). */
    public static function slide(array $s, Context $c, bool $vertical = false): string
    {
        $attrs = ' data-duration="' . max(3, (int) ($s['duration'] ?? 10)) . '" data-type="' . e((string) ($s['type'] ?? '')) . '"';
        $eb = !empty($s['eyebrow']) ? '<p class="ec-eyebrow">' . (!empty($s['live']) ? '<i class="ec-live"></i>' : '') . e($s['eyebrow']) . '</p>' : '';
        $qr = fn (?string $url, string $label = '') => $url ? '<figure class="ec-qr">' . Qr::svg($url, 6, Club::color('navy')) . ($label !== '' ? '<figcaption>' . e($label) . '</figcaption>' : '') . '</figure>' : '';
        $img = fn (?array $im, string $cls = 'ec-img') => $im ? '<figure class="' . $cls . '"><img src="' . e($im['url']) . '" alt="" decoding="async">' . ($im['credit'] !== '' ? '<figcaption>' . e($im['credit']) . '</figcaption>' : '') . '</figure>' : '';
        $stats = function (array $st) {
            $h = '<div class="ec-stats ec-stats--' . count($st) . '">';
            foreach ($st as $x) {
                $h .= '<span' . (!empty($x[2]) ? ' class="is-key"' : '') . '><b>' . e((string) $x[0]) . '</b>' . e((string) $x[1]) . '</span>';
            }
            return $h . '</div>';
        };
        $layout = (string) ($s['layout'] ?? 'quote');
        $h = '<section class="ec-slide ec-l--' . e($layout) . (!empty($s['first']) ? ' ec-first' : '') . '"' . $attrs . (($s['layout'] ?? '') === 'quiz' ? ' data-answer="' . (int) $s['answerAt'] . '"' : '') . (isset($s['countdown']) ? ' data-countdown="' . (int) $s['countdown'] . '"' : '') . '>';
        switch ($layout) {
            case 'duel':
                $h .= '<div class="ec-main">' . $eb . '<h1 class="ec-title">' . e($s['title']) . '</h1>' . (!empty($s['lead']) ? '<p class="ec-lead">' . e($s['lead']) . '</p>' : '') . $stats($s['stats']);
                if (!empty($s['bar'])) {
                    $t = max(1, array_sum($s['bar']));
                    $h .= '<div class="ec-bar"><i class="v" style="width:' . round(100 * $s['bar'][0] / $t, 2) . '%"></i><i class="n" style="width:' . round(100 * $s['bar'][1] / $t, 2) . '%"></i><i class="d" style="width:' . round(100 * $s['bar'][2] / $t, 2) . '%"></i></div>';
                }
                $h .= (!empty($s['sub']) ? '<p class="ec-sub">' . e($s['sub']) . '</p>' : '') . '<ul class="ec-facts">';
                foreach ($s['rows'] as $r) {
                    $h .= '<li><small>' . e($r[0]) . '</small><b>' . e($r[1]) . '</b><span>' . e($r[2]) . '</span></li>';
                }
                $h .= '</ul></div>' . $qr($s['qr'] ?? null, !empty($s['first']) ? $c->tr('Les face-à-face', 'Head-to-heads') : $c->tr('Tout le duel', 'Full record'));
                break;
            case 'list':
                $h .= '<div class="ec-main">' . $eb . '<h1 class="ec-title">' . e($s['title']) . '</h1><ul class="ec-rows' . (!empty($s['trophy']) ? ' ec-rows--trophy' : '') . (count($s['rows']) > 3 ? ' ec-rows--dense' : '') . '">';
                foreach ($s['rows'] as $r) {
                    $res = (string) ($r[3] ?? '');
                    $h .= '<li><em>' . e($r[0]) . '</em><div><b>' . e($r[1]) . '</b><span>' . e($r[2]) . '</span></div>' . ($res !== '' ? '<i class="ec-res ec-res--' . e(strtolower($res)) . '">' . e(match ($res) { 'V' => $c->tr('Victoire', 'Win'), 'N' => $c->tr('Nul', 'Draw'), 'D' => $c->tr('Défaite', 'Defeat'), default => $res }) . '</i>' : '') . '</li>';
                }
                $h .= '</ul></div>';
                break;
            case 'rank':
                $h .= '<div class="ec-main">' . $eb . '<h1 class="ec-title">' . e($s['title']) . '</h1><ol class="ec-rank">';
                foreach ($s['rows'] as $r) {
                    $h .= '<li><em>' . e((string) $r[0]) . '</em><div><b>' . e($r[1]) . '</b>' . ($r[2] !== '' ? '<span>' . e($r[2]) . '</span>' : '') . '</div><strong>' . e($r[3]) . '</strong></li>';
                }
                $h .= '</ol></div>';
                break;
            case 'hero':
                $h .= '<div class="ec-main">' . $eb . '<p class="ec-big">' . e($s['big']) . (!empty($s['unit']) ? ' <small>' . e($s['unit']) . '</small>' : '') . '</p>'
                    . '<h1 class="ec-title">' . e($s['title']) . '</h1>' . (!empty($s['text']) ? '<p class="ec-text">' . e($s['text']) . '</p>' : '') . (!empty($s['sub']) ? '<p class="ec-sub">' . e($s['sub']) . '</p>' : '') . '</div>'
                    . $img($s['image'] ?? null, 'ec-img ec-img--side');
                break;
            case 'quote':
                $h .= '<div class="ec-main">' . $eb . (!empty($s['big']) ? '<p class="ec-big ec-big--year">' . e($s['big']) . '</p>' : '') . '<blockquote class="ec-quote">' . e($s['text']) . '</blockquote>'
                    . (!empty($s['sub']) ? '<p class="ec-sub">' . e($c->tr('Source : ', 'Source: ') . $s['sub']) . '</p>' : '') . '</div>';
                break;
            case 'card':
                $h .= '<div class="ec-cardimg">' . (!empty($s['image']) ? $img($s['image'], 'ec-img ec-img--card') : '<div class="ec-ini">' . e($s['initials']) . '</div>') . '</div>'
                    . '<div class="ec-main">' . $eb . (!empty($s['badge']) ? '<p class="ec-badge">' . e($s['badge']) . '</p>' : '') . '<h1 class="ec-title ec-title--name"><small>' . e($s['first']) . '</small>' . e($s['last']) . '</h1>'
                    . '<p class="ec-sub">' . e($s['sub']) . '</p>' . $stats($s['stats']) . '</div>';
                break;
            case 'match':
                $h .= $img($s['image'] ?? null, 'ec-img ec-img--bg') . '<div class="ec-main">' . $eb . '<div class="ec-score"><b>' . e($s['home']) . '</b><strong>' . e($s['score']) . '</strong><b>' . e($s['away']) . '</b></div>'
                    . '<p class="ec-sub ec-center">' . e($s['sub']) . '</p>' . (!empty($s['text']) ? '<p class="ec-text ec-center">' . e($s['text']) . '</p>' : '') . '</div>' . $qr($s['qr'] ?? null);
                break;
            case 'photo':
                $h .= '<div class="ec-main ec-main--photo">' . $eb . '<figure class="ec-photo"><img src="' . e($s['image']['url']) . '" alt="' . e($s['text']) . '" decoding="async"></figure>'
                    . '<p class="ec-caption">' . e($s['text']) . '</p><p class="ec-credit">' . e($s['image']['credit']) . '</p></div>';
                break;
            case 'quiz':
                $q = $s['quiz'];
                $h .= '<div class="ec-main">' . $eb . '<h1 class="ec-q">' . e($q['q']) . '</h1><ol class="ec-choices ec-choices--' . count($q['a']) . '">';
                foreach ($q['a'] as $i => $a) {
                    $h .= '<li' . ($i === $q['c'] ? ' class="is-good"' : '') . '><em>' . chr(65 + $i) . '</em>' . e($a) . '</li>';
                }
                $h .= '</ol><p class="ec-fact">' . e($q['fact'] !== '' ? $q['fact'] : $c->tr('Bonne réponse : ', 'Right answer: ') . $q['a'][$q['c']]) . '</p></div>'
                    . '<div class="ec-timer" aria-hidden="true"><b data-timer>' . max(1, (int) $s['duration'] - (int) $s['answerAt']) . '</b><span>' . e($c->tr('secondes', 'seconds')) . '</span></div>'
                    . $qr($s['qr'] ?? null, (string) ($s['qrLabel'] ?? ''));
                break;
            case 'timeline':
                $h .= '<div class="ec-main">' . $eb . '<ol class="ec-tl">';
                foreach ($s['events'] as [$y, $t, $x]) {
                    $h .= '<li><b>' . e((string) $y) . '</b><strong>' . e($t) . '</strong>' . ($x !== '' ? '<span>' . e($x) . '</span>' : '') . '</li>';
                }
                $h .= '</ol></div>';
                break;
            case 'map':
                $h .= '<div class="ec-mapbox">' . CarteStades::svg($s['map'], 'ec-map') . '</div><div class="ec-main">' . $eb . '<h1 class="ec-title">' . e($s['title']) . '</h1><ol class="ec-rank ec-rank--map">';
                foreach ($s['rows'] as $r) {
                    $h .= '<li><em>' . e($r[0]) . '</em><div><b>' . e($r[1]) . '</b>' . ($r[2] !== '' ? '<span>' . e($r[2]) . '</span>' : '') . '</div><strong>' . e($r[3]) . '</strong></li>';
                }
                $h .= '</ol></div>';
                break;
            case 'countdown':
                $h .= '<div class="ec-main ec-center">' . $eb . '<h1 class="ec-title">' . e($s['title']) . '</h1><div class="ec-cd"><span><b data-cd="j">' . e($s['big']) . '</b>' . e($c->tr('jours', 'days')) . '</span><span><b data-cd="h">–</b>' . e($c->tr('heures', 'hours')) . '</span><span><b data-cd="m">–</b>' . e($c->tr('minutes', 'minutes')) . '</span></div>'
                    . '<p class="ec-sub">' . e($s['sub']) . '</p></div>';
                break;
            case 'support':
                $h .= '<div class="ec-main">' . $eb . '<h1 class="ec-title">' . e($s['title']) . '</h1><ul class="ec-support ec-support--' . count($s['items']) . '">';
                foreach ($s['items'] as $it) {
                    $h .= '<li>' . $qr($it['qr']) . '<b>' . e($it['label']) . '</b><span>' . e($it['text']) . '</span></li>';
                }
                $h .= '</ul></div>';
                break;
        }
        return $h . '</section>';
    }

    /** Feuille de style de l'écran (en ligne : une seule requête). Mesures en pixels de la scène. */
    public static function css(): string
    {
        $c = fn (string $k) => Club::color($k);
        return ':root{--navy:' . $c('navy') . ';--navy2:' . $c('navy-2') . ';--accent:' . $c('yellow') . ';--cream:' . $c('cream') . ';--mist:' . $c('mist') . ';--red:' . $c('red') . ';--green:#2fb36a;'
            . "--display:'Big Shoulders Display','Arial Narrow',sans-serif;--serif:'Newsreader',Georgia,serif}"
            . '*{box-sizing:border-box;margin:0;padding:0}html,body{width:100%;height:100%;overflow:hidden;background:#000;cursor:none;user-select:none}'
            . 'body{color:var(--cream);font-family:var(--serif);-webkit-font-smoothing:antialiased}'
            . '.ec-stage{position:absolute;left:0;top:0;width:1920px;height:1080px;transform-origin:0 0;background:var(--navy);overflow:hidden}'
            . '.ec--vertical .ec-stage{width:1080px;height:1920px}'
            . '.ec-slides{position:absolute;inset:0 0 120px 0}'
            . '.ec-slide{position:absolute;inset:0;display:flex;gap:70px;align-items:center;padding:60px 110px 40px;opacity:0;transition:opacity .9s ease;overflow:hidden}'
            . '.ec-slide.is-on{opacity:1}.ec-main{flex:1;min-width:0;position:relative;z-index:1}'
            . '.ec--vertical .ec-slide{flex-direction:column;justify-content:center;align-items:stretch;padding:110px 70px 50px;gap:50px}'
            . '.ec-eyebrow{font:800 40px/1.1 var(--display);letter-spacing:.14em;text-transform:uppercase;color:var(--accent);margin-bottom:22px;display:flex;align-items:center;gap:18px}'
            . '.ec-live{width:26px;height:26px;border-radius:50%;background:var(--red);animation:ec-blink 1.4s ease-in-out infinite}@keyframes ec-blink{50%{opacity:.3}}'
            . '.ec-title{font:900 112px/.95 var(--display);text-transform:uppercase;letter-spacing:.01em;margin-bottom:34px}'
            . '.ec--vertical .ec-title{font-size:96px}'
            . '.ec-sub{font:600 40px/1.25 var(--serif);color:var(--mist);margin-top:22px}.ec-lead{font:700 46px/1.2 var(--serif);color:var(--accent);margin:-14px 0 26px}.ec-first .ec-title{font-size:84px;margin-bottom:22px}.ec-first .ec-stats b{font-size:118px}.ec-first .ec-stats span{font-size:28px;padding:18px 8px 16px}.ec-center{text-align:center}'
            . '.ec-text{font:400 44px/1.3 var(--serif);margin-top:18px;display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden}'
            . '.ec-stats{display:grid;gap:26px;grid-template-columns:repeat(4,1fr)}.ec-stats--3{grid-template-columns:repeat(3,1fr)}'
            . '.ec-stats span{border:4px solid rgba(255,255,255,.22);border-radius:22px;padding:22px 10px 20px;text-align:center;font:800 34px/1 var(--display);text-transform:uppercase;letter-spacing:.06em;color:var(--mist)}'
            . '.ec-stats b{display:block;font:900 150px/.9 var(--display);color:var(--cream);letter-spacing:0;margin-bottom:8px}'
            . '.ec-stats .is-key{background:var(--accent);border-color:var(--accent);color:var(--navy)}.ec-stats .is-key b{color:var(--navy)}'
            . '.ec-bar{display:flex;height:34px;border-radius:17px;overflow:hidden;margin-top:30px;background:rgba(255,255,255,.15)}.ec-bar i{display:block}.ec-bar .v{background:var(--accent)}.ec-bar .n{background:var(--cream)}.ec-bar .d{background:var(--red)}'
            . '.ec-facts{list-style:none;margin-top:30px;border-top:3px solid rgba(255,255,255,.2)}.ec-facts li{display:grid;grid-template-columns:420px 1fr auto;gap:30px;align-items:baseline;padding:13px 0;border-bottom:3px solid rgba(255,255,255,.2)}'
            . '.ec-facts small{font:800 30px/1.1 var(--display);text-transform:uppercase;letter-spacing:.08em;color:var(--accent)}.ec-facts b{font:800 46px/1.1 var(--display);text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ec-facts span{font:500 34px/1 var(--serif);color:var(--mist);white-space:nowrap}'
            . '.ec--vertical .ec-facts li{grid-template-columns:1fr;gap:6px}'
            . '.ec-qr{flex:none;align-self:flex-end;background:#fff;border-radius:22px;padding:14px;text-align:center;width:300px}.ec-qr svg{display:block;width:100%;height:auto}.ec-qr figcaption{font:800 24px/1.1 var(--display);text-transform:uppercase;letter-spacing:.06em;color:var(--navy);margin-top:6px}'
            . '.ec--vertical .ec-qr{align-self:center;width:300px}'
            . '.ec-rows{list-style:none}.ec-rows li{display:flex;align-items:center;gap:40px;padding:26px 0;border-bottom:3px solid rgba(255,255,255,.18)}.ec-rows li:first-child{border-top:3px solid rgba(255,255,255,.18)}'
            . '.ec-rows em{flex:none;min-width:250px;font:900 46px/1 var(--display);font-style:normal;text-transform:uppercase;color:var(--navy);background:var(--accent);border-radius:999px;padding:14px 26px;text-align:center}'
            . '.ec-rows--trophy em{min-width:110px;font-size:60px}.ec-rows div{flex:1;min-width:0}.ec-rows b{display:block;font:800 64px/1.05 var(--display);text-transform:uppercase}.ec-rows span{display:block;font:500 36px/1.25 var(--serif);color:var(--mist);margin-top:6px}'
            . '.ec-rows--dense li{padding:13px 0;gap:30px}.ec-rows--dense em{font-size:40px;padding:12px 22px}.ec-rows--dense b{font-size:54px}.ec-rows--dense span{font-size:30px;margin-top:2px}'
            . '.ec--vertical .ec-rows li{flex-direction:column;align-items:flex-start;gap:16px}.ec--vertical .ec-rows em{min-width:0}'
            . '.ec-res{flex:none;font:800 32px/1 var(--display);font-style:normal;text-transform:uppercase;letter-spacing:.06em;padding:12px 20px;border-radius:999px;background:rgba(255,255,255,.2)}.ec-res--v{background:var(--green)}.ec-res--d{background:var(--red)}'
            . '.ec-rank{list-style:none;columns:2;column-gap:70px}.ec-rank li{break-inside:avoid;display:grid;grid-template-columns:80px minmax(0,1fr) auto;gap:22px;align-items:center;padding:14px 0;border-bottom:3px solid rgba(255,255,255,.16)}'
            . '.ec-rank em{font:900 56px/1 var(--display);font-style:normal;color:var(--mist);text-align:center}.ec-rank li:nth-child(-n+3) em{color:var(--accent)}'
            . '.ec-rank b{display:block;font:800 48px/1.05 var(--display);text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ec-rank span{display:block;font:500 26px/1.2 var(--serif);color:var(--mist);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
            . '.ec-rank strong{font:900 52px/1 var(--display);white-space:nowrap}.ec--vertical .ec-rank{columns:1}.ec-rank--map{columns:1}'
            . '.ec-big{font:900 300px/.85 var(--display);color:var(--accent);margin-bottom:24px;letter-spacing:-.01em}.ec-big small{font-size:90px;letter-spacing:.04em;text-transform:uppercase;color:var(--cream)}.ec-big--year{font-size:200px}'
            . '.ec-img{position:relative;margin:0}.ec-img img{display:block;width:100%;height:100%;object-fit:cover;border-radius:26px}.ec-img figcaption{position:absolute;right:16px;bottom:12px;font:500 22px/1.2 var(--serif);color:#fff;background:rgba(0,0,0,.55);padding:6px 12px;border-radius:8px}'
            . '.ec-img--side{flex:none;width:720px;height:820px}.ec--vertical .ec-img--side{width:100%;height:640px;order:-1}'
            . '.ec-quote{font:italic 500 76px/1.22 var(--serif);border-left:14px solid var(--accent);padding-left:50px;display:-webkit-box;-webkit-line-clamp:6;-webkit-box-orient:vertical;overflow:hidden}'
            . '.ec-cardimg{flex:none;width:620px;height:820px;border:14px solid var(--accent);border-radius:30px;overflow:hidden;background:var(--navy2)}.ec-cardimg .ec-img{width:100%;height:100%}.ec-cardimg img{border-radius:0}'
            . '.ec-ini{display:grid;place-items:center;height:100%;font:900 300px/1 var(--display);color:var(--accent)}.ec--vertical .ec-cardimg{width:100%;height:760px}'
            . '.ec-title--name small{display:block;font-size:60px;color:var(--accent);letter-spacing:.06em}.ec-title--name{font-size:150px}'
            . '.ec-badge{display:inline-block;background:var(--accent);color:var(--navy);font:800 32px/1 var(--display);text-transform:uppercase;letter-spacing:.1em;padding:12px 20px;border-radius:10px;margin-bottom:20px}'
            . '.ec-img--bg{position:absolute;inset:0;opacity:.28}.ec-img--bg img{border-radius:0}.ec-img--bg figcaption{bottom:20px;right:24px}'
            . '.ec-score{display:flex;align-items:center;justify-content:center;gap:60px;margin:40px 0 20px}.ec-score b{flex:1;font:900 120px/1 var(--display);text-transform:uppercase;text-align:right}.ec-score b:last-child{text-align:left}'
            . '.ec-score strong{font:900 260px/1 var(--display);color:var(--accent);white-space:nowrap}.ec--vertical .ec-score{flex-direction:column;gap:20px}.ec--vertical .ec-score b{text-align:center!important;font-size:110px}'
            . '.ec-main--photo{height:100%;display:flex;flex-direction:column}.ec-photo{flex:1;min-height:0;margin:0;display:flex;justify-content:center}.ec-photo img{max-width:100%;max-height:100%;object-fit:contain;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.4)}'
            . '.ec-caption{font:600 48px/1.2 var(--serif);text-align:center;margin-top:24px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ec-credit{font:500 26px/1.2 var(--serif);text-align:center;color:var(--mist);margin-top:8px}'
            . '.ec-q{font:700 76px/1.15 var(--serif);margin-bottom:46px}.ec-choices{list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:26px}.ec-choices--2{grid-template-columns:1fr 1fr}'
            . '.ec-choices li{display:flex;align-items:center;gap:26px;font:600 50px/1.15 var(--serif);border:4px solid rgba(255,255,255,.3);border-radius:24px;padding:24px 30px;transition:background .6s,opacity .6s,border-color .6s}'
            . '.ec-choices em{flex:none;display:grid;place-items:center;width:80px;height:80px;border-radius:50%;background:var(--accent);color:var(--navy);font:900 50px/1 var(--display);font-style:normal}'
            . '.ec--vertical .ec-choices{grid-template-columns:1fr}'
            . '.ec-fact{font:500 44px/1.3 var(--serif);margin-top:36px;color:var(--cream);opacity:0;transition:opacity .8s;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}'
            . '.is-answer .ec-choices li{opacity:.35}.is-answer .ec-choices li.is-good{opacity:1;background:var(--green);border-color:var(--green)}.is-answer .ec-fact{opacity:1}.is-answer .ec-timer{opacity:0}'
            . '.ec-timer{position:absolute;right:110px;top:70px;width:220px;height:220px;border-radius:50%;border:12px solid var(--accent);display:grid;place-content:center;text-align:center;transition:opacity .6s}'
            . '.ec-timer b{font:900 110px/1 var(--display);color:var(--accent)}.ec-timer span{font:800 24px/1 var(--display);text-transform:uppercase;letter-spacing:.1em}'
            . '.ec--vertical .ec-timer{right:70px;top:80px;width:180px;height:180px}.ec--vertical .ec-timer b{font-size:86px}'
            . '.ec-l--quiz .ec-qr{position:absolute;right:110px;bottom:40px;width:250px}.ec-l--quiz .ec-main{padding-right:290px}.ec--vertical .ec-l--quiz .ec-main{padding-right:0;padding-top:170px}.ec--vertical .ec-l--quiz .ec-qr{position:static}'
            . '.ec-tl{list-style:none;display:grid;grid-template-columns:repeat(5,1fr);gap:34px;border-top:10px solid var(--accent);padding-top:40px;margin-top:40px}'
            . '.ec-tl li{position:relative}.ec-tl li::before{content:"";position:absolute;top:-63px;left:0;width:36px;height:36px;border-radius:50%;background:var(--accent);border:8px solid var(--navy)}'
            . '.ec-tl b{display:block;font:900 110px/1 var(--display);color:var(--accent)}.ec-tl strong{display:block;font:800 42px/1.08 var(--display);text-transform:uppercase;margin:14px 0 12px}.ec-tl span{display:block;font:500 28px/1.3 var(--serif);color:var(--mist)}'
            . '.ec--vertical .ec-tl{grid-template-columns:1fr;border-top:0;border-left:10px solid var(--accent);padding:0 0 0 50px;gap:30px}.ec--vertical .ec-tl li::before{top:20px;left:-73px}.ec--vertical .ec-tl b{font-size:84px}'
            . '.ec-mapbox{flex:none;width:860px;height:820px}.ec-map{width:100%;height:100%}.ec-map .w-map__land{fill:rgba(255,255,255,.12);stroke:rgba(255,255,255,.35);stroke-width:1.2}'
            . '.ec-map .w-map__pt{fill:var(--accent);fill-opacity:.55;stroke:var(--accent);stroke-width:1.5}.ec-map .w-map__pt.is-home{fill:var(--red);fill-opacity:1;stroke:#fff;stroke-width:3}'
            . '.ec--vertical .ec-mapbox{width:100%;height:780px}'
            . '.ec-cd{display:flex;justify-content:center;gap:40px;margin:30px 0}.ec-cd span{min-width:340px;border:5px solid rgba(255,255,255,.25);border-radius:30px;padding:30px 20px;font:800 44px/1 var(--display);text-transform:uppercase;letter-spacing:.08em;color:var(--mist)}'
            . '.ec-cd b{display:block;font:900 240px/.9 var(--display);color:var(--accent);margin-bottom:14px;letter-spacing:0}.ec-cd span:first-child{background:var(--accent);border-color:var(--accent);color:var(--navy)}.ec-cd span:first-child b{color:var(--navy)}'
            . '.ec--vertical .ec-cd{flex-direction:column;align-items:center}'
            . '.ec-support{list-style:none;display:grid;gap:60px;grid-template-columns:repeat(3,1fr)}.ec-support--1{grid-template-columns:420px}.ec-support--2{grid-template-columns:repeat(2,460px)}'
            . '.ec-support li{text-align:center}.ec-support .ec-qr{width:340px;margin:0 auto 26px}.ec-support b{display:block;font:900 64px/1 var(--display);text-transform:uppercase;color:var(--accent)}.ec-support span{display:block;font:500 32px/1.3 var(--serif);margin-top:12px;color:var(--mist)}'
            . '.ec--vertical .ec-support{grid-template-columns:1fr;gap:40px}.ec--vertical .ec-support .ec-qr{width:260px}'
            . '.ec-progress{position:absolute;left:0;right:0;bottom:120px;height:10px;background:rgba(255,255,255,.12)}.ec-progress i{display:block;height:100%;width:0;background:var(--accent)}'
            . '.ec-foot{position:absolute;left:0;right:0;bottom:0;height:120px;display:flex;align-items:center;gap:26px;padding:0 60px;background:var(--navy2);border-top:6px solid var(--accent)}'
            . '.ec-foot img{height:84px;width:auto}.ec-foot__id{flex:1;min-width:0}.ec-foot__id b{display:block;font:900 46px/1 var(--display);text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
            . '.ec-foot__id span{display:block;font:500 26px/1.2 var(--serif);color:var(--mist);margin-top:4px}.ec-url{font:800 40px/1 var(--display);color:var(--accent);letter-spacing:.03em}'
            . '.ec-clock{font:900 54px/1 var(--display);margin-left:20px;padding-left:26px;border-left:3px solid rgba(255,255,255,.25)}'
            . '.ec--vertical .ec-foot{padding:0 36px;gap:18px}.ec--vertical .ec-foot img{height:70px}.ec--vertical .ec-foot__id b{font-size:36px}.ec--vertical .ec-foot__id span{font-size:22px}.ec--vertical .ec-url{font-size:30px}'
            . '@media (prefers-reduced-motion:reduce){.ec-slide{transition:none}.ec-live{animation:none}}';
    }
}
