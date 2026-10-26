<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Services\TicketFiles;
use App\Services\Tickets;

/**
 * Tickets : toute l'équipe signale un bug, demande une amélioration ou pose une question (avec,
 * si elle le souhaite, le prompt à donner à l'IA). Tout le monde voit tous les tickets du musée ;
 * seul l'administrateur change le statut et la priorité.
 */
final class TicketsAdmin extends Base
{
    private const META = ['crumb' => 'Équipe', 'nav' => 'tickets', 'help' => 'tickets', 'scripts' => ['admin/pieces-jointes.js']];

    public static function index(Request $req): Response
    {
        $user = Auth::user() ?? [];
        $all = Tickets::all();
        $f = [
            'etat' => $req->str('etat', 'ouverts'), 'type' => $req->str('type'),
            'qui' => $req->str('qui'), 'q' => mb_strtolower($req->str('q')),
        ];
        $counts = array_fill_keys(array_keys(Tickets::STATES), 0);
        foreach ($all as $t) {
            $counts[$t['status']]++;
        }
        $list = array_values(array_filter($all, fn ($t) => match ($f['etat']) {
            'ouverts' => in_array($t['status'], Tickets::OPEN, true),
            'tous' => true,
            'miens' => ($t['author']['id'] ?? '') === ($user['id'] ?? null),
            default => $t['status'] === $f['etat'],
        } && ($f['type'] === '' || $t['type'] === $f['type'])
          && ($f['qui'] === '' || ($t['author']['name'] ?? '') === $f['qui'])
          && ($f['q'] === '' || str_contains(mb_strtolower($t['title'] . ' ' . $t['body']), $f['q']))));
        // Les urgents d'abord, puis du plus récent au plus ancien.
        $rank = ['bloquante' => 0, 'urgente' => 1, 'normale' => 2];
        usort($list, fn ($a, $b) => [in_array($a['status'], Tickets::OPEN, true) ? 0 : 1, $rank[$a['priority']], -$a['id']] <=> [in_array($b['status'], Tickets::OPEN, true) ? 0 : 1, $rank[$b['priority']], -$b['id']]);
        $authors = array_values(array_unique(array_map(fn ($t) => $t['author']['name'] ?? '', $all)));
        sort($authors);
        return self::html('admin/tickets/index', [
            'list' => $list, 'counts' => $counts, 'f' => $f, 'authors' => $authors, 'me' => $user,
            'total' => count($all), 'admin' => Auth::isAdmin(),
            'page' => self::fromPage($req),
        ], ['title' => 'Tickets'] + self::META);
    }

    public static function show(Request $req, string $id): Response
    {
        $t = Tickets::get((int) $id);
        if (!$t) {
            return self::back('/admin/tickets', null, 'Ce ticket n’existe pas (ou plus).');
        }
        $user = Auth::user() ?? [];
        $unread = Tickets::unread($t, $user);
        Tickets::markSeen($t['id'], $user);
        return self::html('admin/tickets/show', [
            't' => $t, 'admin' => Auth::isAdmin(), 'canEdit' => Tickets::canEdit($t, $user),
            'prompt' => Tickets::promptFor($t), 'unread' => $unread, 'edit' => $req->str('modifier') === '1',
        ], ['title' => 'Ticket n° ' . $t['id'], 'crumb_html' => 'Équipe › <a href="/admin/tickets">Tickets</a>'] + self::META);
    }

    /** POST /admin/tickets : création. */
    public static function create(Request $req): Response
    {
        $user = self::actor();
        try {
            $t = Tickets::create(self::fields($req), $user, $req->files['pj'] ?? null);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return self::back('/admin/tickets', null, $e->getMessage());
        }
        Activity::log($user, 'a ouvert un ticket', ['title' => 'n° ' . $t['id'] . ' · ' . $t['title'], 'path' => '']);
        return self::back('/admin/tickets/' . $t['id'], 'Ticket n° ' . $t['id'] . ' envoyé. Vous serez prévenu par e-mail à chaque réponse ou changement de statut.');
    }

    /** POST /admin/tickets/{id} : modifier, commenter, changer le statut, supprimer. */
    public static function action(Request $req, string $id): Response
    {
        $id = (int) $id;
        $user = self::actor();
        $url = '/admin/tickets/' . $id;
        try {
            switch ($req->str('action')) {
                case 'modifier':
                    Tickets::edit($id, self::fields($req), $user, $req->files['pj'] ?? null, array_map('strval', (array) ($req->post['pj_retirer'] ?? [])));
                    return self::back($url, 'Ticket modifié.');
                case 'commenter':
                    Tickets::comment($id, $req->str('texte'), $user, $req->files['pj'] ?? null);
                    return self::back($url . '#fil', 'Réponse envoyée.');
                case 'statut':
                    $t = Tickets::setStatus($id, $req->str('status'), $req->str('priority'), $req->str('texte'), $user);
                    Activity::log($user, 'a mis à jour un ticket', ['title' => 'n° ' . $id . ' · ' . Tickets::STATES[$t['status']], 'path' => '']);
                    return self::back($url, 'Ticket mis à jour : ' . Tickets::STATES[$t['status']] . '.');
                case 'supprimer':
                    if ($deny = self::denyUnlessAdmin()) {
                        return $deny;
                    }
                    Tickets::delete($id);
                    Activity::log($user, 'a supprimé un ticket', ['title' => 'n° ' . $id, 'path' => '']);
                    return self::back('/admin/tickets', 'Ticket n° ' . $id . ' supprimé.');
            }
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return self::back($url, null, $e->getMessage());
        }
        return self::back($url, null, 'Action inconnue.');
    }

    /**
     * GET /admin/tickets/{id}/pj/{fid} : une pièce jointe, pour les membres connectés du back-office.
     * Images, PDF et vidéos montrés dans le navigateur, le reste téléchargé ; jamais interprété comme
     * une page (type imposé, « nosniff », bac à sable).
     */
    public static function file(Request $req, string $id, string $fid): Response
    {
        $t = Tickets::get((int) $id);
        $f = $t ? TicketFiles::find($t, $fid) : null;
        $path = $f ? TicketFiles::path((int) $id, $f) : null;
        if (!$path) {
            return new Response('Pièce jointe introuvable.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        $inline = TicketFiles::inline($f) && $req->str('telecharger') !== '1';
        $name = (string) $f['name'];
        $ascii = (string) preg_replace('/[^\x20-\x7E]|["\\]/', '_', $name);
        return new Response((string) file_get_contents($path), 200, [
            'Content-Type' => $inline ? (string) $f['mime'] : 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; media-src 'self'; object-src 'self'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    private static function fields(Request $req): array
    {
        return ['title' => $req->str('title'), 'type' => $req->str('type'), 'priority' => $req->str('priority'),
            'body' => (string) ($req->post['body'] ?? ''), 'prompt' => (string) ($req->post['prompt'] ?? ''), 'page' => $req->str('page')];
    }

    /** Écran d'où l'on vient (bouton « Ticket » du menu) : proposé dans le champ « Écran concerné ». */
    private static function fromPage(Request $req): string
    {
        $p = $req->str('depuis');
        return str_starts_with($p, '/admin') && !str_starts_with($p, '/admin/tickets') ? mb_substr($p, 0, 300) : '';
    }
}
