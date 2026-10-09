<?php
// Acquéreurs : fiche dictée en 30 secondes (critères, budget, financement, délai), qualification,
// rapprochement automatique avec les biens en vente, alerte par e-mail quand un bien correspond.

const STATUTS_ACQUEREUR = ['nouveau' => 'Nouveau', 'qualifie' => 'Qualifié', 'visite' => 'A visité', 'offre' => 'Offre faite', 'achete' => 'A acheté', 'perdu' => 'Perdu'];

function acquereurs(array $agent): array
{
    $l = collection($agent, 'acquereurs');
    usort($l, fn ($a, $b) => strcmp($b['modifie_le'] ?? '', $a['modifie_le'] ?? ''));
    return $l;
}

function acquereur(array $agent, string $id): array
{
    return collection_trouver($agent, 'acquereurs', $id) ?? fail(404, 'Acquéreur introuvable.');
}

/** Nettoie et complète une fiche acquéreur reçue (dictée, formulaire, assistant en ligne). */
function normaliser_acquereur(array $a, array $ancien = []): array
{
    $txt = fn ($v, $max = 200) => mb_substr(trim((string) $v), 0, $max);
    $num = fn ($v) => ($n = (int) preg_replace('/\D/', '', (string) $v)) ? $n : null;
    $c = (array) ($a['criteres'] ?? []);
    $f = (array) ($a['financement'] ?? []);
    $out = array_merge($ancien, array_filter([
        'prenom' => $txt($a['prenom'] ?? '', 60), 'nom' => $txt($a['nom'] ?? '', 60),
        'telephone' => $txt($a['telephone'] ?? '', 30), 'email' => strtolower($txt($a['email'] ?? '', 120)),
        'source' => $txt($a['source'] ?? '', 60), 'notes' => $txt($a['notes'] ?? '', 2000), 'delai' => $txt($a['delai'] ?? '', 80),
    ], fn ($x) => $x !== ''));
    $out['criteres'] = array_merge($ancien['criteres'] ?? [], array_filter([
        'type' => $txt($c['type'] ?? '', 40), 'budget_max' => $num($c['budget_max'] ?? ''), 'surface_min' => $num($c['surface_min'] ?? ''),
        'pieces_min' => $num($c['pieces_min'] ?? ''), 'chambres_min' => $num($c['chambres_min'] ?? ''), 'villes' => $txt($c['villes'] ?? '', 200),
        'exterieur' => $txt($c['exterieur'] ?? '', 60), 'autres' => $txt($c['autres'] ?? '', 400),
    ], fn ($x) => $x !== '' && $x !== null));
    $out['financement'] = array_merge($ancien['financement'] ?? [], array_filter([
        'apport' => $num($f['apport'] ?? ''), 'mode' => $txt($f['mode'] ?? '', 60), 'accord_banque' => $txt($f['accord_banque'] ?? '', 60),
        'courtier' => $txt($f['courtier'] ?? '', 120), 'bien_a_vendre' => $txt($f['bien_a_vendre'] ?? '', 120),
    ], fn ($x) => $x !== '' && $x !== null));
    $out['statut'] = isset(STATUTS_ACQUEREUR[$a['statut'] ?? '']) ? $a['statut'] : ($ancien['statut'] ?? 'nouveau');
    $out['alertes'] = isset($a['alertes']) ? (bool) $a['alertes'] : ($ancien['alertes'] ?? true);
    if (!empty($a['id'])) $out['id'] = $a['id'];
    $out['qualification'] = qualification($out);
    return $out;
}

/** Note de 0 à 100 : un acquéreur « qualifié » a des critères, un budget et un financement crédibles. */
function qualification(array $a): int
{
    $s = 0;
    if (!empty($a['telephone']) || !empty($a['email'])) $s += 15;
    if (!empty($a['criteres']['budget_max'])) $s += 20;
    if (!empty($a['criteres']['type']) || !empty($a['criteres']['villes'])) $s += 15;
    if (!empty($a['financement']['apport'])) $s += 15;
    if (!empty($a['financement']['accord_banque']) && !preg_match('/non|pas/i', $a['financement']['accord_banque'])) $s += 20;
    if (!empty($a['delai'])) $s += 10;
    if (!empty($a['financement']['bien_a_vendre']) && preg_match('/non|aucun|pas/i', $a['financement']['bien_a_vendre'])) $s += 5;
    return min(100, $s);
}

