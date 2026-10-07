<?php
declare(strict_types=1);

// Abonnement calendrier (Google Agenda, Apple Calendrier, Outlook) : /ics.php?t=<jeton personnel de l'agent>
require __DIR__ . '/../app/bootstrap.php';

$t = (string) ($_GET['t'] ?? '');
foreach (users() as $u) {
    if (!empty($u['ics']) && hash_equals($u['ics'], $t)) {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="agenda.ics"');
        echo flux_ics($u);
        exit;
    }
}
http_response_code(404);
echo 'Calendrier introuvable.';
