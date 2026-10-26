<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Club;
use App\Core\JsonStore;

/**
 * Tickets de l'équipe : bugs, demandes d'amélioration et questions sur le musée, avec un
 * « prompt pour l'IA » que l'auteur imagine pour le développement. Seuls les administrateurs
 * changent le statut ou la priorité. Chaque changement et chaque réponse sont envoyés par
 * e-mail (auteur + administrateurs, sauf la personne qui agit).
 * Fichier : storage/tickets.json (propre à chaque musée) : {"next": prochain numéro, "items": [...]}
 * (l'ancien format, une simple liste, est encore lu). Le compteur ne redescend jamais : un numéro
 * n'est pas redonné après la suppression d'un ticket.
 */
final class Tickets
{
    public const FILE = STORAGE_PATH . '/tickets.json';

    public const TYPES = ['bug' => '🐞 Correction de bug', 'amelioration' => '✨ Demande d’amélioration', 'question' => '❓ Question'];
    public const PRIORITIES = ['normale' => 'Normale', 'urgente' => 'Urgente', 'bloquante' => 'Bloquante'];
    public const STATES = [
        'nouveau' => 'Nouveau', 'pris' => 'Pris en compte', 'en-cours' => 'En cours',
        'a-verifier' => 'À vérifier', 'termine' => 'Terminé', 'refuse' => 'Refusé / sans suite',
    ];
    /** Statuts encore ouverts (comptés dans les pastilles). */
    public const OPEN = ['nouveau', 'pris', 'en-cours', 'a-verifier'];

    /** @var null|callable(string,string,string):bool envoi d'e-mail remplaçable (tests) */
    public static $mailer = null;

    /** Tous les tickets, du plus récent au plus ancien. @return list<array> */
    public static function all(): array
    {
        // Lu une fois par page (pastille du menu, liste, ticket ouvert) ; oublié à chaque écriture.
        if (self::$cache === null) {
            $all = self::unpack(JsonStore::read(self::FILE, []))[0];
            usort($all, fn ($a, $b) => $b['id'] <=> $a['id']);
            self::$cache = $all;
        }
        return self::$cache;
    }

    /** @var ?list<array> */
    private static ?array $cache = null;

    public static function get(int $id): ?array
    {
        foreach (self::all() as $t) {
            if ($t['id'] === $id) {
                return $t;
            }
        }
        return null;
    }

    /**
     * Nouveau ticket, avec ses pièces jointes ($upload : champ « pj[] » envoyé).
     * @param array{title?:string,type?:string,priority?:string,body?:string,prompt?:string,page?:string} $in
     */
    public static function create(array $in, array $user, mixed $upload = null): array
    {
        $f = self::clean($in);
        $pending = TicketFiles::check($upload);
        $now = date('c');
        $t = self::store(function (array $all, int &$next) use ($f, $user, $now, $pending) {
            $id = $next++;
            $all[] = $f + [
                'id' => $id, 'status' => 'nouveau', 'author' => self::who($user),
                'created_at' => $now, 'updated_at' => $now, 'comments' => [], 'files' => TicketFiles::store($id, $pending, $user),
                'history' => [['at' => $now, 'by' => $user['name'] ?? '', 'what' => 'a ouvert le ticket']],
                'seen' => [(string) ($user['id'] ?? '') => $now],
            ];
            return $all;
        });
        $t = end($t);
        self::notify($t, $user, 'Nouveau ticket', '<p><b>' . e($user['name'] ?? '') . '</b> a ouvert un ticket.</p>' . self::filesLine($t['files']) . self::summary($t));
        return $t;
    }

