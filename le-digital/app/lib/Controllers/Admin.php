<?php
declare(strict_types=1);

namespace LD\Controllers;

use LD\Ai;
use LD\Auth;
use LD\Booking;
use LD\Http;
use LD\Leads;
use LD\Posts;
use LD\RateLimit;
use LD\Reviews;
use LD\Router;
use LD\Settings;
use LD\Store;
use LD\View;

/**
 * Back-office : authentification, tableau de bord, demandes et rendez-vous.
 * Les contenus/médias sont dans AdminContent, l'IA et les réglages dans AdminSettings.
 */
final class Admin
{
    public static function routes(Router $r): void
    {
        $r->any('/admin/installation', [self::class, 'setup']);
        $r->any('/admin/connexion', [self::class, 'login']);
        $r->post('/admin/deconnexion', [self::class, 'logout']);
        $r->get('/admin', self::guard([self::class, 'dashboard']));
        $r->get('/admin/demandes', self::guard([self::class, 'leads']));
        $r->get('/admin/demandes/export.csv', self::guard([self::class, 'exportLeads']));
        $r->get('/admin/demandes/{id}', self::guard([self::class, 'lead']));
        $r->post('/admin/demandes/{id}', self::guard([self::class, 'updateLead'], true));
        $r->post('/admin/demandes/{id}/supprimer', self::guard([self::class, 'deleteLead'], true));
        $r->get('/admin/vocal/{file}', self::guard([self::class, 'voiceFile']));
        $r->get('/admin/rendez-vous', self::guard([self::class, 'bookings']));
        $r->post('/admin/rendez-vous/reglages', self::guard([self::class, 'saveBookingSettings'], true));
        $r->post('/admin/rendez-vous/{id}/annuler', self::guard([self::class, 'cancelBooking'], true));

        AdminReviews::routes($r);
        AdminContent::routes($r);
        AdminSettings::routes($r);
        AdminUpdates::routes($r);
    }

