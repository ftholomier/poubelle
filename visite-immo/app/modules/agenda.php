<?php
// Agenda de l'agent : rendez-vous (visites, estimations, signatures…), créneaux libres proposés aux acquéreurs
// par l'assistant, abonnement depuis Google Agenda ou Apple Calendrier (lien ICS personnel, sans connexion).

const TYPES_RDV = ['visite' => 'Visite', 'estimation' => 'Estimation', 'signature' => 'Signature', 'appel' => 'Appel', 'autre' => 'Rendez-vous'];
const DISPO_DEFAUT = ['jours' => [1, 2, 3, 4, 5, 6], 'debut' => '09:00', 'fin' => '19:00', 'pause' => ['12:30', '14:00'], 'duree_visite' => 45];

function agenda(array $agent): array
{
    $l = collection($agent, 'agenda');
    usort($l, fn ($a, $b) => strcmp($a['debut'], $b['debut']));
    return $l;
}

function disponibilites(array $agent): array
{
    return ($agent['disponibilites'] ?? []) + DISPO_DEFAUT;
}

function rdv_enregistrer(array $agent, array $in): array
{
    $debut = strtotime((string) ($in['debut'] ?? '')) ?: fail(400, 'Date du rendez-vous invalide.');
    $duree = max(10, min(600, (int) ($in['duree'] ?? 45)));
    $fin = !empty($in['fin']) ? (strtotime((string) $in['fin']) ?: $debut + $duree * 60) : $debut + $duree * 60;
    $r = array_filter([
        'id' => isset($in['id']) && preg_match('/^[a-z]?\w{6,40}$/', (string) $in['id']) ? (string) $in['id'] : null, // sert aussi d'UID dans le calendrier
        'debut' => date('c', $debut), 'fin' => date('c', $fin),
        'type' => isset(TYPES_RDV[$in['type'] ?? '']) ? $in['type'] : 'autre',
        'titre' => mb_substr(trim((string) ($in['titre'] ?? '')), 0, 120),
        'lieu' => mb_substr(trim((string) ($in['lieu'] ?? '')), 0, 200),
        'notes' => mb_substr(trim((string) ($in['notes'] ?? '')), 0, 1000),
        'dossier' => valid_id((string) ($in['dossier'] ?? '')) ? $in['dossier'] : null,
        'acquereur' => preg_match('/^a\w{6,30}$/', (string) ($in['acquereur'] ?? '')) ? $in['acquereur'] : null,
        'source' => mb_substr((string) ($in['source'] ?? 'agent'), 0, 40),
    ], fn ($x) => $x !== null && $x !== '');
    if (($r['titre'] ?? '') === '') $r['titre'] = TYPES_RDV[$r['type']];
    $r = collection_enregistrer($agent, 'agenda', $r);
    // Une visite planifiée apparaît aussi dans le dossier du bien
    if ($r['type'] === 'visite' && !empty($r['dossier'])) {
        $a = !empty($r['acquereur']) ? collection_trouver($agent, 'acquereurs', $r['acquereur']) : null;
        update_visit($agent, $r['dossier'], function (array $v) use ($r, $a) {
            foreach ($v['visites_acq'] ?? [] as $i => $x) {
                if (($x['rdv'] ?? '') === $r['id']) {
                    $v['visites_acq'][$i]['date'] = $r['debut'];
                    return $v;
                }
            }
            $v['visites_acq'][] = ['id' => nouvel_id('va'), 'rdv' => $r['id'], 'date' => $r['debut'], 'acquereur' => $a['id'] ?? null, 'nom' => $a ? nom_acquereur($a) : ($r['titre'] ?? 'Visiteur'), 'statut' => 'prevue', 'source' => $r['source'] ?? 'agent'];
            journal_ajout($v, 'visite', 'Visite planifiée le ' . date('d/m à H:i', strtotime($r['debut'])) . ($a ? ' avec ' . nom_acquereur($a) : '') . '.');
            return $v;
        });
        if ($a && in_array($a['statut'], ['nouveau', 'qualifie'], true)) collection_enregistrer($agent, 'acquereurs', ['statut' => 'visite'] + $a);
    }
    return $r;
}

