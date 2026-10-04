<?php
declare(strict_types=1);

namespace App\Vitrine;

use App\Core\JsonStore;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Services\Mailer;

/**
 * Formulaires du site de l'association : adhésion, bénévolat, contact, newsletter.
 * Antispam (comme sur le musée) : jeton CSRF, champ piège, durée minimale de saisie signée par
 * le serveur, limites d'envois par adresse IP. Les messages rejoignent la boîte du back-office
 * (Communauté › Messages), avec l'étiquette « Site de l'association ».
 */
final class Forms
{
    public const VOLUNTEERS = STORAGE_PATH . '/vitrine/benevoles.json';

    /** Objets du formulaire de contact. */
    public const REASONS = [
        'question' => 'Une question',
        'adhesion' => 'Adhésion',
        'benevolat' => 'Bénévolat',
        'partenariat' => 'Partenariat',
        'presse' => 'Presse',
        'archive' => 'Confier une archive',
        'evenement' => 'Proposer un événement',
        'apres-midi-bonal' => 'Organiser un Après-midi Bonal',
        'documents' => 'Statuts et documents',
        'autre' => 'Autre demande',
    ];

    public const AVAILABILITY = ['ponctuel' => 'Ponctuellement', 'mois' => 'Quelques heures par mois', 'semaine' => 'Quelques heures par semaine', 'evenements' => 'Pour les événements'];
    public const VOLUNTEER_STATUS = ['nouveau' => 'Nouvelle', 'contacte' => 'Contacté', 'actif' => 'Bénévole actif', 'archive' => 'Classée'];

    /** Adhésion en ligne possible (moyens de paiement réglés et activés). */
    public static function onlineMembership(): bool
    {
        return Membership::methods() !== [];
    }

    // ------------------------------------------------------------------ adhésion

    public static function membership(Request $req): Response
    {
        $p = Content::page('adherer');
        $flash = Session::pull('vt_flash_adh');
        if ($req->str('annule') === '1' && !$flash) {
            $flash = ['type' => 'info', 'msg' => 'Paiement annulé : aucun montant n’a été prélevé. Vous pouvez réessayer, ou choisir le règlement par chèque.'];
        }
        return Site::render('adherer', [
            'p' => $p,
            'tariffs' => Content::tariffs(),
            'methods' => Membership::methods(),
            'helloasso' => self::helloasso(),
            'address' => Site::address(),
            'flash' => $flash,
            'old' => Session::pull('vt_old_adh', []),
            'test' => Settings::get('donations.mode', 'test') !== 'live',
        ], ['title' => $p['title'], 'description' => $p['lead'], 'active' => 'soutenir', 'body_class' => 'vt-form-page']);
    }

