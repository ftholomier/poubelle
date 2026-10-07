<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Media;
use App\Data\Names;
use App\Data\Paths;

/**
 * Photos de presse des années 1928-1955 trouvées sur Gallica (BnF) : photographies d'agence (Rol,
 * Meurisse, Mondial Photo-Presse…) qui citent Sochaux, marquées « domaine public » par la BnF.
 * Recherche par l'API SRU de Gallica, image par l'API IIIF, rangement dans la fiche du match
 * (date du titre, ou adversaire et année), sinon du joueur cité ; crédit « Gallica / BnF ».
 * Rien n'est créé : seules des fiches existantes reçoivent des photos ; aucune photo en double.
 */
final class FcsmGallica
{
    public const SRU = 'https://gallica.bnf.fr/SRU';
    public const LAST_YEAR = 1955;
    public const CREDIT = 'Gallica, Bibliothèque nationale de France';
    /** Recherches lancées (CQL). */
    public const QUERIES = [
        '(gallica all "Sochaux") and (dc.type all "image")',
        '(gallica all "FC Sochaux") and (dc.type all "image")',
        '(gallica all "Sochaux Montbéliard") and (dc.type all "image")',
        '(gallica all "football Sochaux") and (dc.type all "photographie")',
    ];
    /** Téléchargement remplaçable (essais automatiques) : fn(string $url): string. */
    public static $get = null;

    private static function file(): string
    {
        return FcsmStory::$dir . '/gallica.json';
    }

    public static function state(): array
    {
        return JsonStore::read(self::file(), []) + ['found' => [], 'done' => [], 'at' => null, 'error' => null];
    }

    private static function fetch(string $url): string
    {
        $get = self::$get ?? static function (string $url): string {
            $ctx = stream_context_create(['http' => ['timeout' => 60, 'header' => "User-Agent: SochauxRetro-Musee/1.0 (musee du FC Sochaux-Montbeliard)\r\n"]]);
            $b = @file_get_contents($url, false, $ctx);
            if ($b === false || $b === '') {
                throw new \RuntimeException('Gallica ne répond pas (' . preg_replace('/\?.*/', '', $url) . ')');
            }
            return $b;
        };
        return $get($url);
    }

    // ------------------------------------------------------------------ recherche

    /**
     * Interroge Gallica et garde les photographies du domaine public, 1928-1955.
     * @return array l'état (notices trouvées, par ark)
     */
    public static function search(int $pages = 6): array
    {
        $found = [];
        $raw = 0;
        foreach (self::QUERIES as $q) {
            for ($p = 0; $p < $pages; $p++) {
                $xml = self::fetch(self::SRU . '?operation=searchRetrieve&version=1.2&maximumRecords=50&startRecord=' . (1 + 50 * $p) . '&query=' . rawurlencode($q));
                $recs = self::records($xml);
                $raw += count($recs);
                foreach ($recs as $r) {
                    if ($ok = self::keep($r)) {
                        $found[$ok['ark']] ??= $ok;
                    }
                }
                if (count($recs) < 50) {
                    break;
                }
            }
        }
        $state = self::state();
        $state['found'] = $found;
        $state['raw'] = $raw;
        $state['at'] = date('c');
        $state['error'] = null;
        JsonStore::write(self::file(), $state);
        return $state;
    }

    /** Notices Dublin Core d'une réponse SRU. @return list<array<string,list<string>>> */
    public static function records(string $xml): array
    {
        $out = [];
        if (!preg_match_all('#<(?:\w+:)?dc\b[^>]*>(.*?)</(?:\w+:)?dc>#s', $xml, $m)) {
            return [];
        }
        foreach ($m[1] as $block) {
            $r = [];
            preg_match_all('#<dc:(\w+)[^>]*>(.*?)</dc:\1>#s', $block, $f, PREG_SET_ORDER);
            foreach ($f as $x) {
                $r[$x[1]][] = trim(html_entity_decode(strip_tags($x[2]), ENT_QUOTES | ENT_XML1 | ENT_HTML5, 'UTF-8'));
            }
            $out[] = $r;
        }
        return $out;
    }

