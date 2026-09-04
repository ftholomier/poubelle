<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * L'image carrée fabriquée quand une publication n'en a pas.
 *
 * Instagram n'accepte **rien** sans image : pas de post de texte, pas de
 * partage de lien. Or une commune publie souvent une information qui n'a pas
 * de photo — un rappel de collecte, une coupure d'eau, un compte-rendu de
 * conseil. Sans cette classe, la moitié de ce qu'une mairie a à dire lui
 * serait fermée.
 *
 * L'image est fabriquée avec GD, déjà utilisé pour les vignettes de la
 * médiathèque : aucune dépendance ajoutée. Elle reprend la **couleur de la
 * commune** — la charte, donc le réglage de l'écran Apparence — et le blason.
 *
 * Deux points méritent d'être compris avant d'y toucher.
 *
 * **Le fond est le ton foncé de la charte, pas la couleur de marque.**
 * `Charte` résout ce ton pour tenir 7:1 avec le blanc, là où la marque ne
 * tient que 4,5:1. Sur une image qui n'est que du texte, la marge est la
 * bienvenue : un fil Instagram s'affiche sur un téléphone, en plein soleil,
 * et souvent réduit. `outils/verifs/vignette.py` mesure ce contraste sur toute
 * la roue chromatique — c'est le pendant de `couleur.py` pour une image.
 *
 * **La police est un fichier TTF, et il n'est pas là par hasard.** GD ne sait
 * pas lire le woff2 que sert le site, ni dessiner un SVG. Deux versions
 * statiques de Montserrat sont donc versionnées dans public/assets/fonts/,
 * ainsi qu'un PNG du blason à fond transparent. Une commune qui reprend ce
 * socle doit fournir les siens — c'est écrit dans NOUVEAU-SITE.md.
 */
final class Vignette
{
    /** Le format d'Instagram : carré, et la taille qu'il conserve sans réduire. */
    public const COTE = 1080;

    private const MARGE = 96;
    private const BLASON = 'assets/img/logo/blason-angeot-512.png';
    private const POLICE_TITRE = 'assets/fonts/montserrat-700.ttf';
    private const POLICE_PIED = 'assets/fonts/montserrat-500.ttf';

    /** Au-delà, le titre ne tient plus lisiblement dans le carré. */
    private const TITRE_MAX = 130;

    public function __construct(
        private readonly string $racineWeb,
        private readonly Charte $charte,
        private readonly string $nomCommune,
    ) {
    }

