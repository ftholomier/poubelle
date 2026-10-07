<?php
// Espace client sans mot de passe (vendeur, acquéreur, notaire) : un lien personnel donne accès aux documents
// du dossier, à la signature en ligne et au dépôt des pièces. Page rendue par public/espace/index.php.
// Les modules ajoutent leurs blocs : espace_section('vendeur', 30, fn (array $ctx) => '<section>…</section>').

$ESPACE_SECTIONS = [];

function espace_section(string $role, int $ordre, callable $fn): void
{
    global $ESPACE_SECTIONS;
    $ESPACE_SECTIONS[$role][$ordre] = $fn;
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES);
}

/** Documents qu'un rôle peut télécharger. */
function espace_docs_autorises(string $role): array
{
    return match ($role) {
        'vendeur' => ['vendeur', 'avis', 'fiche', 'annonce', 'mandat', 'plan', 'cr_hebdo'],
        'acquereur' => ['fiche', 'annonce', 'plan'],
        'notaire' => ['fiche', 'mandat', 'notaire', 'offre', 'vigilance'],
        default => [],
    };
}

// ---------- Blocs de l'espace vendeur ----------

espace_section('vendeur', 10, function (array $ctx): string {
    // À signer (le lien vise un signataire précis, ou le vendeur 1 par défaut)
    $v = $ctx['v'];
    $html = '';
    foreach ($v['signatures'] ?? [] as $cle => $d) {
        if ($d['statut'] !== 'en_attente' || $d['mode'] !== 'interne') continue;
        foreach ($d['signataires'] as $s) {
            if ($s['role'] !== $ctx['role'] || $s['signe_le']) continue;
            if (!empty($ctx['lien']['signataire']) && $ctx['lien']['signataire'] !== $s['id'] && ($ctx['lien']['doc'] ?? '') === $cle) continue;
            $html .= '<section class="es-card es-signer" data-doc="' . e($cle) . '" data-signataire="' . e($s['id']) . '">
              <span class="es-tag">À signer</span>
              <h2>' . e($d['label']) . '</h2>
              <p>Signataire : <strong>' . e($s['nom']) . '</strong></p>
              <a class="es-btn" href="?t=' . e($ctx['token']) . '&pdf=' . e(explode(':', $cle)[0] === 'mandat' ? 'mandat' : $cle) . '" target="_blank">📄 Lire le document</a>
              <label class="es-check"><input type="checkbox" class="es-lu"> J\'ai lu le document et j\'accepte de le signer électroniquement.</label>
              <div class="es-code" hidden><label>Code reçu par e-mail<input inputmode="numeric" maxlength="6" class="es-code-in" placeholder="6 chiffres"></label><button type="button" class="es-lien es-renvoyer">Renvoyer le code</button></div>
              <div class="es-pad-zone" hidden><p class="es-aide">Signez avec le doigt dans le cadre :</p><canvas class="es-pad"></canvas><button type="button" class="es-lien es-effacer">Effacer</button></div>
              <button type="button" class="es-btn es-primaire es-go">✍️ Signer</button>
              <p class="es-msg"></p>
            </section>';
        }
    }
    return $html;
});

espace_section('vendeur', 20, function (array $ctx): string {
    $v = $ctx['v'];
    $docs = [];
    if (trim($v['rapport_vendeur'] ?? '') !== '') $docs[] = ['vendeur', 'Compte rendu de visite'];
    if (!empty($v['avis_valeur'])) $docs[] = ['avis', 'Avis de valeur'];
    if (!empty($v['genere_le'])) $docs[] = ['fiche', 'Fiche du bien'];
    if (trim($v['annonce'] ?? '') !== '') $docs[] = ['annonce', 'Annonce'];
    if (!empty($v['plan']['pieces'])) $docs[] = ['plan', 'Croquis de plan'];
    if (!empty($v['mandat']['numero'])) $docs[] = ['mandat', ($v['signatures']['mandat']['statut'] ?? '') === 'signe' ? 'Mandat de vente signé' : 'Mandat de vente'];
    foreach ($ctx['docs_extra'] ?? [] as $d) $docs[] = $d;
    if (!$docs) return '';
    $li = implode('', array_map(fn ($d) => '<a class="es-doc" href="?t=' . e($ctx['token']) . '&pdf=' . e($d[0]) . '" target="_blank"><span>📄</span>' . e($d[1]) . '<span class="es-fl">→</span></a>', $docs));
    return "<section class=\"es-card\"><h2>Vos documents</h2>$li</section>";
});

