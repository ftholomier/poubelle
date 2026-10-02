<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Mailer;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Str;
use App\Core\Url;
use App\Core\View;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Mail;
use App\Services\Mailing;
use App\Services\Pros;
use App\Services\Settings;
use App\Services\Store;

/** Emailing vers les adhérents, modèles d'emails transactionnels, file d'envoi, prospects. */
final class MailingController extends AdminController
{
    public const CAMPAIGN_STATUSES = ['draft' => 'Brouillon', 'scheduled' => 'Programmée', 'sending' => 'En cours d\'envoi', 'sent' => 'Envoyée', 'cancelled' => 'Annulée'];
    public const VARIABLES = ['prenom' => 'Prénom', 'nom' => 'Nom', 'fiche' => 'Nom de la fiche', 'login' => 'Identifiant', 'ville' => 'Ville', 'lien_fiche' => 'Lien vers la fiche', 'lien_espace' => 'Lien vers l\'espace pro'];

    public function index(): Response
    {
        $items = Store::campaigns()->find(null, null, 100)['items'];
        $queue = ['queued' => count(Store::mailQueue()->ids('status', 'queued')), 'failed' => count(Store::mailQueue()->ids('status', 'failed'))];
        return $this->page('mailing', ['items' => $items, 'queue' => $queue, 'driver' => (string) \App\Core\Env::get('MAIL_DRIVER', 'log')], 'Emailing', 'mailing');
    }

    private static function segmentFromRequest(array $in): array
    {
        $seg = [
            'status' => array_values(array_intersect((array) ($in['status'] ?? ['active']), array_keys(Pros::STATUSES))),
            'cats' => array_values(array_filter((array) ($in['cats'] ?? []), static fn ($c) => Categories::get((string) $c) !== null)),
            'deps' => array_values(array_filter(array_map(static fn ($d) => Geo::depCode((string) $d), (array) ($in['deps'] ?? [])))),
            'regions' => array_values(array_filter((array) ($in['regions'] ?? []), static fn ($r) => Geo::region((string) $r) !== null)),
            'photos' => in_array($in['photos'] ?? '', ['none', 'some'], true) ? $in['photos'] : '',
            'login' => in_array($in['login'] ?? '', ['never', '90'], true) ? $in['login'] : '',
            'score_max' => max(0, min(100, (int) ($in['score_max'] ?? 100))),
        ];
        if (!$seg['status']) {
            $seg['status'] = ['active'];
        }
        return $seg;
    }

    public function audience(): Response
    {
        $seg = self::segmentFromRequest((array) Request::input('segment', []));
        return Response::json(['count' => count(Mailing::audience($seg))]);
    }

