<?php
declare(strict_types=1);

namespace App\Shop;

use App\Core\Memo;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Auth;
use App\Front\Pages;

/**
 * Boutique en ligne, sur le site du musée (celui qui a le trafic ; le site de l'association y renvoie) : catalogue, personnalisation (choix calibrés,
 * aperçu en direct rendu par le serveur), panier, commande payée par carte (Stripe Checkout),
 * page de suivi de commande avec messages à l'imprimeur. Fermée tant que l'administrateur ne
 * l'ouvre pas (Boutique › Réglages) ; l'équipe connectée au back-office la voit déjà.
 */
final class ShopPages
{
    private const CART = 'shop_cart';

    /** Adresse d'une page de la boutique (site du musée). */
    public static function u(string $path): string
    {
        return url($path);
    }

    /** Ouverte au public, ou visible par l'équipe connectée (essais avant l'ouverture). */
    public static function visible(): bool
    {
        return Orders::open() || Auth::user() !== null;
    }

    private static function closed(): ?Response
    {
        return !self::visible() ? Pages::render('boutique/fermee', [], ['title' => 'Boutique', 'active' => 'boutique', 'styles' => ['css/boutique.css']]) : null;
    }

    private static function page(string $tpl, array $vars, array $page): Response
    {
        self::syncBadge();
        return Pages::render('boutique/' . $tpl, $vars, $page + ['active' => 'boutique', 'styles' => ['css/boutique.css'], 'scripts' => ['js/boutique.js']]);
    }

    private static function notFound(): Response
    {
        return Pages::notFound();
    }

    /** @return list<array> modèles en vente */
    private static function products(): array
    {
        return array_values(array_filter(Catalog::models(), fn ($m) => Catalog::sellable($m)));
    }

    /** Aperçu (SVG) d'un modèle avec les choix du client, sur sa première face dessinée. */
    public static function preview(array $m, array $opts = [], array $values = [], string $face = ''): string
    {
        [$mm] = Catalog::applyOptions($m, $opts);
        $sup = Catalog::support($mm['support']);
        $drawn = array_keys(array_filter($mm['faces'], fn ($f) => $f['layers'] || $f['bg']));
        $fk = in_array($face, $drawn, true) ? $face : ($drawn[0] ?? array_key_first($mm['faces']));
        return Mockup::render($sup['mockup'], (string) $fk, $mm['faces'][$fk], $mm['color'], $values)['svg'];
    }

    /**
     * Aperçu du catalogue en fichier statique (public/assets/boutique/), servi par Apache et gardé
     * par le navigateur : la page ne pèse plus 500 Ko et n'est plus dessinée à chaque visite
     * (130 ms). Nom tiré du modèle, du support, des textes et du code du dessin : tout changement
     * donne un nouveau fichier. Fichiers inutilisés depuis 30 jours effacés de temps en temps.
     */
    public static function previewUrl(array $m, array $values = []): string
    {
        $code = Memo::stamp([__DIR__ . '/Mockup.php', __DIR__ . '/Vector.php', __DIR__ . '/Catalog.php']);
        $key = substr(md5(json_encode([$m, Catalog::support($m['support']), $values, $code])), 0, 16);
        $dir = PUBLIC_PATH . '/assets/boutique';
        $file = $dir . '/' . $key . '.svg';
        $mtime = @filemtime($file);
        if ($mtime === false) {
            @mkdir($dir, 0775, true);
            $tmp = $file . '.' . getmypid() . '.tmp';
            if (@file_put_contents($tmp, self::preview($m, [], $values)) === false || !@rename($tmp, $file)) {
                @unlink($tmp);
                return 'data:image/svg+xml;base64,' . base64_encode(self::preview($m, [], $values));
            }
            if (random_int(1, 50) === 1) {
                foreach (glob($dir . '/*.svg') ?: [] as $f) {
                    if (@filemtime($f) < time() - 30 * 86400) {
                        @unlink($f);
                    }
                }
            }
        } elseif ($mtime < time() - 86400) {
            @touch($file); // encore utilisé : pas effacé par le ménage
        }
        return '/assets/boutique/' . $key . '.svg';
    }