espace_section('vendeur', 30, function (array $ctx): string {
    $v = actualiser_pieces($ctx['v']);
    if (!$v['pieces']) return '';
    $manque = count(pieces_manquantes($v));
    $li = '';
    foreach ($v['pieces'] as $p) {
        $recue = ($p['statut'] ?? '') === 'recue';
        $li .= '<div class="es-piece ' . ($recue ? 'ok' : '') . '"><div><strong>' . ($recue ? '✓ ' : '') . e($p['label']) . '</strong>'
            . ($p['aide'] ? '<span class="es-aide">' . e($p['aide']) . '</span>' : '') . (!empty($p['facultative']) ? '<span class="es-aide">Facultatif</span>' : '') . '</div>'
            . '<label class="es-btn es-petit">' . ($recue ? 'Ajouter' : '📷 Déposer') . '<input type="file" accept="image/*,application/pdf" data-piece="' . e($p['cle']) . '" hidden></label></div>';
    }
    return '<section class="es-card"><h2>Documents à fournir ' . ($manque ? "<span class=\"es-badge\">$manque à déposer</span>" : '<span class="es-badge ok">complet</span>') . '</h2>'
        . '<p class="es-aide">Prenez simplement une photo avec votre téléphone, ou choisissez un PDF. Chaque document est lu automatiquement.</p>' . $li . '<p class="es-msg" id="msg-pieces"></p></section>';
});

// ---------- Rendu de la page ----------

function espace_page(string $token): void
{
    global $CONFIG, $ESPACE_SECTIONS;
    $lien = lien_lire($token);
    $trouve = $lien ? chercher_dossier($lien['dossier']) : null;
    if (!$lien || !$trouve || $trouve[0]['id'] !== $lien['agent']) {
        http_response_code(404);
        espace_gabarit('Lien expiré', '<section class="es-card"><h2>Ce lien n\'est plus valable.</h2><p>Demandez un nouveau lien à votre conseiller.</p></section>');
        return;
    }
    [$agent, $v] = $trouve;
    // Suivi du dossier : dernière consultation de l'espace par le vendeur, l'acquéreur, le notaire
    $role = (string) $lien['role'];
    if (strtotime($v['espace_vu'][$role] ?? '2000-01-01') < time() - 600) {
        $v = update_visit($agent, $v['id'], function (array $x) use ($role) {
            if (empty($x['espace_vu'][$role])) journal_ajout($x, 'espace', ['vendeur' => 'Le vendeur', 'acquereur' => "L'acquéreur", 'notaire' => 'Le notaire'][$role] . ' a ouvert son espace pour la première fois.');
            $x['espace_vu'][$role] = date('c');
            return $x;
        });
    }
    $ctx = ['token' => $token, 'lien' => $lien, 'role' => $lien['role'], 'agent' => $agent, 'v' => $v];
    $sections = $ESPACE_SECTIONS[$lien['role']] ?? [];
    ksort($sections);
    $corps = '';
    foreach ($sections as $fn) $corps .= $fn($ctx);
    $prix = champ($v, 'mandat_prix') ?: champ($v, 'prix_souhaite');
    $roleTxt = ['vendeur' => 'Espace vendeur', 'acquereur' => 'Espace acquéreur', 'notaire' => 'Espace notaire'][$lien['role']] ?? 'Votre espace';
    $tete = '<header class="es-tete"><span class="es-tag">' . e($roleTxt) . '</span><h1>' . e(titre_bien($v)) . '</h1>'
        . '<p class="es-sous">' . e(implode(' · ', array_filter([champ($v, 'type_bien'), champ($v, 'surface_habitable') ? champ($v, 'surface_habitable') . ' m²' : '', $prix ? number_format((float) $prix, 0, ',', ' ') . ' €' : '']))) . '</p></header>';
    $contact = '<section class="es-card es-noir"><span class="es-mono">Votre conseiller</span><strong>' . e($agent['nom']) . ' · ' . e((string) $CONFIG['agence']) . '</strong>'
        . '<p>' . implode(' · ', array_filter([($agent['telephone'] ?? '') ? '<a href="tel:' . e(preg_replace('/\s/', '', $agent['telephone'])) . '">' . e($agent['telephone']) . '</a>' : '', ($agent['email'] ?? '') ? '<a href="mailto:' . e($agent['email']) . '">' . e($agent['email']) . '</a>' : ''])) . '</p></section>';
    espace_gabarit($roleTxt, $tete . ($corps ?: '<section class="es-card"><p>Votre conseiller ajoutera bientôt vos documents ici.</p></section>') . $contact);
}