    /**
     * Modification du contenu : l'auteur tant que le ticket est « Nouveau », l'administrateur toujours.
     * Pièces jointes ajoutées ($upload) ou retirées ($remove : leurs identifiants).
     */
    public static function edit(int $id, array $in, array $user, mixed $upload = null, array $remove = []): array
    {
        $f = self::clean($in);
        $pending = TicketFiles::check($upload);
        $gone = [];
        $t = self::change($id, $user, function (array $t) use ($f, $user, $pending, $remove, &$gone) {
            if (!self::canEdit($t, $user)) {
                throw new \InvalidArgumentException('Ce ticket ne peut plus être modifié : ajoutez plutôt un commentaire.');
            }
            if (!Auth::isAdmin()) {
                $f['priority'] = $t['priority']; // la priorité reste celle choisie par l'administrateur
            }
            $files = [];
            foreach ((array) ($t['files'] ?? []) as $pj) {
                if (in_array($pj['id'] ?? '', $remove, true)) {
                    $gone[] = $pj;
                } else {
                    $files[] = $pj;
                }
            }
            $added = TicketFiles::store($t['id'], $pending, $user);
            if (array_intersect_key($t, $f) == $f && !$added && !$gone) {
                return [$t, null, null]; // rien de changé : ni historique ni e-mail
            }
            $label = 'a modifié le ticket' . ($added ? ' (' . count($added) . ' pièce(s) jointe(s) ajoutée(s))' : '') . ($gone ? ' (' . count($gone) . ' pièce(s) jointe(s) retirée(s))' : '');
            return [['files' => array_merge($files, $added)] + $f + $t, $label, '<p><b>' . e($user['name'] ?? '') . '</b> ' . e($label) . '.</p>' . self::filesLine($added)];
        });
        TicketFiles::remove($id, $gone);
        return $t;
    }

    /** Statut et priorité : administrateurs seulement. */
    public static function setStatus(int $id, string $status, string $priority, string $note, array $user): array
    {
        if (!Auth::isAdmin()) {
            throw new \InvalidArgumentException('Seul un administrateur change le statut d’un ticket.');
        }
        if (!isset(self::STATES[$status]) || !isset(self::PRIORITIES[$priority])) {
            throw new \InvalidArgumentException('Statut ou priorité inconnus.');
        }
        return self::change($id, $user, function (array $t) use ($status, $priority, $note, $user) {
            $what = [];
            if ($t['status'] !== $status) {
                $what[] = 'statut « ' . self::STATES[$status] . ' »';
            }
            if ($t['priority'] !== $priority) {
                $what[] = 'priorité « ' . self::PRIORITIES[$priority] . ' »';
            }
            if (!$what && trim($note) === '') {
                return [$t, null, null];
            }
            $t['status'] = $status;
            $t['priority'] = $priority;
            if (trim($note) !== '') {
                $t['comments'][] = ['at' => date('c'), 'by' => self::who($user), 'text' => self::text($note, 5000)];
            }
            $label = $what ? 'a passé le ticket en ' . implode(' et ', $what) : 'a répondu';
            $mail = '<p><b>' . e($user['name'] ?? '') . '</b> ' . e($label) . '.</p>' . (trim($note) !== '' ? '<blockquote>' . nl2br(e(trim($note))) . '</blockquote>' : '');
            return [$t, $label, $mail];
        });
    }

    /** Réponse, avec ses pièces jointes ($upload) : du texte ou au moins un fichier. */
    public static function comment(int $id, string $text, array $user, mixed $upload = null): array
    {
        $text = self::text($text, 5000);
        $pending = TicketFiles::check($upload);
        if ($text === '' && !$pending) {
            throw new \InvalidArgumentException('Le commentaire est vide.');
        }
        return self::change($id, $user, function (array $t) use ($text, $user, $pending) {
            $files = TicketFiles::store($t['id'], $pending, $user);
            $t['comments'][] = ['at' => date('c'), 'by' => self::who($user), 'text' => $text] + ($files ? ['files' => $files] : []);
            return [$t, 'a commenté', '<p><b>' . e($user['name'] ?? '') . '</b> a répondu :</p>' . ($text !== '' ? '<blockquote>' . nl2br(e($text)) . '</blockquote>' : '') . self::filesLine($files)];
        });
    }

    public static function delete(int $id): void
    {
        self::store(fn (array $all) => array_filter($all, fn ($t) => $t['id'] !== $id));
        TicketFiles::removeAll($id);
    }

    /** Le ticket est vu par l'utilisateur (sa pastille s'éteint). */
    public static function markSeen(int $id, array $user): void
    {
        $uid = (string) ($user['id'] ?? '');
        // Déjà vu depuis son dernier changement : rien à écrire (chaque ouverture réécrivait le fichier).
        $t = self::get($id);
        if ($uid === '' || !$t || !self::unread($t, $user)) {
            return;
        }
        self::store(function (array $all) use ($id, $uid, $user) {
            foreach ($all as $k => $t) {
                if ($t['id'] === $id && self::unread($t, $user)) {
                    $all[$k]['seen'][$uid] = date('c');
                }
            }
            return $all;
        });
    }