/** Créneaux libres des prochains jours (pour l'assistant acquéreurs ou l'agent). */
function creneaux_libres(array $agent, int $jours = 10, int $max = 12, ?int $depuis = null): array
{
    $d = disponibilites($agent);
    $duree = (int) $d['duree_visite'] * 60;
    $occupe = array_map(fn ($r) => [strtotime($r['debut']) - 15 * 60, strtotime($r['fin']) + 15 * 60], agenda($agent));
    $out = [];
    $t0 = $depuis ?? maintenant();
    for ($j = 0; $j <= $jours && count($out) < $max; $j++) {
        $jour = strtotime(date('Y-m-d', $t0) . " +$j day");
        if (!in_array((int) date('N', $jour), $d['jours'], true)) continue;
        $debut = strtotime(date('Y-m-d', $jour) . ' ' . $d['debut']);
        $fin = strtotime(date('Y-m-d', $jour) . ' ' . $d['fin']);
        for ($t = $debut; $t + $duree <= $fin && count($out) < $max; $t += 30 * 60) {
            if ($t < $t0 + 3 * 3600) continue; // jamais dans les 3 prochaines heures
            $p0 = strtotime(date('Y-m-d', $jour) . ' ' . $d['pause'][0]);
            $p1 = strtotime(date('Y-m-d', $jour) . ' ' . $d['pause'][1]);
            if ($t < $p1 && $t + $duree > $p0) continue;
            foreach ($occupe as [$a, $b]) if ($t < $b && $t + $duree > $a) continue 2;
            $out[] = date('c', $t);
            $t += 60 * 60; // espace les propositions
        }
    }
    return $out;
}

