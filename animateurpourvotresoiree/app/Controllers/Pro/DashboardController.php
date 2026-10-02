<?php
declare(strict_types=1);

namespace App\Controllers\Pro;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Crypto;
use App\Core\Fs;
use App\Core\HttpException;
use App\Core\Image;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Session;
use App\Core\Str;
use App\Core\Url;
use App\Services\Ai;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Leads;
use App\Services\Mail;
use App\Services\Notify;
use App\Services\Pros;
use App\Services\Reviews;
use App\Services\Settings;
use App\Services\Stats;
use App\Services\Store;

/** Espace des professionnels : tableau de bord, fiche, photos, demandes, messages, avis, statistiques, compte. */
final class DashboardController extends Controller
{
    private const SOCIALS = ['facebook' => 'facebook.com', 'instagram' => 'instagram.com', 'tiktok' => 'tiktok.com', 'youtube' => 'youtube.com', 'linkedin' => 'linkedin.com'];

    private function me(): array
    {
        $pro = Auth::pro();
        if (!$pro) {
            throw new HttpException(403);
        }
        return $pro;
    }

    private function page(string $view, array $data, string $title, string $section): Response
    {
        $pro = $data['pro'] ?? $this->me();
        return $this->view('pro/' . $view, $data + [
            'pro' => $pro,
            'section' => $section,
            'counts' => self::counts($pro),
            'meta' => ['title' => $title . ' — Espace pro', 'robots' => 'noindex, nofollow', 'ads' => false, 'body_class' => 'is-pro', 'scripts' => [Url::asset('js/editor.js'), Url::asset('js/pro.js')]],
        ], 'pro/layout');
    }

    /** Pastilles du menu : demandes et messages non lus. */
    public static function counts(array $pro): array
    {
        $id = (int) $pro['id'];
        $seenUntil = (string) ($pro['inbox']['requests_seen'] ?? '');
        $req = 0;
        foreach (Store::requests()->ids('recipients', $id) as $rid) {
            $l = Store::requests()->light($rid);
            if ($l && $l['created'] > $seenUntil) {
                $req++;
            }
        }
        $msg = 0;
        foreach (Store::messages()->ids('pro_id', $id) as $mid) {
            $l = Store::messages()->light($mid);
            if ($l && !$l['read'] && $l['status'] === 'delivered') {
                $msg++;
            }
        }
        $rev = 0;
        foreach (Store::reviews()->ids('pro_id', $id) as $rvid) {
            $l = Store::reviews()->light($rvid);
            if ($l && $l['status'] === 'approved' && empty(Store::reviews()->get($rvid)['reply'])) {
                $rev++;
            }
        }
        return ['requests' => $req, 'messages' => $msg, 'reviews' => $rev];
    }

    // ---------------------------------------------------------- tableau de bord

    public function index(): Response
    {
        $pro = $this->me();
        $id = (int) $pro['id'];
        $series = Stats::proSeries($id, 30);
        $prev = Stats::proSeries($id, 60);
        $sum = static fn (array $s, string $k): int => array_sum(array_column($s, $k));
        $last30 = ['pro_view' => $sum($series, 'pro_view'), 'phone' => $sum($series, 'phone'), 'site' => $sum($series, 'site')];
        $prev30 = [];
        foreach (['pro_view', 'phone', 'site'] as $k) {
            $prev30[$k] = $sum(array_slice($prev, 0, 30, true), $k);
        }
        $requests = [];
        foreach (array_slice(array_reverse(Store::requests()->ids('recipients', $id)), 0, 5) as $rid) {
            if ($l = Store::requests()->light($rid)) {
                $requests[] = $l;
            }
        }
        $messages = [];
        foreach (array_reverse(Store::messages()->ids('pro_id', $id)) as $mid) {
            $l = Store::messages()->light($mid);
            if ($l && $l['status'] === 'delivered') {
                $messages[] = $l;
            }
            if (count($messages) >= 5) {
                break;
            }
        }
        $since30 = date('c', strtotime('-30 days'));
        $msg30 = 0;
        foreach (Store::messages()->ids('pro_id', $id) as $mid) {
            $l = Store::messages()->light($mid);
            if ($l && $l['status'] === 'delivered' && $l['created'] >= $since30) {
                $msg30++;
            }
        }
        $req30 = 0;
        foreach (Store::requests()->ids('recipients', $id) as $rid) {
            $l = Store::requests()->light($rid);
            if ($l && $l['created'] >= $since30) {
                $req30++;
            }
        }
        return $this->page('dashboard', [
            'pro' => $pro,
            'series' => $series,
            'last30' => $last30 + ['messages' => $msg30, 'requests' => $req30],
            'prev30' => $prev30,
            'requests' => $requests,
            'messages' => $messages,
            'score' => Pros::completeness($pro),
            'tips' => Pros::tips($pro),
            'impersonating' => Session::get('impersonate_from') !== null,
        ], 'Tableau de bord', 'dashboard');
    }

