<?php
/**
 * Contrôle d'exhaustivité : compare chaque page de l'ancien site (blocs HTML
 * d'origine archivés dans data/legacy/) avec la fiche du nouveau site.
 *
 * Usage : php scripts/wp/completeness.php [--detail]
 *
 * Pour chaque fiche importée :
 *   - texte : part des suites de 3 mots de l'ancienne page retrouvées dans la fiche ;
 *     les passages absents sont listés ;
 *   - images : chaque image de l'ancienne page doit être reliée à la fiche ;
 *   - vidéos : chaque vidéo YouTube doit être reprise ;
 *   - tableaux : autant de tableaux (composition, statistiques…) qu'à l'origine.
 * Rapport : storage/import/completeness.json (et résumé à l'écran).
 */

declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/parser.php';

const DATA = ROOT . '/data';
$detail = in_array('--detail', $argv, true);

$media = read_json(DATA . '/media.json') ?: [];

/** Texte normalisé → liste de mots (entités décodées deux fois : certains textes WordPress sont doublement encodés). */
function cc_words(string $text): array
{
    $t = preg_replace('/&(amp;)?nbsp;?/i', ' ', $text);
    $t = html_entity_decode(html_entity_decode((string) $t, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = wp_norm($t);
    return array_values(array_filter(preg_split('/[^a-z0-9]+/', $t) ?: [], fn ($w) => $w !== ''));
}

/** Texte d'un fragment HTML (blocs séparés par des espaces). */
function cc_text(string $html): string
{
    $html = preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $html);
    $html = preg_replace('#<(br|/p|/div|/li|/td|/th|/h\d|/tr)\b[^>]*>#i', ' $0', (string) $html);
    return trim(preg_replace('/\s+/u', ' ', strip_tags((string) $html)));
}

/** Toutes les chaînes d'une structure (fiche), hors archive et métadonnées techniques. */
function cc_strings(mixed $v, array &$out, string $key = ''): void
{
    if (in_array($key, ['legacy', 'i18n', 'path', 'slug', 'old_path', 'source_table', 'modified', 'modified_by', 'author', 'id', 'person_id', 'pid'], true)) {
        return;
    }
    if (is_array($v)) {
        foreach ($v as $k => $x) {
            cc_strings($x, $out, (string) $k);
        }
    } elseif (is_string($v) || is_int($v) || is_float($v)) {
        $out[] = cc_text((string) $v);
    }
}

function cc_shingles(array $words, int $n = 3): array
{
    $out = [];
    for ($i = 0, $c = count($words) - $n + 1; $i < $c; $i++) {
        $out[] = implode(' ', array_slice($words, $i, $n));
    }
    return $out;
}

/** Chemin médiathèque d'une URL d'image WordPress (sans le suffixe de taille). */
function cc_media(string $url, array $media): ?string
{
    if (!preg_match('#/wp-content/uploads/(.+?)$#', $url, $m)) {
        return null;
    }
    $rel = rawurldecode(preg_replace('/[?#].*$/', '', $m[1]));
    if (isset($media[$rel])) {
        return $rel;
    }
    $base = preg_replace('/-\d+x\d+(\.\w+)$/', '$1', $rel);
    if (isset($media[$base])) {
        return $base;
    }
    $scaled = preg_replace('/(\.\w+)$/', '-scaled$1', $base);
    return isset($media[$scaled]) ? $scaled : $rel;
}

/** Identifiants des vidéos citées (YouTube, Dailymotion, Vimeo, Rutube). */
function cc_videos(string $s): array
{
    $ids = [];
    $patterns = [
        '#(?:youtube(?:-nocookie)?\.com/(?:embed/|watch\?v=|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#',
        '#(?:dailymotion\.com/(?:video|embed/video)/|geo\.dailymotion\.com/player(?:/[\w-]+)?\.html\?(?:[^"\s]*&)?video=|dai\.ly/)([a-z0-9]{5,})#i',
        '#player\.vimeo\.com/video/(\d+)#',
        '#rutube\.ru/(?:play/embed|video)/([a-f0-9]{20,})#i',
    ];
    // Lecteur Dailymotion créé par script : videoId = 'x…'
    if (str_contains($s, 'dailymotion') && preg_match_all("/videoId\\s*[=:]\\s*['\"]([a-z0-9]{5,})['\"]/i", $s, $m)) {
        array_push($ids, ...$m[1]);
    }
    foreach ($patterns as $re) {
        if (preg_match_all($re, $s, $m)) {
            array_push($ids, ...$m[1]);
        }
    }
    return array_values(array_unique($ids));
}

/** Libellés de mise en page de l'ancien site, sans contenu propre. */
const LABELS = ['fiche' => 1, 'du' => 1, 'match' => 1, 'nbsp' => 1, 'amp' => 1, 'quot' => 1, 'spectateurs' => 1, 'spectateur' => 1, 'arbitre' => 1, 'arbitres' => 1,
    'buts' => 1, 'but' => 1, 'postes' => 1, 'poste' => 1, 'nom' => 1, 'et' => 1, 'prenom' => 1, 'remp' => 1, 'cartons' => 1, 'changements' => 1, 'lire' => 1, 'la' => 1, 'suite' => 1,
    'voir' => 1, 'aussi' => 1, 'partager' => 1, 'retour' => 1, 'galerie' => 1, 'photos' => 1, 'video' => 1, 'videos' => 1, 'd' => 1, 'identite' => 1];

$report = ['generated' => date('c'), 'summary' => [], 'fiches' => []];
$sum = ['fiches' => 0, 'mots_anciens' => 0, 'mots_retrouves' => 0, 'sous_99' => 0, 'sous_95' => 0, 'images_manquantes' => 0, 'images_anciennes' => 0, 'videos_manquantes' => 0, 'videos_anciennes' => 0, 'tableaux_manquants' => 0, 'tableaux_anciens' => 0];
$worst = [];

foreach (glob(DATA . '/fiches/*.json') ?: [] as $file) {
    $doc = read_json($file);
    $id = (int) ($doc['id'] ?? 0);
    if ($id <= 0 || $id >= 1000000) {
        continue;
    }
    $arch = is_file(DATA . "/legacy/$id.json.gz") ? json_decode((string) gzdecode((string) file_get_contents(DATA . "/legacy/$id.json.gz")), true) : null;
    if (!$arch) {
        continue;
    }
    $sum['fiches']++;
    $oldHtml = '';
    foreach ($arch['blocks'] ?? [] as $b) {
        if (in_array($b['type'], ['divider_2', 'spacer', 'blog'], true)) {
            continue;
        }
        $oldHtml .= ' ' . $b['html'];
    }
    $oldWords = cc_words(cc_text($oldHtml));
    $strings = [];
    cc_strings($doc, $strings);
    // Légendes et crédits des images de la fiche (repris dans la médiathèque).
    foreach (array_merge([$doc['featured_image'] ?? null], array_column($doc['gallery'] ?? [], 'image'), array_column($doc['images'] ?? [], 'image')) as $rel) {
        if ($rel && isset($media[$rel])) {
            $strings[] = ($media[$rel]['caption_raw'] ?? '') . ' ' . ($media[$rel]['caption'] ?? '') . ' ' . ($media[$rel]['credit'] ?? '') . ' ' . ($media[$rel]['title'] ?? '') . ' ' . ($media[$rel]['alt'] ?? '');
        }
    }
    $newWords = cc_words(implode(' ', $strings));
    $newWordSet = array_flip($newWords);
    // Couverture mot à mot (l'ordre change entre un tableau d'origine et une fiche structurée) ;
    // les libellés de mise en page de l'ancien site (« Fiche du match », « Arbitre »…) sont ignorés.
    $missing = [];
    $counted = 0;
    foreach ($oldWords as $i => $w) {
        if (isset(LABELS[$w])) {
            continue;
        }
        $counted++;
        if (!isset($newWordSet[$w])) {
            $missing[] = $i;
        }
    }
    $found = $counted - count($missing);
    $cover = $counted ? $found / $counted : 1.0;
    // Passages absents : au moins 2 mots consécutifs introuvables, avec leur contexte.
    $passages = [];
    $run = [];
    $flushRun = function () use (&$run, &$passages, $oldWords) {
        if (count($run) >= 2) {
            $passages[] = implode(' ', array_slice($oldWords, max(0, $run[0] - 2), count($run) + 4));
        }
        $run = [];
    };
    foreach ($missing as $i) {
        if ($run && $i !== end($run) + 1) {
            $flushRun();
        }
        $run[] = $i;
    }
    $flushRun();
    $lost = array_count_values(array_map(fn ($i) => $oldWords[$i], $missing));
    arsort($lost);

    // Images (chaque adresse d'une liste srcset est lue séparément)
    preg_match_all('#(?:https?://[^"\s,<>]+)?/wp-content/uploads/[^"\s,<>]+#i', $oldHtml, $im);
    $oldImgs = [];
    foreach ($im[0] as $u) {
        $rel = wp_media_rel($u, $media);
        if ($rel && preg_match('/\.(jpe?g|png|gif|webp|pdf)$/i', $rel)) {
            $oldImgs[$rel] = true;
        }
    }
    $newJson = json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $missImgs = array_values(array_filter(array_keys($oldImgs), fn ($r) => !str_contains((string) $newJson, $r)));
    // Vidéos : présentes dans les vidéos de la fiche ou en lien dans le texte (les lecteurs
    // intégrés au texte ne sont pas affichés tels quels, ils ne comptent donc pas).
    $oldVids = cc_videos($oldHtml);
    $visible = (string) preg_replace('#<iframe\b.*?</iframe>#is', '', (string) $newJson);
    $missVids = array_values(array_filter($oldVids, fn ($v) => !str_contains($visible, $v)));
    // Tableaux de la page d'origine (un même tableau affiché deux fois ne compte qu'une fois ;
    // les tableaux vides de mise en page sont ignorés)
    $distinct = [];
    if (preg_match_all('#<table\b[^>]*>.*?</table>#is', $oldHtml, $tm)) {
        foreach ($tm[0] as $t) {
            $txt = cc_text($t);
            if ($txt !== '') {
                $distinct[md5($txt)] = true;
            }
        }
    }
    $oldTables = count($distinct);
    $newTables = count($doc['tables'] ?? []) + (!empty($doc['match']['lineup']['rows']) ? 1 : 0) + count($doc['match']['other_lineups'] ?? []) + (!empty($doc['personne']['stats']) ? 1 : 0);

    $sum['mots_anciens'] += $counted;
    $sum['mots_retrouves'] += $found;
    $sum['images_anciennes'] += count($oldImgs);
    $sum['images_manquantes'] += count($missImgs);
    $sum['videos_anciennes'] += count($oldVids);
    $sum['videos_manquantes'] += count($missVids);
    $sum['tableaux_anciens'] += $oldTables;
    $sum['tableaux_manquants'] += max(0, $oldTables - $newTables);
    if ($cover < 0.99) {
        $sum['sous_99']++;
    }
    if ($cover < 0.95) {
        $sum['sous_95']++;
    }
    if ($cover < 0.99 || $missImgs || $missVids || $newTables < $oldTables) {
        $report['fiches'][$id] = [
            'titre' => $doc['title'], 'type' => $doc['type'], 'url' => $arch['url'] ?? '', 'couverture' => round($cover * 100, 1),
            'passages_absents' => array_slice($passages, 0, 8), 'mots_absents' => array_slice($lost, 0, 25, true), 'images_absentes' => $missImgs, 'videos_absentes' => $missVids,
            'tableaux' => [$oldTables, $newTables],
        ];
        $worst[$id] = $cover;
    }
}
asort($worst);
$sum['couverture_texte'] = $sum['mots_anciens'] ? round(100 * $sum['mots_retrouves'] / $sum['mots_anciens'], 2) : 100;
$report['summary'] = $sum;
write_json(IMPORT_DIR . '/completeness.json', $report);
out(json_encode($sum, JSON_UNESCAPED_UNICODE));
foreach (array_slice(array_keys($worst), 0, $detail ? 60 : 15, true) as $id) {
    $f = $report['fiches'][$id];
    out(sprintf('%5.1f %% · %s · %s%s%s%s', $f['couverture'], $id, mb_substr($f['titre'], 0, 70),
        $f['images_absentes'] ? ' · ' . count($f['images_absentes']) . ' image(s) absente(s)' : '',
        $f['videos_absentes'] ? ' · ' . count($f['videos_absentes']) . ' vidéo(s) absente(s)' : '',
        $f['tableaux'][1] < $f['tableaux'][0] ? ' · tableaux ' . $f['tableaux'][1] . '/' . $f['tableaux'][0] : ''));
    if ($detail) {
        out('        mots absents : ' . implode(', ', array_map(fn ($w, $n) => $n > 1 ? "$w ×$n" : $w, array_keys(array_slice($f['mots_absents'], 0, 15, true)), array_slice($f['mots_absents'], 0, 15, true))));
        foreach (array_slice($f['passages_absents'], 0, 3) as $p) {
            out('        « ' . mb_substr($p, 0, 160) . ' »');
        }
    }
}
