<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Str;

/** Catégories de métiers (gérables dans le back-office) et occasions. */
final class Categories
{
    private static ?array $cache = null;
    private static ?array $occ = null;

    public static function defaults(): array
    {
        return [
            ['slug' => 'dj', 'name' => 'DJ', 'one' => 'DJ', 'many' => 'DJ', 'color' => '#ff4f3a', 'emoji' => '🎧', 'visible' => true,
                'keywords' => ['dj', 'd j', 'disc jockey', 'disque jockey', 'discjockey', 'disquejockey', 'deejay', 'deejays', 'discomobile', 'disco mobile', 'vdj', 'platines', 'mixage', 'dj sax'],
                'tagline' => 'Mariages, anniversaires, soirées d\'entreprise : le son qui fait danser.'],
            ['slug' => 'animateur-soiree', 'name' => 'Animateur de soirée', 'one' => 'animateur de soirée', 'many' => 'animateurs de soirée', 'color' => '#ff9f43', 'emoji' => '🪩', 'visible' => true,
                'keywords' => ['animateur', 'animatrice', 'animateurs', 'animation de soiree', 'animation soiree', 'maitre de ceremonie', 'ambianceur', 'animation mariage', 'animation musicale', 'soiree dansante', 'soiree a theme'],
                'tagline' => 'Ambiance, jeux et micro : ils mènent la fête du début à la fin.'],
            ['slug' => 'orchestre-groupe-live', 'name' => 'Orchestre & groupe live', 'one' => 'orchestre ou groupe live', 'many' => 'orchestres et groupes live', 'color' => '#c8f560', 'emoji' => '🎸', 'visible' => true,
                'keywords' => ['orchestre', 'groupe', 'groupe live', 'groupe de musique', 'fanfare', 'brass band', 'batucada', 'quartet', 'quartette', 'trio', 'big band', 'bal', 'cover', 'reprises', 'live band', 'formation musicale', 'jazz band', 'rock', 'bandas', 'banda'],
                'tagline' => 'De la fanfare au big band : la musique jouée en vrai.'],
            ['slug' => 'chanteur-musicien', 'name' => 'Chanteur & musicien', 'one' => 'chanteur ou musicien', 'many' => 'chanteurs et musiciens', 'color' => '#7be0b0', 'emoji' => '🎷', 'visible' => true,
                'keywords' => ['chanteur', 'chanteuse', 'chanteurs', 'chanteuses', 'musicien', 'musicienne', 'pianiste', 'piano', 'saxophoniste', 'saxophone', 'sax', 'guitariste', 'violon', 'violoniste', 'accordeon', 'accordeoniste', 'harpiste', 'harpe', 'gospel', 'chorale', 'soul', 'jazz', 'jazz manouche', 'cornemuse', 'chant', 'crooner', 'ceremonie laique'],
                'tagline' => 'Voix, piano, sax ou violon pour une cérémonie ou un cocktail.'],
            ['slug' => 'magicien', 'name' => 'Magicien', 'one' => 'magicien', 'many' => 'magiciens', 'color' => '#8f7bff', 'emoji' => '🎩', 'visible' => true,
                'keywords' => ['magicien', 'magicienne', 'magie', 'illusionniste', 'illusion', 'mentaliste', 'close up', 'close-up', 'prestidigitateur', 'prestidigitation', 'tours de magie'],
                'tagline' => 'Close-up, grandes illusions et mentalisme : effet garanti.'],
            ['slug' => 'animation-enfants', 'name' => 'Animation enfants', 'one' => 'animateur pour enfants', 'many' => 'animateurs pour enfants', 'color' => '#ffd23f', 'emoji' => '🎈', 'visible' => true,
                'keywords' => ['enfant', 'enfants', 'clown', 'clowns', 'maquillage', 'maquilleuse', 'sculpture de ballons', 'ballons', 'structure gonflable', 'chateau gonflable', 'structures gonflables', 'jeux gonflables', 'mascotte', 'mascottes', 'marionnette', 'marionnettes', 'conteur', 'gouter', 'kids', 'bulles', 'pere noel', 'arbre de noel', 'spectacle enfant'],
                'tagline' => 'Clowns, ballons, maquillage, bulles : les enfants s\'en souviendront.'],
            ['slug' => 'karaoke', 'name' => 'Karaoké', 'one' => 'animateur karaoké', 'many' => 'animateurs karaoké', 'color' => '#5fd3ff', 'emoji' => '🎤', 'visible' => true,
                'keywords' => ['karaoke', 'karaok', 'blind test', 'quiz musical'],
                'tagline' => 'Écran géant, micros et milliers de titres pour chanter toute la nuit.'],
            ['slug' => 'photobooth', 'name' => 'Photobooth', 'one' => 'photobooth', 'many' => 'photobooths', 'color' => '#ff9ec7', 'emoji' => '📸', 'visible' => true,
                'keywords' => ['photobooth', 'photo booth', 'photomaton', 'borne photo', 'bornes photo', 'borne a selfie', 'selfie', 'miroir photo', 'photosharing', 'photo sharing', 'video booth', '360'],
                'tagline' => 'Bornes et miroirs photo, impressions illimitées, souvenirs immédiats.'],
            ['slug' => 'casino-jeux', 'name' => 'Casino & jeux', 'one' => 'animation casino et jeux', 'many' => 'animations casino et jeux', 'color' => '#ffffff', 'emoji' => '🎲', 'visible' => true,
                'keywords' => ['casino', 'croupier', 'croupiers', 'jeux', 'escape game', 'murder party', 'team building', 'quiz', 'jeux en bois', 'jeu de societe', 'poker', 'roulette'],
                'tagline' => 'Casino fictif, quiz, team building : le jeu au cœur de la soirée.'],
            ['slug' => 'caricaturiste', 'name' => 'Caricaturiste', 'one' => 'caricaturiste', 'many' => 'caricaturistes', 'color' => '#f3c98b', 'emoji' => '✏️', 'visible' => true,
                'keywords' => ['caricaturiste', 'caricature', 'caricatures', 'portraitiste', 'portrait minute', 'dessinateur', 'silhouettiste', 'calligraphe', 'calligraphie', 'tatouage ephemere', 'tatouages ephemeres', 'live painting', 'peintre', 'graffeur'],
                'tagline' => 'Des portraits croqués sur le vif, que les invités emportent.'],
            ['slug' => 'humoriste-imitateur', 'name' => 'Humoriste & imitateur', 'one' => 'humoriste ou imitateur', 'many' => 'humoristes et imitateurs', 'color' => '#c9b8ff', 'emoji' => '😂', 'visible' => true,
                'keywords' => ['humoriste', 'humour', 'imitateur', 'imitatrice', 'imitations', 'comique', 'ventriloque', 'one man show', 'stand up', 'sosie', 'faux serveur', 'faux serveurs', 'theatre', 'comedien', 'comedienne', 'improvisation'],
                'tagline' => 'Imitations, faux serveurs, ventriloquie : fous rires assurés.'],
            ['slug' => 'danse-spectacle', 'name' => 'Danse & spectacle', 'one' => 'artiste de spectacle', 'many' => 'artistes de spectacle', 'color' => '#ff6fb5', 'emoji' => '💃', 'visible' => true,
                'keywords' => ['danse', 'danseuse', 'danseur', 'danseuses', 'danseurs', 'capoeira', 'bresil', 'bresilienne', 'cabaret', 'revue', 'spectacle', 'cracheur de feu', 'jongleur', 'echassier', 'echassiers', 'cirque', 'acrobate', 'feu', 'pyrotechnie', 'feu d artifice', 'show', 'flamenco', 'orientale', 'bollywood', 'samba', 'latino'],
                'tagline' => 'Danseurs, cabaret, cirque et arts du feu pour un show inoubliable.'],
            ['slug' => 'traiteur-bar', 'name' => 'Bar, cocktails & food', 'one' => 'prestataire bar et food', 'many' => 'prestataires bar et food', 'color' => '#ffcf99', 'emoji' => '🍹', 'visible' => true,
                'keywords' => ['cocktail', 'cocktails', 'barman', 'barmans', 'bar a cocktails', 'mixologue', 'food truck', 'foodtruck', 'traiteur', 'crepes', 'crepe', 'churros', 'barbe a papa', 'glaces', 'bar a bonbons', 'candy bar', 'pop corn', 'buffet', 'cuisinier', 'chef a domicile'],
                'tagline' => 'Barmans, food trucks et douceurs pour régaler les invités.'],
            ['slug' => 'sonorisation-eclairage', 'name' => 'Sono, lumière & location', 'one' => 'prestataire sono, lumière ou location', 'many' => 'prestataires sono, lumière et location', 'color' => '#b8f2e6', 'emoji' => '💡', 'visible' => true,
                'keywords' => ['sonorisation', 'sono', 'eclairage', 'lumiere', 'lumieres', 'location sono', 'location de materiel', 'location materiel', 'materiel', 'videoprojection', 'video projection', 'ecran geant', 'son et lumiere', 'regie', 'technicien', 'scene', 'podium', 'animation video', 'vaisselle', 'mobilier', 'chapiteau', 'location de vaisselle', 'location de mobilier'],
                'tagline' => 'Son, lumière, vidéo, mobilier et matériel de réception à louer.'],
        ];
    }

