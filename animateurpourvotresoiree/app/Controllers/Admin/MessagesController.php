<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Str;
use App\Core\Url;
use App\Services\AntiSpam;
use App\Services\Leads;
use App\Services\Mail;
use App\Services\Pros;
use App\Services\Settings;
use App\Services\Store;

/** Messages envoyés aux pros (modération) et messages du formulaire de contact du site. */
final class MessagesController extends AdminController
{
    public const CONTACT_STATUSES = ['new' => 'Nouveau', 'replied' => 'Répondu', 'archived' => 'Archivé', 'spam' => 'Spam'];

    public function index(): Response
    {
        $status = self::q('statut', 20);
        $pro = (int) self::q('pro', 10);
        $q = Str::norm(self::q('q', 100));
        $ids = $pro > 0 ? array_flip(Store::messages()->ids('pro_id', $pro)) : null;
        $items = [];
        $counts = ['' => 0];
        foreach (Store::messages()->iterate() as $id => $m) {
            $counts[$m['status']] = ($counts[$m['status']] ?? 0) + 1;
            $counts['']++;
            if ($status !== '' && $m['status'] !== $status || $ids !== null && !isset($ids[(int) $id])) {
                continue;
            }
            if ($q !== '' && !str_contains(Str::norm($m['name'] . ' ' . $m['email'] . ' ' . $m['excerpt'] . ' ' . $id), $q)) {
                continue;
            }
            $items[(int) $id] = $m;
        }
        $pg = self::paginate($items, 50);
        $names = [];
        foreach ($pg['items'] as $m) {
            $names[$m['pro']] ??= (Store::pros()->light((int) $m['pro'])['name'] ?? ('#' . $m['pro']));
        }
        return $this->page('messages', $pg + ['counts' => $counts, 'filters' => $_GET, 'link' => self::pageLink(), 'names' => $names], 'Messages aux pros', 'messages');
    }

    public function show(int $id): Response
    {
        $m = Store::messages()->get($id);
        if (!$m) {
            throw new HttpException(404);
        }
        $pro = Store::pros()->get((int) $m['pro_id']);
        return $this->page('message', ['m' => $m, 'pro' => $pro], 'Message n° ' . $id, 'messages');
    }

    public function action(int $id): Response
    {
        $m = Store::messages()->get($id);
        if (!$m) {
            throw new HttpException(404);
        }
        switch ((string) Request::input('action', '')) {
            case 'deliver':
                Leads::deliverMessage($id, $this->by());
                $this->audit('Message transmis au pro', ['message' => $id]);
                return $this->done('Message transmis au pro.', $this->next($id));
            case 'reject':
                Store::messages()->update($id, ['status' => 'rejected', 'moderated_by' => $this->by()]);
                return $this->done('Message refusé.', $this->next($id), 'warning');
            case 'spam':
                Store::messages()->update($id, ['status' => 'spam', 'moderated_by' => $this->by()]);
                if (Request::bool('block_email') && !empty($m['email'])) {
                    AntiSpam::block('emails', (string) $m['email']);
                }
                return $this->done('Message classé en spam.', $this->next($id), 'warning');
            case 'delete':
                Store::messages()->delete($id);
                $this->audit('Message supprimé', ['message' => $id]);
                return $this->done('Message supprimé.', 'messages');
        }
        return $this->done('Action inconnue.', 'messages/' . $id, 'error');
    }

    private function next(int $id): string
    {
        foreach (Store::messages()->iterate() as $oid => $l) {
            if ((int) $oid !== $id && $l['status'] === 'pending') {
                return 'messages/' . $oid;
            }
        }
        return 'messages?statut=pending';
    }

    // ------------------------------------------------------- formulaire de contact

    public function contacts(): Response
    {
        $status = self::q('statut', 20);
        $items = [];
        $counts = ['' => 0];
        foreach (Store::contacts()->iterate() as $id => $c) {
            $counts[$c['status']] = ($counts[$c['status']] ?? 0) + 1;
            $counts['']++;
            if ($status !== '' && $c['status'] !== $status) {
                continue;
            }
            $items[(int) $id] = $c;
        }
        $open = (int) self::q('id', 10);
        $current = $open > 0 ? Store::contacts()->get($open) : null;
        return $this->page('contacts', self::paginate($items, 40) + ['counts' => $counts, 'filters' => $_GET, 'link' => self::pageLink(), 'current' => $current], 'Formulaire de contact', 'contacts');
    }

    public function contactAction(int $id): Response
    {
        $c = Store::contacts()->get($id);
        if (!$c) {
            throw new HttpException(404);
        }
        switch ((string) Request::input('action', '')) {
            case 'reply':
                $text = Sanitizer::text((string) Request::input('reply', ''), 8000);
                if (mb_strlen($text) < 2) {
                    return $this->done('Votre réponse est vide.', 'contacts?id=' . $id, 'error');
                }
                $html = '<p>Bonjour ' . e($c['name']) . ',</p>' . \App\Core\Str::paragraphs($text) . '<p>' . nl2br(e((string) Settings::get('mailing.signature', ''))) . '</p>'
                    . '<hr style="border:0;border-top:1px dashed #e4d9c6;margin:20px 0"><p style="font-size:13px;color:#6b5f80">Votre message du ' . e(date_fr((string) ($c['created_at'] ?? ''), 'long')) . ' :<br>' . nl2br(e(Str::limit((string) $c['message'], 1500))) . '</p>';
                $subject = 'Re : ' . ($c['subject'] ?: 'votre message');
                $body = \App\Core\View::partial('emails/layout', ['subject' => $subject, 'body' => $html, 'footer' => '']);
                $res = \App\Core\Mailer::send(['to' => $c['email'], 'subject' => $subject, 'html' => $body, 'reply_to' => (string) \App\Core\Env::get('CONTACT_EMAIL', '')]);
                if (!$res['ok']) {
                    return $this->done('Envoi impossible : ' . $res['error'], 'contacts?id=' . $id, 'error');
                }
                $by = $this->by();
                Store::contacts()->update($id, static function (array $x) use ($text, $by): array {
                    $x['status'] = 'replied';
                    $x['replies'][] = ['text' => $text, 'at' => date('c'), 'by' => $by];
                    return $x;
                });
                $this->audit('Réponse à un contact', ['contact' => $id]);
                return $this->done('Réponse envoyée à ' . $c['email'] . '.', 'contacts?id=' . $id);
            case 'archive':
            case 'spam':
            case 'new':
                $to = ['archive' => 'archived', 'spam' => 'spam', 'new' => 'new'][(string) Request::input('action')];
                Store::contacts()->update($id, ['status' => $to]);
                if ($to === 'spam' && Request::bool('block_email')) {
                    AntiSpam::block('emails', (string) $c['email']);
                }
                return $this->done('Statut mis à jour.', 'contacts' . ($to === 'new' ? '?id=' . $id : ''));
            case 'delete':
                Store::contacts()->delete($id);
                return $this->done('Message supprimé.', 'contacts');
        }
        return $this->done('Action inconnue.', 'contacts?id=' . $id, 'error');
    }
}
