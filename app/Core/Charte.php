<?php
declare(strict_types=1);

namespace App\Core;

/**
 * La charte de couleurs, dérivée d'une seule teinte choisie par la mairie.
 *
 * Le socle pose une règle : « tout réglage laissé à la mairie doit avoir son
 * auditeur qui en force les bornes ». Un sélecteur de couleur libre est le
 * pire des réglages de ce point de vue — il touche les cent cinquante mesures
 * de contraste du site d'un seul clic. Une couleur posée telle quelle dans
 * `--bleu` rendrait invisible la moitié des libellés dès que quelqu'un
 * choisirait un jaune ou un rose pâle.
 *
 * D'où ce parti pris : **la mairie choisit une teinte, pas une palette.** Les
 * cinq tons de marque et les neutres teintés en sont dérivés ici, selon
 * exactement la méthode appliquée à la main pour Angeot :
 *
 *   · la TEINTE vient du choix, et se propage à tout — marque et neutres ;
 *   · la SATURATION vient du choix pour les tons de marque, bornée à
 *     [18 %, 55 %] ; les neutres gardent la leur, faible et fixe, sans quoi
 *     un choix vif rendrait criards les fonds sombres et le texte courant ;
 *   · la LUMINOSITÉ des tons de marque n'est pas choisie : elle est
 *     RÉSOLUE, ton par ton, jusqu'à atteindre le contraste que ce ton doit
 *     tenir sur le fond où il sert.
 *
 * C'est ce troisième point qui fait tout le travail. Le ton qui porte les
 * petits libellés sur le crème est assombri jusqu'à 6:1 sur ce crème-là,
 * quelle que soit la teinte ; celui des accents sur fond sombre est éclairci
 * jusqu'à 4,6:1 sur la plus claire des surfaces sombres du site. La mairie ne
 * peut donc pas produire une page illisible, et n'a rien à savoir du
 * contraste.
 *
 * `outils/verifs/couleur.py` force ce réglage aux quatre coins de la roue et
 * repasse les auditeurs de contraste : c'est lui qui prouve la propriété.
 */
final class Charte
{
    /** La teinte livrée : le bleu ardoise tiré du blason. */
    public const DEFAUT = '#456d8a';

    /**
     * Bornes de saturation des tons de marque.
     *
     * Sous 18 % la couleur cesse d'être une couleur et le site paraît en
     * panne ; au-delà de 55 % les aplats deviennent criards sur un site de
     * service public, et les grands chiffres du bandeau vibrent. La teinte,
     * elle, n'est pas bornée : c'est le seul degré de liberté réel.
     */
    private const SAT_MIN = 18.0;
    private const SAT_MAX = 55.0;

    /**
     * Les neutres teintés : saturation et luminosité fixes, teinte suivie.
     *
     * Ces valeurs sont celles de la charte d'Angeot, relevées une fois. Elles
     * ne se résolvent pas par contraste parce qu'elles sont, elles, les FONDS
     * sur lesquels le contraste se mesure — les résoudre l'une par l'autre
     * n'aurait pas de point fixe.
     *
     * @var array<string, array{float, float}> jeton => [saturation %, luminosité %]
     */
    private const NEUTRES = [
        'ardoise'      => [36.0, 17.0],
        'ardoise-2'    => [38.0, 13.0],
        'anthracite'   => [16.0, 16.0],
        'encre'        => [36.0, 17.0],
        'encre-2'      => [23.0, 24.0],
        'texte'        => [16.0, 27.0],
        'texte-doux'   => [11.0, 37.0],
        'ligne'        => [18.0, 88.0],
        'fond-teinte'  => [24.0, 96.0],
        'creme-pale'   => [24.0, 96.0],
        'creme-voile'  => [26.0, 92.0],
    ];

    /** Teinte choisie, en degrés. */
    private float $teinte;

    /** Saturation des tons de marque, en pourcentage, déjà bornée. */
    private float $saturation;

    public function __construct(string $hex = self::DEFAUT)
    {
        [$h, $s] = self::versTsl(self::normaliser($hex));
        $this->teinte     = $h;
        $this->saturation = max(self::SAT_MIN, min(self::SAT_MAX, $s));
    }

    public static function depuis(Parametres $parametres): self
    {
        return new self((string) $parametres->get('apparence.couleur', self::DEFAUT));
    }

    /**
     * Ramène une saisie à un hexadécimal à six chiffres, ou à la valeur
     * livrée. Un champ de couleur rend toujours `#rrggbb`, mais le fichier de
     * paramètres se recopie d'un site à l'autre et se modifie à la main.
     */
    public static function normaliser(string $hex): string
    {
        $hex = strtolower(trim($hex));
        if (preg_match('/^#?([0-9a-f]{3})$/', $hex, $m) === 1) {
            $hex = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
        }

        return preg_match('/^#[0-9a-f]{6}$/', $hex) === 1 ? $hex : self::DEFAUT;
    }

