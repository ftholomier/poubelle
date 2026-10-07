<?php
// Écran « Aujourd'hui » : tout ce que l'agent doit faire ou savoir aujourd'hui, rassemblé depuis tous les modules.

/** Liste triée des éléments du jour pour un agent. */
function elements_du_jour(array $agent): array
{
    global $A_FAIRE;
    $dossiers = dossiers($agent);
    $items = [];
    foreach ($A_FAIRE as $nom => $fn) {
        try {
            foreach ($fn($agent, $dossiers) as $it) $items[] = $it + ['source' => $nom, 'priorite' => 2];
        } catch (Throwable $e) {
            error_log("a_faire $nom : $e");
        }
    }
    usort($items, fn ($a, $b) => [$a['priorite'], $a['date'] ?? '9'] <=> [$b['priorite'], $b['date'] ?? '9']);
    return $items;
}

// Chaque dossier propose sa prochaine action
a_faire('dossiers', function (array $agent, array $dossiers): array {
    $items = [];
    foreach ($dossiers as $v) {
        if (empty($v['morceaux']) && empty($v['genere_le']) && empty((array) $v['fiche']['champs'])) continue;
        $actions = prochaines_actions($v);
        if (!$actions) continue;
        [$titre, $detail, $cle] = $actions[0];
        $items[] = [
            'type'     => 'dossier',
            'titre'    => $titre,
            'detail'   => titre_bien($v) . ' · ' . $detail,
            'lien'     => "#/visite/{$v['id']}/resume",
            'priorite' => in_array($cle, ['generer', 'dialogue', 'envoyer_vendeur'], true) ? 1 : 2,
            'date'     => $v['modifie_le'] ?? $v['cree_le'],
        ];
    }
    return $items;
});

route('GET aujourdhui', function () {
    $me = require_user();
    $cron = read_json(DATA_DIR . '/cron.json', []);
    send_json([
        'elements' => elements_du_jour($me),
        'cron'     => $cron['dernier'] ?? null,
    ]);
});