    /**
     * Modèles 3D de l'aperçu (public/assets/3d/, allégés par bin/build-shop3d-models.sh) : tous tirés
     * de Sketchfab sous licence Creative Commons Attribution 4.0, d'où le crédit affiché sous la vue 3D.
     */
    public const MODELS_3D = [
        'mug' => ['file' => 'mug.glb', 'title' => 'CANECA SUBLIMAÇÃO 3D MOCK UP (Sublimation Mug)', 'author' => 'misscanning', 'authorUrl' => 'https://sketchfab.com/misscanning', 'url' => 'https://sketchfab.com/3d-models/caneca-sublimacao-3d-mock-up-sublimation-mug-d0d4a48e5f5e4f1a9044c001007f18e4'],
        'mug-email' => ['file' => 'mug-emaille.glb', 'title' => 'Enamel metal mug', 'author' => 'tab1bit0', 'authorUrl' => 'https://sketchfab.com/tab1bit0', 'url' => 'https://sketchfab.com/3d-models/enamel-metal-mug-669f3a1e56a94be69ba155d6bc08c762'],
        'tote' => ['file' => 'tote.glb', 'title' => 'Batik Beach Tote Bag', 'author' => 'eeelabvisual', 'authorUrl' => 'https://sketchfab.com/eeelabvisual', 'url' => 'https://sketchfab.com/3d-models/batik-beach-tote-bag-12b908ac5af54e79bb0aed793e7c66c7'],
        'tee' => ['file' => 'tshirt.glb', 'title' => 'T Shirt', 'author' => 'funlab117', 'authorUrl' => 'https://sketchfab.com/funlab117', 'url' => 'https://sketchfab.com/3d-models/t-shirt-c1a3e5eb9b5445f4b7d4be82f1127eba'],
        'hoodie' => ['file' => 'sweat.glb', 'title' => 'Hoodie', 'author' => 'Virtual Pandora', 'authorUrl' => 'https://sketchfab.com/virtualpandora', 'url' => 'https://sketchfab.com/3d-models/hoodie-97611a53e3b846f69e0655b210f72b2f'],
        'cap' => ['file' => 'casquette.glb', 'title' => 'Baseball Cap', 'author' => 'jomalon', 'authorUrl' => 'https://sketchfab.com/estebancandiani', 'url' => 'https://sketchfab.com/3d-models/baseball-cap-75d11d363e1c4884a714a776049ea4a0'],
    ];

    /** Clé de forme 3D d'un support (le mug émaillé a son propre modèle). */
    public static function kind3d(array $sup): string
    {
        return $sup['mockup'] === 'mug' && str_contains($sup['key'], 'email') ? 'mug-email' : $sup['mockup'];
    }

    /**
     * Pour l'aperçu 3D : le dessin à plat de chaque face (SVG vectoriel du fichier d'impression,
     * fond transparent sauf fond du modèle), la couleur du produit et la forme 3D à construire.
     */
    public static function flat(array $m, array $opts = [], array $values = []): array
    {
        [$mm] = Catalog::applyOptions($m, $opts);
        $sup = Catalog::support($mm['support']);
        $faces = [];
        foreach ($mm['faces'] as $fk => $f) {
            $drawn = $f['layers'] || $f['bg'];
            $faces[$fk] = ['w' => $f['w'], 'h' => $f['h'], 'bg' => $f['bg'] ? Vector::hex($f['bg']) : '', 'svg' => $drawn ? Vector::svg($f, $values) : ''];
        }
        $kind = self::kind3d($sup);
        $model = isset(self::MODELS_3D[$kind]) ? asset('3d/' . self::MODELS_3D[$kind]['file']) : '';
        return ['kind' => $kind, 'color' => $mm['color'], 'faces' => $faces, 'model' => $model];
    }

    // ------------------------------------------------------------------ catalogue, produit

    public static function index(Request $req): Response
    {
        if ($r = self::closed()) {
            return $r;
        }
        $cards = [];
        foreach (self::products() as $m) {
            $sup = Catalog::support($m['support']);
            $from = $m['sale']['price'] + ($m['sale']['extra'] ? 0 : 0);
            $cards[] = ['m' => $m, 'sup' => $sup, 'img' => self::previewUrl($m, self::samples($m, self::anecStart($m))), 'from' => $from];
        }
        $book = BookShop::sellable() ? BookShop::config() : null;
        return self::page('index', ['book' => $book, 'cards' => $cards, 'config' => Orders::config(), 'count' => self::count()], ['title' => 'Boutique', 'description' => 'Les objets de Sochaux Rétro, personnalisés et fabriqués à la demande près de chez nous. Chaque achat soutient l’association.']);
    }

    public static function product(Request $req, string $id): Response
    {
        if ($r = self::closed()) {
            return $r;
        }
        $m = Catalog::find($id);
        if (!$m || !Catalog::sellable($m)) {
            return self::notFound();
        }
        $sup = Catalog::support($m['support']);
        return self::page('produit', [
            'm' => $m, 'sup' => $sup, 'fields' => Catalog::fields($m), 'positions' => Catalog::positions($m),
            'svg' => self::preview($m, [], self::carnetValues($m, self::samples($m, $anec = self::anecStart($m)))), 'anec' => $anec, 'config' => Orders::config(), 'count' => self::count(),
            'faces' => array_keys(array_filter($m['faces'], fn ($f) => $f['layers'] || $f['bg'])), 'flash' => Session::pull('shop_flash'),
        ], ['title' => $m['name'], 'description' => $m['sale']['desc'] ?: $m['name'] . ' · boutique Sochaux Rétro']);
    }