    /**
     * Toute la palette, jeton CSS => valeur.
     *
     * @return array<string, string>
     */
    public function jetons(): array
    {
        $n = [];
        foreach (self::NEUTRES as $nom => [$s, $l]) {
            $n[$nom] = self::depuisTsl($this->teinte, $s, $l);
        }

        // L'ordre compte : chaque ton se résout sur des fonds déjà connus.
        $fonce = $this->resoudre(33.0, [[ '#ffffff', 7.0 ]], false);

        // La couleur de marque porte du blanc en aplat et se pose sur le
        // crème : elle doit tenir les deux, donc on garde la plus sombre des
        // deux solutions.
        $marque = $this->resoudre(41.0, [['#ffffff', 4.5], [$n['fond-teinte'], 4.5]], false);

        $texte  = $this->resoudre(36.0, [[$n['fond-teinte'], 6.0]], false);

        // Les accents sur fond sombre : la contrainte est donnée par la plus
        // CLAIRE des surfaces sombres, pas par l'ardoise. S'y ajoute la tuile
        // d'élu, qui est l'ardoise éclaircie de 3 % de blanc.
        $tuile = self::composer('#ffffff', 0.03, $n['ardoise']);
        $clair = $this->resoudre(61.0, [
            [$n['ardoise'], 4.6], [$n['ardoise-2'], 4.6], [$n['anthracite'], 4.6],
            [$n['encre-2'], 4.6], [$tuile, 4.6],
        ], true);

        // La barre collante est translucide et floutée : au-dessus des
        // sections claires, son fond composité monte à rgb(86,90,93) — mesuré
        // au pixel, pas déduit. Ce ton sert aussi de texte sur la bande
        // « En ce moment », dont le fond est --bleu-fonce.
        $barre = $this->resoudre(83.0, [['#565a5d', 4.6], [$fonce, 4.6]], true);

        $lm = self::versTsl($marque)[2];

        return [
            'bleu'         => $marque,
            'bleu-fonce'   => $fonce,
            'bleu-texte'   => $texte,
            'bleu-clair'   => $clair,
            'bleu-barre'   => $barre,
            'bleu-chiffre' => $fonce,
            'bleu-voile'   => self::depuisTsl($this->teinte, $this->saturation, 94.0),
            'creme-pale'   => $n['creme-pale'],
            'creme-voile'  => $n['creme-voile'],
            'ardoise'      => $n['ardoise'],
            'ardoise-2'    => $n['ardoise-2'],
            'anthracite'   => $n['anthracite'],
            'encre'        => $n['encre'],
            'encre-2'      => $n['encre-2'],
            'texte'        => $n['texte'],
            'texte-doux'   => $n['texte-doux'],
            'ligne'        => $n['ligne'],
            'fond-teinte'  => $n['fond-teinte'],
            // Le dégradé porteur de texte : trois crans de la marque
            // assombrie, le plus clair étant la marque elle-même. Tous
            // tiennent donc au moins ce qu'elle tient avec le blanc.
            'degrade-bleu' => sprintf(
                'linear-gradient(120deg, %s 0%%, %s 55%%, %s 100%%)',
                self::depuisTsl($this->teinte, $this->saturation, max(0.0, $lm - 13.0)),
                self::depuisTsl($this->teinte, $this->saturation, max(0.0, $lm - 7.0)),
                $marque
            ),
        ];
    }

    /**
     * Le bloc `:root` à poser dans l'en-tête du document.
     *
     * Rien n'est écrit dans site.css : la feuille est un fichier statique,
     * mise en cache et partagée par toutes les pages, alors que ces valeurs
     * appartiennent au back-office et doivent s'appliquer au premier rendu,
     * sans clignotement. Les jetons `-rgb` accompagnent les couleurs qui
     * servent aussi en translucide.
     */
    public function styleRacine(): string
    {
        $jetons = $this->jetons();
        $lignes = [];
        foreach ($jetons as $nom => $valeur) {
            $lignes[] = '--' . $nom . ':' . $valeur;
        }
        foreach (['bleu', 'bleu-fonce', 'bleu-clair', 'bleu-barre',
                  'ardoise', 'ardoise-2', 'anthracite'] as $nom) {
            [$r, $v, $b] = self::versRvb($jetons[$nom]);
            $lignes[] = '--' . $nom . '-rgb:' . $r . ',' . $v . ',' . $b;
        }
        // Deux voiles, qui ne servent jamais qu'en translucide et n'ont donc
        // pas de jeton de couleur pleine : la nuit posée sur les photos de
        // bandeau, et le cœur clair du filet lumineux de la barre.
        foreach (['nuit' => [27.0, 8.0], 'voile-clair' => [30.0, 95.0]] as $nom => [$sat, $lum]) {
            [$r, $v, $b] = self::versRvb(self::depuisTsl($this->teinte, $sat, $lum));
            $lignes[] = '--' . $nom . '-rgb:' . $r . ',' . $v . ',' . $b;
        }

        return ':root{' . implode(';', $lignes) . '}';
    }

