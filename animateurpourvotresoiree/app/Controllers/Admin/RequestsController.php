<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\Url;
use App\Services\AntiSpam;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Leads;
use App\Services\Pros;
use App\Services\Settings;
use App\Services\Store;

/** Demandes de devis : modération, diffusion aux pros, export. */
final class RequestsController extends AdminController
{
    private function filtered(): array
    {
        $status = self::q('statut', 20);
        $q = Str::norm(self::q('q', 100));
        $dep = Geo::depCode(self::q('dep', 3));
        $cat = self::q('cat', 60);
        $src = self::q('source', 20);
        $pro = (int) self::q('pro', 10);
        $from = self::q('du', 10);
        $to = self::q('au', 10);
        $proIds = $pro > 0 ? array_flip(Store::requests()->ids('recipients', $pro)) : null;
        $out = [];
        foreach (Store::requests()->iterate() as $id => $r) {
            if ($status !== '' && $r['status'] !== $status) {
                continue;
            }
            if ($proIds !== null && !isset($proIds[(int) $id])) {
                continue;
            }
            if ($dep !== '' && $r['dep'] !== $dep) {
                continue;
            }
            if ($cat !== '' && !in_array($cat, (array) $r['cats'], true)) {
                continue;
            }
            if ($src !== '' && $r['src'] !== $src) {
                continue;
            }
            if ($from !== '' && substr($r['created'], 0, 10) < $from || $to !== '' && substr($r['created'], 0, 10) > $to) {
                continue;
            }
            if ($q !== '' && !str_contains(Str::norm($r['name'] . ' ' . $r['email'] . ' ' . $r['phone'] . ' ' . $r['city'] . ' ' . $r['excerpt'] . ' ' . $id), $q)) {
                continue;
            }
            $out[(int) $id] = $r;
        }
        return $out;
    }

    public function index(): Response
    {
        $counts = ['' => 0];
        foreach (Store::requests()->iterate() as $r) {
            $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1;
            $counts['']++;
        }
        return $this->page('requests', self::paginate($this->filtered(), 50) + ['counts' => $counts, 'filters' => $_GET, 'link' => self::pageLink()], 'Demandes de devis', 'requests');
    }

    public function export(): Response
    {
        $rows = [['id', 'date', 'statut', 'origine', 'prénom nom', 'email', 'téléphone', 'événement', 'date événement', 'ville', 'département', 'invités', 'budget', 'métiers', 'pros destinataires', 'score spam', 'demande']];
        foreach ($this->filtered() as $id => $l) {
            $r = Store::requests()->get($id);
            if (!$r) {
                continue;
            }
            $rows[] = [$id, substr((string) ($r['created_at'] ?? ''), 0, 16), Leads::REQUEST_STATUSES[$r['status']] ?? $r['status'], $r['source'] ?? '', trim(($r['client']['first_name'] ?? '') . ' ' . ($r['client']['last_name'] ?? '')), $r['client']['email'] ?? '', $r['client']['phone'] ?? '', Leads::EVENT_TYPES[$r['event']['type'] ?? ''] ?? '', $r['event']['date'] ?? ($r['event']['date_text'] ?? ''), $r['event']['city'] ?? '', $r['event']['dep'] ?? '', $r['event']['guests'] ?? '', $r['event']['budget'] ?? '', implode(', ', array_map([Categories::class, 'name'], (array) ($r['event']['categories'] ?? []))), count((array) ($r['recipients'] ?? [])), (int) ($r['spam']['score'] ?? 0), (string) ($r['event']['message'] ?? '')];
        }
        $this->audit('Export CSV des demandes', ['lignes' => count($rows) - 1]);
        return Response::csv($rows, 'demandes-devis-' . date('Y-m-d') . '.csv');
    }