    /** POST /boutique/apercu/ (JSON) : aperçu et prix pendant que le client choisit. */
    public static function livePreview(Request $req): Response
    {
        if (!RateLimiter::hit('boutique-apercu', $req->ip(), 900, 3600)) {
            return Response::json(['error' => 'Trop de demandes.'], 429);
        }
        $in = $req->json();
        $m = Catalog::find((string) ($in['model'] ?? ''));
        if (!$m || !self::visible() || (!Catalog::sellable($m) && Auth::user() === null)) {
            return Response::json(['error' => 'Article indisponible.'], 404);
        }
        $values = [];
        foreach (Catalog::fields($m) as $k => $f) {
            $v = trim(mb_substr((string) ($in['values'][$k] ?? ''), 0, 200));
            $values[$k] = $v !== '' ? $v : ($k === Anecdotes::FIELD ? Anecdotes::clean($f['default']) : $f['default']);
        }
        $opts = array_map(fn ($v) => mb_substr((string) $v, 0, 20), (array) ($in['opts'] ?? []));
        [$mm, $opt] = Catalog::applyOptions($m, $opts);
        $note = '';
        if (TonMatch::isFor($m) && $opt['date'] === '') {
            $values = self::matchSample($m, $values);
        }
        if (TonMatch::isFor($m) && $opt['date'] !== '') {
            $tm = TonMatch::values($opt['date']);
            $note = TonMatch::note($tm, $opt['date']);
            $values = array_replace($values, array_intersect_key($tm, $values));
        }
        $pf = Poster::fieldOf($m);
        if (Poster::isFor($m) && (string) ($in['values'][$pf] ?? '') !== '' && ($lab = Poster::subject(Poster::kind($m), (string) ($values[$pf] ?? ''))) !== null) {
            $note = ['joueur' => 'Votre joueur : ', 'carnet' => 'Votre ', 'match' => 'Votre match : '][Poster::kind($m)] . $lab . '.';
        }
        $chk = Catalog::check($mm, array_filter(array_map('strval', (array) ($in['values'] ?? []))));
        $size = (string) ($in['size'] ?? '');
        $flat = !empty($in['flat']) ? self::flat($m, $opts, $values) : null;
        return Response::json(['ok' => true, 'svg' => self::preview($m, $opts, $values, (string) ($in['face'] ?? '')), 'flat' => $flat, 'errors' => $chk['errors'], 'note' => $note,
            'price' => Orders::money(Catalog::price($m, $size) * max(1, min(20, (int) ($in['qty'] ?? 1))))]);
    }

    /** GET /boutique/poster/matchs/?q= (JSON) : matchs proposés pour un poster souvenir. */
    public static function posterMatches(Request $req): Response
    {
        if (!self::visible()) {
            return Response::json(['error' => 'Boutique fermée.'], 404);
        }
        if (!RateLimiter::hit('boutique-poster-q', $req->ip(), 120, 60)) {
            return Response::json(['error' => 'Trop de demandes.'], 429);
        }
        $q = mb_substr(trim($req->str('q')), 0, 60);
        return Response::json(['ok' => true, 'items' => mb_strlen($q) >= 2 ? Poster::search($q) : []]);
    }

    /** GET /boutique/poster/joueurs/?q= (JSON) : joueurs proposés pour un poster souvenir. */
    public static function posterPlayers(Request $req): Response
    {
        if (!self::visible()) {
            return Response::json(['error' => 'Boutique fermée.'], 404);
        }
        if (!RateLimiter::hit('boutique-poster-q', $req->ip(), 120, 60)) {
            return Response::json(['error' => 'Trop de demandes.'], 429);
        }
        $q = mb_substr(trim($req->str('q')), 0, 60);
        return Response::json(['ok' => true, 'items' => mb_strlen($q) >= 2 ? PlayerPoster::search($q) : []]);
    }

    /**
     * POST /boutique/poster/preparer/ (JSON {model, subject}) : le musée prépare le contenu du poster
     * de ce match ou de ce joueur (anecdote, citations, récit condensé par l'IA, vérifiés), une fois par sujet.
     */
    public static function posterPrepare(Request $req): Response
    {
        if (!Session::checkCsrf((string) ($_SERVER['HTTP_X_CSRF'] ?? ''))) {
            return Response::json(['error' => 'Rechargez la page.'], 403);
        }
        if (!RateLimiter::hit('boutique-poster-min', $req->ip(), 6, 60) || !RateLimiter::hit('boutique-poster', $req->ip(), 30, 3600)) {
            return Response::json(['error' => 'Doucement : réessayez dans un moment.'], 429);
        }
        $in = $req->json();
        $m = Catalog::find((string) ($in['model'] ?? ''));
        $id = (string) ($in['subject'] ?? $in['match'] ?? '');
        $kind = $m ? Poster::kind($m) : '';
        if (!$m || !self::visible() || !Poster::isFor($m) || (!Catalog::sellable($m) && Auth::user() === null) || !Poster::eligibleFor($kind, $id)) {
            return Response::json(['error' => $kind === 'joueur' ? 'Joueur indisponible.' : 'Match indisponible.'], 404);
        }
        if (Poster::prepared($kind, $id)) {
            return Response::json(['ok' => true, 'ready' => true]);
        }
        // Budget IA du jour (le même que pour les anecdotes) ; sans IA, le poster se passe de ces blocs.
        $budget = (int) Orders::config()['anec_daily'];
        if ($budget <= 0 || RateLimiter::remaining('boutique-poster-ia', 'site', $budget, 86400) <= 0) {
            return Response::json(['ok' => true, 'ready' => false]);
        }
        RateLimiter::hit('boutique-poster-ia', 'site', $budget, 86400);
        Session::release(); // le client peut continuer à composer pendant la préparation
        return Response::json(['ok' => true, 'ready' => Poster::prepare($kind, $id)]);
    }