    /** Ticket changé depuis la dernière visite de l'utilisateur (et pas par lui). */
    public static function unread(array $t, array $user): bool
    {
        $seen = $t['seen'][(string) ($user['id'] ?? '')] ?? null;
        return !$seen || strtotime($t['updated_at']) > strtotime($seen);
    }

    /**
     * Pastille du menu : pour l'administrateur, les tickets nouveaux ou changés depuis sa visite ;
     * pour les autres, leurs tickets qui ont bougé (réponse, statut).
     */
    public static function badge(array $user): int
    {
        $n = 0;
        $admin = ($user['role'] ?? '') === 'admin';
        foreach (self::all() as $t) {
            $mine = ($t['author']['id'] ?? '') === ($user['id'] ?? null);
            if (($admin || $mine) && self::unread($t, $user)) {
                $n++;
            }
        }
        return $n;
    }

    public static function canEdit(array $t, array $user): bool
    {
        return Auth::isAdmin() || (($t['author']['id'] ?? '') === ($user['id'] ?? null) && $t['status'] === 'nouveau');
    }

    /** Ticket tel qu'il faut le copier pour le développement (titre, demande, prompt). */
    public static function promptFor(array $t): string
    {
        $out = 'Ticket n° ' . $t['id'] . ' · ' . strip_tags(self::TYPES[$t['type']] ?? '') . ' · ' . $t['title'] . "\n";
        if ($t['page'] !== '') {
            $out .= 'Écran concerné : ' . $t['page'] . "\n";
        }
        $out .= "\nDemande :\n" . $t['body'] . "\n";
        if ($t['prompt'] !== '') {
            $out .= "\nConsigne proposée par " . ($t['author']['name'] ?? 'l’auteur') . " :\n" . $t['prompt'] . "\n";
        }
        if ($files = TicketFiles::all($t)) {
            $out .= "\nPièces jointes (à ouvrir connecté au back-office) :\n";
            foreach ($files as $f) {
                $out .= '- ' . $f['name'] . ' : ' . rtrim(base_url(), '/') . self::fileUrl($t['id'], $f) . "\n";
            }
        }
        return $out;
    }

    /** Adresse d'une pièce jointe (réservée au back-office). */
    public static function fileUrl(int $ticket, array $f): string
    {
        return '/admin/tickets/' . $ticket . '/pj/' . rawurlencode((string) $f['id']);
    }

    /** Ligne « 📎 pièces jointes » d'un e-mail (les fichiers restent dans le back-office). */
    private static function filesLine(array $files): string
    {
        if (!$files) {
            return '';
        }
        return '<p>📎 ' . count($files) . ' pièce(s) jointe(s) : ' . e(implode(', ', array_column($files, 'name'))) . ' (à ouvrir dans le ticket).</p>';
    }

    // ── Interne ────────────────────────────────────────────────────────────

    /**
     * Tickets et prochain numéro, quel que soit le format du fichier (liste d'avant le compteur, ou
     * {"next", "items"}). Le prochain numéro n'est jamais inférieur au plus grand numéro + 1.
     * @return array{0:list<array>,1:int}
     */
    private static function unpack(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $items = array_key_exists('items', $raw) ? (array) $raw['items'] : (array_is_list($raw) ? $raw : []);
        $items = array_values(array_filter($items, fn ($t) => is_array($t) && isset($t['id'])));
        $max = $items ? max(array_map('intval', array_column($items, 'id'))) : 0;
        return [$items, max((int) ($raw['next'] ?? 0), $max + 1)];
    }

    /**
     * Lecture-modification-écriture sous verrou.
     * @param callable(list<array>, int&):array $fn reçoit les tickets et le prochain numéro (modifiable)
     * @return list<array> les tickets enregistrés
     */
    private static function store(callable $fn): array
    {
        self::$cache = null;
        $saved = JsonStore::update(self::FILE, function ($raw) use ($fn) {
            [$items, $next] = self::unpack($raw);
            $items = array_values($fn($items, $next));
            return ['next' => $next, 'items' => $items];
        }, []);
        self::$cache = null;
        return $saved['items'];
    }