    public function show(int $id): Response
    {
        $r = Store::requests()->get($id);
        if (!$r) {
            throw new HttpException(404);
        }
        $ev = $r['event'];
        $suggested = Pros::recipientsFor((string) ($ev['dep'] ?? ''), $ev['lat'] ?? null, $ev['lng'] ?? null, (array) ($ev['categories'] ?? []), 80, (int) Settings::get('moderation.radius_km', 50));
        $index = Pros::publicIndex();
        $candidates = [];
        foreach ($suggested as $pid) {
            if (isset($index[$pid])) {
                $p = $index[$pid];
                $p['distance'] = ($ev['lat'] ?? null) !== null && $p['lat'] !== null ? (int) round(Geo::distance((float) $ev['lat'], (float) $ev['lng'], (float) $p['lat'], (float) $p['lng'])) : null;
                $candidates[$pid] = $p;
            }
        }
        $recipients = [];
        foreach ((array) ($r['recipients'] ?? []) as $pid) {
            $light = Store::pros()->light((int) $pid);
            $recipients[(int) $pid] = $light + ['seen' => $r['views'][(string) $pid] ?? null, 'state' => $r['pro_states'][(string) $pid]['state'] ?? null];
        }
        // autres demandes du même client
        $same = [];
        foreach (Store::requests()->ids('client.email', (string) ($r['client']['email'] ?? '')) as $oid) {
            if ($oid !== $id && ($l = Store::requests()->light($oid))) {
                $same[] = $l;
            }
        }
        return $this->page('request', [
            'r' => $r,
            'details' => Leads::requestDetails($r, true),
            'candidates' => $candidates,
            'recipients' => $recipients,
            'same' => array_slice(array_reverse($same), 0, 10),
            'max' => (int) Settings::get('moderation.max_recipients', 40),
        ], 'Demande n° ' . $id, 'requests');
    }

    public function action(int $id): Response
    {
        $r = Store::requests()->get($id);
        if (!$r) {
            throw new HttpException(404);
        }
        $action = (string) Request::input('action', '');
        switch ($action) {
            case 'diffuse':
                $pros = array_values(array_unique(array_filter(array_map('intval', (array) Request::arr('pros')))));
                $res = Leads::diffuse($id, $pros ?: null, $this->by());
                $n = count((array) ($res['recipients'] ?? []));
                $this->audit('Demande diffusée', ['demande' => $id, 'pros' => $n]);
                return $this->done("Demande diffusée : $n professionnel(s) destinataire(s).", 'demandes/' . $id);
            case 'reject':
                Store::requests()->update($id, ['status' => 'rejected', 'moderated_by' => $this->by(), 'moderated_at' => date('c')]);
                $this->audit('Demande refusée', ['demande' => $id]);
                return $this->done('Demande refusée.', $this->next($id), 'warning');
            case 'spam':
                Store::requests()->update($id, ['status' => 'spam', 'moderated_by' => $this->by(), 'moderated_at' => date('c')]);
                if (Request::bool('block_email') && !empty($r['client']['email'])) {
                    AntiSpam::block('emails', (string) $r['client']['email']);
                }
                if (Request::bool('block_domain') && !empty($r['client']['email'])) {
                    AntiSpam::block('domains', substr((string) strrchr((string) $r['client']['email'], '@'), 1));
                }
                $this->audit('Demande marquée comme spam', ['demande' => $id]);
                return $this->done('Demande classée en spam.', $this->next($id), 'warning');
            case 'close':
                Store::requests()->update($id, ['status' => 'closed']);
                return $this->done('Demande clôturée.', 'demandes/' . $id);
            case 'pending':
                Store::requests()->update($id, ['status' => 'pending']);
                return $this->done('Demande remise en modération.', 'demandes/' . $id);
            case 'note':
                Store::requests()->update($id, ['admin_notes' => \App\Core\Sanitizer::text((string) Request::input('admin_notes', ''), 3000)], false);
                return $this->done('Note enregistrée.', 'demandes/' . $id);
            case 'delete':
                Store::requests()->delete($id);
                $this->audit('Demande supprimée', ['demande' => $id]);
                return $this->done('Demande supprimée.', 'demandes');
        }
        return $this->done('Action inconnue.', 'demandes/' . $id, 'error');
    }

    /** Après modération : demande suivante à modérer, sinon la liste. */
    private function next(int $id): string
    {
        foreach (Store::requests()->iterate() as $oid => $l) {
            if ((int) $oid !== $id && $l['status'] === 'pending') {
                return 'demandes/' . $oid;
            }
        }
        return 'demandes?statut=pending';
    }
}
