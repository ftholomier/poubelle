<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Ce qui transforme un brouillon en publication partie.
 *
 * Trois pièces se rencontrent ici et nulle part ailleurs : la file
 * (`Publications`), l'accès à Meta (`Reseaux`) et la fabrique d'image
 * (`Vignette`). Les écrans, la case à cocher de l'éditeur d'actualité et la
 * tâche planifiée passent tous par cette classe : il n'existe donc qu'un seul
 * chemin d'envoi, et une correction faite ici vaut pour les trois.
 *
 * **Une publication qui échoue n'est jamais perdue.** Elle reste en file, avec
 * son motif, et sera réessayée. Au bout de trois essais elle passe au journal
 * en échec, où la mairie la voit. Le pire, pour une mairie, n'est pas qu'une
 * publication échoue : c'est qu'elle échoue en silence et que personne ne
 * l'apprenne.
 *
 * **Un réseau qui échoue n'empêche pas l'autre.** Facebook et Instagram sont
 * tentés séparément et rapportés séparément. Une image refusée par Instagram
 * ne doit pas retenir le texte qui serait passé sur Facebook.
 */
final class Diffusion
{
    /**
     * Ce qu'une visite du back-office dépile au plus.
     *
     * Cinq, et pas la file entière : le dépilage opportuniste se greffe sur
     * l'affichage d'un écran, et une file de trente publications en retard
     * ferait attendre l'administrateur une minute devant une page blanche. Le
     * reste part à la visite suivante.
     */
    private const DEPILE_MAX = 5;

    public function __construct(
        private readonly Publicateur $reseaux,
        private readonly Publications $publications,
        private readonly Vignette $vignette,
        private readonly string $racineWeb,
        private readonly string $origine,
    ) {
    }

    /**
     * Complète un brouillon : texte assemblé, image résolue ou fabriquée.
     *
     * @param array<string, mixed> $brouillon
     * @return array<string, mixed>
     */
    public function preparer(array $brouillon): array
    {
        $titre = trim((string) ($brouillon['titre'] ?? ''));
        $texte = trim((string) ($brouillon['texte'] ?? ''));
        $lien  = trim((string) ($brouillon['lien'] ?? ''));
        $image = trim((string) ($brouillon['image'] ?? ''));
        $reseaux = array_values(array_intersect(
            array_map('strval', (array) ($brouillon['reseaux'] ?? [])),
            ['facebook', 'instagram']
        ));

        if ($texte === '' && $titre === '') {
            throw new RuntimeException('Une publication vide n’a rien à dire.');
        }
        if ($reseaux === []) {
            throw new RuntimeException('Choisissez au moins un réseau.');
        }

        /* Le texte est assemblé ICI, en entier, et vérifié ici.

           Il l'était auparavant en deux temps — le lien au moment de préparer,
           le titre au moment d'envoyer — et la coupe aux 2 000 caractères
           tombait après les deux. Le compteur de l'écran, lui, ne comptait que
           le corps du texte : on lisait « 1 990 / 2 000 », et le post partait
           coupé en pleine phrase, le lien ajouté en fin de légende étant le
           premier à sauter. Un seul assemblage, une seule mesure. */
        /* Y aura-t-il une image ? La question se tranche AVANT de la
           fabriquer : refuser un message trop long après avoir écrit un JPEG
           sur le disque y laisserait une vignette que plus rien ne référence.
           Or la réponse est connue d'avance — Instagram sélectionné sans photo
           veut dire qu'une vignette sera faite. */
        $porteImage = $image !== '' || in_array('instagram', $reseaux, true);
        $textes = [
            // Sur /feed, le lien voyage à part et Facebook en tire un aperçu ;
            // sur /photos il n'existe pas de champ pour lui, il doit être dans
            // la légende. C'est la présence d'une image qui décide.
            'facebook'  => $this->assembler($titre, $texte, $porteImage ? $lien : ''),
            // Instagram ne rend aucun lien cliquable, mais l'adresse écrite
            // reste recopiable — et c'est mieux que rien.
            'instagram' => $this->assembler($titre, $texte, $lien),
        ];

        $limites = [
            'facebook'  => Reseaux::TEXTE_MAX,
            'instagram' => Reseaux::TEXTE_MAX_INSTAGRAM,
        ];
        foreach ($reseaux as $reseau) {
            $long = mb_strlen($textes[$reseau]);
            if ($long > $limites[$reseau]) {
                throw new RuntimeException(sprintf(
                    'Le message fait %d caractères une fois le titre%s assemblés ; '
                    . '%s en accepte %d. Retirez-en %d.',
                    $long,
                    $lien !== '' ? ' et le lien' : '',
                    ucfirst($reseau),
                    $limites[$reseau],
                    $long - $limites[$reseau]
                ));
            }
        }

        // Instagram n'accepte rien sans image : on en fabrique une plutôt que
        // de refuser la publication. Voir App\Core\Vignette.
        $fabriquee = false;
        if ($image === '' && in_array('instagram', $reseaux, true)) {
            $image = $this->vignette->fabriquer(
                $titre !== '' ? $titre : mb_substr($texte, 0, 120),
                (string) ($brouillon['surtitre'] ?? '')
            );
            $fabriquee = true;
        }

        return [
            'titre'     => $titre,
            'texte'     => $textes['instagram'],
            'texte_fb'  => $textes['facebook'],
            // Marque les publications dont le texte est déjà assemblé et déjà
            // mesuré. Une publication mise en file avant cette correction ne
            // la porte pas, et repasse par l'assemblage d'autrefois.
            'assemble'  => true,
            'lien'      => $lien,
            'image'     => $image,
            'image_fabriquee' => $fabriquee,
            'reseaux'   => $reseaux,
            'source'    => (string) ($brouillon['source'] ?? 'libre'),
            'quand'     => max(0, (int) ($brouillon['quand'] ?? 0)),
        ];
    }

