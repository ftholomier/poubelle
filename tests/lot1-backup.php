<?php
/**
 * Sauvegardes (App\Services\Backup) : tout storage/ est pris (boutique, carnets, albums,
 * tickets, consentements, radio, défis, validations, Trouvailles…) sauf caches, sessions,
 * limites, journaux, mises à jour, fichiers temporaires et verrous ; avec les médias, voix IA
 * et commentaires radio du musée servi (public/m/<id>/ pour un musée non historique).
 * Usage : php tests/lot1-backup.php (code de sortie 1 en cas d'échec). Travaille sur le musée
 * « paris » ; les fichiers d'essai et l'archive créés sont supprimés à la fin.
 */
declare(strict_types=1);

putenv('MUSEE_CLUB=paris');
require __DIR__ . '/../app/bootstrap.php';

use App\Services\Backup;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// Règles d'exclusion.
$eq('storage/shop/orders.json gardé', Backup::excluded('storage/shop/orders.json'), false);
$eq('storage/tickets.json gardé', Backup::excluded('storage/tickets.json'), false);
$eq('storage/audio (textes) gardé', Backup::excluded('storage/audio/x.json'), false);
$eq('storage/audio/jobs exclu', Backup::excluded('storage/audio/jobs/a.json'), true);
$eq('storage/cache exclu', Backup::excluded('storage/cache/x.json'), true);
$eq('storage/compos/cache exclu', Backup::excluded('storage/compos/cache/x.html'), true);
$eq('storage/compos/etat.json gardé', Backup::excluded('storage/compos/etat.json'), false);
$eq('storage/import/rest-full exclu', Backup::excluded('storage/import/rest-full/a.json'), true);
$eq('storage/import/feuilles gardé', Backup::excluded('storage/import/feuilles/a.json'), false);
$eq('storage/backups exclu', Backup::excluded('storage/backups/a.zip'), true);
$eq('verrou exclu', Backup::excluded('storage/shop/orders.json.lock'), true);
$eq('data/ gardé', Backup::excluded('data/fiches/1.json'), false);

// Archive réelle du musée « paris » avec des fichiers d'essai.
$created = [];
$touch = function (string $abs) use (&$created) {
    $d = dirname($abs);
    $mk = [];
    while (!is_dir($d)) {
        $mk[] = $d;
        $d = dirname($d);
    }
    foreach (array_reverse($mk) as $m) {
        mkdir($m, 0775);
        $created[] = $m;
    }
    file_put_contents($abs, 'essai');
    $created[] = $abs;
};
$keep = [
    'storage/shop/lot1-essai-orders.json', 'storage/carnets/lot1-essai.json', 'storage/vignettes/lot1-essai.json',
    'storage/livres/lot1-essai.json', 'storage/lot1-essai-tickets.json', 'storage/consent/lot1-essai.jsonl',
    'storage/radio/lot1-essai.json', 'storage/defi/lot1-essai.json', 'storage/lot1-essai-validations.json',
    'storage/trouvailles/lot1-essai.json',
];
$drop = [
    'storage/cache/lot1-essai.json', 'storage/sessions/sess_lot1essai', 'storage/ratelimit/lot1-essai',
    'storage/logs/lot1-essai.log', 'storage/update/lot1-essai.zip', 'storage/tmp/lot1-essai', 'storage/shop/lot1-essai.json.lock',
];
$root = dirname(DATA_PATH);
foreach (array_merge($keep, $drop) as $rel) {
    $touch("$root/$rel");
}
$pub = substr(MEDIA_PATH, strlen(APP_ROOT) + 1);
$touch(MEDIA_PATH . '/audio/lot1-essai.mp3');
$touch(MEDIA_PATH . '/radio/lot1-essai.mp3');

$r = Backup::run(true, true);
$eq('archive créée', isset($r['file']), true);
$names = [];
if (isset($r['file'])) {
    $z = new ZipArchive();
    $z->open(Backup::DIR . '/' . $r['file']);
    for ($i = 0; $i < $z->numFiles; $i++) {
        $names[$z->getNameIndex($i)] = true;
    }
    $z->close();
    @unlink(Backup::DIR . '/' . $r['file']);
    echo '     (' . count($names) . ' fichiers, ' . round($r['size'] / 1024) . " Ko)\n";
}
foreach ($keep as $rel) {
    $eq("sauvegardé : $rel", isset($names[$rel]), true);
}
foreach ($drop as $rel) {
    $eq("non sauvegardé : $rel", isset($names[$rel]), false);
}
$eq("voix IA du musée ($pub/audio)", isset($names["$pub/audio/lot1-essai.mp3"]), true);
$eq("commentaires radio ($pub/radio)", isset($names["$pub/radio/lot1-essai.mp3"]), true);
$eq('réglages sauvegardés', isset($names['storage/settings.json']), true);

foreach (array_reverse($created) as $p) {
    is_dir($p) ? @rmdir($p) : @unlink($p);
}
@rmdir(Backup::DIR);
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
