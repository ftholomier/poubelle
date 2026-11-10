<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Media;

/**
 * Photos du terrain (tâche planifiée « pastilles ») : visage recadré pour la pastille ronde des
 * joueurs dont la photo principale n'est pas un portrait (photo d'action ou d'équipe, en largeur),
 * sans rien demander aux historiens. Les photos du joueur (photo principale, puis galerie) sont
 * montrées une à une à Gemini, qui compte les visages nets et situe le visage. Retenue seulement une
 * photo où un seul visage est net, bien visible et assez grand ; au moindre doute (plusieurs visages,
 * flou, de dos, trop petit), rien n'est mis et la photo suivante est essayée. Le visage est recadré en
 * carré (tête et haut des épaules) dans public/media/pastilles/. Aucune fiche ni aucun média n'est
 * modifié ; si les photos du joueur changent, il est repris.
 */
final class PitchPhotos
{
    public const FILE = STORAGE_PATH . '/pastilles.json';
    /** Côté de la vignette recadrée (pixels) : pastille de 52 px, nette sur un écran haute densité. */
    public const SIZE = 200;
    /** Côté minimal du carré recadré dans la photo d'origine (pixels) : en dessous, pastille floue. */
    public const MIN_SIDE = 48;
    /** Photos essayées par joueur au plus (photo principale, puis galerie). */
    private const MAX_TRIES = 5;

    /** Détecteur de visage remplacé dans les essais : fn (string $file, array $person, string $caption): ?array. */
    public static $detect = null;

    private static ?array $memo = null;

    public static function all(): array
    {
        return self::$memo ??= (array) (JsonStore::read(self::FILE, []) ?: []);
    }

    public static function forget(): void
    {
        self::$memo = null;
    }

    /** Adresse de la pastille recadrée d'une personne, ou null (pas de visage sûr, ou pas encore vue). */
    public static function url(?int $pid): ?string
    {
        $f = $pid ? (self::all()[$pid]['file'] ?? null) : null;
        return is_string($f) && $f !== '' && is_file(MEDIA_PATH . '/pastilles/' . $f) ? MEDIA_URL . '/pastilles/' . $f : null;
    }

    /** Photo principale déjà en portrait (carrée ou en hauteur) : utilisée telle quelle, sans recadrage. */
    public static function isPortrait(?string $rel): bool
    {
        if (!$rel || Index::isPlaceholderImage($rel)) {
            return false;
        }
        $m = Media::get($rel);
        $w = (int) ($m['width'] ?? 0);
        $h = (int) ($m['height'] ?? 0);
        return $w > 0 && $h >= 0.8 * $w;
    }

    /**
     * Photos à essayer pour une fiche personne : photo principale puis galerie (vraies photos de la
     * médiathèque, fichier présent), sans doublon.
     * @return list<string>
     */
    public static function candidates(array $doc): array
    {
        $out = [];
        foreach (array_merge([(string) ($doc['featured_image'] ?? '')], array_map(fn ($g) => (string) ($g['image'] ?? ''), (array) ($doc['gallery'] ?? []))) as $rel) {
            if ($rel !== '' && !Index::isPlaceholderImage($rel) && !in_array($rel, $out, true) && !str_ends_with(strtolower($rel), '.svg') && Media::file($rel)) {
                $out[] = $rel;
            }
        }
        return array_slice($out, 0, self::MAX_TRIES);
    }

    /** Empreinte des photos d'une personne (et de leurs retouches) : le joueur est repris si elle change. */
    public static function sig(array $doc): string
    {
        return substr(md5(json_encode(array_map(fn ($r) => [$r, Media::get($r)['edit'] ?? null, Media::get($r)['sha1'] ?? null], self::candidates($doc)))), 0, 12);
    }

