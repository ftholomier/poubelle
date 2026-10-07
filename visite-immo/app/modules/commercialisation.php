<?php
// Commercialisation : page publique du bien (vitrine), assistant acquéreurs 24 h/24 qui répond, qualifie et
// réserve les visites dans l'agenda, flux d'export pour les portails, statistiques de consultation.
// La publication n'est possible qu'avec un mandat signé (loi Hoguet).

const FICHIER_VITRINES = '/vitrines.json';

function vitrine_trouver(string $slug): ?array
{
    $map = read_json(DATA_DIR . FICHIER_VITRINES, []);
    if (!isset($map[$slug])) return null;
    $agent = user_by_id($map[$slug]['agent']);
    if (!$agent) return null;
    try {
        $v = load_visit($agent, $map[$slug]['dossier']);
    } catch (Throwable) {
        return null;
    }
    return empty($v['vitrine']['publiee']) ? null : [$agent, $v];
}

function publier(array $agent, string $id, bool $publier = true): array
{
    $v = load_visit($agent, $id);
    if ($publier && empty($v['mandat']['signe_le'])) fail(400, 'Le bien ne peut être diffusé qu\'avec un mandat signé.');
    $slug = $v['vitrine']['slug'] ?? (slug(($v['titre_annonce'] ?: titre_bien($v)), 50) . '-' . substr(md5($id), 0, 5));
    update_json(DATA_DIR . FICHIER_VITRINES, function (array $m) use ($slug, $agent, $id) {
        $m[$slug] = ['agent' => $agent['id'], 'dossier' => $id];
        return $m;
    });
    return update_visit($agent, $id, function (array $v) use ($slug, $publier) {
        $v['vitrine']['slug'] = $slug;
        $v['vitrine']['publiee'] = $publier;
        if ($publier) $v['vitrine']['publiee_le'] ??= date('c');
        journal_ajout($v, 'diffusion', $publier ? 'Bien publié : page du bien en ligne, flux portails mis à jour, acquéreurs compatibles prévenus.' : 'Diffusion suspendue.');
        return $v;
    });
}

/** Mentions de prix obligatoires dans une annonce (arrêté du 10 janvier 2017). */
function mention_prix(array $v): array
{
    $fai = (float) champ($v, 'mandat_prix') ?: (float) champ($v, 'prix_souhaite');
    $net = (float) champ($v, 'prix_souhaite');
    $hono = champ($v, 'mandat_honoraires');
    $charge = champ($v, 'mandat_honoraires_charge');
    $pct = null;
    if (str_contains($hono, '%')) $pct = (float) str_replace(',', '.', preg_replace('/[^\d,.]/', '', $hono));
    elseif (($h = (float) preg_replace('/[^\d]/', '', $hono)) && $fai) $pct = round($h / ($charge === 'Le vendeur' ? $fai : max(1, $fai - $h)) * 100, 1);
    $f = fn ($x) => number_format($x, 0, ',', ' ') . ' €';
    if ($charge === "L'acquéreur" && $fai) {
        $hors = $pct ? $fai / (1 + $pct / 100) : $net;
        $txt = "Prix honoraires inclus : {$f($fai)}" . ($pct ? ', dont ' . str_replace('.', ',', (string) $pct) . " % TTC d'honoraires à la charge de l'acquéreur" : '') . ($hors ? " (prix hors honoraires : {$f($hors)})" : '') . '.';
    } else {
        $txt = $fai ? "Prix : {$f($fai)}. Honoraires à la charge du vendeur." : '';
    }
    return ['prix' => $fai, 'texte' => $txt];
}

/** Ce que l'assistant et la page publique ont le droit de dire : jamais les champs internes (vendeur, mandat, juridique). */
function infos_publiques(array $v): array
{
    $champs = (array) $v['fiche']['champs'];
    $out = [];
    foreach (SECTIONS as $s) {
        if (!empty($s['interne'])) continue;
        foreach ($s['champs'] as $c) {
            $val = trim((string) ($champs[$c['cle']]['valeur'] ?? ''));
            if ($val !== '' && $c['cle'] !== 'adresse') $out[$c['label']] = $val . (isset($c['unite']) ? ' ' . $c['unite'] : '');
        }
    }
    return $out;
}

