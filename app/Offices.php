<?php
declare(strict_types=1);

namespace App;

/**
 * Catalogue des bureaux : lecture, filtres, décoration pour le rendu.
 * Source unique content/offices.json, écrite par le back-office.
 */
final class Offices
{
    public const FILE = 'offices.json';

    /** Palette de la charte, utilisée comme couleur de vignette par défaut. */
    public const PALETTE = ['#FFD100', '#12B39A', '#EDE5D5', '#FFF8EA'];

    public static function all(): array
    {
        $data = Store::read(self::FILE);
        $offices = \is_array($data['offices'] ?? null) ? $data['offices'] : [];
        $offices = array_values(array_filter($offices, 'is_array'));
        usort($offices, static function (array $a, array $b): int {
            return [(int) ($a['order'] ?? 999), (string) ($a['name'] ?? '')]
                <=> [(int) ($b['order'] ?? 999), (string) ($b['name'] ?? '')];
        });
        return $offices;
    }

    /** Bureaux visibles sur le site : ceux activés au back-office. */
    public static function published(): array
    {
        return array_values(array_filter(self::all(), static fn (array $o): bool => ($o['enabled'] ?? true) === true));
    }

    public static function find(string $id): ?array
    {
        foreach (self::all() as $office) {
            if ((string) ($office['id'] ?? '') === $id) {
                return $office;
            }
        }
        return null;
    }

    public static function findPublished(string $id): ?array
    {
        $office = self::find($id);
        return $office !== null && ($office['enabled'] ?? true) ? $office : null;
    }

    /** @param array{site?:string,status?:string,type?:string} $filters */
    public static function filter(array $offices, array $filters): array
    {
        return array_values(array_filter($offices, static function (array $o) use ($filters): bool {
            foreach (['site', 'status', 'type'] as $key) {
                $wanted = $filters[$key] ?? '';
                if ($wanted !== '' && $wanted !== 'all' && (string) ($o[$key] ?? '') !== $wanted) {
                    return false;
                }
            }
            return true;
        }));
    }

    public static function availableCount(?string $site = null): int
    {
        $offices = self::published();
        if ($site !== null) {
            $offices = self::filter($offices, ['site' => $site]);
        }
        return \count(self::filter($offices, ['status' => 'available']));
    }

    /**
     * Liste « ce qui est compris » d'une fiche : les points forts du bureau,
     * puis les prestations générales de la page, sans redite.
     *
     * Les deux sources disent souvent la même chose avec des mots différents
     * (« Internet filaire et wifi très haut débit » / « Internet très haut
     * débit »). On compare les mots utiles : si l'entrée générale est déjà
     * couverte par un point fort du bureau, on ne la répète pas.
     *
     * @param array<int,string> $features points forts du bureau (prioritaires)
     * @param array<int,string> $included prestations communes de la page
     * @return array<int,string>
     */
    public static function mergeFeatures(array $features, array $included): array
    {
        $kept = [];
        $keptWords = [];

        foreach ([...array_values($features), ...array_values($included)] as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            $words = self::significantWords($item);
            if ($words === []) {
                continue;
            }

            foreach ($keptWords as $existing) {
                if (self::covers($existing, $words) || self::covers($words, $existing)) {
                    continue 2; // déjà dit, sous une autre forme
                }
            }
            $kept[] = $item;
            $keptWords[] = $words;
        }
        return $kept;
    }

    /** true si $long reprend au moins deux tiers des mots utiles de $short. */
    private static function covers(array $long, array $short): bool
    {
        if ($short === [] || \count($long) < \count($short)) {
            return false;
        }
        $common = \count(array_intersect($short, $long));
        return $common / \count($short) >= 0.66;
    }

    /** @return array<int,string> mots porteurs de sens, sans accents ni liaisons */
    private static function significantWords(string $text): array
    {
        $skip = ['de', 'du', 'des', 'la', 'le', 'les', 'et', 'ou', 'un', 'une', 'en', 'au', 'aux', 'a',
            'the', 'and', 'or', 'of', 'in', 'to', 'with'];
        $parts = preg_split('/[^a-z0-9]+/', mb_strtolower(Text::deaccent($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique(array_filter(
            $parts,
            static fn (string $w): bool => mb_strlen($w) > 1 && !\in_array($w, $skip, true)
        )));
    }

    /** « Aucun bureau disponible », « 1 bureau disponible », « 3 bureaux disponibles ». */
    public static function availabilityLabel(int $count): string
    {
        if ($count <= 0) {
            return I18n::t('office.noneAvailable');
        }
        return I18n::t($count === 1 ? 'office.oneAvailable' : 'office.someAvailable', ['count' => $count]);
    }

    public static function minPrice(): ?int
    {
        $prices = [];
        foreach (self::published() as $office) {
            if (($office['status'] ?? '') !== 'rented' && (int) ($office['price'] ?? 0) > 0) {
                $prices[] = (int) $office['price'];
            }
        }
        return $prices === [] ? null : min($prices);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'available' => I18n::t('status.available'),
            'soon' => I18n::t('status.soon'),
            default => I18n::t('status.rented'),
        };
    }

    /** Couleur de la pastille de statut, conforme à la charte. */
    public static function statusColor(string $status): string
    {
        return match ($status) {
            'available' => '#12B39A',
            'soon' => '#FFD100',
            default => 'rgba(14,14,14,.12)',
        };
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'openspace' => I18n::t('type.openspace'),
            'meeting' => I18n::t('type.meeting'),
            default => I18n::t('type.private'),
        };
    }

