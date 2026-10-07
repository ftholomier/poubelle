<?php
// Photos du bien : prise de vue depuis l'appli, retouche automatique (niveaux, contraste, netteté), détection des
// photos floues, reconnaissance de la pièce par l'IA, ordre conseillé pour l'annonce, home staging virtuel.
// Toute image modifiée par l'IA porte la mention « Aménagement virtuel » (obligatoire pour ne pas tromper).

const PHOTO_MAX = 2400;
const ORDRE_PIECES = ['Façade', 'Extérieur', 'Jardin', 'Séjour', 'Salon', 'Cuisine', 'Salle à manger', 'Chambre', 'Salle de bain', 'Bureau', 'Vue', 'Terrasse', 'Garage', 'Autre'];

function photos_dir(array $agent, string $id): string
{
    return visit_dir($agent, $id) . '/photos';
}

function charger_image(string $chemin)
{
    $info = @getimagesize($chemin);
    return match ($info[2] ?? 0) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($chemin),
        IMAGETYPE_PNG => @imagecreatefrompng($chemin),
        IMAGETYPE_WEBP => @imagecreatefromwebp($chemin),
        default => false,
    };
}

/** Redresse selon l'orientation EXIF (photos de téléphone) et réduit à 2400 px. */
function normaliser_image($img, string $source)
{
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($source);
        $img = match ((int) ($exif['Orientation'] ?? 1)) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
    }
    $w = imagesx($img); $h = imagesy($img);
    if (max($w, $h) > PHOTO_MAX) {
        $r = PHOTO_MAX / max($w, $h);
        $img = imagescale($img, (int) ($w * $r), (int) ($h * $r), IMG_BICUBIC);
    }
    return $img;
}

/** Netteté estimée (variance du laplacien sur une version réduite) : en dessous de ~60, la photo est floue. */
function nettete($img): float
{
    $p = imagescale($img, 320, -1);
    imagefilter($p, IMG_FILTER_GRAYSCALE);
    $w = imagesx($p); $h = imagesy($p);
    $vals = [];
    for ($y = 1; $y < $h - 1; $y += 2) {
        for ($x = 1; $x < $w - 1; $x += 2) {
            $g = fn ($x, $y) => imagecolorat($p, $x, $y) & 0xFF;
            $vals[] = 4 * $g($x, $y) - $g($x - 1, $y) - $g($x + 1, $y) - $g($x, $y - 1) - $g($x, $y + 1);
        }
    }
    $n = count($vals) ?: 1;
    $moy = array_sum($vals) / $n;
    return round(array_sum(array_map(fn ($v) => ($v - $moy) ** 2, $vals)) / $n, 1);
}

/** Retouche automatique : étirement des niveaux, un peu de contraste et de saturation, netteté douce. */
function retoucher($img)
{
    $w = imagesx($img); $h = imagesy($img);
    // histogramme de luminance sur un échantillon
    $hist = array_fill(0, 256, 0);
    $pas = max(1, (int) sqrt($w * $h / 40000));
    for ($y = 0; $y < $h; $y += $pas) for ($x = 0; $x < $w; $x += $pas) {
        $c = imagecolorat($img, $x, $y);
        $hist[(int) (0.299 * (($c >> 16) & 0xFF) + 0.587 * (($c >> 8) & 0xFF) + 0.114 * ($c & 0xFF))]++;
    }
    $total = array_sum($hist);
    $cumul = 0; $bas = 0; $haut = 255;
    foreach ($hist as $i => $n) { $cumul += $n; if ($cumul < $total * 0.01) $bas = $i; if ($cumul < $total * 0.99) $haut = $i; }
    $haut = max($haut, $bas + 40);
    $gain = 255 / ($haut - $bas);
    $out = imagecreatetruecolor($w, $h);
    imagecopy($out, $img, 0, 0, 0, 0, $w, $h);
    if ($gain > 1.03) {
        imagefilter($out, IMG_FILTER_BRIGHTNESS, (int) -($bas * $gain * 0.9));
        imagefilter($out, IMG_FILTER_CONTRAST, (int) -min(25, ($gain - 1) * 40));
    }
    // éclaircit légèrement les photos sombres (intérieurs)
    $moyenne = 0;
    foreach ($hist as $i => $n) $moyenne += $i * $n;
    $moyenne /= max(1, $total);
    if ($moyenne < 105) imagegammacorrect($out, 1.0, 1.0 + (105 - $moyenne) / 160);
    imageconvolution($out, [[0, -0.6, 0], [-0.6, 3.4, -0.6], [0, -0.6, 0]], 1, 0); // netteté douce
    return $out;
}

