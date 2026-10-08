<?php
declare(strict_types=1);

namespace App\Shop;

use App\Core\JsonStore;
use App\Pdf\Livre;

/**
 * Boutique : le livre « 100 récits du Lion », personnalisé par le client (App\Pdf\Livre). Article à part
 * dans le panier (modèle « livre ») : choix contrôlés ici, numéro d'exemplaire attribué au paiement,
 * fichier d'impression composé avec les autres articles (Orders::buildPdfs). Réglages (mise en vente,
 * prix, coût de fabrication) : Contenus › Livre des récits.
 */
final class BookShop
{
    public const MODEL = 'livre';
    public const NAME = 'Livre « 100 récits du Lion »';
    public const SUPPORT = 'Livre 21 × 27 cm, personnalisé';
    private const CONFIG = STORAGE_PATH . '/livres/boutique.json';
    private const NUMBERS = STORAGE_PATH . '/livres/numeros.json';
    public const UPLOADS = STORAGE_PATH . '/livres/envois';
    public const DEFAULTS = ['active' => false, 'paper' => false, 'price' => 3900, 'price_pdf' => 1500, 'cost' => 1800, 'desc' => 'Les cent grands récits du FC Sochaux-Montbéliard, des origines à nos jours, réunis dans un livre de 21 × 27 cm illustré par les archives du musée, et composé pour vous : votre nom en couverture, votre dédicace, votre maillot floqué, votre match…'];

    public static function config(): array
    {
        $c = (array) JsonStore::read(self::CONFIG, []);
        return array_replace(self::DEFAULTS, array_intersect_key($c, self::DEFAULTS));
    }

    public static function saveConfig(array $in): array
    {
        $c = ['active' => !empty($in['active']), 'paper' => !empty($in['paper']), 'price' => max(0, (int) round((float) str_replace(',', '.', (string) ($in['price'] ?? 0)) * 100)),
            'price_pdf' => max(0, (int) round((float) str_replace(',', '.', (string) ($in['price_pdf'] ?? 0)) * 100)),
            'cost' => max(0, (int) round((float) str_replace(',', '.', (string) ($in['cost'] ?? 0)) * 100)), 'desc' => mb_substr(trim((string) ($in['desc'] ?? '')), 0, 800)];
        @mkdir(dirname(self::CONFIG), 0775, true);
        JsonStore::write(self::CONFIG, $c);
        return $c;
    }

    /** En vente (activé, prix fixé, au moins un récit publié). */
    public static function sellable(): bool
    {
        $c = self::config();
        // Livre imprimé désactivé par défaut (pas encore d'imprimeur) : le PDF seul suffit.
        return $c['active'] && (($c['paper'] && $c['price'] > 0) || $c['price_pdf'] > 0);
    }

    /** Fichier envoyé par le client (photo, maillot 3D) : jeton => chemin, ou null. */
    public static function upload(string $token): ?string
    {
        if (!preg_match('/^[a-f0-9]{24}\.(jpg|png)$/', $token)) {
            return null;
        }
        $f = self::UPLOADS . '/' . $token;
        return is_file($f) ? $f : null;
    }

    /**
     * Fichier envoyé depuis la page du livre : photo du lecteur (JPEG ou PNG, assez définie pour être
     * imprimée) ou photo 3D du maillot (PNG). @return array{token?:string,error?:string}
     */
    public static function receive(string $tmp, string $kind): array
    {
        $info = @getimagesize($tmp);
        if (!$info || !in_array($info[2], $kind === 'photo' ? [IMAGETYPE_JPEG, IMAGETYPE_PNG] : [IMAGETYPE_PNG], true)) {
            return ['error' => $kind === 'photo' ? 'Votre photo doit être au format JPEG ou PNG.' : 'Image du maillot illisible.'];
        }
        if ($kind === 'photo' && !Livre::photoFrame($tmp)) {
            return ['error' => 'Votre photo est trop petite pour être imprimée nettement (' . $info[0] . ' × ' . $info[1] . ' pixels) : il en faut au moins 670 de large.'];
        }
        if ($kind !== 'photo' && $info[0] < 800) {
            return ['error' => 'Image du maillot trop petite.'];
        }
        @mkdir(self::UPLOADS, 0775, true);
        self::purge();
        $token = bin2hex(random_bytes(12)) . ($info[2] === IMAGETYPE_PNG ? '.png' : '.jpg');
        if (!@move_uploaded_file($tmp, self::UPLOADS . '/' . $token) && !@rename($tmp, self::UPLOADS . '/' . $token)) {
            return ['error' => 'Envoi impossible, réessayez.'];
        }
        return ['token' => $token];
    }

