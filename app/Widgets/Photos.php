<?php
declare(strict_types=1);

namespace App\Widgets;

use App\Core\Club;
use App\Data\Media;
use App\Services\PhotoWall;

/**
 * Les seules photos qui sortent du musée dans un widget, toujours avec leur crédit :
 * - les photos livrées avec le club (config/clubs/<club>/photos.php) sous licence libre
 *   (Creative Commons, CC0, domaine public), avec auteur, licence et source ;
 * - les photos de la médiathèque créditées que montrent déjà les murs de photos (PhotoWall :
 *   crédit renseigné, ni « DR » ni source à risque, fiche publiée, assez grande), sauf celles
 *   créditées au club lui-même (photos officielles : jamais hors du musée).
 * Toute autre image est remplacée par un visuel neutre (initiales, année).
 */
final class Photos
{
    /** @var array<string,array>|null photos des murs, par chemin */
    private static ?array $wall = null;

    /**
     * @return array{url:string,rel:string,credit:string,license:string,license_url:string,source:string,caption:string,year:int,fiche:int,w:int,h:int}|null
     */
    public static function safe(?string $rel, int $width = 800): ?array
    {
        if (!$rel) {
            return null;
        }
        if (str_starts_with($rel, Media::BUNDLED)) {
            $b = Media::bundled()[substr($rel, strlen(Media::BUNDLED))] ?? null;
            return $b && self::freeLicense((string) ($b['license'] ?? '')) ? self::fromBundled($b, $width) : null;
        }
        $p = self::wall()[$rel] ?? null;
        if (!$p || self::official((string) $p['c'])) {
            return null;
        }
        return ['url' => Context::abs(img($rel, $width)), 'rel' => $rel, 'credit' => (string) $p['c'], 'license' => '', 'license_url' => '', 'source' => '',
            'caption' => (string) $p['cap'], 'caption_en' => (string) ($p['cap_en'] ?? ''), 'year' => (int) $p['y'], 'fiche' => (int) $p['f'], 'w' => (int) $p['w'], 'h' => (int) $p['h']];
    }

    /** Toutes les photos diffusables (photo du jour). @return list<array> */
    public static function pool(int $width = 800): array
    {
        $out = [];
        foreach (Media::bundled() as $b) {
            if (self::freeLicense((string) ($b['license'] ?? '')) && Media::file((string) $b['file'])) {
                $out[] = self::fromBundled($b, $width);
            }
        }
        foreach (self::wall() as $rel => $p) {
            if (!str_starts_with((string) $rel, Media::BUNDLED) && ($s = self::safe((string) $rel, $width))) {
                $out[] = $s;
            }
        }
        return $out;
    }

    /**
     * Licence qui permet la diffusion ailleurs (avec crédit). Jamais une licence « NC » (pas
     * d'utilisation commerciale : CC BY-NC, BY-NC-SA, BY-NC-ND) : les widgets s'affichent aussi sur
     * des sites commerciaux (partenaires, régies de stade).
     */
    public static function freeLicense(string $license): bool
    {
        $l = trim($license);
        if (preg_match('/(^|[\s\-_\/])NC([\s\-_\/.]|$)|non[\s-]?commercial/i', $l)) {
            return false;
        }
        return (bool) preg_match('/^(CC[\s-]?(BY|0|ZERO)|CC0|Domaine public|Public domain|PD\b)/i', $l);
    }

    /** Crédit au nom du club lui-même (photo officielle) ? */
    public static function official(string $credit): bool
    {
        $c = mb_strtolower(trim($credit));
        if ($c === '') {
            return true;
        }
        foreach (['club', 'club_short', 'sigle'] as $k) {
            $n = mb_strtolower(trim(Club::s($k)));
            if ($n !== '' && ($c === $n || str_starts_with($c, $n . ' ') || str_contains($c, '(' . $n . ')'))) {
                return true;
            }
        }
        return false;
    }

    /** Oublie les photos gardées en mémoire (tests). */
    public static function forget(): void
    {
        self::$wall = null;
    }

    private static function wall(): array
    {
        if (self::$wall === null) {
            self::$wall = [];
            foreach (PhotoWall::photos() as $p) {
                self::$wall[(string) $p['r']] = $p;
            }
        }
        return self::$wall;
    }

    private static function fromBundled(array $b, int $width): array
    {
        $f = Media::file((string) $b['file']);
        $size = $f ? @getimagesize($f) : false;
        return ['url' => Context::abs(img((string) $b['file'], $width)), 'rel' => (string) $b['file'],
            'credit' => trim((string) ($b['author'] ?? '')) ?: 'Wikimedia Commons', 'license' => (string) ($b['license'] ?? ''),
            'license_url' => (string) ($b['license_url'] ?? ''), 'source' => (string) ($b['url'] ?? ''),
            'caption' => (string) ($b['title'] ?? ''), 'caption_en' => '', 'year' => (int) ($b['year'] ?? 0), 'fiche' => 0,
            'w' => (int) ($size[0] ?? 0), 'h' => (int) ($size[1] ?? 0)];
    }
}