    /** GET /boutique/anecdote/sujets/?q= (JSON) : joueurs et matchs proposés comme sujet d'anecdote. */
    public static function anecdoteTopics(Request $req): Response
    {
        if (!self::visible()) {
            return Response::json(['error' => 'Boutique fermée.'], 404);
        }
        if (!RateLimiter::hit('boutique-sujets', $req->ip(), 120, 60)) {
            return Response::json(['error' => 'Trop de demandes.'], 429);
        }
        return Response::json(['ok' => true, 'items' => Anecdotes::topics(mb_substr(trim($req->str('q')), 0, 60))]);
    }

    /** POST /boutique/anecdote/ (JSON {model, avoid, topic}) : une anecdote tirée pour ce modèle (sur le sujet choisi), signée. */
    public static function anecdote(Request $req): Response
    {
        // Garde-fous : jeton de la page (pas d'appel direct par un robot), cadence par visiteur,
        // budget IA du jour pour tout le site ; au-delà, la réserve d'anecdotes déjà rédigées (sans IA).
        if (!Session::checkCsrf((string) ($_SERVER['HTTP_X_CSRF'] ?? ''))) {
            return Response::json(['error' => 'Rechargez la page pour tirer une anecdote.'], 403);
        }
        if (!RateLimiter::hit('boutique-anecdote-min', $req->ip(), 6, 60) || !RateLimiter::hit('boutique-anecdote', $req->ip(), 40, 3600)) {
            return Response::json(['error' => 'Doucement : vous avez tiré beaucoup d’anecdotes, réessayez dans un moment.'], 429);
        }
        $in = $req->json();
        $m = Catalog::find((string) ($in['model'] ?? ''));
        if (!$m || !self::visible() || (!Catalog::sellable($m) && Auth::user() === null)) {
            return Response::json(['error' => 'Article indisponible.'], 404);
        }
        [$mm] = Catalog::applyOptions($m, array_map(fn ($v) => mb_substr((string) $v, 0, 20), (array) ($in['opts'] ?? [])));
        $layers = Anecdotes::layers($mm);
        if (!$layers) {
            return Response::json(['error' => 'Ce modèle n’a pas d’anecdote.'], 404);
        }
        $avoid = array_slice(array_map('strval', (array) ($in['avoid'] ?? [])), 0, 30);
        $c = Orders::config();
        $budget = (int) $c['anec_daily'];
        // Le compteur du budget n'avance que si l'IA est vraiment sollicitée (stock épuisé pour ce client).
        $aiAllowed = $budget > 0 && RateLimiter::remaining('boutique-anecdote-ia', 'site', $budget, 86400) > 0;
        // Sujet choisi par le client (un match, un joueur) : seulement un identifiant connu du musée.
        $topic = (string) ($in['topic'] ?? '');
        $topic = $topic !== '' && Anecdotes::topicFact($topic) !== null ? $topic : '';
        Session::release(); // le client peut continuer à composer pendant que l'IA rédige
        $r = Anecdotes::pick($layers, $avoid, $aiAllowed, $topic);
        if (!isset($r['pool']) && !isset($r['error'])) {
            RateLimiter::hit('boutique-anecdote-ia', 'site', $budget, 86400);
        }
        unset($r['pool']);
        return Response::json(isset($r['error']) ? ['ok' => false, 'error' => $r['error']] : ['ok' => true] + $r);
    }

    /** Réponses d'exemple : le texte d'exemple de chaque champ. */
    private static function samples(array $m, ?array $anec = null): array
    {
        $v = array_map(fn ($f) => $f['default'], Catalog::fields($m));
        if (isset($v[Anecdotes::FIELD])) {
            $v[Anecdotes::FIELD] = $anec['text'] ?? Anecdotes::clean($v[Anecdotes::FIELD]);
        }
        return self::matchSample($m, $v);
    }