    /** Envois de plus de 60 jours jamais commandés : effacés. */
    private static function purge(): void
    {
        $used = [];
        foreach (Orders::all() as $o) {
            foreach ($o['items'] as $it) {
                foreach (['photo', 'maillot_image', 'maillot_devant'] as $k) {
                    $used[(string) ($it['values'][$k] ?? '')] = true;
                }
            }
        }
        foreach (glob(self::UPLOADS . '/*') ?: [] as $f) {
            if (filemtime($f) < time() - 60 * 86400 && !isset($used[basename($f)])) {
                @unlink($f);
            }
        }
    }

    /** Champs du formulaire : clé => longueur maximale. */
    public const FORMATS = ['papier' => 'Livre imprimé', 'numerique' => 'Livre numérique (PDF)'];

    private const FIELDS = ['format' => 10, 'nom' => 60, 'dedicace' => 600, 'signature' => 80, 'depuis' => 4, 'couverture' => 200, 'naissance' => 10, 'naissance_titre' => 50,
        'carnet' => 16, 'photo' => 40, 'photo_legende' => 120, 'maillot_nom' => 14, 'maillot_numero' => 2, 'maillot_style' => 20, 'maillot_image' => 40, 'maillot_devant' => 40,
        'match' => 12, 'joueurs' => 40];

