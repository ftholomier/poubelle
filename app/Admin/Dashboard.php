<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Data\Activity;
use App\Data\Index;
use App\Front\Community as FrontCommunity;
use App\Front\Donations;
use App\Front\Interactive;
use App\Front\Site;
use App\Services\Stats;

/** Pilotage : tableau de bord, qualité, journal d'activité, audience. */
final class Dashboard extends Base
{
    public static function index(Request $req): Response
    {
        $counts = ['match' => 0, 'personne' => 0, 'article' => 0, 'objet' => 0, 'moment' => 0, 'page' => 0];
        $month = 0;
        $noBirth = 0;
        $toReview = 0;
        $scheduled = [];
        foreach (Index::all() as $s) {
            if ($s['status'] === 'corbeille') {
                continue;
            }
            $counts[$s['type']] = ($counts[$s['type']] ?? 0) + 1;
            if (($s['date'] ?? '') >= date('Y-m-01')) {
                $month++;
            }
            if ($s['type'] === 'personne' && empty($s['p']['birth_place'])) {
                $noBirth++;
            }
            if ($s['status'] === 'relire') {
                $toReview++;
            }
            if ($s['status'] === 'planifie') {
                $scheduled[] = $s;
            }
        }
        usort($scheduled, fn ($a, $b) => strcmp((string) $a['publish_at'], (string) $b['publish_at']));
        $quality = Quality::all();
        $high = count(array_filter($quality['stats'], fn ($q) => $q['sev'] === 'haute'));
        $contribs = array_filter(Community::contributionList(), fn ($c) => ($c['status'] ?? 'nouveau') === 'nouveau');
        $oldest = $contribs ? min(array_map(fn ($c) => strtotime($c['at'] ?? 'now'), $contribs)) : null;
        $messages = array_filter(Community::messageList(), fn ($m) => ($m['status'] ?? 'nouveau') === 'nouveau');
        $subs = count(FrontCommunity::subscribers());
        $gauge = Donations::gauge();
        $monthDons = 0;
        foreach (Donations::all() as $d) {
            foreach ($d['payments'] ?? [] as $p) {
                if ($p['status'] === 'paid' && substr((string) $p['at'], 0, 7) === date('Y-m')) {
                    $monthDons += (int) $p['amount'];
                }
            }
        }
        $todos = [];
        if ($high) {
            $todos[] = ['#D9342B', "Corriger $high alerte" . ($high > 1 ? 's' : '') . ' qualité haute (statistiques incohérentes)', 'Qualité', '/admin/qualite'];
        }
        if ($contribs) {
            $todos[] = ['#F6C400', 'Valider ' . count($contribs) . ' contribution' . (count($contribs) > 1 ? 's' : ''), 'Contributions', '/admin/contributions'];
        }
        if ($messages) {
            $todos[] = ['#1F3FA8', 'Répondre à ' . count($messages) . ' message' . (count($messages) > 1 ? 's' : ''), 'Messages', '/admin/messages'];
        }
        if ($toReview) {
            $todos[] = ['#1F3FA8', "Relire $toReview fiche" . ($toReview > 1 ? 's' : '') . ' « à relire »', 'Fiches', '/admin/matchs?statut=relire'];
        }
        if (count($quality['credits'])) {
            $todos[] = ['#F6C400', 'Créditer ' . (count($quality['credits']) >= 500 ? '500+' : count($quality['credits'])) . ' photos', 'Médias', '/admin/medias?filtre=sans-credit'];
        }
        $noFiche = count(array_filter($quality['liens'], fn ($q) => $q['id'] === null));
        if ($noFiche) {
            $todos[] = ['#1F3FA8', 'Créer les fiches de ' . $noFiche . ' joueurs cités sans fiche', 'Qualité', '/admin/qualite?cat=liens'];
        }
        if (!\App\Services\Gemini::ready() && \App\Core\Auth::isAdmin()) {
            $todos[] = ['#F6C400', 'Saisir la clé Gemini (assistant IA, traductions)', 'Réglages', '/admin/reglages?groupe=ai'];
        }
        if (!(string) Settings::get('general.contact_email', '') && \App\Core\Auth::isAdmin()) {
            $todos[] = ['#D9342B', 'Renseigner l’e-mail de contact (messages, contributions)', 'Réglages', '/admin/reglages?groupe=general'];
        }
        $moments = Interactive::moments100();
        $published = count(array_filter($moments, fn ($m) => !empty($m['open'])));
        $onze = Interactive::onzeVoters();
        return self::html('admin/dashboard', [
            'counts' => $counts, 'month' => $month, 'noBirth' => $noBirth, 'high' => $high, 'qualityTotal' => count($quality['stats']),
            'contribs' => count($contribs), 'oldest' => $oldest, 'subs' => $subs, 'gauge' => $gauge, 'monthDons' => $monthDons,
            'todos' => $todos, 'activity' => Activity::recent(10), 'series' => Stats::series(30), 'top' => Stats::top(30, 5),
            'days' => Site::daysToCentenary(), 'momentsPub' => $published, 'onze' => $onze, 'scheduled' => array_slice($scheduled, 0, 5),
        ], ['title' => 'Tableau de bord', 'crumb' => 'Pilotage', 'nav' => 'dash']);
    }

    public static function quality(Request $req): Response
    {
        $all = Quality::all();
        $cat = isset($all[$req->str('cat')]) ? $req->str('cat') : 'stats';
        $sev = $req->str('niveau');
        $items = array_values(array_filter($all[$cat], fn ($i) => $sev === '' || $i['sev'] === $sev));
        return self::html('admin/quality', ['all' => $all, 'cat' => $cat, 'sev' => $sev, 'items' => array_slice($items, 0, 400), 'total' => count($items)], ['title' => 'Qualité', 'crumb' => 'Pilotage', 'nav' => 'qualite']);
    }

    public static function journal(Request $req): Response
    {
        $who = $req->str('qui');
        $rows = Activity::recent(800);
        $people = array_values(array_unique(array_column($rows, 'by')));
        sort($people);
        if ($who !== '') {
            $rows = array_values(array_filter($rows, fn ($r) => ($r['by'] ?? '') === $who));
        }
        return self::html('admin/journal', ['rows' => array_slice($rows, 0, 300), 'people' => $people, 'who' => $who], ['title' => 'Journal', 'crumb' => 'Pilotage', 'nav' => 'journal']);
    }

    public static function audience(Request $req): Response
    {
        return self::html('admin/audience', ['series' => Stats::series(90), 'top' => Stats::top(30, 40), 'en' => Stats::englishShare(30)], ['title' => 'Audience', 'crumb' => 'Pilotage', 'nav' => 'dash']);
    }
}