    public static function membershipSend(Request $req): Response
    {
        $back = Host::url('/nous-soutenir/adherer/') . '#formulaire';
        $f = fn (string $k, int $max = 160) => trim((string) preg_replace('/\s+/u', ' ', strip_tags(mb_substr((string) ($req->post[$k] ?? ''), 0, $max))));
        $tariff = Content::tariff($f('tariff', 40));
        $methods = Membership::methods();
        $method = $f('method', 20);
        if (!isset($methods[$method])) {
            $method = 'cheque';
        }
        $amount = $tariff ? (int) $tariff['amount'] : 0;
        if ($tariff && !empty($tariff['free'])) {
            $amount = max($amount, (int) preg_replace('/\D/', '', $f('amount', 8)));
        }
        $m = [
            'first' => $f('first', 60),
            'last' => $f('last', 80),
            'email' => mb_strtolower($f('email', 160)),
            'phone' => $f('phone', 30),
            'address' => $f('address', 160),
            'zip' => $f('zip', 12),
            'city' => $f('city', 80),
            'family' => $f('family', 300),
        ];
        $err = self::guard($req, 'adhesion', 8) ?? match (true) {
            !$tariff => 'Choisissez une formule d’adhésion.',
            $amount > 5000 => 'Pour un soutien de plus de 5 000 €, contactez-nous : nous vous proposerons un virement.',
            $m['first'] === '' || $m['last'] === '' => 'Merci d’indiquer votre prénom et votre nom.',
            !filter_var($m['email'], FILTER_VALIDATE_EMAIL) => 'Merci d’indiquer une adresse e-mail valide.',
            empty($req->post['consent']) => 'Merci d’accepter l’utilisation de vos coordonnées pour la gestion de votre adhésion.',
            default => null,
        };
        if ($err) {
            Session::set('vt_flash_adh', ['type' => 'error', 'msg' => $err]);
            Session::set('vt_old_adh', array_intersect_key($req->post, array_flip(['tariff', 'amount', 'first', 'last', 'email', 'phone', 'address', 'zip', 'city', 'family', 'method', 'source', 'newsletter'])));
            return Response::redirect($back, 303);
        }
        $online = $method !== 'cheque';
        $a = [
            'id' => Membership::newId(),
            'created' => date('c'),
            'updated' => date('c'),
            'year' => (int) date('Y'),
            'tariff' => $tariff['key'],
            'label' => (string) $tariff['label'],
            'amount' => $amount * 100,
            'status' => $online ? 'pending' : 'offline',
            'provider' => $method,
            'mode' => Settings::get('donations.mode', 'test') === 'live' ? 'live' : 'test',
            'member' => $m,
            'newsletter' => !empty($req->post['newsletter']),
            'source' => $f('source', 200),
            'ext' => [],
            'payments' => [],
            'ip' => ip_hash($req->ip()),
            'origin' => Site::$preview ? 'apercu' : 'site',
        ];
        Membership::put($a);
        Session::set('vt_adhesions', array_slice(array_merge((array) Session::get('vt_adhesions', []), [$a['id']]), -5));
        if ($a['newsletter']) {
            self::subscribe($m['email'], $req);
        }
        if (!$online) {
            Membership::notifyTeam($a, 'Nouvelle adhésion (règlement par chèque à recevoir)');
            return Response::redirect(Host::url('/nous-soutenir/adherer/merci/') . '?a=' . $a['id'], 303);
        }
        $r = Membership::checkout($a);
        if (isset($r['url'])) {
            return Response::redirect($r['url'], 303);
        }
        Session::set('vt_flash_adh', ['type' => 'error', 'msg' => $r['error'] ?? 'Paiement impossible pour le moment.']);
        return Response::redirect($back, 303);
    }

    /** Retour du paiement (ou envoi par chèque) : récapitulatif, au seul navigateur qui a rempli le formulaire. */
    public static function membershipThanks(Request $req): Response
    {
        $id = $req->str('a');
        $a = Membership::validId($id) ? Membership::get($id) : null;
        if ($a && in_array($id, (array) Session::get('vt_adhesions', []), true)) {
            if ($a['status'] === 'pending') {
                Membership::syncReturn($a, $req->str('session_id'), $req->str('pp'), $req->str('token'));
                $a = Membership::get($id);
            }
        } else {
            $a = null;
        }
        return Site::render('adherer-merci', ['a' => $a, 'address' => Site::address()], ['title' => 'Merci pour votre adhésion', 'noindex' => true, 'active' => 'soutenir']);
    }

    /** Bulletin d'adhésion à imprimer. */
    public static function membershipPaper(Request $req): Response
    {
        return Response::html(\App\Core\View::render('vitrine/bulletin', ['tariffs' => Content::tariffs(), 'address' => Site::address(), 'p' => Content::page('adherer')]));
    }

    private static function helloasso(): string
    {
        $u = trim((string) Settings::get('vitrine.helloasso', ''));
        return preg_match('#^https://(www\.)?helloasso\.com/#i', $u) ? $u : '';
    }

    // ------------------------------------------------------------------ bénévolat