    /** Photographie du domaine public, 1928-1955, liée à Sochaux ? → notice utile, ou null. */
    public static function keep(array $r): ?array
    {
        $title = (string) ($r['title'][0] ?? '');
        $rights = mb_strtolower(implode(' ', $r['rights'] ?? []));
        $type = mb_strtolower(implode(' ', $r['type'] ?? []));
        $ark = null;
        foreach ($r['identifier'] ?? [] as $id) {
            if (preg_match('#(ark:/12148/[a-z0-9]+)#i', $id, $a)) {
                $ark = $a[1];
                break;
            }
        }
        if (!$ark || $title === '' || !preg_match('/domaine public|public domain/u', $rights) || !preg_match('/image|photo/u', $type)) {
            return null;
        }
        if (!preg_match('/sochaux/iu', Names::ascii($title . ' ' . implode(' ', $r['description'] ?? []) . ' ' . implode(' ', $r['subject'] ?? [])))) {
            return null;
        }
        $date = self::dateOf($title, (string) ($r['date'][0] ?? ''));
        $year = $date ? (int) substr($date, 0, 4) : (preg_match('/\b(19\d\d)\b/', (string) ($r['date'][0] ?? '') . ' ' . $title, $y) ? (int) $y[1] : null);
        if (!$year || $year < FcsmStory::FIRST || $year > self::LAST_YEAR) {
            return null;
        }
        $creator = trim((string) ($r['creator'][0] ?? ($r['publisher'][0] ?? '')));
        $creator = trim(preg_replace('/\s*\(.*?\)\s*/u', ' ', $creator) ?? $creator);
        $creator = trim((string) preg_split('/\.\s+/u', $creator)[0], ' .'); // « Agence Rol. Agence photographique » → « Agence Rol »
        return ['ark' => $ark, 'title' => self::cleanTitle($title), 'date' => $date, 'year' => $year, 'creator' => $creator,
            'url' => 'https://gallica.bnf.fr/' . $ark];
    }

