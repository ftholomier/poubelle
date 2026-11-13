<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Data\Media;

/**
 * Droits des photos : repère les photos d'agences de presse et de banques d'images (Getty, Adobe
 * Stock, AFP, Icon Sport…), celles qui valent des factures quand elles sont publiées sans licence.
 *
 * Deux sources, jamais modifiées ici :
 * - ce que dit la médiathèque (nom du fichier, crédit, droits, légende, titre, légende d'origine) ;
 * - les informations cachées dans le fichier (IPTC, EXIF, XMP : copyright, crédit, auteur, source,
 *   conditions d'utilisation), lues par la tâche planifiée « Droits des photos » et à l'envoi.
 *
 * Les métadonnées des photos ne sont jamais effacées ni modifiées : elles servent à créditer.
 * Une photo signalée le reste jusqu'à la décision d'un membre de l'équipe (champ « rights_ok » du
 * média : qui, quand, pourquoi on la garde) ; retirer la photo des contenus puis la supprimer
 * fait aussi disparaître l'alerte.
 */
final class PhotoRights
{
    /** Informations lues dans les fichiers : chemin => ['sig' => taille-date, 'meta' => [...]]. */
    public const FILE = STORAGE_PATH . '/droits-photos.json';

    public const KINDS = [
        'banque' => 'banque d’images',
        'agence' => 'agence photo',
        'presse' => 'journal',
    ];

    /**
     * Qui, quelle sorte, motif. « ctx » : nom courant dans une phrase (« l'équipe de Sochaux ») :
     * dans une légende ou un titre, retenu seulement après « photo », « crédit », « © »…
     * Dans le crédit, les droits et les informations du fichier, toujours retenu.
     */
    public const SOURCES = [
        ['Adobe Stock', 'banque', '(?:adobe ?stock|stock\.adobe\.com)'],
        ['Fotolia', 'banque', 'fotolia'],
        ['Shutterstock', 'banque', 'shutterstock'],
        ['Getty Images', 'banque', 'getty ?images|gettyimages|getty'],
        ['iStock', 'banque', 'istock(?:photo)?'],
        ['Depositphotos', 'banque', 'depositphotos'],
        ['Dreamstime', 'banque', 'dreamstime'],
        ['123RF', 'banque', '123rf'],
        ['Alamy', 'banque', 'alamy'],
        ['Bigstock', 'banque', 'bigstock(?:photo)?'],
        ['AFP', 'agence', 'afp'],
        ['Reuters', 'agence', 'reuters'],
        ['Associated Press', 'agence', 'associated press|ap photo'],
        ['Icon Sport', 'agence', 'icon ?sport'],
        ['Maxppp', 'agence', 'maxppp'],
        ['Presse Sports', 'agence', 'presse ?sports?'],
        ['Panoramic', 'agence', 'panoramic'],
        ['Abaca', 'agence', 'abaca(?:press)?'],
        ['Sipa', 'agence', 'sipa(?: ?press)?', true],
        ['DPPI', 'agence', 'dppi'],
        ['Imago', 'agence', 'imago(?: images)?|imago\d{6,}'],
        ['Keystone', 'agence', 'keystone', true],
        ['Belga', 'agence', 'belga', true],
        ['EPA', 'agence', 'epa[- ]efe|epa images'],
        ['Gamma-Rapho', 'agence', 'gamma(?:[- ]rapho)?|rapho', true],
        ['Roger-Viollet', 'agence', 'roger[- ]viollet'],
        ['Corbis', 'agence', 'corbis'],
        ['L’Équipe', 'presse', 'l[’\'-]? ?[ée]quipe', true],
        ['France Football', 'presse', 'france ?football', true],
        ['L’Est Républicain', 'presse', 'l[’\'-]? ?est r[ée]publicain|\ber\b', true],
        ['Le Pays', 'presse', 'le pays', true],
        ['Onze Mondial', 'presse', 'onze mondial', true],
        ['Paris Match', 'presse', 'paris[- ]match', true],
        ['Le Parisien', 'presse', 'le parisien', true],
        ['Ouest-France', 'presse', 'ouest[- ]france', true],
        ['Miroir des sports', 'presse', 'miroir (?:des )?sports?|miroir[- ]sprint', true],
        ['But !', 'presse', 'but ?!', true],
    ];

