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
        private readonly Reseaux $reseaux,
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

        // Le lien est ajouté au texte pour Instagram, qui ne rend pas les
        // liens cliquables mais où l'adresse écrite reste recopiable ; sur
        // Facebook il est passé à part, ce qui donne un aperçu de la page.
        $complet = $texte;
        if ($lien !== '' && in_array('instagram', $reseaux, true)) {
            $complet = rtrim($texte) . "\n\n" . $lien;
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
            'texte'     => $complet,
            'texte_fb'  => $texte,
            'lien'      => $lien,
            'image'     => $image,
            'image_fabriquee' => $fabriquee,
            'reseaux'   => $reseaux,
            'source'    => (string) ($brouillon['source'] ?? 'libre'),
            'quand'     => max(0, (int) ($brouillon['quand'] ?? 0)),
        ];
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
        $quand = (int) ($publication['quand'] ?? 0);

        if (in_array('facebook', $reseaux, true)) {
            try {
                $ids['facebook'] = $this->reseaux->publierFacebook(
                    $this->texteComplet($publication, 'texte_fb'),
                    $url,
                    (string) ($publication['lien'] ?? ''),
                    // Facebook exige au moins dix minutes d'avance ; en deçà,
                    // on publie tout de suite plutôt que d'essuyer son refus.
                    $quand > time() + 660 ? $quand : 0
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
                $ids['instagram'] = $this->reseaux->publierInstagram(
                    $this->texteComplet($publication, 'texte'),
                    $url
                );
            } catch (Throwable $e) {
                $echecs['instagram'] = $e->getMessage();
            }
        }

        return [$ids, $echecs];
    }

    /** Le titre en tête du texte, quand les deux sont là. */
    private function texteComplet(array $publication, string $cle): string
    {
        $titre = trim((string) ($publication['titre'] ?? ''));
        $texte = trim((string) ($publication[$cle] ?? $publication['texte'] ?? ''));

        if ($titre === '') {
            return $texte;
        }
        if ($texte === '') {
            return $titre;
        }

        return $titre . "\n\n" . $texte;
    }

    /**
     * Dépile ce dont l'heure est venue.
     *
     * Appelé par la tâche planifiée, et faute de tâche planifiée, par les
     * visites du back-office. Il ne lève jamais : une file en panne ne doit
     * pas empêcher l'administration de s'afficher.
     *
     * @return array{partis: int, echecs: int}
     */
    public function depiler(int $maintenant, int $max = self::DEPILE_MAX): array
    {
        $partis = 0;
        $echecs = 0;

        foreach (array_slice($this->publications->aEnvoyer($maintenant), 0, $max) as $p) {
            try {
                // L'heure est venue : on ne repasse pas la date à Facebook,
                // sans quoi il la refuserait comme étant dans le passé.
                $p['quand'] = 0;
                [$ids, $motifs] = $this->envoyer($p);
            } catch (Throwable $e) {
                $ids = [];
                $motifs = ['*' => $e->getMessage()];
            }

            if ($ids === []) {
                $echecs++;
                $this->publications->noterEchec(
                    (string) ($p['id'] ?? ''),
                    implode(' · ', $motifs) ?: 'Échec sans motif.'
                );
                continue;
            }

            $partis++;
            $this->publications->retirer((string) ($p['id'] ?? ''));
            $this->publications->journaliser($p, $ids, implode(' · ', $motifs), true);
        }

        return ['partis' => $partis, 'echecs' => $echecs];
    }
}