    /** Choix du client nettoyés et contrôlés. @return array{values?:array,error?:string} */
    public static function clean(array $in): array
    {
        $v = [];
        foreach (self::FIELDS as $k => $max) {
            $s = trim((string) preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', mb_substr((string) ($in[$k] ?? ''), 0, $max)));
            if ($s !== '') {
                $v[$k] = $s;
            }
        }
        $paper = self::config()['paper'];
        $v['format'] = isset(self::FORMATS[$v['format'] ?? '']) ? $v['format'] : ($paper ? 'papier' : 'numerique');
        if ($v['format'] === 'papier' && !$paper) {
            $v['format'] = 'numerique';
        }
        if (($v['nom'] ?? '') === '') {
            return ['error' => 'Indiquez le nom à imprimer sur la couverture.'];
        }
        if (isset($v['depuis']) && (!ctype_digit($v['depuis']) || (int) $v['depuis'] < 1928 || (int) $v['depuis'] > (int) date('Y'))) {
            unset($v['depuis']);
        }
        if (isset($v['couverture']) && !in_array($v['couverture'], Livre::offeredCovers(), true)) {
            unset($v['couverture']);
        }
        if (isset($v['naissance']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v['naissance'])) {
            unset($v['naissance']);
        }
        if (isset($v['carnet']) && !\App\Services\Carnet::get($v['carnet'])) {
            unset($v['carnet']);
        }
        foreach (['photo', 'maillot_image', 'maillot_devant'] as $k) {
            if (isset($v[$k]) && !self::upload($v[$k])) {
                unset($v[$k]);
            }
        }
        if (isset($v['maillot_style']) && !isset(Livre::jerseysValid()[$v['maillot_style']])) {
            unset($v['maillot_style'], $v['maillot_image'], $v['maillot_devant']);
        }
        if (isset($v['maillot_numero'])) {
            $v['maillot_numero'] = preg_replace('/\D/', '', $v['maillot_numero']);
        }
        if ((($v['maillot_nom'] ?? '') !== '' || ($v['maillot_numero'] ?? '') !== '') && !isset($v['maillot_style'])) {
            unset($v['maillot_nom'], $v['maillot_numero'], $v['maillot_image'], $v['maillot_devant']);
        }
        if (isset($v['match']) && !self::published((int) $v['match'], 'match')) {
            unset($v['match']);
        }
        if (isset($v['joueurs'])) {
            $ids = array_slice(array_values(array_filter(array_map('intval', preg_split('/\D+/', $v['joueurs']) ?: []), fn ($id) => self::published($id, 'personne'))), 0, 3);
            $ids ? $v['joueurs'] = implode(',', $ids) : $v['joueurs'] = null;
            $v = array_filter($v, fn ($x) => $x !== null);
        }
        return ['values' => $v];
    }

    private static function published(int $id, string $type): bool
    {
        $d = $id > 0 ? \App\Data\Fiches::get($id) : null;
        return $d && ($d['type'] ?? '') === $type && ($d['status'] ?? '') === 'publie';
    }

    /** Ligne de panier du livre (même forme que les autres articles). @return array{item?:array,error?:string} */
    public static function line(array $in): array
    {
        if (!self::sellable()) {
            return ['error' => 'Le livre n’est pas en vente pour le moment.'];
        }
        $r = self::clean((array) ($in['values'] ?? []));
        if (isset($r['error'])) {
            return $r;
        }
        $c = self::config();
        $digital = $r['values']['format'] === 'numerique';
        if ($digital && $c['price_pdf'] <= 0) {
            return ['error' => 'Le livre numérique n’est pas proposé pour le moment.'];
        }
        $qty = $digital ? 1 : max(1, min(5, (int) ($in['qty'] ?? 1)));
        $unit = $digital ? $c['price_pdf'] : $c['price'];
        return ['item' => [
            'model' => self::MODEL, 'name' => self::NAME, 'support' => $digital ? 'Livre numérique (PDF), à télécharger' : self::SUPPORT, 'size' => '', 'color' => '', 'color_name' => '',
            'opts' => [], 'values' => $r['values'], 'qty' => $qty, 'unit' => $unit, 'total' => $unit * $qty, 'cost' => $digital ? 0 : $c['cost'], 'rate' => 0.0, 'digital' => $digital,
        ]];
    }

    /** Résumé lisible des choix. */
    public static function describe(array $it): string
    {
        $v = $it['values'];
        $p = [self::FORMATS[$v['format'] ?? 'papier'] ?? '', 'Pour ' . ($v['nom'] ?? '')];
        if (!empty($v['depuis'])) {
            $p[] = 'supporter depuis ' . $v['depuis'];
        }
        if (!empty($v['dedicace'])) {
            $p[] = 'dédicace';
        }
        if (!empty($v['couverture'])) {
            $p[] = 'photo de couverture choisie';
        }
        if (!empty($v['maillot_style'])) {
            $p[] = 'maillot ' . (Livre::JERSEYS[$v['maillot_style']]['era'] ?: 'extérieur') . ' floqué ' . trim(($v['maillot_nom'] ?? '') . ' ' . ($v['maillot_numero'] ?? ''));
        }
        if (!empty($v['naissance'])) {
            $p[] = 'le jour du ' . date_fr($v['naissance']);
        }
        if (!empty($v['match'])) {
            $p[] = 'Mon match';
        }
        if (!empty($v['joueurs'])) {
            $p[] = 'Mes joueurs';
        }
        if (!empty($v['carnet'])) {
            $p[] = 'tampons « J’y étais » du carnet';
        }
        if (!empty($v['photo'])) {
            $p[] = 'sa photo';
        }
        if (!empty($v['_numero'])) {
            $p[] = 'exemplaire n° ' . $v['_numero'];
        }
        return implode(' · ', $p);
    }

    /** Numéro d'exemplaire, attribué une fois pour toutes à un article payé (0001, 0002…). */
    public static function number(string $ref): string
    {
        $out = '';
        JsonStore::update(self::NUMBERS, function ($d) use ($ref, &$out) {
            $d = is_array($d) ? $d : ['last' => 0, 'refs' => []];
            if (!isset($d['refs'][$ref])) {
                $d['refs'][$ref] = ++$d['last'];
            }
            $out = sprintf('%04d', $d['refs'][$ref]);
            return $d;
        }, ['last' => 0, 'refs' => []]);
        return $out;
    }

    /** Options du moteur du livre pour un article commandé. */
    public static function options(array $v, string $numero): array
    {
        $o = array_intersect_key($v, array_flip(['nom', 'dedicace', 'signature', 'couverture', 'naissance', 'naissance_titre', 'carnet', 'photo_legende', 'maillot_nom', 'maillot_numero', 'maillot_style', 'match']));
        $o['numero'] = $numero;
        // Livre numérique : PDF pour l'écran (150 dpi, 15 à 20 Mo) ; le fichier d'impression reste en 300 dpi.
        $o['ecran'] = ($v['format'] ?? '') === 'numerique';
        $o['depuis'] = (int) ($v['depuis'] ?? 0);
        $o['joueurs'] = array_map('intval', array_filter(explode(',', (string) ($v['joueurs'] ?? ''))));
        foreach (['photo', 'maillot_image', 'maillot_devant'] as $k) {
            if (($f = self::upload((string) ($v[$k] ?? ''))) !== null) {
                $o[$k] = $f;
            }
        }
        return $o;
    }

    /** Fichier d'impression d'un exemplaire (récits publiés seulement). */
    public static function pdf(array $v, string $numero): string
    {
        @set_time_limit(600);
        @ini_set('memory_limit', '1024M');
        return (new Livre(self::options($v, $numero)))->build();
    }

    /** Aperçu de la couverture (SVG, pour le panier, le suivi et le back-office). */
    public static function coverSvg(array $v): string
    {
        $nom = e(mb_strtoupper((string) ($v['nom'] ?? 'Votre nom')));
        $img = '';
        if (!empty($v['couverture'])) {
            $img = '<image href="' . e(url('/media/480/' . $v['couverture'] . '.webp')) . '" x="0" y="0" width="210" height="171" preserveAspectRatio="xMidYMid slice"/><rect x="0" y="120" width="210" height="51" fill="url(#cv)"/>';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 210 270" role="img" aria-label="Couverture du livre"><defs><linearGradient id="cv" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0E1F4D" stop-opacity="0"/><stop offset="1" stop-color="#0E1F4D"/></linearGradient></defs>'
            . '<rect width="210" height="270" fill="#0E1F4D"/>' . $img
            . '<rect x="9" y="9" width="192" height="252" fill="none" stroke="#F6C400" stroke-width=".6"/>'
            . '<text x="16" y="203" font-family="Big Shoulders Display,Impact,sans-serif" font-weight="900" font-size="74" fill="#F6C400">100</text>'
            . '<text x="105" y="180" font-family="Big Shoulders Display,Impact,sans-serif" font-weight="900" font-size="20" fill="#fff">RÉCITS</text>'
            . '<text x="105" y="200" font-family="Big Shoulders Display,Impact,sans-serif" font-weight="900" font-size="20" fill="#fff">DU LION</text>'
            . '<polygon points="0,218 210,208 210,219 0,229" fill="#F6C400"/>'
            . '<rect x="16" y="236" width="' . min(180, 20 + mb_strlen($nom) * 6.4) . '" height="18" fill="#F3EDDF"/><rect x="16" y="236" width="2" height="18" fill="#F6C400"/>'
            . '<text x="22" y="249" font-family="Big Shoulders Display,Impact,sans-serif" font-weight="900" font-size="10" fill="#0E1F4D">' . $nom . '</text></svg>';
    }

    /** Vignette d'un maillot du livre (SVG à plat, devant ; couleurs, col, motif d'après Livre::JERSEYS). */
    public static function jerseySvg(array $j, string $uid = 'j'): string
    {
        $c = fn (?string $h, string $d = 'F6C400') => '#' . preg_replace('/[^0-9A-Fa-f]/', '', $h ?: $d);
        $body = $c($j['body'] ?? null);
        $shade = $c($j['shade'] ?? null, 'D9A800');
        $trim = $c($j['trim'] ?? null, '0E1F4D');
        $sleeve = $c($j['sleeve'] ?? null, ltrim($body, '#'));
        $torso = 'M26 10 L39 5 Q50 15 61 5 L74 10 L76 36 L78 104 L22 104 L24 36 Z';
        $sl = 'M26 10 L2 30 L12 46 L24 37 Z';
        $sr = 'M74 10 L98 30 L88 46 L76 37 Z';
        $id = 'jc' . preg_replace('/[^a-z0-9]/i', '', $uid);
        $in = '';
        foreach ($j['layers'] ?? [] as $l) {
            $lc = $c($l['color'] ?? null, ltrim($trim, '#'));
            switch ($l['t'] ?? '') {
                case 'checker':
                    $y0 = 6 + 98 * ($l['y0'] ?? .2);
                    $h = 98 * (($l['y1'] ?? .4) - ($l['y0'] ?? .2)) / 2;
                    $n = max(4, (int) ($l['n'] ?? 12));
                    $w = 56 / $n;
                    for ($r = 0; $r < 2; $r++) {
                        for ($k = $r % 2; $k < $n; $k += 2) {
                            $in .= '<rect x="' . round(22 + $k * $w, 2) . '" y="' . round($y0 + $r * $h, 2) . '" width="' . round($w, 2) . '" height="' . round($h, 2) . '" fill="' . $lc . '"/>';
                        }
                    }
                    break;
                case 'hstripes':
                    $n = max(4, (int) ($l['n'] ?? 20));
                    $step = 98 / $n;
                    for ($k = 0; $k < $n; $k++) {
                        $in .= '<rect x="0" y="' . round(6 + $k * $step, 2) . '" width="100" height="' . round(max(.5, $step * ($l['w'] ?? .1) * 3), 2) . '" fill="' . $lc . '"/>';
                    }
                    break;
                case 'chevrons':
                    for ($k = 0; $k < min(5, (int) ($l['n'] ?? 5)); $k++) {
                        $y = 34 + $k * 7;
                        $in .= '<path d="M30 ' . $y . ' L50 ' . ($y + 9) . ' L70 ' . $y . '" fill="none" stroke="' . $lc . '" stroke-width="2.2"/>';
                    }
                    break;
                case 'side':
                    $w = max(4, (0.5 - ($l['x'] ?? .38)) * 70);
                    $in .= '<rect x="0" y="0" width="' . (22 + $w) . '" height="110" fill="' . $lc . '"/><rect x="' . (78 - $w) . '" y="0" width="40" height="110" fill="' . $lc . '"/>';
                    break;
                case 'yoke':
                    $in .= '<path d="M0 0 H100 V24 Q50 32 0 24 Z" fill="' . $lc . '"/>';
                    break;
            }
        }
        $raglan = '';
        $yoke = false;
        foreach ($j['layers'] ?? [] as $l) {
            if (($l['t'] ?? '') === 'raglan') {
                $lc = $c($l['color'] ?? null, ltrim($body, '#'));
                $raglan = '<path d="M27 11 L5 31 M73 11 L95 31" stroke="' . $lc . '" stroke-width="2.6"/>';
            }
            if (($l['t'] ?? '') === 'yoke' || ($l['t'] ?? '') === 'side') {
                $yoke = $yoke ?: $c($l['color'] ?? null);
            }
        }
        $sleeveFill = ($j['sleeve'] ?? null) ? $sleeve : (($j['layers'][0]['t'] ?? '') === 'yoke' ? $yoke : $body);
        $collar = match ($j['collar'] ?? 'crew') {
            'v' => '<path d="M39 5 L50 20 L61 5" fill="none" stroke="' . $trim . '" stroke-width="3" stroke-linejoin="round"/>',
            'polo' => '<path d="M39 5 L50 15 L61 5" fill="none" stroke="' . $trim . '" stroke-width="2"/><path d="M39 5 L33 15 L47 16 Z M61 5 L67 15 L53 16 Z" fill="' . $trim . '"/><path d="M50 15 V27" stroke="' . $trim . '" stroke-width="1.6"/><circle cx="50" cy="20" r=".9" fill="' . $body . '"/><circle cx="50" cy="24" r=".9" fill="' . $body . '"/>',
            'lace' => '<path d="M39 5 Q50 13 61 5" fill="none" stroke="' . $trim . '" stroke-width="2.6"/><path d="M48 9 V25 M52 9 V25" stroke="' . $trim . '" stroke-width="1"/><path d="M48 12 L52 15 M52 12 L48 15 M48 18 L52 21 M52 18 L48 21" stroke="' . $trim . '" stroke-width=".8"/>',
            default => '<path d="M39 5 Q50 15 61 5" fill="none" stroke="' . $trim . '" stroke-width="3.2"/>',
        };
        $cuffs = !empty($j['cuffs']) ? '<path d="M3.6 32.6 L13.6 44.6 M96.4 32.6 L86.4 44.6" stroke="' . $trim . '" stroke-width="3.2"/>' : '';
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 108" role="img" aria-label="' . e((string) ($j['label'] ?? 'Maillot')) . '"><defs><clipPath id="' . $id . '"><path d="' . $torso . '"/></clipPath>'
            . '<linearGradient id="' . $id . 'g" x1="0" x2="1"><stop offset="0" stop-color="#000" stop-opacity=".14"/><stop offset=".22" stop-color="#000" stop-opacity="0"/><stop offset=".78" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".14"/></linearGradient></defs>'
            . '<path d="' . $sl . '" fill="' . $sleeveFill . '" stroke="' . $shade . '" stroke-width=".8"/><path d="' . $sr . '" fill="' . $sleeveFill . '" stroke="' . $shade . '" stroke-width=".8"/>' . $raglan . $cuffs
            . '<path d="' . $torso . '" fill="' . $body . '"/><g clip-path="url(#' . $id . ')">' . $in . '<rect width="100" height="110" fill="url(#' . $id . 'g)"/></g>'
            . '<path d="' . $torso . '" fill="none" stroke="' . $shade . '" stroke-width=".8"/>' . $collar . '</svg>';
    }

    /** Article numérique (livre PDF : ni impression ni expédition). */
    public static function digital(array $it): bool
    {
        return ($it['model'] ?? '') === self::MODEL && (($it['values']['format'] ?? '') === 'numerique' || !empty($it['digital']));
    }

    /** Adresse de téléchargement sécurisée d'un livre numérique (jeton secret de la commande). */
    /** Téléchargements permis par livre numérique ; au-delà, le client écrit au musée. */
    public const MAX_DOWNLOADS = 5;

    public static function downloadUrl(array $o, int $n): string
    {
        return base_url() . url('/boutique/commande/' . $o['token'] . '/livre/' . ($n + 1) . '/');
    }
}
