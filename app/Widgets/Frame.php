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
            // Vague 2 : records, quiz, défi, dernier match, saison, palmarès, frise, bandeau
            . '.w-text a,.w-list a{color:var(--link)}.w-center{text-align:center}.small{font-size:13px}.w-right{float:right;font-family:var(--display)}.w-inline{display:inline;margin:0 6px 0 0}'
            . '.w-rank{list-style:none;margin:6px 0 12px;padding:0;border-top:1px solid var(--line)}.w-rank li{display:grid;grid-template-columns:26px minmax(0,1fr) auto;gap:8px;align-items:center;padding:5px 0;border-bottom:1px solid var(--line)}'
            . '.w-rank__n{font:900 18px/1 var(--display);color:var(--soft);text-align:center}.w-rank li:nth-child(-n+3) .w-rank__n{color:var(--fg)}[data-theme=sombre] .w-rank li:nth-child(-n+3) .w-rank__n{color:var(--accent)}'
            . '.w-rank a{color:var(--fg);text-decoration:none;min-width:0}.w-rank a:hover b{text-decoration:underline}.w-rank b{display:block;font:800 17px/1.1 var(--display);text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
            . '.w-rank small{display:block;font-size:12px;color:var(--soft);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.w-rank__v{font:900 22px/1 var(--display);text-align:right}.w-rank__v small{display:inline;font-size:11px;letter-spacing:.06em;text-transform:uppercase}'
            . '.w-qz__q{font:600 clamp(18px,4.8vw,21px)/1.3 var(--serif);margin:2px 0 12px}.w-qz__a{display:grid;gap:8px;margin:0 0 10px}'
            . '.w-qz__c{display:flex;gap:10px;align-items:center;width:100%;text-align:left;font:15px/1.3 var(--serif);color:var(--fg);background:transparent;border:1.5px solid var(--line);border-radius:10px;padding:9px 12px;cursor:pointer}'
            . '.w-qz__c span{flex:none;display:grid;place-items:center;width:26px;height:26px;border-radius:50%;background:var(--line);font:800 14px/1 var(--display)}'
            . '.w-qz__c:hover:not([disabled]){border-color:var(--accent)}.w-qz__c[disabled]{cursor:default;opacity:.7}.w-qz__c.is-good{background:#1f8a4c;border-color:#1f8a4c;color:#fff;opacity:1}.w-qz__c.is-bad{background:var(--red);border-color:var(--red);color:#fff;opacity:1}'
            . '.w-qz__c.is-good span,.w-qz__c.is-bad span{background:rgba(255,255,255,.25)}.w-qz__c:focus-visible{outline:2px solid var(--accent);outline-offset:2px}'
            . '.w-qz__r{font:800 18px/1.2 var(--display);text-transform:uppercase;letter-spacing:.03em;margin:4px 0 6px}'
            . '.w-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(72px,1fr));gap:6px;margin:10px 0}.w-stats span{border:1.5px solid var(--line);border-radius:8px;padding:7px 4px;text-align:center;font:700 12px/1.15 var(--display);text-transform:uppercase;letter-spacing:.04em;color:var(--soft)}'
            . '.w-stats b{display:block;font:900 28px/1 var(--display);color:var(--fg);letter-spacing:0}.w-stats .is-key{background:var(--accent);border-color:var(--accent);color:var(--on-accent)}.w-stats .is-key b{color:var(--on-accent)}'
            . '.w-match a{display:flex;align-items:center;justify-content:space-between;gap:10px;color:var(--fg);text-decoration:none;margin:8px 0 4px}.w-match span{flex:1;font:800 18px/1.05 var(--display);text-transform:uppercase}.w-match span:last-child{text-align:right}'
            . '.w-match b{font:900 42px/1 var(--display);white-space:nowrap}[data-theme=sombre] .w-match b{color:var(--accent)}'
            . '.w-trophies{display:flex;flex-wrap:wrap;gap:6px;margin:4px 0 8px}.w-trophies span,.w-cups span{background:var(--accent);color:var(--on-accent);border-radius:999px;padding:5px 10px;font:800 13px/1.1 var(--display);text-transform:uppercase;letter-spacing:.04em}.w-trophies a{color:inherit;text-decoration:none}'
            . '.w-list--tight li{padding:4px 0}.w-cups{display:flex;flex-wrap:wrap;gap:6px;margin:6px 0 12px}.w-cups b{margin-right:5px;font-size:15px}'
            . '.w-palm li{font-size:14px}.w-palm b{font:800 16px/1.1 var(--display);text-transform:uppercase}.w-palm__n{display:inline-block;min-width:22px;text-align:center;background:var(--accent);color:var(--on-accent);border-radius:999px;font:800 12px/1.5 var(--display);margin-left:4px}'
            . '.w-palm__y{color:var(--soft);font-size:13px}.w-palm__y a{color:var(--link)}'
            . '@keyframes w-scroll{to{transform:translateX(-50%)}}'
            . '.w-tl{overflow:hidden;margin:0 -16px 10px;-webkit-mask-image:linear-gradient(90deg,transparent,#000 16px,#000 calc(100% - 16px),transparent);mask-image:linear-gradient(90deg,transparent,#000 16px,#000 calc(100% - 16px),transparent)}'
            . '.w-tl__track{display:flex;width:max-content;list-style:none;margin:0;padding:4px 0;animation:w-scroll calc(var(--n,10) * 5s) linear infinite}.w-tl:hover .w-tl__track,.w-tl:focus-within .w-tl__track{animation-play-state:paused}'
            . '.w-tl li{margin-right:10px}.w-tl a{display:block;width:180px;height:136px;overflow:hidden;border:1px solid var(--line);border-top:4px solid var(--accent);border-radius:8px;padding:8px 10px;color:var(--fg);text-decoration:none}'
            . '.w-tl b{display:block;font:900 30px/1 var(--display)}[data-theme=sombre] .w-tl b{color:var(--accent)}.w-tl span{display:block;font:800 15px/1.1 var(--display);text-transform:uppercase;margin:3px 0}.w-tl small{display:block;font-size:12.5px;line-height:1.3;color:var(--soft)}'
            . '.w--bandeau .w-box{flex-direction:row;height:62px;border-radius:10px}.w--bandeau .w-head{display:flex;align-items:center;border-bottom:0;border-right:4px solid var(--accent);padding:0 10px}'
            . '.w--bandeau .w-brand img{width:26px}.w--bandeau .w-main{padding:0;min-width:0;display:flex;align-items:center}'
            . '.w--bandeau .w-foot{border-top:0;border-left:1px solid var(--line);flex-direction:column;justify-content:center;align-items:flex-end;flex-wrap:nowrap;gap:3px;padding:0 10px;font-size:10.5px;white-space:nowrap}.w--bandeau .w-url{font-size:12px}'
            . '@media (max-width:640px){.w--bandeau .w-brand span{display:none}.w--bandeau .w-mention{font-size:9px}}'
            . '.w-tk{display:flex;align-items:center;width:100%;min-width:0;height:100%}.w-tk__live{flex:none;display:flex;align-items:center;gap:6px;padding:0 10px;font:800 13px/1 var(--display);text-transform:uppercase;letter-spacing:.08em;color:var(--red)}'
            . '[data-theme=sombre] .w-tk__live{color:var(--accent)}.w-tk__live i{width:8px;height:8px;border-radius:50%;background:currentColor;animation:w-blink 1.6s ease-in-out infinite}@keyframes w-blink{50%{opacity:.25}}'
            . '.w-tk__vp{flex:1;min-width:0;overflow:hidden;-webkit-mask-image:linear-gradient(90deg,transparent,#000 12px,#000 calc(100% - 12px),transparent);mask-image:linear-gradient(90deg,transparent,#000 12px,#000 calc(100% - 12px),transparent)}'
            . '.w-tk__track{display:flex;width:max-content;animation:w-scroll calc(var(--n,4) * 8s) linear infinite}.w-tk__vp:hover .w-tk__track,.w-tk__vp:focus-within .w-tk__track{animation-play-state:paused}'
            . '.w-tk a{white-space:nowrap;color:var(--fg);text-decoration:none;font-size:15px;padding-left:14px}.w-tk a:hover{text-decoration:underline}.w-tk b{font:800 14px/1 var(--display);text-transform:uppercase;letter-spacing:.06em;color:var(--soft);margin-right:4px}.w-tk a span{color:var(--accent);margin-left:14px}'
            . '@media (prefers-reduced-motion:reduce){.w-tl__track,.w-tk__track,.w-tk__live i{animation:none}.w-tl,.w-tk__vp{overflow-x:auto}}'
            // Vague 3 : histoire du duel, carte des stades, compte à rebours, soutenir, recherche, Rétro-Direct
            . '.w-bar{display:flex;height:12px;border-radius:6px;overflow:hidden;background:var(--line);margin:2px 0 8px}.w-bar i{display:block}.w-bar__v{background:var(--accent)}.w-bar__n{background:var(--soft)}.w-bar__d{background:var(--red)}'
            . '.w-mapbox{display:block;margin:0 0 6px}.w-map{display:block;width:100%;height:auto;max-height:300px;background:var(--line);border-radius:8px}.w-map__land{fill:var(--bg);stroke:var(--soft);stroke-width:.8;stroke-opacity:.6}'
            . '.w-map__pt{fill:var(--accent);fill-opacity:.6;stroke:var(--fg);stroke-width:.8}.w-map__pt.is-home{fill:var(--red);fill-opacity:1;stroke:#fff;stroke-width:2}'
            . '.w-era{display:grid;place-items:center;aspect-ratio:16/9;border-radius:8px;background:var(--head);color:var(--accent);margin:0 0 8px;text-align:center}.w-era b{font:900 84px/1 var(--display)}.w-era span{display:block;font:700 16px/1 var(--display);letter-spacing:.1em;color:var(--on-head)}'
            . '.w-cd{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:10px 0}.w-cd span{border:1.5px solid var(--line);border-radius:10px;padding:10px 4px;text-align:center;font:700 13px/1 var(--display);text-transform:uppercase;letter-spacing:.06em;color:var(--soft)}'
            . '.w-cd b{display:block;font:900 46px/1 var(--display);color:var(--fg);margin-bottom:4px}.w-cd span:first-child{background:var(--accent);border-color:var(--accent);color:var(--on-accent)}.w-cd span:first-child b{color:var(--on-accent)}'
            . '.w-help{list-style:none;margin:8px 0 4px;padding:0;display:grid;gap:12px}.w-help li{display:grid;gap:5px;justify-items:start}.w-btn--ghost{background:transparent;color:var(--link);border:1.5px solid var(--line)}'
            . '.w-search{display:flex;gap:8px;margin:6px 0 10px}.w-search input{flex:1;min-width:0;font:16px/1.2 var(--serif);color:var(--fg);background:transparent;border:1.5px solid var(--line);border-radius:999px;padding:9px 14px}'
            . '.w-search input:focus{outline:2px solid var(--accent);outline-offset:1px}.w-search .w-btn{border:0;cursor:pointer}.w-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}'
            . '.w-live{display:inline-flex;align-items:center;gap:6px;color:var(--red)}[data-theme=sombre] .w-live{color:var(--accent)}.w-live i{width:8px;height:8px;border-radius:50%;background:currentColor;animation:w-blink 1.6s ease-in-out infinite}'
            // Boutique : carte qui se retourne (.w-flip, comme le pied de page) ou grille compacte
            . '.w-shop{list-style:none;margin:4px 0 12px;padding:0;display:grid;gap:10px}.w-shop a{display:flex;flex-direction:column;gap:2px;height:100%;color:var(--fg);text-decoration:none;border:1px solid var(--line);border-top:4px solid var(--accent);border-radius:10px;padding:8px}'
            . '.w-shop a:hover b{text-decoration:underline}.w-shop__img{display:block;aspect-ratio:1;border-radius:6px;overflow:hidden;background:var(--cream);margin-bottom:6px}.w-shop__img img{display:block;width:100%;height:100%;object-fit:contain}'
            . '.w-shop b{font:800 16px/1.1 var(--display);text-transform:uppercase}.w-shop small{font-size:12.5px;color:var(--soft)}.w-shop__price{margin-top:auto;padding-top:4px;font:800 15px/1.1 var(--display);letter-spacing:.03em}[data-theme=sombre] .w-shop__price{color:var(--accent)}'
            . '.w-flip{display:grid;perspective:900px;width:min(100%,240px);margin:6px auto 12px}.w-flip__card{grid-area:1/1;visibility:hidden;display:flex;flex-direction:column;background:var(--navy);color:var(--cream);border:2px solid var(--accent);border-radius:10px;overflow:hidden;text-decoration:none;box-shadow:0 6px 18px rgba(0,0,0,.18)}'
            . '.w-flip__card.is-on{visibility:visible;transform:rotateY(0);transition:transform .28s ease-in}.w-flip.is-turning>.w-flip__card.is-on{transform:rotateY(90deg)}.w-flip>.w-flip__card.is-on:not(:only-child){animation:wFlipIn .28s ease-out}@keyframes wFlipIn{from{transform:rotateY(-90deg)}to{transform:rotateY(0)}}'
            . '.w-flip__img{display:block;aspect-ratio:1;position:relative;background:radial-gradient(circle at 50% 45%,rgba(255,255,255,.25),rgba(255,255,255,.04) 70%);border-bottom:2px solid var(--accent)}.w-flip__img img{position:absolute;inset:10px;width:calc(100% - 20px);height:calc(100% - 20px);object-fit:contain}'
            . '[data-theme=sombre] .w-flip__card{background:var(--navy2)}.w-flip__txt{display:flex;flex-direction:column;gap:2px;padding:9px 12px 12px}.w-flip__txt small{font:700 11px/1.2 var(--display);letter-spacing:.12em;text-transform:uppercase;color:var(--accent)}.w-flip__txt b{font:800 18px/1.1 var(--display);text-transform:uppercase}.w-flip__txt span{font-size:14px;opacity:.9}.w-flip__card:hover b{text-decoration:underline}'
            . '@media (prefers-reduced-motion:reduce){.w-flip__card.is-on{transition:opacity .4s}.w-flip.is-turning>.w-flip__card.is-on{transform:none;opacity:0}.w-flip>.w-flip__card.is-on:not(:only-child){animation:wFade .4s}@keyframes wFade{from{opacity:0}to{opacity:1}}}'
            . '.w-shop--grille{grid-template-columns:repeat(auto-fit,minmax(110px,1fr))}.w-shop--one{grid-auto-flow:row;grid-template-columns:minmax(0,260px);justify-content:center}'
            . '.w--compact .w-media,.w--compact .w-list:not(.w-palm){display:none}.w--compact .w-main{padding:10px 12px 6px}.w--compact .w-big{font-size:56px}'
            . 'a:focus-visible{outline:2px solid var(--accent);outline-offset:2px}';
    }
}
