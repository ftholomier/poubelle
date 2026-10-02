<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\Crypto;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Str;
use App\Core\Url;
use App\Services\AntiSpam;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Leads;
use App\Services\Pros;
use App\Services\Reviews;
use App\Services\Search;
use App\Services\Seo;
use App\Services\Settings;
use App\Services\Stats;
use App\Services\Store;

/** Fiche publique d'un pro, formulaire de contact direct, avis. */
final class ProController extends Controller
{
    public function show(string $a, string $b, string $c, string $d): Response
    {
        $pro = Pros::bySlug($d);
        if (!$pro) {
            throw new HttpException(404);
        }
        return $this->render($pro);
    }

    public function bySlug(string $slug): Response
    {
        $pro = ctype_digit($slug) ? Store::pros()->get((int) $slug) : Pros::bySlug($slug);
        if (!$pro) {
            throw new HttpException(404);
        }
        return $this->render($pro);
    }

    private function render(array $pro): Response
    {
        $canonical = Url::pro($pro);
        if (!Pros::isPublished($pro)) {
            // Fiche retirée : redirection vers la liste de son métier dans son département.
            $cat = $pro['categories'][0] ?? null;
            return $this->redirect(!empty($pro['dep']) ? Url::dep($cat, (string) $pro['dep']) : Url::category($cat), 301);
        }
        if (Request::path() !== $canonical) {
            $qs = Request::queryString();
            return $this->redirect($canonical . ($qs !== '' ? '?' . $qs : ''), 301);
        }
        $cat = Pros::primaryCategory($pro);
        $commune = !empty($pro['insee']) ? Geo::commune((string) $pro['insee']) : null;
        $dep = Geo::dep((string) ($pro['dep'] ?? ''));
        $reviews = Reviews::published((int) $pro['id']);
        $cityName = $commune['n'] ?? ($pro['city'] ?? '');
        $similar = [];
        $found = Search::run(array_filter(['cat' => $cat['slug'] ?? null, 'insee' => $pro['insee'] ?? null, 'dep' => empty($pro['insee']) ? ($pro['dep'] ?? null) : null, 'per' => 7, 'radius' => 80]));
        foreach ($found['items'] as $it) {
            if ((int) $it['id'] !== (int) $pro['id'] && count($similar) < 6) {
                $similar[] = $it;
            }
        }
        $crumbs = [['Accueil', '/']];
        if ($cat) {
            $crumbs[] = [$cat['name'], Url::category($cat['slug'])];
        }
        if ($dep) {
            $crumbs[] = [$dep['name'] . ' (' . $dep['code'] . ')', Url::dep($cat['slug'] ?? null, $dep['code'])];
        }
        if ($commune) {
            $crumbs[] = [$commune['n'], Url::city($cat['slug'] ?? null, $commune['insee'])];
        }
        $crumbs[] = [Pros::displayName($pro), $canonical];
        $vars = [
            'name' => Pros::displayName($pro),
            'cat' => $cat['name'] ?? 'Animation',
            'in_city' => $cityName !== '' ? Geo::inCity($cityName) : ($dep['in'] ?? ''),
            'code' => $dep['code'] ?? '',
            'tagline' => Str::limit((string) ($pro['tagline'] ?: Str::excerpt((string) ($pro['description'] ?? ''), 110)), 110),
        ];
        $meta = Seo::meta('pro', $vars);
        if (!empty($pro['seo']['title'])) {
            $meta['title'] = (string) $pro['seo']['title'];
        }
        if (!empty($pro['seo']['description'])) {
            $meta['description'] = (string) $pro['seo']['description'];
        }
        if (!Request::isBot()) {
            Stats::hit('pro_view', (int) $pro['id'], (string) Request::query('src', ''));
        }
        $cover = $pro['photos'][0] ?? null;
        return $this->view('front/pro', [
            'pro' => $pro,
            'cat' => $cat,
            'commune' => $commune,
            'dep' => $dep,
            'reviews' => $reviews,
            'similar' => $similar,
            'crumbs' => $crumbs,
            'cityName' => $cityName,
            'meta' => $meta + [
                'canonical' => Url::abs($canonical),
                'type' => 'profile',
                'og_image' => $cover ? Pros::photo($cover, 'lg') : null,
                'jsonld' => [Seo::proLd($pro, $reviews), Seo::breadcrumbs($crumbs)],
                'body_class' => 'page-pro',
            ],
        ]);
    }