function mention_virtuelle($img, string $texte = 'AMÉNAGEMENT VIRTUEL · IMAGE RETOUCHÉE'): void
{
    $w = imagesx($img); $h = imagesy($img);
    $police = APP_ROOT . '/app/lib/tfpdf/font/unifont/JetBrainsMono-Medium.ttf';
    $taille = max(12, (int) ($w / 70));
    $bande = (int) ($taille * 2.4);
    imagefilledrectangle($img, 0, $h - $bande, $w, $h, imagecolorallocatealpha($img, 17, 17, 20, 25));
    if (is_file($police)) imagettftext($img, $taille, 0, (int) ($taille * 1.2), $h - (int) ($bande * 0.32), imagecolorallocate($img, 212, 242, 46), $police, $texte);
    else imagestring($img, 5, 12, $h - $bande + 8, 'AMENAGEMENT VIRTUEL', imagecolorallocate($img, 212, 242, 46));
}

function enregistrer_jpeg($img, string $chemin, int $qualite = 84): void
{
    imageinterlace($img, true);
    imagejpeg($img, $chemin, $qualite);
}

function miniature(string $source, string $dest, int $largeur = 480): void
{
    $img = charger_image($source);
    if (!$img) return;
    enregistrer_jpeg(imagescale($img, $largeur, -1, IMG_BICUBIC), $dest, 78);
}

/** Pièce représentée sur la photo, reconnue par Gemini (ou devinée en démo). */
function reconnaitre_piece(string $chemin, int $rang): array
{
    global $CONFIG;
    if (empty($CONFIG['gemini_api_key'])) {
        return ['piece' => ['Façade', 'Séjour', 'Cuisine', 'Jardin', 'Chambre', 'Salle de bain', 'Chambre', 'Terrasse'][$rang % 8], 'description' => '', 'note' => 7];
    }
    $mini = imagescale(charger_image($chemin), 768, -1);
    ob_start();
    imagejpeg($mini, null, 80);
    $jpeg = (string) ob_get_clean();
    $txt = gemini_generate($CONFIG['modele_analyse'], [
        'contents' => [['role' => 'user', 'parts' => [
            ['text' => "Photo d'un bien immobilier à vendre. Indique la pièce ou la vue représentée, une description de 8 mots maximum pour l'annonce, et une note de 1 à 10 de son intérêt pour l'annonce (lumière, cadrage, attrait)."],
            ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($jpeg)]],
        ]]],
        'generationConfig' => ['responseMimeType' => 'application/json', 'responseSchema' => [
            'type' => 'OBJECT',
            'properties' => ['piece' => ['type' => 'STRING', 'enum' => ORDRE_PIECES], 'description' => ['type' => 'STRING'], 'note' => ['type' => 'INTEGER']],
            'required' => ['piece', 'description', 'note'],
        ]],
    ], 60);
    return json_decode($txt, true) ?: ['piece' => 'Autre', 'description' => '', 'note' => 5];
}

/** Ordre conseillé : la plus belle façade ou le séjour d'abord, puis l'ordre logique de visite, les floues à la fin. */
function trier_photos(array $photos): array
{
    usort($photos, function ($a, $b) {
        $ra = array_search($a['piece'] ?? 'Autre', ORDRE_PIECES, true);
        $rb = array_search($b['piece'] ?? 'Autre', ORDRE_PIECES, true);
        return [!empty($a['floue']), $ra === false ? 99 : $ra, -($a['note'] ?? 5)] <=> [!empty($b['floue']), $rb === false ? 99 : $rb, -($b['note'] ?? 5)];
    });
    return $photos;
}