    /**
     * Titre, texte et lien en un seul message.
     *
     * Public parce que le compteur de l'écran doit mesurer exactement ce qui
     * partira : deux assemblages différents redonneraient l'écart qu'on vient
     * de fermer.
     */
    public function assembler(string $titre, string $texte, string $lien): string
    {
        $morceaux = array_values(array_filter([trim($titre), trim($texte), trim($lien)], 'strlen'));

        return implode("\n\n", $morceaux);
    }

    /**
     * L'adresse publique d'une image, telle que Meta pourra la télécharger.
     *
     * Rend une chaîne vide quand l'adresse n'est pas utilisable de
     * l'extérieur : c'est le cas d'un poste local, et Instagram doit alors le
     * dire clairement plutôt que d'échouer sur un message de Meta.
     */
    public function urlImage(string $relatif): string
    {
        if ($relatif === '') {
            return '';
        }
        if (str_starts_with($relatif, 'http')) {
            return $relatif;
        }

        return rtrim($this->origine, '/') . '/' . ltrim($relatif, '/');
    }

    /** L'image existe-t-elle vraiment sur le disque ? */
    public function imagePresente(string $relatif): bool
    {
        return $relatif !== '' && is_file($this->racineWeb . '/' . ltrim($relatif, '/'));
    }

    /**
     * Envoie tout de suite. Rend [identifiants par réseau, motifs d'échec].
     *
     * @param array<string, mixed> $publication
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    public function envoyer(array $publication): array
    {
        $ids = [];
        $echecs = [];
        $reseaux = (array) ($publication['reseaux'] ?? []);
        $image = (string) ($publication['image'] ?? '');
        $url = $this->urlImage($image);

        if (in_array('facebook', $reseaux, true)) {
            try {
                $ids['facebook'] = $this->reseaux->publierFacebook(
                    $this->texteFinal($publication, 'texte_fb'),
                    $url,
                    (string) ($publication['lien'] ?? '')
                );
            } catch (Throwable $e) {
                $echecs['facebook'] = $e->getMessage();
            }
        }

        if (in_array('instagram', $reseaux, true)) {
            try {
                if ($url === '' || !$this->imagePresente($image)) {
                    throw new RuntimeException('Instagram n’accepte aucune publication sans image.');
                }
                $id = $this->reseaux->publierInstagram(
                    $this->texteFinal($publication, 'texte'),
                    $url
                );
                // Le permalien, quand Meta le donne, pour que le journal
                // renvoie à la publication et non au profil.
                $ids['instagram'] = $this->reseaux->permalienInstagram($id) ?: $id;
            } catch (Throwable $e) {
                $echecs['instagram'] = $e->getMessage();
            }
        }

        return [$ids, $echecs];
    }

    /**
     * Le message tel qu'il partira.
     *
     * `preparer()` l'a assemblé et mesuré ; il n'y a plus qu'à le lire. Le
     * repli ne sert qu'aux publications mises en file avant cette correction,
     * qui portent encore un titre à part.
     */
    private function texteFinal(array $publication, string $cle): string
    {
        $texte = (string) ($publication[$cle] ?? $publication['texte'] ?? '');
        if (($publication['assemble'] ?? false) === true) {
            return $texte;
        }

        return $this->assembler((string) ($publication['titre'] ?? ''), $texte, '');
    }