    // ------------------------------------------------------------------- fiche

    public function fiche(): Response
    {
        $pro = $this->me();
        return $this->page('fiche', [
            'pro' => $pro,
            'commune' => !empty($pro['insee']) ? Geo::commune((string) $pro['insee']) : null,
            'aiOn' => Settings::aiOn('seo'),
            'maxZones' => (int) Settings::get('registration.max_zones', 10),
        ], 'Ma fiche', 'fiche');
    }

    public function saveFiche(): Response
    {
        $pro = $this->me();
        $errors = [];
        $p = [];
        $p['display_name'] = Sanitizer::line((string) Request::input('display_name', ''), 80);
        if (mb_strlen($p['display_name']) < 2) {
            $errors['display_name'] = 'Indiquez votre nom de scène ou de société.';
        }
        $p['company'] = Sanitizer::line((string) Request::input('company', ''), 100);
        $p['first_name'] = Str::nameCase(Sanitizer::line((string) Request::input('first_name', ''), 60));
        $p['last_name'] = Str::nameCase(Sanitizer::line((string) Request::input('last_name', ''), 60));
        $p['tagline'] = Sanitizer::line((string) Request::input('tagline', ''), 220);
        $desc = Sanitizer::html((string) Request::raw('description', ''), ['headings' => true]);
        if (mb_strlen(Str::text($desc)) > 12000) {
            $errors['description'] = 'Description trop longue (12 000 caractères maximum).';
        }
        $p['description'] = $desc;
        $cats = array_slice(array_values(array_unique(array_filter((array) Request::arr('categories'), static fn ($c) => Categories::get((string) $c) !== null))), 0, 4);
        if (!$cats) {
            $errors['categories'] = 'Choisissez au moins un métier.';
        }
        $p['categories'] = $cats;
        $tags = [];
        foreach (preg_split('/[,;\n]+/', (string) Request::input('tags', '')) ?: [] as $t) {
            $t = Sanitizer::line($t, 40);
            if ($t !== '' && count($tags) < 10) {
                $tags[mb_strtolower($t)] = $t;
            }
        }
        $p['tags'] = array_values($tags);
        // localisation
        $insee = preg_match('/^[0-9AB]{5}$/', (string) Request::input('insee', '')) ? (string) Request::input('insee') : '';
        $commune = $insee !== '' ? Geo::commune($insee) : null;
        if (!$commune && ($city = Sanitizer::line((string) Request::input('city', ''), 80)) !== '' && $city !== Geo::label(Geo::commune((string) ($pro['insee'] ?? '')))) {
            $found = Geo::search($city, 1);
            $commune = $found ? Geo::commune($found[0]['insee']) : null;
        }
        if ($commune) {
            $cp = preg_replace('/\D/', '', (string) Request::input('postcode', '')) ?? '';
            $p['city'] = (string) $commune['n'];
            $p['postcode'] = in_array($cp, (array) $commune['cp'], true) ? $cp : (string) ($commune['cp'][0] ?? '');
            $p['geo_manual'] = false;
        } elseif (empty($pro['insee'])) {
            $errors['city'] = 'Choisissez votre ville dans la liste.';
        }
        $p['address'] = Sanitizer::line((string) Request::input('address', ''), 160);
        $maxZones = (int) Settings::get('registration.max_zones', 10);
        $zones = array_values(array_unique(array_filter(array_map(static fn ($z) => Geo::depCode((string) $z), (array) Request::arr('zones')), static fn ($z) => Geo::dep($z) !== null)));
        if (count($zones) > $maxZones) {
            $errors['zones'] = "Vous pouvez choisir $maxZones départements au maximum (cochez « Toute la France » si vous vous déplacez partout).";
        }
        $p['zones'] = array_slice($zones, 0, $maxZones);
        $p['all_france'] = Request::bool('all_france');
        // contact et liens
        $phone = Str::phone(Sanitizer::line((string) Request::input('phone', ''), 30));
        if ($phone !== '' && !Str::phoneValid($phone)) {
            $errors['phone'] = 'Numéro de téléphone invalide.';
        }
        $p['phone'] = $phone;
        $site = Sanitizer::line((string) Request::input('website', ''), 255);
        $p['website'] = $site !== '' ? Str::url($site) : '';
        if ($site !== '' && $p['website'] === '') {
            $errors['website'] = 'Adresse de site invalide.';
        }
        $socials = [];
        foreach (self::SOCIALS as $net => $domain) {
            $u = Str::url(Sanitizer::line((string) (Request::arr('socials')[$net] ?? ''), 255));
            if ($u !== '') {
                if (!str_contains(Str::domain($u), str_replace('.com', '', $domain))) {
                    $errors['socials'] = 'Le lien ' . ucfirst($net) . ' doit pointer vers ' . $domain . '.';
                    continue;
                }
                $socials[$net] = $u;
            }
        }
        $p['socials'] = $socials;
        $videos = [];
        foreach (preg_split('/\s+/', (string) Request::input('videos', '')) ?: [] as $v) {
            $v = trim($v);
            if ($v === '') {
                continue;
            }
            if (!preg_match('~^https?://(www\.|m\.)?(youtube\.com|youtu\.be|vimeo\.com)/~i', $v)) {
                $errors['videos'] = 'Seuls les liens YouTube et Vimeo sont acceptés.';
                continue;
            }
            if (count($videos) < 4) {
                $videos[] = Str::limit($v, 200, '');
            }
        }
        $p['videos'] = $videos;
        $price = trim((string) Request::input('price_from', ''));
        $p['price_from'] = $price === '' ? null : max(0, min(100000, (int) preg_replace('/\D/', '', $price)));
        $p['price_note'] = Sanitizer::line((string) Request::input('price_note', ''), 80);
        $p['accept_requests'] = Request::bool('accept_requests');
        $p['siret'] = preg_replace('/\D/', '', (string) Request::input('siret', '')) ?? '';
        $p['guso'] = Sanitizer::line((string) Request::input('guso', ''), 30);
        $p['languages'] = Sanitizer::line((string) Request::input('languages', ''), 80);
        $p['experience_since'] = preg_match('/^(19[5-9]\d|20\d\d)$/', (string) Request::input('experience_since', '')) ? (int) Request::input('experience_since') : null;

        if ($errors) {
            Session::flash('error', 'Certains champs sont à corriger.');
            Session::withInput($_POST, $errors);
            return $this->redirect('/espace-pro/fiche/');
        }
        $before = $pro;
        $saved = Pros::save((int) $pro['id'], static function (array $cur) use ($p, $commune): array {
            foreach ($p as $k => $v) {
                $cur[$k] = $v;
            }
            if ($commune) {
                // la commune choisie fait foi (pas de nouvelle recherche approximative)
                $cur['insee'] = $commune['insee'];
                $cur['dep'] = $commune['d'];
                $cur['region'] = $commune['r'];
                $cur['lat'] = (float) $commune['la'];
                $cur['lng'] = (float) $commune['lo'];
                $cur['city_official'] = $commune['n'];
                $cur['geo_precision'] = 'commune';
                $cur['geo_manual'] = true;
            }
            $cur['updated_by_pro_at'] = date('c');
            return $cur;
        });
        if ($saved && ($saved['status'] ?? '') === 'active') {
            $changed = [];
            foreach (['display_name' => 'nom', 'tagline' => 'accroche', 'description' => 'description', 'categories' => 'métiers', 'city' => 'ville', 'website' => 'site', 'videos' => 'vidéos'] as $k => $label) {
                if (($before[$k] ?? null) != ($saved[$k] ?? null)) {
                    $changed[] = $label;
                }
            }
            if ($changed && RateLimiter::attempt('notify-pro-updated:' . $pro['id'], 1, 3600)) {
                Notify::admin('pro_updated', 'Fiche modifiée : ' . Pros::displayName($saved), 'Champs modifiés : ' . implode(', ', $changed), Url::admin('pros/' . $saved['id']), 'info');
            }
        }
        Logger::audit('Fiche modifiée par le pro', ['pro' => (int) $pro['id']]);
        Session::flash('success', 'Votre fiche est enregistrée ✔');
        return $this->redirect('/espace-pro/fiche/');
    }

