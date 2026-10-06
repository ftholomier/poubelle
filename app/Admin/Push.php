<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Data\Activity;
use App\Services\Notifications;
use App\Services\WebPush;

/**
 * Communauté › Notifications (administrateurs) : abonnés de l'appli du musée, envoi d'une
 * notification par l'équipe (aperçu, sujet, version anglaise facultative), envois automatiques
 * à venir, historique (reçus, abonnements disparus, ouvertures).
 */
final class Push extends Base
{
    /** Communauté › Carnets du supporter : combien, et les matchs les plus « vécus » (aucune donnée personnelle). */
    public static function carnets(Request $req): Response
    {
        return self::html('admin/community/carnets', ['o' => \App\Services\Carnet::overview()], ['title' => 'Carnets du supporter', 'crumb' => 'Communauté', 'nav' => 'carnets']);
    }

    public static function index(Request $req): Response
    {
        $ready = WebPush::available();
        return self::html('admin/community/notifications', [
            'ready' => $ready,
            'enabled' => Notifications::enabled(),
            'stats' => Notifications::stats(),
            'queue' => Notifications::queue(),
            'history' => Notifications::history(),
            'due' => $ready ? Notifications::due() : [],
            // Annonce de l'ouverture : proposée tant que le musée est fermé, puis jusqu'à son envoi
            // si des visiteurs l'attendent (inscrits depuis la page d'attente).
            'waiting' => !Notifications::sent('ouverture') && (((bool) Settings::get('waiting.enabled', false) && (bool) Settings::get('app.push_waiting', true)) || Notifications::stats()['waiting'] > 0),
            'closed' => (bool) Settings::get('waiting.enabled', false),
            'old' => $req->query,
        ], ['title' => 'Notifications de l’appli', 'crumb' => 'Communauté', 'nav' => 'notifications', 'scripts' => ['admin/notifications.js']]);
    }

    public static function action(Request $req): Response
    {
        $p = $req->post;
        $action = (string) ($p['action'] ?? '');
        if ($action === 'envoyer') {
            if (!Notifications::enabled()) {
                return self::back('/admin/notifications', null, 'Les notifications sont désactivées (Réglages › Application du musée).');
            }
            $title = trim((string) ($p['title'] ?? ''));
            $body = trim((string) ($p['body'] ?? ''));
            $link = trim((string) ($p['url'] ?? '')) ?: '/';
            $topic = (string) ($p['topic'] ?? 'nouvelles');
            $back = '/admin/notifications?' . http_build_query(array_intersect_key($p, array_flip(['title', 'body', 'url', 'topic', 'title_en', 'body_en', 'annonce'])));
            // Annonce de l'ouverture : une seule fois (clé « ouverture »).
            $key = ($p['annonce'] ?? '') === 'ouverture' ? 'ouverture' : 'manuel:' . bin2hex(random_bytes(6));
            if ($title === '' || mb_strlen($title) > 60 || mb_strlen($body) > 180) {
                return self::back($back, null, 'Un titre (60 caractères au plus) et un texte de 180 caractères au plus.');
            }
            if (!isset(Notifications::TOPICS[$topic])) {
                return self::back($back, null, 'Sujet inconnu.');
            }
            // Lien : une page du musée (/…) ou son adresse complète.
            $base = rtrim((string) Settings::get('general.base_url', ''), '/');
            if ($base !== '' && str_starts_with($link, $base . '/')) {
                $link = substr($link, strlen($base));
            }
            if (!preg_match('#^/(?!/)\S*$#', $link)) {
                return self::back($back, null, 'Le lien doit mener à une page du musée : une adresse qui commence par « / » (ex. /centenaire/).');
            }
            $en = [];
            $titleEn = trim((string) ($p['title_en'] ?? ''));
            if ($titleEn !== '') {
                $en = ['en' => ['title' => mb_substr($titleEn, 0, 60), 'body' => mb_substr(trim((string) ($p['body_en'] ?? '')), 0, 180), 'url' => str_starts_with($link, '/en/') ? $link : ($link === '/' ? '/en/' : '/en' . $link)]];
            }
            $u = Auth::user();
            $r = Notifications::enqueue($key, $topic, ['fr' => ['title' => $title, 'body' => $body, 'url' => $link]] + $en, ['by' => (string) ($u['name'] ?? '')]);
            if (!$r['ok']) {
                return self::back($back, null, $r['error'] ?? 'Envoi impossible.');
            }
            Activity::log($u, 'a envoyé la notification « ' . $title . ' » (' . Notifications::TOPICS[$topic][0] . ', ' . $r['n'] . ' abonné(s))', null);
            @set_time_limit(120);
            $res = Notifications::process(45);
            $left = $res['left'] ?? 0;
            return self::back('/admin/notifications', 'Notification envoyée à ' . $r['n'] . ' abonné(s)' . ($res ? ' : ' . $res['ok'] . ' reçue(s) par les services de notifications' . ($res['gone'] ? ', ' . $res['gone'] . ' abonnement(s) disparu(s) effacé(s)' : '') : '') . ($left ? ' ; ' . $left . ' en attente (suite au prochain passage de la tâche planifiée)' : '') . '.');
        }
        if ($action === 'relancer') {
            @set_time_limit(180);
            $res = Notifications::process(90);
            return self::back('/admin/notifications', $res ? $res['sent'] . ' envoyée(s), ' . $res['left'] . ' en attente.' : 'Rien à envoyer (ou un envoi est déjà en cours).');
        }
        return self::back('/admin/notifications', null, 'Action inconnue.');
    }
}
