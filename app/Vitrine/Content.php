<?php
declare(strict_types=1);

namespace App\Vitrine;

use App\Core\Settings;
use App\Data\Index;
use App\Data\Media;

/**
 * Lecture des contenus du site de l'association, prêts à afficher : textes des pages, actions,
 * actualités, agenda (avec les Rétro-Direct du musée), équipe, partenaires, presse, documents,
 * tarifs d'adhésion.
 *
 * « À vérifier » : un contenu d'exemple (date, fait ou montant inventé faute de l'information)
 * porte la case « a_verifier ». Une actualité ou un événement à vérifier n'est jamais montré au
 * public (seulement dans l'aperçu du back-office, avec une étiquette) ; les autres contenus le
 * sont, et le tableau de bord du pavé liste tout ce qui reste à vérifier.
 */
final class Content
{
    // ------------------------------------------------------------------ pages

    /** Textes d'une page : ceux du back-office, complétés par ceux de départ. */
    public static function page(string $key): array
    {
        $def = Store::defaults('pages')[$key] ?? [];
        if (!preg_match('/^[a-z0-9-]{1,30}$/', $key) || Store::isDefault("page-$key")) {
            return $def;
        }
        return Store::get("page-$key") + $def;
    }

    /** Clés et titres des pages modifiables. @return array<string,string> */
    public static function pageKeys(): array
    {
        $out = [];
        foreach (Store::defaults('pages') as $k => $p) {
            $out[$k] = (string) ($p['_label'] ?? $p['title'] ?? $k);
        }
        return $out;
    }

    // ------------------------------------------------------------------ page d'attente

    /** Valeurs de départ des anciens réglages « Page d'attente : titre / texte » (Réglages du site). */
    private const OLD_WAITING = [
        'Le nouveau site de l’association arrive',
        '<p>Sochaux Rétro prépare le site de son association : nos actions, l’agenda, l’adhésion en ligne et toutes les façons de nous rejoindre.</p><p>En attendant, l’histoire du FC Sochaux-Montbéliard vous attend au musée en ligne.</p>',
    ];

    /**
     * Page d'attente (site fermé) : contenu du back-office, complété par celui de départ. Un titre
     * ou un texte saisi avant l'écran dédié (anciens réglages du site) est repris tant que la page
     * n'a pas été enregistrée.
     */
    public static function waiting(): array
    {
        $saved = Store::get('attente');
        $w = $saved + Store::defaults('attente');
        // Page enregistrée avant le champ « Crédit » : le crédit de départ ne vaut que pour la photo de départ.
        if (!array_key_exists('image_credit', $saved) && ($w['image'] ?? '') !== (Store::defaults('attente')['image'] ?? '')) {
            $w['image_credit'] = '';
        }
        if (Store::isDefault('attente')) {
            foreach (['title' => 'vitrine.waiting_title', 'text' => 'vitrine.waiting_text'] as $k => $key) {
                $v = trim((string) Settings::get($key, ''));
                if ($v !== '' && !in_array($v, self::OLD_WAITING, true)) {
                    $w[$k] = $v;
                }
            }
        }
        return $w;
    }

    /**
     * Crédit affiché sur la photo de la page d'attente (« Photo : … ») : celui saisi dans l'écran de
     * la page, sinon celui de la photo dans la médiathèque ; vide sans photo.
     */
    public static function waitingCredit(array $w): string
    {
        $image = (string) ($w['image'] ?? '');
        $m = $image !== '' ? Media::get($image) : null;
        if (!$m) {
            return '';
        }
        return self::credit(trim((string) ($w['image_credit'] ?? '')) ?: (string) ($m['credit'] ?? ''));
    }

    /** Crédit sans « © », « Crédit photo : »… de tête (« Photo : » est ajouté à l'affichage). */
    public static function credit(string $raw): string
    {
        $s = trim((string) preg_replace('/\s+/u', ' ', $raw));
        do {
            $before = $s;
            $s = trim((string) preg_replace('/^(?:(?:cr[ée]dits?(?:\s+photos?)?|photos?)(?!\p{L})\s*[:.\-–]?|©|\(c\))\s*/iu', '', $s));
        } while ($s !== $before && $s !== '');
        return $s;
    }

    // ------------------------------------------------------------------ actions

