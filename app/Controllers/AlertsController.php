<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\I18n;
use App\Services\JobAlerts;

/** Liens des e-mails d'alerte : accepter, refuser, se désinscrire. Un clic, sans compte. */
final class AlertsController extends Controller
{
    public function choose(Request $request, array $params): Response
    {
        $choice = (string) $request->get('choix', '');
        $ok = in_array($choice, ['oui', 'non', 'stop'], true)
            && JobAlerts::decide((string) $request->get('cv', ''), (string) $request->get('t', ''), $choice === 'oui');
        $message = !$ok ? I18n::t('alerts.bad_link')
                 : ($choice === 'oui' ? I18n::t('alerts.done_yes') : I18n::t('alerts.done_no'));
        return $this->page('pages/alerts', ['message' => $message, 'ok' => $ok], [
            'title' => I18n::t('alerts.title'), 'path' => '/alertes-emploi', 'robots' => 'noindex, nofollow',
        ])->withHeader('Cache-Control', 'private, no-store');
    }
}