// ---------- Contacts entrants (formulaire, assistant) ----------

/** Un acquéreur se manifeste : fiche acquéreur, contact dans le dossier, rendez-vous si créneau choisi, e-mails. */
function enregistrer_contact(array $agent, string $id, array $c, string $canal): array
{
    $nom = mb_substr(trim((string) ($c['nom'] ?? '')), 0, 80);
    $tel = mb_substr(trim((string) ($c['telephone'] ?? '')), 0, 30);
    $email = mb_substr(trim((string) ($c['email'] ?? '')), 0, 120);
    if ($nom === '' || ($tel === '' && !valid_email($email))) fail(400, 'Indiquez votre nom et un téléphone ou un e-mail.');
    $v = load_visit($agent, $id);
    // Acquéreur existant (même e-mail ou téléphone) ou nouveau
    $a = null;
    foreach (acquereurs($agent) as $x) {
        if (($email && strcasecmp($x['email'] ?? '', $email) === 0) || ($tel && preg_replace('/\D/', '', $x['telephone'] ?? '') === preg_replace('/\D/', '', $tel))) $a = $x;
    }
    [$prenom, $nomFamille] = str_contains($nom, ' ') ? explode(' ', $nom, 2) : ['', $nom];
    $a = normaliser_acquereur(['prenom' => $a['prenom'] ?? $prenom, 'nom' => $a['nom'] ?? $nomFamille, 'telephone' => $tel, 'email' => $email, 'source' => $a['source'] ?? $canal,
        'criteres' => array_filter(['budget_max' => $c['budget'] ?? null, 'type' => champ($v, 'type_bien')]), 'financement' => array_filter(['mode' => $c['financement'] ?? null, 'accord_banque' => $c['accord_banque'] ?? null]), 'delai' => $c['delai'] ?? ''], $a ?? []);
    $a['historique'][] = ['date' => date('c'), 'texte' => "S'intéresse à " . titre_bien($v) . " ($canal)" . (!empty($c['message']) ? ' : « ' . mb_substr($c['message'], 0, 300) . ' »' : '')];
    $a = collection_enregistrer($agent, 'acquereurs', $a);

    $rdv = null;
    if (!empty($c['creneau']) && in_array($c['creneau'], creneaux_libres($agent, 14, 40), true)) {
        $rdv = rdv_enregistrer($agent, ['type' => 'visite', 'debut' => $c['creneau'], 'duree' => disponibilites($agent)['duree_visite'], 'dossier' => $id, 'acquereur' => $a['id'], 'titre' => 'Visite · ' . nom_acquereur($a), 'lieu' => trim(champ($v, 'adresse') . ' ' . champ($v, 'ville')) ?: titre_bien($v), 'source' => 'assistant']);
    }
    update_visit($agent, $id, function (array $v) use ($a, $canal, $c, $rdv) {
        $v['contacts'][] = ['date' => date('c'), 'acquereur' => $a['id'], 'nom' => nom_acquereur($a), 'canal' => $canal, 'message' => mb_substr((string) ($c['message'] ?? ''), 0, 1000), 'rdv' => $rdv['id'] ?? null, 'conversation' => $c['conversation'] ?? null];
        journal_ajout($v, 'contact', 'Nouveau contact : ' . nom_acquereur($a) . " ($canal)" . ($rdv ? ', visite réservée le ' . libelle_creneau($rdv['debut']) : '') . '.');
        return $v;
    });
    // Prévenir l'agent et confirmer à l'acquéreur
    $txtAgent = "Nouveau contact pour " . titre_bien($v) . " ($canal) :\n\n" . nom_acquereur($a) . "\n" . implode(' · ', array_filter([$tel, $email]))
        . (!empty($c['message']) ? "\n\nMessage : {$c['message']}" : '') . ($rdv ? "\n\nVisite réservée : " . libelle_creneau($rdv['debut']) . ' (ajoutée à votre agenda)' : '') . "\n\nFiche : " . url_publique('#/acquereur/' . $a['id']);
    try {
        if (valid_email($agent['email'] ?? '')) envoyer_mail_agent($agent, $agent['email'], ($rdv ? '📅 Visite réservée · ' : '🔔 Nouveau contact · ') . titre_bien($v), $txtAgent);
        if ($rdv && valid_email($email)) envoyer_mail_agent($agent, $email, 'Votre visite · ' . ($v['titre_annonce'] ?: titre_bien($v)), "Bonjour " . nom_acquereur($a) . ",\n\nVotre visite est confirmée " . libelle_creneau($rdv['debut']) . ".\nAdresse communiquée par votre conseiller la veille de la visite.\n\nÀ très bientôt,\n" . signature_agent($agent));
    } catch (Throwable $e) {
        error_log('contact : ' . $e->getMessage());
    }
    if (function_exists('notifier')) notifier($agent, $rdv ? 'Visite réservée' : 'Nouveau contact', nom_acquereur($a) . ' · ' . titre_bien($v), '#/acquereur/' . $a['id']);
    return ['acquereur' => $a, 'rdv' => $rdv];
}

