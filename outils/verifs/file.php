<?php
declare(strict_types=1);

/**
 * Ce que fait la file de publication quand un seul réseau répond.
 *
 * Les huit auditeurs Python mesurent des pages. Celui-ci mesure une décision
 * qui ne se voit sur aucune page : ce qui reste en file quand Facebook accepte
 * et qu'Instagram refuse. C'est la branche qui était fausse — la publication
 * était retirée de la file et inscrite au journal comme réussie, Instagram
 * n'était jamais retenté, et la mairie croyait avoir publié partout.
 *
 * Aucune requête ne sort : la doublure ci-dessous tient lieu de Meta.
 *
 *     php outils/verifs/file.php
 *
 * Sort en 1 au premier écart.
 */

use App\Core\Diffusion;
use App\Core\Publicateur;
use App\Core\Publications;

$racine = dirname(__DIR__, 2);

require $racine . '/app/Core/Publicateur.php';
require $racine . '/app/Core/Publications.php';
require $racine . '/app/Core/Diffusion.php';
require $racine . '/app/Core/Reseaux.php';
require $racine . '/app/Core/Charte.php';
require $racine . '/app/Core/Vignette.php';

/** Un Meta de comptoir : chaque réseau répond ce qu'on lui a dit de répondre. */
final class MetaDeComptoir implements Publicateur
{
    /** @var list<string> */
    public array $appels = [];

    public function __construct(
        private readonly bool $facebookAccepte,
        private readonly bool $instagramAccepte,
    ) {
    }

    public function publierFacebook(string $texte, string $imageUrl = '', string $lien = ''): string
    {
        $this->appels[] = 'facebook';
        if (!$this->facebookAccepte) {
            throw new RuntimeException('Facebook refuse (doublure).');
        }

        return 'fb-1';
    }

    public function publierInstagram(string $texte, string $imageUrl): string
    {
        $this->appels[] = 'instagram';
        if (!$this->instagramAccepte) {
            throw new RuntimeException('Instagram refuse (doublure).');
        }

        return 'ig-1';
    }

    public function permalienInstagram(string $mediaId): string
    {
        return 'https://www.instagram.com/p/' . $mediaId;
    }
}

$ecarts = [];
$mesures = 0;

function verifier(string $quoi, bool $vrai): void
{
    global $ecarts, $mesures;
    $mesures++;
    if (!$vrai) {
        $ecarts[] = $quoi;
        echo "  ✗ $quoi\n";
        return;
    }
    echo "  · $quoi\n";
}

