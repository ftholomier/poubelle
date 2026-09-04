<?php
declare(strict_types=1);

namespace App\Core;

/**
 * La bulle de l'assistant, en bas à droite : forme, couleurs, libellé, taille.
 *
 * Ce sont quatre réglages laissés à la mairie, et le socle en tire une
 * obligation : « tout réglage laissé à la mairie doit avoir son auditeur qui
 * en force les bornes ». Deux sélecteurs de couleur libres sont, de ce point
 * de vue, le pire des réglages — un fond jaune et un texte blanc donnent
 * 1,07:1, et personne au guichet ne s'en apercevra.
 *
 * D'où le partage : **le fond est libre, la couleur du texte est une
 * intention.** La teinte choisie pour le texte est conservée ; c'est sa
 * LUMINOSITÉ qui est résolue, par pas d'un demi-point, jusqu'à ce que le
 * libellé tienne 4,5:1 sur le fond choisi. La mairie obtient la couleur
 * qu'elle a demandée quand elle est lisible, et la même couleur éclaircie ou
 * assombrie quand elle ne l'est pas — jamais un bouton illisible, jamais un
 * refus d'enregistrer.
 *
 * C'est la méthode de `Charte`, appliquée à un seul ton ; les fonctions de
 * couleur sont d'ailleurs les siennes, pour qu'il n'existe qu'une arithmétique
 * de contraste dans le code.
 *
 * `outils/verifs/bulle.py` force les cinq formes, les deux bornes de taille et
 * des couples de couleurs volontairement mauvais : c'est lui qui prouve la
 * propriété.
 */
final class Bulle
{
    /**
     * Les formes proposées.
     *
     * Elles ne sont pas cinq variantes d'arrondi : chacune répond à un usage
     * différent, et le résumé est ce que la mairie lit dans l'écran.
     *
     * @var array<string, array{nom: string, resume: string, libelle: bool}>
     */
    public const FORMES = [
        'barre' => [
            'nom'     => 'Barre',
            'resume'  => 'Un rectangle à angles vifs, picto et libellé côte à côte.',
            'libelle' => true,
        ],
        'pilule' => [
            'nom'     => 'Pilule',
            'resume'  => 'La même, entièrement arrondie : plus douce, plus courante sur le web.',
            'libelle' => true,
        ],
        'rond' => [
            'nom'     => 'Rond',
            'resume'  => 'Un cercle avec le seul picto. Le libellé reste lu par les lecteurs d’écran.',
            'libelle' => false,
        ],
        'pastille' => [
            'nom'     => 'Pastille',
            'resume'  => 'Un carré à coins doux avec le seul picto : discret, dans l’esprit du site.',
            'libelle' => false,
        ],
        'onglet' => [
            'nom'     => 'Onglet',
            'resume'  => 'Une étiquette collée au bord droit, libellé à la verticale.',
            'libelle' => true,
        ],
    ];

    public const FORME_DEFAUT = 'barre';

    /**
     * Les animations d'appel.
     *
     * Elles ont toutes la même mécanique et le même budget : un mouvement bref,
     * joué trois fois après un temps de latence, puis plus jamais. Ce n'est pas
     * une question de goût — voir la note de durée dans site.css : au-delà de
     * cinq secondes, une animation qui démarre seule doit pouvoir être arrêtée
     * par le visiteur. En dessous, elle n'a rien à demander à personne, et un
     * bouton qui bat sans fin dans le coin de l'écran est de toute façon une
     * gêne, pas un appel.
     *
     * @var array<string, array{nom: string, resume: string}>
     */
    public const ANIMATIONS = [
        'aucune' => [
            'nom'    => 'Aucune',
            'resume' => 'Le bouton reste immobile.',
        ],
        'halo' => [
            'nom'    => 'Halo',
            'resume' => 'Un anneau de la couleur du bouton s’en écarte et s’efface. Rien ne bouge : c’est la plus discrète.',
        ],
        'rebond' => [
            'nom'    => 'Rebond',
            'resume' => 'Le bouton fait deux petits sauts, comme s’il se rappelait à vous.',
        ],
        'balancement' => [
            'nom'    => 'Balancement',
            'resume' => 'Une oscillation légère, de quelques degrés, qui s’amortit.',
        ],
        'respiration' => [
            'nom'    => 'Respiration',
            'resume' => 'Le bouton enfle et redescend une fois par cycle. Très doux.',
        ],
    ];