function nom_acquereur(array $a): string
{
    return trim(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? '')) ?: 'Acquéreur sans nom';
}

/** Au-delà de la commune demandée : biens proposés jusqu'à 15 km, avec un score qui baisse avec la distance. */
const RAYON_SECTEUR_KM = 15;

/** « Châtillon-le-Duc », « chatillon le duc », « St-Vit » / « Saint Vit » : même clé. */
function cle_commune(string $s): string
{
    $s = preg_replace('/\s+/', ' ', normaliser_nom($s));
    $s = preg_replace('/\b(st|ste)\b/', 'saint', $s);
    return trim(preg_replace('/\b(\d{5})\b/', '', $s));
}

/** Coordonnées d'une commune nommée par l'acquéreur (Base Adresse Nationale), gardées en cache. */
function commune_geo(string $nom): ?array
{
    static $memo = [];
    $cle = cle_commune($nom);
    if ($cle === '') return null;
    if (array_key_exists($cle, $memo)) return $memo[$cle];
    $f = DATA_DIR . '/cache/communes/' . slug($cle) . '.json';
    if (is_file($f)) return $memo[$cle] = json_decode((string) file_get_contents($f), true);
    $r = chercher_commune($nom)[0] ?? null;
    if ($r) {
        if (!is_dir(dirname($f))) mkdir(dirname($f), 0770, true);
        file_put_contents($f, json_encode($r, JSON_UNESCAPED_UNICODE));
    }
    return $memo[$cle] = $r;
}

/** Le bien est-il dans le secteur recherché ? ['oui'|'proche'|'non', raison]. */
function secteur_compatible(string $villes, array $v): array
{
    $geo = $v['public']['geo'] ?? [];
    $communeBien = trim(($geo['city'] ?? '') ?: preg_replace('/\b\d{5}\b/', '', champ($v, 'ville')));
    $cleBien = cle_commune($communeBien . ' ' . champ($v, 'ville'));
    $demandees = array_filter(array_map('trim', preg_split('/[,;\/]| ou | et /u', $villes)));
    $plusProche = null;
    foreach ($demandees as $d) {
        $cd = cle_commune($d);
        if ($cd === '') continue;
        if (preg_match('/(^| )' . preg_quote($cd, '/') . '( |$)/', $cleBien)) return ['oui', 'secteur recherché'];
        $g = commune_geo($d);
        if ($g && !empty($geo['citycode']) && $g['citycode'] === $geo['citycode']) return ['oui', 'secteur recherché'];
        if ($g && !empty($geo['lat'])) {
            $km = distance_m((float) $geo['lat'], (float) $geo['lon'], (float) $g['lat'], (float) $g['lon']) / 1000;
            if ($plusProche === null || $km < $plusProche[1]) $plusProche = [$g['nom'] ?? $d, $km];
        }
    }
    if ($plusProche && $plusProche[1] <= RAYON_SECTEUR_KM) return ['proche', 'commune voisine, à ' . number_format($plusProche[1], 1, ',', '') . ' km de ' . $plusProche[0], $plusProche[1]];
    return ['non', 'hors secteur (' . ($communeBien ?: 'commune inconnue') . ')'];
}