// ---------- Assistant acquéreurs 24 h/24 ----------

function assistant_repondre(array $agent, array $v, array $historique, string $message): array
{
    global $CONFIG;
    $creneaux = creneaux_libres($agent, 10, 6);
    $prix = mention_prix($v);
    $infos = infos_publiques($v);
    if (empty($CONFIG['gemini_api_key'])) return assistant_demo($v, $message, $creneaux, $prix, $infos);

    $contexte = "Bien : " . ($v['titre_annonce'] ?: titre_bien($v)) . "\nVille : " . (champ($v, 'ville') ?: ($v['public']['geo']['city'] ?? '')) . "\n" . $prix['texte'] . "\n"
        . implode("\n", array_map(fn ($k, $x) => "$k : $x", array_keys($infos), $infos))
        . "\nAtouts : " . implode(', ', $v['points_forts'] ?? []) . "\nPièces : " . implode(', ', array_map(fn ($p) => $p['nom'] . (($p['surface'] ?? 0) ? " ({$p['surface']} m²)" : ''), $v['plan']['pieces'] ?? []))
        . "\nRisques (Géorisques) : " . implode(', ', $v['public']['risques']['liste'] ?? []) . "\nDescription :\n" . $v['annonce']
        . "\n\nCréneaux de visite libres (identifiants à reprendre tels quels) :\n" . implode("\n", array_map(fn ($c) => "$c = " . libelle_creneau($c), $creneaux));
    $consigne = "Tu es l'assistant de {$agent['nom']}, agent immobilier chez {$CONFIG['agence']}, sur la page d'annonce d'un bien. Tu réponds aux acquéreurs 24 h/24, en français, vouvoiement, phrases courtes, chaleureux et précis. "
        . "Réponds uniquement à partir des informations du bien ; si tu ne sais pas, dis que le conseiller répondra. Ne donne jamais l'adresse exacte, ni d'information sur le vendeur, ni de marge de négociation. "
        . "Qualifie l'acquéreur en douceur (budget, financement, délai) sans interrogatoire. Si la personne veut visiter : propose 2 ou 3 créneaux de la liste, demande son nom et son téléphone ou e-mail ; quand elle a choisi un créneau et donné ses coordonnées, mets action = reserver avec l'identifiant exact du créneau. "
        . "Remplis « contact » avec ce que la personne a donné (sinon vide).";
    $contents = [];
    foreach (array_slice($historique, -12) as $m) $contents[] = ['role' => $m['de'] === 'client' ? 'user' : 'model', 'parts' => [['text' => $m['texte']]]];
    $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];
    $txt = gemini_generate($CONFIG['modele_analyse'], [
        'systemInstruction' => ['parts' => [['text' => $consigne . "\n\n" . $contexte]]],
        'contents' => $contents,
        'generationConfig' => ['responseMimeType' => 'application/json', 'responseSchema' => s_obj([
            'reponse' => s_txt(), 'action' => s_enum(['aucune', 'proposer_creneaux', 'reserver']), 'creneau' => s_txt(),
            'contact' => s_obj(['nom' => s_txt(), 'telephone' => s_txt(), 'email' => s_txt(), 'budget' => s_txt(), 'financement' => s_txt(), 'delai' => s_txt()]),
        ], ['reponse', 'action'])],
    ], 45);
    flush_usage($agent, $v['id'], 'assistant');
    $r = json_decode($txt, true) ?: ['reponse' => 'Je transmets votre question à votre conseiller, qui vous répond rapidement.', 'action' => 'aucune'];
    if ($r['action'] === 'proposer_creneaux') $r['creneaux'] = array_slice($creneaux, 0, 3);
    return $r;
}