    /**
     * Poster du carnet : l'aperçu de la fiche produit montre le carnet ouvert sur l'appareil (jamais
     * dans le catalogue, dont les aperçus sont des fichiers publics).
     */
    private static function carnetValues(array $m, array $v): array
    {
        if (Poster::kind($m) === 'carnet' && ($c = \App\Services\Carnet::current()) && CarnetPoster::eligible($c['id'])) {
            $v[CarnetPoster::FIELD] = $c['id'];
        } elseif (CarnetCard::isFor($m) && ($c = \App\Services\Carnet::current()) && CarnetPoster::eligible($c['id'], CarnetCard::MIN)) {
            $v[CarnetPoster::FIELD] = $c['id']; // carte du carnet : celle du client dans l'aperçu
        }
        return $v;
    }

    /** « Ton match » sans date choisie : l'exemple de la finale 1988, jamais les noms des champs. */
    private static function matchSample(array $m, array $v): array
    {
        if (!TonMatch::isFor($m)) {
            return $v;
        }
        // Gardé : sinon 50 à 100 ms à chaque affichage du catalogue (buteurs de tout le musée lus).
        $sample = Memo::get('boutique-ton-match-exemple', [\App\Data\Index::CACHE, __DIR__ . '/TonMatch.php'], \App\Data\Derived::built(), fn () => TonMatch::values('1988-06-11'));
        return array_replace($v, array_intersect_key($sample, $v));
    }

    /** Une anecdote du stock pour ouvrir la fiche (signée : commandable telle quelle), ou null. */
    private static function anecStart(array $m): ?array
    {
        if (!isset(Catalog::fields($m)[Anecdotes::FIELD])) {
            return null;
        }
        $r = Anecdotes::fromPool(Anecdotes::layers($m));
        return isset($r['error']) ? null : $r;
    }

    // ------------------------------------------------------------------ panier

    private static function cart(): array
    {
        return array_values(array_filter((array) Session::get(self::CART, []), 'is_array'));
    }

    private static function count(): int
    {
        return (int) array_sum(array_map(fn ($l) => (int) ($l['qty'] ?? 1), self::cart()));
    }

    /**
     * Nombre d'articles du panier dans un petit cookie lisible par le script du site : la pastille
     * du bouton « Boutique » s'affiche sur toutes les pages, même mises en cache.
     */
    private static function syncBadge(): void
    {
        if (headers_sent()) {
            return;
        }
        $n = self::count();
        if ((string) ($_COOKIE['sr_cart'] ?? '0') === (string) $n) {
            return;
        }
        setcookie('sr_cart', (string) $n, ['expires' => $n ? time() + 30 * 86400 : time() - 3600, 'path' => '/', 'samesite' => 'Lax', 'secure' => (($_SERVER['HTTPS'] ?? '') === 'on')]);
    }

    public static function cartPage(Request $req): Response
    {
        if ($r = self::closed()) {
            return $r;
        }
        $lines = [];
        $keep = [];
        foreach (self::cart() as $in) {
            $r = Orders::line($in);
            if (isset($r['item'])) {
                $m = Catalog::find($in['model']);
                $lines[] = $r['item'] + ['svg' => $in['model'] === BookShop::MODEL ? BookShop::coverSvg($r['item']['values']) : self::preview($m, $r['item']['opts'], $r['item']['values'])];
                $keep[] = $in;
            }
        }
        if (count($keep) !== count(self::cart())) {
            Session::set(self::CART, $keep);
            self::syncBadge();
        }
        $sub = array_sum(array_column($lines, 'total'));
        $promo = null;
        if (($code = (string) Session::get('shop_promo', '')) !== '' && $lines) {
            $promo = Promos::apply($code, $lines);
            if (isset($promo['error'])) {
                Session::forget('shop_promo');
                $promo = null;
            }
        }
        $discount = (int) ($promo['discount'] ?? 0);
        $flash = Session::pull('shop_flash');
        if (!$flash && $req->str('annule') === '1') {
            $flash = ['type' => 'info', 'msg' => 'Paiement annulé : rien n’a été prélevé. Votre panier vous attend.'];
        }
        return self::page('panier', [
            'lines' => $lines, 'sub' => $sub, 'promo' => $promo, 'discount' => $discount,
            'ship' => $lines && Orders::physical($lines) ? (!empty($promo['free_shipping']) ? 0 : Orders::shipping($sub - $discount)) : 0, 'config' => Orders::config(), 'count' => self::count(),
            'flash' => $flash, 'old' => Session::pull('shop_old', []), 'payable' => Orders::payable(), 'test' => \App\Core\Settings::get('donations.mode', 'test') !== 'live',
        ], ['title' => 'Votre panier', 'noindex' => true]);
    }