    public static function volunteer(Request $req): Response
    {
        $p = Content::page('benevolat');
        return Site::render('benevolat', [
            'p' => $p,
            'poles' => Content::poles(),
            'flash' => Session::pull('vt_flash_ben'),
            'old' => Session::pull('vt_old_ben', []),
        ], ['title' => $p['title'], 'description' => $p['lead'], 'image' => Content::hasImage($p['image'] ?? null) ? img((string) $p['image'], 1200) : '', 'active' => 'soutenir', 'body_class' => 'vt-form-page']);
    }

    public static function volunteerSend(Request $req): Response
    {
        $back = Host::url('/nous-soutenir/benevolat/') . '#formulaire';
        $f = fn (string $k, int $max = 160) => trim(mb_substr((string) ($req->post[$k] ?? ''), 0, $max));
        $poles = array_column(Content::poles(), 'title', 'key');
        $d = [
            'first' => $f('first', 60),
            'last' => $f('last', 80),
            'email' => mb_strtolower($f('email', 160)),
            'phone' => $f('phone', 30),
            'city' => $f('city', 80),
            'poles' => array_values(array_intersect(array_keys($poles), array_map('strval', (array) ($req->post['poles'] ?? [])))),
            'skills' => $f('skills', 500),
            'availability' => isset(self::AVAILABILITY[$f('availability', 20)]) ? $f('availability', 20) : '',
            'message' => $f('message', 4000),
        ];
        $err = self::guard($req, 'benevolat', 5) ?? match (true) {
            $d['first'] === '' => 'Merci d’indiquer votre prénom.',
            !filter_var($d['email'], FILTER_VALIDATE_EMAIL) => 'Merci d’indiquer une adresse e-mail valide.',
            !$d['poles'] && mb_strlen($d['message']) < 5 && $d['skills'] === '' => 'Dites-nous en quelques mots ce qui vous plairait.',
            substr_count(mb_strtolower($d['message'] . $d['skills']), 'http') > 3 => 'Votre message contient trop de liens.',
            empty($req->post['consent']) => 'Merci d’accepter que nous utilisions vos coordonnées pour vous recontacter.',
            default => null,
        };
        if ($err) {
            Session::set('vt_flash_ben', ['type' => 'error', 'msg' => $err]);
            Session::set('vt_old_ben', $d);
            return Response::redirect($back, 303);
        }
        $id = 'B' . date('ymd') . '-' . bin2hex(random_bytes(3));
        JsonStore::update(self::VOLUNTEERS, function ($all) use ($id, $d, $req) {
            $all = $all ?: [];
            $all[$id] = $d + ['id' => $id, 'at' => date('c'), 'status' => 'nouveau', 'ip' => ip_hash($req->ip()), 'origin' => Site::$preview ? 'apercu' : 'site'];
            return $all;
        }, []);
        if ($to = Site::email()) {
            Mailer::send($to, '[Bénévolat] ' . trim($d['first'] . ' ' . $d['last']),
                '<p><b>Nouvelle proposition de bénévolat</b></p><p>' . e(trim($d['first'] . ' ' . $d['last'])) . ' · ' . e($d['email']) . ($d['phone'] ? ' · ' . e($d['phone']) : '') . ($d['city'] ? ' · ' . e($d['city']) : '') . '</p>'
                . '<p>' . e(implode(', ', array_map(fn ($k) => $poles[$k] ?? $k, $d['poles']))) . '</p><p>' . nl2br(e($d['message'])) . '</p>'
                . '<p><a href="' . e(base_url()) . '/admin/association/benevoles">Ouvrir dans le back-office</a></p>', $d['email']);
        }
        Mailer::send($d['email'], 'Merci pour votre proposition d’aide !',
            '<p>Bonjour ' . e($d['first']) . ',</p><p>Merci de vouloir donner un peu de votre temps à ' . e(Site::name()) . ' ! Un membre de l’équipe vous recontacte très vite.</p><p>L’équipe de ' . e(Site::name()) . '</p>');
        Session::set('vt_flash_ben', ['type' => 'ok', 'msg' => 'Merci ' . $d['first'] . ' ! Votre proposition est bien arrivée : un membre de l’équipe vous recontacte très vite.']);
        return Response::redirect($back, 303);
    }