    /** Champs de la médiathèque lus, et leur nom à l'écran. « true » : le champ dit déjà qui détient les droits. */
    private const FIELDS = [
        'credit' => ['le crédit', true],
        'rights' => ['les droits', true],
        'file' => ['le nom du fichier', false],
        'title' => ['le titre', false],
        'caption' => ['la légende', false],
        'caption_raw' => ['la légende d’origine', false],
        'alt' => ['le texte alternatif', false],
    ];

    /** Informations du fichier, et leur nom à l'écran. */
    public const META_LABELS = [
        'copyright' => 'copyright',
        'credit' => 'crédit',
        'byline' => 'auteur',
        'source' => 'source',
        'usage' => 'conditions d’utilisation',
        'url' => 'adresse des droits',
        'description' => 'description',
    ];

    private const CONTEXT = '(?:photos?|cr[ée]dits?|copyright|©|\(c\)|source|via|agence|cliché|ph\.?|/)\s*[:\-–]?\s*(?:[a-zà-ÿ.\'’ -]{0,40}?[/,]\s*)?';

    private static ?array $store = null;

    /**
     * Ce qui signale la photo, le plus sûr d'abord (une entrée par détenteur).
     * @param ?array $fileMeta informations du fichier (null : celles enregistrées par la tâche)
     * @return list<array{who:string,kind:string,where:string,text:string}>
     */
    public static function findings(string $rel, array $m, ?array $fileMeta = null): array
    {
        $out = [];
        $add = function (string $who, string $kind, string $where, string $text) use (&$out) {
            $out[$who] ??= ['who' => $who, 'kind' => $kind, 'where' => $where, 'text' => mb_substr(trim($text), 0, 160)];
        };
        $fileMeta ??= self::stored($rel);
        foreach ($fileMeta as $k => $v) {
            foreach (self::match((string) $v, true) as [$who, $kind]) {
                $add($who, $kind, 'les informations cachées de la photo (' . (self::META_LABELS[$k] ?? $k) . ')', (string) $v);
            }
        }
        foreach (self::FIELDS as $k => [$label, $owner]) {
            $v = $k === 'file' ? str_replace(['_', '-', '.'], ' ', basename($rel)) : (string) ($m[$k] ?? '');
            foreach (self::match($v, $owner) as [$who, $kind]) {
                $add($who, $kind, $label, $k === 'file' ? basename($rel) : $v);
            }
        }
        // Noms de fichiers des banques d'images (« AdobeStock_123456789.jpeg », « GettyImages-123… »).
        if (preg_match('/^(adobestock|shutterstock|gettyimages|istock|fotolia|depositphotos|dreamstime|bigstock)[_-]?\d{5,}/i', basename($rel), $x)) {
            foreach (self::match($x[1], true) as [$who, $kind]) {
                $add($who, $kind, 'le nom du fichier', basename($rel));
            }
        }
        $rank = ['banque' => 0, 'agence' => 1, 'presse' => 2];
        $out = array_values($out);
        usort($out, fn ($a, $b) => $rank[$a['kind']] <=> $rank[$b['kind']]);
        return $out;
    }

    /** @return list<array{0:string,1:string}> détenteurs nommés dans un texte */
    public static function match(string $text, bool $owner): array
    {
        if (trim($text) === '') {
            return [];
        }
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $found = [];
        foreach (self::SOURCES as $s) {
            [$who, $kind, $re] = $s;
            $ambiguous = $s[3] ?? false;
            // « ER » (L'Est Républicain) : seulement après « photo » (« photo ER Jean Dupont »), jamais seul.
            $needCtx = $ambiguous && !($owner && $who !== 'L’Est Républicain');
            $pattern = '~(?<![\p{L}\d])' . ($needCtx ? self::CONTEXT : '') . '(?:' . $re . ')(?![\p{L}])~iu';
            if (preg_match($pattern, $text)) {
                $found[] = [$who, $kind];
            }
        }
        return $found;
    }

