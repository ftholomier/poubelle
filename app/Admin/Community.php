<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\JsonStore;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Data\Activity;
use App\Data\Fiches as Store;
use App\Data\Index;
use App\Data\Media;
use App\Front\Community as Front;
use App\Services\Mailer;
use App\Services\Newsletter;
use App\Services\Souvenirs;

/** Communauté : contributions, messages, newsletter. */
final class Community extends Base
{
    public const C_STATUS = ['nouveau' => 'À traiter', 'info' => 'Info demandée', 'valide' => 'Publiée', 'refuse' => 'Refusée'];
    public const M_STATUS = ['nouveau' => 'Nouveau', 'lu' => 'Lu', 'traite' => 'Traité'];

    // ------------------------------------------------------------------ contributions

    /** @return list<array> plus récentes d'abord */
    public static function contributionList(): array
    {
        $out = [];
        foreach (glob(Front::INBOX . '/contributions/*/contribution.json') ?: [] as $f) {
            $c = JsonStore::read($f, null);
            if (is_array($c)) {
                $out[] = $c;
            }
        }
        usort($out, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
        return $out;
    }

    private static function contrib(string $ticket): ?array
    {
        return preg_match('/^SR-\d{4}-\d{4,}$/', $ticket) ? JsonStore::read(Front::INBOX . "/contributions/$ticket/contribution.json", null) : null;
    }

    private static function saveContrib(array $c): void
    {
        JsonStore::write(Front::INBOX . '/contributions/' . $c['ticket'] . '/contribution.json', $c);
    }

    public static function contributions(Request $req): Response
    {
        $all = self::contributionList();
        $tab = $req->str('statut', 'a-traiter');
        $rows = array_values(array_filter($all, fn ($c) => match ($tab) {
            'a-traiter' => in_array($c['status'] ?? 'nouveau', ['nouveau', 'info'], true),
            'tout' => true,
            default => ($c['status'] ?? '') === $tab,
        }));
        $counts = ['a-traiter' => 0, 'valide' => 0, 'refuse' => 0, 'tout' => count($all)];
        foreach ($all as $c) {
            $s = $c['status'] ?? 'nouveau';
            if (in_array($s, ['nouveau', 'info'], true)) {
                $counts['a-traiter']++;
            } elseif (isset($counts[$s])) {
                $counts[$s]++;
            }
        }
        return self::html('admin/community/contributions', ['rows' => $rows, 'tab' => $tab, 'counts' => $counts], ['title' => 'Contributions', 'crumb' => 'Communauté', 'nav' => 'contributions']);
    }

    public static function contribution(Request $req, string $ticket): Response
    {
        $c = self::contrib($ticket);
        if (!$c) {
            return self::html('admin/message', ['title' => 'Contribution introuvable', 'text' => "Aucune contribution $ticket.", 'back' => '/admin/contributions'], ['title' => 'Introuvable'], 404);
        }
        $cols = [];
        foreach (\App\Front\Pages::reserves() as $col) {
            $cols[$col['slug']] = $col['name'];
        }
        return self::html('admin/community/contribution', ['c' => $c, 'cols' => $cols], ['title' => $c['ticket'] . ' · ' . (Front::TYPES[$c['type']][1] ?? 'Contribution'), 'crumb_html' => 'Communauté › <a href="/admin/contributions">Contributions</a>', 'nav' => 'contributions']);
    }

    /** Fichier d'une contribution (aperçu ou téléchargement, réservé à l'équipe). */
    public static function contributionFile(Request $req, string $ticket, int $n): Response
    {
        $c = self::contrib($ticket);
        $f = $c['files'][$n] ?? null;
        $path = $f ? Front::INBOX . "/contributions/$ticket/" . basename((string) $f['file']) : null;
        if (!$path || !is_file($path)) {
            return Response::notFound();
        }
        $mime = (string) ($f['mime'] ?? 'application/octet-stream');
        $inline = preg_match('#^image/(jpeg|png|gif|webp)$|^application/pdf$#', $mime);
        return new Response((string) file_get_contents($path), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . addslashes((string) $f['name']) . '"',
            'Cache-Control' => 'private, max-age=600',
            // Fichier envoyé par le public : jamais interprété autrement que selon son type vérifié.
            'X-Content-Type-Options' => 'nosniff',
        ] + (str_starts_with($mime, 'image/') ? ['Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox"] : []));
    }

    public static function contributionAction(Request $req, string $ticket): Response
    {
        $c = self::contrib($ticket);
        if (!$c) {
            return self::back('/admin/contributions', null, 'Contribution introuvable.');
        }
        $action = (string) ($req->post['action'] ?? '');
        $user = self::actor();
        $back = '/admin/contributions/' . $ticket;
        $note = Html::clean(mb_substr((string) ($req->post['message'] ?? ''), 0, 8000));
        $credit = Html::line($req->post['credit'] ?? ($c['credit'] ?: $c['name']), 200);
        switch ($action) {
            case 'mediatheque':
            case 'objet':
            case 'fiche':
                $rels = self::importFiles($c, $credit, $user);
                $c['media'] = $rels;
                if ($action === 'objet') {
                    $doc = Store::blank('objet');
                    $doc['title'] = Html::line($req->post['title'] ?? '', 250) ?: (Front::TYPES[$c['type']][1] ?? 'Objet') . ' · ' . $c['name'];
                    $doc['objet']['collection'] = Html::line($req->post['collection'] ?? 'photos', 40);
                    $doc['objet']['credit'] = $credit;
                    $doc['objet']['date_text'] = $c['date'] ?? '';
                    $doc['objet']['contribution'] = $ticket;
                    $doc['intro'] = Html::clean(nl2br(e($c['description'] ?? '')));
                    $doc['featured_image'] = $rels[0] ?? null;
                    $doc['gallery'] = array_map(fn ($r) => ['image' => $r, 'caption' => '', 'credit' => $credit, 'caption_raw' => $credit], $rels);
                    $doc['status'] = 'brouillon';
                    $doc['path'] = \App\Data\Paths::unique(\App\Data\Paths::suggest($doc), -1);
                    $doc = Store::save($doc, $user, 'Création depuis la contribution ' . $ticket);
                    $c['fiche_id'] = $doc['id'];
                    $c['status'] = 'valide';
                    $c['handled'] = ['by' => $user['name'], 'at' => date('c'), 'action' => 'objet'];
                    self::saveContrib($c);
                    $sent = self::notify($c, 'valide', $note);
                    return self::back('/admin/fiche/' . $doc['id'], 'Objet créé en brouillon avec ' . count($rels) . ' image(s). Relisez-le puis publiez-le.' . self::mailNote($sent));
                }
                if ($action === 'fiche') {
                    $fid = (int) ($req->post['fiche_id'] ?? 0);
                    $doc = $fid ? Store::get($fid) : null;
                    if (!$doc) {
                        return self::back($back, null, 'Choisissez la fiche à compléter.');
                    }
                    foreach ($rels as $r) {
                        $doc['gallery'][] = ['image' => $r, 'caption' => '', 'credit' => $credit, 'caption_raw' => $credit];
                    }
                    Store::save($doc, $user, count($rels) . ' photo(s) ajoutée(s) (contribution ' . $ticket . ')');
                    $c['fiche_id'] = $fid;
                    $c['status'] = 'valide';
                    $c['handled'] = ['by' => $user['name'], 'at' => date('c'), 'action' => 'fiche'];
                    self::saveContrib($c);
                    $sent = self::notify($c, 'valide', $note, $doc);
                    return self::back('/admin/fiche/' . $fid . '#medias', count($rels) . ' photo(s) ajoutée(s) à la galerie de la fiche.' . self::mailNote($sent));
                }
                self::saveContrib($c);
                return self::back($back, count($rels) . ' fichier(s) importé(s) dans la médiathèque.');
            case 'valider':
                $c['status'] = 'valide';
                $c['handled'] = ['by' => $user['name'], 'at' => date('c'), 'action' => 'valider'];
                $fid = (int) ($req->post['fiche_id'] ?? 0);
                if ($fid) {
                    $c['fiche_id'] = $fid;
                }
                // Témoignage : publié dans « Ils y étaient » sur la fiche du match (texte et signature relus).
                if (($c['type'] ?? '') === 'temoignage') {
                    $isMatch = !empty($c['fiche_id']) && (Index::get((int) $c['fiche_id'])['type'] ?? '') === 'match';
                    $c['public'] = $isMatch && !empty($req->post['public']);
                    $c['public_text'] = trim(mb_substr(str_replace("\r", '', (string) ($req->post['public_text'] ?? '')), 0, 1500)) ?: null;
                    $c['public_name'] = Html::line($req->post['public_name'] ?? '', 80) ?: null;
                }
                self::saveContrib($c);
                Souvenirs::reindex();
                $sent = self::notify($c, 'valide', $note, $fid ? Store::get($fid) : null);
                Activity::log($user, 'a validé la contribution', ['title' => $ticket]);
                return self::back('/admin/contributions', 'Contribution validée.' . self::mailNote($sent));
            case 'refuser':
                $c['status'] = 'refuse';
                $c['handled'] = ['by' => $user['name'], 'at' => date('c'), 'action' => 'refuser', 'note' => $note];
                self::saveContrib($c);
                Souvenirs::reindex();
                $sent = self::notify($c, 'refuse', $note);
                Activity::log($user, 'a refusé la contribution', ['title' => $ticket]);
                return self::back('/admin/contributions', 'Contribution refusée.' . self::mailNote($sent));
            case 'info':
                if ($note === '') {
                    return self::back($back, null, 'Écrivez votre question au contributeur.');
                }
                $c['status'] = 'info';
                $c['thread'][] = ['by' => $user['name'], 'at' => date('c'), 'text' => $note];
                self::saveContrib($c);
                $sent = self::notify($c, 'info', $note);
                return $sent ? self::back($back, 'Question envoyée par e-mail au contributeur.') : self::back($back, null, 'Question enregistrée, mais l’e-mail n’a pas pu partir : vérifiez les réglages e-mail.');
            case 'supprimer':
                if (!Auth::can('destroy')) {
                    return self::back($back, null, 'Suppression réservée aux administrateurs.');
                }
                $dir = Front::INBOX . '/contributions/' . $ticket;
                foreach (glob("$dir/*") ?: [] as $f) {
                    @unlink($f);
                }
                @rmdir($dir);
                Souvenirs::reindex();
                Activity::log($user, 'a supprimé la contribution', ['title' => $ticket]);
                return self::back('/admin/contributions', 'Contribution et fichiers supprimés.');
        }
        return self::back($back, null, 'Action inconnue.');
    }

    /** Copie les fichiers d'une contribution dans la médiathèque (une seule fois). */
    private static function importFiles(array $c, string $credit, array $user): array
    {
        if (!empty($c['media'])) {
            return $c['media'];
        }
        $rels = [];
        foreach ($c['files'] ?? [] as $i => $f) {
            $src = Front::INBOX . '/contributions/' . $c['ticket'] . '/' . basename((string) $f['file']);
            if (!is_file($src)) {
                continue;
            }
            $ext = strtolower(pathinfo((string) $f['file'], PATHINFO_EXTENSION));
            $rel = 'contributions/' . strtolower($c['ticket']) . '/' . sprintf('%02d', $i + 1) . '-' . (\App\Data\Paths::slug(pathinfo((string) $f['name'], PATHINFO_FILENAME), 50) ?: 'fichier') . '.' . $ext;
            $dest = Media::ORIGINALS . '/' . $rel;
            if (!is_dir(dirname($dest))) {
                mkdir(dirname($dest), 0775, true);
            }
            copy($src, $dest);
            [$w, $h] = @getimagesize($dest) ?: [null, null];
            Media::put($rel, ['file' => $rel, 'caption' => '', 'credit' => $credit, 'alt' => '', 'rights' => 'Cession contributeur (' . $c['ticket'] . ')', 'width' => $w, 'height' => $h, 'size' => filesize($dest), 'added' => date('c'), 'source' => 'contribution ' . $c['ticket']], $user);
            $rels[] = $rel;
        }
        return $rels;
    }

    /** E-mail au contributeur (validation, refus, demande d'information). */
    private static function notify(array $c, string $kind, string $note, ?array $doc = null): bool
    {
        $prev = \App\Services\I18n::lang();
        \App\Services\I18n::set($c['lang'] ?? 'fr');
        $hello = '<p>' . e(t('Bonjour {prenom},', ['prenom' => $c['name']])) . '</p>';
        $ref = '<p style="font-size:13px;color:#3A4A75">' . e(t('N° de suivi')) . ' : ' . e($c['ticket']) . '</p>';
        $msg = $note !== '' ? '<blockquote style="border-left:4px solid #F6C400;margin:12px 0;padding:6px 12px">' . $note . '</blockquote>' : '';
        [$subject, $body] = match ($kind) {
            'valide' => [t('Votre contribution est publiée · Sochaux Rétro'), $hello . '<p>' . e(t('Bonne nouvelle : votre contribution a été validée par l’équipe du musée. Merci pour ce précieux souvenir !')) . '</p>' . $msg . ($doc && Store::isVisible($doc) ? '<p><a href="' . e(base_url() . $doc['path']) . '">' . e(t('Voir la fiche')) . ' →</a></p>' : '')],
            'refuse' => [t('Votre contribution · Sochaux Rétro'), $hello . '<p>' . e(t('Merci pour votre contribution. Après examen, l’équipe ne peut malheureusement pas la publier.')) . '</p>' . $msg],
            default => [t('Une question sur votre contribution · Sochaux Rétro'), $hello . '<p>' . e(t('L’équipe du musée a besoin d’une précision pour publier votre contribution :')) . '</p>' . $msg . '<p>' . e(t('Il vous suffit de répondre à cet e-mail.')) . '</p>'],
        };
        $reply = (string) Settings::get('general.contact_email', '') ?: null;
        $sent = Mailer::send((string) $c['email'], $subject, $body . $ref, $reply);
        \App\Services\I18n::set($prev);
        return $sent;
    }

    /** Fin de message selon que l'e-mail au contributeur est parti ou non. */
    private static function mailNote(bool $sent): string
    {
        return $sent ? ' Le contributeur est prévenu par e-mail.' : ' (L’e-mail au contributeur n’a pas pu partir : vérifiez les réglages e-mail.)';
    }

    // ------------------------------------------------------------------ messages

    public static function messageList(): array
    {
        $out = [];
        foreach (glob(Front::INBOX . '/messages/*.json') ?: [] as $f) {
            $m = JsonStore::read($f, null);
            if (is_array($m)) {
                $out[] = $m + ['read' => ($m['status'] ?? 'nouveau') !== 'nouveau'];
            }
        }
        usort($out, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
        return $out;
    }

    private static function msg(string $id): ?array
    {
        return preg_match('/^\d{14}-[a-f0-9]{6}$/', $id) ? JsonStore::read(Front::INBOX . "/messages/$id.json", null) : null;
    }

    public static function messages(Request $req): Response
    {
        $all = self::messageList();
        $tab = $req->str('statut', 'actifs');
        $rows = array_values(array_filter($all, fn ($m) => $tab === 'tout' || ($tab === 'actifs' ? ($m['status'] ?? 'nouveau') !== 'traite' : ($m['status'] ?? '') === $tab)));
        return self::html('admin/community/messages', ['rows' => $rows, 'tab' => $tab, 'all' => $all], ['title' => 'Messages', 'crumb' => 'Communauté', 'nav' => 'messages']);
    }

    public static function message(Request $req, string $id): Response
    {
        $m = self::msg($id);
        if (!$m) {
            return self::html('admin/message', ['title' => 'Message introuvable', 'text' => 'Ce message n’existe plus.', 'back' => '/admin/messages'], ['title' => 'Introuvable'], 404);
        }
        if (($m['status'] ?? 'nouveau') === 'nouveau') {
            $m['status'] = 'lu';
            $m['read_by'] = self::actor()['name'];
            JsonStore::write(Front::INBOX . "/messages/$id.json", $m);
        }
        return self::html('admin/community/message', ['m' => $m, 'users' => Auth::users()], ['title' => $m['name'] . ' · ' . (Front::REASONS[$m['reason']] ?? 'Message'), 'crumb_html' => 'Communauté › <a href="/admin/messages">Messages</a>', 'nav' => 'messages']);
    }

    public static function messageAction(Request $req, string $id): Response
    {
        $m = self::msg($id);
        if (!$m) {
            return self::back('/admin/messages', null, 'Message introuvable.');
        }
        $action = (string) ($req->post['action'] ?? '');
        $user = self::actor();
        if ($action === 'repondre') {
            $text = Html::clean(mb_substr((string) ($req->post['reply'] ?? ''), 0, 20000));
            if ($text === '') {
                return self::back('/admin/messages/' . $id, null, 'Écrivez votre réponse.');
            }
            $ok = Mailer::send((string) $m['email'], 'Re : ' . (Front::REASONS[$m['reason']] ?? 'votre message') . ' · Sochaux Rétro', $text . '<p style="font-size:13px;color:#3A4A75;border-top:1px solid #E8DFC9;padding-top:10px">' . e($user['name']) . ' · Sochaux Rétro</p><blockquote style="color:#3A4A75;border-left:3px solid #E8DFC9;margin:10px 0;padding:4px 10px;font-size:14px">' . nl2br(e(mb_substr((string) $m['message'], 0, 2000))) . '</blockquote>', (string) Settings::get('general.contact_email', '') ?: null);
            $m['replies'][] = ['by' => $user['name'], 'at' => date('c'), 'text' => $text, 'sent' => $ok];
            $m['status'] = 'traite';
            JsonStore::write(Front::INBOX . "/messages/$id.json", $m);
            return self::back('/admin/messages/' . $id, $ok ? 'Réponse envoyée.' : null, $ok ? null : 'La réponse est enregistrée mais l’e-mail n’a pas pu partir (vérifiez les réglages e-mail).');
        }
        if ($action === 'assigner') {
            $m['assigned'] = Html::line($req->post['to'] ?? '', 80);
        } elseif (isset(self::M_STATUS[$action])) {
            $m['status'] = $action;
        } elseif ($action === 'supprimer' && Auth::can('destroy')) {
            @unlink(Front::INBOX . "/messages/$id.json");
            return self::back('/admin/messages', 'Message supprimé.');
        }
        $m['note'] = Html::clean(mb_substr((string) ($req->post['note'] ?? ($m['note'] ?? '')), 0, 8000));
        JsonStore::write(Front::INBOX . "/messages/$id.json", $m);
        return self::back('/admin/messages/' . $id, 'Message mis à jour.');
    }

    // ------------------------------------------------------------------ newsletter

    public static function newsletter(Request $req): Response
    {
        $all = JsonStore::read(STORAGE_PATH . '/newsletter/subscribers.json', []) ?: [];
        $stat = ['active' => 0, 'pending' => 0, 'unsubscribed' => 0];
        foreach ($all as $s) {
            $stat[$s['status']] = ($stat[$s['status']] ?? 0) + 1;
        }
        $state = JsonStore::read(STORAGE_PATH . '/newsletter/envoi.json', []) ?: [];
        $next = self::nextSend();
        $q = mb_strtolower($req->str('q'));
        $subs = array_filter($all, fn ($s) => ($s['status'] ?? '') !== 'unsubscribed' && ($q === '' || str_contains(mb_strtolower((string) ($s['email'] ?? '')), $q)));
        uasort($subs, fn ($a, $b) => strcmp((string) ($b['created'] ?? ''), (string) ($a['created'] ?? '')));
        // Abonnés gagnés et perdus, semaine par semaine (12 dernières semaines).
        $growth = [];
        for ($i = 11; $i >= 0; $i--) {
            $growth[date('o-W', strtotime("-$i week"))] = ['label' => date('d/m', strtotime('monday this week', strtotime("-$i week"))), 'in' => 0, 'out' => 0];
        }
        foreach ($all as $s) {
            if (!empty($s['confirmed']) && isset($growth[$w = date('o-W', strtotime((string) $s['confirmed']))])) {
                $growth[$w]['in']++;
            }
            if (!empty($s['unsubscribed']) && isset($growth[$w = date('o-W', strtotime((string) $s['unsubscribed']))])) {
                $growth[$w]['out']++;
            }
        }
        return self::html('admin/community/newsletter', ['history' => \App\Services\NewsletterStats::history(), 'growth' => $growth, 'stat' => $stat, 'state' => $state, 'next' => $next, 'subs' => array_slice($subs, 0, 200, true), 'found' => count($subs), 'q' => $q, 'items' => Newsletter::items()], ['title' => 'Newsletter « Ce jour-là »', 'crumb' => 'Communauté', 'nav' => 'newsletter']);
    }

    private static function nextSend(): ?string
    {
        if (!Settings::get('newsletter.enabled', false)) {
            return null;
        }
        $wd = (int) Settings::get('newsletter.weekday', 1);
        $h = (int) Settings::get('newsletter.hour', 8);
        for ($i = 0; $i < 8; $i++) {
            $ts = strtotime("+$i day");
            if ((int) date('N', $ts) === $wd) {
                $t = strtotime(date('Y-m-d', $ts) . sprintf(' %02d:00', $h));
                if ($t > time()) {
                    return date('c', $t);
                }
            }
        }
        return null;
    }

    public static function newsletterAction(Request $req): Response
    {
        $action = (string) ($req->post['action'] ?? '');
        if ($action === 'test') {
            $u = Auth::user();
            $ok = Mailer::send((string) $u['email'], '[Test] ' . Newsletter::subject(), Newsletter::html() . '<p style="font-size:12px;color:#3A4A75">Envoi de test.</p>');
            return self::back('/admin/newsletter', $ok ? 'E-mail de test envoyé à ' . $u['email'] . '.' : null, $ok ? null : 'Envoi impossible : vérifiez les réglages e-mail (Réglages › E-mail).');
        }
        if ($action === 'envoyer') {
            // Renvoyer à tous les abonnés une lettre déjà partie cette semaine : administrateurs seulement.
            if (Newsletter::sentThisWeek() && !Auth::isAdmin()) {
                return self::back('/admin/newsletter', null, 'La lettre de cette semaine est déjà partie : seul un administrateur peut la renvoyer.');
            }
            @set_time_limit(300);
            $r = Newsletter::tick(true);
            return self::back('/admin/newsletter', 'Envoi lancé : ' . ($r['sent'] ?? 0) . ' e-mail(s) envoyé(s)' . (($r['remaining'] ?? 0) ? ', ' . $r['remaining'] . ' en attente (suite au prochain passage de la tâche planifiée)' : '') . '.');
        }
        if ($action === 'ajouter') {
            $email = mb_strtolower(trim((string) ($req->post['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return self::back('/admin/newsletter', null, 'Adresse e-mail invalide.');
            }
            $lang = ($req->post['lang'] ?? '') === 'en' ? 'en' : 'fr';
            $key = hash('sha256', $email);
            $was = null;
            JsonStore::update(STORAGE_PATH . '/newsletter/subscribers.json', function ($all) use ($key, $email, $lang, &$was) {
                $all = $all ?: [];
                $was = $all[$key]['status'] ?? null;
                if ($was !== 'active') {
                    $all[$key] = ['email' => $email, 'status' => 'active', 'token' => bin2hex(random_bytes(16)), 'created' => $all[$key]['created'] ?? date('c'), 'confirmed' => date('c'), 'lang' => $lang, 'by' => 'équipe'];
                }
                return $all;
            }, []);
            if ($was === 'active') {
                return self::back('/admin/newsletter', $email . ' est déjà abonné(e).');
            }
            Activity::log(Auth::user(), 'a abonné à la newsletter', ['title' => $email]);
            return self::back('/admin/newsletter', $email . ' est abonné(e) : il ou elle recevra la prochaine lettre.');
        }
        if ($action === 'desinscrire' && ($key = (string) ($req->post['key'] ?? '')) !== '') {
            JsonStore::update(STORAGE_PATH . '/newsletter/subscribers.json', function ($all) use ($key) {
                if (isset($all[$key])) {
                    $all[$key] = ['email' => null, 'status' => 'unsubscribed', 'token' => $all[$key]['token'] ?? '', 'created' => $all[$key]['created'] ?? null, 'confirmed' => $all[$key]['confirmed'] ?? null, 'unsubscribed' => date('c'), 'by' => 'équipe'];
                }
                return $all ?: [];
            }, []);
            \App\Services\NewsletterStats::unsub('équipe');
            return self::back('/admin/newsletter', 'Abonné désinscrit, adresse effacée.');
        }
        return self::back('/admin/newsletter', null, 'Action inconnue.');
    }

    public static function newsletterPreview(Request $req): Response
    {
        return Response::html(Mailer::layout(Newsletter::subject(), Newsletter::html() . '<p style="font-size:12px;color:#3A4A75;margin-top:24px;text-align:center">Vous recevez ce message car vous êtes inscrit(e) à la newsletter « Ce jour-là » de Sochaux Rétro.<br><a href="#" style="color:#3A4A75">Se désinscrire</a></p>'));
    }
}