    /** @return array<string,array> catégories indexées par slug, triées */
    public static function all(bool $withHidden = false): array
    {
        if (self::$cache === null) {
            $list = Store::doc('categories', self::defaults())->all();
            $out = [];
            foreach ($list as $i => $c) {
                if (empty($c['slug'])) {
                    continue;
                }
                $c['order'] ??= $i;
                $out[$c['slug']] = $c;
            }
            uasort($out, static fn ($a, $b) => ($a['order'] <=> $b['order']));
            self::$cache = $out;
        }
        return $withHidden ? self::$cache : array_filter(self::$cache, static fn ($c) => !empty($c['visible']));
    }

    public static function get(?string $slug): ?array
    {
        return $slug ? (self::all(true)[$slug] ?? null) : null;
    }

    public static function name(?string $slug): string
    {
        return self::get($slug)['name'] ?? '';
    }

    public static function color(?string $slug): string
    {
        return self::get($slug)['color'] ?? '#ffd23f';
    }

    public static function reset(): void
    {
        self::$cache = null;
        self::$occ = null;
    }

    /**
     * Classement automatique d'un pro selon ses textes (mots-clés pondérés).
     * @return string[] slugs, le plus probable en premier
     */
    public static function classify(array $texts): array
    {
        // $texts : ['tags' => '...', 'name' => '...', 'tagline' => '...', 'description' => '...']
        $weights = ['tags' => 5, 'name' => 4, 'tagline' => 3, 'description' => 1];
        $scores = [];
        foreach (self::all(true) as $slug => $cat) {
            $score = 0;
            foreach ($weights as $field => $w) {
                $t = ' ' . Str::norm((string) ($texts[$field] ?? '')) . ' ';
                if (trim($t) === '') {
                    continue;
                }
                foreach ($cat['keywords'] ?? [] as $kw) {
                    $k = Str::norm((string) $kw);
                    if ($k === '') {
                        continue;
                    }
                    $count = substr_count($t, ' ' . $k . ' ');
                    if ($count === 0 && strlen($k) >= 6) {
                        $count = substr_count($t, ' ' . $k); // préfixe : karaok, caricatur…
                    }
                    if ($count > 0) {
                        $score += $w * min($count, 3) * (str_contains($k, ' ') ? 1.5 : 1);
                    }
                }
            }
            if ($score > 0) {
                $scores[$slug] = $score;
            }
        }
        arsort($scores);
        if (!$scores) {
            return [];
        }
        $top = reset($scores);
        // On garde les catégories qui pèsent au moins 30 % de la principale (3 maximum).
        $out = [];
        foreach ($scores as $slug => $s) {
            if ($s >= max(3, $top * 0.3) && count($out) < 3) {
                $out[] = $slug;
            }
        }
        return $out ?: [array_key_first($scores)];
    }