function espace_gabarit(string $titre, string $corps): void
{
    global $CONFIG;
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    $logo = uploaded_logo_path() ? '../api/?r=logo' : '../img/synapse-logo.svg';
    $v = substr(md5((string) @filemtime(APP_ROOT . '/public/css/espace.css') . @filemtime(APP_ROOT . '/public/js/espace.js') . @filemtime(APP_ROOT . '/public/js/pad.js')), 0, 8);
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>' . e($titre) . ' · ' . e((string) $CONFIG['agence']) . '</title><link rel="icon" href="../icon.svg"><link rel="stylesheet" href="../css/espace.css?v=' . $v . '">'
        . '<script type="importmap">{"imports":{"../js/pad.js":"../js/pad.js?v=' . $v . '"}}</script></head><body>'
        . '<div class="es-page"><img class="es-logo" src="' . $logo . '" alt="' . e((string) $CONFIG['agence']) . '">' . $corps
        . '<p class="es-pied">' . e((string) $CONFIG['agence']) . ' · lien personnel, ne le transférez pas.</p></div>'
        . '<script type="module" src="../js/espace.js?v=' . $v . '"></script></body></html>';
}

/** Actions de l'espace (appelées en JavaScript depuis la page) : code, signer, pièce. */
function espace_action(string $token, string $action): never
{
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'espace') send_json(['error' => 'Requête refusée.'], 403);
    $lien = lien_lire($token) ?? send_json(['error' => 'Lien expiré.'], 403);
    [$agent, $v] = chercher_dossier($lien['dossier']) ?? send_json(['error' => 'Dossier introuvable.'], 404);
    $in = json_input();
    try {
        switch ($action) {
            case 'code':
                $s = signataire($v, (string) $in['doc'], (string) $in['signataire']);
                if ($s['role'] !== $lien['role']) fail(403, 'Non autorisé.');
                send_json(['envoye' => envoyer_code_signature($agent, $v['id'], (string) $in['doc'], (string) $in['signataire'])]);
            case 'signer':
                $s = signataire($v, (string) $in['doc'], (string) $in['signataire']);
                if ($s['role'] !== $lien['role']) fail(403, 'Non autorisé.');
                if (email_configure() && valid_email($s['email'] ?? '') && empty($in['code'])) fail(400, 'Saisissez le code reçu par e-mail.');
                enregistrer_signature($agent, $v['id'], (string) $in['doc'], (string) $in['signataire'], (string) ($in['image'] ?? ''), 'lien', (string) ($in['code'] ?? ''));
                send_json(['ok' => true]);
            case 'piece':
                if ($lien['role'] !== 'vendeur') fail(403, 'Non autorisé.');
                $v = deposer_piece($agent, $v['id'], $_FILES['fichier'] ?? [], (string) ($_POST['cle'] ?? 'auto'), 'vendeur');
                send_json(['ok' => true, 'manquantes' => count(pieces_manquantes(actualiser_pieces($v)))]);
            default:
                global $ESPACE_ACTIONS;
                if (isset($ESPACE_ACTIONS[$action])) ($ESPACE_ACTIONS[$action])($agent, $v, $lien, $in);
                fail(404, 'Action inconnue.');
        }
    } catch (HttpError $e) {
        send_json(['error' => $e->getMessage()], $e->getCode());
    } catch (Throwable $e) {
        error_log((string) $e);
        send_json(['error' => $e->getMessage()], 500);
    }
}

$ESPACE_ACTIONS = [];
/** Action supplémentaire de l'espace client : fn (array $agent, array $visit, array $lien, array $in): never */
function espace_action_module(string $nom, callable $fn): void
{
    global $ESPACE_ACTIONS;
    $ESPACE_ACTIONS[$nom] = $fn;
}

function espace_pdf(string $token, string $doc): never
{
    $lien = lien_lire($token);
    $trouve = $lien ? chercher_dossier($lien['dossier']) : null;
    if (!$trouve) {
        http_response_code(404);
        exit('Lien expiré.');
    }
    [$agent, $v] = $trouve;
    $prefixe = explode(':', $doc)[0];
    if (!in_array($prefixe, espace_docs_autorises($lien['role']), true) && !isset($v['signatures'][$doc])) {
        http_response_code(403);
        exit('Document non disponible.');
    }
    try {
        [$bin, $nom] = isset($v['signatures'][$doc]) ? pdf_signe($v, $agent, $doc) : build_pdf($prefixe, $v, $agent, ['cle' => $doc] + $_GET);
    } catch (Throwable $e) {
        http_response_code(404);
        exit('Document non disponible.');
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $nom . '"');
    header('Cache-Control: private, no-store');
    echo $bin;
    exit;
}