    /**
     * Joueurs qui en ont besoin : sur au moins un terrain (composition reliée), fiche visible, photo
     * principale qui n'est pas un portrait ; les plus souvent sur un terrain d'abord.
     * @return list<int>
     */
    public static function needed(): array
    {
        $count = [];
        foreach (array_keys(Derived::part('matches')) as $mid) {
            foreach (Derived::lineupLinks((int) $mid) as $a) {
                $count[(int) $a[0]] = ($count[(int) $a[0]] ?? 0) + 1;
            }
        }
        arsort($count);
        $out = [];
        foreach (array_keys($count) as $pid) {
            $s = Index::get($pid);
            if ($s && ($s['type'] ?? '') === 'personne' && Index::visible($s) && !self::isPortrait($s['image'] ?? null)) {
                $out[] = $pid;
            }
        }
        return $out;
    }

    /**
     * Un passage : les joueurs pas encore vus (ou dont les photos ont changé), dans le temps donné
     * (secondes). Compte rendu, ou null s'il n'y avait rien à faire.
     */
    public static function run(float $budget = 60): ?string
    {
        $end = microtime(true) + $budget;
        $done = self::all();
        $found = $none = 0;
        foreach (self::needed() as $pid) {
            if (microtime(true) > $end - 15) {
                break;
            }
            $doc = Fiches::fresh($pid);
            if (!$doc) {
                continue;
            }
            $sig = self::sig($doc);
            if (($done[$pid]['sig'] ?? null) === $sig) {
                continue;
            }
            $r = self::forPerson($doc) + ['sig' => $sig, 'at' => date('c')];
            // Ancienne vignette du joueur remplacée.
            $old = (string) ($done[$pid]['file'] ?? '');
            if ($old !== '' && $old !== ($r['file'] ?? '')) {
                @unlink(MEDIA_PATH . '/pastilles/' . $old);
            }
            JsonStore::update(self::FILE, function ($d) use ($pid, $r) {
                $d = is_array($d) ? $d : [];
                $d[$pid] = $r;
                return $d;
            });
            $done[$pid] = $r;
            $r['file'] ? $found++ : $none++;
        }
        self::forget();
        return $found + $none ? $found . ' visage(s) recadré(s) pour le terrain' . ($none ? ', ' . $none . ' joueur(s) sans photo sûre' : '') : null;
    }

    /** Visage d'une personne : ['file' => vignette, 'img' => photo, 'box' => carré] ou ['file' => null, 'tried' => n]. */
    public static function forPerson(array $doc): array
    {
        $person = $doc['personne'] ?? [];
        $tried = 0;
        foreach (self::candidates($doc) as $rel) {
            $file = \App\Services\Images::derivative($rel, 800);
            if (!$file) {
                continue;
            }
            $tried++;
            $face = self::detect($file, $doc, (string) (Media::get($rel)['caption'] ?? ''));
            if (!$face) {
                continue;
            }
            $src = Media::file($rel);
            $im = $src ? \App\Services\Images::open($src, Media::get($rel)['edit'] ?? null) : null;
            if (!$im) {
                continue;
            }
            $box = self::square($face, imagesx($im), imagesy($im));
            if (!$box) {
                imagedestroy($im);
                continue;
            }
            $name = (int) $doc['id'] . '-' . substr(md5($rel . json_encode($box)), 0, 8) . '.webp';
            $ok = self::write($im, $box, MEDIA_PATH . '/pastilles/' . $name);
            imagedestroy($im);
            if ($ok) {
                return ['file' => $name, 'img' => $rel, 'box' => $box];
            }
        }
        return ['file' => null, 'tried' => $tried];
    }