    // ------------------------------------------------------------------ photos

    public function photos(): Response
    {
        $pro = $this->me();
        return $this->page('photos', ['pro' => $pro, 'max' => (int) Settings::get('registration.max_photos', 12)], 'Mes photos', 'photos');
    }

    public function uploadPhotos(): Response
    {
        $pro = $this->me();
        $max = (int) Settings::get('registration.max_photos', 12);
        $files = Request::files('photos');
        if (!$files && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            return $this->photoResponse(false, 'Envoi trop volumineux : envoyez vos photos en plusieurs fois (10 Mo maximum par photo).');
        }
        if (!RateLimiter::attempt('photo-upload:' . $pro['id'], 60, 3600)) {
            return $this->photoResponse(false, 'Trop d\'envois en peu de temps, réessayez plus tard.');
        }
        $photos = (array) ($pro['photos'] ?? []);
        $added = 0;
        $errors = [];
        foreach ($files as $f) {
            if (count($photos) >= $max) {
                $errors[] = "Limite de $max photos atteinte.";
                break;
            }
            if (($err = Image::validateUpload($f)) !== null) {
                $errors[] = ($f['name'] ?? 'Fichier') . ' : ' . $err;
                continue;
            }
            try {
                $img = Image::store($f['tmp_name'], PUBLIC_PATH . '/media/pros/' . (int) $pro['id']);
                $photos[] = [
                    'id' => $img['id'],
                    'file' => (int) $pro['id'] . '/' . $img['id'],
                    'ext' => $img['ext'],
                    'w' => $img['w'],
                    'h' => $img['h'],
                    'alt' => Pros::displayName($pro),
                    'cover' => $photos === [],
                    'added_at' => date('c'),
                ];
                $added++;
            } catch (\Throwable $e) {
                $errors[] = ($f['name'] ?? 'Fichier') . ' : ' . $e->getMessage();
            }
        }
        if ($added) {
            Pros::save((int) $pro['id'], static function (array $cur) use ($photos): array {
                $cur['photos'] = $photos;
                return $cur;
            });
            Logger::audit('Photos ajoutées par le pro', ['pro' => (int) $pro['id'], 'n' => $added]);
        }
        $msg = $added ? ($added > 1 ? "$added photos ajoutées." : 'Photo ajoutée.') : 'Aucune photo ajoutée.';
        return $this->photoResponse($added > 0, $msg . ($errors ? ' ' . implode(' ', $errors) : ''));
    }