function ajouter_photo(array $agent, string $id, array $f): array
{
    if (($f['error'] ?? 1) !== UPLOAD_ERR_OK) fail(400, 'Envoi de la photo incomplet.');
    if ($f['size'] > 25 * 1024 * 1024) fail(413, 'Photo trop lourde (25 Mo maximum).');
    $img = charger_image($f['tmp_name']) ?: fail(415, 'Format de photo non pris en charge (JPEG, PNG ou WebP).');
    $img = normaliser_image($img, $f['tmp_name']);
    $dir = photos_dir($agent, $id);
    if (!is_dir($dir)) mkdir($dir, 0770, true);
    $base = date('ymd-His') . '-' . bin2hex(random_bytes(3));
    enregistrer_jpeg($img, "$dir/$base-original.jpg", 88);
    $net = nettete($img);
    $retouchee = retoucher($img);
    enregistrer_jpeg($retouchee, "$dir/$base.jpg");
    miniature("$dir/$base.jpg", "$dir/$base-mini.jpg");
    $v = load_visit($agent, $id);
    try {
        $ia = reconnaitre_piece("$dir/$base.jpg", count($v['photos'] ?? []));
        flush_usage($agent, $id, 'analyse');
    } catch (Throwable) {
        $ia = ['piece' => 'Autre', 'description' => '', 'note' => 5];
    }
    $photo = ['fichier' => "$base.jpg", 'original' => "$base-original.jpg", 'piece' => $ia['piece'], 'description' => $ia['description'], 'note' => (int) $ia['note'],
        'nettete' => $net, 'floue' => $net < 60, 'retouchee' => true, 'ajoutee_le' => date('c')];
    return update_visit($agent, $id, function (array $v) use ($photo) {
        $v['photos'] = trier_photos(array_merge($v['photos'] ?? [], [$photo]));
        return $v;
    });
}

/** Home staging virtuel : Gemini (modèle d'images) meuble ou désencombre la pièce ; en démo, simple ambiance chaude. */
function home_staging(array $agent, string $id, string $fichier, string $style): array
{
    global $CONFIG;
    $dir = photos_dir($agent, $id);
    if (!preg_match('/^[\w-]+\.jpg$/', $fichier) || !is_file("$dir/$fichier")) fail(404, 'Photo introuvable.');
    $dest = preg_replace('/\.jpg$/', '-staging.jpg', $fichier);
    $modele = (string) ($CONFIG['modele_image'] ?? 'gemini-2.5-flash-image');
    $img = null;
    if (!empty($CONFIG['gemini_api_key'])) {
        $data = gemini_request('POST', '/models/' . rawurlencode($modele) . ':generateContent', $CONFIG['gemini_api_key'], [
            'contents' => [['role' => 'user', 'parts' => [
                ['text' => "Home staging virtuel pour une annonce immobilière : " . ($style === 'vider' ? 'retire tous les meubles et objets personnels de cette pièce, murs et sols propres' : "meuble et décore cette pièce dans un style $style, chaleureux et lumineux")
                    . ". Conserve exactement l'architecture : murs, fenêtres, portes, sol, plafond, perspective et lumière naturelle. Ne modifie pas la taille de la pièce."],
                ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode((string) file_get_contents("$dir/$fichier"))]],
            ]]],
            'generationConfig' => ['responseModalities' => ['IMAGE', 'TEXT']],
        ], 120);
        global $AI_USAGE;
        if (!empty($data['usageMetadata'])) $AI_USAGE[] = [$modele, cout_usage($modele, $data['usageMetadata']) + 0.035, $data['usageMetadata']]; // ≈ prix d'une image générée
        foreach ($data['candidates'][0]['content']['parts'] ?? [] as $p) {
            if (!empty($p['inlineData']['data'])) $img = imagecreatefromstring(base64_decode($p['inlineData']['data']));
        }
        flush_usage($agent, $id, 'image');
        if (!$img) fail(502, "Le modèle d'images n'a pas renvoyé de photo (vérifiez le modèle dans les Paramètres).");
    } else {
        $img = charger_image("$dir/$fichier");
        imagefilter($img, IMG_FILTER_COLORIZE, 18, 8, -6);
        imagefilter($img, IMG_FILTER_CONTRAST, -6);
    }
    mention_virtuelle($img);
    enregistrer_jpeg($img, "$dir/$dest");
    miniature("$dir/$dest", "$dir/" . preg_replace('/\.jpg$/', '-mini.jpg', $dest));
    return update_visit($agent, $id, function (array $v) use ($fichier, $dest, $style) {
        foreach ($v['photos'] as &$p) if ($p['fichier'] === $fichier) $p['staging'] = ['fichier' => $dest, 'style' => $style, 'le' => date('c')];
        unset($p);
        journal_ajout($v, 'photo', "Home staging virtuel ($style) créé, avec la mention « aménagement virtuel ».");
        return $v;
    });
}

