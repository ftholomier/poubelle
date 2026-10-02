<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Str;
use App\Services\AntiSpam;
use App\Services\Pros;
use App\Services\Reviews;
use App\Services\Store;

/** Avis clients : modération, correction, suppression. */
final class ReviewsController extends AdminController
{
    public function index(): Response
    {
        $status = self::q('statut', 20);
        $pro = (int) self::q('pro', 10);
        $q = Str::norm(self::q('q', 100));
        $items = [];
        $counts = ['' => 0];
        foreach (Store::reviews()->iterate() as $id => $r) {
            $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1;
            $counts['']++;
            if ($status !== '' && $r['status'] !== $status || $pro > 0 && $r['pro'] !== $pro) {
                continue;
            }
            if ($q !== '' && !str_contains(Str::norm($r['author'] . ' ' . $r['excerpt']), $q)) {
                continue;
            }
            $items[(int) $id] = $r;
        }
        $pg = self::paginate($items, 30);
        $full = [];
        foreach ($pg['items'] as $l) {
            $r = Store::reviews()->get((int) $l['id']);
            if ($r) {
                $r['pro_name'] = Store::pros()->light((int) $r['pro_id'])['name'] ?? ('#' . $r['pro_id']);
                $full[] = $r;
            }
        }
        $pg['items'] = $full;
        return $this->page('reviews', $pg + ['counts' => $counts, 'filters' => $_GET, 'link' => self::pageLink()], 'Avis', 'reviews');
    }

    public function action(int $id): Response
    {
        $r = Store::reviews()->get($id);
        if (!$r) {
            throw new HttpException(404);
        }
        $back = 'avis' . (Request::input('back') ? '?' . preg_replace('/[^a-z0-9=&_\-]/i', '', (string) Request::input('back')) : '');
        switch ((string) Request::input('action', '')) {
            case 'approve':
                Reviews::approve($id, $this->by());
                $this->audit('Avis publié', ['avis' => $id]);
                return $this->done('Avis publié (le pro est prévenu).', $back);
            case 'reject':
                Reviews::reject($id, $this->by());
                $this->audit('Avis refusé', ['avis' => $id]);
                return $this->done('Avis refusé.', $back, 'warning');
            case 'spam':
                Reviews::reject($id, $this->by());
                if (!empty($r['author_email'])) {
                    AntiSpam::block('emails', (string) $r['author_email']);
                }
                return $this->done('Avis refusé et email bloqué.', $back, 'warning');
            case 'edit':
                Store::reviews()->update($id, [
                    'title' => Sanitizer::line((string) Request::input('title', ''), 90),
                    'body' => Sanitizer::text((string) Request::input('body', ''), 3000),
                    'author_name' => Sanitizer::line((string) Request::input('author_name', ''), 60),
                    'rating' => max(1, min(5, Request::int('rating', (int) $r['rating']))),
                    'edited_by' => $this->by(),
                ]);
                Reviews::recompute((int) $r['pro_id']);
                $this->audit('Avis corrigé', ['avis' => $id]);
                return $this->done('Avis corrigé.', $back);
            case 'remove-reply':
                Store::reviews()->update($id, ['reply' => null]);
                Pros::changed();
                return $this->done('Réponse du pro retirée.', $back);
            case 'delete':
                Store::reviews()->delete($id);
                Reviews::recompute((int) $r['pro_id']);
                $this->audit('Avis supprimé', ['avis' => $id]);
                return $this->done('Avis supprimé.', $back, 'warning');
        }
        return $this->done('Action inconnue.', $back, 'error');
    }
}