    /** Les actions de l'association (pages « Nos actions »), dans l'ordre. */
    public static function actions(): array
    {
        return array_values(array_filter(Store::get('actions'), fn ($a) => !empty($a['slug']) && empty($a['hidden'])));
    }

    public static function action(string $slug): ?array
    {
        foreach (self::actions() as $a) {
            if ($a['slug'] === $slug) {
                return $a;
            }
        }
        return null;
    }

    // ------------------------------------------------------------------ actualités

    /**
     * Actualités publiées, de la plus récente à la plus ancienne. Le public ne voit pas les
     * brouillons, les actualités datées dans le futur ni celles « à vérifier ».
     */
    public static function news(): array
    {
        $today = date('Y-m-d');
        $out = [];
        foreach (Store::get('actualites') as $n) {
            if (empty($n['slug']) || empty($n['title'])) {
                continue;
            }
            if (!Site::$preview && (!empty($n['draft']) || !empty($n['a_verifier']) || (string) ($n['date'] ?? '') > $today)) {
                continue;
            }
            $out[] = $n;
        }
        usort($out, fn ($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
        return $out;
    }

    public static function newsItem(string $slug): ?array
    {
        foreach (self::news() as $n) {
            if ($n['slug'] === $slug) {
                return $n;
            }
        }
        return null;
    }

    // ------------------------------------------------------------------ agenda

    /**
     * Événements, du plus proche au plus lointain (à venir) ou du plus récent au plus ancien
     * (passés) : ceux de l'association, les Rétro-Direct programmés au musée et le centenaire.
     * Chaque événement : slug, title, start (Y-m-d H:i), end, place, excerpt, body, image, href
     * (lien externe : Rétro-Direct), kind (asso, retro, centenaire).
     */
    public static function events(bool $past = false): array
    {
        $now = date('Y-m-d H:i');
        $all = [];
        foreach (Store::get('agenda') as $e) {
            if (empty($e['slug']) || empty($e['title']) || !preg_match('/^\d{4}-\d{2}-\d{2}/', (string) ($e['start'] ?? ''))) {
                continue;
            }
            if (!Site::$preview && (!empty($e['draft']) || !empty($e['a_verifier']))) {
                continue;
            }
            $e['kind'] = 'asso';
            $e['start'] = self::dt((string) $e['start']);
            $e['end'] = !empty($e['end']) ? self::dt((string) $e['end']) : '';
            $all[] = $e;
        }
        if (Settings::get('vitrine.agenda_retro', true)) {
            foreach (\App\Services\RetroDirect::program(null, false) as $r) {
                $m = $r['s']['m'] ?? [];
                $year = substr((string) ($m['date'] ?? ''), 0, 4);
                $all[] = [
                    'slug' => 'retro-direct-' . $r['id'] . '-' . $r['date'],
                    'title' => 'Rétro-Direct : ' . trim(($m['home'] ?? '') . ' – ' . ($m['away'] ?? '')) . ($year !== '' ? " ($year)" : ''),
                    'start' => date('Y-m-d H:i', (int) $r['start']),
                    'end' => date('Y-m-d H:i', (int) $r['end']),
                    'place' => 'En ligne, sur le musée',
                    'excerpt' => 'Le match rejoué minute par minute sur le musée en ligne, le jour anniversaire : commentaires, buts et réactions en direct.',
                    'image' => $r['s']['image'] ?? null,
                    'href' => Host::museum(parse_url(\App\Services\RetroDirect::url($r['s']), PHP_URL_PATH) ?: '/interactif/retro-direct/'),
                    'kind' => 'retro',
                ];
            }
        }
        $c = (string) Settings::get('home.centenary_date', '2028-06-14');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $c)) {
            $all[] = [
                'slug' => 'centenaire-du-fcsm',
                'title' => 'Les 100 ans du FC Sochaux-Montbéliard',
                'start' => "$c 00:00",
                'end' => '',
                'allday' => true,
                'place' => 'Sochaux et partout où bat un cœur jaune et bleu',
                'excerpt' => 'Le club fête son centenaire. L’association prépare ce rendez-vous depuis des années : moments d’histoire, Onze de légende, archives sauvées.',
                'image' => '2025/03/que-le-jaune-soit-or-et-que-le-bleu-soit-roi-1652281058.jpg',
                'href' => Host::url('/nos-actions/centenaire-2028/'),
                'kind' => 'centenaire',
            ];
        }
        $list = array_values(array_filter($all, function ($e) use ($past, $now) {
            $end = $e['end'] !== '' ? $e['end'] : (!empty($e['allday']) ? substr($e['start'], 0, 10) . ' 23:59' : $e['start']);
            return $past ? $end < $now : $end >= $now;
        }));
        usort($list, fn ($a, $b) => $past ? strcmp($b['start'], $a['start']) : strcmp($a['start'], $b['start']));
        return $list;
    }