    /** Photos à vérifier (non tranchées par l'équipe) : chemin => constats. */
    public static function pending(): array
    {
        static $memo = null;
        if ($memo !== null) {
            return $memo;
        }
        // Recalcul seulement quand la médiathèque ou le relevé des fichiers ont changé (½ s sur 13 000 photos).
        $cache = STORAGE_PATH . '/cache/droits-a-verifier.php';
        $sig = @filemtime(Media::FILE) . '-' . @filesize(Media::FILE) . '-' . @filemtime(self::FILE) . '-' . @filesize(self::FILE) . '-' . md5_file(__FILE__);
        $c = is_file($cache) ? @include $cache : null;
        if (is_array($c) && ($c['sig'] ?? '') === $sig) {
            return $memo = $c['list'];
        }
        $memo = [];
        foreach (Media::all() as $rel => $m) {
            $rel = (string) $rel;
            if (!empty($m['rights_ok']) || str_ends_with(strtolower($rel), '.pdf')) {
                continue;
            }
            if ($f = self::findings($rel, $m)) {
                $memo[$rel] = $f;
            }
        }
        @mkdir(dirname($cache), 0775, true);
        @file_put_contents($cache, '<?php return ' . var_export(['sig' => $sig, 'list' => $memo], true) . ';', LOCK_EX);
        return $memo;
    }

    /** Phrase courte : « AFP (agence photo), trouvé dans la légende ». */
    public static function summary(array $findings): string
    {
        if (!$findings) {
            return '';
        }
        $f = $findings[0];
        $others = array_slice(array_column($findings, 'who'), 1);
        return $f['who'] . ' (' . self::KINDS[$f['kind']] . '), trouvé dans ' . $f['where'] . ($others ? ' ; aussi : ' . implode(', ', $others) : '');
    }

    /** Crédit proposé à partir des informations du fichier (jamais appliqué d'office à une photo existante). */
    public static function suggestedCredit(array $meta): string
    {
        $credit = trim((string) ($meta['credit'] ?? ''));
        $by = trim((string) ($meta['byline'] ?? ''));
        $parts = array_values(array_unique(array_filter([$by, $credit])));
        $s = implode(' / ', $parts);
        if ($s === '') {
            $s = trim((string) preg_replace('/^(?:copyright|©|\(c\))\s*(?:\d{4}\s*)?/iu', '', (string) ($meta['copyright'] ?? '')));
        }
        return mb_substr($s, 0, 200);
    }

    /** Informations enregistrées pour un fichier (vide si pas encore lu). */
    public static function stored(string $rel): array
    {
        self::$store ??= JsonStore::read(self::FILE, []) ?: [];
        return (array) (self::$store[$rel]['meta'] ?? []);
    }

    /** Lecture d'un fichier à l'envoi : informations enregistrées tout de suite. */
    public static function remember(string $rel, string $path): array
    {
        $meta = self::read($path);
        JsonStore::update(self::FILE, function ($all) use ($rel, $path, $meta) {
            $all[$rel] = ['sig' => self::sig($path), 'meta' => $meta];
            return $all;
        }, []);
        self::$store = null;
        return $meta;
    }

    /**
     * Tâche planifiée : lit les fichiers pas encore lus ou changés, dans le temps donné.
     * @return ?string compte rendu (null : rien à faire)
     */
    public static function scan(float $budget = 30, int $max = 800): ?string
    {
        $end = microtime(true) + $budget;
        $store = JsonStore::read(self::FILE, []) ?: [];
        $all = Media::all();
        $done = 0;
        $left = 0;
        foreach ($all as $rel => $m) {
            $rel = (string) $rel;
            if (str_ends_with(strtolower($rel), '.pdf') || !($path = Media::file($rel))) {
                continue;
            }
            $sig = self::sig($path);
            if (($store[$rel]['sig'] ?? null) === $sig) {
                continue;
            }
            if ($done >= $max || microtime(true) > $end) {
                $left++;
                continue;
            }
            $store[$rel] = ['sig' => $sig, 'meta' => self::read($path)];
            $done++;
        }
        $store = array_intersect_key($store, $all);
        JsonStore::write(self::FILE, $store);
        self::$store = $store;
        if (!$done) {
            return null;
        }
        $flagged = count(array_filter(array_keys($store), fn ($r) => self::findings((string) $r, $all[$r] ?? [], (array) $store[$r]['meta']) !== []));
        return $done . ' photo' . ($done > 1 ? 's' : '') . ' lue' . ($done > 1 ? 's' : '') . ($left ? ', ' . $left . ' restante' . ($left > 1 ? 's' : '') : '') . ' ; ' . count(self::pending()) . ' à vérifier dans Qualité › Droits des photos (' . $flagged . ' signalée' . ($flagged > 1 ? 's' : '') . ' au total)';
    }

