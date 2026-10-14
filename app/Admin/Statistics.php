<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\Stats;
use App\Services\StatsReport;

/**
 * Pilotage › Statistiques (administrateurs) : audience du musée en temps réel et sur une période
 * (filtres), graphiques, classements, rapport PDF et remise à zéro avant l'ouverture.
 */
final class Statistics extends Base
{
    /** « m=equipe » : visites des membres connectés, comptées à part (même avant l'ouverture). */
    private static function scope(Request $req): string
    {
        $m = (string) ($req->query['m'] ?? $req->post['m'] ?? '') === 'equipe' ? 'equipe' : 'public';
        Stats::scope($m);
        return $m;
    }

    private static function range(Request $req): array
    {
        self::scope($req);
        return StatsReport::period((string) ($req->query['p'] ?? '30j'), (string) ($req->query['du'] ?? ''), (string) ($req->query['au'] ?? ''));
    }

    public static function index(Request $req): Response
    {
        [$from, $to, $label] = self::range($req);
        $m = self::scope($req);
        $r = StatsReport::build($from, $to);
        return self::html('admin/statistiques', ['r' => $r, 'label' => $label, 'preset' => (string) ($req->query['p'] ?? (isset($req->query['du']) ? '' : '30j')),
            'm' => $m, 'live' => Stats::live(), 'resetAt' => Stats::resetAt(), 'team' => $m === 'equipe' ? StatsReport::team($from, $to) : null], ['title' => 'Statistiques', 'crumb' => 'Pilotage', 'nav' => 'stats']);
    }

    /** GET /admin/statistiques/direct : temps réel (rafraîchi toutes les 15 s par la page). */
    public static function live(Request $req): Response
    {
        self::scope($req);
        return self::json(Stats::live());
    }

    /** GET /admin/statistiques/rapport.pdf : rapport de la période filtrée. */
    public static function pdf(Request $req): Response
    {
        [$from, $to, $label] = self::range($req);
        $pdf = \App\Pdf\StatsPdf::build(StatsReport::build($from, $to), $label);
        return new Response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="statistiques-musee-' . $from . '-' . $to . '.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    /** POST /admin/statistiques/remise-a-zero : tout repart de zéro (anciennes données mises de côté). */
    public static function reset(Request $req): Response
    {
        if (mb_strtoupper(trim((string) ($req->post['confirm'] ?? ''))) !== 'REMETTRE A ZERO') {
            return self::back('/admin/statistiques', null, 'Tapez REMETTRE A ZERO pour confirmer.');
        }
        $m = self::scope($req);
        $dir = Stats::reset();
        \App\Data\Activity::log(self::actor(), $m === 'equipe' ? 'a remis à zéro les statistiques de l\'équipe' : 'a remis à zéro les statistiques du musée', ['path' => '/admin/statistiques']);
        return self::back('/admin/statistiques' . ($m === 'equipe' ? '?m=equipe' : ''), 'Statistiques remises à zéro. Les anciennes données sont gardées de côté (storage/' . $dir . ').');
    }
}