    public static function siteLabel(string $site): string
    {
        $sites = Content::settings()['sites'] ?? [];
        foreach ($sites as $entry) {
            if ((string) ($entry['id'] ?? '') === $site) {
                return (string) ($entry['shortName'] ?? $entry['name'] ?? ucfirst($site));
            }
        }
        return ucfirst($site);
    }

    /**
     * Ajoute au bureau tout ce dont les vues ont besoin (libellés traduits,
     * couleurs, URL, photo de couverture) sans jamais lever d'erreur.
     */
    public static function decorate(array $office, string $lang, int $index = 0): array
    {
        $status = (string) ($office['status'] ?? 'available');
        $type = (string) ($office['type'] ?? 'private');
        $site = (string) ($office['site'] ?? 'carnot');
        $photos = array_values(array_filter((array) ($office['photos'] ?? []), static fn ($p): bool => \is_string($p) && $p !== ''));
        $price = (int) ($office['price'] ?? 0);

        return array_replace($office, [
            'id' => (string) ($office['id'] ?? ''),
            'name' => Content::i18n($office, 'name', $lang, 'Bureau'),
            'description' => Content::i18n($office, 'description', $lang),
            'area' => Content::i18n($office, 'area', $lang),
            'features' => self::featuresFor($office, $lang),
            'status' => $status,
            'statusLabel' => self::statusLabel($status),
            'statusColor' => self::statusColor($status),
            'type' => $type,
            'typeLabel' => self::typeLabel($type),
            'site' => $site,
            'siteLabel' => self::siteLabel($site),
            'price' => $price,
            'priceLabel' => $price > 0 ? I18n::price($price) : I18n::t('office.onRequest'),
            'color' => (string) ($office['color'] ?? self::PALETTE[$index % \count(self::PALETTE)]),
            'photos' => $photos,
            'cover' => $photos[0] ?? '',
            'url' => Router::url('office', $lang, ['id' => (string) ($office['id'] ?? '')]),
            // La carte ne demande rien : elle mène à la fiche, où le visiteur
            // choisit lui-même de se manifester ou d'être prévenu.
            'cta' => I18n::t('office.view'),
            'availableFrom' => (string) ($office['availableFrom'] ?? ''),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function decorateAll(array $offices, string $lang): array
    {
        $out = [];
        foreach (array_values($offices) as $i => $office) {
            $out[] = self::decorate($office, $lang, $i);
        }
        return $out;
    }

    private static function featuresFor(array $office, string $lang): array
    {
        if ($lang !== Config::DEFAULT_LANG) {
            $translated = $office['i18n'][$lang]['features'] ?? null;
            if (\is_array($translated) && $translated !== []) {
                return array_values(array_filter($translated, 'is_string'));
            }
        }
        $features = $office['features'] ?? [];
        return \is_array($features) ? array_values(array_filter($features, 'is_string')) : [];
    }

    /** Écriture du catalogue complet (back-office). */
    public static function save(array $offices, string $by): bool
    {
        $offices = array_values(array_filter($offices, 'is_array'));
        foreach ($offices as $i => $office) {
            $offices[$i]['order'] = (int) ($office['order'] ?? $i);
        }
        return Store::write(self::FILE, ['_schema' => Config::SCHEMA, 'offices' => $offices], $by);
    }

    /** Identifiant technique unique dérivé du nom. */
    public static function makeId(string $site, string $name, array $existing): string
    {
        $base = Text::slug($site . '-' . $name);
        if ($base === '') {
            $base = 'bureau';
        }
        $id = $base;
        $n = 2;
        $ids = array_map(static fn (array $o): string => (string) ($o['id'] ?? ''), $existing);
        while (\in_array($id, $ids, true)) {
            $id = $base . '-' . $n++;
        }
        return $id;
    }
}