    public function edit(?int $id = null): Response
    {
        $c = $id ? Store::campaigns()->get($id) : null;
        if ($id && !$c) {
            throw new HttpException(404);
        }
        if (Request::isPost()) {
            if ($c && !in_array($c['status'], ['draft', 'scheduled'], true)) {
                return $this->done('Cette campagne est déjà partie : dupliquez-la pour la modifier.', 'emailing/' . $id, 'error');
            }
            $data = [
                'name' => Sanitizer::line((string) Request::input('name', ''), 120) ?: 'Campagne du ' . date('d/m/Y'),
                'subject' => Sanitizer::line((string) Request::input('subject', ''), 160),
                'body' => Mailing::sanitizeBody((string) Request::raw('body', '')),
                'segment' => self::segmentFromRequest((array) Request::input('segment', [])),
                'updated_by' => $this->by(),
            ];
            if ($data['subject'] === '' || Str::text($data['body']) === '') {
                \App\Core\Session::withInput($_POST);
                return $this->done('L\'objet et le contenu sont obligatoires.', $id ? 'emailing/' . $id : 'emailing/nouvelle', 'error');
            }
            if ($c) {
                Store::campaigns()->update($id, $data);
            } else {
                $c = Store::campaigns()->insert($data + ['status' => 'draft', 'created_by' => $this->by(), 'stats' => []]);
                $id = (int) $c['id'];
            }
            $next = (string) Request::input('next', 'save');
            if ($next === 'test') {
                return $this->sendTest($id);
            }
            if ($next === 'send') {
                return $this->launch($id);
            }
            if ($next === 'schedule') {
                $at = (string) Request::input('scheduled_at', '');
                $ts = strtotime($at);
                if (!$ts || $ts < time() + 60) {
                    return $this->done('Date de programmation invalide (elle doit être dans le futur).', 'emailing/' . $id, 'error');
                }
                Store::campaigns()->update($id, ['status' => 'scheduled', 'scheduled_at' => date('c', $ts)]);
                $this->audit('Campagne programmée', ['campagne' => $id, 'date' => date('c', $ts)]);
                return $this->done('Campagne programmée pour le ' . date_fr(date('c', $ts), 'datetime') . '.', 'emailing/' . $id);
            }
            return $this->done('Campagne enregistrée.', 'emailing/' . $id);
        }
        $sample = null;
        foreach (Pros::publicIndex() as $pid => $p) {
            $sample = Store::pros()->get((int) $pid);
            break;
        }
        return $this->page('campaign', [
            'c' => $c,
            'segment' => $c['segment'] ?? ['status' => ['active']],
            'preview' => $c ? self::preview($c, $sample) : '',
            'statuses' => self::CAMPAIGN_STATUSES,
        ], $c ? (string) $c['name'] : 'Nouvelle campagne', 'mailing');
    }