    /** GD et la police sont-ils là ? Sinon l'écran le dit au lieu d'échouer. */
    public function possible(): string
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext')) {
            return 'La bibliothèque GD n’est pas disponible sur ce serveur : '
                 . 'l’image ne peut pas être fabriquée.';
        }
        if (!is_file($this->racineWeb . '/' . self::POLICE_TITRE)) {
            return 'La police ' . self::POLICE_TITRE . ' est absente.';
        }

        return '';
    }

    /**
     * Fabrique l'image et rend son chemin, relatif à la racine web.
     *
     * Le nom est tiré du contenu : deux appels pour le même titre rendent le
     * même fichier, et une publication rejouée ne laisse pas un deuxième
     * fichier derrière elle.
     */
    public function fabriquer(string $titre, string $surtitre = ''): string
    {
        $souci = $this->possible();
        if ($souci !== '') {
            throw new RuntimeException($souci);
        }

        $titre = trim(preg_replace('/\s+/u', ' ', $titre) ?? '');
        if ($titre === '') {
            throw new RuntimeException('Une image de texte a besoin d’un titre.');
        }
        if (mb_strlen($titre) > self::TITRE_MAX) {
            $titre = mb_substr($titre, 0, self::TITRE_MAX - 1) . '…';
        }

        $jetons = $this->charte->jetons();
        $dossier = $this->racineWeb . '/assets/img/reseaux';
        if (!is_dir($dossier) && !@mkdir($dossier, 0755, true) && !is_dir($dossier)) {
            throw new RuntimeException('Le dossier ' . $dossier . ' n’a pas pu être créé.');
        }

        // La couleur entre dans le nom : changer la couleur de la commune
        // doit produire une nouvelle image, pas resservir l'ancienne.
        $empreinte = $titre . '|' . $surtitre . '|' . $jetons['bleu-fonce'] . '|' . $jetons['bleu-barre'];
        $nom = 'vignette-' . substr(hash('sha256', $empreinte), 0, 16) . '.jpg';
        $chemin = $dossier . '/' . $nom;
        $relatif = 'assets/img/reseaux/' . $nom;
        if (is_file($chemin)) {
            return $relatif;
        }

        $image = imagecreatetruecolor(self::COTE, self::COTE);
        if ($image === false) {
            throw new RuntimeException('GD n’a pas pu créer l’image.');
        }

        [$fr, $fv, $fb] = Charte::versRvb($jetons['bleu-fonce']);
        $fond = (int) imagecolorallocate($image, $fr, $fv, $fb);
        imagefilledrectangle($image, 0, 0, self::COTE, self::COTE, $fond);

        // Le ton retenu pour le sur-titre et le liseré est `bleu-barre`, et
        // c'est le seul possible : `Charte` le résout explicitement pour tenir
        // 4,6:1 sur `bleu-fonce`, qui est justement le fond d'ici. `bleu-clair`
        // paraît plus naturel mais n'est résolu que contre l'ardoise et les
        // fonds sombres du site — sur ce fond-ci, son contraste n'est garanti
        // par rien, et il tombe à 2,8:1 sur certaines teintes.
        [$lr, $lv, $lb] = Charte::versRvb($jetons['bleu-barre']);
        $liseré = (int) imagecolorallocate($image, $lr, $lv, $lb);
        imagefilledrectangle($image, 0, self::COTE - 12, self::COTE, self::COTE, $liseré);

        $blanc = (int) imagecolorallocate($image, 255, 255, 255);
        $police = $this->racineWeb . '/' . self::POLICE_TITRE;
        $policePied = is_file($this->racineWeb . '/' . self::POLICE_PIED)
            ? $this->racineWeb . '/' . self::POLICE_PIED
            : $police;

        $y = self::MARGE;

        // Le blason, quand il est là. Une commune sans PNG de blason obtient
        // la même image sans lui, plutôt qu'une erreur.
        $blason = $this->racineWeb . '/' . self::BLASON;
        if (is_file($blason)) {
            $source = @imagecreatefrompng($blason);
            if ($source !== false) {
                $hauteur = 150;
                $largeur = (int) round(imagesx($source) * $hauteur / imagesy($source));
                imagealphablending($image, true);
                imagecopyresampled($image, $source, self::MARGE, $y, 0, 0,
                                   $largeur, $hauteur, imagesx($source), imagesy($source));
                imagedestroy($source);
                $y += $hauteur + 46;
            }
        }

        if ($surtitre !== '') {
            $this->ecrire($image, mb_strtoupper($surtitre, 'UTF-8'), $policePied, 26, self::MARGE, $y, $liseré, 7);
            $y += 54;
        }

        // Le corps du titre s'adapte à sa longueur : un titre de huit mots
        // écrit en soixante-dix points déborderait, et le réduire d'office
        // gâcherait un titre de trois mots.
        $corps = mb_strlen($titre) > 70 ? 46 : (mb_strlen($titre) > 40 ? 56 : 68);
        $lignes = $this->couper($titre, $police, $corps, self::COTE - 2 * self::MARGE);
        $interligne = (int) round($corps * 1.34);

        foreach ($lignes as $ligne) {
            $y += $interligne;
            imagettftext($image, $corps, 0, self::MARGE, $y, $blanc, $police, $ligne);
        }

        $this->ecrire($image, $this->nomCommune, $policePied, 30,
                      self::MARGE, self::COTE - self::MARGE - 6, $blanc, 0);

        $ok = imagejpeg($image, $chemin, 88);
        imagedestroy($image);
        if ($ok === false) {
            throw new RuntimeException('L’image n’a pas pu être écrite dans ' . $dossier . '.');
        }

        return $relatif;
    }

    /** @param resource|\GdImage $image */
    private function ecrire($image, string $texte, string $police, int $corps,
                            int $x, int $y, int $couleur, int $espacement): void
    {
        if ($espacement === 0) {
            imagettftext($image, $corps, 0, $x, $y, $couleur, $police, $texte);
            return;
        }

        // GD ne sait pas espacer les lettres : on les pose une à une. C'est ce
        // qui donne au sur-titre l'allure des sur-titres du site.
        $lettres = preg_split('//u', $texte, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($lettres as $lettre) {
            $boite = imagettftext($image, $corps, 0, $x, $y, $couleur, $police, $lettre);
            $x += (int) round(($boite[2] - $boite[0]) + $espacement);
        }
    }

    /**
     * Coupe le titre en lignes qui tiennent dans la largeur.
     *
     * La mesure est faite par GD lui-même (`imagettfbbox`) plutôt qu'estimée
     * au nombre de caractères : « Illimité » et « WWWWWWWW » n'ont pas la même
     * largeur, et un titre de mairie contient des mots longs.
     *
     * @return list<string>
     */
    private function couper(string $titre, string $police, int $corps, int $largeurMax): array
    {
        $mots = preg_split('/\s+/u', $titre) ?: [];
        $lignes = [];
        $courante = '';

        foreach ($mots as $mot) {
            $essai = $courante === '' ? $mot : $courante . ' ' . $mot;
            $boite = imagettfbbox($corps, 0, $police, $essai);
            $largeur = $boite === false ? 0 : abs($boite[2] - $boite[0]);
            if ($largeur > $largeurMax && $courante !== '') {
                $lignes[] = $courante;
                $courante = $mot;
            } else {
                $courante = $essai;
            }
        }
        if ($courante !== '') {
            $lignes[] = $courante;
        }

        return $lignes;
    }
}
