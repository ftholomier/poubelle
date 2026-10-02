<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Session;
use App\Core\Str;
use App\Core\Url;
use App\Services\AntiSpam;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Mail;
use App\Services\Notify;
use App\Services\Pros;
use App\Services\Seo;
use App\Services\Settings;
use App\Services\Stats;
use App\Services\Store;

/** Pages institutionnelles, contact, plan du site et page « Je suis un pro ». */
final class PageController extends Controller
{
    public function show(array $page): Response
    {
        $crumbs = [['Accueil', '/'], [$page['title'], Url::page($page['slug'])]];
        $jsonld = [Seo::breadcrumbs($crumbs)];
        if ($page['slug'] === 'faq' && preg_match_all('#<h2>(.*?)</h2>\s*<p>(.*?)</p>#s', (string) $page['body'], $m, PREG_SET_ORDER)) {
            $jsonld[] = Seo::faq(array_map(static fn ($x) => [strip_tags($x[1]), strip_tags($x[2])], $m));
        }
        Stats::hit('pv');
        return $this->view('front/page', [
            'page' => $page,
            'crumbs' => $crumbs,
            'meta' => [
                'title' => $page['seo']['title'] ?? '' ?: $page['title'],
                'description' => $page['seo']['description'] ?? '' ?: Str::excerpt((string) $page['body'], 160),
                'canonical' => Url::abs(Url::page($page['slug'])),
                'jsonld' => $jsonld,
                'robots' => in_array($page['slug'], ['mentions-legales', 'cgu', 'confidentialite'], true) ? 'noindex, follow' : 'index, follow',
            ],
        ]);
    }

    public function contact(): Response
    {
        return $this->view('front/contact', ['meta' => ['title' => 'Contact', 'description' => 'Une question, une suggestion, un partenariat ? Écrivez-nous.', 'canonical' => Url::abs('/contact/')]]);
    }

    public function contactSubmit(): Response
    {
        $in = [
            'name' => Sanitizer::line((string) Request::input('name', ''), 80),
            'email' => Str::email((string) Request::input('email', '')),
            'subject' => Sanitizer::line((string) Request::input('subject', ''), 120),
            'message' => Sanitizer::text((string) Request::input('message', ''), 5000),
            'kind' => in_array(Request::input('kind'), ['visiteur', 'pro', 'partenariat', 'presse', 'rgpd'], true) ? (string) Request::input('kind') : 'visiteur',
        ];
        $errors = [];
        if (mb_strlen($in['name']) < 2) {
            $errors['name'] = 'Indiquez votre nom.';
        }
        if (!Str::emailValid($in['email'])) {
            $errors['email'] = 'Email invalide.';
        }
        if (mb_strlen($in['message']) < 10) {
            $errors['message'] = 'Votre message est trop court.';
        }
        if ($errors) {
            return $this->ajax(false, 'Merci de corriger les champs indiqués.', $errors);
        }
        $spam = AntiSpam::evaluate('site_contact', $in);
        if ($spam['blocked']) {
            return $this->ajax(false, (string) $spam['message'], [], 429);
        }
        $rec = Store::contacts()->insert($in + ['status' => $spam['decision'] === 'spam' ? 'spam' : 'new', 'spam' => ['score' => $spam['score'], 'reasons' => $spam['reasons']], 'ip_hash' => \App\Core\Request::ipHash()]);
        if ($spam['decision'] !== 'spam') {
            Notify::admin('contact', 'Contact : ' . ($in['subject'] ?: $in['kind']), $in['name'] . ' <' . $in['email'] . '> : ' . Str::limit($in['message'], 200), Url::admin('contacts'), 'info');
            foreach (Mail::adminEmails() as $to) {
                $m = Mail::build('site_contact', ['sujet' => $in['subject'] ?: 'Message de ' . $in['name']], ['details' => Mail::details(['Nom' => $in['name'], 'Email' => $in['email'], 'Profil' => $in['kind'], 'Sujet' => $in['subject'], 'Message' => $in['message']])]);
                Mail::queue($to, $m['subject'], $m['html'], ['reply_to' => $in['email'], 'priority' => 2]);
            }
        }
        return $this->ajax(true, 'Merci ' . $in['name'] . ', votre message est bien arrivé ! Nous vous répondons rapidement.');
    }

    private function ajax(bool $ok, string $message, array $errors = [], int $status = 422): Response
    {
        if (Request::isAjax()) {
            return Response::json(['ok' => $ok, 'message' => $message, 'error' => $ok ? null : $message, 'errors' => $errors], $ok ? 200 : $status);
        }
        Session::flash($ok ? 'success' : 'error', $message);
        if (!$ok) {
            Session::withInput($_POST, $errors);
        }
        return $this->redirect('/contact/');
    }

    /** Plan du site : métiers, régions, départements, villes (maillage interne). */
    public function sitemap(): Response
    {
        $deps = [];
        $cities = [];
        foreach (Pros::publicIndex() as $p) {
            foreach (array_unique(array_filter(array_merge([$p['dep']], $p['zones']))) as $d) {
                $deps[$d] = ($deps[$d] ?? 0) + 1;
            }
            if ($p['insee']) {
                $cities[$p['insee']] = ($cities[$p['insee']] ?? 0) + 1;
            }
        }
        $cityLinks = [];
        foreach (array_keys($cities) as $insee) {
            $c = Geo::commune((string) $insee);
            if ($c) {
                $cityLinks[$c['n'] . ' (' . $c['d'] . ')'] = Url::city(null, (string) $insee);
            }
        }
        ksort($cityLinks, SORT_NATURAL | SORT_FLAG_CASE);
        Stats::hit('pv');
        return $this->view('front/plan', [
            'deps' => $deps,
            'cities' => $cityLinks,
            'meta' => ['title' => 'Plan du site : tous les métiers, régions et villes', 'description' => 'Trouvez un DJ, un magicien, un animateur ou un groupe dans votre région, votre département ou votre ville.', 'canonical' => Url::abs('/plan-du-site/')],
        ]);
    }

    /** « Je suis un pro » : avantages, fonctionnement, FAQ. */
    public function pros(): Response
    {
        $n = \App\Services\Stats::publicNumbers();
        Stats::hit('pv');
        return $this->view('front/professionnels', [
            'numbers' => $n,
            'meta' => [
                'title' => 'Professionnels de l\'animation : inscription gratuite sur l\'annuaire',
                'description' => 'DJ, animateurs, groupes, magiciens : créez votre fiche gratuitement et recevez des demandes de devis de clients près de chez vous. Sans commission.',
                'canonical' => Url::abs('/professionnels/'),
                'jsonld' => [Seo::faq([
                    ['L\'inscription est-elle vraiment gratuite ?', 'Oui. La fiche, les demandes de devis et les messages sont gratuits et sans commission. Le site est financé par la publicité.'],
                    ['Comment reçoit-on les demandes de devis ?', 'Par email (et en notification sur votre téléphone si vous installez l\'application), dès qu\'un client de votre zone dépose une demande correspondant à vos métiers.'],
                    ['Faut-il être déclaré ?', 'Oui, l\'annuaire référence uniquement des professionnels déclarés (SIREN, GUSO ou statut adapté).'],
                ])],
            ],
        ]);
    }
}