    public function photoAction(): Response
    {
        $pro = $this->me();
        $photoId = (string) Request::input('photo', '');
        $action = (string) Request::input('action', '');
        $photos = array_values((array) ($pro['photos'] ?? []));
        $idx = null;
        foreach ($photos as $i => $ph) {
            if (($ph['id'] ?? '') === $photoId) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            return $this->photoResponse(false, 'Photo introuvable.');
        }
        switch ($action) {
            case 'delete':
                $ph = $photos[$idx];
                Image::deleteSet(PUBLIC_PATH . '/media/pros/' . (int) $pro['id'], (string) $ph['id']);
                array_splice($photos, $idx, 1);
                if ($photos && !array_filter($photos, static fn ($x) => !empty($x['cover']))) {
                    $photos[0]['cover'] = true;
                }
                $msg = 'Photo supprimée.';
                break;
            case 'cover':
                foreach ($photos as $i => $ph) {
                    $photos[$i]['cover'] = $i === $idx;
                }
                $msg = 'Photo principale modifiée.';
                break;
            case 'left':
            case 'right':
                $j = $action === 'left' ? $idx - 1 : $idx + 1;
                if (isset($photos[$j])) {
                    [$photos[$idx], $photos[$j]] = [$photos[$j], $photos[$idx]];
                }
                $msg = 'Ordre modifié.';
                break;
            case 'alt':
                $photos[$idx]['alt'] = Sanitizer::line((string) Request::input('alt', ''), 120) ?: Pros::displayName($pro);
                $msg = 'Légende enregistrée.';
                break;
            default:
                return $this->photoResponse(false, 'Action inconnue.');
        }
        Pros::save((int) $pro['id'], static function (array $cur) use ($photos): array {
            $cur['photos'] = $photos;
            return $cur;
        });
        return $this->photoResponse(true, $msg);
    }

    private function photoResponse(bool $ok, string $message): Response
    {
        if (Request::isAjax()) {
            return Response::json(['ok' => $ok, 'message' => $message, 'error' => $ok ? null : $message], $ok ? 200 : 422);
        }
        Session::flash($ok ? 'success' : 'error', $message);
        return $this->redirect('/espace-pro/photos/');
    }

    // --------------------------------------------------------------- demandes

    public function requests(): Response
    {
        $pro = $this->me();
        $ids = array_reverse(Store::requests()->ids('recipients', (int) $pro['id']));
        $per = 20;
        $page = max(1, Request::int('page', 1));
        $items = [];
        foreach (array_slice($ids, ($page - 1) * $per, $per) as $rid) {
            if ($l = Store::requests()->light($rid)) {
                $items[] = $l;
            }
        }
        $seen = (string) ($pro['inbox']['requests_seen'] ?? '');
        // les demandes affichées sont désormais « vues »
        Store::pros()->update((int) $pro['id'], static function (array $p): array {
            $p['inbox']['requests_seen'] = date('c');
            return $p;
        }, false);
        return $this->page('requests', ['pro' => $pro, 'items' => $items, 'seen' => $seen, 'total' => count($ids), 'page' => $page, 'pages' => (int) ceil(count($ids) / $per), 'states' => self::proStates()], 'Demandes de devis', 'requests');
    }