    public static function event(string $slug): ?array
    {
        foreach (array_merge(self::events(), self::events(true)) as $e) {
            if ($e['slug'] === $slug && $e['kind'] === 'asso') {
                return $e;
            }
        }
        return null;
    }

    /** « 2026-11-14T15:00 » ou « 2026-11-14 15:00 » → « 2026-11-14 15:00 ». */
    private static function dt(string $v): string
    {
        $v = str_replace('T', ' ', trim($v));
        return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $v) ? substr($v, 0, 16) : substr($v, 0, 10) . ' 00:00';
    }

    // ------------------------------------------------------------------ association

    /** Membres de l'équipe affichés : ceux dont le nom est renseigné. */
    public static function team(): array
    {
        $t = Store::get('equipe');
        return array_values(array_filter($t['members'] ?? [], fn ($m) => trim((string) ($m['name'] ?? '')) !== '' && empty($m['hidden'])));
    }

    /** Pôles de bénévoles (page L'équipe et page Bénévolat). */
    public static function poles(): array
    {
        return array_values(array_filter(Store::get('equipe')['poles'] ?? [], fn ($p) => !empty($p['title'])));
    }

    public static function partners(): array
    {
        return array_values(array_filter(Store::get('partenaires'), fn ($p) => trim((string) ($p['name'] ?? '')) !== '' && empty($p['hidden'])));
    }

    /** Revue de presse (articles parus), du plus récent au plus ancien. */
    public static function press(): array
    {
        $list = array_values(array_filter(Store::get('presse'), fn ($p) => trim((string) ($p['title'] ?? '')) !== '' && empty($p['hidden'])));
        usort($list, fn ($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
        return $list;
    }

    /** Documents publics (statuts, comptes rendus…) dont le fichier est présent. */
    public static function documents(): array
    {
        return array_values(array_filter(Store::get('documents'), fn ($d) => !empty($d['title']) && empty($d['hidden']) && Documents::path((string) ($d['file'] ?? '')) !== null));
    }

    /** Tarifs d'adhésion (montants en euros). */
    public static function tariffs(): array
    {
        return array_values(array_filter(Store::get('tarifs')['items'] ?? [], fn ($t) => !empty($t['key']) && (int) ($t['amount'] ?? 0) > 0 && empty($t['hidden'])));
    }

    public static function tariff(string $key): ?array
    {
        foreach (self::tariffs() as $t) {
            if ($t['key'] === $key) {
                return $t;
            }
        }
        return null;
    }

    // ------------------------------------------------------------------ chiffres

    /** Chiffres clés : communauté, vidéos, musée (calculés), centenaire. */
    public static function figures(): array
    {
        return [
            ['n' => self::num((string) Settings::get('home.counter_community', '11000+')), 'label' => 'passionnés', 'text' => 'dans la communauté Sochaux Rétro, tous réseaux confondus'],
            ['n' => self::num((string) Settings::get('home.counter_videos', '1400+')), 'label' => 'vidéos', 'text' => 'sur notre chaîne YouTube'],
            ['n' => number_format(count(Index::published('match')), 0, ',', ' '), 'label' => 'matchs', 'text' => 'racontés au musée en ligne, depuis 1928'],
            ['n' => number_format(\App\Front\Site::count(\App\Front\Site::C_JOUEURS), 0, ',', ' '), 'label' => 'joueurs', 'text' => 'avec leur fiche au musée'],
        ];
    }

    /** « 11000+ » → « 11 000+ » (compteur saisi dans les réglages). */
    private static function num(string $s): string
    {
        return preg_match('/^\s*(\d{4,})\s*(\+?)\s*$/', $s, $m) ? number_format((int) $m[1], 0, ',', ' ') . $m[2] : trim($s);
    }

    /** Une image de la médiathèque existe-t-elle ? */
    public static function hasImage(?string $rel): bool
    {
        return is_string($rel) && $rel !== '' && Media::get($rel) !== null;
    }
}