    /** Redirection comptabilisée vers le site web du pro. */
    public function go(int $id): Response
    {
        $pro = Store::pros()->get($id);
        if (!$pro || !Pros::isPublished($pro) || empty($pro['website'])) {
            throw new HttpException(404);
        }
        $url = Str::url((string) $pro['website']);
        if ($url === '') {
            throw new HttpException(404);
        }
        if (!Request::isBot()) {
            Stats::hit('site', $id);
        }
        return Response::redirect($url, 302)->header('X-Robots-Tag', 'noindex');
    }

    /** Message direct au pro (formulaire de la fiche). */
    public function contact(int $id): Response
    {
        $pro = Store::pros()->get($id);
        if (!$pro || !Pros::isPublished($pro)) {
            throw new HttpException(404);
        }
        $in = [
            'name' => Sanitizer::line((string) Request::input('name', ''), 80),
            'email' => Str::email((string) Request::input('email', '')),
            'phone' => Sanitizer::line((string) Request::input('phone', ''), 30),
            'place' => Sanitizer::line((string) Request::input('place', ''), 120),
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) Request::input('date', '')) ? (string) Request::input('date') : '',
            'event_type' => array_key_exists((string) Request::input('event_type', ''), Leads::EVENT_TYPES) ? (string) Request::input('event_type') : '',
            'guests' => Sanitizer::line((string) Request::input('guests', ''), 30),
            'message' => Sanitizer::text((string) Request::input('message', ''), 4000),
        ];
        $errors = [];
        if (mb_strlen($in['name']) < 2) {
            $errors['name'] = 'Indiquez votre nom.';
        }
        if (!Str::emailValid($in['email'])) {
            $errors['email'] = 'Adresse email invalide.';
        }
        if (mb_strlen($in['message']) < 10) {
            $errors['message'] = 'Décrivez votre demande en quelques mots.';
        }
        if ($in['phone'] !== '' && !Str::phoneValid($in['phone'])) {
            $errors['phone'] = 'Numéro de téléphone invalide.';
        }
        if (!Request::bool('consent')) {
            $errors['consent'] = 'Merci d\'accepter la transmission de vos coordonnées.';
        }
        if ($errors) {
            return $this->formResponse(false, 'Merci de corriger les champs indiqués.', $errors, Url::pro($pro) . '#contact');
        }
        $spam = AntiSpam::evaluate('contact', $in);
        if ($spam['blocked']) {
            return $this->formResponse(false, (string) $spam['message'], [], Url::pro($pro) . '#contact', 429);
        }
        Leads::createMessage($pro, $in, $spam);
        $msg = 'Merci ' . $in['name'] . ' ! Votre message a bien été envoyé à ' . Pros::displayName($pro) . '. Une confirmation vous a été adressée par email.';
        return $this->formResponse(true, $msg, [], '/message/merci/?pro=' . (int) $pro['id']);
    }

    public function thanks(): Response
    {
        $pro = Store::pros()->get((int) Request::query('pro', 0));
        return $this->view('front/thanks', [
            'title' => 'Message envoyé !',
            'text' => 'Votre message a bien été pris en compte' . ($pro ? ' pour ' . Pros::displayName($pro) : '') . '. Le professionnel vous répondra directement par email ou par téléphone.',
            'pro' => $pro,
            'meta' => ['title' => 'Message envoyé', 'robots' => 'noindex, nofollow'],
        ]);
    }

    /** Dépôt d'un avis (confirmé ensuite par email). */
    public function review(int $id): Response
    {
        $pro = Store::pros()->get($id);
        if (!$pro || !Pros::isPublished($pro) || !Settings::get('features.reviews', true)) {
            throw new HttpException(404);
        }
        $in = [
            'rating' => (int) Request::input('rating', 0),
            'name' => Sanitizer::line((string) Request::input('name', ''), 60),
            'email' => Str::email((string) Request::input('email', '')),
            'title' => Sanitizer::line((string) Request::input('title', ''), 90),
            'body' => Sanitizer::text((string) Request::input('body', ''), 3000),
            'event_type' => array_key_exists((string) Request::input('event_type', ''), Leads::EVENT_TYPES) ? (string) Request::input('event_type') : '',
            'event_date' => preg_match('/^\d{4}-\d{2}$/', (string) Request::input('event_date', '')) ? (string) Request::input('event_date') : '',
        ];
        $errors = [];
        if ($in['rating'] < 1 || $in['rating'] > 5) {
            $errors['rating'] = 'Choisissez une note.';
        }
        if (mb_strlen($in['name']) < 2) {
            $errors['name'] = 'Indiquez votre prénom.';
        }
        if (!Str::emailValid($in['email'])) {
            $errors['email'] = 'Email invalide (il ne sera pas publié).';
        }
        if (mb_strlen($in['body']) < (int) Settings::get('reviews.min_length', 30)) {
            $errors['body'] = 'Racontez votre expérience en quelques phrases (' . (int) Settings::get('reviews.min_length', 30) . ' caractères minimum).';
        }
        if (!Request::bool('consent')) {
            $errors['consent'] = 'Merci de certifier votre avis.';
        }
        if ($errors) {
            return $this->formResponse(false, 'Merci de corriger les champs indiqués.', $errors, Url::pro($pro) . '#avis');
        }
        $spam = AntiSpam::evaluate('review', ['name' => $in['name'], 'email' => $in['email'], 'message' => $in['body']]);
        if ($spam['blocked']) {
            return $this->formResponse(false, (string) $spam['message'], [], Url::pro($pro) . '#avis', 429);
        }
        if ($spam['decision'] !== 'spam') {
            Reviews::create($pro, $in + ['spam' => ['score' => $spam['score'], 'decision' => $spam['decision']]]);
        }
        return $this->formResponse(true, 'Merci ! Un email de confirmation vient de vous être envoyé : cliquez sur le lien pour valider votre avis.', [], Url::pro($pro) . '#avis');
    }

    public function confirmReview(string $token): Response
    {
        $rev = Reviews::confirm($token);
        $pro = $rev ? Store::pros()->get((int) $rev['pro_id']) : null;
        return $this->view('front/thanks', [
            'title' => $rev ? 'Avis confirmé, merci !' : 'Lien expiré',
            'text' => $rev ? 'Votre avis sera publié après une rapide relecture par notre équipe.' : 'Ce lien de confirmation n\'est plus valide.',
            'pro' => $pro,
            'meta' => ['title' => 'Confirmation de votre avis', 'robots' => 'noindex, nofollow'],
        ]);
    }

    /** Avis « client vérifié » via l'invitation envoyée par le pro. */
    public function invitedReview(string $token): Response
    {
        $inv = Reviews::inviteToken($token);
        $pro = $inv ? Store::pros()->get((int) $inv['p']) : null;
        if (!$inv || !$pro) {
            return $this->view('front/thanks', ['title' => 'Lien expiré', 'text' => 'Cette invitation n\'est plus valide.', 'pro' => null, 'meta' => ['title' => 'Invitation expirée', 'robots' => 'noindex']]);
        }
        $used = Store::doc('review_invites')->get(sha1($token));
        if (Request::isPost() && !$used) {
            $in = [
                'rating' => max(1, min(5, (int) Request::input('rating', 5))),
                'name' => Sanitizer::line((string) Request::input('name', (string) $inv['n']), 60),
                'email' => (string) $inv['e'],
                'title' => Sanitizer::line((string) Request::input('title', ''), 90),
                'body' => Sanitizer::text((string) Request::input('body', ''), 3000),
                'event_type' => (string) Request::input('event_type', ''),
            ];
            if (mb_strlen($in['body']) >= 15 && mb_strlen($in['name']) >= 2) {
                Reviews::create($pro, $in, true);
                Store::doc('review_invites')->set(sha1($token), date('c'));
                return $this->view('front/thanks', ['title' => 'Merci pour votre avis !', 'text' => 'Il sera publié après relecture, avec la mention « client vérifié ».', 'pro' => $pro, 'meta' => ['title' => 'Merci', 'robots' => 'noindex']]);
            }
        }
        return $this->view('front/review-invite', ['pro' => $pro, 'inv' => $inv, 'used' => (bool) $used, 'meta' => ['title' => 'Votre avis sur ' . Pros::displayName($pro), 'robots' => 'noindex, nofollow']]);
    }

    private function formResponse(bool $ok, string $message, array $errors, string $redirect, int $status = 422): Response
    {
        if (Request::isAjax()) {
            return Response::json(['ok' => $ok, 'message' => $message, 'error' => $ok ? null : $message, 'errors' => $errors, 'redirect' => $ok && str_starts_with($redirect, '/message/') ? $redirect : null], $ok ? 200 : $status);
        }
        \App\Core\Session::flash($ok ? 'success' : 'error', $message);
        if (!$ok) {
            \App\Core\Session::withInput($_POST, $errors);
        }
        return $this->redirect($redirect);
    }
}
