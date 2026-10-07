<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Data\Fiches;
use App\Data\Media;
use App\Data\Names;
use App\Data\Paths;

/**
 * Photos de la reprise 1928-1969 : coupures de presse et photos d'agence publiées sur
 * fcsmstory.com, rangées dans la fiche du match (ou de l'article, du tournoi, du portrait) créée par
 * FcsmImport, avec légende et crédit.
 *
 * Seules les reproductions fidèles d'originaux du domaine public sont reprises : journal ou agence
 * identifié dans le nom du fichier, date de publication en 1955 au plus tard (œuvre collective : 70 ans
 * après la publication ; reproduction fidèle sans droit nouveau, directive 2019/790, art. 14). Sont
 * écartés : images retouchées ou colorisées (« -ia », « color »), dessins et caricatures signés,
 * captures d'écran, tableaux, logos, photos sans date ni journal.
 */
final class FcsmPhotos
{
    public const LAST_YEAR = 1955;
    public const DIR = 'fcsmstory';
    public const CREDIT = 'Presse de l’époque, via FCSM Story (fcsmstory.com)';
    /** Téléchargement remplaçable (essais automatiques) : fn(string $url): string. */
    public static $get = null;

    /** Jetons du nom de fichier → titre du journal ou de l'agence. */
    private const PUBLICATIONS = [
        'lauto' => 'L’Auto', 'l-auto' => 'L’Auto', 'le-matin' => 'Le Matin', 'journal-officiel' => 'Journal officiel', 'le-journal' => 'Le Journal',
        'le-miroir-des-sports' => 'Le Miroir des sports', 'miroir' => 'Le Miroir des sports', 'l-alsace' => 'L’Alsace', 'lalsace' => 'L’Alsace',
        'les-sports' => 'Les Sports', 'l-est-republicain' => 'L’Est républicain', 'lest-republicain' => 'L’Est républicain', 'l-est' => 'L’Est républicain',
        'paris-soir' => 'Paris-Soir', 'paris-midi' => 'Paris-Midi', 'la-republique' => 'La République', 'lecho-dalger' => 'L’Écho d’Alger', 'lecho-de-paris' => 'L’Écho de Paris',
        'lecho' => 'L’Écho', 'ostdeutsche-morgenpost' => 'Ostdeutsche Morgenpost', 'le-petit-comtois' => 'Le Petit Comtois', 'le-petit-parisien' => 'Le Petit Parisien',
        'le-petit-journal' => 'Le Petit Journal', 'le-petit' => 'Le Petit Comtois', 'match-lintran' => 'Match l’Intran', 'match' => 'Match l’Intran', 'sport' => 'Sport',
        'lancien-combattant' => 'L’Ancien Combattant', 'le-pays' => 'Le Pays', 'le-jour' => 'Le Jour', 'ce-soir' => 'Ce soir', 'les-dernieres-nouvelles' => 'Les Dernières Nouvelles d’Alsace',
        'lequipe' => 'L’Équipe', 'le-dimanche' => 'Le Dimanche', 'the-chicago' => 'The Chicago Tribune', 'l-homme' => 'L’Homme libre', 'la-depeche' => 'La Dépêche',
        'liberte-soir' => 'Liberté-Soir', 'le-figaro' => 'Le Figaro', 'l-ami' => 'L’Ami du peuple', 'loeuvre' => 'L’Œuvre', 'l-oeuvre' => 'L’Œuvre', 'excelsior' => 'Excelsior',
        'lintransigeant' => 'L’Intransigeant', 'bonner-zeitung' => 'Bonner Zeitung', 'dresdner-nachrichten' => 'Dresdner Nachrichten', 'dresdner-neueste' => 'Dresdner Neueste Nachrichten',
        'sachsische-elbzeitung' => 'Sächsische Elbzeitung', 'der-sachsische' => 'Der Sächsische Erzähler', 'sachsische-volkszeitung' => 'Sächsische Volkszeitung',
        'kattowitzer-zeitung' => 'Kattowitzer Zeitung', 'saale-zeitung' => 'Saale-Zeitung', 'hallische-nachrichten' => 'Hallische Nachrichten',
        'football' => 'Football', 'agence-rol' => 'Agence Rol', 'rol' => 'Agence Rol', 'meurisse' => 'Agence Meurisse', 'buffalo' => 'Agence Rol',
    ];