    public function request(int $id): Response
    {
        $pro = $this->me();
        $r = Store::requests()->get($id);
        if (!$r || !in_array((int) $pro['id'], array_map('intval', (array) ($r['recipients'] ?? [])), true)) {
            throw new HttpException(404, 'Cette demande ne vous a pas été transmise.');
        }
        if (Request::isPost()) {
            $state = (string) Request::input('state', '');
            if (isset(self::proStates()[$state])) {
                $r = Store::requests()->update($id, static function (array $x) use ($pro, $state): array {
                    $x['pro_states'][(string) $pro['id']] = ['state' => $state, 'at' => date('c')];
                    return $x;
                }, false);
                Session::flash('success', 'Statut enregistré : ' . self::proStates()[$state]);
            }
            return $this->redirect('/espace-pro/demandes/' . $id . '/');
        }
        if (empty($r['views'][(string) $pro['id']])) {
            $r = Store::requests()->update($id, static function (array $x) use ($pro): array {
                $x['views'][(string) $pro['id']] = date('c');
                return $x;
            }, false) ?? $r;
        }
        return $this->page('request', ['pro' => $pro, 'r' => $r, 'details' => Leads::requestDetails($r, true), 'state' => $r['pro_states'][(string) $pro['id']]['state'] ?? '', 'states' => self::proStates()], 'Demande de devis', 'requests');
    }

    /** Suivi personnel d'une demande par le pro. */
    public static function proStates(): array
    {
        return ['contacted' => 'J\'ai contacté le client', 'quoted' => 'Devis envoyé', 'won' => 'Prestation obtenue 🎉', 'lost' => 'Pas retenu', 'declined' => 'Pas disponible / pas intéressé'];
    }

    // --------------------------------------------------------------- messages

    public function messages(): Response
    {
        $pro = $this->me();
        $all = [];
        foreach (array_reverse(Store::messages()->ids('pro_id', (int) $pro['id'])) as $mid) {
            $l = Store::messages()->light($mid);
            if ($l && $l['status'] === 'delivered') {
                $all[] = $l;
            }
        }
        $per = 25;
        $page = max(1, Request::int('page', 1));
        return $this->page('messages', ['pro' => $pro, 'items' => array_slice($all, ($page - 1) * $per, $per), 'total' => count($all), 'page' => $page, 'pages' => (int) ceil(count($all) / $per)], 'Messages', 'messages');
    }

    public function message(int $id): Response
    {
        $pro = $this->me();
        $m = Store::messages()->get($id);
        if (!$m || (int) $m['pro_id'] !== (int) $pro['id'] || ($m['status'] ?? '') !== 'delivered') {
            throw new HttpException(404, 'Message introuvable.');
        }
        if (empty($m['read_at']) && !Session::get('impersonate_from')) {
            $m = Store::messages()->update($id, ['read_at' => date('c')], false) ?? $m;
        }
        return $this->page('message', ['pro' => $pro, 'm' => $m], 'Message de ' . $m['name'], 'messages');
    }

    // -------------------------------------------------------------------- avis

    public function reviews(): Response
    {
        $pro = $this->me();
        $items = [];
        foreach (array_reverse(Store::reviews()->ids('pro_id', (int) $pro['id'])) as $rid) {
            $r = Store::reviews()->get($rid);
            if ($r && in_array($r['status'], ['approved', 'pending'], true)) {
                $items[] = $r;
            }
        }
        return $this->page('reviews', ['pro' => $pro, 'items' => $items, 'enabled' => (bool) Settings::get('features.reviews', true)], 'Avis clients', 'reviews');
    }

    public function replyReview(): Response
    {
        $pro = $this->me();
        $id = Request::int('review');
        $r = Store::reviews()->get($id);
        if (!$r || (int) $r['pro_id'] !== (int) $pro['id'] || $r['status'] !== 'approved') {
            throw new HttpException(404);
        }
        $text = Sanitizer::text((string) Request::input('reply', ''), 1500);
        Store::reviews()->update($id, ['reply' => $text !== '' ? ['text' => $text, 'at' => date('c')] : null]);
        Pros::changed();
        Session::flash('success', $text !== '' ? 'Votre réponse est publiée.' : 'Réponse supprimée.');
        return $this->redirect('/espace-pro/avis/#avis-' . $id);
    }