function libelle_creneau(string $iso): string
{
    $jours = ['', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $t = strtotime($iso);
    return $jours[(int) date('N', $t)] . ' ' . date('j', $t) . ' ' . $mois[(int) date('n', $t)] . ' à ' . date('G\hi', $t);
}

// ---------- Flux ICS (abonnement calendrier) ----------

function ics_echapper(string $s): string
{
    return str_replace(["\\", ";", ",", "\n"], ["\\\\", "\\;", "\\,", "\\n"], $s);
}

function flux_ics(array $agent): string
{
    global $CONFIG;
    $l = ["BEGIN:VCALENDAR", "VERSION:2.0", "PRODID:-//Synapse//Visite Immo//FR", "CALSCALE:GREGORIAN", "X-WR-CALNAME:" . ics_echapper($CONFIG['agence'] . ' · ' . $agent['nom']), "X-WR-TIMEZONE:Europe/Paris"];
    foreach (agenda($agent) as $r) {
        if (strtotime($r['debut']) < time() - 60 * 86400) continue;
        $desc = trim(($r['notes'] ?? '') . (!empty($r['dossier']) ? "\n" . url_publique('#/visite/' . $r['dossier']) : ''));
        array_push($l, "BEGIN:VEVENT", "UID:{$r['id']}@visite-immo", 'DTSTAMP:' . gmdate('Ymd\THis\Z'), 'DTSTART:' . gmdate('Ymd\THis\Z', strtotime($r['debut'])), 'DTEND:' . gmdate('Ymd\THis\Z', strtotime($r['fin'])),
            'SUMMARY:' . ics_echapper(TYPES_RDV[$r['type']] . ' · ' . $r['titre']), 'LOCATION:' . ics_echapper($r['lieu'] ?? ''), 'DESCRIPTION:' . ics_echapper($desc), "END:VEVENT");
    }
    $l[] = "END:VCALENDAR";
    return implode("\r\n", array_map('ics_plier', $l)) . "\r\n";
}

/** Lignes de 75 octets au plus (norme iCalendar), sans couper un caractère accentué. */
function ics_plier(string $ligne): string
{
    $out = [];
    $courant = '';
    foreach (mb_str_split($ligne) as $c) {
        if (strlen($courant . $c) > 73) {
            $out[] = $courant;
            $courant = '';
        }
        $courant .= $c;
    }
    $out[] = $courant;
    return implode("\r\n ", $out);
}

function jeton_ics(array $agent): string
{
    if (!empty($agent['ics'])) return $agent['ics'];
    $t = bin2hex(random_bytes(16));
    update_json(USERS_FILE, fn (array $users) => array_map(fn ($u) => $u['id'] === $agent['id'] ? $u + ['ics' => $t] : $u, $users));
    return $t;
}

// ---------- Dictée d'un rendez-vous ----------

type_dictee('rdv',
    s_obj(['date' => s_txt(), 'heure' => s_txt(), 'duree' => s_num(), 'type' => s_enum(array_keys(TYPES_RDV)), 'titre' => s_txt(), 'lieu' => s_txt(), 'notes' => s_txt()], ['date', 'heure', 'type', 'titre']),
    "L'agent dicte un rendez-vous à ajouter à son agenda. Déduis la date exacte (« jeudi » = le prochain jeudi), l'heure, la durée en minutes (45 par défaut pour une visite), le type, un titre court, le lieu.",
    fn (string $t) => ['date' => date('Y-m-d', strtotime('next thursday')), 'heure' => '14:30', 'duree' => 45, 'type' => 'estimation', 'titre' => 'Estimation chez M. Bernard', 'lieu' => '4 rue des Lilas, Lougres', 'notes' => ''],
);
apres_dictee('rdv', fn (array $agent, array $d) => ['rdv' => rdv_enregistrer($agent, ['debut' => "{$d['date']} {$d['heure']}"] + $d)]);

// ---------- API ----------

route('GET agenda', function () {
    $me = require_user();
    $de = strtotime((string) ($_GET['de'] ?? '')) ?: strtotime('today');
    $a = strtotime((string) ($_GET['a'] ?? '')) ?: $de + 14 * 86400;
    $acq = array_column(acquereurs($me), null, 'id');
    $titres = [];
    foreach (dossiers($me) as $v) $titres[$v['id']] = titre_bien($v);
    $l = array_values(array_filter(agenda($me), fn ($r) => strtotime($r['fin']) >= $de && strtotime($r['debut']) <= $a));
    $l = array_map(fn ($r) => $r + ['bien' => $titres[$r['dossier'] ?? ''] ?? null, 'acquereur_nom' => isset($acq[$r['acquereur'] ?? '']) ? nom_acquereur($acq[$r['acquereur']]) : null], $l);
    send_json(['rdv' => $l, 'ics' => url_publique('ics.php?t=' . jeton_ics($me)), 'disponibilites' => disponibilites($me), 'creneaux' => creneaux_libres($me, 7, 6)]);
});

route('POST rdv', function ($id) {
    $me = require_user();
    $in = json_input();
    if ($id !== '') $in['id'] = $id;
    send_json(rdv_enregistrer($me, $in));
});

route('DELETE rdv', function ($id) {
    $me = require_user();
    $r = collection_trouver($me, 'agenda', $id);
    collection_supprimer($me, 'agenda', $id);
    if ($r && !empty($r['dossier'])) {
        update_visit($me, $r['dossier'], function (array $v) use ($id) {
            $v['visites_acq'] = array_values(array_filter($v['visites_acq'] ?? [], fn ($x) => ($x['rdv'] ?? '') !== $id || ($x['statut'] ?? '') !== 'prevue'));
            return $v;
        });
    }
    send_json(['ok' => true]);
});

route('POST disponibilites', function () {
    $me = require_user();
    $in = json_input();
    $heure = fn ($h, $def) => preg_match('/^\d{2}:\d{2}$/', (string) $h) ? $h : $def;
    $d = [
        'jours' => array_values(array_filter(array_map('intval', (array) ($in['jours'] ?? [])), fn ($j) => $j >= 1 && $j <= 7)) ?: DISPO_DEFAUT['jours'],
        'debut' => $heure($in['debut'] ?? '', '09:00'), 'fin' => $heure($in['fin'] ?? '', '19:00'),
        'pause' => [$heure($in['pause'][0] ?? '', '12:30'), $heure($in['pause'][1] ?? '', '14:00')],
        'duree_visite' => max(15, min(120, (int) ($in['duree_visite'] ?? 45))),
    ];
    update_json(USERS_FILE, fn (array $users) => array_map(fn ($u) => $u['id'] === $me['id'] ? ['disponibilites' => $d] + $u : $u, $users));
    send_json($d);
});

a_faire('agenda', function (array $agent): array {
    $items = [];
    $auj = date('Y-m-d', maintenant());
    foreach (agenda($agent) as $r) {
        if (substr($r['debut'], 0, 10) !== $auj) continue;
        $items[] = ['type' => 'rdv', 'titre' => TYPES_RDV[$r['type']] . ' · ' . $r['titre'], 'detail' => $r['lieu'] ?? '', 'heure' => date('H:i', strtotime($r['debut'])),
            'lien' => !empty($r['dossier']) ? "#/visite/{$r['dossier']}/vente" : '#/agenda', 'priorite' => 1, 'date' => $r['debut']];
    }
    return $items;
});
