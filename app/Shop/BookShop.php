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
    public const DEFAULTS = ['active' => false, 'price' => 3900, 'price_pdf' => 1500, 'cost' => 1800, 'desc' => 'Les cent grands récits du FC Sochaux-Montbéliard, des origines à nos jours, réunis dans un livre de 21 × 27 cm illustré par les archives du musée, et composé pour vous : votre nom en couverture, votre dédicace, votre maillot floqué, votre match…'];

    public static function config(): array
    {
        $c = (array) JsonStore::read(self::CONFIG, []);
        return array_replace(self::DEFAULTS, array_intersect_key($c, self::DEFAULTS));
    }

    public static function saveConfig(array $in): array
    {
        $c = ['active' => !empty($in['active']), 'price' => max(0, (int) round((float) str_replace(',', '.', (string) ($in['price'] ?? 0)) * 100)),
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
        return $c['active'] && $c['price'] > 0;
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
        $v['format'] = isset(self::FORMATS[$v['format'] ?? '']) ? $v['format'] : 'papier';
        if (($v['nom'] ?? '') === '') {
            return ['error' => 'Indiquez le nom à imprimer sur la couverture.'];
        }
        if (isset($v['depuis']) && (!ctype_digit($v['depuis']) || (int) $v['depuis'] < 1928 || (int) $v['depuis'] > (int) date('Y'))) {
            unset($v['depuis']);
        }
        if (isset($v['couverture']) && !in_array($v['couverture'], Livre::covers(), true)) {
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

    /** Article numérique (livre PDF : ni impression ni expédition). */
    public static function digital(array $it): bool
    {
        return ($it['model'] ?? '') === self::MODEL && (($it['values']['format'] ?? '') === 'numerique' || !empty($it['digital']));
    }

    /** Adresse de téléchargement sécurisée d'un livre numérique (jeton secret de la commande). */
    public static function downloadUrl(array $o, int $n): string
    {
        return base_url() . url('/boutique/commande/' . $o['token'] . '/livre/' . ($n + 1) . '/');
    }
}
