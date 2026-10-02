<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Str;
use App\Core\Url;
use App\Services\Ai;
use App\Services\Categories;
use App\Services\Pros;
use App\Services\Settings;
use App\Services\Store;

/** Intelligence artificielle (Gemini) : activation par rôle, budget, essais, traitements par lots, conversations. */
final class AiController extends AdminController
{
    public const ROLES = [
        'assistant' => ['Assistant des visiteurs', 'Bulle de discussion qui trouve les bons pros et prépare la demande de devis.'],
        'moderation' => ['Modération anti-spam', 'Note les demandes douteuses (spam / qualité) en complément des filtres classiques.'],
        'seo' => ['Rédaction SEO', 'Textes des pages locales, titres, brouillons d\'articles, aide à la rédaction des fiches pros.'],
        'classification' => ['Classement des pros', 'Propose les métiers d\'un pro à partir de ses textes.'],
    ];

    public function index(): Response
    {
        if (Request::isPost()) {
            $roles = [];
            foreach (self::ROLES as $k => $_) {
                $roles[$k] = !empty(Request::arr('roles')[$k]);
            }
            Settings::merge(['ai' => [
                'enabled' => Request::bool('enabled'),
                'roles' => $roles,
                'temperature' => max(0, min(1.5, (float) Request::input('temperature', 0.5))),
                'daily_limit' => max(10, min(100000, Request::int('daily_limit', 1500))),
                'assistant_name' => Sanitizer::line((string) Request::input('assistant_name', 'Confetti'), 30) ?: 'Confetti',
                'assistant_greeting' => Sanitizer::line((string) Request::input('assistant_greeting', ''), 300),
                'assistant_prompt' => Sanitizer::text((string) Request::input('assistant_prompt', ''), 4000),
            ]]);
            \App\Core\Cache::flush('pages');
            $this->audit('Réglages IA modifiés');
            return $this->done('Réglages de l\'IA enregistrés.', 'ia');
        }
        $usage = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $usage[$d] = Ai::usage($d);
        }
        return $this->page('ai', [
            'ai' => Settings::get('ai', []),
            'configured' => Ai::configured(),
            'model' => Ai::model(),
            'fast' => Ai::model(true),
            'usage' => $usage,
            'today' => Ai::usage(),
            'chats' => Store::chats()->count(),
            'unclassified' => Store::pros()->count(static fn ($p) => $p['status'] === 'active' && ($p['cats'] === ['animateur-soiree'] || !$p['cats'])),
        ], 'Intelligence artificielle', 'ai');
    }

    /** Bac à sable : envoie un message à Gemini (ou à l'assistant tel que les visiteurs le voient). */
    public function test(): Response
    {
        $prompt = Sanitizer::text((string) Request::input('prompt', ''), 4000);
        if ($prompt === '') {
            return Response::json(['error' => 'Message vide.'], 422);
        }
        if (Request::input('mode') === 'assistant') {
            $r = Ai::chat([['role' => 'user', 'text' => $prompt]]);
            $html = '<div class="alert alert-info"><div>' . nl2br(e($r['text'])) . '</div></div>';
            foreach ($r['cards'] as $c) {
                $html .= '<div class="small">• <a href="' . e($c['url']) . '" target="_blank">' . e($c['name']) . '</a> — ' . e($c['cat'] . ' · ' . $c['city']) . '</div>';
            }
            if ($r['quote']) {
                $html .= '<p class="small">Lien de devis proposé : <a href="' . e($r['quote']) . '" target="_blank">' . e($r['quote']) . '</a></p>';
            }
            return Response::json(['ok' => $r['error'] === null, 'html' => $html]);
        }
        $r = Ai::generate([['role' => 'user', 'parts' => [['text' => $prompt]]]], ['role' => 'test', 'max_tokens' => 1500, 'timeout' => 60]);
        if (!$r['ok']) {
            return Response::json(['error' => $r['error']], 502);
        }
        return Response::json(['ok' => true, 'html' => '<div class="box prose" style="box-shadow:none">' . Str::paragraphs($r['text']) . '</div>']);
    }

    /** Traitements par lots (appelés pas à pas par le navigateur). */
    public function batch(): Response
    {
        $job = (string) Request::input('step', '');
        $offset = max(0, Request::int('offset', 0));
        $limit = 8;
        if ($job === 'classify') {
            if (!Settings::aiOn('classification')) {
                return Response::json(['error' => 'Activez l\'IA et le rôle « Classement des pros ».'], 422);
            }
            $ids = [];
            foreach (Store::pros()->iterate(false) as $id => $p) {
                if ($p['status'] === 'active') {
                    $ids[] = (int) $id;
                }
            }
            $changed = 0;
            foreach (array_slice($ids, $offset, $limit) as $id) {
                $pro = Store::pros()->get($id);
                $cats = $pro ? Ai::classify($pro) : null;
                if ($cats && $cats !== ($pro['categories'] ?? [])) {
                    Store::pros()->update($id, static function (array $p) use ($cats): array {
                        $p['categories_before_ai'] = $p['categories'] ?? [];
                        $p['categories'] = $cats;
                        return $p;
                    }, false);
                    $changed++;
                }
            }
            $next = min(count($ids), $offset + $limit);
            if ($next >= count($ids)) {
                Pros::changed();
                $this->audit('Classement IA des pros terminé', ['fiches' => count($ids)]);
            }
            return Response::json(['done' => $next >= count($ids), 'offset' => $next, 'total' => count($ids), 'message' => "$next / " . count($ids) . " fiches analysées ($changed reclassées dans ce lot)"]);
        }
        return Response::json(['error' => 'Traitement inconnu'], 422);
    }

    public function chats(): Response
    {
        $id = (int) self::q('id', 10);
        $current = $id > 0 ? Store::chats()->get($id) : null;
        $items = Store::chats()->find(null, null, 300)['items'];
        return $this->page('chats', self::paginate($items, 50) + ['current' => $current, 'link' => self::pageLink()], 'Conversations de l\'assistant', 'ai');
    }
}