    /** POST /boutique/panier/ : ajouter (depuis la page produit), changer une quantité, retirer. */
    public static function cartAction(Request $req): Response
    {
        if ($r = self::closed()) {
            return $r;
        }
        if (!Session::checkCsrf((string) ($req->post['_csrf'] ?? ''))) {
            Session::set('shop_flash', ['type' => 'error', 'msg' => 'Votre session a expiré : recommencez.']);
            return Response::redirect(self::u('/boutique/panier/'));
        }
        $cart = self::cart();
        $do = (string) ($req->post['do'] ?? 'add');
        if ($do === 'add' && ($req->post['model'] ?? '') === BookShop::MODEL) {
            $in = ['model' => BookShop::MODEL, 'qty' => (int) ($req->post['qty'] ?? 1), 'values' => array_map(fn ($v) => mb_substr((string) $v, 0, 600), (array) ($req->post['livre'] ?? []))];
            $r = Orders::line($in);
            if (isset($r['error'])) {
                Session::set('shop_flash', ['type' => 'error', 'msg' => $r['error']]);
                Session::set('shop_livre', $in['values']);
                return Response::redirect(self::u('/boutique/livre/'));
            }
            if (count($cart) >= Orders::MAX_ITEMS) {
                Session::set('shop_flash', ['type' => 'error', 'msg' => 'Votre panier est plein (' . Orders::MAX_ITEMS . ' articles).']);
                return Response::redirect(self::u('/boutique/panier/'));
            }
            $cart[] = ['model' => BookShop::MODEL, 'size' => '', 'qty' => $r['item']['qty'], 'values' => $r['item']['values'], 'opts' => []];
            Session::set(self::CART, $cart);
            self::syncBadge();
            Session::set('shop_flash', ['type' => 'ok', 'msg' => '« ' . BookShop::NAME . ' » ajouté au panier.']);
            return Response::redirect(self::u('/boutique/panier/'));
        }
        if ($do === 'add') {
            $in = ['model' => (string) ($req->post['model'] ?? ''), 'size' => (string) ($req->post['size'] ?? ''), 'qty' => (int) ($req->post['qty'] ?? 1),
                'values' => array_map(fn ($v) => mb_substr((string) $v, 0, 200), (array) ($req->post['values'] ?? [])),
                'opts' => array_map(fn ($v) => mb_substr((string) $v, 0, 20), (array) ($req->post['opts'] ?? []))];
            $r = Orders::line($in);
            if (isset($r['error'])) {
                Session::set('shop_flash', ['type' => 'error', 'msg' => $r['error']]);
                return Response::redirect(self::u('/boutique/' . rawurlencode($in['model']) . '/'));
            }
            if (count($cart) >= Orders::MAX_ITEMS) {
                Session::set('shop_flash', ['type' => 'error', 'msg' => 'Votre panier est plein (' . Orders::MAX_ITEMS . ' articles).']);
                return Response::redirect(self::u('/boutique/panier/'));
            }
            $cart[] = ['model' => $r['item']['model'], 'size' => $r['item']['size'], 'qty' => $r['item']['qty'], 'values' => $r['item']['values'], 'opts' => $r['item']['opts']];
            Session::set(self::CART, $cart);
            self::syncBadge();
            Session::set('shop_flash', ['type' => 'ok', 'msg' => '« ' . $r['item']['name'] . ' » ajouté au panier.']);
            return Response::redirect(self::u('/boutique/panier/'));
        }
        if ($do === 'promo') {
            $code = Promos::code((string) ($req->post['code'] ?? ''));
            $items = array_values(array_filter(array_map(fn ($in) => Orders::line($in)['item'] ?? null, $cart)));
            $r = $code !== '' ? Promos::apply($code, $items) : ['error' => 'Saisissez un code promo.'];
            if (!RateLimiter::hit('boutique-promo', $req->ip(), 30, 3600)) {
                $r = ['error' => 'Trop d’essais : réessayez dans une heure.'];
            }
            if (isset($r['error'])) {
                Session::set('shop_flash', ['type' => 'error', 'msg' => $r['error']]);
            } else {
                Session::set('shop_promo', $r['code']);
                Session::set('shop_flash', ['type' => 'ok', 'msg' => 'Code ' . $r['code'] . ' appliqué : ' . $r['label'] . '.']);
            }
            return Response::redirect(self::u('/boutique/panier/'));
        }
        if ($do === 'unpromo') {
            Session::forget('shop_promo');
            return Response::redirect(self::u('/boutique/panier/'));
        }
        $i = (int) ($req->post['line'] ?? -1);
        if (isset($cart[$i])) {
            if ($do === 'remove') {
                array_splice($cart, $i, 1);
            } elseif ($do === 'qty') {
                $cart[$i]['qty'] = max(1, min(($cart[$i]['model'] ?? '') === BookShop::MODEL ? 5 : 20, (int) ($req->post['qty'] ?? 1)));
            }
            Session::set(self::CART, $cart);
            self::syncBadge();
        }
        return Response::redirect(self::u('/boutique/panier/'));
    }

