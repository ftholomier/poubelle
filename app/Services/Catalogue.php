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
 * Catalogue des archives confiées au musée (photos, pages de livres, documents du musée Peugeot) :
 * chaque image a été lue une fois (type, date, légende, personnes, match, droits) et décrite dans
 * app/Resources/import/catalogue-images.json.gz, sous l'empreinte MD5 du fichier d'origine.
 *
 * receive() : une image déposée dans le back-office est reconnue par son contenu (pas par son nom),
 *             rangée dans la médiathèque avec sa légende, son crédit, sa date et sa rotation ;
 * accept()  : l'image validée va dans la galerie des fiches choisies (match, joueurs, pages) ;
 * reject()  : écartée, jamais reproposée. Rien n'est publié sans validation.
 */
final class Catalogue
{
    public static string $data = APP_ROOT . '/app/Resources/import/catalogue-images.json.gz';
    public static string $dir = STORAGE_PATH . '/import/catalogue';
    private const AUTHOR = ['name' => 'Catalogue des archives'];
    public const TYPES = [
        'photo' => 'Photo', 'planche_contact' => 'Planche-contact', 'page_livre' => 'Page de livre', 'coupure_presse' => 'Coupure de presse',
        'magazine' => 'Magazine', 'journal_interne' => 'Journal interne Peugeot', 'programme' => 'Programme', 'brochure' => 'Brochure',
        'document' => 'Document', 'affiche' => 'Affiche', 'autre' => 'Autre',
    ];
    public const RIGHTS = [
        'club' => 'Archives du club / de l’association', 'peugeot' => 'Archives Peugeot', 'presse' => 'Presse (autorisation à obtenir)',
        'photographe' => 'Photographe (crédit visible)', 'inconnu' => 'Origine à préciser',
    ];
    private static ?array $items = null;

    /** @return array<string,array> fiches du catalogue par MD5 (les doublons exacts pointent vers la même fiche) */
    public static function items(): array
    {
        if (self::$items !== null) {
            return self::$items;
        }
        $j = is_file(self::$data) ? gzdecode((string) file_get_contents(self::$data)) : false;
        $d = $j ? json_decode($j, true) : null;
        $out = [];
        foreach ((array) ($d['items'] ?? []) as $it) {
            if (!empty($it['md5'])) {
                $out[$it['md5']] = $it;
            }
        }
        return self::$items = $out;
    }

    /** MD5 du fichier, y compris pour ses doublons exacts : MD5 de la fiche du catalogue. */
    public static function find(string $md5): ?array
    {
        $items = self::items();
        if (isset($items[$md5])) {
            return $items[$md5];
        }
        foreach ($items as $it) {
            if (in_array($md5, (array) ($it['dup_md5'] ?? []), true)) {
                return $it;
            }
        }
        return null;
    }

    public static function state(): array
    {
        return (JsonStore::read(self::$dir . '/state.json', []) ?? []) + ['items' => []];
    }

    private static function save(string $md5, array $patch): void
    {
        JsonStore::update(self::$dir . '/state.json', function ($s) use ($md5, $patch) {
            $s = (is_array($s) ? $s : []) + ['items' => []];
            $s['items'][$md5] = $patch + ($s['items'][$md5] ?? []);
            return $s;
        }, []);
    }

    /** Statuts : attente (pas encore déposée), recu (à ranger), range, ecarte. */
    public static function summary(): array
    {
        $st = self::state()['items'];
        $out = ['total' => count(self::items()), 'attente' => 0, 'recu' => 0, 'range' => 0, 'ecarte' => 0];
        foreach (self::items() as $md5 => $_) {
            $out[$st[$md5]['status'] ?? 'attente']++;
        }
        return $out;
    }