    /** Propositions de bénévolat restées sans suite : effacées après 2 ans (RGPD). */
    public static function purgeVolunteers(): int
    {
        $n = 0;
        JsonStore::update(self::VOLUNTEERS, function ($all) use (&$n) {
            $all = $all ?: [];
            foreach ($all as $id => $v) {
                if (($v['status'] ?? '') !== 'actif' && strtotime((string) ($v['at'] ?? 'now')) < time() - 2 * 365 * 86400) {
                    unset($all[$id]);
                    $n++;
                }
            }
            return $all;
        }, []);
        return $n;
    }

    // ------------------------------------------------------------------ contact

    public static function contact(Request $req): Response
    {
        $p = Content::page('contact');
        $reason = isset(self::REASONS[$req->str('objet')]) ? $req->str('objet') : 'question';
        return Site::render('contact', [
            'p' => $p,
            'reason' => $reason,
            'flash' => Session::pull('vt_flash_contact'),
            'old' => Session::pull('vt_old_contact', []),
            'email' => Site::email(),
            'phone' => trim((string) Settings::get('vitrine.phone', '')),
            'address' => Site::address(),
        ], ['title' => $p['title'], 'description' => $p['lead'], 'active' => 'contact', 'body_class' => 'vt-form-page', 'jsonld' => Site::organizationLd()]);
    }

    public static function contactSend(Request $req): Response
    {
        $f = fn (string $k, int $max = 200) => trim(mb_substr((string) ($req->post[$k] ?? ''), 0, $max));
        $data = [
            'reason' => isset(self::REASONS[$f('reason', 30)]) ? $f('reason', 30) : 'autre',
            'name' => $f('name', 120),
            'email' => $f('email', 160),
            'org' => $f('org', 160),
            'phone' => $f('phone', 40),
            'message' => $f('message', 8000),
            'page' => 'Site de l’association',
        ];
        $back = Host::url('/contact/') . '?objet=' . $data['reason'] . '#formulaire';
        $err = self::guard($req, 'contact', 5) ?? match (true) {
            $data['name'] === '' => 'Merci d’indiquer votre nom.',
            !filter_var($data['email'], FILTER_VALIDATE_EMAIL) => 'Merci d’indiquer une adresse e-mail valide.',
            mb_strlen($data['message']) < 10 => 'Votre message est un peu court.',
            substr_count(mb_strtolower($data['message']), 'http') > 4 => 'Votre message contient trop de liens.',
            empty($req->post['consent']) => 'Merci d’accepter que nous utilisions vos coordonnées pour vous répondre.',
            default => null,
        };
        if ($err) {
            Session::set('vt_flash_contact', ['type' => 'error', 'msg' => $err]);
            Session::set('vt_old_contact', $data);
            return Response::redirect($back, 303);
        }
        $id = date('YmdHis') . '-' . bin2hex(random_bytes(3));
        JsonStore::write(\App\Front\Community::INBOX . "/messages/$id.json", $data + ['id' => $id, 'at' => date('c'), 'status' => 'nouveau', 'lang' => 'fr', 'site' => 'association', 'origin' => Site::$preview ? 'apercu' : 'site']);
        if ($to = Site::email()) {
            Mailer::send($to, '[Association] ' . self::REASONS[$data['reason']] . ' · ' . $data['name'],
                '<p><b>' . e(self::REASONS[$data['reason']]) . '</b> (site de l’association)</p><p>' . e($data['name']) . ($data['org'] ? ' · ' . e($data['org']) : '') . '<br>' . e($data['email']) . ($data['phone'] ? ' · ' . e($data['phone']) : '') . '</p><p>' . nl2br(e($data['message'])) . '</p><p><a href="' . e(base_url()) . '/admin/messages/' . e($id) . '">Ouvrir dans le back-office</a></p>',
                $data['email']);
        }
        Session::set('vt_flash_contact', ['type' => 'ok', 'msg' => 'Message envoyé ✓ Un bénévole vous répond dans les meilleurs délais.']);
        return Response::redirect($back, 303);
    }