function chemin_photo(array $agent, string $id, string $f, bool $mini = false): string
{
    if (!preg_match('/^[\w-]+\.jpg$/', $f)) fail(400, 'Photo invalide.');
    $dir = photos_dir($agent, $id);
    $m = preg_replace('/\.jpg$/', '-mini.jpg', $f);
    $chemin = $mini && is_file("$dir/$m") ? "$dir/$m" : "$dir/$f";
    if (!is_file($chemin)) fail(404, 'Photo introuvable.');
    return $chemin;
}

function servir_image(string $chemin): never
{
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($chemin));
    header('Cache-Control: private, max-age=86400');
    readfile($chemin);
    exit;
}

route('GET photo', function ($id) {
    $me = require_user();
    servir_image(chemin_photo($me, $id, (string) ($_GET['f'] ?? ''), !empty($_GET['mini'])));
});

route('POST photos', function ($id) {
    $me = require_user();
    $v = ajouter_photo($me, $id, $_FILES['photo'] ?? []);
    send_json(['photos' => $v['photos']]);
});

route('POST photos_ordre', function ($id) {
    $me = require_user();
    $in = json_input();
    $v = update_visit($me, $id, function (array $v) use ($in) {
        $par = array_column($v['photos'] ?? [], null, 'fichier');
        if (!empty($in['supprimer'])) unset($par[$in['supprimer']]); // fichiers effacés après l'enregistrement
        if (!empty($in['ordre'])) {
            $tri = [];
            foreach ((array) $in['ordre'] as $f) if (isset($par[$f])) { $tri[] = $par[$f]; unset($par[$f]); }
            $par = array_merge($tri, array_values($par));
        }
        if (!empty($in['piece']) && isset($par[$in['piece']['fichier'] ?? ''])) $par[$in['piece']['fichier']]['piece'] = mb_substr((string) $in['piece']['nom'], 0, 30);
        if (!empty($in['trier'])) $par = trier_photos(array_values($par));
        $v['photos'] = array_values($par);
        return $v;
    });
    if (!empty($in['supprimer'])) {
        $dir = photos_dir($me, $id);
        $base = preg_replace('/\.jpg$/', '', (string) $in['supprimer']);
        if (preg_match('/^[\w-]+$/', $base)) foreach (glob("$dir/$base*.jpg") ?: [] as $f) unlink($f);
    }
    send_json(['photos' => $v['photos']]);
});

route('POST staging', function ($id) {
    $me = require_user();
    $in = json_input();
    $style = in_array($in['style'] ?? '', ['contemporain', 'scandinave', 'industriel', 'vider'], true) ? $in['style'] : 'contemporain';
    $v = home_staging($me, $id, (string) ($in['fichier'] ?? ''), $style);
    send_json(['photos' => $v['photos']]);
});
