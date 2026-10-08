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
use App\Services\Controle;
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
        // Hébergement à régler (version de PHP, extensions, droits des dossiers) : en tête de liste.
        if (\App\Core\Auth::isAdmin() && ($srv = \App\Services\ServerCheck::problems())) {
            $todos[] = ['#D9342B', 'Régler le serveur : ' . rtrim($srv[0], '.') . (count($srv) > 1 ? ' (et ' . (count($srv) - 1) . ' autre' . (count($srv) > 2 ? 's' : '') . ' réglage' . (count($srv) > 2 ? 's' : '') . ')' : ''), 'Serveur', '/admin/taches#serveur'];
        }
        // Site ouvert au public mais toujours masqué aux moteurs de recherche : à ne pas oublier.
        if (\App\Core\Auth::isAdmin() && !\App\Front\Seo::closed() && \App\Core\Settings::get('general.noindex', false)) {
            $todos[] = ['#F6C400', 'Le site est ouvert mais masqué aux moteurs de recherche : décocher « Masquer le site aux moteurs de recherche » quand il doit être trouvé sur Google', 'Réglages', '/admin/reglages?groupe=general'];
        }
        // Anomalies apparues depuis le dernier contrôle complet (bouton « Contrôler maintenant »).
        $fresh = array_sum(array_map(fn ($l) => count(array_filter($l, fn ($i) => $i['new'])), $quality));
        if ($fresh) {
            $since = Controle::state()['since'];
            $todos[] = ['#D9342B', 'Vérifier ' . $fresh . ' nouvelle' . ($fresh > 1 ? 's' : '') . ' anomalie' . ($fresh > 1 ? 's' : '') . ($since ? ' depuis ' . Controle::sinceLabel($since) : ''), 'Qualité', '/admin/qualite?nouveau=1'];
        }
        if ($high) {
            $todos[] = ['#D9342B', "Corriger $high alerte" . ($high > 1 ? 's' : '') . ' grave' . ($high > 1 ? 's' : '') . ' (statistiques et dates incohérentes)', 'Qualité', '/admin/qualite?cat=stats&niveau=haute'];
        }
        $broken = count(array_filter($quality['site'], fn ($q) => $q['sev'] === 'haute' && $q['code'] !== 'photos'));
        if ($broken) {
            $todos[] = ['#D9342B', "Corriger $broken anomalie" . ($broken > 1 ? 's' : '') . ' grave' . ($broken > 1 ? 's' : '') . ' d’adresse, de fichier ou de référentiel', 'Qualité', '/admin/qualite?cat=site&niveau=haute'];
        }
        // Autres onglets (textes de l'interface anglaise…), hors orthographe relue en tâche de fond.
        $other = 0;
        $otherTab = null;
        foreach ($quality as $tab => $list) {
            $n = in_array($tab, ['stats', 'site', 'orthographe'], true) ? 0 : count(array_filter($list, fn ($q) => $q['sev'] === 'haute'));
            $other += $n;
            $otherTab ??= $n ? $tab : null;
        }
        if ($other) {
            $todos[] = ['#D9342B', "Corriger $other autre" . ($other > 1 ? 's' : '') . ' alerte' . ($other > 1 ? 's' : '') . ' grave' . ($other > 1 ? 's' : '') . ' (' . mb_strtolower(Quality::TABS[$otherTab][0]) . ')', 'Qualité', '/admin/qualite?cat=' . $otherTab . '&niveau=haute'];
        }
        // Serveur neuf : photos originales pas encore copiées depuis WordPress (administrateurs).
        if (\App\Core\Auth::isAdmin() && array_filter($quality['site'], fn ($q) => $q['code'] === 'photos')) {
            $todos[] = ['#D9342B', 'Copier les photos originales sur le serveur (copie depuis WordPress à faire ou à terminer)', 'Qualité', '/admin/qualite?cat=site'];
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
            $todos[] = ['#F6C400', 'Créditer ' . number_format(count($quality['credits']), 0, ',', ' ') . ' photo' . (count($quality['credits']) > 1 ? 's' : ''), 'Médias', '/admin/medias?filtre=sans-credit'];
        }
        $spell = count(array_filter($quality['orthographe'], fn ($q) => $q['sev'] !== 'basse'));
        if ($spell) {
            $todos[] = ['#1F3FA8', 'Corriger l’orthographe de ' . $spell . ' fiche' . ($spell > 1 ? 's' : '') . ' (corrections proposées)', 'Qualité', '/admin/qualite?cat=orthographe'];
        }
        $noFiche = count(array_filter($quality['liens'], fn ($q) => $q['id'] === null));
        if ($noFiche) {
            $todos[] = ['#1F3FA8', 'Créer les fiches de ' . $noFiche . ' joueurs cités sans fiche', 'Qualité', '/admin/qualite?cat=liens'];
        }
        // Site d'essai sur un sous-domaine : les liens des e-mails doivent y mener aussi.
        if (\App\Core\Auth::isAdmin() && ($mis = self::addressMismatch($req))) {
            $todos[] = ['#D9342B', 'Régler l’adresse du site : les liens des e-mails (invitations, mot de passe oublié) mènent à ' . $mis[0] . ', alors que le site est ouvert sur ' . $mis[1], 'Réglages', '/admin/reglages?groupe=general'];
        }
        if (\App\Core\Auth::isAdmin() && ($maj = \App\Services\Updater::available())) {
            $todos[] = ['#F6C400', \App\Services\Updater::summary($maj), 'Mises à jour', '/admin/mises-a-jour'];
        }
        if (!\App\Services\Gemini::ready() && \App\Core\Auth::isAdmin()) {
            $todos[] = ['#F6C400', 'Saisir la clé Gemini (assistant IA, traductions)', 'Réglages', '/admin/reglages?groupe=ai'];
        }
        if (\App\Core\Auth::isAdmin()) {
            // Frais d'IA avancés : mois terminés pas encore remboursés, budget du mois atteint.
            $due = \App\Services\AiCosts::toReimburse(false);
            if ($due['eur'] >= 0.01) {
                $todos[] = ['#F6C400', 'Faire rembourser ' . number_format($due['eur'], 2, ',', ' ') . ' € de frais d’IA (' . implode(', ', array_map([Costs::class, 'monthLabel'], $due['months'])) . ')', 'Coûts IA', '/admin/couts-ia#mois'];
            }
            if (\App\Services\AiCosts::budget()['over']) {
                $todos[] = ['#D9342B', 'Budget IA du mois atteint' . (\App\Services\AiCosts::paused('correcteur') ? ' : tâches automatiques en pause' : ''), 'Coûts IA', '/admin/couts-ia'];
            }
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
        // « Nouvelles » : les anomalies apparues depuis le contrôle précédent, tous onglets confondus.
        $fresh = $req->str('nouveau') === '1';
        $newCounts = array_map(fn ($l) => count(array_filter($l, fn ($i) => $i['new'])), $all);
        $source = $fresh ? array_merge(...array_values($all)) : $all[$cat];
        $items = array_values(array_filter($source, fn ($i) => ($sev === '' || $i['sev'] === $sev) && (!$fresh || $i['new'])));
        if ($fresh) {
            // Les plus graves d'abord, dans l'ordre des onglets à gravité égale.
            $rank = ['haute' => 0, 'moyenne' => 1, 'basse' => 2];
            $keyed = [];
            foreach ($items as $n => $i) {
                $keyed[] = [$rank[$i['sev']] ?? 3, $n, $i];
            }
            usort($keyed, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
            $items = array_column($keyed, 2);
        }
        // 300 alertes par page (toutes restent accessibles).
        $per = 300;
        $pages = max(1, (int) ceil(count($items) / $per));
        $page = min($pages, max(1, (int) $req->str('page')));
        return self::html('admin/quality', ['all' => $all, 'cat' => $cat, 'sev' => $sev, 'fresh' => $fresh, 'newCounts' => $newCounts,
            'items' => array_slice($items, ($page - 1) * $per, $per), 'total' => count($items), 'page' => $page, 'pages' => $pages,
            'last' => Controle::last(), 'state' => Controle::state(),
            'proof' => !$fresh && $cat === 'orthographe' ? \App\Services\Proofreader::summary() : null], ['title' => 'Qualité', 'crumb' => 'Pilotage', 'nav' => 'qualite']);
    }

    /** Bouton « Contrôler maintenant » : contrôle complet, puis liste des nouvelles anomalies. */
    public static function control(Request $req): Response
    {
        // Le contrôle occupe le serveur quelques secondes : quatre au plus en deux minutes par personne.
        $actor = self::actor();
        if (!\App\Core\RateLimiter::hit('controle', (string) ($actor['id'] ?? $actor['name'] ?? ''), 4, 120)) {
            return self::back('/admin/qualite', null, 'Plusieurs contrôles viennent d’être lancés : réessayez dans deux minutes.');
        }
        // Session libérée pendant le contrôle : les autres onglets du back-office restent utilisables.
        session_write_close();
        $r = Controle::run($actor);
        if (!empty($r['busy'])) {
            return self::back('/admin/qualite', null, 'Un contrôle est déjà en cours : réessayez dans quelques secondes.');
        }
        $msg = 'Contrôle terminé en ' . number_format($r['ms'] / 1000, 1, ',', ' ') . ' s : ' . Controle::counts($r) . '.'
            . ($r['repaired'] ? ' Index des fiches remis à jour (' . $r['repaired'] . ' fiche' . ($r['repaired'] > 1 ? 's' : '') . ' modifiée' . ($r['repaired'] > 1 ? 's' : '') . ' hors du back-office).' : '');
        return self::back('/admin/qualite' . ($r['new'] ? '?nouveau=1' : ''), $msg);
    }

    /** POST /admin/qualite/orthographe-tout : applique toutes les corrections trouvées, fiche par fiche. */
    public static function proofAll(Request $req): Response
    {
        if ($deny = self::denyUnlessAdmin()) {
            return $deny;
        }
        session_write_close();
        @set_time_limit(300);
        $lang = $req->str('portee') === 'langue';
        $fiches = $applied = $skipped = 0;
        foreach (\App\Services\Proofreader::summary()['rows'] as $r) {
            if (\App\Services\QualityAck::acked((int) $r['id'], 'orthographe', '')) {
                continue;
            }
            $x = \App\Services\Proofreader::applyStored((int) $r['id'], self::actor(), $lang);
            $applied += $x['applied'];
            $skipped += $x['skipped'];
            $fiches += $x['applied'] ? 1 : 0;
        }
        \App\Services\Activity::log(self::actor(), 'a appliqué ' . $applied . ' correction(s) d’orthographe dans ' . $fiches . ' fiche(s)', null);
        return self::back('/admin/qualite?cat=orthographe', $applied . ' correction(s) appliquée(s) dans ' . $fiches . ' fiche(s)' . ($skipped ? ' ; ' . $skipped . ' laissée(s) de côté (texte modifié depuis, ou passage à cheval sur une mise en forme) : à faire à la main dans la fiche' : '') . '. Les fiches corrigées sont revérifiées par la tâche de fond.');
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