/** Sans clé Gemini : réponses simples par mots-clés, pour tester le parcours. */
function assistant_demo(array $v, string $m, array $creneaux, array $prix, array $infos): array
{
    $m = mb_strtolower($m);
    $r = ['reponse' => "Merci pour votre message ! Je peux vous renseigner sur ce bien (surface, pièces, chauffage, DPE, charges…) ou vous proposer une visite.", 'action' => 'aucune', 'contact' => []];
    if (preg_match('/(\d{2}[ .]?\d{2}[ .]?\d{2}[ .]?\d{2}[ .]?\d{2})|@/u', $m) && preg_match('/je (m\'appelle|suis)\s+([\p{L} -]+?)(,|\.|$| et)/u', $m, $n)) {
        preg_match('/(0\d[ .]?\d{2}[ .]?\d{2}[ .]?\d{2}[ .]?\d{2})/u', $m, $t);
        preg_match('/[\w.+-]+@[\w-]+\.[\w.]+/u', $m, $e);
        $r['contact'] = ['nom' => mb_convert_case(trim($n[2]), MB_CASE_TITLE), 'telephone' => $t[1] ?? '', 'email' => $e[0] ?? ''];
        $r['action'] = 'reserver';
        $r['creneau'] = $creneaux[0] ?? '';
        $r['reponse'] = $creneaux ? 'C\'est noté, votre visite est réservée ' . libelle_creneau($creneaux[0]) . '. Vous allez recevoir une confirmation.' : 'Merci, votre conseiller vous rappelle très vite pour fixer la visite.';
    } elseif (preg_match('/visit|voir|rendez|dispo/u', $m)) {
        $r = ['reponse' => 'Avec plaisir ! Je peux vous proposer ' . implode(', ', array_map('libelle_creneau', array_slice($creneaux, 0, 3))) . ". Lequel vous convient ? Indiquez-moi aussi votre nom et votre téléphone.", 'action' => 'proposer_creneaux', 'creneaux' => array_slice($creneaux, 0, 3), 'contact' => []];
    } elseif (preg_match('/prix|combien|honorair|frais/u', $m)) {
        $r['reponse'] = $prix['texte'] ?: 'Le prix est indiqué sur l\'annonce.';
    } elseif (preg_match('/dpe|énergi|energi|chauff|ges/u', $m)) {
        $r['reponse'] = trim('Classe énergie : ' . ($infos['DPE'] ?? 'non communiquée') . '. ' . (isset($infos['Chauffage']) ? 'Chauffage : ' . $infos['Chauffage'] . '.' : ''));
    } elseif (preg_match('/chambre|pièce|piece|surface|m2|m²/u', $m)) {
        $r['reponse'] = trim(implode(' · ', array_filter([isset($infos['Surface habitable']) ? $infos['Surface habitable'] . ' habitables' : '', isset($infos['Pièces']) ? $infos['Pièces'] . ' pièces' : '', isset($infos['Chambres']) ? $infos['Chambres'] . ' chambres' : '']))) ?: $r['reponse'];
    } elseif (preg_match('/jardin|terrain|extérieur|exterieur|garage|parking/u', $m)) {
        $r['reponse'] = trim(implode('. ', array_filter([$infos['Balcon / terrasse / jardin'] ?? '', $infos['Parking / garage'] ?? '', isset($infos['Surface du terrain']) ? 'Terrain de ' . $infos['Surface du terrain'] : '']))) ?: $r['reponse'];
    }
    return $r;
}

// ---------- Flux pour les portails ----------

