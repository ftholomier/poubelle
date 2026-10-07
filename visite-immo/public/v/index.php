<?php
declare(strict_types=1);

// Page publique d'un bien (vitrine) avec formulaire de visite et assistant acquéreurs 24 h/24.
//   /v/?b=<slug>                 page
//   /v/?b=<slug>&photo=<n>       photo n° n
//   POST /v/?b=<slug>&a=chat     message à l'assistant
//   POST /v/?b=<slug>&a=contact  demande de visite / de contact

require __DIR__ . '/../../app/bootstrap.php';
set_time_limit(90);

$slug = (string) ($_GET['b'] ?? '');
$trouve = preg_match('/^[a-z0-9-]{3,80}$/', $slug) ? vitrine_trouver($slug) : null;
if (!$trouve) {
    http_response_code(404);
    espace_gabarit('Annonce introuvable', '<section class="es-card"><h2>Cette annonce n\'est plus en ligne.</h2><p>Le bien a peut-être été vendu.</p></section>');
    exit;
}
[$agent, $v] = $trouve;

if (isset($_GET['photo'])) {
    $p = $v['photos'][(int) $_GET['photo']] ?? null;
    if (!$p) { http_response_code(404); exit; }
    servir_image(chemin_photo($agent, $v['id'], $p['staging']['fichier'] ?? $p['fichier'], !empty($_GET['mini'])));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'vitrine') send_json(['error' => 'Requête refusée.'], 403);
    $in = json_input();
    try {
        // Limite simple contre les abus : 40 messages par heure et par adresse IP
        $cle = DATA_DIR . '/cache/limites/' . md5(($_SERVER['REMOTE_ADDR'] ?? '') . date('YmdH')) . '.txt';
        if (!is_dir(dirname($cle))) mkdir(dirname($cle), 0770, true);
        $n = (int) @file_get_contents($cle) + 1;
        file_put_contents($cle, (string) $n);
        if ($n > 40) fail(429, 'Trop de messages, réessayez plus tard ou appelez l\'agence.');

        if (($_GET['a'] ?? '') === 'chat') {
            $conv = preg_match('/^[a-f0-9]{16}$/', (string) ($in['conversation'] ?? '')) ? $in['conversation'] : bin2hex(random_bytes(8));
            $fichier = DATA_DIR . "/conversations/{$v['id']}-$conv.json";
            $hist = read_json($fichier, []);
            $message = mb_substr(trim((string) ($in['message'] ?? '')), 0, 800);
            if ($message === '') fail(400, 'Message vide.');
            $r = assistant_repondre($agent, $v, $hist, $message);
            $hist[] = ['de' => 'client', 'texte' => $message, 'date' => date('c')];
            $hist[] = ['de' => 'assistant', 'texte' => $r['reponse'], 'date' => date('c')];
            write_json($fichier, $hist);
            $contact = array_filter((array) ($r['contact'] ?? []));
            $resa = null;
            if (($r['action'] ?? '') === 'reserver' && !empty($contact['nom']) && (!empty($contact['telephone']) || !empty($contact['email']))) {
                $resa = enregistrer_contact($agent, $v['id'], $contact + ['creneau' => $r['creneau'] ?? '', 'conversation' => $conv, 'message' => $message], 'Assistant de la page du bien');
            }
            send_json(['conversation' => $conv, 'reponse' => $r['reponse'], 'creneaux' => array_map(fn ($c) => ['valeur' => $c, 'libelle' => libelle_creneau($c)], $r['creneaux'] ?? []), 'reserve' => (bool) ($resa['rdv'] ?? false)]);
        }
        if (($_GET['a'] ?? '') === 'contact') {
            $r = enregistrer_contact($agent, $v['id'], $in, 'Formulaire de la page du bien');
            send_json(['ok' => true, 'rdv' => $r['rdv'] ? libelle_creneau($r['rdv']['debut']) : null]);
        }
        fail(404, 'Action inconnue.');
    } catch (HttpError $e) {
        send_json(['error' => $e->getMessage()], $e->getCode());
    } catch (Throwable $e) {
        error_log((string) $e);
        send_json(['error' => 'Service momentanément indisponible.'], 500);
    }
}

// Statistique de consultation (une par jour et par visiteur)
if (empty($_COOKIE['vu_' . substr(md5($slug), 0, 8)])) {
    setcookie('vu_' . substr(md5($slug), 0, 8), '1', ['expires' => strtotime('tomorrow'), 'path' => '/', 'samesite' => 'Lax']);
    update_visit($agent, $v['id'], function (array $x) {
        $x['vitrine']['vues'][date('Y-m-d')] = ($x['vitrine']['vues'][date('Y-m-d')] ?? 0) + 1;
        return $x;
    });
}
echo page_vitrine($agent, $v, $slug);
