<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;

/** Base des contrôleurs du back-office. */
abstract class AdminController extends Controller
{
    protected function current(): array
    {
        $a = Auth::admin();
        if (!$a) {
            throw new HttpException(403);
        }
        return $a;
    }

    /** Identité courte de l'administrateur pour l'historique (« moderated_by »…). */
    protected function by(): string
    {
        $a = Auth::admin();
        return $a ? (($a['name'] ?? '') ?: $a['email']) : 'système';
    }

    protected function page(string $view, array $data, string $title, string $section = ''): Response
    {
        return $this->view('admin/' . $view, $data + ['title' => $title, 'section' => $section], 'admin/layout');
    }

    protected function done(string $message, string $to, string $type = 'success'): Response
    {
        \App\Services\AdminStats::forget();
        if (Request::isAjax()) {
            return Response::json(['ok' => $type !== 'error', 'message' => $message, 'error' => $type === 'error' ? $message : null], $type === 'error' ? 422 : 200);
        }
        Session::flash($type, $message);
        return $this->redirect(str_starts_with($to, '/') ? $to : Url::admin($to));
    }

    protected function audit(string $action, array $ctx = []): void
    {
        Logger::audit($action, $ctx + ['admin' => $this->by()]);
    }

    /** @return array{items:array, page:int, pages:int, total:int, per:int} */
    protected static function paginate(array $items, int $per = 50): array
    {
        $total = count($items);
        $pages = max(1, (int) ceil($total / $per));
        $page = min($pages, max(1, Request::int('page', 1)));
        return ['items' => array_slice(array_values($items), ($page - 1) * $per, $per), 'page' => $page, 'pages' => $pages, 'total' => $total, 'per' => $per];
    }

    /** Lien de pagination conservant les filtres. */
    protected static function pageLink(): callable
    {
        $q = $_GET;
        return static function (int $p) use ($q): string {
            $q['page'] = $p;
            if ($p <= 1) {
                unset($q['page']);
            }
            return strtok(Request::uri(), '?') . ($q ? '?' . http_build_query($q) : '');
        };
    }

    protected static function q(string $key, int $max = 120): string
    {
        $v = Request::query($key, '');
        return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
    }
}