    /**
     * Visage du joueur sur une photo (fractions [haut, gauche, bas, droite] de 0 à 1), ou null si ce
     * n'est pas sûr : un seul visage net, déclaré sûr, cadre cohérent.
     */
    public static function detect(string $file, array $doc, string $caption): ?array
    {
        if (is_callable(self::$detect)) {
            return (self::$detect)($file, $doc, $caption);
        }
        if (!Gemini::ready()) {
            return null;
        }
        $p = $doc['personne'] ?? [];
        $name = trim((string) ($doc['title'] ?? ''));
        $prompt = 'Photo de la fiche du joueur « ' . $name . ' » dans un musée de football.' . ($caption !== '' ? ' Légende : « ' . $caption . ' ».' : '')
            . "\nCompte les visages humains nets et bien visibles : de face ou de trois quarts, ni flous, ni de dos, ni cachés, ni coupés par le bord de la photo, ni minuscules dans une foule ou une photo d'équipe."
            . "\nS'il y a exactement un tel visage, donne son cadre box_2d [ymin, xmin, ymax, xmax] sur 1000 (le visage seul, du haut du front au menton)."
            . "\nMets sure à true seulement si ce visage est net, assez grand pour être reconnu dans une petite vignette ronde, et le seul visage net de la photo. Au moindre doute, sure à false.";
        $schema = ['type' => 'OBJECT', 'properties' => [
            'faces' => ['type' => 'INTEGER'],
            'box_2d' => ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER'], 'nullable' => true],
            'sure' => ['type' => 'BOOLEAN'],
        ], 'required' => ['faces', 'sure']];
        try {
            $r = Gemini::generate([['role' => 'user', 'text' => $prompt, 'images' => [['mime' => 'image/webp', 'data' => base64_encode((string) file_get_contents($file))]]]],
                'Tu analyses des photos d’archives pour recadrer le visage d’un joueur. Réponds seulement en JSON.',
                ['json' => true, 'schema' => $schema, 'temperature' => 0, 'max_tokens' => 200, 'for' => 'pastilles', 'ref' => (string) ($doc['id'] ?? '')]);
        } catch (\Throwable $e) {
            return null;
        }
        return self::accept(json_decode($r['text'], true));
    }

    /** Réponse du détecteur retenue : un seul visage, sûr, cadre valable ; fractions [haut, gauche, bas, droite]. */
    public static function accept(mixed $j): ?array
    {
        if (!is_array($j) || ($j['sure'] ?? false) !== true || (int) ($j['faces'] ?? 0) !== 1) {
            return null;
        }
        $b = $j['box_2d'] ?? null;
        if (!is_array($b) || count($b) !== 4) {
            return null;
        }
        [$y0, $x0, $y1, $x1] = array_map(fn ($v) => max(0, min(1000, (int) $v)) / 1000, array_values($b));
        // Cadre absurde (vide, inversé) ou visage qui occupe presque toute la photo : pas sûr.
        if ($y1 - $y0 < 0.02 || $x1 - $x0 < 0.02 || ($y1 - $y0) * ($x1 - $x0) > 0.8) {
            return null;
        }
        return [$y0, $x0, $y1, $x1];
    }

    /**
     * Carré à découper autour du visage (pixels de la photo d'origine) : tête et haut des épaules,
     * centré un peu sous le visage, gardé dans la photo ; null s'il serait trop petit (pastille floue).
     * @param array{0:float,1:float,2:float,3:float} $face fractions [haut, gauche, bas, droite]
     * @return array{x:int,y:int,side:int}|null
     */
    public static function square(array $face, int $w, int $h): ?array
    {
        [$y0, $x0, $y1, $x1] = $face;
        $fw = ($x1 - $x0) * $w;
        $fh = ($y1 - $y0) * $h;
        $side = (int) round(min(max($fw, $fh) * 1.9, $w, $h));
        if ($side < self::MIN_SIDE || max($fw, $fh) < 24) {
            return null;
        }
        $cx = ($x0 + $x1) / 2 * $w;
        $cy = ($y0 + $y1) / 2 * $h + 0.12 * $fh;
        $x = (int) round(max(0, min($w - $side, $cx - $side / 2)));
        $y = (int) round(max(0, min($h - $side, $cy - $side / 2)));
        return ['x' => $x, 'y' => $y, 'side' => $side];
    }

    /** Vignette carrée recadrée, en WebP. */
    public static function write(\GdImage $im, array $box, string $dest): bool
    {
        $out = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled($out, $im, 0, 0, $box['x'], $box['y'], self::SIZE, self::SIZE, $box['side'], $box['side']);
        if (!is_dir(dirname($dest))) {
            @mkdir(dirname($dest), 0775, true);
        }
        $ok = imagewebp($out, $dest, 82);
        imagedestroy($out);
        return $ok && is_file($dest);
    }
}
