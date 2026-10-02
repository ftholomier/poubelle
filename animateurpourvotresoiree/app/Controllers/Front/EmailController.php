<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\Crypto;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Mailing;
use App\Services\Store;

/** Suivi des emails (ouvertures, clics) et désinscription en un clic (RFC 8058). */
final class EmailController extends Controller
{
    public function open(string $token): Response
    {
        Mailing::trackOpen($token);
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        return new Response((string) $gif, 200, ['Content-Type' => 'image/gif', 'Cache-Control' => 'no-store, private']);
    }

    public function click(string $token): Response
    {
        $url = (string) Request::query('u', '');
        $h = (string) Request::query('h', '');
        if (!preg_match('#^https?://#i', $url) || !hash_equals(Mailing::clickHash($token, $url), $h)) {
            throw new HttpException(404);
        }
        Mailing::trackClick($token);
        return Response::redirect($url, 302);
    }

    public function unsubscribe(string $token): Response
    {
        $p = Crypto::verify($token, 'unsub');
        if (!$p) {
            throw new HttpException(404);
        }
        $oneClick = Request::isPost() && (Request::input('List-Unsubscribe') === 'One-Click' || Request::input('confirm') === '1');
        if ($oneClick) {
            if ($p['t'] === 'pro') {
                Store::doc('unsubscribed')->set('pros.' . (int) $p['i'], date('c'));
                Store::pros()->update((int) $p['i'], static function (array $pro): array {
                    $pro['settings']['newsletter'] = false;
                    return $pro;
                }, false);
            }
            if (Request::input('List-Unsubscribe') === 'One-Click') {
                return new Response('', 200);
            }
            return $this->view('front/thanks', ['title' => 'Vous êtes désinscrit', 'text' => 'Vous ne recevrez plus nos emails d\'information. Les emails liés à votre compte (demandes de devis, messages) restent envoyés.', 'pro' => null, 'meta' => ['title' => 'Désinscription', 'robots' => 'noindex']]);
        }
        return $this->view('front/unsubscribe', ['token' => $token, 'meta' => ['title' => 'Désinscription', 'robots' => 'noindex']]);
    }
}