    /** POST /boutique/commander/ : coordonnées, commande, puis paiement chez Stripe. */
    public static function checkout(Request $req): Response
    {
        if ($r = self::closed()) {
            return $r;
        }
        $back = self::u('/boutique/panier/') . '#commander';
        $p = $req->post;
        Session::set('shop_old', array_intersect_key(array_map(fn ($v) => is_string($v) ? mb_substr($v, 0, 160) : '', $p), array_flip(['name', 'email', 'phone', 'line1', 'line2', 'zip', 'city', 'country'])));
        $err = null;
        if (!Session::checkCsrf((string) ($p['_csrf'] ?? ''))) {
            $err = 'Votre session a expiré : renvoyez le formulaire.';
        } elseif (trim((string) ($p['website'] ?? '')) !== '') {
            $err = 'Envoi refusé.';
        } elseif (($age = form_ts_age((string) ($p['_ts'] ?? ''))) === null || $age < 3) {
            $err = 'Merci de prendre le temps de remplir le formulaire.';
        } elseif (!RateLimiter::hit('vt-boutique', $req->ip(), 20, 3600)) {
            $err = 'Trop de commandes depuis votre connexion : réessayez dans une heure.';
        } elseif (($p['cgv'] ?? '') !== '1') {
            $err = 'Merci d’accepter les conditions de vente.';
        } elseif (!Orders::payable()) {
            $err = 'Le paiement en ligne n’est pas encore ouvert.';
        }
        if ($err) {
            Session::set('shop_flash', ['type' => 'error', 'msg' => $err]);
            return Response::redirect($back);
        }
        $r = Orders::create(self::cart(), $p, (string) Session::get('shop_promo', ''));
        if (isset($r['error'])) {
            Session::set('shop_flash', ['type' => 'error', 'msg' => $r['error']]);
            return Response::redirect($back);
        }
        $base = base_url();
        $pay = Orders::checkout($r['order'], $base);
        if (isset($pay['error'])) {
            Session::set('shop_flash', ['type' => 'error', 'msg' => $pay['error']]);
            return Response::redirect($back);
        }
        Session::forget('shop_old');
        Session::forget('shop_promo');
        Session::set('shop_pending', $r['order']['id']);
        return Response::redirect($pay['url']);
    }


    // ------------------------------------------------------------------ livre « 100 récits du Lion »

    /** GET /boutique/livre/ : page du livre, personnalisation et aperçu de la couverture. */
    public static function book(Request $req): Response
    {
        if ($r = self::closed()) {
            return $r;
        }
        if (!BookShop::sellable()) {
            return self::notFound();
        }
        $covers = [];
        foreach (\App\Pdf\Livre::offeredCovers() as $rel) {
            $covers[] = ['rel' => $rel, 'caption' => \App\Data\Media::caption($rel)];
        }
        $carnet = \App\Services\Carnet::current();
        return self::page('livre', [
            'config' => Orders::config(), 'book' => BookShop::config(), 'count' => self::count(), 'covers' => $covers,
            'jerseys' => \App\Pdf\Livre::jerseysValid(), 'carnet' => $carnet && !empty($carnet['matches']) ? $carnet : null,
            'old' => (array) Session::pull('shop_livre', []), 'flash' => Session::pull('shop_flash'),
        ], ['title' => BookShop::NAME, 'description' => BookShop::config()['desc'], 'scripts' => ['js/boutique.js', 'js/livre.js']]);
    }