    /** Livrée : discrète, et la seule qui ne déplace pas le bouton. */
    public const ANIMATION_DEFAUT = 'halo';

    /**
     * Bornes de taille, en pixels.
     *
     * Le plancher n'est pas un goût : sous 44 px la cible tactile passe sous
     * le minimum retenu par `mise-en-page.py`, qui le verrait. Le plafond tient
     * à la place disponible en bas d'un iPhone SE, où la bulle voisine avec le
     * bouton d'appel du bandeau.
     */
    public const TAILLE_MIN = 44;
    public const TAILLE_MAX = 76;
    public const TAILLE_PAS = 2;
    public const TAILLE_DEFAUT = 52;

    /** Au-delà, le libellé déborde de l'écran d'un téléphone. */
    public const LIBELLE_MAX = 40;

    /** Le seuil que le libellé doit tenir sur son fond. */
    public const CONTRASTE_MINI = 4.5;

    /** Le blanc : la couleur de texte livrée, et le repli de tout calcul. */
    public const TEXTE_DEFAUT = '#ffffff';

    private readonly string $fond;
    private readonly string $fondCommune;
    private readonly string $texteChoisi;
    private readonly string $texte;

    /**
     * @param array<string, mixed> $reglages le sous-tableau `assistant.bulle`
     * @param string $libelleDefaut le titre de l'assistant, si aucun libellé propre
     */
    public function __construct(
        private readonly array $reglages,
        private readonly string $libelleDefaut,
        Charte $charte,
    ) {
        // Sans choix de fond, la bulle suit la couleur de la commune : c'est
        // le cas livré, et il reste juste quelle que soit cette couleur
        // puisque la marque est déjà résolue pour porter du blanc.
        $this->fondCommune = $charte->jetons()['bleu'];
        $fond = trim((string) ($reglages['fond'] ?? ''));
        $this->fond = $fond !== '' ? Charte::normaliser($fond) : $this->fondCommune;

        $texte = trim((string) ($reglages['texte'] ?? ''));
        $this->texteChoisi = $texte !== '' ? Charte::normaliser($texte) : self::TEXTE_DEFAUT;
        $this->texte = self::resoudreTexte($this->texteChoisi, $this->fond);
    }

    public static function depuis(Parametres $parametres, string $libelleDefaut): self
    {
        $reglages = $parametres->get('assistant.bulle', []);

        return new self(
            is_array($reglages) ? $reglages : [],
            $libelleDefaut,
            Charte::depuis($parametres)
        );
    }

    // ---------------------------------------------------------------- lecture

    public function forme(): string
    {
        $f = (string) ($this->reglages['forme'] ?? '');

        return isset(self::FORMES[$f]) ? $f : self::FORME_DEFAUT;
    }

    public function animation(): string
    {
        $a = (string) ($this->reglages['animation'] ?? '');

        return isset(self::ANIMATIONS[$a]) ? $a : self::ANIMATION_DEFAUT;
    }

    /**
     * Le libellé du bouton.
     *
     * Il retombe sur le titre de l'assistant plutôt que sur un texte écrit
     * ici : une mairie qui a soigné son titre n'a rien de plus à saisir, et
     * les deux ne peuvent pas se contredire.
     */
    public function libelle(): string
    {
        $l = trim((string) ($this->reglages['libelle'] ?? ''));

        return $l !== '' ? mb_substr($l, 0, self::LIBELLE_MAX) : $this->libelleDefaut;
    }

    public function taille(): int
    {
        return self::borner($this->reglages['taille'] ?? self::TAILLE_DEFAUT);
    }

    public function fond(): string
    {
        return $this->fond;
    }

    /** La couleur de marque du moment : ce que « suivre la commune » donne. */
    public function fondCommune(): string
    {
        return $this->fondCommune;
    }

    /** Le fond est-il celui de la commune, ou une couleur à part ? */
    public function suitLaCommune(): bool
    {
        return trim((string) ($this->reglages['fond'] ?? '')) === '';
    }

    /** La couleur de texte réellement peinte, après résolution. */
    public function texte(): string
    {
        return $this->texte;
    }