/** Compatibilité acquéreur ↔ bien, de 0 à 100, avec les raisons. */
function rapprochement(array $a, array $v): array
{
    $c = $a['criteres'] ?? [];
    $prix = (float) (champ($v, 'mandat_prix') ?: champ($v, 'prix_souhaite'));
    $surface = (float) champ($v, 'surface_habitable');
    $score = 100;
    $raisons = [];
    $bloquant = false;
    if (!empty($c['type']) && champ($v, 'type_bien') !== '' && mb_stripos($c['type'], champ($v, 'type_bien')) === false && mb_stripos(champ($v, 'type_bien'), $c['type']) === false) { $score -= 60; $bloquant = true; $raisons[] = 'type différent'; }
    if (!empty($c['budget_max']) && $prix) {
        $r = $prix / $c['budget_max'];
        if ($r > 1.10) { $score -= 50; $bloquant = true; $raisons[] = 'hors budget'; }
        elseif ($r > 1.0) { $score -= 15; $raisons[] = 'budget un peu juste'; }
        else $raisons[] = 'dans le budget';
    }
    if (!empty($c['surface_min']) && $surface) {
        if ($surface < $c['surface_min'] * 0.9) { $score -= 30; $raisons[] = 'surface trop petite'; }
        else $raisons[] = 'surface ok';
    }
    $pieces = (int) champ($v, 'nb_pieces');
    if (!empty($c['pieces_min']) && $pieces && $pieces < $c['pieces_min']) { $score -= 20; $raisons[] = 'pas assez de pièces'; }
    $ch = (int) champ($v, 'nb_chambres');
    if (!empty($c['chambres_min']) && $ch && $ch < $c['chambres_min']) { $score -= 20; $raisons[] = 'pas assez de chambres'; }
    if (!empty($c['villes'])) {
        // Secteur : la commune demandée d'abord (accents, tirets, « St » / « Saint » sans importance), puis les communes
        // voisines avec un score qui baisse avec la distance (−5 points, puis −2,5 par km), écartées au-delà de 15 km.
        [$ok, $raison, $km] = secteur_compatible($c['villes'], $v) + [2 => 0];
        if ($ok === 'oui') $raisons[] = $raison;
        elseif ($ok === 'proche') { $score -= (int) round(5 + 2.5 * $km); $raisons[] = $raison; }
        else { $score -= 60; $bloquant = true; $raisons[] = $raison; }
    }
    if (!empty($c['exterieur']) && preg_match('/jardin|terrasse|balcon|extérieur/iu', $c['exterieur'])) {
        if (preg_match('/jardin|terrasse|balcon/iu', champ($v, 'exterieur'))) $raisons[] = 'extérieur';
        else { $score -= 15; $raisons[] = 'sans extérieur'; }
    }
    return ['score' => max(0, $score), 'raisons' => $raisons, 'bloquant' => $bloquant];
}

/** Biens en vente (ou prêts) de l'agent, les plus compatibles d'abord. */
function biens_pour(array $agent, array $a): array
{
    $out = [];
    foreach (dossiers($agent) as $v) {
        if (!in_array(etape_dossier($v), ['preparation', 'signature', 'en_vente'], true)) continue;
        $r = rapprochement($a, $v);
        if ($r['score'] >= 50 && !$r['bloquant']) $out[] = ['id' => $v['id'], 'titre' => titre_bien($v), 'prix' => champ($v, 'mandat_prix') ?: champ($v, 'prix_souhaite'), 'etape' => etape_dossier($v)] + $r;
    }
    usort($out, fn ($x, $y) => $y['score'] <=> $x['score']);
    return $out;
}

function acquereurs_pour(array $agent, array $v): array
{
    $out = [];
    foreach (acquereurs($agent) as $a) {
        if (in_array($a['statut'], ['achete', 'perdu'], true)) continue;
        $r = rapprochement($a, $v);
        if ($r['score'] >= 50 && !$r['bloquant']) $out[] = ['id' => $a['id'], 'nom' => nom_acquereur($a), 'qualification' => $a['qualification'] ?? 0, 'email' => $a['email'] ?? '', 'telephone' => $a['telephone'] ?? '',
            'propose' => in_array($v['id'], array_column($a['propositions'] ?? [], 'dossier'), true)] + $r;
    }
    usort($out, fn ($x, $y) => [$y['score'], $y['qualification']] <=> [$x['score'], $x['qualification']]);
    return $out;
}