/** Un dossier de file neuf pour chaque cas. */
function fileNeuve(): Publications
{
    $dossier = sys_get_temp_dir() . '/file-verif-' . bin2hex(random_bytes(6));
    mkdir($dossier, 0755, true);
    register_shutdown_function(static function () use ($dossier): void {
        foreach (glob($dossier . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dossier);
    });

    return new Publications($dossier);
}

function diffusion(Publications $file, Publicateur $meta): Diffusion
{
    // La racine web et l'origine ne servent qu'à l'image : les cas ci-dessous
    // publient sans photo, sauf celui d'Instagram qui a besoin d'un fichier
    // réellement présent — d'où la racine du dépôt et une image du site.
    return new Diffusion($meta, $file, new App\Core\Vignette(
        dirname(__DIR__, 2) . '/public',
        new App\Core\Charte('#1b4a7a'),
        'Angeot'
    ), dirname(__DIR__, 2) . '/public', 'https://exemple.test');
}

echo "Un réseau accepte, l’autre refuse\n";
$file = fileNeuve();
$meta = new MetaDeComptoir(true, false);
$diff = diffusion($file, $meta);

$file->empiler([
    'titre'    => 'Essai',
    'texte'    => 'Corps du message.',
    'texte_fb' => "Essai\n\nCorps du message.",
    'assemble' => true,
    'lien'     => '',
    // Instagram ne publie rien sans image, et vérifie qu'elle est bien sur le
    // disque : sans une photo réelle, l'essai échouerait pour la mauvaise raison.
    'image'    => 'assets/img/site/affouage-bois-faconne.jpg',
    'reseaux'  => ['facebook', 'instagram'],
    'source'   => 'libre',
    'quand'    => 0,
]);

$bilan = $diff->depiler(time());
$reste = $file->file();

verifier('la publication reste en file', count($reste) === 1);
verifier('seul Instagram reste à servir', ($reste[0]['reseaux'] ?? []) === ['instagram']);
verifier('le post Facebook obtenu est gardé', ($reste[0]['posts_acquis']['facebook'] ?? '') === 'fb-1');
verifier('les deux réseaux demandés sont mémorisés',
    ($reste[0]['reseaux_demandes'] ?? []) === ['facebook', 'instagram']);
verifier('un essai est compté', (int) ($reste[0]['essais'] ?? 0) === 1);
verifier('la reprise est repoussée d’au moins cinq minutes',
    (int) ($reste[0]['reprise'] ?? 0) >= time() + 290);
verifier('rien n’est inscrit au journal comme réussi', $file->journal() === []);
verifier('le bilan compte un échec', $bilan['echecs'] === 1 && $bilan['partis'] === 0);

echo "\nLe recul retient la publication\n";
verifier('elle n’est pas à renvoyer tout de suite', $file->aEnvoyer(time()) === []);
verifier('elle le sera passé le recul', count($file->aEnvoyer(time() + 400)) === 1);

echo "\nLe réseau manquant part au tour suivant\n";
$meta2 = new MetaDeComptoir(true, true);
$diff2 = diffusion($file, $meta2);
$bilan2 = $diff2->depiler(time() + 400);

verifier('Facebook n’est pas republié', $meta2->appels === ['instagram']);
verifier('la file est vide', $file->file() === []);
$journal = $file->journal();
verifier('le journal porte une ligne', count($journal) === 1);
verifier('elle est en succès', ($journal[0]['succes'] ?? false) === true);
verifier('elle porte les deux posts',
    ($journal[0]['posts']['facebook'] ?? '') === 'fb-1'
    && str_contains((string) ($journal[0]['posts']['instagram'] ?? ''), 'ig-1'));
verifier('elle rappelle les deux réseaux demandés',
    ($journal[0]['reseaux'] ?? []) === ['facebook', 'instagram']);
verifier('le bilan compte un départ', $bilan2['partis'] === 1);

echo "\nLe verrou empêche deux dépilages simultanés\n";
$file3 = fileNeuve();
$meta3 = new MetaDeComptoir(true, true);
$diff3 = diffusion($file3, $meta3);
$file3->empiler([
    'titre' => 'Verrou', 'texte' => 'x', 'texte_fb' => 'x', 'assemble' => true,
    'lien' => '', 'image' => '', 'reseaux' => ['facebook'], 'source' => 'libre', 'quand' => 0,
]);

$poignee = $file3->verrouiller();
verifier('le verrou est pris', $poignee !== null);
$bilan3 = $diff3->depiler(time());
verifier('le second dépilage ne fait rien', ($bilan3['verrouille'] ?? false) === true);
verifier('rien n’a été envoyé', $meta3->appels === []);
verifier('la publication est toujours en file', count($file3->file()) === 1);
$file3->relacher($poignee);
$bilan4 = $diff3->depiler(time());
verifier('le verrou relâché, elle part', $bilan4['partis'] === 1);

echo "\nAu bout des essais, la publication passe au journal en échec\n";
$file4 = fileNeuve();
$meta4 = new MetaDeComptoir(false, false);
$diff4 = diffusion($file4, $meta4);
$file4->empiler([
    'titre' => 'Perdue', 'texte' => 'x', 'texte_fb' => 'x', 'assemble' => true,
    'lien' => '', 'image' => '', 'reseaux' => ['facebook'], 'source' => 'libre', 'quand' => 0,
]);
$instant = time();
for ($i = 0; $i < Publications::ESSAIS_MAX; $i++) {
    $diff4->depiler($instant);
    $instant += 7300;   // au-delà du plus long recul
}
verifier('la file est vidée', $file4->file() === []);
$j4 = $file4->journal();
verifier('le journal porte l’échec', count($j4) === 1 && ($j4[0]['succes'] ?? true) === false);

echo "\nL’assemblage est mesuré avant l’envoi\n";
$file5 = fileNeuve();
$diff5 = diffusion($file5, new MetaDeComptoir(true, true));
$trop = str_repeat('a', 2100);
try {
    $diff5->preparer([
        'titre' => 'Titre', 'texte' => $trop, 'lien' => 'https://exemple.test/a',
        'image' => 'assets/img/site/affouage-bois-faconne.jpg', 'reseaux' => ['facebook'],
    ]);
    verifier('un message trop long est refusé', false);
} catch (RuntimeException $e) {
    verifier('un message trop long est refusé', str_contains($e->getMessage(), 'Retirez-en'));
}

$pret = $diff5->preparer([
    'titre' => 'Titre', 'texte' => 'Corps', 'lien' => 'https://exemple.test/a',
    'image' => 'assets/img/site/affouage-bois-faconne.jpg', 'reseaux' => ['facebook', 'instagram'],
]);
verifier('le lien est dans la légende quand il y a une photo',
    str_ends_with((string) $pret['texte_fb'], 'https://exemple.test/a'));
verifier('le titre est en tête', str_starts_with((string) $pret['texte_fb'], "Titre\n\n"));

$sansPhoto = $diff5->preparer([
    'titre' => 'Titre', 'texte' => 'Corps', 'lien' => 'https://exemple.test/a',
    'image' => '', 'reseaux' => ['facebook'],
]);
verifier('sans photo, le lien reste hors du message',
    !str_contains((string) $sansPhoto['texte_fb'], 'https://exemple.test/a'));

echo "\n" . $mesures . " mesure(s) — " . count($ecarts) . " écart(s).\n";
exit($ecarts === [] ? 0 : 1);