    /** La couleur telle que la mairie l'a choisie, pour la réafficher. */
    public function choisie(): string
    {
        return self::depuisTsl($this->teinte, $this->saturation, 41.0);
    }

    // ------------------------------------------------------------ résolution

    /**
     * Cherche la luminosité qui satisfait toutes les contraintes.
     *
     * On part de la luminosité de référence — celle de la charte d'Angeot,
     * qui est la bonne pour une teinte de bleu moyen — et on s'en écarte par
     * pas d'un demi-point jusqu'à ce que TOUTES les cibles soient tenues. On
     * ne s'en écarte que dans un sens : un ton d'accent ne doit pas devenir
     * plus sombre que prévu parce qu'une teinte l'arrangeait.
     *
     * Le pas est fin et la boucle bornée : au pire deux cents essais, sur une
     * arithmétique de quelques multiplications. Cela s'exécute une fois par
     * rendu de page, et ne se mesure pas.
     *
     * @param list<array{string, float}> $cibles [fond, rapport minimal]
     * @param bool $eclaircir sens de la recherche
     */
    private function resoudre(float $depart, array $cibles, bool $eclaircir): string
    {
        $sens = $eclaircir ? 1.0 : -1.0;
        for ($i = 0; $i <= 200; $i++) {
            $l = $depart + $sens * $i * 0.5;
            if ($l < 0.0 || $l > 100.0) {
                break;
            }
            $couleur = self::depuisTsl($this->teinte, $this->saturation, $l);
            $tenu = true;
            foreach ($cibles as [$fond, $mini]) {
                if (self::rapport($couleur, $fond) < $mini) {
                    $tenu = false;
                    break;
                }
            }
            if ($tenu) {
                return $couleur;
            }
        }

        // Aucune luminosité ne convient : on rend l'extrême, et l'auditeur de
        // couleur le verra. Le cas n'existe pas dans les bornes de saturation
        // retenues — il est là parce qu'une constante peut changer.
        return self::depuisTsl($this->teinte, $this->saturation, $eclaircir ? 100.0 : 0.0);
    }

    // -------------------------------------------------------------- couleurs

    /* Les quatre fonctions qui suivent sont publiques parce que `Bulle` les
       réutilise : il ne doit exister qu'une arithmétique de contraste dans le
       code. Deux implémentations divergeraient au premier arrondi, et l'on ne
       saurait plus laquelle croire le jour où l'une trouve ce que l'autre
       rate. */

    /** @return array{float, float, float} teinte, saturation, luminosité */
    public static function versTsl(string $hex): array
    {
        [$r, $v, $b] = array_map(static fn(int $c): float => $c / 255, self::versRvb($hex));
        $max = max($r, $v, $b);
        $min = min($r, $v, $b);
        $l   = ($max + $min) / 2;
        if ($max === $min) {
            return [0.0, 0.0, $l * 100];
        }
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match (true) {
            $max === $r => ($v - $b) / $d + ($v < $b ? 6 : 0),
            $max === $v => ($b - $r) / $d + 2,
            default     => ($r - $v) / $d + 4,
        };

        return [$h * 60, $s * 100, $l * 100];
    }

    public static function depuisTsl(float $h, float $s, float $l): string
    {
        $h = fmod(fmod($h, 360) + 360, 360) / 360;
        $s = max(0.0, min(100.0, $s)) / 100;
        $l = max(0.0, min(100.0, $l)) / 100;

        if ($s === 0.0) {
            $c = (int) round($l * 255);
            return sprintf('#%02x%02x%02x', $c, $c, $c);
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $canal = static function (float $t) use ($p, $q): int {
            $t = fmod(fmod($t, 1.0) + 1.0, 1.0);
            $v = match (true) {
                $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
                $t < 1 / 2 => $q,
                $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
                default    => $p,
            };
            return (int) round($v * 255);
        };

        return sprintf('#%02x%02x%02x', $canal($h + 1 / 3), $canal($h), $canal($h - 1 / 3));
    }

    /** @return array{int, int, int} */
    public static function versRvb(string $hex): array
    {
        $hex = ltrim(self::normaliser($hex), '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /** Aplatit une couche translucide sur un fond opaque. */
    private static function composer(string $dessus, float $alpha, string $dessous): string
    {
        [$r1, $v1, $b1] = self::versRvb($dessus);
        [$r2, $v2, $b2] = self::versRvb($dessous);

        return sprintf(
            '#%02x%02x%02x',
            (int) round($r1 * $alpha + $r2 * (1 - $alpha)),
            (int) round($v1 * $alpha + $v2 * (1 - $alpha)),
            (int) round($b1 * $alpha + $b2 * (1 - $alpha))
        );
    }

    /** Luminance relative, au sens des règles d'accessibilité. */
    public static function luminance(string $hex): float
    {
        $somme = 0.0;
        foreach ([0.2126, 0.7152, 0.0722] as $i => $poids) {
            $c = self::versRvb($hex)[$i] / 255;
            $somme += $poids * ($c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4);
        }

        return $somme;
    }

    public static function rapport(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }
}