/** Propose un bien à un acquéreur par e-mail (lien vers la page du bien si elle est publiée, sinon fiche en PDF). */
function proposer_bien(array $agent, array $a, array $v): bool
{
    if (!valid_email($a['email'] ?? '')) return false;
    $prix = champ($v, 'mandat_prix') ?: champ($v, 'prix_souhaite');
    $lien = !empty($v['vitrine']['publiee']) ? url_publique('v/?b=' . $v['vitrine']['slug']) : '';
    $texte = "Bonjour " . ($a['prenom'] ?? '') . ",\n\nUn bien vient d'arriver qui correspond à votre recherche :\n\n"
        . ($v['titre_annonce'] ?: titre_bien($v)) . ($prix ? ' · ' . number_format((float) $prix, 0, ',', ' ') . ' €' : '') . "\n"
        . implode("\n", array_map(fn ($p) => "- $p", $v['points_forts'] ?? [])) . "\n\n"
        . ($lien ? "Toutes les photos et la description : $lien\n\n" : "Vous trouverez la fiche du bien en pièce jointe.\n\n")
        . "Souhaitez-vous le visiter ? Répondez simplement à cet e-mail ou appelez-moi.\n\n" . signature_agent($agent);
    $pj = [];
    if (!$lien) {
        [$bin, $nom] = build_pdf('fiche', $v, $agent);
        $pj[] = [$nom, $bin, 'application/pdf'];
    }
    envoyer_mail_agent($agent, $a['email'], 'Un bien pour vous · ' . ($v['titre_annonce'] ?: titre_bien($v)), $texte, $pj, true);
    $a['propositions'][] = ['dossier' => $v['id'], 'date' => date('c')];
    $a['historique'][] = ['date' => date('c'), 'texte' => 'Bien proposé : ' . titre_bien($v)];
    collection_enregistrer($agent, 'acquereurs', $a);
    journaliser($agent, $v['id'], 'acquereur', 'Bien proposé à ' . nom_acquereur($a) . '.');
    return true;
}

// ---------- Dictée « nouvel acquéreur » ----------

type_dictee('acquereur',
    s_obj([
        'prenom' => s_txt(), 'nom' => s_txt(), 'telephone' => s_txt(), 'email' => s_txt(), 'source' => s_txt(), 'delai' => s_txt(), 'notes' => s_txt(),
        'criteres' => s_obj(['type' => s_txt(), 'budget_max' => s_num(), 'surface_min' => s_num(), 'pieces_min' => s_num(), 'chambres_min' => s_num(), 'villes' => s_txt(), 'exterieur' => s_txt(), 'autres' => s_txt()]),
        'financement' => s_obj(['apport' => s_num(), 'mode' => s_txt(), 'accord_banque' => s_txt(), 'courtier' => s_txt(), 'bien_a_vendre' => s_txt()]),
    ], ['criteres', 'financement']),
    "L'agent décrit un acquéreur qu'il vient de rencontrer ou d'avoir au téléphone. Remplis sa fiche : identité, coordonnées, critères de recherche (type Maison/Appartement/Terrain, budget maximum, surface, pièces, chambres, villes ou quartiers, extérieur, autres souhaits), financement (apport, prêt, accord de principe de la banque, courtier, bien à vendre avant d'acheter), délai, et notes utiles (situation, motivation). Le courriel épelé doit être reconstitué (arobase → @).",
    fn (string $t) => [
        'prenom' => 'Julien', 'nom' => 'Moreau', 'telephone' => '06 22 33 44 55', 'email' => 'julien.moreau@exemple.fr', 'source' => 'Appel entrant', 'delai' => 'Achat avant l\'été', 'notes' => 'Couple avec deux enfants, mutation professionnelle à Besançon.',
        'criteres' => ['type' => 'Maison', 'budget_max' => 430000, 'surface_min' => 110, 'pieces_min' => 5, 'chambres_min' => 4, 'villes' => 'Lougres, Montbéliard', 'exterieur' => 'Jardin', 'autres' => 'Garage souhaité'],
        'financement' => ['apport' => 90000, 'mode' => 'Prêt immobilier', 'accord_banque' => 'Accord de principe obtenu', 'courtier' => 'Cabinet Prêt+', 'bien_a_vendre' => 'Non'],
    ],
);