    private static function sig(string $path): string
    {
        return filesize($path) . '-' . filemtime($path);
    }

    /**
     * Informations de droits cachées dans un fichier image : IPTC (JPEG), EXIF (JPEG), XMP (JPEG,
     * PNG, WebP, GIF). Seulement les champs utiles, texte court.
     * @return array<string,string>
     */
    public static function read(string $path): array
    {
        $out = [];
        $put = function (string $k, mixed $v) use (&$out) {
            if (is_array($v)) {
                $v = implode(', ', array_filter(array_map(fn ($x) => is_scalar($x) ? (string) $x : '', $v)));
            }
            $v = trim((string) preg_replace('/\s+/u', ' ', self::utf8((string) $v)));
            if ($v !== '' && !isset($out[$k])) {
                $out[$k] = mb_substr($v, 0, 300);
            }
        };
        $info = [];
        if (@getimagesize($path, $info) && isset($info['APP13']) && ($iptc = @iptcparse($info['APP13']))) {
            foreach (['2#116' => 'copyright', '2#110' => 'credit', '2#080' => 'byline', '2#115' => 'source', '2#040' => 'usage', '2#120' => 'description'] as $tag => $k) {
                if (isset($iptc[$tag])) {
                    $put($k, $iptc[$tag]);
                }
            }
        }
        if (function_exists('exif_read_data') && preg_match('/\.jpe?g$/i', $path) && ($exif = @exif_read_data($path, 'IFD0'))) {
            $put('copyright', $exif['Copyright'] ?? '');
            $put('byline', $exif['Artist'] ?? '');
            $put('description', $exif['ImageDescription'] ?? '');
        }
        $fh = @fopen($path, 'rb');
        $head = $fh ? (string) fread($fh, 1024 * 1024) : '';
        if ($fh) {
            fclose($fh);
        }
        if (($a = strpos($head, '<x:xmpmeta')) !== false && ($b = strpos($head, '</x:xmpmeta>', $a)) !== false) {
            $xmp = substr($head, $a, $b - $a);
            foreach (['dc:rights' => 'copyright', 'photoshop:Credit' => 'credit', 'dc:creator' => 'byline', 'photoshop:Source' => 'source', 'xmpRights:UsageTerms' => 'usage', 'xmpRights:WebStatement' => 'url', 'plus:LicensorURL' => 'url', 'plus:LicensorName' => 'credit', 'dc:description' => 'description'] as $tag => $k) {
                $put($k, self::xmp($xmp, $tag));
            }
        }
        return $out;
    }

    /** Valeur d'un champ XMP (attribut ou élément, listes rdf:li comprises). */
    private static function xmp(string $xmp, string $tag): string
    {
        $t = preg_quote($tag, '/');
        if (preg_match('/\s' . $t . '="([^"]*)"/', $xmp, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        if (preg_match('/<' . $t . '\b[^>]*>(.*?)<\/' . $t . '>/s', $xmp, $m)) {
            preg_match_all('/<rdf:li\b[^>]*>(.*?)<\/rdf:li>/s', $m[1], $li);
            $v = $li[1] ? implode(', ', array_unique($li[1])) : strip_tags($m[1]);
            return html_entity_decode($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        return '';
    }

    /** Texte en UTF-8 (l'IPTC ancien est souvent en Latin-1). */
    private static function utf8(string $s): string
    {
        $s = str_replace("\0", '', $s);
        return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
    }
}