/** Flux XML de tous les biens publiés de l'agence (format d'échange simple, documenté dans PASSATION.md). */
function flux_portails(): string
{
    global $CONFIG;
    $x = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><annonces/>');
    $x->addAttribute('agence', (string) $CONFIG['agence']);
    $x->addAttribute('genere_le', date('c'));
    foreach (read_json(DATA_DIR . FICHIER_VITRINES, []) as $slug => $m) {
        $t = vitrine_trouver($slug);
        if (!$t) continue;
        [$agent, $v] = $t;
        $a = $x->addChild('annonce');
        $a->addAttribute('reference', strtoupper(substr(md5($v['id']), 0, 8)));
        foreach ([
            'titre' => $v['titre_annonce'] ?: titre_bien($v), 'type' => champ($v, 'type_bien'), 'prix' => (string) mention_prix($v)['prix'], 'mention_prix' => mention_prix($v)['texte'],
            'surface' => champ($v, 'surface_habitable'), 'terrain' => champ($v, 'surface_terrain'), 'pieces' => champ($v, 'nb_pieces'), 'chambres' => champ($v, 'nb_chambres'),
            'ville' => champ($v, 'ville'), 'code_postal' => $v['public']['geo']['postcode'] ?? '', 'dpe' => champ($v, 'dpe'), 'ges' => champ($v, 'ges'),
            'mandat' => champ($v, 'mandat_type'), 'numero_mandat' => $v['mandat']['numero'] ?? '', 'url' => url_publique('v/?b=' . $slug),
            'contact_nom' => $agent['nom'], 'contact_telephone' => $agent['telephone'] ?? '', 'contact_email' => $agent['email'] ?? '',
        ] as $k => $val) $a->addChild($k, htmlspecialchars((string) $val, ENT_XML1));
        $a->addChild('description', htmlspecialchars((string) $v['annonce'], ENT_XML1));
        $ph = $a->addChild('photos');
        foreach ($v['photos'] ?? [] as $i => $p) $ph->addChild('photo', htmlspecialchars(url_publique('v/?b=' . $slug . '&photo=' . $i), ENT_XML1));
    }
    return (string) $x->asXML();
}

function jeton_flux(): string
{
    global $CONFIG;
    if (!empty($CONFIG['flux_jeton'])) return $CONFIG['flux_jeton'];
    $s = read_json(SETTINGS_FILE, []);
    $s['flux_jeton'] = bin2hex(random_bytes(12));
    write_json(SETTINGS_FILE, $s);
    $CONFIG['flux_jeton'] = $s['flux_jeton'];
    return $s['flux_jeton'];
}

// ---------- API ----------

route('POST publier', function ($id) {
    $me = require_user();
    $v = publier($me, $id, (bool) (json_input()['publier'] ?? true));
    send_json(vue_dossier($v));
});

route('GET diffusion', function ($id) {
    $me = require_user();
    $v = load_visit($me, $id);
    $vues = $v['vitrine']['vues'] ?? [];
    send_json([
        'publiee' => !empty($v['vitrine']['publiee']),
        'url' => !empty($v['vitrine']['slug']) ? url_publique('v/?b=' . $v['vitrine']['slug']) : null,
        'publiee_le' => $v['vitrine']['publiee_le'] ?? null,
        'vues_total' => array_sum($vues), 'vues_7j' => array_sum(array_filter($vues, fn ($k) => strtotime($k) > time() - 7 * 86400, ARRAY_FILTER_USE_KEY)),
        'contacts' => array_reverse($v['contacts'] ?? []),
        'flux' => url_publique('flux.php?t=' . jeton_flux()),
        'mention_prix' => mention_prix($v)['texte'],
        'mandat_signe' => !empty($v['mandat']['signe_le']),
    ]);
});

a_faire('contacts', function (array $agent, array $dossiers): array {
    $items = [];
    foreach ($dossiers as $v) foreach ($v['contacts'] ?? [] as $c) {
        if (strtotime($c['date']) < maintenant() - 2 * 86400) continue;
        $items[] = ['type' => 'contact', 'titre' => ($c['rdv'] ? 'Visite réservée par ' : 'Nouveau contact : ') . $c['nom'], 'detail' => titre_bien($v) . ' · ' . $c['canal'], 'lien' => "#/acquereur/{$c['acquereur']}", 'priorite' => $c['rdv'] ? 2 : 1, 'date' => $c['date']];
    }
    return $items;
});