    public function inviteReview(): Response
    {
        $pro = $this->me();
        if (($pro['status'] ?? '') !== 'active') {
            Session::flash('error', 'Les invitations sont disponibles une fois votre fiche publiée.');
            return $this->redirect('/espace-pro/avis/');
        }
        $emails = array_slice(array_values(array_unique(array_filter(array_map([Str::class, 'email'], preg_split('/[\s,;]+/', (string) Request::input('emails', '')) ?: []), [Str::class, 'emailValid']))), 0, 10);
        $sent = 0;
        foreach ($emails as $email) {
            if (!RateLimiter::attempt('review-invite:' . $pro['id'], 30, 86400) || !RateLimiter::attempt('review-invite-to:' . $email, 1, 86400 * 30)) {
                continue;
            }
            if (Reviews::invite($pro, $email)) {
                $sent++;
            }
        }
        Logger::audit('Invitations à laisser un avis', ['pro' => (int) $pro['id'], 'n' => $sent]);
        Session::flash($sent ? 'success' : 'warning', $sent ? "$sent invitation(s) envoyée(s). Chaque avis reçu portera la mention « client vérifié »." : 'Aucune invitation envoyée (adresses invalides ou déjà invitées récemment).');
        return $this->redirect('/espace-pro/avis/');
    }

    // ------------------------------------------------------------ statistiques

    public function stats(): Response
    {
        $pro = $this->me();
        $days = in_array(Request::int('jours', 30), [7, 30, 90, 365], true) ? Request::int('jours', 30) : 30;
        $series = Stats::proSeries((int) $pro['id'], $days);
        $msgByDay = [];
        $since = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        foreach (Store::messages()->ids('pro_id', (int) $pro['id']) as $mid) {
            $l = Store::messages()->light($mid);
            if ($l && $l['status'] === 'delivered' && substr($l['created'], 0, 10) >= $since) {
                $d = substr($l['created'], 0, 10);
                $msgByDay[$d] = ($msgByDay[$d] ?? 0) + 1;
            }
        }
        $reqByDay = [];
        foreach (Store::requests()->ids('recipients', (int) $pro['id']) as $rid) {
            $l = Store::requests()->light($rid);
            if ($l && substr($l['created'], 0, 10) >= $since) {
                $d = substr($l['created'], 0, 10);
                $reqByDay[$d] = ($reqByDay[$d] ?? 0) + 1;
            }
        }
        foreach ($series as $d => $v) {
            $series[$d]['message'] = $msgByDay[$d] ?? 0;
            $series[$d]['request'] = $reqByDay[$d] ?? 0;
        }
        // position dans son département et son métier
        $rank = null;
        $cat = $pro['categories'][0] ?? null;
        if (($pro['status'] ?? '') === 'active' && $cat && !empty($pro['dep'])) {
            $peers = array_filter(Pros::publicIndex(), static fn ($p) => $p['dep'] === $pro['dep'] && in_array($cat, $p['cats'], true));
            uasort($peers, static fn ($a, $b) => $b['views'] <=> $a['views']);
            $pos = array_search((int) $pro['id'], array_map('intval', array_keys($peers)), true);
            $rank = $pos === false ? null : ['pos' => $pos + 1, 'of' => count($peers), 'cat' => Categories::name($cat), 'dep' => Geo::dep((string) $pro['dep'])['name'] ?? $pro['dep']];
        }
        return $this->page('stats', ['pro' => $pro, 'series' => $series, 'days' => $days, 'rank' => $rank], 'Statistiques', 'stats');
    }

    // ------------------------------------------------------------------- compte

    public function account(): Response
    {
        $pro = $this->me();
        return $this->page('account', ['pro' => $pro, 'push' => Settings::get('features.push', true) && \App\Services\Push::available()], 'Mon compte', 'account');
    }

