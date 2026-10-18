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
 * Fichier : storage/tickets.json (propre à chaque musée).
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
        $all = array_values(JsonStore::read(self::FILE, []) ?: []);
        usort($all, fn ($a, $b) => $b['id'] <=> $a['id']);
        return $all;
    }

    public static function get(int $id): ?array
    {
        foreach (self::all() as $t) {
            if ($t['id'] === $id) {
                return $t;
            }
        }
        return null;
    }

    /** Nouveau ticket. @param array{title?:string,type?:string,priority?:string,body?:string,prompt?:string,page?:string} $in */
    public static function create(array $in, array $user): array
    {
        $f = self::clean($in);
        $now = date('c');
        $t = JsonStore::update(self::FILE, function (?array $all) use ($f, $user, $now) {
            $all = $all ?: [];
            $id = $all ? max(array_column($all, 'id')) + 1 : 1;
            $all[] = $f + [
                'id' => $id, 'status' => 'nouveau', 'author' => self::who($user),
                'created_at' => $now, 'updated_at' => $now, 'comments' => [],
                'history' => [['at' => $now, 'by' => $user['name'] ?? '', 'what' => 'a ouvert le ticket']],
                'seen' => [(string) ($user['id'] ?? '') => $now],
            ];
            return $all;
        }, []);
        $t = end($t);
        self::notify($t, $user, 'Nouveau ticket', '<p><b>' . e($user['name'] ?? '') . '</b> a ouvert un ticket.</p>' . self::summary($t));
        return $t;
    }

    /** Modification du contenu : l'auteur tant que le ticket est « Nouveau », l'administrateur toujours. */
    public static function edit(int $id, array $in, array $user): array
    {
        $f = self::clean($in);
        return self::change($id, $user, function (array $t) use ($f, $user) {
            if (!self::canEdit($t, $user)) {
                throw new \InvalidArgumentException('Ce ticket ne peut plus être modifié : ajoutez plutôt un commentaire.');
            }
            if (!Auth::isAdmin()) {
                $f['priority'] = $t['priority']; // la priorité reste celle choisie par l'administrateur
            }
            return [$f + $t, 'a modifié le ticket', null];
        });
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

    public static function comment(int $id, string $text, array $user): array
    {
        $text = self::text($text, 5000);
        if ($text === '') {
            throw new \InvalidArgumentException('Le commentaire est vide.');
        }
        return self::change($id, $user, function (array $t) use ($text, $user) {
            $t['comments'][] = ['at' => date('c'), 'by' => self::who($user), 'text' => $text];
            return [$t, 'a commenté', '<p><b>' . e($user['name'] ?? '') . '</b> a répondu :</p><blockquote>' . nl2br(e($text)) . '</blockquote>'];
        });
    }

    public static function delete(int $id): void
    {
        JsonStore::update(self::FILE, fn (?array $all) => array_values(array_filter($all ?: [], fn ($t) => $t['id'] !== $id)), []);
    }

    /** Le ticket est vu par l'utilisateur (sa pastille s'éteint). */
    public static function markSeen(int $id, array $user): void
    {
        $uid = (string) ($user['id'] ?? '');
        if ($uid === '') {
            return;
        }
        JsonStore::update(self::FILE, function (?array $all) use ($id, $uid) {
            foreach ($all ?: [] as $k => $t) {
                if ($t['id'] === $id) {
                    $all[$k]['seen'][$uid] = date('c');
                }
            }
            return $all ?: [];
        }, []);
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
        return $out;
    }

    // ── Interne ────────────────────────────────────────────────────────────

    /** @param callable(array):array{0:array,1:?string,2:?string} $fn ticket modifié, ligne d'historique, texte de l'e-mail */
    private static function change(int $id, array $user, callable $fn): array
    {
        $out = null;
        $label = null;
        $mail = null;
        JsonStore::update(self::FILE, function (?array $all) use ($id, $user, $fn, &$out, &$label, &$mail) {
            foreach ($all ?: [] as $k => $t) {
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
            return $all ?: [];
        }, []);
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

    /** E-mail à l'auteur et aux administrateurs actifs du musée, sauf à la personne qui agit. */
    private static function notify(array $t, array $actor, string $what, string $html): void
    {
        $to = [];
        foreach (Auth::users() as $id => $u) {
            $isAuthor = (string) $id === ($t['author']['id'] ?? '');
            if (($u['status'] ?? 'active') === 'active' && ($u['role'] === 'admin' || $isAuthor) && (string) $id !== (string) ($actor['id'] ?? '')) {
                $to[(string) ($u['email'] ?? '')] = true;
            }
        }
        $subject = '[' . Club::s('site') . '] Ticket n° ' . $t['id'] . ' · ' . $what . ' · ' . $t['title'];
        foreach (array_keys($to) as $email) {
            if ($email === '') {
                continue;
            }
            try {
                self::$mailer ? (self::$mailer)($email, $subject, $html) : Mailer::send($email, $subject, Mailer::layout('Ticket n° ' . $t['id'], $html));
            } catch (\Throwable $e) {
                error_log('[tickets] e-mail : ' . $e->getMessage());
            }
        }
    }
}