    /**
     * Remet en file les réseaux qu'un envoi immédiat n'a pas servis.
     *
     * Publier « tout de suite » ne passe pas par la file : la mairie veut
     * savoir sur-le-champ si c'est parti. Mais quand Facebook accepte et
     * qu'Instagram refuse, le message disait l'échec et rien ne le rattrapait
     * — il fallait retaper la publication. Ce qui reste à servir retourne
     * donc en file, où le dépilage le reprendra.
     *
     * @param array<string, mixed>  $publication
     * @param array<string, string> $ids    ce qui est parti
     * @return list<string> les réseaux remis en file
     */
    public function reprendrePlusTard(array $publication, array $ids): array
    {
        $restants = array_values(array_filter(
            (array) ($publication['reseaux'] ?? []),
            static fn(string $r): bool => !isset($ids[$r])
        ));
        if ($restants === []) {
            return [];
        }

        $publication['reseaux_demandes'] = array_values((array) ($publication['reseaux'] ?? []));
        $publication['reseaux'] = $restants;
        $publication['posts_acquis'] = $ids;
        $publication['quand'] = 0;
        // Cinq minutes : le refus vient d'avoir lieu, le refaire dans la
        // seconde ne ferait que consommer un essai.
        $publication['reprise'] = time() + 300;
        unset($publication['id']);

        $this->publications->empiler($publication);

        return $restants;
    }

    /**
     * Dépile ce dont l'heure est venue.
     *
     * Appelé par la tâche planifiée, et faute de tâche planifiée, par les
     * visites du back-office. Il ne lève jamais : une file en panne ne doit
     * pas empêcher l'administration de s'afficher.
     *
     * **Un seul dépilage à la fois.** Deux déclencheurs, c'est deux dépilages
     * possibles à la même seconde, chacun lisant la même file et envoyant la
     * même publication : la mairie publie deux fois. Le verrou est pris sans
     * attendre — celui qui arrive second n'a rien à faire.
     *
     * @return array{partis: int, echecs: int, verrouille: bool}
     */
    public function depiler(int $maintenant, int $max = self::DEPILE_MAX): array
    {
        $poignee = $this->publications->verrouiller();
        if ($poignee === null) {
            return ['partis' => 0, 'echecs' => 0, 'verrouille' => true];
        }

        try {
            return $this->depilerVerrouille($maintenant, $max) + ['verrouille' => false];
        } finally {
            $this->publications->noterDepilage($maintenant);
            $this->publications->relacher($poignee);
        }
    }

    /** @return array{partis: int, echecs: int} */
    private function depilerVerrouille(int $maintenant, int $max): array
    {
        $partis = 0;
        $echecs = 0;

        foreach (array_slice($this->publications->aEnvoyer($maintenant), 0, $max) as $p) {
            $demandes = array_values((array) ($p['reseaux'] ?? []));
            try {
                [$ids, $motifs] = $this->envoyer($p);
            } catch (Throwable $e) {
                $ids = [];
                $motifs = ['*' => $e->getMessage()];
            }

            $acquis = array_merge((array) ($p['posts_acquis'] ?? []), $ids);
            $motif = implode(' · ', $motifs);

            /* Tout est parti : la publication quitte la file.

               « Tout », c'est-à-dire aucun motif d'échec — et non pas « au
               moins un identifiant ». La nuance a coûté cher : Facebook
               accepté et Instagram refusé, la publication était retirée de la
               file et inscrite au journal comme réussie. Instagram n'était
               jamais retenté, et le journal affirmait le contraire. */
            if ($motifs === []) {
                $partis++;
                $this->publications->retirer((string) ($p['id'] ?? ''));
                $this->publications->journaliser($p, $acquis, '', true);
                continue;
            }

            // Il reste quelque chose à servir : seuls les réseaux qui ont
            // échoué restent en file, avec ce qui est déjà parti.
            $echecs++;
            $restants = array_values(array_filter(
                $demandes,
                static fn(string $r): bool => !isset($ids[$r])
            ));
            $this->publications->noterEchec(
                (string) ($p['id'] ?? ''),
                $motif !== '' ? $motif : 'Échec sans motif.',
                $restants,
                $ids
            );
        }

        return ['partis' => $partis, 'echecs' => $echecs];
    }
}