    /** Enveloppe un gestionnaire : connexion obligatoire (+ jeton CSRF pour les POST). */
    public static function guard(callable $handler, bool $csrf = false): \Closure
    {
        return static function (...$args) use ($handler, $csrf) {
            header('X-Robots-Tag: noindex, nofollow');
            header('Cache-Control: no-store');
            if (!Auth::hasUsers()) {
                Http::redirect('/admin/installation');
            }
            if (!Auth::check()) {
                if (str_starts_with(Http::path(), '/admin/api/')) {
                    Http::fail('Session expirée, reconnecte-toi.', 401);
                }
                Http::redirect('/admin/connexion?r=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/admin'));
            }
            if ($csrf || Http::method() === 'POST') {
                Auth::verifyCsrf();
            }
            $handler(...$args);
        };
    }

    /** Rendu d'une page du back-office. */
    public static function view(string $template, array $vars = [], string $title = ''): never
    {
        header('Content-Type: text/html; charset=utf-8');
        $vars += ['flash' => Auth::takeFlash(), 'user' => Auth::user(), 'csrf' => Auth::csrf(), 'title' => $title, 'counts' => Leads::counts()];
        $vars['body'] = View::render('admin/' . $template, $vars);
        echo View::render('admin/layout', $vars);
        exit;
    }

    public static function back(string $fallback, string $type, string $message): never
    {
        Auth::flash($type, $message);
        Http::redirect($fallback);
    }

    // ------------------------------------------------------------------ authentification

    public static function setup(): never
    {
        header('X-Robots-Tag: noindex, nofollow');
        if (Auth::hasUsers()) {
            Http::redirect('/admin/connexion');
        }
        $error = '';
        if (Http::method() === 'POST') {
            Auth::verifyCsrf();
            $email = trim((string) ($_POST['email'] ?? ''));
            $pass = (string) ($_POST['password'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Adresse e-mail invalide.';
            } elseif (mb_strlen($pass) < 10) {
                $error = 'Choisis un mot de passe d’au moins 10 caractères.';
            } elseif ($pass !== (string) ($_POST['password2'] ?? '')) {
                $error = 'Les deux mots de passe ne correspondent pas.';
            } else {
                Auth::createUser($email, $pass, trim((string) ($_POST['name'] ?? '')));
                Auth::attempt($email, $pass);
                // Pré-remplit l'e-mail de notification avec celui du compte.
                $s = Store::read('content/settings.json', []);
                if (empty($s['mail']['notify_email'])) {
                    $s['mail']['notify_email'] = $email;
                    Settings::save($s);
                }
                self::back('/admin', 'ok', 'Bienvenue ! Ton back-office est prêt. Commence par la checklist ci-dessous ☕');
            }
        }
        header('Content-Type: text/html; charset=utf-8');
        echo View::render('admin/auth', ['mode' => 'setup', 'error' => $error, 'csrf' => Auth::csrf()]);
        exit;
    }

    public static function login(): never
    {
        header('X-Robots-Tag: noindex, nofollow');
        if (!Auth::hasUsers()) {
            Http::redirect('/admin/installation');
        }
        if (Auth::check()) {
            Http::redirect('/admin');
        }
        $error = '';
        if (Http::method() === 'POST') {
            Auth::verifyCsrf();
            if (!RateLimit::hit('login', 6, 900)) {
                $error = 'Trop de tentatives. Patiente 15 minutes avant de réessayer.';
            } elseif (Auth::attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
                $to = (string) ($_GET['r'] ?? '/admin');
                Http::redirect(str_starts_with($to, '/admin') ? $to : '/admin');
            } else {
                $error = 'Identifiants incorrects.';
                Store::appendLog('auth', ['event' => 'login_failed', 'ip' => substr(hash('sha256', Http::ip()), 0, 12)]);
            }
        }
        header('Content-Type: text/html; charset=utf-8');
        echo View::render('admin/auth', ['mode' => 'login', 'error' => $error, 'csrf' => Auth::csrf()]);
        exit;
    }

    public static function logout(): never
    {
        Auth::verifyCsrf();
        Auth::logout();
        Http::redirect('/admin/connexion');
    }

    // ------------------------------------------------------------------ tableau de bord

    public static function dashboard(): never
    {
        $s = Settings::all();
        $sol = \LD\Content::get('solutions');
        $qui = \LD\Content::get('qui');
        $form = \LD\Content::get('formation');
        $checks = [
            ['Numéro direct', !empty($s['contact']['phone']), '/admin/parametres#coordonnees'],
            ['Lien LinkedIn', !empty($s['contact']['linkedin']), '/admin/parametres#coordonnees'],
            ['E-mails : adresse de notification et envoi qui fonctionne', \LD\Mailer::notifyAddress() !== '' && !empty(\LD\Mailer::recent(1)[0]['ok']), '/admin/parametres#emails'],
            ['Clé API d’IA (assistant, démos)', Ai::resolve('chat') !== null, '/admin/ia'],
            ['Avis clients (Google, widget ou saisis)', count(Reviews::all(true)) > 0 || Reviews::widgetCode() !== '', \LD\GoogleReviews::enabled() || Reviews::all() ? '/admin/avis' : '/admin/avis/google'],
            ['Articles de blog publiés', count(Posts::all(true)) > 0, '/admin/blog'],
            ['Présentation et capture de Formaliance', trim((string) $sol['formaliance']['text']) !== '' && $sol['formaliance']['image'] !== '', '/admin/contenus/solutions'],
            ['Capture et lien d’Intensiti', $sol['intensiti']['image'] !== '' && $sol['intensiti']['url'] !== '', '/admin/contenus/solutions'],
            ['Parcours de Frédéric validé', !empty($qui['story_validated']), '/admin/contenus/qui'],
            ['Programme réel de la formation', trim((string) $form['programme']['todo']) === '', '/admin/contenus/formation'],
            ['Mentions légales complétées', !str_contains((string) json_encode(\LD\Content::get('mentions'), JSON_UNESCAPED_UNICODE), 'à fournir'), '/admin/contenus/mentions'],
            ['Adresse du site (URL canonique)', !empty($s['site']['url']), '/admin/parametres#identite'],
        ];
        $usage = Store::read('data/ai-usage.json', []);
        $cron = Store::read('cache/cron.json', []);
        self::view('dashboard', [
            'checks'   => $checks,
            'latest'   => array_slice(Leads::all(), 0, 6),
            'upcoming' => array_slice(Booking::upcoming(), 0, 5),
            'aiToday'  => $usage[date('Y-m-d')] ?? ['calls' => 0, 'errors' => 0],
            'aiWeek'   => array_sum(array_map(static fn($d) => $d['calls'] ?? 0, array_slice($usage, -7, 7, true))),
            'cron'     => $cron['last_run'] ?? null,
        ], 'Tableau de bord');
    }

    // ------------------------------------------------------------------ demandes

    public static function leads(): never
    {
        $type = isset($_GET['type'], Leads::TYPES[$_GET['type']]) ? (string) $_GET['type'] : null;
        $status = isset($_GET['statut']) && ($_GET['statut'] === 'open' || isset(Leads::STATUSES[$_GET['statut']])) ? (string) $_GET['statut'] : null;
        $q = clean_text((string) ($_GET['q'] ?? ''), 80);
        self::view('leads', ['leads' => Leads::all($type, $status, $q), 'type' => $type, 'status' => $status, 'q' => $q], 'Demandes');
    }

    public static function lead(string $id): never
    {
        $lead = Leads::find($id) ?? self::back('/admin/demandes', 'err', 'Demande introuvable.');
        if ($lead['status'] === 'new') {
            Leads::update($id, ['status' => 'progress']);
            $lead['status'] = 'progress';
        }
        $booking = !empty($lead['data']['booking_id']) ? Booking::find((string) $lead['data']['booking_id']) : null;
        self::view('lead', ['lead' => $lead, 'booking' => $booking], (Leads::TYPES[$lead['type']] ?? 'Demande') . ($lead['name'] ? ' · ' . $lead['name'] : ''));
    }

    public static function updateLead(string $id): never
    {
        $status = (string) ($_POST['status'] ?? '');
        Leads::update($id, array_filter([
            'status' => isset(Leads::STATUSES[$status]) ? $status : null,
            'notes'  => isset($_POST['notes']) ? mb_substr((string) $_POST['notes'], 0, 5000) : null,
        ], static fn($v) => $v !== null));
        self::back('/admin/demandes/' . rawurlencode($id), 'ok', 'Demande mise à jour.');
    }

    public static function deleteLead(string $id): never
    {
        Leads::delete($id);
        self::back('/admin/demandes', 'ok', 'Demande supprimée.');
    }

    public static function exportLeads(): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="demandes-le-digital-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Date', 'Type', 'Statut', 'Prénom', 'Email', 'Téléphone', 'Détails', 'Notes'], ';');
        foreach (Leads::all() as $l) {
            $rows = Leads::rows($l);
            unset($rows['Prénom'], $rows['Email'], $rows['Téléphone']);
            $details = implode(' | ', array_map(static fn($k, $v) => $k . ' : ' . str_replace("\n", ' / ', $v), array_keys($rows), $rows));
            fputcsv($out, [date('d/m/Y H:i', strtotime($l['created_at'])), Leads::TYPES[$l['type']] ?? $l['type'], Leads::STATUSES[$l['status']] ?? $l['status'], $l['name'], $l['email'], $l['phone'], $details, $l['notes']], ';');
        }
        exit;
    }

    public static function voiceFile(string $file): never
    {
        $path = Store::path('data/voices/' . basename($file));
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        header('Content-Type: ' . (str_starts_with($mime, 'video/') ? str_replace('video/', 'audio/', $mime) : $mime));
        header('Content-Length: ' . filesize($path));
        header('Accept-Ranges: none');
        if (isset($_GET['dl'])) {
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        }
        readfile($path);
        exit;
    }

    // ------------------------------------------------------------------ rendez-vous

    public static function bookings(): never
    {
        self::view('bookings', [
            'upcoming' => Booking::upcoming(),
            'past'     => Booking::past(30),
            'cfg'      => Booking::config(),
            'icsUrl'   => abs_url('/rdv/agenda.ics?k=' . Booking::icsKey()),
            'preview'  => Booking::days(),
        ], 'Rendez-vous');
    }

    public static function saveBookingSettings(): never
    {
        $s = Store::read('content/settings.json', []);
        $times = static fn(string $raw) => array_values(array_unique(array_filter(array_map(static function ($t) {
            $t = trim(str_replace(['h', 'H', '.'], ':', $t));
            if (preg_match('/^(\d{1,2}):?(\d{2})?$/', $t, $m)) {
                return sprintf('%02d:%02d', min(23, (int) $m[1]), min(59, (int) ($m[2] ?? 0)));
            }
            return '';
        }, preg_split('/[\s,;]+/', $raw) ?: []))));
        $byDay = [];
        foreach ((array) ($_POST['slots_by_day'] ?? []) as $wd => $raw) {
            $list = $times((string) $raw);
            if ($list) {
                $byDay[(string) (int) $wd] = $list;
            }
        }
        $blocked = array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) ($_POST['blocked_dates'] ?? '')) ?: []), static fn($d) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)));
        $s['booking'] = [
            'enabled'          => !empty($_POST['enabled']),
            'days_shown'       => max(1, min(10, (int) ($_POST['days_shown'] ?? 5))),
            'weekdays'         => array_values(array_map('intval', array_intersect((array) ($_POST['weekdays'] ?? []), ['1', '2', '3', '4', '5', '6', '7']))),
            'slots'            => $times((string) ($_POST['slots'] ?? '')),
            'slots_by_day'     => (object) $byDay,
            'duration'         => max(10, min(240, (int) ($_POST['duration'] ?? 45))),
            'min_notice_hours' => max(0, min(240, (int) ($_POST['min_notice_hours'] ?? 12))),
            'max_days_ahead'   => max(5, min(180, (int) ($_POST['max_days_ahead'] ?? 30))),
            'blocked_dates'    => $blocked,
            'modes'            => array_values(array_filter(array_map(static fn($m) => clean_text((string) $m, 40), (array) ($_POST['modes'] ?? [])))) ?: ['En visio', 'En vrai'],
            'address'          => clean_text((string) ($_POST['address'] ?? ''), 200),
            'visio_link'       => filter_var(trim((string) ($_POST['visio_link'] ?? '')), FILTER_VALIDATE_URL) ?: '',
        ];
        Settings::save($s);
        self::back('/admin/rendez-vous', 'ok', 'Disponibilités enregistrées.');
    }

    public static function cancelBooking(string $id): never
    {
        $b = Booking::setStatus($id, 'cancelled');
        if ($b && !empty($_POST['notify']) && $b['email']) {
            \LD\Mailer::send($b['email'], 'Notre rendez-vous est annulé', \LD\Mailer::layout('Rendez-vous annulé', '<p>Je suis désolé, je dois annuler notre rendez-vous du ' . e(Booking::label($b)) . '.</p><p>Choisis un autre créneau quand tu veux : <a href="' . e(abs_url('/on-prend-rdv')) . '">' . e(abs_url('/on-prend-rdv')) . '</a></p><p>À très vite,<br>Fred</p>'));
        }
        self::back('/admin/rendez-vous', 'ok', 'Rendez-vous annulé, le créneau est de nouveau disponible.');
    }
}