    /** POST /boutique/livre/fichier/ (JSON) : photo du lecteur ou photo 3D du maillot. */
    public static function bookUpload(Request $req): Response
    {
        $json = fn (array $d, int $st = 200) => new Response(json_encode($d, JSON_UNESCAPED_UNICODE), $st, ['Content-Type' => 'application/json; charset=UTF-8']);
        if (!self::visible() || !BookShop::sellable() || !Session::checkCsrf((string) ($req->post['_csrf'] ?? ''))) {
            return $json(['error' => 'Session expirée : rechargez la page.'], 403);
        }
        if (!RateLimiter::hit('livre-envoi', $req->ip(), 40, 3600)) {
            return $json(['error' => 'Trop d’envois : réessayez dans une heure.'], 429);
        }
        $f = $req->files['file'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) $f['size'] > 20 * 1024 * 1024) {
            return $json(['error' => 'Fichier non reçu (20 Mo au plus).'], 422);
        }
        $r = BookShop::receive((string) $f['tmp_name'], (string) ($req->post['kind'] ?? 'photo') === 'photo' ? 'photo' : 'maillot');
        return $json($r, isset($r['error']) ? 422 : 200);
    }

    /** POST /boutique/livre/extrait/ : extrait à feuilleter (PDF de quelques pages) avec les choix du client. */
    public static function bookExcerpt(Request $req): Response
    {
        if (!self::visible() || !BookShop::sellable() || !Session::checkCsrf((string) ($req->post['_csrf'] ?? ''))) {
            return new Response('Session expirée : rechargez la page.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        if (!RateLimiter::hit('livre-extrait', $req->ip(), 20, 3600)) {
            return new Response('Trop d’extraits demandés : réessayez dans une heure.', 429, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        $in = array_map(fn ($v) => mb_substr((string) $v, 0, 600), (array) ($req->post['livre'] ?? []));
        if (trim((string) ($in['nom'] ?? '')) === '') {
            $in['nom'] = 'Votre nom';
        }
        $r = BookShop::clean($in);
        @set_time_limit(180);
        @ini_set('memory_limit', '512M');
        $pdf = (new \App\Pdf\Livre(BookShop::options($r['values'] ?? [], 'XXXX') + ['apercu' => true]))->build();
        return new Response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="extrait-100-recits-du-lion.pdf"', 'Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex']);
    }

    // ------------------------------------------------------------------ suivi de commande

    public static function track(Request $req, string $token): Response
    {
        $o = Orders::byToken($token);
        if (!$o) {
            return self::notFound();
        }
        if ($o['status'] === 'pending' && ($sid = $req->str('session_id')) !== '') {
            Orders::syncReturn($o, $sid);
            $o = Orders::get($o['id']);
        }
        // Commande payée : le panier est vidé.
        if ($o['status'] !== 'pending' && Session::get('shop_pending') === $o['id']) {
            Session::forget(self::CART);
            self::syncBadge();
            Session::forget('shop_pending');
        }
        $previews = [];
        foreach ($o['items'] as $it) {
            $m = Catalog::find($it['model']);
            $previews[] = $it['model'] === BookShop::MODEL ? BookShop::coverSvg($it['values']) : ($m ? self::preview($m, $it['opts'], $it['values']) : '');
        }
        return self::page('suivi', ['o' => $o, 'previews' => $previews, 'flash' => Session::pull('shop_flash'), 'config' => Orders::config(), 'count' => self::count()],
            ['title' => 'Commande ' . $o['id'], 'noindex' => true]);
    }

    /** GET /boutique/commande/{jeton}/livre/{n}/ : livre numérique payé, au plus BookShop::MAX_DOWNLOADS téléchargements. */
    public static function bookDownload(Request $req, string $token, string $n): Response
    {
        $o = Orders::byToken($token);
        $i = (int) $n - 1;
        $it = $o['items'][$i] ?? null;
        if (!$o || !$it || !BookShop::digital($it) || !in_array($o['status'], ['paid', 'production', 'shipped', 'delivered'], true)) {
            return self::notFound();
        }
        if ((int) ($o['ext']['downloads'][$i] ?? 0) >= BookShop::MAX_DOWNLOADS) {
            Session::set('shop_flash', ['type' => 'error', 'msg' => 'Vous avez utilisé vos ' . BookShop::MAX_DOWNLOADS . ' téléchargements. Besoin d’un nouveau téléchargement ? Écrivez au musée ci-dessous, nous vous répondons rapidement.']);
            return Response::redirect(self::u('/boutique/commande/' . $token . '/') . '#messages');
        }
        @set_time_limit(600);
        $pdf = Orders::pdf($o, $i);
        if ($pdf === null) {
            return new Response('Votre livre est en cours de composition : réessayez dans quelques minutes.', 503, ['Content-Type' => 'text/plain; charset=UTF-8', 'Retry-After' => '120']);
        }
        // Compté seulement quand le fichier part (pas pendant la composition).
        Orders::update($o['id'], function ($x) use ($i) {
            $x['ext']['downloads'][$i] = (int) ($x['ext']['downloads'][$i] ?? 0) + 1;
            return $x;
        });
        return new Response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="100-recits-du-lion-' . slugify((string) ($it['values']['nom'] ?? 'livre')) . '.pdf"', 'Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex']);
    }

    public static function trackMessage(Request $req, string $token): Response
    {
        $o = Orders::byToken($token);
        if (!$o) {
            return self::notFound();
        }
        $back = self::u('/boutique/commande/' . $token . '/') . '#messages';
        if (!Session::checkCsrf((string) ($req->post['_csrf'] ?? '')) || trim((string) ($req->post['website'] ?? '')) !== '') {
            Session::set('shop_flash', ['type' => 'error', 'msg' => 'Votre session a expiré : renvoyez le message.']);
        } elseif (!RateLimiter::hit('vt-boutique-msg', $req->ip(), 10, 3600)) {
            Session::set('shop_flash', ['type' => 'error', 'msg' => 'Trop de messages : réessayez dans une heure.']);
        } elseif (Orders::message($o['id'], 'client', (string) ($req->post['text'] ?? ''))) {
            Session::set('shop_flash', ['type' => 'ok', 'msg' => 'Message envoyé : l’imprimeur vous répond par e-mail et sur cette page.']);
        }
        return Response::redirect($back);
    }
}