    /**
     * Fichier repris ? Renvoie [date ISO, publication] ou null (écarté).
     */
    public static function eligible(string $file): ?array
    {
        $n = strtolower(Names::ascii(pathinfo($file, PATHINFO_FILENAME)));
        if (!preg_match('/^(19\d\d)[-_](\d\d)[-_](\d\d)[-_](.+)$/', $n, $m) || (int) $m[1] > self::LAST_YEAR || (int) $m[1] < FcsmStory::FIRST - 1) {
            return null;
        }
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }
        // Retouches, dessins signés, captures, tableaux, logos : jamais.
        if (preg_match('/(^|[-_])(ia|ai)\d*([-_]|$)|colo(r|ri)|couleur|clorise|croqu|dessin|caricat|screenshot|capture|tableau|logo|ecuson|bayard|\bbd\b/', $n)) {
            return null;
        }
        $rest = str_replace('_', '-', $m[4]);
        $pub = null;
        foreach (self::PUBLICATIONS as $tok => $title) {
            if (str_starts_with($rest, $tok . '-') || $rest === $tok || str_contains('-' . $rest . '-', '-' . $tok . '-') && in_array($tok, ['agence-rol', 'rol', 'meurisse', 'buffalo'], true)) {
                $pub = $title;
                break;
            }
        }
        return $pub ? [sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]), $pub] : null;
    }

    /**
     * Photos reprenables et fiche de destination. Sur une page de saison, une photo va au match dans
     * le récit duquel elle se trouve ; sinon (introduction, bilan) au match joué 0 à 3 jours avant la
     * date du journal. Ailleurs, à la fiche créée pour la page.
     * @return list<array{url:string,file:string,date:string,pub:string,caption:string,key:?string}>
     */
    public static function plan(array $raw, array $items): array
    {
        $byDate = [];
        foreach ($items as $it) {
            if (in_array($it['kind'], ['match', 'match-page'], true) && ($d = $it['data']['date'] ?? ($it['date'] ?? null))) {
                $byDate[$d][] = $it['key'];
            }
        }
        $pageKey = [];
        foreach ($items as $it) {
            if (!in_array($it['kind'], ['match', 'season'], true)) {
                $pageKey[$it['source']] = $it['key'];
            }
        }
        $firstOf = [];
        foreach ($items as $it) {
            if ($it['kind'] === 'match' && ($d = $it['data']['date'] ?? null)) {
                $s = FcsmStory::seasonFor($d);
                if (!isset($firstOf[$s]) || $d < $firstOf[$s][0]) {
                    $firstOf[$s] = [$d, $it['key']];
                }
            }
        }
        $firstOf = array_map(fn ($x) => $x[1], $firstOf);
        $out = [];
        $seen = [];
        foreach ($raw as $page) {
            $kind = FcsmStory::classify($page);
            if ($kind === 'skip') {
                continue;
            }
            $season = $kind === 'season' ? (string) FcsmStory::seasonOf($page) : '';
            $current = null;
            // Le contenu est parcouru dans l'ordre : le texte entre deux images fait avancer le match en cours.
            $parts = preg_split('#(<img\b[^>]*>|<figcaption\b[^>]*>.*?</figcaption>)#is', $page['html'], -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
            $last = null;
            foreach ($parts as $t) {
                if (stripos($t, '<img') === 0) {
                    if (!preg_match('/\bsrc="([^"]+)"/i', $t, $s)) {
                        continue;
                    }
                    $url = preg_replace('/-\d+x\d+(?=\.\w+$)/', '', html_entity_decode($s[1]));
                    $file = basename((string) parse_url((string) $url, PHP_URL_PATH));
                    $ok = self::eligible($file);
                    if (!$ok || isset($seen[$url])) {
                        $last = null;
                        continue;
                    }
                    [$date, $pub] = $ok;
                    $key = $kind === 'season' ? $current : ($pageKey[$page['link']] ?? null);
                    if ($kind === 'season' && !$key) {
                        // Introduction ou bilan : match du jour du journal, sinon premier match de la saison (calendriers, classements).
                        $key = self::nearest($byDate, $date) ?? ($firstOf[$season] ?? null);
                    }
                    $alt = preg_match('/\balt="([^"]*)"/i', $t, $a) ? trim(html_entity_decode($a[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
                    if ($kind === 'player' && !self::mentions($file . ' ' . $alt, (string) ($items[$key]['label'] ?? ''))) {
                        $key = null; // photo de portrait qui ne montre pas ce joueur (classement, calendrier…)
                    }
                    $key ??= self::nearest($byDate, $date); // à défaut : le match du jour du journal
                    $seen[$url] = true;
                    $out[] = ['url' => $url, 'file' => $file, 'date' => $date, 'pub' => $pub, 'caption' => self::cleanAlt($alt, $file), 'key' => $key, 'page' => $page['link']];
                    $last = count($out) - 1;
                } elseif (stripos($t, '<figcaption') === 0) {
                    $cap = trim(html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if ($last !== null && $cap !== '' && mb_strlen($cap) < 200) {
                        $out[$last]['caption'] = $cap;
                    }
                } elseif ($kind === 'season') {
                    foreach (explode("\n", FcsmStory::text($t)) as $line) {
                        if ($line !== '' && ($m = FcsmStory::match([$line], $season))) {
                            $current = 'match:' . FcsmStory::key($m);
                            $last = null;
                        }
                    }
                }
            }
        }
        return $out;
    }

    /** Le texte cite-t-il ce joueur (nom de famille) ? */
    private static function mentions(string $text, string $name): bool
    {
        $hay = ' ' . implode(' ', Names::tokens($text)) . ' ';
        foreach (Names::tokens($name) as $t) {
            if (mb_strlen($t) >= 4 && str_contains($hay, " $t ")) {
                return true; // nom ou prénom distinctif (« Leslie » pour Leslie Miller)
            }
        }
        return false;
    }

    /** Match joué le jour même ou dans les trois jours avant la date du journal. */
    private static function nearest(array $byDate, string $date): ?string
    {
        for ($i = 0; $i <= 3; $i++) {
            $d = date('Y-m-d', (int) strtotime("$date -$i day"));
            if (!empty($byDate[$d])) {
                return $byDate[$d][0];
            }
        }
        return null;
    }

    /** Texte alternatif utile (pas un nom de fichier). */
    private static function cleanAlt(string $alt, string $file): string
    {
        $norm = fn (string $x) => trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(Names::ascii($x))), '-');
        return $alt === '' || str_contains($norm(pathinfo($file, PATHINFO_FILENAME)), $norm($alt)) ? '' : $alt;
    }

    /**
     * Importe au plus $max photos : téléchargement, médiathèque (légende, crédit, droits, source),
     * ajout à la galerie de la fiche. @return array{done:int,skipped:int,errors:int,left:int,messages:list<string>}
     */
    public static function run(int $max = 40): array
    {
        $state = FcsmImport::state();
        $items = $state['items'];
        $plan = self::plan(FcsmStory::raw(), $items);
        $file = FcsmStory::$dir . '/photos.json';
        $done = JsonStore::read($file, []) ?: [];
        $res = ['done' => 0, 'skipped' => 0, 'errors' => 0, 'left' => 0, 'messages' => []];
        $hashes = [];
        // Photo déjà au musée (même empreinte) : réutilisée, jamais en double.
        foreach (Media::all() as $rel => $m) {
            if (!empty($m['sha1'])) {
                $hashes[(string) $m['sha1']] = (string) $rel;
            }
        }
        foreach ($done as $d) {
            if (!empty($d['sha1'])) {
                $hashes[$d['sha1']] = $d['rel'];
            }
        }
        $n = 0;
        foreach ($plan as $p) {
            if (isset($done[$p['url']])) {
                continue;
            }
            $fiche = $p['key'] && in_array($items[$p['key']]['status'] ?? '', ['fait', 'existe'], true) ? (int) ($items[$p['key']]['fiche'] ?? 0) : 0;
            if (!$fiche || !Fiches::get($fiche)) {
                $res['left']++; // fiche pas encore créée (ou sans destination) : au prochain passage
                continue;
            }
            if ($n >= $max) {
                $res['left']++;
                continue;
            }
            $n++;
            try {
                $rel = self::store($p, $hashes);
                self::attach($fiche, $rel, $p);
                $done[$p['url']] = ['rel' => $rel, 'fiche' => $fiche, 'at' => date('c'), 'sha1' => sha1_file(Media::ORIGINALS . '/' . $rel) ?: ''];
                $hashes[$done[$p['url']]['sha1']] = $rel;
                $res['done']++;
            } catch (\Throwable $e) {
                $res['errors']++;
                $res['messages'][] = $p['file'] . ' : ' . mb_substr($e->getMessage(), 0, 160);
            }
        }
        JsonStore::write($file, $done);
        $res['skipped'] = count(array_filter($plan, fn ($p) => !$p['key']));
        return $res;
    }

    /** Ce que le plan contient, pour l'écran. */
    public static function summary(): array
    {
        $state = FcsmImport::state();
        $plan = self::plan(FcsmStory::raw(), $state['items']);
        $done = JsonStore::read(FcsmStory::$dir . '/photos.json', []) ?: [];
        $ready = count(array_filter($plan, fn ($p) => $p['key'] && in_array($state['items'][$p['key']]['status'] ?? '', ['fait', 'existe'], true)));
        return ['total' => count($plan), 'done' => count(array_intersect_key($done, array_flip(array_column($plan, 'url')))), 'ready' => $ready,
            'homeless' => array_values(array_map(fn ($p) => $p['file'], array_filter($plan, fn ($p) => !$p['key'])))];
    }

    /** Fichier dans la médiathèque (fcsmstory/AAAA/…), même photo jamais en double. */
    private static function store(array $p, array $hashes): string
    {
        $get = self::$get ?? static function (string $url): string {
            $ctx = stream_context_create(['http' => ['timeout' => 40, 'header' => "User-Agent: SochauxRetro-Musee/1.0\r\n"]]);
            $b = @file_get_contents($url, false, $ctx);
            if ($b === false || $b === '') {
                throw new \RuntimeException('téléchargement impossible');
            }
            return $b;
        };
        $bytes = $get($p['url']);
        if (strlen($bytes) > Media::UPLOAD_MAX) {
            throw new \RuntimeException('fichier trop lourd');
        }
        $info = @getimagesizefromstring($bytes);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            throw new \RuntimeException('ce n’est pas une image');
        }
        $sha = sha1($bytes);
        if (isset($hashes[$sha])) {
            return $hashes[$sha];
        }
        $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$info[2]];
        $base = Paths::slug(pathinfo($p['file'], PATHINFO_FILENAME), 80) ?: 'photo';
        $rel = self::DIR . '/' . substr($p['date'], 0, 4) . "/$base.$ext";
        for ($i = 2; is_file(Media::ORIGINALS . '/' . $rel) || Media::get($rel); $i++) {
            $rel = self::DIR . '/' . substr($p['date'], 0, 4) . "/$base-$i.$ext";
        }
        $dest = Media::ORIGINALS . '/' . $rel;
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0775, true);
        }
        if (file_put_contents($dest, $bytes) === false) {
            throw new \RuntimeException('écriture impossible');
        }
        @chmod($dest, 0664);
        $title = self::title($p);
        Media::put($rel, ['file' => $rel, 'mime' => $info['mime'], 'width' => $info[0], 'height' => $info[1], 'size' => strlen($bytes), 'sha1' => $sha,
            'title' => $title, 'caption' => $p['caption'] !== '' ? $p['caption'] : $title, 'credit' => self::CREDIT,
            'rights' => 'Domaine public (' . $p['pub'] . ', ' . substr($p['date'], 0, 4) . ')', 'alt' => $title,
            'added' => date('c'), 'source' => $p['url']], ['name' => 'Reprise FCSM Story']);
        return $rel;
    }

    /** « L’Auto, 4 février 1935 ». */
    public static function title(array $p): string
    {
        return $p['pub'] . ', ' . date_fr($p['date']);
    }

    /** Ajoute la photo à la galerie de la fiche (une seule fois). */
    private static function attach(int $id, string $rel, array $p): void
    {
        $doc = Fiches::fresh($id);
        if (!$doc) {
            throw new \RuntimeException("fiche $id introuvable");
        }
        if (in_array($rel, array_column((array) $doc['gallery'], 'image'), true)) {
            return;
        }
        $cap = $p['caption'] !== '' && $p['caption'] !== self::title($p) ? $p['caption'] . ' (' . self::title($p) . ')' : self::title($p);
        $doc['gallery'][] = ['image' => $rel, 'caption' => $cap, 'credit' => self::CREDIT, 'caption_raw' => $cap];
        Fiches::save($doc, ['name' => 'Reprise FCSM Story'], 'Photo de presse ajoutée (' . self::title($p) . ')');
    }
}
