<?php
declare(strict_types=1);

namespace App\Widgets;

use App\Core\Club;

/**
 * Le cadre commun de tous les widgets (mode iframe) : page HTML autonome, légère, sans cookie,
 * aux couleurs du musée servi (charte du club, polices du site), thème clair ou sombre.
 * Toujours, et sans option pour les retirer : le logo du musée, la mention « non officiel » et
 * le lien vers le musée (adresse du site en petit, cliquable).
 */
final class Frame
{
    /** Remplace chaque « href » (chemin du musée) des données par un lien complet, compté comme clic. */
    public static function finalize(array $d, Context $c): array
    {
        foreach ($d as $k => $v) {
            if (is_array($v)) {
                $d[$k] = self::finalize($v, $c);
            } elseif ($k === 'href' && is_string($v) && str_starts_with($v, '/') && !str_starts_with($v, '//')) {
                $d[$k] = $c->link($v);
            }
        }
        return $d;
    }

    /** Photo du widget avec son crédit (ou rien : pas de photo diffusable). */
    public static function media(?array $img, string $alt): string
    {
        if (!$img) {
            return '';
        }
        return '<figure class="w-media"><img src="' . e($img['url']) . '" alt="' . e($alt) . '" loading="lazy" decoding="async">'
            . ($img['credit'] !== '' ? '<figcaption class="w-credit">© ' . e($img['credit']) . ($img['license'] ?? '' ? ' · ' . e($img['license']) : '') . '</figcaption>' : '')
            . '</figure>';
    }

    /** Mention « non officiel » du widget. */
    public static function mention(Context $c): string
    {
        return $c->tr('Musée indépendant, non officiel', 'Independent, unofficial museum');
    }

    /**
     * Page complète. $body : cœur du widget (null : rien à montrer aujourd'hui) ; $notice : message
     * à la place du widget (accès refusé, widget désactivé…).
     */
    public static function page(Widget $w, ?array $data, Context $c, ?string $notice = null): string
    {
        $lang = $c->en() ? 'en' : 'fr';
        $site = Club::s('site');
        $inner = $notice !== null ? '<p class="w-notice">' . e($notice) . '</p>'
            : ($data === null ? '<p class="w-notice">' . e($c->tr('Rien à montrer pour le moment : revenez bientôt.', 'Nothing to show right now: come back soon.')) . '</p>' : $w->body($data, $c));
        $home = $c->link('/');
        $logo = Context::abs((string) (Club::s('logo_400') ?: Club::s('logo_png')));
        return '<!DOCTYPE html><html lang="' . $lang . '" data-theme="' . e($c->theme) . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
            . '<meta name="referrer" content="strict-origin-when-cross-origin">'
            . '<title>' . e($w->name($lang) . ' · ' . $site) . '</title>'
            . '<link rel="stylesheet" href="' . e(Context::abs(asset('css/fonts.css'))) . '">'
            . '<style>' . self::css() . '</style></head>'
            . '<body class="w w--' . e($w->key()) . ' w--' . e($c->format) . '">'
            . '<article class="w-box">'
            . '<header class="w-head"><a href="' . e($home) . '" target="_blank" rel="noopener" class="w-brand"><img src="' . e($logo) . '" alt="" width="34" height="38"><span>' . e($site) . '</span></a></header>'
            . '<main class="w-main">' . $inner . '</main>'
            . '<footer class="w-foot"><span class="w-mention">' . e(self::mention($c)) . '</span>'
            . '<a class="w-url" href="' . e($home) . '" target="_blank" rel="noopener">' . e(Context::host()) . ' ↗</a></footer>'
            . '</article>'
            . '<script src="' . e(Context::abs(asset('js/widget.js'))) . '" defer></script>'
            . '</body></html>';
    }