    /** @param callable(array):array{0:array,1:?string,2:?string} $fn ticket modifié, ligne d'historique, texte de l'e-mail */
    private static function change(int $id, array $user, callable $fn): array
    {
        $out = null;
        $label = null;
        $mail = null;
        self::store(function (array $all) use ($id, $user, $fn, &$out, &$label, &$mail) {
            foreach ($all as $k => $t) {
                if ($t['id'] !== $id) {
                    continue;
                }
                [$t, $label, $mail] = $fn($t);
                if ($label !== null) {
                    $now = date('c');
                    $t['updated_at'] = $now;
                    $t['history'][] = ['at' => $now, 'by' => $user['name'] ?? '', 'what' => $label];
                    $t['seen'][(string) ($user['id'] ?? '')] = $now;
                }
                $all[$k] = $out = $t;
            }
            return $all;
        });
        if ($out === null) {
            throw new \InvalidArgumentException('Ticket introuvable.');
        }
        if ($mail !== null) {
            self::notify($out, $user, ucfirst($label), $mail . self::summary($out));
        }
        return $out;
    }

    private static function clean(array $in): array
    {
        $f = [
            'title' => self::text((string) ($in['title'] ?? ''), 160, true),
            'type' => isset(self::TYPES[$in['type'] ?? '']) ? $in['type'] : 'bug',
            'priority' => isset(self::PRIORITIES[$in['priority'] ?? '']) ? $in['priority'] : 'normale',
            'body' => self::text((string) ($in['body'] ?? ''), 10000),
            'prompt' => self::text((string) ($in['prompt'] ?? ''), 10000),
            'page' => self::text((string) ($in['page'] ?? ''), 300, true),
        ];
        if ($f['title'] === '' || $f['body'] === '') {
            throw new \InvalidArgumentException('Donnez un titre et expliquez votre demande.');
        }
        return $f;
    }

    private static function text(string $s, int $max, bool $line = false): string
    {
        $s = trim(str_replace("\r\n", "\n", $s));
        if ($line) {
            $s = (string) preg_replace('/\s+/u', ' ', $s);
        }
        return mb_substr($s, 0, $max);
    }

    private static function who(array $user): array
    {
        return ['id' => (string) ($user['id'] ?? ''), 'name' => (string) ($user['name'] ?? 'Inconnu')];
    }

    private static function summary(array $t): string
    {
        $url = rtrim(base_url(), '/') . '/admin/tickets/' . $t['id'];
        return '<p><b>Ticket n° ' . $t['id'] . ' · ' . e($t['title']) . '</b><br>' . e(self::TYPES[$t['type']]) . ' · statut : ' . e(self::STATES[$t['status']])
            . ' · priorité : ' . e(self::PRIORITIES[$t['priority']]) . '<br>Ouvert par ' . e($t['author']['name'] ?? '') . '</p>'
            . '<p><a href="' . e($url) . '">Ouvrir le ticket dans le back-office</a></p>';
    }

    /**
     * E-mail à l'auteur, à ceux qui ont commenté le ticket et aux administrateurs actifs du musée, sauf
     * à la personne qui agit.
     */
    private static function notify(array $t, array $actor, string $what, string $html): void
    {
        $to = [];
        $involved = [(string) ($t['author']['id'] ?? '') => true];
        foreach ($t['comments'] ?? [] as $c) {
            $involved[(string) ($c['by']['id'] ?? '')] = true;
        }
        unset($involved['']);
        foreach (Auth::users() as $id => $u) {
            if (($u['status'] ?? 'active') === 'active' && (($u['role'] ?? '') === 'admin' || isset($involved[(string) $id])) && (string) $id !== (string) ($actor['id'] ?? '')) {
                $to[(string) ($u['email'] ?? '')] = true;
            }
        }
        $subject = '[' . Club::s('site') . '] Ticket n° ' . $t['id'] . ' · ' . $what . ' · ' . $t['title'];
        $send = function () use ($to, $subject, $html) {
            foreach (array_keys($to) as $email) {
                if ($email === '') {
                    continue;
                }
                try {
                    // Mailer::send met lui-même le message en page (gabarit du musée) : HTML brut ici.
                    self::$mailer ? (self::$mailer)($email, $subject, $html) : Mailer::send($email, $subject, $html);
                } catch (\Throwable $e) {
                    error_log('[tickets] e-mail : ' . $e->getMessage());
                }
            }
        };
        // Sur le site : e-mails envoyés après la page (un envoi SMTP par destinataire faisait attendre
        // chaque changement de statut ou commentaire). En ligne de commande et en essai : tout de suite.
        if (self::$mailer || PHP_SAPI === 'cli') {
            $send();
            return;
        }
        register_shutdown_function(function () use ($send) {
            \App\Core\Response::detach();
            $send();
        });
    }
}