    public function saveAccount(): Response
    {
        $pro = $this->me();
        $form = (string) Request::input('form', '');
        $impersonating = Session::get('impersonate_from') !== null;
        switch ($form) {
            case 'password':
                if ($impersonating) {
                    Session::flash('error', 'Action impossible en mode aperçu administrateur.');
                    break;
                }
                $current = (string) Request::raw('current', '');
                $new = (string) Request::raw('password', '');
                $forced = !empty($pro['must_change_password']);
                if (!Crypto::verifyPassword($current, (string) ($pro['password_hash'] ?? ''))) {
                    Session::flash('error', 'Mot de passe actuel incorrect.');
                    break;
                }
                if (($err = Auth::passwordError($new, [$pro['login'] ?? '', $pro['email'] ?? ''])) !== null) {
                    Session::flash('error', $err);
                    break;
                }
                if ($new !== (string) Request::raw('password2', '')) {
                    Session::flash('error', 'Les deux mots de passe ne correspondent pas.');
                    break;
                }
                if (hash_equals($current, $new)) {
                    Session::flash('error', 'Choisissez un mot de passe différent de l\'ancien.');
                    break;
                }
                $pro = Store::pros()->update((int) $pro['id'], ['password_hash' => Crypto::hashPassword($new), 'must_change_password' => false, 'password_changed_at' => date('c'), 'session_version' => (int) ($pro['session_version'] ?? 0) + 1], false);
                Auth::loginPro($pro); // les autres sessions sont déconnectées
                Logger::security('Mot de passe pro modifié', ['pro' => (int) $pro['id']], 'info');
                Session::flash('success', 'Mot de passe modifié.' . ($forced ? ' Merci ! Votre espace est maintenant entièrement accessible.' : ''));
                return $this->redirect($forced ? '/espace-pro/' : '/espace-pro/compte/');
            case 'email':
                if ($impersonating) {
                    Session::flash('error', 'Action impossible en mode aperçu administrateur.');
                    break;
                }
                $email = Str::email((string) Request::input('email', ''));
                if (!Str::emailValid($email)) {
                    Session::flash('error', 'Adresse email invalide.');
                    break;
                }
                if (!Crypto::verifyPassword((string) Request::raw('current', ''), (string) ($pro['password_hash'] ?? ''))) {
                    Session::flash('error', 'Mot de passe incorrect.');
                    break;
                }
                if (Pros::emailTaken($email, (int) $pro['id'])) {
                    Session::flash('error', 'Cette adresse est déjà utilisée par un autre compte.');
                    break;
                }
                if (!RateLimiter::attempt('email-change:' . $pro['id'], 5, 86400)) {
                    Session::flash('error', 'Trop de demandes aujourd\'hui, réessayez demain.');
                    break;
                }
                $pro = Store::pros()->update((int) $pro['id'], ['email_pending' => $email], false);
                RegisterController::sendVerification($pro, 'pro_verify_email', $email);
                Session::flash('success', 'Un lien de confirmation a été envoyé à ' . $email . '. Votre adresse actuelle reste active tant que la nouvelle n\'est pas confirmée.');
                break;
            case 'resend':
                if (empty($pro['email_verified']) && RateLimiter::attempt('verify-resend:' . $pro['id'], 3, 3600)) {
                    RegisterController::sendVerification($pro);
                    Session::flash('success', 'Email de confirmation renvoyé à ' . $pro['email'] . '.');
                }
                break;
            case 'prefs':
                $vacationUntil = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) Request::input('vacation_until', '')) ? (string) Request::input('vacation_until') : '';
                Pros::save((int) $pro['id'], static function (array $p) use ($vacationUntil): array {
                    $p['settings']['notify_requests'] = Request::bool('notify_requests');
                    $p['settings']['notify_messages'] = Request::bool('notify_messages');
                    $p['settings']['weekly_report'] = Request::bool('weekly_report');
                    $p['settings']['newsletter'] = Request::bool('newsletter');
                    $p['settings']['vacation'] = Request::bool('vacation');
                    $p['settings']['vacation_until'] = Request::bool('vacation') ? $vacationUntil : '';
                    return $p;
                });
                if (Request::bool('newsletter')) {
                    Store::doc('unsubscribed')->update(static function (array $d) use ($pro): array {
                        unset($d['pros'][(string) $pro['id']]);
                        return $d;
                    });
                }
                Session::flash('success', 'Préférences enregistrées.');
                break;
            default:
                Session::flash('error', 'Formulaire inconnu.');
        }
        return $this->redirect('/espace-pro/compte/');
    }

    /** Export de toutes les données du compte (portabilité RGPD). */
    public function export(): Response
    {
        $pro = $this->me();
        $data = $pro;
        unset($data['password_hash'], $data['session_version'], $data['failed_logins'], $data['locked_until'], $data['registration']['ip_hash'], $data['admin_notes']);
        $data['messages'] = [];
        foreach (Store::messages()->ids('pro_id', (int) $pro['id']) as $mid) {
            $m = Store::messages()->get($mid);
            if ($m && $m['status'] === 'delivered') {
                unset($m['ip_hash'], $m['spam']);
                $data['messages'][] = $m;
            }
        }
        $data['requests'] = [];
        foreach (Store::requests()->ids('recipients', (int) $pro['id']) as $rid) {
            $r = Store::requests()->get($rid);
            if ($r) {
                $data['requests'][] = ['id' => $r['id'], 'created_at' => $r['created_at'] ?? '', 'details' => Leads::requestDetails($r, true)];
            }
        }
        $data['reviews'] = [];
        foreach (Store::reviews()->ids('pro_id', (int) $pro['id']) as $rid) {
            $r = Store::reviews()->get($rid);
            if ($r && $r['status'] === 'approved') {
                $data['reviews'][] = ['rating' => $r['rating'], 'author' => $r['author_name'], 'body' => $r['body'], 'reply' => $r['reply'], 'created_at' => $r['created_at'] ?? ''];
            }
        }
        $data['statistiques'] = Fs::readJson(STORAGE_PATH . '/data/stats/pros/' . (int) $pro['id'] . '.json', []);
        Logger::audit('Export des données par le pro', ['pro' => (int) $pro['id']]);
        $json = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return new Response($json, 200, ['Content-Type' => 'application/json; charset=utf-8', 'Content-Disposition' => 'attachment; filename="mes-donnees-apvs-' . date('Y-m-d') . '.json"', 'Cache-Control' => 'no-store']);
    }

    public function deleteAccount(): Response
    {
        $pro = $this->me();
        if (Session::get('impersonate_from') !== null) {
            Session::flash('error', 'Action impossible en mode aperçu administrateur.');
            return $this->redirect('/espace-pro/compte/');
        }
        if (!Crypto::verifyPassword((string) Request::raw('current', ''), (string) ($pro['password_hash'] ?? '')) || mb_strtoupper(trim((string) Request::input('confirm', ''))) !== 'SUPPRIMER') {
            Session::flash('error', 'Pour supprimer votre compte, saisissez votre mot de passe et le mot SUPPRIMER.');
            return $this->redirect('/espace-pro/compte/#supprimer');
        }
        $email = (string) $pro['email'];
        $name = Pros::displayName($pro);
        self::erase((int) $pro['id'], 'demande du pro');
        if (Str::emailValid($email)) {
            Mail::send($email, 'account_deleted', ['prenom' => $pro['first_name'] ?: $name]);
        }
        Notify::admin('pro_updated', 'Compte supprimé par le pro : ' . $name, 'Le pro a supprimé son compte depuis son espace.', Url::admin('pros/' . $pro['id']), 'warning');
        Auth::logoutPro();
        Session::regenerate();
        Session::flash('success', 'Votre compte a été supprimé. Merci d\'avoir fait partie de l\'aventure !');
        return $this->redirect('/');
    }

    /** Effacement RGPD : données personnelles et photos supprimées, seule une trace minimale subsiste. */
    public static function erase(int $id, string $reason): void
    {
        $pro = Store::pros()->get($id);
        if (!$pro) {
            return;
        }
        Fs::rmrf(PUBLIC_PATH . '/media/pros/' . $id);
        Store::pros()->replace($id, [
            'id' => $id,
            'status' => 'deleted',
            'display_name' => Pros::displayName($pro),
            'slug' => $pro['slug'] ?? '',
            'categories' => $pro['categories'] ?? [],
            'dep' => $pro['dep'] ?? '',
            'region' => $pro['region'] ?? '',
            'city' => $pro['city'] ?? '',
            'insee' => $pro['insee'] ?? '',
            'email' => '',
            'login' => '',
            'photos' => [],
            'deleted_at' => date('c'),
            'deleted_reason' => $reason,
            'created_at' => $pro['created_at'] ?? date('c'),
            'source' => $pro['source'] ?? '',
        ]);
        foreach (Store::pushSubs()->ids('owner', 'pro:' . $id) as $sid) {
            Store::pushSubs()->delete($sid);
        }
        Pros::changed();
        Logger::audit('Compte pro effacé', ['pro' => $id, 'motif' => $reason]);
    }

    // --------------------------------------------------------------- IA, aperçu

    public function aiDescription(): Response
    {
        $pro = $this->me();
        if (!Settings::aiOn('seo')) {
            return Response::json(['error' => 'L\'assistant de rédaction est désactivé.'], 503);
        }
        if (!RateLimiter::attempt('ai-desc:' . $pro['id'], 10, 86400)) {
            return Response::json(['error' => 'Vous avez atteint la limite de 10 propositions par jour.'], 429);
        }
        $draft = Str::text(Sanitizer::html((string) Request::raw('draft', '')));
        if (mb_strlen($draft) < 60) {
            return Response::json(['error' => 'Écrivez d\'abord quelques lignes (60 caractères minimum) : l\'IA les améliore sans rien inventer.'], 422);
        }
        $text = Ai::improveDescription($pro, $draft);
        if (!$text) {
            return Response::json(['error' => 'L\'IA est momentanément indisponible, réessayez plus tard.'], 503);
        }
        return Response::json(['ok' => true, 'html' => Str::paragraphs($text)]);
    }

    public function stopImpersonate(): Response
    {
        $adminId = Session::get('impersonate_from');
        $proId = (int) ($this->me()['id']);
        Auth::logoutPro();
        Session::forget('impersonate_from');
        return $this->redirect($adminId ? Url::admin('pros/' . $proId) : '/');
    }
}