    /** Aperçu de la campagne avec les données d'un pro réel. */
    private static function preview(array $c, ?array $pro): string
    {
        $vars = $pro ? ['prenom' => $pro['first_name'] ?: Pros::displayName($pro), 'nom' => $pro['last_name'] ?? '', 'fiche' => Pros::displayName($pro), 'login' => $pro['login'] ?? '', 'ville' => $pro['city'] ?? '', 'lien_fiche' => Url::abs(Url::pro($pro)), 'lien_espace' => Url::abs('/espace-pro/')] : [];
        $body = (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', static fn ($m) => e($vars[$m[1]] ?? '{{' . $m[1] . '}}'), (string) ($c['body'] ?? ''));
        $footer = '<p style="font-size:12px;color:#6b5f80">Vous recevez cet email car vous êtes inscrit sur ' . e(Settings::siteName()) . '. <a href="#" style="color:#6b5f80">Se désinscrire</a></p>';
        return View::partial('emails/layout', ['subject' => (string) ($c['subject'] ?? ''), 'body' => $body, 'footer' => $footer]);
    }

    private function sendTest(int $id): Response
    {
        $c = Store::campaigns()->get($id);
        $me = $this->current();
        $sample = null;
        foreach (Pros::publicIndex() as $pid => $_) {
            $sample = Store::pros()->get((int) $pid);
            break;
        }
        $html = self::preview($c, $sample);
        $res = Mailer::send(['to' => $me['email'], 'subject' => '[TEST] ' . $c['subject'], 'html' => $html]);
        return $this->done($res['ok'] ? 'Email de test envoyé à ' . $me['email'] . '.' : 'Échec de l\'envoi : ' . $res['error'], 'emailing/' . $id, $res['ok'] ? 'success' : 'error');
    }

    private function launch(int $id): Response
    {
        $n = Mailing::launch($id);
        if ($n === 0) {
            return $this->done('Aucun destinataire pour ce segment (ou campagne déjà envoyée).', 'emailing/' . $id, 'warning');
        }
        return $this->done("Campagne lancée : $n emails mis en file d'envoi (" . (int) Settings::get('mailing.rate_per_minute', 60) . ' par minute).', 'emailing/' . $id);
    }

    public function action(int $id): Response
    {
        $c = Store::campaigns()->get($id);
        if (!$c) {
            throw new HttpException(404);
        }
        switch ((string) Request::input('action', '')) {
            case 'send':
                return $this->launch($id);
            case 'test':
                return $this->sendTest($id);
            case 'cancel':
                $removed = 0;
                foreach (Store::mailQueue()->ids('campaign_id', $id) as $mid) {
                    $m = Store::mailQueue()->light($mid);
                    if ($m && $m['status'] === 'queued') {
                        Store::mailQueue()->delete($mid);
                        $removed++;
                    }
                }
                Store::campaigns()->update($id, ['status' => $c['status'] === 'scheduled' ? 'draft' : 'cancelled', 'scheduled_at' => null]);
                $this->audit('Campagne annulée', ['campagne' => $id, 'retirés' => $removed]);
                return $this->done($removed ? "Envoi stoppé : $removed emails retirés de la file." : 'Programmation annulée.', 'emailing/' . $id, 'warning');
            case 'duplicate':
                $copy = Store::campaigns()->insert(['name' => $c['name'] . ' (copie)', 'subject' => $c['subject'], 'body' => $c['body'], 'segment' => $c['segment'] ?? [], 'status' => 'draft', 'stats' => [], 'created_by' => $this->by()]);
                return $this->done('Campagne dupliquée.', 'emailing/' . $copy['id']);
            case 'delete':
                if ($c['status'] === 'sending') {
                    return $this->done('Annulez d\'abord l\'envoi en cours.', 'emailing/' . $id, 'error');
                }
                Store::campaigns()->delete($id);
                return $this->done('Campagne supprimée.', 'emailing');
        }
        return $this->done('Action inconnue.', 'emailing/' . $id, 'error');
    }

    // ------------------------------------------------------------- modèles

    public function templates(): Response
    {
        $list = [];
        foreach (Mail::defaults() as $key => [$label]) {
            $t = Mail::template($key);
            $list[$key] = ['label' => $label, 'subject' => $t['subject'], 'custom' => $t['custom']];
        }
        return $this->page('templates', ['list' => $list], 'Modèles d\'emails', 'templates');
    }

    public function template(string $key): Response
    {
        if (!isset(Mail::defaults()[$key])) {
            throw new HttpException(404);
        }
        if (Request::isPost()) {
            $action = (string) Request::input('action', 'save');
            if ($action === 'reset') {
                Store::doc('email_templates')->update(static function (array $d) use ($key): array {
                    unset($d[$key]);
                    return $d;
                });
                return $this->done('Modèle d\'origine rétabli.', 'emailing/modeles/' . $key);
            }
            $subject = Sanitizer::line((string) Request::input('subject', ''), 200);
            $body = Sanitizer::html((string) Request::raw('body', ''), ['images' => true, 'tables' => true, 'headings' => true]);
            // les variables {{…}} doivent survivre au nettoyage
            Store::doc('email_templates')->set($key, ['subject' => $subject, 'body' => $body, 'updated_at' => date('c'), 'updated_by' => $this->by()]);
            $this->audit('Modèle d\'email modifié', ['modele' => $key]);
            if ($action === 'test') {
                $m = Mail::build($key, self::sampleVars(), self::sampleRaw());
                $res = Mailer::send(['to' => $this->current()['email'], 'subject' => '[TEST] ' . $m['subject'], 'html' => $m['html']]);
                return $this->done($res['ok'] ? 'Modèle enregistré et test envoyé.' : 'Enregistré, mais test impossible : ' . $res['error'], 'emailing/modeles/' . $key, $res['ok'] ? 'success' : 'error');
            }
            return $this->done('Modèle enregistré.', 'emailing/modeles/' . $key);
        }
        $t = Mail::template($key);
        $preview = Mail::build($key, self::sampleVars(), self::sampleRaw());
        return $this->page('template', ['key' => $key, 't' => $t, 'preview' => $preview, 'default' => Mail::defaults()[$key]], 'Modèle : ' . $t['label'], 'templates');
    }

    private static function sampleVars(): array
    {
        return ['prenom' => 'Camille', 'fiche' => 'DJ Paillettes', 'client' => 'Marie Dupont', 'evenement' => 'Mariage', 'lieu_court' => 'à Lyon', 'note' => '5', 'avis' => 'Soirée parfaite, merci !', 'motif' => 'Merci d\'ajouter une description de votre prestation.', 'sujet' => 'Question sur le site', 'titre' => 'Exemple d\'alerte', 'corps' => 'Texte de l\'alerte.'];
    }

    private static function sampleRaw(): array
    {
        return [
            'details' => Mail::details(['Événement' => 'Mariage', 'Date' => 'samedi 12 juin 2027', 'Lieu' => 'Lyon (69)', 'Invités' => '120', 'Demande' => 'Soirée dansante de 20 h à 3 h, années 80 à aujourd\'hui.']),
            'bouton_url' => '/espace-pro/',
            'bouton_label' => 'Ouvrir',
            'suite' => 'Elle a été transmise à 12 professionnels de votre secteur.',
            'avis' => 'Soirée parfaite, merci !',
        ];
    }

    // ------------------------------------------------------------- file d'envoi

    public function queue(): Response
    {
        if (Request::isPost()) {
            $action = (string) Request::input('action', '');
            switch ($action) {
                case 'process':
                    $r = Mail::processQueue(100);
                    return $this->done('Envoi manuel : ' . $r['sent'] . ' envoyé(s), ' . $r['failed'] . ' échec(s).', 'emailing/file');
                case 'retry':
                    $n = 0;
                    foreach (Store::mailQueue()->ids('status', 'failed') as $mid) {
                        Store::mailQueue()->update($mid, ['status' => 'queued', 'attempts' => 0, 'send_after' => date('c')]);
                        $n++;
                    }
                    return $this->done("$n email(s) remis en file.", 'emailing/file');
                case 'delete':
                    Store::mailQueue()->delete(Request::int('id'));
                    return $this->done('Email retiré de la file.', 'emailing/file');
                case 'purge':
                    $n = 0;
                    foreach (Store::mailQueue()->ids('status', 'sent') as $mid) {
                        Store::mailQueue()->delete($mid);
                        $n++;
                    }
                    return $this->done("$n email(s) envoyés effacés de l'historique.", 'emailing/file');
            }
        }
        $status = self::q('statut', 20);
        $items = [];
        foreach (Store::mailQueue()->iterate() as $id => $m) {
            if ($status !== '' && $m['status'] !== $status) {
                continue;
            }
            $items[(int) $id] = $m;
        }
        $counts = [];
        foreach (['queued', 'sent', 'failed'] as $s) {
            $counts[$s] = count(Store::mailQueue()->ids('status', $s));
        }
        return $this->page('queue', self::paginate($items, 60) + ['counts' => $counts, 'status' => $status, 'link' => self::pageLink(), 'driver' => (string) \App\Core\Env::get('MAIL_DRIVER', 'log')], 'File d\'envoi', 'queue');
    }

    // ------------------------------------------------------------- prospects

    public function prospects(): Response
    {
        $q = mb_strtolower(self::q('q', 100));
        $all = (array) Store::doc('prospects')->get('items', []);
        if ($q !== '') {
            $all = array_values(array_filter($all, static fn ($p) => str_contains((string) $p['email'], $q)));
        }
        return $this->page('prospects', self::paginate($all, 100) + ['q' => $q, 'link' => self::pageLink(), 'updated' => Store::doc('prospects')->get('updated_at')], 'Prospects', 'prospects');
    }

    public function prospectsExport(): Response
    {
        $rows = [['email', 'source', 'désinscrit']];
        foreach ((array) Store::doc('prospects')->get('items', []) as $p) {
            $rows[] = [$p['email'], $p['source'] ?? '', $p['unsubscribed_at'] ?? ''];
        }
        $this->audit('Export des prospects', ['lignes' => count($rows) - 1]);
        return Response::csv($rows, 'prospects-' . date('Y-m-d') . '.csv');
    }
}