    /** Feuille de style du cadre (en ligne : une seule requête, quelques kilo-octets). */
    public static function css(): string
    {
        $c = fn (string $k) => Club::color($k);
        return ':root{--navy:' . $c('navy') . ';--navy2:' . $c('navy-2') . ';--accent:' . $c('yellow') . ';--on-accent:' . $c('navy') . ';--cream:' . $c('cream') . ';--paper:' . $c('paper') . ';--sand:' . $c('sand') . ';--muted:' . $c('muted') . ';--mist:' . $c('mist') . ';--red:' . $c('red') . ';'
            . '--bg:var(--paper);--fg:var(--navy);--soft:var(--muted);--line:var(--sand);--head:var(--navy);--on-head:var(--cream);--link:var(--navy);'
            . "--display:'Big Shoulders Display','Arial Narrow',sans-serif;--serif:'Newsreader',Georgia,serif}"
            . '[data-theme=sombre]{--bg:var(--navy);--fg:var(--cream);--soft:var(--mist);--line:rgba(255,255,255,.16);--head:var(--navy2);--on-head:var(--cream);--link:var(--accent)}'
            . '*{box-sizing:border-box}html,body{margin:0;background:transparent}'
            . 'body{font:16px/1.45 var(--serif);color:var(--fg);-webkit-font-smoothing:antialiased}'
            . '.w-box{background:var(--bg);border:1px solid var(--line);border-radius:14px;overflow:hidden;display:flex;flex-direction:column;max-width:100%}'
            . '.w-head{background:var(--head);border-bottom:4px solid var(--accent);padding:8px 14px}'
            . '.w-brand{display:flex;align-items:center;gap:10px;color:var(--on-head);text-decoration:none;font:800 18px/1 var(--display);text-transform:uppercase;letter-spacing:.04em}'
            . '.w-brand img{width:30px;height:auto;display:block}'
            . '.w-main{padding:14px 16px 10px;flex:1}.w-main>*:first-child{margin-top:0}'
            . '.w-eyebrow{font:700 13px/1.2 var(--display);letter-spacing:.14em;text-transform:uppercase;color:var(--soft);margin:0 0 8px}'
            . '.w-kicker{font:700 14px/1.2 var(--display);letter-spacing:.06em;text-transform:uppercase;margin:10px 0 2px;color:var(--soft)}'
            . '.w-title{font:900 clamp(22px,6vw,28px)/1.02 var(--display);text-transform:uppercase;margin:4px 0 6px}'
            . '.w-title a,.w-list a,.w-who a{color:var(--link);text-decoration:none}.w-title a:hover,.w-list a:hover,.w-who a:hover{text-decoration:underline}'
            . '.w-big{font:900 clamp(56px,18vw,88px)/.9 var(--display);margin:4px 0 6px;color:var(--fg)}.w-big small{font-size:.38em;letter-spacing:.04em;text-transform:uppercase;color:var(--soft)}'
            . '[data-theme=sombre] .w-big{color:var(--accent)}'
            . '.w-text{margin:0 0 8px;font-size:15px}.w-clamp{display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden}'
            . '.w-quote{font:italic 400 clamp(19px,5vw,23px)/1.3 var(--serif);margin:4px 0 10px;border-left:4px solid var(--accent);padding-left:12px}'
            . '.w-media,.w-photo{display:block;margin:0 0 8px}.w-media img,.w-photo img{display:block;width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:8px;background:var(--line)}'
            . '.w-photo img{aspect-ratio:auto;max-height:340px}'
            . '.w-credit{font:12px/1.3 var(--serif);color:var(--soft);margin:4px 0 6px}.w-credit a{color:inherit}'
            . '.w-btn{display:inline-block;background:var(--accent);color:var(--on-accent);font:800 14px/1 var(--display);letter-spacing:.08em;text-transform:uppercase;text-decoration:none;padding:9px 12px;border-radius:999px}'
            . '.w-btn:hover{filter:brightness(1.06)}'
            . '.w-list{list-style:none;margin:8px 0 0;padding:0;border-top:1px solid var(--line)}.w-list li{padding:6px 0;border-bottom:1px solid var(--line);font-size:14px}.w-list b{font-family:var(--display);margin-right:6px}'
            . '.w-res{font:700 12px/1 var(--display);text-transform:uppercase;letter-spacing:.08em;padding:3px 7px;border-radius:999px;background:var(--line);margin-left:6px;vertical-align:2px}'
            . '.w-res--v{background:#1f8a4c;color:#fff}.w-res--d{background:var(--red);color:#fff}'
            . '.w-score{font-family:var(--display);color:var(--soft)}'
            . '.w-tabin{position:absolute;opacity:0;pointer-events:none}.w-tabbar{display:flex;gap:6px;margin:0 0 12px;flex-wrap:wrap}'
            . '.w-tabbar label{cursor:pointer;font:800 14px/1 var(--display);text-transform:uppercase;letter-spacing:.06em;border:1.5px solid var(--line);padding:7px 11px;border-radius:999px}'
            . '.w-tabin:focus-visible+label{outline:2px solid var(--accent)}.w-pane{display:none}'
            . '.w-card{max-width:300px;margin:0 auto;background:linear-gradient(160deg,var(--accent),#fff0 60%),var(--paper);color:var(--navy);border:6px solid var(--navy);border-radius:12px;padding:10px;box-shadow:0 6px 18px rgba(0,0,0,.18)}'
            . '.w-card__top{display:flex;justify-content:space-between;font:800 13px/1 var(--display);text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px}'
            . '.w-card__intl{background:var(--navy);color:var(--accent);padding:3px 6px;border-radius:4px}'
            . '.w-card__photo{display:block;text-decoration:none;aspect-ratio:4/5;border-radius:6px;overflow:hidden;background:var(--navy)}.w-card__photo img{width:100%;height:100%;object-fit:cover;display:block}'
            . '.w-card__ini{display:grid;place-items:center;height:100%;font:900 84px/1 var(--display);color:var(--accent)}'
            . '.w-card .w-credit{color:var(--muted)}'
            . '.w-card__name{background:var(--navy);color:var(--cream);margin:8px -10px 0;padding:8px 12px;text-transform:uppercase;font-family:var(--display)}'
            . '.w-card__name small{display:block;font:700 14px/1 var(--display);letter-spacing:.08em;color:var(--accent)}.w-card__name b{font:900 30px/1 var(--display)}'
            . '.w-card__pos{font:700 14px/1.2 var(--display);text-transform:uppercase;letter-spacing:.06em;margin:8px 0}'
            . '.w-card__stats{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;text-align:center}.w-card__stats span{background:#fff;border:1.5px solid var(--navy);border-radius:6px;padding:5px 2px;font:700 12px/1.1 var(--display);text-transform:uppercase}'
            . '.w-card__stats b{display:block;font:900 24px/1 var(--display)}'
            . '.w-notice{font-size:16px;margin:8px 0 12px}'
            . '.w-foot{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;border-top:1px solid var(--line);padding:8px 14px;font:12px/1.3 var(--serif);color:var(--soft)}'
            . '.w-url{color:var(--link);font:700 13px/1 var(--display);letter-spacing:.04em;text-decoration:none}.w-url:hover{text-decoration:underline}'
            . '.w--compact .w-media,.w--compact .w-list{display:none}.w--compact .w-main{padding:10px 12px 6px}.w--compact .w-big{font-size:56px}'
            . 'a:focus-visible{outline:2px solid var(--accent);outline-offset:2px}';
    }
}