    // ------------------------------------------------------------------ newsletter

    /** Messages de la newsletter (code dans l'adresse de retour : pas de session pour ce formulaire). */
    public const NL_MESSAGES = [
        'ok' => ['ok', 'Presque fini ! Confirmez votre inscription grâce au lien reçu par e-mail.'],
        'deja' => ['ok', 'Vous êtes déjà inscrit : merci !'],
        'email' => ['error', 'Adresse e-mail invalide.'],
        'vite' => ['error', 'Merci de prendre le temps de remplir le formulaire.'],
        'limite' => ['error', 'Trop de demandes depuis votre connexion : réessayez plus tard.'],
        'erreur' => ['error', 'Inscription impossible pour le moment : réessayez plus tard.'],
    ];

    /**
     * Inscription à la newsletter « Ce jour-là » du musée (double validation par e-mail).
     * Formulaire présent sur toutes les pages : sans session (ni cookie) ; protégé par le champ
     * piège, l'horodatage signé par le serveur et la limite d'envois par adresse IP.
     */
    public static function newsletter(Request $req): Response
    {
        $backPath = (string) ($req->post['back'] ?? '/');
        if (!preg_match('#^/[a-z0-9/_-]*$#', $backPath)) {
            $backPath = '/';
        }
        $anchor = preg_match('/^nl-[a-z]{2,10}$/', (string) ($req->post['anchor'] ?? '')) ? (string) $req->post['anchor'] : 'nl-foot';
        $age = form_ts_age((string) ($req->post['_ts'] ?? ''));
        $code = match (true) {
            trim((string) ($req->post['website'] ?? '')) !== '' => 'ok', // robot : on ne dit rien
            $age === null || $age < 2 || $age > 172800 => 'vite',
            !RateLimiter::hit('vt-newsletter', $req->ip(), 10, 3600) => 'limite',
            default => self::subscribe(mb_strtolower(trim((string) ($req->post['email'] ?? ''))), $req),
        };
        return Response::redirect(Host::url($backPath) . '?nl=' . $code . '&f=' . $anchor . '#' . $anchor, 303);
    }

    /** Inscription (newsletter du musée) : code de NL_MESSAGES. */
    private static function subscribe(string $email, Request $req): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        $r = \App\Front\Community::newsletterApi(new Request('POST', '/api/newsletter', [], ['email' => $email], [], $req->server, ''));
        $d = json_decode($r->body, true) ?: [];
        return match (true) {
            !empty($d['ok']) => str_contains((string) ($d['message'] ?? ''), 'déjà') ? 'deja' : 'ok',
            $r->status === 422 => 'email',
            $r->status === 429 => 'limite',
            default => 'erreur',
        };
    }

    // ------------------------------------------------------------------ antispam

    /** Contrôles communs : CSRF, champ piège, durée de saisie, limite par IP. Message d'erreur ou null. */
    private static function guard(Request $req, string $bucket, int $perHour, int $minSeconds = 3): ?string
    {
        if (!Session::checkCsrf((string) ($req->post['_csrf'] ?? ''))) {
            return 'Votre session a expiré : merci de renvoyer le formulaire.';
        }
        if (trim((string) ($req->post['website'] ?? '')) !== '') {
            return 'Envoi refusé.';
        }
        $age = form_ts_age((string) ($req->post['_ts'] ?? ''));
        if ($age === null || $age < $minSeconds) {
            return 'Merci de prendre le temps de remplir le formulaire.';
        }
        if ($age > 172800) {
            return 'Le formulaire a expiré : rechargez la page puis renvoyez-le.';
        }
        if (!RateLimiter::hit('vt-' . $bucket, $req->ip(), $perHour, 3600)) {
            return 'Trop d’envois depuis votre connexion : réessayez dans une heure.';
        }
        return null;
    }
}
