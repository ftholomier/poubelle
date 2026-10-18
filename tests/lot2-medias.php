<?php
/**
 * Lot 2 — médias : crédit écrit dans la légende (CaptionCredit::split, faux positifs écartés, cas
 * courants gardés), écran « Crédits dans les légendes » qui n'applique que la liste vue (légende
 * inchangée), enregistrement d'un média qui ne découpe plus d'office, média supprimé pendant sa
 * modification jamais recréé. data/media.json est remis dans son état d'origine à la fin.
 * Usage : php tests/lot2-medias.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Admin\Medias as Admin;
use App\Core\JsonStore;
use App\Core\Request;
use App\Data\Media;
use App\Services\CaptionCredit as CC;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// Découpage : faux positifs relevés par la revue.
$eq('« la photo Officielle 1938 » : pas un crédit', CC::split('L’équipe pose pour la photo Officielle 1938'), null);
$eq('« Hommage à Photo Club Montbéliard » : pas un crédit', CC::split('Hommage à Photo Club Montbéliard'), null);
$eq('« Expo Photo Club Montbéliard » : pas un crédit', CC::split('Expo Photo Club Montbéliard'), null);
$eq('prénom composé en tête', CC::split('©Jean-Pierre Dupont ; Bonal 1950'), ['caption' => 'Bonal 1950', 'credit' => 'Jean-Pierre Dupont']);
$eq('tiret entouré d’espaces en tête', CC::split('©AFP - Sochaux champion'), ['caption' => 'Sochaux champion', 'credit' => 'AFP']);
// Cas courants des légendes reprises (gardés).
$eq('Nom Photo Prénom Nom', CC::split('Gomel Photo Jean-Yves Caddedu'), ['caption' => 'Gomel', 'credit' => 'Jean-Yves Caddedu']);
$eq('Nom photo Agence', CC::split('Kanté, Carlao, Valbuena photo AFP'), ['caption' => 'Kanté, Carlao, Valbuena', 'credit' => 'AFP']);
$eq('phrase. Photo …', CC::split('Moussa Doumbia s’impose de la tête. Photo Lionel Vadam'), ['caption' => 'Moussa Doumbia s’impose de la tête.', 'credit' => 'Lionel Vadam']);
$eq('« ! Photo … »', CC::split('Sochaux en D1 ! Photo France football.'), ['caption' => 'Sochaux en D1 !', 'credit' => 'France football']);
$eq('Crédit en fin', CC::split('Jacky Colin. Crédit Est Républicain.'), ['caption' => 'Jacky Colin.', 'credit' => 'Est Républicain']);
$eq('© en tête avec agence', CC::split('©PHOTOPQR/L’ALSACE/Lionel Vadam ; MONTBELIARD LE 10/07/06'), ['caption' => 'MONTBELIARD LE 10/07/06', 'credit' => 'PHOTOPQR/L’ALSACE/Lionel Vadam']);
$eq('légende seule : crédit seul', CC::split('Photo FCSM'), ['caption' => '', 'credit' => 'FCSM']);
$eq('© en fin', CC::split('Le stade © Jean Dupont'), ['caption' => 'Le stade', 'credit' => 'Jean Dupont']);

// Écran d'aperçu et enregistrement : sur une copie de la médiathèque, restaurée à la fin.
$file = Media::FILE;
$backup = file_get_contents($file);
$req = fn (array $post, ?array $json = null) => new Request('POST', '/admin/medias', [], $post, [], ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '127.0.0.1', 'CONTENT_TYPE' => $json !== null ? 'application/json' : ''], $json !== null ? json_encode($json) : '');
try {
    $a = 'zz-lot2/a.jpg';
    $b = 'zz-lot2/b.jpg';
    $c = 'zz-lot2/c.jpg';
    foreach ([$a => 'Gomel Photo Laurent Rea', $b => 'Djoco Photo FCSM', $c => 'Saidi Photo Pascal Bonniere'] as $rel => $cap) {
        Media::put($rel, ['file' => $rel, 'caption' => $cap, 'credit' => '']);
    }
    // La liste vue : a et b (c est arrivée après l'ouverture de l'aperçu) ; b modifiée entre-temps.
    $seen = [$a => Admin::captionHash('Gomel Photo Laurent Rea'), $b => Admin::captionHash('Djoco Photo FCSM')];
    Media::put($b, ['caption' => 'Djoco et Masson Photo FCSM']);
    $_SESSION = [];
    @Admin::creditsApply($req(['seen' => json_encode($seen)]));
    Media::forget();
    $eq('vue et inchangée : découpée', [Media::get($a)['caption'], Media::get($a)['credit']], ['Gomel', 'Laurent Rea']);
    $eq('légende changée depuis l’aperçu : pas touchée', [Media::get($b)['caption'], Media::get($b)['credit']], ['Djoco et Masson Photo FCSM', '']);
    $eq('pas dans la liste vue : pas touchée', Media::get($c)['credit'], '');
    @Admin::creditsApply($req([]));
    Media::forget();
    $eq('sans liste vue : rien n’est appliqué', Media::get($c)['credit'], '');

    // Enregistrement d'un média : plus de découpage d'office, une indication seulement.
    $_SESSION = [];
    $r = @Admin::save($req([], ['file' => $c, 'caption' => 'Saidi Photo Pascal Bonniere', 'credit' => '']));
    Media::forget();
    $out = json_decode($r->body, true);
    $eq('enregistrement : légende gardée telle quelle', [Media::get($c)['caption'], Media::get($c)['credit']], ['Saidi Photo Pascal Bonniere', '']);
    $eq('enregistrement : crédit repéré signalé', str_contains((string) ($out['message'] ?? ''), 'Pascal Bonniere'), true);

    // Média supprimé pendant sa modification : pas d'entrée fantôme.
    Media::remove($c);
    $eq('Media::put sur un média supprimé : refusé', Media::put($c, ['caption' => 'x']), false);
    JsonStore::forget($file);
    $eq('aucune entrée recréée', isset(JsonStore::read($file, [])[$c]), false);
    $eq('un dépôt (champ file) crée toujours', Media::put($c, ['file' => $c, 'caption' => 'y']), true);
} finally {
    file_put_contents($file, $backup);
    JsonStore::forget($file);
    Media::forget();
    Media::all();
}
$eq('médiathèque remise en état', file_get_contents($file) === $backup, true);
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