    /** Catégories déduites d'un texte libre (recherche, demande de devis). */
    public static function detect(string $text, int $max = 3): array
    {
        $t = ' ' . Str::norm($text) . ' ';
        $scores = [];
        foreach (self::all() as $slug => $cat) {
            foreach ($cat['keywords'] ?? [] as $kw) {
                $k = Str::norm((string) $kw);
                if ($k !== '' && (str_contains($t, ' ' . $k . ' ') || (strlen($k) >= 6 && str_contains($t, ' ' . $k)))) {
                    $scores[$slug] = ($scores[$slug] ?? 0) + (str_contains($k, ' ') ? 2 : 1);
                }
            }
            if (str_contains($t, ' ' . Str::norm($cat['name']) . ' ')) {
                $scores[$slug] = ($scores[$slug] ?? 0) + 3;
            }
        }
        arsort($scores);
        return array_slice(array_keys($scores), 0, $max);
    }

    // --------------------------------------------------------------- occasions

    public static function occasionDefaults(): array
    {
        return [
            ['key' => 'mariage', 'slug' => 'animation-mariage', 'name' => 'Mariage', 'title' => 'Animation de mariage', 'emoji' => '💍', 'bg' => '#fff6e8', 'fg' => '#1c1233', 'tilt' => -1.5, 'hint' => 'DJ, groupes, photobooth',
                'keywords' => ['mariage', 'mariages', 'wedding', 'vin d honneur', 'noces', 'maries', 'ceremonie'], 'cats' => ['dj', 'orchestre-groupe-live', 'chanteur-musicien', 'photobooth', 'animateur-soiree']],
            ['key' => 'anniversaire', 'slug' => 'animation-anniversaire', 'name' => 'Anniversaire', 'title' => 'Animation d\'anniversaire', 'emoji' => '🎂', 'bg' => '#ffd23f', 'fg' => '#1c1233', 'tilt' => 1.2, 'hint' => 'Magiciens, clowns, maquillage',
                'keywords' => ['anniversaire', 'anniversaires', 'anniv', 'fete', 'enfant', 'enfants'], 'cats' => ['animation-enfants', 'magicien', 'dj', 'karaoke']],
            ['key' => 'seminaire', 'slug' => 'animation-soiree-entreprise', 'name' => 'Séminaire', 'title' => 'Animation de séminaire et soirée d\'entreprise', 'emoji' => '💼', 'bg' => '#c8f560', 'fg' => '#1c1233', 'tilt' => -0.8, 'hint' => 'Team building, casino, quiz',
                'keywords' => ['seminaire', 'entreprise', 'entreprises', 'team building', 'comite', 'ce', 'cse', 'arbre de noel', 'corporate', 'salon', 'inauguration', 'gala', 'soiree d entreprise'], 'cats' => ['casino-jeux', 'dj', 'magicien', 'humoriste-imitateur', 'sonorisation-eclairage']],
            ['key' => 'soiree-privee', 'slug' => 'animation-soiree-privee', 'name' => 'Soirée privée', 'title' => 'Animation de soirée privée', 'emoji' => '🪩', 'bg' => '#ff4f3a', 'fg' => '#fff6e8', 'tilt' => 1.6, 'hint' => 'Karaoké, DJ, cocktails',
                'keywords' => ['soiree', 'soirees', 'privee', 'fete', 'karaoke', 'cocktail', 'reveillon', 'nouvel an', 'soiree a theme', 'bal', 'evg', 'evjf', 'bapteme', 'communion'], 'cats' => ['dj', 'karaoke', 'traiteur-bar', 'animateur-soiree']],
        ];
    }

    /** @return array<string,array> occasions indexées par slug d'URL */
    public static function occasions(): array
    {
        if (self::$occ === null) {
            $out = [];
            foreach (Store::doc('occasions', self::occasionDefaults())->all() as $o) {
                if (!empty($o['slug'])) {
                    $out[$o['slug']] = $o;
                }
            }
            self::$occ = $out;
        }
        return self::$occ;
    }

    public static function occasion(string $slug): ?array
    {
        return self::occasions()[$slug] ?? null;
    }
}
