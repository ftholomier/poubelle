<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Pdf\Livre;

/**
 * Contenus › Livre des récits (administrateurs) : compose le livre « 100 récits du Lion » en PDF
 * prêt à imprimer (App\Pdf\Livre), personnalisé, et affiche le bilan des photos de la dernière composition.
 */
final class Book extends Base
{
    private const LAST = STORAGE_PATH . '/livres/dernier.json';

    public static function index(Request $req): Response
    {
        if ($r = self::denyUnlessAdmin()) {
            return $r;
        }
        $last = json_decode((string) @file_get_contents(self::LAST), true) ?: null;
        $groups = (new Livre(['relire' => true]))->recits();
        $count = ['publie' => 0, 'relire' => 0];
        foreach ($groups as $items) {
            foreach ($items as $it) {
                $count[($it['doc']['status'] ?? '') === 'publie' ? 'publie' : 'relire']++;
            }
        }
        return self::html('admin/fiches/livre', ['last' => $last, 'decades' => array_keys($groups), 'count' => $count],
            ['title' => 'Livre des récits', 'crumb' => 'Contenus', 'nav' => 'livre']);
    }

    /** POST : compose le livre et le renvoie en téléchargement. */
    public static function build(Request $req): Response
    {
        if ($r = self::denyUnlessAdmin()) {
            return $r;
        }
        @set_time_limit(600);
        @ini_set('memory_limit', '1024M');
        $dec = array_values(array_filter(array_map('intval', (array) ($req->post['decennies'] ?? []))));
        $o = [
            'nom' => mb_substr(trim((string) ($req->post['nom'] ?? '')), 0, 60),
            'dedicace' => mb_substr(trim((string) ($req->post['dedicace'] ?? '')), 0, 600),
            'signature' => mb_substr(trim((string) ($req->post['signature'] ?? '')), 0, 80),
            'numero' => mb_substr(trim((string) ($req->post['numero'] ?? '')), 0, 12),
            'relire' => !empty($req->post['relire']),
            'decennies' => $dec,
        ];
        $book = new Livre($o);
        $t = microtime(true);
        $pdf = $book->build();
        $rep = $book->report() + ['at' => date('c'), 'seconds' => round(microtime(true) - $t, 1), 'size' => strlen($pdf), 'pages' => preg_match_all('#/Type /Page\b#', $pdf), 'options' => $o];
        @mkdir(dirname(self::LAST), 0775, true);
        file_put_contents(self::LAST, json_encode($rep, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        Activity::log(self::actor(), 'a composé le livre des récits (' . $rep['pages'] . ' pages)', null);
        $name = 'livre-100-recits' . ($o['nom'] !== '' ? '-' . slugify($o['nom']) : '') . '-' . date('Ymd') . '.pdf';
        return new Response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $name . '"', 'Cache-Control' => 'no-store']);
    }
}