    /**
     * Image déposée : reconnue par son empreinte, enregistrée dans la médiathèque (une seule fois).
     * @return array{status:string,md5:string,title?:string,file?:string}
     */
    public static function receive(string $path, ?array $user = null): array
    {
        $md5 = (string) md5_file($path);
        $it = self::find($md5);
        if (!$it) {
            return ['status' => 'inconnue', 'md5' => $md5];
        }
        $md5 = $it['md5'];
        $cur = self::state()['items'][$md5] ?? null;
        if ($cur && !empty($cur['file']) && Media::get($cur['file'])) {
            return ['status' => 'deja', 'md5' => $md5, 'title' => $it['title'], 'file' => $cur['file']];
        }
        if (!@getimagesize($path)) {
            return ['status' => 'illisible', 'md5' => $md5];
        }
        $year = preg_match('/^(\d{4})/', (string) ($it['date'] ?? ''), $y) ? $y[1] : (!empty($it['decade']) ? 'annees-' . $it['decade'] : 'sans-date');
        $base = Paths::slug(($it['title'] ?? '') ?: 'archive', 60) ?: 'archive';
        $rel = "archives/$year/$base.jpg";
        for ($i = 2; is_file(Media::ORIGINALS . '/' . $rel) || Media::get($rel); $i++) {
            $rel = "archives/$year/$base-$i.jpg";
        }
        $dest = Media::ORIGINALS . '/' . $rel;
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0775, true);
        }
        if (!copy($path, $dest)) {
            throw new \RuntimeException('Écriture impossible sur le serveur.');
        }
        @chmod($dest, 0664);
        [$w, $h] = @getimagesize($dest) ?: [null, null];
        $meta = ['file' => $rel, 'mime' => 'image/jpeg', 'width' => $w, 'height' => $h, 'size' => filesize($dest),
            'title' => (string) ($it['title'] ?? ''), 'caption' => (string) ($it['caption'] ?? ''), 'alt' => (string) ($it['title'] ?? ''),
            'credit' => self::credit($it), 'rights' => self::RIGHTS[$it['rights'] ?? 'inconnu'] ?? '', 'date_text' => self::dateText((string) ($it['date'] ?? '')) ?: (!empty($it['decade']) ? 'années ' . $it['decade'] : ''),
            'added' => date('c'), 'source' => 'Catalogue des archives (' . ($it['lot'] ?? '') . '/' . ($it['file'] ?? '') . ')', 'archive' => $md5];
        $rot = (int) ($it['rotate'] ?? 0);
        if (in_array($rot, [90, 180, 270], true)) {
            $meta['edit'] = ['rotate' => $rot];
        }
        Media::put($rel, $meta, $user ?? self::AUTHOR);
        self::save($md5, ['status' => 'recu', 'file' => $rel, 'at' => date('c')]);
        return ['status' => 'nouvelle', 'md5' => $md5, 'title' => (string) $it['title'], 'file' => $rel];
    }

    private static function credit(array $it): string
    {
        $c = trim((string) ($it['credit'] ?? ''));
        return $c !== '' ? $c : match ($it['rights'] ?? '') {
            'peugeot' => 'Archives Peugeot',
            'club' => 'Archives FCSM / Sochaux Rétro',
            'presse' => trim((string) ($it['publication'] ?? '')) ?: 'Presse de l’époque',
            default => 'Archives Sochaux Rétro',
        };
    }

    private static function dateText(string $d): string
    {
        return match (true) {
            (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) => date_fr($d),
            (bool) preg_match('/^(\d{4})-(\d{2})$/', $d, $m) => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'][(int) $m[2] - 1] . ' ' . $m[1],
            default => $d,
        };
    }

    /**
     * Fiches où ranger l'image : le match (date ± 2 jours, adversaire), les joueurs cités (nom de famille,
     * fiche sans ambiguïté), la saison. @return list<array{id:int,title:string,kind:string,checked:bool}>
     */
    public static function suggest(array $it): array
    {
        $out = [];
        $m = $it['match'] ?? null;
        $date = is_array($m) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($m['date'] ?? '')) ? $m['date'] : (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($it['date'] ?? '')) && is_array($m) ? $it['date'] : null);
        $byLast = [];
        foreach (Index::all() as $e) {
            $t = $e['type'] ?? '';
            if ($t === 'match' && $date && ($md = (string) ($e['m']['date'] ?? '')) !== '' && abs(strtotime($md) - strtotime($date)) <= 2 * 86400
                && (preg_match('/sochaux/i', Names::ascii((string) $e['m']['home'] . ' ' . $e['m']['away'])))) {
                $opp = Names::ascii((string) ($m['opponent'] ?? ''));
                $eOpp = Names::ascii((string) ($e['m']['sh'] ? $e['m']['away'] : $e['m']['home']));
                $same = $opp === '' || self::common($opp, $eOpp);
                $out['f' . $e['id']] = ['id' => (int) $e['id'], 'title' => (string) $e['title'], 'kind' => 'Match', 'checked' => $same, 'rank' => $same ? 0 : 3];
            } elseif ($t === 'personne' && Index::visible($e)) {
                $last = (string) ($e['p']['last'] ?? '');
                if ($last !== '') {
                    $byLast[strtolower(Names::ascii($last))][] = $e;
                }
            }
        }
        foreach ((array) ($it['persons'] ?? []) as $name) {
            $w = preg_split('/\s+/u', trim((string) $name)) ?: [];
            $cands = [];
            // Nom de famille : dernier mot, ou les deux derniers (« De La Quintinie »).
            for ($k = 1; $k <= min(3, count($w)) && !$cands; $k++) {
                $cands = $byLast[strtolower(Names::ascii(implode(' ', array_slice($w, -$k))))] ?? [];
            }
            if (count($cands) > 1 && count($w) > 1) {
                $first = strtolower(Names::ascii($w[0]));
                $cands = array_values(array_filter($cands, fn ($e) => str_starts_with(strtolower(Names::ascii((string) ($e['p']['first'] ?? ''))), substr($first, 0, 1))));
            }
            if (count($cands) === 1) {
                $e = $cands[0];
                $out['f' . $e['id']] ??= ['id' => (int) $e['id'], 'title' => (string) ($e['p']['name'] ?? $e['title']), 'kind' => 'Joueur', 'checked' => count((array) $it['persons']) <= 3, 'rank' => 1];
            }
        }
        foreach (GrandsRecits::forImage($it) as $r) {
            $out['f' . $r['id']] ??= ['id' => $r['id'], 'title' => $r['title'], 'kind' => 'Grand récit', 'checked' => true, 'rank' => 2];
        }
        $list = array_values($out);
        usort($list, fn ($a, $b) => [$a['rank'], $a['title']] <=> [$b['rank'], $b['title']]);
        return array_slice($list, 0, 12);
    }

    private static function common(string $a, string $b): bool
    {
        $w = fn ($s) => array_filter(preg_split('/[^a-z0-9]+/', strtolower($s)) ?: [], fn ($x) => strlen($x) > 2 && !in_array($x, ['club', 'stade', 'sport', 'sports', 'football', 'olympique', 'racing', 'union', 'sochaux'], true));
        return (bool) array_intersect($w($a), $w($b));
    }

    /** Liste pour l'écran : fiches du catalogue avec leur état, filtrées. */
    public static function listing(string $status = 'recu', string $type = ''): array
    {
        $st = self::state()['items'];
        $out = [];
        foreach (self::items() as $md5 => $it) {
            $s = $st[$md5]['status'] ?? 'attente';
            if (($status !== 'tout' && $s !== $status) || ($type !== '' && ($it['type'] ?? '') !== $type)) {
                continue;
            }
            $out[] = $it + ['status' => $s, 'media' => $st[$md5]['file'] ?? null, 'fiches' => $st[$md5]['fiches'] ?? []];
        }
        usort($out, fn ($a, $b) => [(string) ($a['date'] ?? '') ?: '9999', $a['title'] ?? ''] <=> [(string) ($b['date'] ?? '') ?: '9999', $b['title'] ?? '']);
        return $out;
    }

    /** Range l'image dans la galerie des fiches choisies (légende éventuellement corrigée). */
    public static function accept(string $md5, array $ids, ?string $caption = null, ?array $user = null): int
    {
        $it = self::find($md5);
        $cur = self::state()['items'][$md5] ?? null;
        if (!$it || empty($cur['file']) || !Media::get($cur['file'])) {
            throw new \RuntimeException('Image pas encore déposée dans le back-office.');
        }
        $rel = $cur['file'];
        $caption = trim((string) ($caption ?? '')) !== '' ? trim((string) $caption) : (string) (Media::get($rel)['caption'] ?? '');
        if ($caption !== (string) (Media::get($rel)['caption'] ?? '')) {
            Media::put($rel, ['caption' => $caption], $user);
        }
        $credit = (string) (Media::get($rel)['credit'] ?? '');
        $done = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $doc = Fiches::get($id);
            if (!$doc) {
                continue;
            }
            if (!in_array($rel, array_column((array) ($doc['gallery'] ?? []), 'image'), true)) {
                $doc['gallery'][] = ['image' => $rel, 'caption' => $caption, 'credit' => $credit, 'caption_raw' => $caption . ($credit !== '' ? ' – ' . $credit : '')];
                // Photo de bonne qualité et fiche sans image : elle devient l'image principale.
                if (empty($doc['featured_image']) && ($it['type'] ?? '') === 'photo' && ($it['quality'] ?? '') === 'bonne') {
                    $doc['featured_image'] = $rel;
                }
                Fiches::save($doc, $user ?? self::AUTHOR, 'Image des archives ajoutée à la galerie');
            }
            $done[] = $id;
        }
        self::save($md5, ['status' => 'range', 'fiches' => array_values(array_unique(array_merge($cur['fiches'] ?? [], $done))), 'at' => date('c')]);
        return count($done);
    }

    public static function reject(string $md5): void
    {
        if (self::find($md5)) {
            self::save($md5, ['status' => 'ecarte', 'at' => date('c')]);
        }
    }

    /** Remet une image écartée ou rangée « à ranger » (la galerie des fiches n'est pas modifiée). */
    public static function restore(string $md5): void
    {
        $cur = self::state()['items'][$md5] ?? null;
        if ($cur) {
            self::save($md5, ['status' => !empty($cur['file']) ? 'recu' : 'attente']);
        }
    }
}
