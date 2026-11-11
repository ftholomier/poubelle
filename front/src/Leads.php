<?php

declare(strict_types=1);

/** Demandes reçues par le site : devis, contact, rappel. Enregistrées puis notifiées par e-mail. */
final class Leads
{
    public const TYPES = ['devis' => 'Devis', 'contact' => 'Contact', 'rappel' => 'Rappel', 'vocal' => 'Vocal'];
    public const STATUS = ['nouveau' => 'Nouveau', 'encours' => 'En cours', 'traite' => 'Traité', 'archive' => 'Archivé', 'spam' => 'Spam'];
    /** Les spams sont effacés au bout de ce nombre de jours, et on n'en garde pas plus que SPAM_MAX. */
    public const SPAM_DAYS = 30;
    public const SPAM_MAX = 500;
    /** Au-delà, le fichier audio d'un vocal n'est pas joint à l'e-mail (il reste dans le back-office). */
    public const VOICE_ATTACH_MAX = 6 * 1024 * 1024;

    public static function all(): array
    {
        $l = Store::get('leads', []);
        usort($l, fn($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
        return $l;
    }

    public static function find(string $id): ?array
    {
        foreach (Store::get('leads', []) as $l) {
            if ($l['id'] === $id) {
                return $l;
            }
        }
        return null;
    }

    /** Hors spams : ce que l'équipe doit traiter (listes, compteurs, export). */
    public static function real(): array
    {
        return array_values(array_filter(self::all(), fn($l) => ($l['status'] ?? '') !== 'spam'));
    }

    /**
     * @param array|null $spam résultat d'Antispam::check() : s'il dit « spam », la demande est gardée dans
     *                         le statut Spam, sans e-mail de notification (l'équipe peut la récupérer).
     */
    public static function add(array $lead, array $attachments = [], ?array $spam = null): array
    {
        $isSpam = !empty($spam['spam']);
        $lead = ['id' => date('ymd') . '-' . bin2hex(random_bytes(4)), 'at' => date('c'), 'status' => $isSpam ? 'spam' : 'nouveau', 'notes' => []] + $lead;
        if ($isSpam) {
            $lead['spam'] = ['score' => (int) $spam['score'], 'reasons' => array_values((array) $spam['reasons'])];
        }
        $dropped = [];
        Store::update('leads', function (array $all) use ($lead, &$dropped) {
            array_unshift($all, $lead);
            // Ménage des spams : plus vieux que SPAM_DAYS jours, ou au-delà des SPAM_MAX plus récents
            $limit = date('c', time() - self::SPAM_DAYS * 86400);
            $n = 0;
            $keep = [];
            foreach ($all as $l) {
                if (($l['status'] ?? '') === 'spam' && (($l['at'] ?? '') < $limit || ++$n > self::SPAM_MAX)) {
                    $dropped[] = $l;
                    continue;
                }
                $keep[] = $l;
            }
            return array_slice($keep, 0, 5000);
        });
        foreach ($dropped as $l) {
            if (!empty($l['audio'])) {
                Voice::delete((string) $l['audio']);
            }
        }
        if ($isSpam) {
            return $lead;
        }
        $sent = self::notify($lead, $attachments);
        if ($sent !== true) {
            error_log('[renovest] notification e-mail : ' . $sent);
            self::update($lead['id'], ['mail_error' => (string) $sent]);
        }
        return $lead;
    }

    public static function update(string $id, array $changes): void
    {
        Store::update('leads', function (array $all) use ($id, $changes) {
            foreach ($all as &$l) {
                if ($l['id'] === $id) {
                    $l = array_replace($l, $changes);
                }
            }
            return $all;
        });
    }

    public static function delete(string $id): void
    {
        $l = self::find($id);
        if ($l && !empty($l['audio'])) {
            Voice::delete((string) $l['audio']);
        }
        Store::update('leads', fn(array $all) => array_values(array_filter($all, fn($l) => $l['id'] !== $id)));
    }

    /**
     * « Ce n'est pas un spam » : la demande redevient nouvelle. Si elle avait été écartée automatiquement
     * (donc jamais notifiée), l'e-mail de notification part maintenant.
     * @return string|false 'sent' (e-mail envoyé), 'restored' (marquée à la main : déjà notifiée), false
     */
    public static function release(string $id)
    {
        $l = self::find($id);
        if (!$l || ($l['status'] ?? '') !== 'spam') {
            return false;
        }
        $auto = !empty($l['spam']['reasons']) || isset($l['spam']['score']);
        self::update($id, ['status' => 'nouveau', 'spam_was' => $l['spam'] ?? ['manual' => true]]);
        if (!$auto) {
            return 'restored';
        }
        $l = self::find($id);
        unset($l['spam']);
        $att = [];
        $path = !empty($l['audio']) ? Voice::file((string) $l['audio']) : null;
        if ($path && Settings::get('voice_attach', true) && filesize($path) < self::VOICE_ATTACH_MAX) {
            $att[] = ['name' => 'vocal-' . (($l['nom'] ?? '') !== '' ? slugify((string) $l['nom']) : 'client') . '.' . pathinfo($path, PATHINFO_EXTENSION), 'type' => Voice::mime((string) $l['audio']), 'content' => (string) file_get_contents($path)];
        }
        $sent = self::notify($l, $att);
        if ($sent !== true) {
            self::update($id, ['mail_error' => (string) $sent]);
        }
        return 'sent';
    }

    public static function unread(): int
    {
        return count(array_filter(Store::get('leads', []), fn($l) => ($l['status'] ?? '') === 'nouveau'));
    }

    /**
     * Contenu complet d'une demande, rangé par rubriques, pour l'e-mail de notification :
     * l'équipe doit tout avoir sous les yeux sans passer par le back-office.
     * Un champ du formulaire laissé vide reste listé (valeur '') pour qu'on sache qu'il n'a pas été rempli.
     *
     * @return array{title:string, origin:string, sections:array<string,array<string,string>>, message:array{0:string,1:string}|null, summary:string}
     */
    public static function details(array $l): array
    {
        $type = (string) ($l['type'] ?? '');
        $has = fn(string $k): bool => array_key_exists($k, $l);
        $val = fn(string $k): string => trim((string) ($l[$k] ?? ''));
        $fromContact = $has('objet');

        $coord = [];
        if ($type !== 'rappel' || $val('nom') !== '') {
            $coord['Nom'] = $val('nom');
        }
        $coord['Téléphone'] = $val('tel');
        if ($has('email')) {
            $coord['E-mail'] = $val('email');
        }
        if ($has('cp')) {
            $coord['Code postal du chantier'] = $val('cp');
        }

        $projet = [];
        if ($fromContact) {
            $projet['Objet'] = $val('objet');
        }
        if ($type === 'devis') {
            $projet['Produits'] = implode(', ', array_map('strval', (array) ($l['types'] ?? [])));
        }
        if ($has('qty')) {
            $projet["Nombre d'éléments"] = $val('qty');
        }
        if ($has('delai')) {
            $projet['Échéance du projet'] = $val('delai');
        }
        if ($type === 'vocal' && $val('duration') !== '') {
            $projet['Durée du message'] = $val('duration');
        }

        $sections = ['Coordonnées' => $coord];
        if ($projet) {
            $sections[$type === 'vocal' ? 'Message vocal' : ($fromContact ? 'Demande' : 'Projet')] = $projet;
        }

        $message = null;
        if ($type === 'vocal') {
            $message = ['Transcription du message', $val('msg')];
        } elseif ($has('msg')) {
            $message = [$fromContact && $val('objet') !== 'Devis' ? 'Message' : 'Message / précisions du client', $val('msg')];
        }

        $titles = ['devis' => 'Nouvelle demande de devis', 'contact' => 'Nouveau message de contact', 'rappel' => 'Demande de rappel', 'vocal' => 'Nouveau message vocal'];
        $origins = [
            'devis' => $fromContact ? 'le formulaire de contact (objet « Devis »)' : 'le formulaire de demande de devis',
            'contact' => 'le formulaire de contact',
            'rappel' => 'la fenêtre « On vous rappelle » (affichée quand le visiteur quitte le site)',
            'vocal' => 'le bouton « Laissez un vocal »',
        ];

        return [
            'title' => $titles[$type] ?? 'Nouvelle demande',
            'origin' => $origins[$type] ?? 'le site',
            'sections' => $sections,
            'message' => $message,
            'summary' => $type === 'vocal' ? $val('summary') : '',
        ];
    }

    /** Adresse complète de la page d'où la demande a été envoyée ('' si inconnue). */
    private static function pageUrl(array $l): string
    {
        $p = trim((string) ($l['page'] ?? ''));
        return $p !== '' && $p[0] === '/' && !str_starts_with($p, '//') ? rtrim(Http::abs('/'), '/') . $p : '';
    }

    /** Date de réception en clair : « mardi 10 novembre 2026 à 14h32 ». */
    private static function when(array $l): string
    {
        static $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $t = strtotime((string) ($l['at'] ?? '')) ?: time();
        return $days[(int) date('w', $t)] . ' ' . date_fr(date('Y-m-d', $t)) . ' à ' . date('G\hi', $t);
    }

    /**
     * Où trouver l'audio d'un vocal : [joint à l'e-mail ?, phrase à afficher]. [false, ''] hors vocal.
     * @return array{0:bool,1:string}
     */
    private static function audioNote(array $l, bool $attached): array
    {
        if (($l['type'] ?? '') !== 'vocal' || empty($l['audio'])) {
            return [false, ''];
        }
        if ($attached) {
            return [true, 'Le fichier audio du message est en pièce jointe de cet e-mail.'];
        }
        $why = Settings::get('voice_attach', true)
            ? 'il est trop lourd pour être envoyé par e-mail'
            : 'l\'envoi de l\'audio par e-mail est désactivé dans les réglages';
        $then = trim((string) ($l['msg'] ?? '')) !== ''
            ? 'la transcription ci-dessous reprend le message, et l\'enregistrement s\'écoute dans le back-office, rubrique Demandes.'
            : 'il n\'y a pas de transcription, écoutez l\'enregistrement dans le back-office, rubrique Demandes.';
        return [false, 'Le fichier audio n\'est pas joint à cet e-mail (' . $why . ') : ' . $then];
    }

    /** Texte affiché à la place d'un message vide. */
    private static function emptyMessage(array $l): string
    {
        return ($l['type'] ?? '') === 'vocal'
            ? 'Pas de transcription : le navigateur du client ne la fait pas et aucune clé Gemini n\'est réglée.'
            : 'Aucun message : le champ a été laissé vide.';
    }

    /** Version texte complète de la demande (e-mail sans HTML, copie). */
    public static function summary(array $l, bool $audioAttached = false): string
    {
        $d = self::details($l);
        $out = mb_strtoupper($d['title']) . "\n"
            . 'Le ' . self::when($l) . ', via ' . $d['origin'] . ".\n";
        [, $note] = self::audioNote($l, $audioAttached);
        if ($note !== '') {
            $out .= $note . "\n";
        }
        foreach ($d['sections'] as $title => $rows) {
            $out .= "\n" . mb_strtoupper($title) . "\n";
            foreach ($rows as $k => $v) {
                $out .= $k . ' : ' . ($v !== '' ? $v : 'non renseigné') . "\n";
            }
        }
        if ($d['summary'] !== '') {
            $out .= "\nRÉSUMÉ\n" . $d['summary'] . "\n";
        }
        if ($d['message']) {
            $out .= "\n" . mb_strtoupper($d['message'][0]) . "\n" . ($d['message'][1] !== '' ? $d['message'][1] : '(' . self::emptyMessage($l) . ')') . "\n";
        }
        $page = self::pageUrl($l);
        $out .= "\n—\n" . ($page !== '' ? 'Page d\'origine : ' . rawurldecode($page) . "\n" : '')
            . 'Référence : ' . $l['id'] . "\n"
            . 'Back-office : ' . Http::abs('/admin/demandes') . '?id=' . rawurlencode((string) $l['id']) . "\n";
        return $out;
    }

    /** Version HTML de l'e-mail : tout le contenu de la demande, mis en page pour les messageries. */
    public static function mailHtml(array $l, bool $audioAttached = false): string
    {
        $d = self::details($l);
        $f = "font-family:Arial,Helvetica,sans-serif;";
        $tel = trim((string) ($l['tel'] ?? ''));
        $email = trim((string) ($l['email'] ?? ''));
        $nom = trim((string) ($l['nom'] ?? ''));
        $site = Content::site()['name'] ?? "Renov'Est";
        $empty = '<span style="color:#9a9a9a;font-style:italic">non renseigné</span>';

        // Boutons d'action : appeler, répondre
        $btns = '';
        if ($tel !== '') {
            $btns .= '<a href="tel:' . e(tel($tel)) . '" style="' . $f . 'display:inline-block;background:#FFF200;color:#1c1c1c;font-size:15px;font-weight:bold;text-decoration:none;padding:12px 20px;border-radius:8px;margin:0 8px 8px 0">Appeler le ' . e($tel) . '</a>';
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $re = 'Votre demande — ' . $site;
            $btns .= '<a href="mailto:' . e($email) . '?subject=' . rawurlencode($re) . '" style="' . $f . 'display:inline-block;background:#1c1c1c;color:#ffffff;font-size:15px;font-weight:bold;text-decoration:none;padding:12px 20px;border-radius:8px;margin:0 8px 8px 0">Répondre par e-mail</a>';
        }

        $h = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($d['title']) . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f1f1ee">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f1f1ee"><tr><td align="center" style="padding:24px 12px">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden">'
            // En-tête
            . '<tr><td style="background:#1c1c1c;padding:24px 28px;border-bottom:5px solid #FFF200">'
            . '<div style="' . $f . 'font-size:12px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#FFF200">' . e($site) . ' · site internet</div>'
            . '<div style="' . $f . 'font-size:24px;line-height:30px;font-weight:bold;color:#ffffff;margin-top:6px">' . e($d['title']) . '</div>'
            . '<div style="' . $f . 'font-size:14px;line-height:20px;color:#bdbdbd;margin-top:4px">Le ' . e(self::when($l)) . '<br>via ' . e($d['origin']) . '</div>'
            . '</td></tr>'
            // Nom + actions
            . '<tr><td style="padding:24px 28px 4px">'
            . ($nom !== '' ? '<div style="' . $f . 'font-size:21px;line-height:27px;font-weight:bold;color:#1c1c1c;margin-bottom:14px">' . e($nom) . '</div>' : '')
            . $btns
            . self::audioNoteHtml($l, $audioAttached, $f)
            . '</td></tr>';

        foreach ($d['sections'] as $title => $rows) {
            $h .= '<tr><td style="padding:18px 28px 0">'
                . '<div style="' . $f . 'font-size:12px;font-weight:bold;letter-spacing:1.5px;text-transform:uppercase;color:#8c8c8c;padding-bottom:8px;border-bottom:2px solid #FFF200">' . e($title) . '</div>'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="table-layout:fixed">';
            foreach ($rows as $k => $v) {
                if ($v === '') {
                    $shown = $empty;
                } elseif ($k === 'Téléphone') {
                    $shown = '<a href="tel:' . e(tel($v)) . '" style="color:#1c1c1c;font-weight:bold;text-decoration:none">' . e($v) . '</a>';
                } elseif ($k === 'E-mail') {
                    $shown = '<a href="mailto:' . e($v) . '" style="color:#1c1c1c;font-weight:bold">' . e($v) . '</a>';
                } else {
                    $shown = '<b>' . e($v) . '</b>';
                }
                $h .= '<tr><td valign="top" width="42%" style="' . $f . 'font-size:14px;line-height:20px;color:#6b6b6b;padding:10px 12px 10px 0;border-bottom:1px solid #eeeeee">' . e($k) . '</td>'
                    . '<td valign="top" style="' . $f . 'font-size:15px;line-height:21px;color:#1c1c1c;padding:10px 0;border-bottom:1px solid #eeeeee">' . $shown . '</td></tr>';
            }
            $h .= '</table></td></tr>';
        }

        if ($d['summary'] !== '') {
            $h .= '<tr><td style="padding:22px 28px 0">'
                . '<div style="' . $f . 'font-size:12px;font-weight:bold;letter-spacing:1.5px;text-transform:uppercase;color:#8c8c8c;padding-bottom:8px;border-bottom:2px solid #FFF200">Résumé</div>'
                . '<div style="' . $f . 'font-size:15px;line-height:23px;color:#1c1c1c;padding-top:10px">' . nl2br(e($d['summary'])) . '</div></td></tr>';
        }
        if ($d['message']) {
            [$mt, $mv] = $d['message'];
            $h .= '<tr><td style="padding:22px 28px 0">'
                . '<div style="' . $f . 'font-size:12px;font-weight:bold;letter-spacing:1.5px;text-transform:uppercase;color:#8c8c8c;padding-bottom:10px">' . e($mt) . '</div>'
                . '<div style="' . $f . 'font-size:15px;line-height:24px;color:#1c1c1c;background:#f7f7f4;border-left:4px solid #FFF200;border-radius:4px;padding:16px 18px">'
                . ($mv !== '' ? nl2br(e($mv)) : '<span style="color:#9a9a9a;font-style:italic">' . e(self::emptyMessage($l)) . '</span>')
                . '</div></td></tr>';
        }

        $page = self::pageUrl($l);
        $h .= '<tr><td style="padding:26px 28px 26px">'
            . '<div style="' . $f . 'font-size:12px;line-height:19px;color:#9a9a9a;border-top:1px solid #eeeeee;padding-top:14px">'
            . ($page !== '' ? 'Page d\'origine : <a href="' . e($page) . '" style="color:#6b6b6b">' . e(rawurldecode($page)) . '</a><br>' : '')
            . ($email !== '' ? 'Répondre à cet e-mail écrit directement au client (' . e($email) . ').<br>' : '')
            . 'Référence ' . e((string) $l['id']) . ' · <a href="' . e(Http::abs('/admin/demandes') . '?id=' . rawurlencode((string) $l['id'])) . '" style="color:#6b6b6b">copie dans le back-office</a>'
            . '</div></td></tr>'
            . '</table></td></tr></table></body></html>';
        return $h;
    }

    /** Mention de l'audio d'un vocal dans l'e-mail HTML (encadré avec lien quand il n'est pas joint). */
    private static function audioNoteHtml(array $l, bool $attached, string $f): string
    {
        [$joined, $note] = self::audioNote($l, $attached);
        if ($note === '') {
            return '';
        }
        if ($joined) {
            return '<div style="' . $f . 'font-size:14px;line-height:20px;color:#434343;margin-top:6px">' . e($note) . '</div>';
        }
        $link = Http::abs('/admin/demandes') . '?id=' . rawurlencode((string) $l['id']);
        return '<div style="' . $f . 'font-size:14px;line-height:21px;color:#1c1c1c;background:#fffbcc;border:1px solid #f0e400;border-radius:8px;padding:12px 14px;margin-top:8px">'
            . e($note) . '<br><a href="' . e($link) . '" style="color:#1c1c1c;font-weight:bold">Écouter le message dans le back-office →</a></div>';
    }

    /** Objet de l'e-mail : l'essentiel lisible dès la boîte de réception. */
    private static function subject(array $l): string
    {
        $type = (string) ($l['type'] ?? '');
        $who = trim((string) ($l['nom'] ?? '')) !== '' ? trim((string) $l['nom']) : trim((string) ($l['tel'] ?? ''));
        switch ($type) {
            case 'devis':
                $types = array_map('strval', (array) ($l['types'] ?? []));
                $what = count($types) > 2 ? implode(', ', array_slice($types, 0, 2)) . ' +' . (count($types) - 2) : implode(', ', $types);
                $s = '[Site] Demande de devis — ' . $who . ($what !== '' ? ' — ' . $what : '') . (trim((string) ($l['cp'] ?? '')) !== '' ? ' (' . trim((string) $l['cp']) . ')' : '');
                break;
            case 'contact':
                $s = '[Site] Nouveau message — ' . $who;
                break;
            case 'rappel':
                $s = '[Site] À rappeler — ' . trim((string) ($l['tel'] ?? ''));
                break;
            case 'vocal':
                $s = '[Site] Nouveau message vocal — ' . $who;
                break;
            default:
                $s = '[Site] Nouvelle demande — ' . $who;
        }
        return mb_substr($s, 0, 180);
    }

    /** @return true|string */
    private static function notify(array $l, array $attachments = [])
    {
        $to = trim((string) Settings::get('notify_email', ''));
        $cc = trim((string) Settings::get('notify_cc', ''));
        if ($cc !== '') {
            $to .= ',' . $cc;
        }
        $audio = $attachments !== [];
        return Mailer::send($to, self::subject($l), self::summary($l, $audio), (string) ($l['email'] ?? ''), $attachments, self::mailHtml($l, $audio));
    }
}
