<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Crypto;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Services\Ai;
use App\Services\Settings;
use App\Services\Stats;
use App\Services\Store;

/**
 * Assistant conversationnel des visiteurs (Gemini).
 * L'historique est conservé côté serveur : le navigateur n'envoie que le nouveau message
 * et un identifiant de conversation signé (impossible d'injecter de faux échanges).
 */
final class ChatController extends Controller
{
    private const MAX_TURNS = 30;

    public function message(): Response
    {
        if (!Settings::aiOn('assistant')) {
            return Response::json(['error' => 'Assistant indisponible.'], 503);
        }
        // Requête de la page elle-même uniquement.
        $origin = (string) (Request::header('Origin') ?? '');
        if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== explode(':', Request::host())[0]) {
            return Response::json(['error' => 'Origine refusée.'], 403);
        }
        [$max, $window] = Settings::get('antispam.rates.chat', [40, 3600]);
        if (!RateLimiter::attempt('chat:' . Request::ip(), (int) $max, (int) $window)) {
            return Response::json(['error' => 'Vous avez envoyé beaucoup de messages. Faites une petite pause et réessayez plus tard.'], 429);
        }
        $in = Request::json();
        $text = Str::clean(Str::limit((string) ($in['text'] ?? ''), 1000, ''), true);
        if (mb_strlen($text) < 1) {
            return Response::json(['error' => 'Message vide.'], 422);
        }
        $chat = null;
        $p = Crypto::verify((string) ($in['cid'] ?? ''), 'chat');
        if ($p) {
            $chat = Store::chats()->get((int) $p['c']);
            if ($chat && ($chat['ip_hash'] ?? '') !== Request::ipHash() && !empty($chat['ip_hash'])) {
                $chat = null; // conversation d'un autre visiteur
            }
        }
        if ($chat && count($chat['messages'] ?? []) >= self::MAX_TURNS * 2) {
            return Response::json(['error' => 'Cette conversation est longue : rechargez la page pour en démarrer une nouvelle.', 'reset' => true], 422);
        }
        if (!$chat) {
            $chat = Store::chats()->insert([
                'messages' => [],
                'ip_hash' => Request::ipHash(),
                'ua' => Str::limit(Request::userAgent(), 160, ''),
                'page' => Str::limit((string) ($in['page'] ?? ''), 200, ''),
                'cards_shown' => 0,
            ]);
            Stats::hit('chat');
        }
        $history = [];
        foreach ($chat['messages'] ?? [] as $m) {
            $history[] = ['role' => $m['role'], 'text' => $m['text']];
        }
        $history[] = ['role' => 'user', 'text' => $text];
        $res = Ai::chat($history);
        $cards = array_map(static fn ($c) => [
            'id' => $c['id'], 'name' => $c['name'], 'url' => $c['url'] . '?src=assistant', 'cat' => $c['cat'], 'color' => $c['color'],
            'city' => $c['city'], 'photo' => $c['photo'], 'rating' => $c['rating'], 'price' => $c['price'],
        ], $res['cards']);
        $now = date('c');
        Store::chats()->update((int) $chat['id'], static function (array $c) use ($text, $res, $cards, $now): array {
            $c['messages'][] = ['role' => 'user', 'text' => $text, 'at' => $now];
            $c['messages'][] = ['role' => 'model', 'text' => $res['text'], 'at' => $now, 'cards' => array_column($cards, 'id'), 'quote' => $res['quote'], 'error' => $res['error']];
            $c['cards_shown'] = (int) ($c['cards_shown'] ?? 0) + count($cards);
            if ($res['quote']) {
                $c['quote_proposed'] = true;
            }
            return $c;
        });
        return Response::json([
            'cid' => Crypto::sign(['c' => (int) $chat['id']], 'chat', 86400 * 2),
            'text' => $res['text'],
            'cards' => $cards,
            'quote' => $res['quote'],
        ]);
    }
}