    /** Celle que la mairie a choisie, pour la réafficher dans le sélecteur. */
    public function texteChoisi(): string
    {
        return $this->texteChoisi;
    }

    /** A-t-il fallu corriger la couleur choisie ? L'écran le dit alors. */
    public function corrigee(): bool
    {
        return $this->texte !== $this->texteChoisi;
    }

    public function contraste(): float
    {
        return Charte::rapport($this->texte, $this->fond);
    }

    // ------------------------------------------------------------------ rendu

    /**
     * Les classes posées sur le conteneur : la forme, et l'animation quand il
     * y en a une.
     *
     * « aucune » ne pose pas de classe plutôt que d'en poser une qui ne fait
     * rien : le JavaScript reconnaît une bulle animée au préfixe
     * `assistant--anim-`, et il doit pouvoir la retirer.
     */
    public function classe(): string
    {
        $classes = ['assistant--' . $this->forme()];
        if ($this->animation() !== 'aucune') {
            $classes[] = 'assistant--anim-' . $this->animation();
        }

        return implode(' ', $classes);
    }

    /**
     * Les jetons CSS de la bulle, en style en ligne sur le conteneur.
     *
     * En ligne et non dans la feuille : ces valeurs viennent du back-office,
     * et site.css est un fichier statique mis en cache. En ligne et non dans
     * le bloc `:root` du gabarit non plus — elles ne concernent qu'un élément,
     * et le bloc racine appartient à la charte.
     */
    public function style(): string
    {
        [$r, $v, $b] = Charte::versRvb($this->fond);

        return '--bulle-fond:' . $this->fond
            . ';--bulle-fond-rgb:' . $r . ',' . $v . ',' . $b
            . ';--bulle-texte:' . $this->texte
            . ';--bulle-taille:' . $this->taille() . 'px';
    }

    // ------------------------------------------------------------ résolution

    /**
     * Éclaircit ou assombrit la couleur de texte jusqu'au seuil.
     *
     * La teinte et la saturation du choix sont conservées : c'est ce qui fait
     * qu'un bordeaux corrigé reste un bordeaux, et non un noir.
     *
     * Le sens est choisi avant de chercher. On part du côté où la couleur
     * penche déjà — un texte plus clair que son fond s'éclaircit — et l'on
     * bascule si ce côté ne peut pas aboutir. Il aboutit toujours d'un côté
     * ou de l'autre : le blanc tient 4,5:1 sur tout fond de luminance
     * inférieure à 0,183, le noir sur tout fond au-dessus de 0,175, et ces
     * deux intervalles se recouvrent.
     */
    private static function resoudreTexte(string $choisi, string $fond): string
    {
        if (Charte::rapport($choisi, $fond) >= self::CONTRASTE_MINI) {
            return $choisi;
        }

        [$h, $s] = Charte::versTsl($choisi);
        $eclaircir = Charte::luminance($choisi) >= Charte::luminance($fond);
        if (Charte::rapport($eclaircir ? '#ffffff' : '#000000', $fond) < self::CONTRASTE_MINI) {
            $eclaircir = !$eclaircir;
        }

        $depart = Charte::versTsl($choisi)[2];
        $sens = $eclaircir ? 1.0 : -1.0;
        for ($i = 0; $i <= 200; $i++) {
            $l = $depart + $sens * $i * 0.5;
            if ($l < 0.0 || $l > 100.0) {
                break;
            }
            $couleur = Charte::depuisTsl($h, $s, $l);
            if (Charte::rapport($couleur, $fond) >= self::CONTRASTE_MINI) {
                return $couleur;
            }
        }

        return $eclaircir ? '#ffffff' : '#000000';
    }

    /** @param mixed $valeur */
    private static function borner(mixed $valeur): int
    {
        $n = is_numeric($valeur) ? (int) round((float) $valeur) : self::TAILLE_DEFAUT;
        $n = max(self::TAILLE_MIN, min(self::TAILLE_MAX, $n));

        // Le pas est celui du curseur : une valeur saisie à la main entre deux
        // crans est ramenée au cran, pour que l'écran affiche ce qu'il sert.
        return self::TAILLE_MIN
            + (int) round(($n - self::TAILLE_MIN) / self::TAILLE_PAS) * self::TAILLE_PAS;
    }
}