apres_dictee('acquereur', function (array $agent, array $d, array $in) {
    $a = normaliser_acquereur($d + ['source' => $d['source'] ?? 'Dictée'], !empty($in['id']) ? acquereur($agent, (string) $in['id']) : []);
    $a['historique'][] = ['date' => date('c'), 'texte' => empty($in['id']) ? 'Fiche créée par dictée.' : 'Fiche complétée par dictée.'];
    $a = collection_enregistrer($agent, 'acquereurs', $a);
    return ['acquereur' => $a, 'biens' => biens_pour($agent, $a)];
});

// ---------- API ----------

route('GET acquereurs', function () {
    $me = require_user();
    send_json(array_map(fn ($a) => $a + ['nb_biens' => count(biens_pour($me, $a))], acquereurs($me)));
});

route('GET acquereur', function ($id) {
    $me = require_user();
    $a = acquereur($me, $id);
    $visites = [];
    foreach (dossiers($me) as $v) foreach ($v['visites_acq'] ?? [] as $va) if (($va['acquereur'] ?? '') === $id) $visites[] = $va + ['dossier' => $v['id'], 'bien' => titre_bien($v)];
    send_json(['acquereur' => $a, 'biens' => biens_pour($me, $a), 'visites' => $visites]);
});

route('POST acquereur', function ($id) {
    $me = require_user();
    $in = json_input();
    $ancien = $id !== '' ? acquereur($me, $id) : [];
    unset($in['id']); // l'identifiant vient de l'adresse, jamais du corps de la requête
    $a = normaliser_acquereur($in + ($id !== '' ? ['id' => $id] : []), $ancien);
    if (!$ancien) $a['historique'][] = ['date' => date('c'), 'texte' => 'Fiche créée.'];
    send_json(collection_enregistrer($me, 'acquereurs', $a));
});

route('DELETE acquereur', function ($id) {
    $me = require_user();
    collection_supprimer($me, 'acquereurs', $id);
    send_json(['ok' => true]);
});

route('GET rapprochements', function ($id) {
    $me = require_user();
    send_json(acquereurs_pour($me, load_visit($me, $id)));
});

route('POST proposer', function ($id) {
    $me = require_user();
    $v = load_visit($me, $id);
    $n = 0;
    foreach ((array) (json_input()['acquereurs'] ?? []) as $aid) {
        $a = collection_trouver($me, 'acquereurs', (string) $aid);
        if ($a && proposer_bien($me, $a, $v)) $n++;
    }
    send_json(['envoyes' => $n]);
});

// Quand un bien est mis en vente, les acquéreurs compatibles (score ≥ 75, alertes actives) reçoivent le bien automatiquement
tache_cron('alertes_acquereurs', function (array $agent, array $dossiers): int {
    if (!email_configure()) return 0;
    $n = 0;
    foreach ($dossiers as $v) {
        if (etape_dossier($v) !== 'en_vente' || empty($v['vitrine']['publiee'])) continue;
        foreach (acquereurs_pour($agent, $v) as $r) {
            if ($r['propose'] || $r['score'] < 75 || $r['bloquant']) continue;
            $a = collection_trouver($agent, 'acquereurs', $r['id']);
            if (!$a || empty($a['alertes'])) continue;
            try {
                if (proposer_bien($agent, $a, $v)) $n++;
            } catch (Throwable $e) {
                error_log('alerte acquéreur : ' . $e->getMessage());
            }
        }
    }
    return $n;
});

a_faire('acquereurs', function (array $agent, array $dossiers): array {
    $items = [];
    foreach (acquereurs($agent) as $a) {
        if ($a['statut'] !== 'nouveau') continue;
        $items[] = ['type' => 'contact', 'titre' => 'Nouveau contact : ' . nom_acquereur($a), 'detail' => trim(($a['source'] ?? '') . ' · ' . ($a['qualification'] ?? 0) . ' % qualifié · ' . count(biens_pour($agent, $a)) . ' bien(s) compatible(s)', ' ·'),
            'lien' => "#/acquereur/{$a['id']}", 'priorite' => 1, 'date' => $a['cree_le'] ?? ''];
    }
    return $items;
});