    /** Date du jour : « 8-9-29, équipe… » dans le titre (agences Rol, Meurisse), ou date complète de la notice. */
    public static function dateOf(string $title, string $date): ?string
    {
        if (preg_match('/^\s*(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2,4})\b/', $title, $m)) {
            $y = (int) $m[3];
            $y = $y < 100 ? 1900 + $y : $y;
            if (checkdate((int) $m[2], (int) $m[1], $y)) {
                return sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
            }
        }
        if (preg_match('/^(19\d\d)-(\d\d)-(\d\d)$/', trim($date), $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return "$m[1]-$m[2]-$m[3]";
        }
        return null;
    }

    /** Titre lisible : sans « [photographie de presse] », sans la date en tête, sans l'agence en fin. */
    private static function cleanTitle(string $t): string
    {
        // Crochets de la BnF : « [photographie de presse] » retiré ; « [Leslie] Miller », « [i.e. André Maschinot] » gardés.
        $t = preg_replace('/\s*\[[^\]]*(photographie|agence|graphique|image)[^\]]*\]\s*/iu', ' ', $t) ?? $t;
        $t = preg_replace('/\[i\.\s?e\.\s*([^\]]*)\]/u', '($1)', $t) ?? $t;
        $t = preg_replace('/\[([^\]]*)\]/u', '$1', $t) ?? $t;
        $t = preg_replace('/\s*:\s*$/u', '', trim($t)) ?? $t;
        $t = preg_replace('/^\s*\d{1,2}[-\/.]\d{1,2}[-\/.]\d{2,4}\s*[,:]?\s*/u', '', $t) ?? $t;
        $t = preg_replace('#\s*/\s*[^/]*$#u', '', $t) ?? $t;
        $t = trim(preg_replace('/\s+/u', ' ', $t) ?? $t, " ,.:;");
        return mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
    }

    // ------------------------------------------------------------------ rangement

    /** Fiche de destination d'une notice : [id, pourquoi] ou null. */
    public static function target(array $n, array $matches, array $people): ?array
    {
        $title = ' ' . implode(' ', Names::tokens($n['title'])) . ' ';
        // 1. Match du jour (ou de la veille : photo datée du lendemain).
        if ($n['date']) {
            foreach ([0, 1] as $back) {
                $d = date('Y-m-d', (int) strtotime($n['date'] . " -$back day"));
                if (!empty($matches['date'][$d])) {
                    return [$matches['date'][$d][0], 'match du ' . date_fr($d)];
                }
            }
        }
        // 2. Adversaire cité dans le titre, même année (un seul match possible).
        $cands = [];
        foreach ($matches['year'][$n['year']] ?? [] as [$id, $opp]) {
            $toks = array_filter(Names::tokens($opp), fn ($t) => mb_strlen($t) >= 4 && !in_array($t, ['club', 'sport', 'sports', 'football', 'union', 'stade', 'racing'], true));
            foreach ($toks as $t) {
                if (str_contains($title, " $t ")) {
                    $cands[$id] = true;
                }
            }
        }
        if (count($cands) === 1) {
            return [(int) array_key_first($cands), 'adversaire cité, ' . $n['year']];
        }
        // 3. Joueur cité (nom de famille distinctif, présent une seule fois au musée).
        foreach ($people as $last => $ids) {
            if (count($ids) === 1 && mb_strlen($last) >= 5 && str_contains($title, " $last ")) {
                return [$ids[0], 'joueur cité'];
            }
        }
        return null;
    }

    /** Index des matchs (par date, par année) et des personnes (nom de famille). */
    private static function indexes(): array
    {
        $m = ['date' => [], 'year' => []];
        $p = [];
        foreach (Index::all() as $id => $s) {
            if (($s['type'] ?? '') === 'match' && !empty($s['m']['date']) && ($s['status'] ?? '') === 'publie') {
                $d = (string) $s['m']['date'];
                if ((int) substr($d, 0, 4) <= self::LAST_YEAR) {
                    $m['date'][$d][] = (int) $id;
                    $opp = !empty($s['m']['sh']) ? (string) ($s['m']['away'] ?? '') : (string) ($s['m']['home'] ?? '');
                    $m['year'][(int) substr($d, 0, 4)][] = [(int) $id, $opp];
                }
            } elseif (($s['type'] ?? '') === 'personne') {
                $toks = Names::tokens(preg_replace('/\(.*?\)/u', '', (string) $s['title']) ?? '');
                if ($toks) {
                    $p[end($toks)][] = (int) $id;
                }
            }
        }
        return [$m, $p];
    }

    /**
     * Importe au plus $max photos trouvées : image IIIF, médiathèque, galerie (et image principale
     * si la fiche n'en a pas). @return array{done:int,left:int,unplaced:int,errors:int,messages:list<string>}
     */
    public static function import(int $max = 60): array
    {
        $state = self::state();
        [$matches, $people] = self::indexes();
        $hashes = [];
        foreach (Media::all() as $rel => $m) {
            if (!empty($m['sha1'])) {
                $hashes[(string) $m['sha1']] = (string) $rel;
            }
        }
        $res = ['done' => 0, 'left' => 0, 'unplaced' => 0, 'errors' => 0, 'messages' => []];
        $n = 0;
        foreach ($state['found'] as $ark => $notice) {
            if (isset($state['done'][$ark])) {
                continue;
            }
            $t = self::target($notice, $matches, $people);
            if (!$t) {
                $res['unplaced']++;
                continue;
            }
            if ($n++ >= $max) {
                $res['left']++;
                continue;
            }
            try {
                $rel = self::store($notice, $hashes);
                self::attach($t[0], $rel, $notice);
                $state['done'][$ark] = ['rel' => $rel, 'fiche' => $t[0], 'why' => $t[1], 'at' => date('c')];
                $res['done']++;
            } catch (\Throwable $e) {
                $res['errors']++;
                $res['messages'][] = $notice['title'] . ' : ' . mb_substr($e->getMessage(), 0, 160);
            }
            JsonStore::write(self::file(), $state);
        }
        JsonStore::write(self::file(), $state);
        return $res;
    }

    /** Notices trouvées mais sans fiche de destination (pour l'écran). */
    public static function unplaced(): array
    {
        $state = self::state();
        [$matches, $people] = self::indexes();
        $out = [];
        foreach ($state['found'] as $ark => $n) {
            if (!isset($state['done'][$ark]) && !self::target($n, $matches, $people)) {
                $out[] = $n;
            }
        }
        return $out;
    }

    private static function store(array $n, array &$hashes): string
    {
        $bytes = '';
        foreach (['https://gallica.bnf.fr/iiif/' . $n['ark'] . '/f1/full/1600,/0/native.jpg', 'https://gallica.bnf.fr/' . $n['ark'] . '/f1.highres'] as $u) {
            try {
                $bytes = self::fetch($u);
                if (@getimagesizefromstring($bytes)) {
                    break;
                }
            } catch (\Throwable) {
                $bytes = '';
            }
        }
        $info = $bytes !== '' ? @getimagesizefromstring($bytes) : false;
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw new \RuntimeException('image introuvable sur Gallica');
        }
        $sha = sha1($bytes);
        if (isset($hashes[$sha])) {
            return $hashes[$sha];
        }
        $ext = $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg';
        $base = Paths::slug(($n['date'] ?? (string) $n['year']) . '-' . $n['title'], 80) ?: 'gallica';
        $rel = FcsmPhotos::DIR . '/gallica/' . $n['year'] . "/$base.$ext";
        for ($i = 2; is_file(Media::ORIGINALS . '/' . $rel) || Media::get($rel); $i++) {
            $rel = FcsmPhotos::DIR . '/gallica/' . $n['year'] . "/$base-$i.$ext";
        }
        $dest = Media::ORIGINALS . '/' . $rel;
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0775, true);
        }
        if (file_put_contents($dest, $bytes) === false) {
            throw new \RuntimeException('écriture impossible');
        }
        @chmod($dest, 0664);
        Media::put($rel, ['file' => $rel, 'mime' => $info['mime'], 'width' => $info[0], 'height' => $info[1], 'size' => strlen($bytes), 'sha1' => $sha,
            'title' => $n['title'], 'caption' => self::caption($n), 'credit' => self::credit($n), 'alt' => $n['title'],
            'rights' => 'Domaine public (BnF, Gallica)', 'added' => date('c'), 'source' => $n['url']], ['name' => 'Reprise Gallica']);
        $hashes[$sha] = $rel;
        return $rel;
    }

    public static function caption(array $n): string
    {
        return $n['title'] . ($n['date'] ? ', ' . date_fr($n['date']) : ', ' . $n['year']);
    }

    public static function credit(array $n): string
    {
        return ($n['creator'] !== '' ? $n['creator'] . ' · ' : '') . self::CREDIT;
    }

    private static function attach(int $id, string $rel, array $n): void
    {
        $doc = Fiches::fresh($id);
        if (!$doc) {
            throw new \RuntimeException("fiche $id introuvable");
        }
        if (in_array($rel, array_column((array) $doc['gallery'], 'image'), true)) {
            return;
        }
        $doc['gallery'][] = ['image' => $rel, 'caption' => self::caption($n), 'credit' => self::credit($n), 'caption_raw' => self::caption($n)];
        if (empty($doc['featured_image'])) {
            $doc['featured_image'] = $rel;
        }
        Fiches::save($doc, ['name' => 'Reprise Gallica'], 'Photo de Gallica ajoutée (' . $n['title'] . ')');
    }
}
