<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Core\Settings;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Front\Explore;
use App\Front\Fiche;
use App\Front\Mosaic;
use App\Front\Unknown;

/**
 * Pages de synthèse racontées à voix haute : face-à-face, bilans (compétition, stade), saisons,
 * livre des records et chiffres du FCSM.
 *
 * Récit rédigé par l'IA, comme un historien qui raconte (accroche, introduction, récit en
 * paragraphes, conclusion), à partir des « faits » de la page : chiffres, premier et dernier
 * match, grands matchs et début de leur fiche, buteurs, séries, finales, bilan de la saison…
 * Essai sur une page (tout de suite, tarif normal), puis lancement pour tout le musée depuis
 * Système › Fiches audio (traitement groupé, moitié prix), en français et en anglais. Après ce
 * premier lancement seulement, chaque nuit, les récits manquants ou dont les chiffres ont changé
 * sont refaits (réglage « pages_ai »), puis lus par la voix IA de Gemini, enregistrée (réglage
 * « pages_voice », public/media/audio/pages/).
 * Tant que le récit de l'IA manque ou ne correspond plus aux chiffres (empreinte des faits), le
 * récit automatique est lu : construit à chaque affichage depuis les mêmes données, toujours à
 * jour et gratuit, par la voix du navigateur (bouton « Écouter », comme sur les fiches). La voix
 * enregistrée n'est jouée que si elle lit bien le récit affiché.
 *
 * État : storage/audio/pages/{page}.json = ['fr' => ['text', 'sig', 'model', 'at', 'audio' =>
 * ['file', 'th' (empreinte du texte lu), 'voice', 'model', 'dur', 'bytes', 'at']], 'en' => …].
 */
final class PageAudio
{
    /** Version de la consigne de rédaction : la changer fait refaire les récits rédigés par l'IA. */
    private const TEXT_VERSION = 1;
    public const LANGS = ['fr', 'en'];
    /** Classements du livre des records racontés (filtres de la page). */
    private const RECORD_COMPS = ['championnat', 'coupe-de-france', 'coupe-de-la-ligue', 'coupe-d-europe', 'amical'];

    public static string $dir = STORAGE_PATH . '/audio/pages';

    /**
     * Bouton « Écouter » (même forme que FicheAudio::forPage) pour des paragraphes, chacun une
     * liste de phrases ; null si l'audio est désactivé ou le texte vide.
     */
    public static function forPage(array $paras, bool $en): ?array
    {
        if (!FicheAudio::enabled()) {
            return null;
        }
        $out = [];
        foreach ($paras as $p) {
            $p = trim((string) preg_replace('/\s+/u', ' ', implode(' ', array_filter((array) $p, fn ($s) => trim((string) $s) !== ''))));
            if ($p !== '') {
                $out[] = self::speakable($p, $en);
            }
        }
        $text = FicheAudio::fitText(implode("\n\n", $out), FicheAudio::maxWords());
        if ($text === '') {
            return null;
        }
        return ['text' => $text, 'url' => null, 'lang' => FicheAudio::LANGS[$en ? 'en' : 'fr'], 'dur' => null,
            'secs' => (int) round(FicheAudio::words($text) / FicheAudio::WPM * 60)];
    }

    // ------------------------------------------------------------------ sur le site

    /** Face-à-face contre un club. $v : variables de la page (Explore::opponentData). */
    public static function opponent(string $club, string $name, array $v, bool $en): ?array
    {
        return self::choose('club-' . $club, fn () => self::clubFacts($name, $v), fn () => self::opponentAuto($name, $v, $en), $en);
    }

    /** Bilan d'une compétition (/bilans/coupe-de-france/…). */
    public static function competition(string $key, string $label, array $v, bool $en): ?array
    {
        return self::choose('bilan-' . $key, fn () => self::compFacts($key, $v), fn () => self::competitionAuto($key, $label, $v, $en), $en);
    }

    /** Bilan dans un stade (/bilans/stade-auguste-bonal/…). */
    public static function stadium(string $key, string $stadium, array $v, bool $en): ?array
    {
        return self::choose('bilan-stade-' . $key, fn () => self::stadiumFacts($key, Explore::stadiumName($key), $v), fn () => self::stadiumAuto($key, $stadium, $v, $en), $en);
    }

    /** Saison (/matchs/1987-1988/). */
    public static function season(array $v, ?string $division, bool $en): ?array
    {
        return self::choose('saison-' . $v['season'], fn () => self::seasonFacts($v, $division), fn () => self::seasonAuto($v, $division, $en), $en);
    }

    /** Livre des records : le classement affiché (catégorie, décennie, compétition). */
    public static function records(string $cat, ?int $decade, ?string $comp, string $title, string $unit, string $scope, array $rows, bool $en): ?array
    {
        $slug = self::recordsSlug($cat, $decade, $comp);
        return self::choose($slug, fn () => self::factsFor($slug), fn () => self::recordsAuto($cat, $title, $unit, $scope, $rows, $en), $en);
    }

    /** « Les chiffres du FCSM ». */
    public static function chiffres(array $chapters, int $count, bool $en): ?array
    {
        return self::choose('chiffres', fn () => self::factsFor('chiffres'), fn () => self::chiffresAuto($chapters, $count, $en), $en);
    }

    /** Récit d'une page : celui de l'IA s'il correspond toujours aux chiffres de la page, sinon l'automatique. */
    private static function choose(string $slug, callable $facts, callable $auto, bool $en): ?array
    {
        if (!FicheAudio::enabled()) {
            return null;
        }
        $lang = $en ? 'en' : 'fr';
        $st = self::stored($slug)[$lang] ?? null;
        if (is_array($st) && trim((string) ($st['text'] ?? '')) !== '' && ($f = $facts()) !== null && ($st['sig'] ?? '') === self::sig($f)) {
            $text = self::sayText((string) $st['text'], $en);
            if ($text !== '') {
                $a = self::voiceOf($st);
                return ['text' => $text, 'url' => $a ? '/media/' . $a['file'] : null, 'lang' => FicheAudio::LANGS[$lang], 'dur' => $a['dur'] ?? null,
                    'secs' => (int) round((float) ($a['dur'] ?? 0) ?: FicheAudio::words($text) / FicheAudio::WPM * 60), 'src' => 'ai'];
            }
        }
        $a = $auto();
        return $a ? $a + ['src' => 'auto'] : null;
    }

    // ------------------------------------------------------------------ récits de l'IA : état

    /** Récits enregistrés d'une page : ['fr' => ['text', 'sig', 'model', 'at'], 'en' => …]. */
    public static function stored(string $slug): array
    {
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            return [];
        }
        $s = JsonStore::read(self::$dir . '/' . $slug . '.json', []);
        return is_array($s) ? $s : [];
    }

    public static function saveText(string $slug, string $lang, string $text, string $sig, string $model = ''): void
    {
        if (!preg_match('/^[a-z0-9-]+$/', $slug) || !in_array($lang, self::LANGS, true)) {
            return;
        }
        @mkdir(self::$dir, 0775, true);
        JsonStore::update(self::$dir . '/' . $slug . '.json', function ($s) use ($lang, $text, $sig, $model) {
            $s = is_array($s) ? $s : [];
            // L'ancienne voix reste rangée (remplacée par la suivante) mais ne lit plus ce texte : plus jouée.
            $s[$lang] = ['text' => trim($text), 'sig' => $sig, 'model' => $model, 'at' => date('c')] + array_intersect_key($s[$lang] ?? [], ['audio' => 1]);
            return $s;
        }, []);
    }

    /** Voix IA enregistrée qui lit bien le récit rangé ($st : état d'une langue), sinon null. */
    private static function voiceOf(array $st): ?array
    {
        $a = $st['audio'] ?? null;
        if (!is_array($a) || empty($a['file']) || (int) ($a['v'] ?? 1) < FicheAudio::VOICE_VERSION || ($a['th'] ?? '') !== sha1(trim((string) ($st['text'] ?? '')))) {
            return null;
        }
        return is_file(FicheAudio::$media . '/' . $a['file']) ? $a : null;
    }

    /** Enregistre la voix IA d'un récit (MP3 si possible, sinon WAV) et remplace la précédente. */
    public static function storeVoice(string $slug, string $lang, string $pcm, int $rate, string $text, string $model, string $voice): ?array
    {
        if (!preg_match('/^[a-z0-9-]+$/', $slug) || !in_array($lang, self::LANGS, true)) {
            return null;
        }
        $base = sprintf('%s-%s-%s', $slug, $lang, substr(sha1($text . '|' . $voice . '|' . $model . '|' . strlen($pcm)), 0, 10));
        $entry = FicheAudio::encodeVoice($base, $pcm, $rate, 'audio/pages') + ['th' => sha1(trim($text)), 'voice' => $voice, 'model' => $model, 'v' => FicheAudio::VOICE_VERSION, 'at' => date('c')];
        $old = null;
        @mkdir(self::$dir, 0775, true);
        JsonStore::update(self::$dir . '/' . $slug . '.json', function ($s) use ($lang, $entry, &$old) {
            $s = is_array($s) ? $s : [];
            $old = $s[$lang]['audio']['file'] ?? null;
            $s[$lang]['audio'] = $entry;
            return $s;
        }, []);
        if ($old && $old !== $entry['file'] && str_starts_with((string) $old, 'audio/pages/')) {
            @unlink(FicheAudio::$media . '/' . $old);
        }
        return $entry;
    }

    /** Demande de voix du traitement groupé pour une clé « page:… » : le récit rangé, préparé pour la voix ; null s'il manque. */
    public static function speechRequest(string $key, string $voice): ?array
    {
        $k = self::parseKey($key);
        $text = $k ? trim((string) (self::stored($k[0])[$k[1]]['text'] ?? '')) : '';
        if ($text === '') {
            return null;
        }
        return [Gemini::speechRequest(self::sayText($text, $k[1] === 'en'), $voice), $text];
    }

    /** Le récit tel qu'il est affiché et dit : paragraphes, chaque paragraphe préparé pour la voix. */
    private static function sayText(string $text, bool $en): string
    {
        return implode("\n\n", array_filter(array_map(fn ($p) => self::speakable($p, $en), preg_split('/\n\s*\n/u', FicheAudio::paragraphs($text)) ?: []), fn ($p) => $p !== ''));
    }

    /** Empreinte des faits racontés (et de la consigne, de la durée maximale) : change si les chiffres changent. */
    public static function sig(array $facts): string
    {
        return substr(sha1((string) json_encode([self::TEXT_VERSION, FicheAudio::maxWords(), $facts], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 16);
    }

    /** Clé d'un traitement groupé : « page:club-nancy:fr ». */
    public static function key(string $slug, string $lang): string
    {
        return 'page:' . $slug . ':' . $lang;
    }

    public static function isKey(string $key): bool
    {
        return str_starts_with($key, 'page:');
    }

    /** [page, langue] d'une clé, ou null. */
    public static function parseKey(string $key): ?array
    {
        return preg_match('/^page:([a-z0-9-]+):(fr|en)$/', $key, $m) ? [$m[1], $m[2]] : null;
    }

    private static function recordsSlug(string $cat, ?int $decade, ?string $comp): string
    {
        return 'records-' . $cat . ($decade ? '-' . $decade : '') . ($comp ? '-' . $comp : '');
    }

    /** Toutes les pages qui peuvent se raconter (les vides sont écartées par factsFor()). */
    public static function slugs(): array
    {
        $d = Derived::get();
        $out = [];
        foreach ($d['clubs'] ?? [] as $club => $c) {
            if (($c['count'] ?? 0) > 0 && $club !== 'sochaux' && preg_match('/^[a-z0-9-]+$/', (string) $club)) {
                $out[] = 'club-' . $club;
            }
        }
        foreach ($d['seasons'] ?? [] as $season => $S) {
            if (!empty($S['matches']) && preg_match('/^\d{4}-\d{4}$/', (string) $season)) {
                $out[] = 'saison-' . $season;
            }
        }
        foreach (array_keys(Mosaic::COMPS) as $k) {
            $out[] = 'bilan-' . $k;
        }
        foreach (array_keys($d['stades'] ?? []) as $k) {
            if (preg_match('/^[a-z0-9-]+$/', (string) $k)) {
                $out[] = 'bilan-stade-' . $k;
            }
        }
        foreach (array_keys(Explore::RECORDS) as $cat) {
            foreach (array_merge([null], range(1920, (int) date('Y'), 10)) as $dec) {
                foreach (array_merge([null], self::RECORD_COMPS) as $comp) {
                    $out[] = self::recordsSlug($cat, $dec, $comp);
                }
            }
        }
        $out[] = 'chiffres';
        return $out;
    }

    /**
     * Faits d'une page, calculés comme sur le site mais toujours en français (la même empreinte
     * pour les deux langues) ; null si la page n'existe pas ou n'a rien à raconter.
     */
    public static function factsFor(string $slug): ?array
    {
        $prev = I18n::lang();
        I18n::set('fr');
        try {
            if (preg_match('/^club-([a-z0-9-]+)$/', $slug, $m)) {
                $v = Explore::opponentData($m[1]);
                return $v ? self::clubFacts(Fiche::clubName($m[1]), $v['vars']) : null;
            }
            if (preg_match('/^saison-(\d{4}-\d{4})$/', $slug, $m)) {
                $v = Explore::seasonData($m[1]);
                return $v ? self::seasonFacts($v['vars'], Derived::get()['seasons'][$m[1]]['division'] ?? null) : null;
            }
            if (preg_match('/^bilan-stade-([a-z0-9-]+)$/', $slug, $m)) {
                $v = Explore::bilanPage('stade-' . $m[1]);
                return $v ? self::stadiumFacts($m[1], Explore::stadiumName($m[1]), $v['vars']) : null;
            }
            if (preg_match('/^bilan-([a-z0-9-]+)$/', $slug, $m) && isset(Mosaic::COMPS[$m[1]])) {
                $v = Explore::bilanPage($m[1]);
                return $v ? self::compFacts($m[1], $v['vars']) : null;
            }
            if (preg_match('/^records-([a-z]+)(?:-(\d{4}))?(?:-([a-z-]+))?$/', $slug, $m) && isset(Explore::RECORDS[$m[1]])) {
                $comp = ($m[3] ?? '') !== '' ? $m[3] : null;
                if ($comp !== null && !in_array($comp, self::RECORD_COMPS, true)) {
                    return null;
                }
                return self::recordsFacts($m[1], ($m[2] ?? '') !== '' ? (int) $m[2] : null, $comp);
            }
            if ($slug === 'chiffres') {
                $all = Chiffres::all();
                return self::chiffresFacts($all['chapters'] ?? [], (int) ($all['count'] ?? 0));
            }
            return null;
        } finally {
            I18n::set($prev);
        }
    }

    // ------------------------------------------------------------------ récits de l'IA : rédaction

    /** Consigne et faits envoyés à Gemini pour raconter une page. */
    public static function aiPrompt(array $facts, string $lang): array
    {
        $en = $lang === 'en';
        [$subject, $focus] = self::subject($facts, $en);
        $max = FicheAudio::maxWords();
        $min = rtrim(rtrim(number_format(FicheAudio::maxMinutes(), 1, $en ? '.' : ',', ''), '0'), '.,');
        $system = $en
            ? "You are a historian of FC Sochaux-Montbéliard and a passionate storyteller. You tell, out loud, a page of the online museum Sochaux Rétro: $subject. This page is calculated from the museum’s match pages; the data below give its figures and its highlights. Your script will be read by a synthetic voice.\n"
                . "Bring it to life, as if you were telling it to supporters gathered around you:\n"
                . "- a hook that makes people want to listen;\n"
                . "- an introduction that sets out the subject and the era;\n"
                . "- the heart of the story in several paragraphs: $focus;\n"
                . "- a conclusion that puts things in perspective and leaves a strong image.\n"
                . "Rules:\n"
                . "- only facts found in the data: never invent a figure, a date, a name or an anecdote; do not recite every number, pick the most telling ones and tell them;\n"
                . "- the figures cover the matches recorded in the museum: say \"the museum holds…\" rather than \"Sochaux played in all…\";\n"
                . "- real sentences, varied and well punctuated, with natural transitions; paragraphs separated by a blank line; no list, no title, no emoji, no stage directions;\n"
                . "- write for the ear: scores as \"3–1\", clear dates (\"on 11 June 1988\");\n"
                . "- at most $max words (about $min minutes); the length follows the richness of the data;\n"
                . "- in English. Answer with the script only."
            : "Tu es historien du FC Sochaux-Montbéliard et conteur passionné. Tu racontes à voix haute une page du musée en ligne Sochaux Rétro : $subject. Cette page est calculée à partir des fiches de matchs du musée ; les données ci-dessous en donnent les chiffres et les moments marquants. Ton texte sera lu par une voix de synthèse.\n"
                . "Fais-la revivre, comme si tu la racontais à des supporters réunis autour de toi :\n"
                . "– une accroche qui donne envie d’écouter ;\n"
                . "– une introduction qui pose le sujet et l’époque ;\n"
                . "– le cœur du récit en plusieurs paragraphes : $focus ;\n"
                . "– une conclusion qui met en perspective et laisse une image forte.\n"
                . "Règles :\n"
                . "– uniquement des faits présents dans les données : n’invente rien, ni chiffre, ni date, ni nom, ni anecdote ; ne récite pas tous les chiffres, choisis les plus parlants et raconte-les ;\n"
                . "– les chiffres portent sur les matchs fichés dans le musée : dis « le musée compte… » plutôt que « Sochaux a joué en tout… » ;\n"
                . "– de vraies phrases, variées et bien ponctuées, avec des transitions naturelles ; paragraphes séparés par une ligne vide ; ni liste, ni titre, ni émoji, ni indication de mise en scène ;\n"
                . "– écris pour l’oreille : scores « 3 à 1 », dates claires (« le 11 juin 1988 ») ;\n"
                . "– au plus $max mots (environ $min minutes) ; la longueur suit la richesse des données ;\n"
                . "– en français. Réponds seulement par le texte à dire.";
        return [$system, (string) json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
    }

    /** [sujet, ce que le récit doit couvrir] selon le type de page. */
    private static function subject(array $f, bool $en): array
    {
        return match ($f['page'] ?? '') {
            'face-à-face' => $en
                ? ["the head-to-head between Sochaux and {$f['adversaire']}, all their meetings recorded in the museum", 'what the record says about this rivalry, the first meeting, the big matches (wide wins, painful defeats, crowds, finals), the scorers, the streaks, the latest chapter']
                : ["le face-à-face entre Sochaux et {$f['adversaire']}, toutes leurs rencontres fichées dans le musée", 'ce que dit le bilan de ce duel, la première rencontre, les grands matchs (larges victoires, défaites marquantes, affluences, finales), les buteurs, les séries, le dernier épisode'],
            'saison' => $en
                ? ["the {$f['saison']} season of FC Sochaux-Montbéliard", 'the context (division, coach), the league and cup campaigns, the big matches, the men (scorers, most used players), what the season review tells']
                : ["la saison {$f['saison']} du FC Sochaux-Montbéliard", 'le contexte (division, entraîneur), le parcours en championnat et en coupe, les grands matchs, les hommes (buteurs, joueurs les plus utilisés), ce que raconte le bilan de la saison'],
            'bilan d’une compétition' => $en
                ? ["the record of Sochaux in the {$f['compétition']}", 'the record, the runs season after season, the finals and the great exploits, the painful defeats, the scorers']
                : ["le bilan de Sochaux en {$f['compétition']}", 'le bilan, les parcours saison après saison, les finales et les exploits, les défaites marquantes, les buteurs'],
            'bilan dans un stade' => $en
                ? ["the record of Sochaux at {$f['stade']}", 'what this ground means, the record, the great nights, the crowds, the best years']
                : ["le bilan de Sochaux au {$f['stade']}", 'ce que représente ce stade, le bilan, les grandes soirées, les affluences, les années fastes'],
            'livre des records' => $en
                ? ["a ranking of the Sochaux record book: {$f['classement']} ({$f['portée']})", 'who leads the ranking and what these figures mean, then the names that follow']
                : ["un classement du livre des records du FCSM : {$f['classement']} ({$f['portée']})", 'qui domine le classement et ce que représentent ces chiffres, puis les noms qui suivent'],
            default => $en
                ? ["Sochaux in numbers, {$f['statistiques']} statistics calculated since 1929", 'a journey chapter by chapter through the most striking figures, with the names and dates behind them']
                : ["les chiffres du FCSM, {$f['statistiques']} statistiques calculées depuis 1929", 'un parcours chapitre par chapitre à travers les chiffres les plus marquants, avec les noms et les dates qui les accompagnent'],
        };
    }

    /** Demande du traitement groupé pour une clé « page:… », et l'empreinte des faits ; null si la page n'a rien à raconter. */
    public static function request(string $key, string $model): ?array
    {
        $k = self::parseKey($key);
        $facts = $k ? self::factsFor($k[0]) : null;
        if (!$facts) {
            return null;
        }
        [$system, $user] = self::aiPrompt($facts, $k[1]);
        return [Gemini::requestBody([['role' => 'user', 'text' => $user]], $system, ['temperature' => 0.6, 'max_tokens' => FicheAudio::aiTokens()], $model), self::sig($facts)];
    }

    /**
     * Ce qu'il reste à faire : récits manquants ou dont les chiffres ont changé (tous si $redo),
     * voix IA manquantes des récits à jour. ['keys' => récits à rédiger, 'voices' => voix à
     * enregistrer (clés « page:… »), 'pages' => pages qui se racontent].
     */
    public static function plan(bool $redo = false, ?array $only = null): array
    {
        $keys = [];
        $voices = [];
        $n = 0;
        foreach ($only ?? self::slugs() as $slug) {
            $facts = self::factsFor($slug);
            if (!$facts) {
                continue;
            }
            $n++;
            $sig = self::sig($facts);
            $st = self::stored($slug);
            foreach (self::LANGS as $lang) {
                if ($redo || ($st[$lang]['sig'] ?? '') !== $sig || trim((string) ($st[$lang]['text'] ?? '')) === '') {
                    $keys[] = self::key($slug, $lang);
                } elseif (!self::voiceOf($st[$lang])) {
                    $voices[] = self::key($slug, $lang);
                }
            }
        }
        if ($only === null) {
            @mkdir(self::$dir, 0775, true);
            JsonStore::write(dirname(self::$dir) . '/pages-plan.json', ['at' => time(), 'pages' => $n, 'todo' => $redo ? null : count($keys), 'voices' => $redo ? null : count($voices)]);
        }
        return ['keys' => $keys, 'voices' => $voices, 'pages' => $n];
    }

    /** Dernier calcul de ce qu'il reste à rédiger : ['at', 'pages', 'todo'] ou null. */
    public static function lastPlan(): ?array
    {
        $p = JsonStore::read(dirname(self::$dir) . '/pages-plan.json', null);
        return is_array($p) ? $p : null;
    }

    /** Coût estimé de $n voix IA de récits (environ 250 mots en moyenne), en traitement groupé. */
    public static function voiceEstimate(int $n): array
    {
        $e = FicheAudio::estimate(0, $n, min(250.0, FicheAudio::maxWords() * 0.6), true);
        return ['usd' => $e['usd'], 'eur' => $e['eur']];
    }

    /** Coût estimé de $n récits en traitement groupé (moitié prix) : faits d'environ 3 000 jetons. */
    public static function estimate(int $n): array
    {
        $p = AiCosts::price(Gemini::ready() ? FicheAudio::textModel() : 'gemini-2.5-flash-lite');
        $usd = $n * (3000 * $p['in'] + (FicheAudio::maxWords() * 0.8 * 1.7 + 60) * $p['out']) / 1e6 / 2;
        return ['usd' => $usd, 'eur' => AiCosts::eur($usd)];
    }

    /**
     * Confie au traitement groupé les récits à rédiger, puis leurs voix IA, et les voix manquantes
     * des récits déjà à jour (réglage « pages_voice », ou $voices). Envoyés par la tâche planifiée.
     */
    public static function launch(bool $redo, ?array $user, ?array $only = null, ?bool $voices = null): array
    {
        $voices ??= self::voicesOn();
        $plan = self::plan($redo, $only);
        $jobs = $plan['keys'] ? FicheAudio::queueTexts($plan['keys'], $user, true, $voices) : 0;
        $v = $voices ? $plan['voices'] : [];
        $jobs += $v ? FicheAudio::queueVoices($v, $user, true) : 0;
        return ['text' => count($plan['keys']), 'voice' => count($v) + ($voices ? count($plan['keys']) : 0), 'jobs' => $jobs, 'pages' => $plan['pages']];
    }

    /**
     * Essai sur une page, tout de suite (tarif normal) : récit rédigé par l'IA, puis sa voix IA si
     * $voice. ['text', 'voice' => voix enregistrée ou null].
     */
    public static function tryPage(string $slug, string $lang, bool $voice): array
    {
        $facts = self::factsFor($slug);
        if (!$facts) {
            throw new \RuntimeException('Cette page n’a rien à raconter (aucun match fiché).');
        }
        [$system, $user] = self::aiPrompt($facts, $lang);
        $model = FicheAudio::textModel();
        $g = Gemini::generate([['role' => 'user', 'text' => $user]], $system, ['model' => $model, 'temperature' => 0.6, 'max_tokens' => FicheAudio::aiTokens(), 'for' => 'audio', 'ref' => 'page:' . $slug]);
        $text = FicheAudio::cleanAi((string) $g['text']);
        if ($text === '') {
            throw new \RuntimeException('Gemini n’a pas rédigé de récit.');
        }
        self::saveText($slug, $lang, $text, self::sig($facts), $model);
        $out = ['text' => $text, 'voice' => null];
        if ($voice) {
            $r = Gemini::speech(self::sayText($text, $lang === 'en'), FicheAudio::voice(), '', 'page:' . $slug);
            $out['voice'] = self::storeVoice($slug, $lang, $r['pcm'], $r['rate'], $text, (string) $r['model'], FicheAudio::voice());
        }
        return $out;
    }

    /** [page, langue] d'une adresse du site (« /face-a-face/nancy/ », « /en/chiffres/ », « /records/?cat=series »), ou null. */
    public static function slugFromUrl(string $url): ?array
    {
        $p = parse_url(trim($url));
        $path = (string) ($p['path'] ?? '');
        parse_str((string) ($p['query'] ?? ''), $q);
        $lang = 'fr';
        if (preg_match('#^/en(/|$)#', $path)) {
            $lang = 'en';
            $path = substr($path, 3);
        }
        $path = '/' . trim($path, '/') . '/';
        if (preg_match('#^/face-a-face/([a-z0-9-]+)/$#', $path, $m)) {
            return ['club-' . $m[1], $lang];
        }
        if (preg_match('#^/matchs/(\d{4}-\d{4})/$#', $path, $m)) {
            return ['saison-' . $m[1], $lang];
        }
        if (preg_match('#^/bilans/([a-z0-9-]+)/$#', $path, $m)) {
            return ['bilan-' . $m[1], $lang];
        }
        if ($path === '/records/') {
            $cat = (string) ($q['cat'] ?? 'buteurs');
            $dec = preg_match('/^(19|20)\d0$/', (string) ($q['decennie'] ?? '')) ? (int) $q['decennie'] : null;
            $comp = in_array($q['comp'] ?? '', self::RECORD_COMPS, true) ? (string) $q['comp'] : null;
            return isset(Explore::RECORDS[$cat]) ? [self::recordsSlug($cat, $dec, $comp), $lang] : null;
        }
        return $path === '/chiffres/' ? ['chiffres', $lang] : null;
    }

    /** Adresse d'une page sur le site. */
    public static function urlFor(string $slug, string $lang): string
    {
        $pre = $lang === 'en' ? '/en' : '';
        if (preg_match('/^records-([a-z]+)(?:-(\d{4}))?(?:-([a-z-]+))?$/', $slug, $m)) {
            $q = array_filter(['cat' => $m[1] === 'buteurs' ? null : $m[1], 'decennie' => ($m[2] ?? '') ?: null, 'comp' => ($m[3] ?? '') ?: null]);
            return $pre . '/records/' . ($q ? '?' . http_build_query($q) : '');
        }
        return $pre . match (true) {
            str_starts_with($slug, 'club-') => '/face-a-face/' . substr($slug, 5) . '/',
            str_starts_with($slug, 'saison-') => '/matchs/' . substr($slug, 7) . '/',
            str_starts_with($slug, 'bilan-') => '/bilans/' . substr($slug, 6) . '/',
            default => '/chiffres/',
        };
    }

    /**
     * La rédaction de nuit ne commence qu'après le premier lancement pour tout le musée (Système ›
     * Fiches audio) : rien n'est dépensé tant que l'administrateur n'a pas essayé puis validé.
     */
    public static function activated(): bool
    {
        return !empty(JsonStore::read(dirname(self::$dir) . '/pages-etat.json', [])['activated']);
    }

    public static function activate(?array $user): void
    {
        @mkdir(dirname(self::$dir), 0775, true);
        JsonStore::write(dirname(self::$dir) . '/pages-etat.json', ['activated' => date('c'), 'by' => (string) ($user['name'] ?? 'Inconnu')]);
    }

    /** Voix IA pour les récits des pages (Réglages › Fiches audio). */
    public static function voicesOn(): bool
    {
        return (bool) Settings::get('audio.pages_voice', true);
    }

    /** Récits rédigés par l'IA et voix IA qui les lisent, par langue ; place des voix. */
    public static function stats(): array
    {
        $out = ['fr' => 0, 'en' => 0, 'voice_fr' => 0, 'voice_en' => 0, 'bytes' => 0, 'wav' => 0];
        foreach (glob(self::$dir . '/*.json') ?: [] as $f) {
            $s = JsonStore::read($f, []);
            foreach (self::LANGS as $lang) {
                if (trim((string) ($s[$lang]['text'] ?? '')) !== '') {
                    $out[$lang]++;
                    if ($a = self::voiceOf($s[$lang])) {
                        $out['voice_' . $lang]++;
                        $out['bytes'] += (int) ($a['bytes'] ?? 0);
                        $out['wav'] += str_ends_with((string) $a['file'], '.wav') ? 1 : 0;
                    }
                }
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ récits de l'IA : faits

    /** Un match dit à l'IA : date, compétition, tour, lieu, score (domicile d'abord), résultat, affluence, début de sa fiche. */
    private static function matchFacts(?array $x, bool $story = false): ?array
    {
        if (!$x) {
            return null;
        }
        $f = [
            'date' => (string) $x['date'],
            'compétition' => trim((string) (($x['label'] ?? '') ?: ($x['comp'] ?? ''))),
            'tour' => self::round((string) ($x['round'] ?? ''), false) ?: null,
            'adversaire' => (string) ($x['opp'] ?? ''),
            'lieu' => self::rank((string) ($x['round'] ?? '')) === 100 && !empty($x['stade']) ? Explore::stadiumName((string) $x['stade']) . ' (terrain neutre)'
                : (!empty($x['sh']) ? (($x['stade'] ?? '') === 'auguste-bonal' ? 'à domicile, au stade Auguste-Bonal' : 'à domicile') : 'à l’extérieur'),
            'score' => $x['us'] === null || $x['us'] === '' ? null
                : $x['home'] . ' ' . (!empty($x['sh']) ? $x['us'] : $x['them']) . '-' . (!empty($x['sh']) ? $x['them'] : $x['us']) . ' ' . $x['away'],
            'résultat' => ['V' => 'victoire de Sochaux', 'N' => 'match nul', 'D' => 'défaite de Sochaux'][$x['result'] ?? ''] ?? null,
        ];
        $extra = mb_strtolower((string) ($x['extra'] ?? ''));
        if (preg_match('/\ba\.?\s?p\b|prol/u', $extra)) {
            $f['prolongation'] = 'oui';
        }
        if (str_contains($extra, 'tab') && preg_match('/(\d+)\s*-\s*(\d+)/', $extra, $m)) {
            $f['tirs au but'] = 'remportés par ' . (($x['result'] ?? '') === 'V' ? 'Sochaux' : $x['opp']) . ', ' . max((int) $m[1], (int) $m[2]) . '-' . min((int) $m[1], (int) $m[2]);
        }
        if (($x['spectators'] ?? 0) > 0) {
            $f['spectateurs'] = (int) $x['spectators'];
        }
        if ($story && ($doc = Fiches::get((int) $x['id']))) {
            $txt = self::plainOf((string) ($doc['intro'] ?? ''));
            if ($txt === '') {
                $txt = self::plainOf((string) ($doc['sections'][0]['html'] ?? ''));
            }
            if ($txt !== '') {
                $f['début de sa fiche'] = mb_strimwidth($txt, 0, 700, '…');
            }
        }
        return array_filter($f, fn ($v) => $v !== null && $v !== '');
    }

    /** Une ligne par match : « 1988-06-11 · Coupe de France, finale · Metz 1-1 Sochaux · défaite (tirs au but) ». */
    private static function line(array $x): string
    {
        $f = self::matchFacts($x);
        return implode(' · ', array_filter([
            $f['date'], $f['compétition'] . (isset($f['tour']) ? ', ' . $f['tour'] : ''), $f['score'] ?? 'score inconnu',
            ['V' => 'victoire', 'N' => 'nul', 'D' => 'défaite'][$x['result'] ?? ''] ?? null,
            isset($f['tirs au but']) ? 'tirs au but ' . $f['tirs au but'] : (isset($f['prolongation']) ? 'après prolongation' : null),
        ]));
    }

    private static function plainOf(string $html): string
    {
        // « xx » de l'ancien site (information inconnue) jamais transmis.
        return trim((string) preg_replace('/\s+/u', ' ', Unknown::text(plain($html))));
    }

    private static function vndFacts(int $V, int $N, int $D): array
    {
        return ['victoires' => $V, 'nuls' => $N, 'défaites' => $D];
    }

    /** [nom => buts] des meilleurs buteurs sochaliens d'une série de matchs. */
    private static function scorerFacts(array $ids, int $n): array
    {
        $out = [];
        foreach (self::scorers($ids, $n) as $s) {
            $out[$s['name']] = $s['g'];
        }
        return $out;
    }

    private static function clubFacts(string $name, array $v): ?array
    {
        $chrono = self::chrono($v['list'] ?? []);
        if (!$chrono) {
            return null;
        }
        $t = $v['t'];
        $last = $chrono[count($chrono) - 1];
        [$best, $worst, $crowd] = self::extremes($chrono);
        $home = array_values(array_filter($chrono, fn ($x) => !empty($x['sh'])));
        $away = array_values(array_filter($chrono, fn ($x) => empty($x['sh'])));
        $run = self::unbeaten($chrono);
        return array_filter([
            'page' => 'face-à-face',
            'adversaire' => $name,
            'matchs fichés dans le musée' => (int) $t['count'],
            'période' => self::year($chrono[0]) . '-' . self::year($last),
            'résultats de Sochaux' => self::vndFacts((int) $t['V'], (int) $t['N'], (int) $t['D']),
            'buts' => ['marqués par Sochaux' => (int) $t['gf'], 'encaissés par Sochaux' => (int) $t['ga']],
            'à domicile' => $home ? self::vndFacts(...self::vnd($home)) : null,
            'à l’extérieur' => $away ? self::vndFacts(...self::vnd($away)) : null,
            'compétitions' => $v['comps'] ?? [],
            'premier match' => self::matchFacts($chrono[0], true),
            'dernier match' => count($chrono) > 1 ? self::matchFacts($last, true) : null,
            'plus large victoire' => self::matchFacts($best, true),
            'plus lourde défaite' => self::matchFacts($worst, true),
            'plus forte affluence' => self::matchFacts($crowd),
            'meilleurs buteurs sochaliens' => self::scorerFacts(array_column($chrono, 'id'), 5),
            'plus longue série sans défaite' => $run ? ['matchs' => $run['n'], 'du' => $run['from']['date'], 'au' => $run['to']['date']] : null,
            'tous les matchs' => count($chrono) <= 80 ? array_map([self::class, 'line'], $chrono) : null,
        ], fn ($x) => $x !== null && $x !== []);
    }

    private static function seasonFacts(array $v, ?string $division): ?array
    {
        $matches = self::chrono($v['matches'] ?? []);
        if (!$matches) {
            return null;
        }
        [$V, $N, $D] = self::vnd($matches);
        $gf = $ga = 0;
        foreach ($matches as $x) {
            if ($x['us'] !== null && $x['us'] !== '') {
                $gf += (int) $x['us'];
                $ga += (int) $x['them'];
            }
        }
        [$best, $worst, $crowd] = self::extremes($matches);
        $cups = [];
        foreach ($matches as $x) {
            if (!in_array($x['comp'], ['Championnat', 'Amical'], true)) {
                $cups[trim((string) (($x['label'] ?? '') ?: $x['comp']))][] = self::line($x);
            }
        }
        $squad = $v['squad'] ?? [];
        usort($squad, fn ($a, $b) => $b['mj'] <=> $a['mj']);
        $review = null;
        if (!empty($v['bilan']['id']) && ($doc = Fiches::get((int) $v['bilan']['id']))) {
            $txt = self::plainOf((string) ($doc['intro'] ?? '') . "\n" . implode("\n", array_map(fn ($sec) => (string) ($sec['html'] ?? ''), $doc['sections'] ?? [])));
            $review = $txt !== '' ? ['titre' => (string) $doc['title'], 'texte' => mb_strimwidth($txt, 0, 6000, '…')] : null;
        }
        return array_filter([
            'page' => 'saison',
            'saison' => (string) $v['season'],
            'division' => $division,
            'saison en cours' => !empty($v['current']) ? 'oui' : null,
            'matchs fichés dans le musée' => count($v['matches'] ?? []),
            'résultats' => self::vndFacts($V, $N, $D),
            'buts' => ['marqués' => $gf, 'encaissés' => $ga],
            'entraîneurs' => array_column(array_map(fn ($c) => ['n' => $c['name'], 'm' => $c['n']], $v['coaches'] ?? []), 'm', 'n'),
            'meilleurs buteurs' => array_column(array_map(fn ($c) => ['n' => $c['name'], 'g' => $c['g']], array_slice($v['scorers'] ?? [], 0, 8)), 'g', 'n'),
            'joueurs les plus utilisés (matchs)' => array_column(array_map(fn ($c) => ['n' => $c['name'], 'm' => $c['mj']], array_slice($squad, 0, 6)), 'm', 'n'),
            'coupes' => $cups,
            'plus large victoire' => self::matchFacts($best, true),
            'plus lourde défaite' => self::matchFacts($worst, true),
            'plus forte affluence' => self::matchFacts($crowd),
            'tous les matchs' => count($matches) <= 80 ? array_map([self::class, 'line'], $matches) : null,
            'bilan de la saison (fiche du musée)' => $review,
        ], fn ($x) => $x !== null && $x !== []);
    }

    private static function compFacts(string $key, array $v): ?array
    {
        $chrono = self::chrono($v['list'] ?? []);
        if (!$chrono || !isset(Mosaic::COMPS[$key])) {
            return null;
        }
        $t = $v['t'];
        [$best, $worst, $crowd] = self::extremes($chrono);
        $groups = $v['groups'] ?? [];
        usort($groups, fn ($a, $b) => strcmp((string) $a['key'], (string) $b['key']));
        $seasons = [];
        foreach ($groups as $g) {
            $s = self::vndFacts((int) $g['V'], (int) $g['N'], (int) $g['D']) + ['matchs' => (int) $g['n']];
            if ($key !== 'championnat' && !empty($g['last'])) {
                $s['dernier match'] = self::line($g['last']);
            }
            $seasons[(string) $g['key']] = $s;
        }
        $finals = array_values(array_filter($chrono, fn ($x) => self::rank((string) ($x['round'] ?? '')) === 100));
        return array_filter([
            'page' => 'bilan d’une compétition',
            'compétition' => Mosaic::COMPS[$key][0],
            'matchs fichés dans le musée' => (int) $t['count'],
            'période' => self::year($chrono[0]) . '-' . self::year($chrono[count($chrono) - 1]),
            'résultats de Sochaux' => self::vndFacts((int) $t['V'], (int) $t['N'], (int) $t['D']),
            'buts' => ['marqués par Sochaux' => (int) $t['gf'], 'encaissés par Sochaux' => (int) $t['ga']],
            'saison par saison' => $seasons,
            'finales' => $finals ? array_map(fn ($x) => self::matchFacts($x, true), array_slice($finals, 0, 8)) : null,
            'premier match' => self::matchFacts($chrono[0], true),
            'dernier match' => self::matchFacts($chrono[count($chrono) - 1], true),
            'plus large victoire' => self::matchFacts($best, true),
            'plus lourde défaite' => self::matchFacts($worst, true),
            'plus forte affluence' => self::matchFacts($crowd),
            'meilleurs buteurs sochaliens' => self::scorerFacts(array_column($chrono, 'id'), 6),
        ], fn ($x) => $x !== null && $x !== []);
    }

    private static function stadiumFacts(string $key, string $name, array $v): ?array
    {
        $chrono = self::chrono($v['list'] ?? []);
        if (!$chrono) {
            return null;
        }
        $t = $v['t'];
        [$best, $worst, $crowd] = self::extremes($chrono);
        $decades = [];
        foreach ($v['groups'] ?? [] as $g) {
            $decades[(string) $g['key']] = self::vndFacts((int) $g['V'], (int) $g['N'], (int) $g['D']) + ['matchs' => (int) $g['n']];
        }
        ksort($decades);
        return array_filter([
            'page' => 'bilan dans un stade',
            'stade' => $key === 'auguste-bonal' ? 'stade Auguste-Bonal, à Montbéliard : le stade du FC Sochaux-Montbéliard' : $name,
            'matchs fichés dans le musée' => (int) $t['count'],
            'période' => self::year($chrono[0]) . '-' . self::year($chrono[count($chrono) - 1]),
            'résultats de Sochaux' => self::vndFacts((int) $t['V'], (int) $t['N'], (int) $t['D']),
            'buts' => ['marqués par Sochaux' => (int) $t['gf'], 'encaissés par Sochaux' => (int) $t['ga']],
            'décennie par décennie' => $decades,
            'premier match fiché' => self::matchFacts($chrono[0], true),
            'dernier match' => self::matchFacts($chrono[count($chrono) - 1], true),
            'plus large victoire' => self::matchFacts($best, true),
            'plus lourde défaite' => self::matchFacts($worst, true),
            'plus forte affluence' => self::matchFacts($crowd, true),
            'meilleurs buteurs sochaliens' => self::scorerFacts(array_column($chrono, 'id'), 5),
            'tous les matchs' => count($chrono) <= 40 ? array_map([self::class, 'line'], $chrono) : null,
        ], fn ($x) => $x !== null && $x !== []);
    }

    private static function recordsFacts(string $cat, ?int $decade, ?string $comp): ?array
    {
        $rows = Explore::recordRows($cat, $decade, $comp, 10);
        if (!$rows) {
            return null;
        }
        [, $title, $unit] = Explore::RECORDS[$cat];
        $lines = [];
        foreach ($rows as $i => $r) {
            $lines[] = array_filter(['rang' => $i + 1, 'nom' => (string) $r['name'], 'valeur' => (string) $r['v'], 'détail' => (string) ($r['meta'] ?? '')], fn ($x) => $x !== '');
        }
        return [
            'page' => 'livre des records',
            'classement' => $title,
            'portée' => ($decade ? 'années ' . $decade : 'toutes époques') . ', ' . ($comp ? Mosaic::COMPS[$comp][0] : 'toutes compétitions officielles'),
            'unité' => $unit,
            'classement détaillé' => $lines,
        ];
    }

    private static function chiffresFacts(array $chapters, int $count): ?array
    {
        $out = [];
        foreach ($chapters as $ch) {
            $stats = [];
            foreach ($ch['stats'] ?? [] as $st) {
                $stats[] = array_filter([
                    'chiffre' => (string) $st['label'],
                    'valeur' => trim($st['value'] . ' ' . ($st['unit'] ?? '')),
                    'qui ou quoi' => implode(', ', array_map(fn ($w) => (string) $w['name'], $st['who'] ?? [])),
                    'précision' => trim((string) ($st['text'] ?? '')),
                ], fn ($x) => $x !== '');
            }
            if ($stats) {
                $out[] = ['chapitre' => (string) $ch['title'], 'présentation' => (string) ($ch['intro'] ?? ''), 'chiffres' => $stats];
            }
        }
        return $out ? ['page' => 'les chiffres du FCSM', 'statistiques' => $count, 'chapitres' => $out] : null;
    }

    // ------------------------------------------------------------------ récits automatiques

    /** Face-à-face (récit automatique). */
    private static function opponentAuto(string $name, array $v, bool $en): ?array
    {
        $chrono = self::chrono($v['list'] ?? []);
        $t = $v['t'] ?? [];
        $n = (int) ($t['count'] ?? 0);
        if (!$chrono || $n === 0) {
            return null;
        }
        $first = $chrono[0];
        $last = $chrono[count($chrono) - 1];
        [$y1, $y2] = [self::year($first), self::year($last)];
        [$V, $N, $D] = [(int) $t['V'], (int) $t['N'], (int) $t['D']];

        if ($n === 1) {
            return self::forPage([[
                $en ? "Sochaux and $name have met only once, " . self::on($first, $en) . ', ' . self::inComp($first, $en) . ': ' . self::result($first, $en) . '.'
                    : "Sochaux et $name ne se sont affrontés qu’une seule fois, " . self::on($first, $en) . ', ' . self::inComp($first, $en) . ' : ' . self::result($first, $en) . '.',
                $en ? 'A single chapter, to relive in its match page.' : 'Un chapitre unique, à revivre dans sa fiche de match.',
            ]], $en);
        }

        $intro = [];
        if ($n >= 30) {
            $intro[] = $en ? "Between Sochaux and $name, it is a long story: $n meetings, from $y1 to $y2." : "Entre Sochaux et $name, c’est une longue histoire : $n rencontres, de $y1 à $y2.";
        } elseif ($n >= 10) {
            $intro[] = $en ? "Sochaux and $name have faced each other $n times, from $y1 to $y2." : "Sochaux et $name se sont affrontés $n fois, de $y1 à $y2.";
        } else {
            $intro[] = $en ? "Sochaux and $name have crossed paths only $n times" . ($y1 === $y2 ? ", in $y1." : ", between $y1 and $y2.")
                : "Sochaux et $name ne se sont croisés que $n fois" . ($y1 === $y2 ? ", en $y1." : ", entre $y1 et $y2.");
        }
        $rec = self::record($V, $N, $D, $en, true);
        $intro[] = match (self::trend($V, $N, $D)) {
            'domine' => $en ? "And the record leans clearly towards Sochaux: $rec." : "Et le bilan penche nettement du côté des Lionceaux : $rec.",
            'subit' => $en ? "$name have often hurt Sochaux: $rec." : "$name a souvent fait souffrir les Sochaliens : $rec.",
            'favorable' => $en ? "The record favours Sochaux: $rec." : "Le bilan est favorable aux Lionceaux : $rec.",
            'defavorable' => $en ? "The record favours $name: $rec." : "Le bilan est à l’avantage de $name : $rec.",
            'serre' => $en ? "The record could hardly be closer: $rec." : "Le bilan est très serré : $rec.",
            default => $en ? "The record is perfectly balanced: $rec." : "Le bilan est parfaitement équilibré : $rec.",
        };
        $intro[] = self::goals((int) $t['gf'], (int) $t['ga'], $en);

        // Domicile, extérieur, compétitions.
        $where = [];
        $home = array_values(array_filter($chrono, fn ($x) => !empty($x['sh'])));
        $away = array_values(array_filter($chrono, fn ($x) => empty($x['sh'])));
        if (count($home) >= 2 && count($away) >= 2) {
            [$hv, $hn, $hd] = self::vnd($home);
            [$av, $an, $ad] = self::vnd($away);
            $where[] = $en ? 'At home: ' . self::record($hv, $hn, $hd, true) . '. Away: ' . self::record($av, $an, $ad, true) . '.'
                : 'À domicile, ' . self::record($hv, $hn, $hd, false) . ' ; à l’extérieur, ' . self::record($av, $an, $ad, false) . '.';
        }
        $where[] = self::comps($v['comps'] ?? [], $en);

        // Les grands moments.
        $story = [];
        $story[] = $en ? 'It all began ' . self::on($first, $en) . ', ' . self::inComp($first, $en) . ': ' . self::result($first, $en) . '.'
            : 'Tout a commencé ' . self::on($first, $en) . ', ' . self::inComp($first, $en) . ' : ' . self::result($first, $en) . '.';
        [$best, $worst, $crowd] = self::extremes($chrono);
        if ($best && $best['id'] !== $first['id']) {
            $story[] = self::bestWin($best, $en, $name);
        }
        if ($worst && $worst['id'] !== $first['id']) {
            $story[] = self::worstLoss($worst, $en, $name);
        }
        if ($crowd) {
            $story[] = self::crowd($crowd, $en, $name);
        }
        $sc = self::scorers(array_column($chrono, 'id'));
        if ($sc && $sc[0]['g'] >= 2) {
            $story[] = self::scorerSentence($sc, $en, $name);
        }
        $run = self::unbeaten($chrono);
        if ($run && $n >= 6) {
            $story[] = $en ? "Sochaux even went {$run['n']} games unbeaten against $name, from " . self::date($run['from'], true) . ' to ' . self::date($run['to'], true) . '.'
                : "Les Lionceaux ont même enchaîné {$run['n']} matchs sans défaite contre $name, du " . self::date($run['from'], false) . ' au ' . self::date($run['to'], false) . '.';
        }

        $end = [];
        $end[] = $en ? 'The latest chapter was played ' . self::on($last, $en) . ', ' . self::inComp($last, $en) . ': ' . self::result($last, $en) . '.'
            : 'Le dernier épisode s’est joué ' . self::on($last, $en) . ', ' . self::inComp($last, $en) . ' : ' . self::result($last, $en) . '.';
        $end[] = match (self::trend($V, $N, $D)) {
            'domine' => $en ? 'An opponent Sochaux have often tamed: a fine page in the history of the club.' : 'Un adversaire que les Lionceaux ont souvent su dompter : une belle page de l’histoire du FCSM.',
            'favorable' => $en ? 'A fixture that has often smiled on the yellow and blue, and the story goes on.' : 'Un duel qui a souvent souri aux Jaune et Bleu, et l’histoire continue.',
            'subit', 'defavorable' => $en ? 'A tough opponent who has given Sochaux plenty of trouble: there is always a score to settle.' : 'Un adversaire coriace, qui a donné du fil à retordre aux Jaune et Bleu : une revanche est toujours à prendre.',
            default => $en ? 'A balanced rivalry, where nothing was ever decided in advance.' : 'Un duel équilibré, où rien n’a jamais été joué d’avance.',
        };
        return self::forPage([$intro, $where, $story, $end], $en);
    }

    // ------------------------------------------------------------------ bilans

    /** Bilan d'une compétition (/bilans/coupe-de-france/…). $label : nom affiché de la compétition. */
    private static function competitionAuto(string $key, string $label, array $v, bool $en): ?array
    {
        $chrono = self::chrono($v['list'] ?? []);
        $t = $v['t'] ?? [];
        $n = (int) ($t['count'] ?? 0);
        if (!$chrono || $n === 0) {
            return null;
        }
        [$y1, $y2] = [self::year($chrono[0]), self::year($chrono[count($chrono) - 1])];
        [$V, $N, $D] = [(int) $t['V'], (int) $t['N'], (int) $t['D']];
        $intro = [match ($key) {
            'championnat' => $en ? "The league is the club’s daily bread: $n matches recorded in the museum, from $y1 to $y2." : "Le championnat, c’est le quotidien du club : $n matchs fichés dans le musée, de $y1 à $y2.",
            'coupe-de-france' => $en ? "The Coupe de France, the competition of every upset: the museum holds $n Sochaux matches in it, from $y1 to $y2." : "La Coupe de France, l’épreuve de tous les exploits : le musée y compte $n matchs de Sochaux, de $y1 à $y2.",
            'coupe-d-europe' => $en ? "Europe and its great nights: $n European matches of Sochaux are recorded in the museum, from $y1 to $y2." : "L’Europe et ses grandes soirées : $n matchs européens du FCSM sont fichés dans le musée, de $y1 à $y2.",
            default => $en ? "$label: $n Sochaux matches recorded in the museum, from $y1 to $y2." : "$label : $n matchs de Sochaux fichés dans le musée, de $y1 à $y2.",
        }];
        $intro[] = ($en ? 'The record: ' : 'Le bilan : ') . self::record($V, $N, $D, $en) . '.';
        $intro[] = self::goals((int) $t['gf'], (int) $t['ga'], $en);

        $story = [];
        $groups = $v['groups'] ?? [];
        if ($key === 'championnat') {
            // La saison la plus victorieuse (au moins 10 matchs fichés).
            $top = null;
            foreach ($groups as $g) {
                if ($g['n'] >= 10 && (!$top || $g['V'] / $g['n'] > $top['V'] / $top['n'])) {
                    $top = $g;
                }
            }
            if ($top) {
                $story[] = $en ? 'The most successful season: ' . self::seasonLabel($top['key']) . ", with {$top['V']} wins in {$top['n']} matches recorded." : 'La saison la plus victorieuse : ' . self::seasonLabel($top['key']) . ", avec {$top['V']} victoires en {$top['n']} matchs fichés.";
            }
        } else {
            $count = count($groups);
            if ($count > 1) {
                $story[] = $en ? "Sochaux took part in it in $count seasons recorded in the museum." : "Sochaux y a pris part lors de $count saisons fichées dans le musée.";
            }
            // Le plus beau parcours : les finales, sinon le tour le plus avancé.
            $finals = array_values(array_filter($chrono, fn ($x) => self::rank((string) ($x['round'] ?? '')) === 100));
            if ($finals) {
                $k = count($finals);
                if ($k === 1) {
                    $f = $finals[0];
                    $story[] = $en ? 'Sochaux reached the final ' . self::on($f, true) . ", against {$f['opp']}: " . self::result($f, true) . '.'
                        : 'Sochaux a atteint la finale ' . self::on($f, false) . ", contre {$f['opp']} : " . self::result($f, false) . '.';
                } else {
                    $story[] = $en ? "Sochaux reached the final $k times." : "Sochaux a atteint la finale $k fois.";
                    foreach (array_slice($finals, 0, 4) as $f) {
                        $story[] = $en ? ucfirst(self::on($f, true)) . ", against {$f['opp']}: " . self::result($f, true) . '.'
                            : self::ucfirst(self::on($f, false)) . ", contre {$f['opp']} : " . self::result($f, false) . '.';
                    }
                }
            } else {
                $deep = null;
                foreach ($chrono as $x) {
                    if (($r = self::rank((string) ($x['round'] ?? ''))) > 0 && (!$deep || $r > self::rank((string) $deep['round']))) {
                        $deep = $x;
                    }
                }
                if ($deep && ($round = self::round((string) $deep['round'], $en)) !== '') {
                    $story[] = $en ? "The finest run went as far as the $round, in " . self::seasonLabel((string) $deep['season']) . ': ' . self::result($deep, true) . " against {$deep['opp']}."
                        : "Le plus beau parcours va jusqu’" . (ctype_digit($round[0]) ? 'au ' : 'en ') . "$round, en " . self::seasonLabel((string) $deep['season']) . ' : ' . self::result($deep, false) . " contre {$deep['opp']}.";
                }
            }
        }
        [$best, $worst, $crowd] = self::extremes($chrono);
        if ($best) {
            $story[] = self::bestWin($best, $en);
        }
        if ($worst) {
            $story[] = self::worstLoss($worst, $en);
        }
        if ($crowd) {
            $story[] = self::crowd($crowd, $en);
        }
        $sc = self::scorers(array_column($chrono, 'id'));
        if ($sc && $sc[0]['g'] >= 2) {
            $story[] = self::scorerSentence($sc, $en, null);
        }
        $end = [$en ? 'A competition to relive season after season, match by match, in the museum.' : 'Une épreuve à revivre saison après saison, match après match, dans le musée.'];
        return self::forPage([$intro, $story, $end], $en);
    }

    /** Bilan dans un stade (/bilans/stade-auguste-bonal/…). */
    private static function stadiumAuto(string $key, string $stadium, array $v, bool $en): ?array
    {
        $chrono = self::chrono($v['list'] ?? []);
        $t = $v['t'] ?? [];
        $n = (int) ($t['count'] ?? 0);
        if (!$chrono || $n === 0) {
            return null;
        }
        [$y1, $y2] = [self::year($chrono[0]), self::year($chrono[count($chrono) - 1])];
        [$V, $N, $D] = [(int) $t['V'], (int) $t['N'], (int) $t['D']];
        $bonal = $key === 'auguste-bonal';
        $at = self::stadiumAt($stadium, $en);
        $intro = [$bonal
            ? ($en ? "Stade Auguste-Bonal is the home of the Lionceaux: $n matches recorded in the museum, from $y1 to $y2." : "Le stade Auguste-Bonal, c’est la maison des Lionceaux : $n matchs fichés dans le musée, de $y1 à $y2.")
            : ($en ? ucfirst($at) . ", the museum holds $n Sochaux matches, from $y1 to $y2." : self::ucfirst($at) . ", le musée compte $n matchs de Sochaux, de $y1 à $y2.")];
        $intro[] = ($en ? 'The record: ' : 'Le bilan : ') . self::record($V, $N, $D, $en) . '.';
        $intro[] = self::goals((int) $t['gf'], (int) $t['ga'], $en);

        $story = [];
        $first = $chrono[0];
        $story[] = $en ? 'The first match recorded here dates from ' . self::date($first, true) . ': ' . self::result($first, true, false) . " against {$first['opp']}."
            : 'Le premier match fiché ici date du ' . self::date($first, false) . ' : ' . self::result($first, false, false) . " contre {$first['opp']}.";
        // La décennie la plus heureuse (au moins 20 matchs fichés).
        $top = null;
        foreach ($v['groups'] ?? [] as $g) {
            if ($g['n'] >= 20 && is_numeric($g['key']) && (!$top || $g['V'] / $g['n'] > $top['V'] / $top['n'])) {
                $top = $g;
            }
        }
        if ($top) {
            $story[] = $en ? "The {$top['key']}s were the happiest years here: {$top['V']} wins in {$top['n']} matches."
                : "Les années {$top['key']} y furent les plus heureuses : {$top['V']} victoires en {$top['n']} matchs.";
        }
        [$best, $worst, $crowd] = self::extremes($chrono);
        if ($best) {
            $story[] = self::bestWin($best, $en);
        }
        if ($worst) {
            $story[] = self::worstLoss($worst, $en);
        }
        if ($crowd) {
            $story[] = self::crowd($crowd, $en);
        }
        $end = [$bonal
            ? ($en ? 'Bonal, where so many pages of the club’s history were written.' : 'Bonal, où se sont écrites tant de pages de l’histoire du FCSM.')
            : ($en ? 'Every match is in the museum, one match page at a time.' : 'Tous ces matchs sont à revivre, fiche après fiche, dans le musée.')];
        return self::forPage([$intro, $story, $end], $en);
    }

    // ------------------------------------------------------------------ saisons

    /** Saison (/matchs/1987-1988/). $v : variables de la page (Explore::seasonData). */
    private static function seasonAuto(array $v, ?string $division, bool $en): ?array
    {
        $matches = array_values(array_filter($v['matches'] ?? [], fn ($x) => !empty($x['date'])));
        if (!$matches) {
            return null;
        }
        $label = self::seasonLabel((string) $v['season']);
        [$V, $N, $D] = self::vnd($matches);
        $gf = $ga = 0;
        foreach ($matches as $x) {
            if ($x['us'] !== null) {
                $gf += (int) $x['us'];
                $ga += (int) $x['them'];
            }
        }
        $n = count($v['matches']);
        $intro = [];
        if (!empty($v['current'])) {
            $intro[] = $en ? "The $label season is under way" . ($division ? ", in $division." : '.') : "La saison $label est en cours" . ($division ? ", en $division." : '.');
        } else {
            $intro[] = $en ? "A look back at the $label season" . ($division ? ", played in $division." : '.') : "Retour sur la saison $label" . ($division ? ", vécue en $division." : '.');
        }
        $intro[] = $en ? "The museum holds $n " . ($n > 1 ? 'matches' : 'match') . ' of it: ' . self::record($V, $N, $D, true) . '.'
            : "Le musée en compte $n " . ($n > 1 ? 'matchs' : 'match') . ' : ' . self::record($V, $N, $D, false) . '.';
        $intro[] = self::goals($gf, $ga, $en);

        $men = [];
        $coaches = array_slice($v['coaches'] ?? [], 0, 3);
        if ($coaches) {
            $names = array_column($coaches, 'name');
            $men[] = ($en ? 'In the dugout: ' : 'Sur le banc : ') . self::join($names, $en) . '.';
        }
        $sc = array_slice($v['scorers'] ?? [], 0, 3);
        if ($sc) {
            $g = (int) $sc[0]['g'];
            $s0 = $en ? "The season’s top scorer is {$sc[0]['name']}, with $g " . ($g > 1 ? 'goals' : 'goal') : "Le meilleur buteur de la saison est {$sc[0]['name']}, avec $g " . ($g > 1 ? 'buts' : 'but');
            $rest = array_map(fn ($x) => $x['name'] . ' (' . $x['g'] . ')', array_slice($sc, 1));
            $men[] = $s0 . ($rest ? ($en ? ', ahead of ' : ', devant ') . self::join($rest, $en) : '') . '.';
        }
        $squad = $v['squad'] ?? [];
        usort($squad, fn ($a, $b) => $b['mj'] <=> $a['mj']);
        if ($squad && $squad[0]['mj'] >= 3) {
            $p = $squad[0];
            $men[] = $en ? "{$p['name']} was the most used player, with {$p['mj']} appearances." : "{$p['name']} est le joueur le plus utilisé, avec {$p['mj']} matchs.";
        }

        // Les coupes : jusqu'où ?
        $cups = [];
        $byComp = [];
        foreach ($matches as $x) {
            if (!in_array($x['comp'], ['Championnat', 'Amical'], true)) {
                $byComp[$x['comp']][] = $x;
            }
        }
        foreach ($byComp as $list) {
            $lastCup = $list[count($list) - 1];
            $cup = trim((string) (($lastCup['label'] ?? '') ?: $lastCup['comp']));
            $round = self::round((string) ($lastCup['round'] ?? ''), $en);
            $final = self::rank((string) ($lastCup['round'] ?? '')) === 100;
            if ($final && $lastCup['result'] === 'V') {
                $cups[] = $en ? "In the $cup, Sochaux went all the way: the final was won, " . self::result($lastCup, true) . " against {$lastCup['opp']}."
                    : "En $cup, l’aventure va jusqu’au bout : la finale est gagnée, " . self::result($lastCup, false) . " contre {$lastCup['opp']}.";
            } elseif ($lastCup['result'] === 'D' && $round !== '') {
                $cups[] = $en ? "In the $cup, the run ended in the $round: " . self::result($lastCup, true) . " against {$lastCup['opp']}."
                    : "En $cup, l’aventure s’arrête en $round : " . self::result($lastCup, false) . " contre {$lastCup['opp']}.";
            }
        }
        [$best, $worst] = self::extremes($matches);
        if ($best) {
            $cups[] = $en ? 'The biggest win: ' . self::score($best, true) . ' against ' . $best['opp'] . ', ' . self::on($best, true) . '.'
                : 'La plus large victoire : ' . self::score($best, false) . ' contre ' . $best['opp'] . ', ' . self::on($best, false) . '.';
        }
        if ($worst) {
            $cups[] = $en ? 'The heaviest defeat: ' . self::score($worst, true) . ' against ' . $worst['opp'] . ', ' . self::on($worst, true) . '.'
                : 'La plus lourde défaite : ' . self::score($worst, false) . ' contre ' . $worst['opp'] . ', ' . self::on($worst, false) . '.';
        }

        $end = [];
        if (!empty($v['bilan']['title'])) {
            $end[] = $en ? 'The full season review is told in the page “' . $v['bilan']['title'] . '”.' : 'Le bilan complet de la saison est raconté dans la fiche « ' . $v['bilan']['title'] . ' ».';
        }
        $played = $V + $N + $D;
        $end[] = match (true) {
            $played >= 15 && $V / $played >= 0.6 => $en ? 'A golden season, the kind people love to tell.' : 'Une saison faste, de celles qu’on aime raconter.',
            $played >= 15 && $V / $played <= 0.25 => $en ? 'A difficult season, but one that belongs to the story too.' : 'Une saison difficile, mais qui fait aussi partie de l’histoire.',
            default => $en ? 'A season to relive match by match in the museum.' : 'Une saison à revivre match après match dans le musée.',
        };
        return self::forPage([$intro, $men, $cups, $end], $en);
    }

    // ------------------------------------------------------------------ records et chiffres

    /** Livre des records : le classement affiché. $rows : Explore::recordRows. */
    private static function recordsAuto(string $cat, string $title, string $unit, string $scope, array $rows, bool $en): ?array
    {
        if (!$rows) {
            return null;
        }
        $val = function (array $r) use ($cat, $unit, $en): string {
            $v = trim((string) $r['v']);
            if ($cat === 'victoires') {
                $d = (int) ltrim($v, '+');
                return $en ? "$d-goal margin" : "$d buts d’écart";
            }
            return $v . ' ' . $unit;
        };
        $intro = [($en ? 'The Sochaux record book: ' : 'Le livre des records du FCSM : ') . self::lcfirst($title, $en) . ', ' . self::lcfirst($scope, $en) . '.'];
        $top = $rows[0];
        $intro[] = ($en ? 'At the top: ' : 'En tête du classement : ') . $top['name'] . ', ' . $val($top) . (trim((string) ($top['meta'] ?? '')) !== '' ? ' (' . $top['meta'] . ')' : '') . '.';
        // « Du 3 octobre 1987 au… » (séries) se dit en minuscule au milieu de la phrase.
        $next = array_map(fn ($r) => (string) preg_replace('/^(Du|From) /u', $en ? 'from ' : 'du ', (string) $r['name']) . ', ' . $val($r), array_slice($rows, 1, 4));
        if ($next) {
            $intro[] = ($en ? 'Next come ' : 'Suivent ') . self::join($next, $en, $en ? '; ' : ' ; ') . '.';
        }
        $end = [$en ? 'The full ranking, and the other records, are on this page.' : 'Le classement complet et les autres records sont à découvrir sur cette page.'];
        return self::forPage([$intro, $end], $en);
    }

    /** « Les chiffres du FCSM » : le plus marquant de chaque chapitre. */
    private static function chiffresAuto(array $chapters, int $count, bool $en): ?array
    {
        if (!$chapters) {
            return null;
        }
        $paras = [[$en ? "Sochaux in numbers: $count statistics drawn from the whole memory of the museum. Here are the most striking, chapter by chapter."
            : "Les chiffres du FCSM : $count statistiques tirées de toute la mémoire du musée. Voici les plus marquantes, chapitre par chapitre."]];
        $stat = function (array $st) use ($en): array {
            $who = array_values(array_filter(array_map(fn ($w) => isset($w['id']) || isset($w['last']) ? (string) $w['name'] : '', $st['who'] ?? []), fn ($w) => $w !== ''));
            $value = trim($st['value'] . ' ' . ($st['unit'] ?? ''));
            return [rtrim((string) $st['label'], ' .') . ($en ? ': ' : ' : ') . ($who ? self::join($who, $en) . ', ' . $value : $value) . '.', trim((string) ($st['text'] ?? ''))];
        };
        // Le premier chiffre de chaque chapitre, puis le deuxième tant que la durée le permet.
        $words = fn (array $p) => FicheAudio::words(implode(' ', $p));
        $total = $words($paras[0]) + 12;
        $byChapter = [];
        foreach ($chapters as $i => $ch) {
            if (!empty($ch['stats'][0])) {
                $byChapter[$i] = array_merge([rtrim((string) $ch['title'], '.') . '.'], $stat($ch['stats'][0]));
                $total += $words($byChapter[$i]);
            }
        }
        foreach ($chapters as $i => $ch) {
            if (isset($byChapter[$i]) && !empty($ch['stats'][1])) {
                $more = $stat($ch['stats'][1]);
                if ($total + $words($more) <= FicheAudio::maxWords()) {
                    $byChapter[$i] = array_merge($byChapter[$i], $more);
                    $total += $words($more);
                }
            }
        }
        $paras = array_merge($paras, array_values($byChapter));
        $paras[] = [$en ? 'And there are many more to discover on this page.' : 'Et bien d’autres sont à découvrir sur cette page.'];
        return self::forPage($paras, $en);
    }

    // ------------------------------------------------------------------ phrases

    /** Matchs datés, du plus ancien au plus récent. */
    private static function chrono(array $list): array
    {
        $list = array_values(array_filter($list, fn ($x) => !empty($x['date'])));
        usort($list, fn ($a, $b) => strcmp((string) $a['date'], (string) $b['date']));
        return $list;
    }

    private static function year(array $x): int
    {
        return (int) substr((string) $x['date'], 0, 4);
    }

    /** [victoires, nuls, défaites] d'une liste de matchs. */
    private static function vnd(array $list): array
    {
        $r = ['V' => 0, 'N' => 0, 'D' => 0];
        foreach ($list as $x) {
            if (isset($r[$x['result'] ?? ''])) {
                $r[$x['result']]++;
            }
        }
        return [$r['V'], $r['N'], $r['D']];
    }

    private static function trend(int $V, int $N, int $D): string
    {
        $n = $V + $N + $D;
        return match (true) {
            $V >= 3 && $V >= 2 * $D => 'domine',
            $D >= 3 && $D >= 2 * $V => 'subit',
            $V !== $D && $n >= 10 && abs($V - $D) <= max(1, (int) round($n / 10)) => 'serre',
            $V > $D => 'favorable',
            $D > $V => 'defavorable',
            default => 'equilibre',
        };
    }

    /** « 12 victoires, 5 nuls et 3 défaites » ($whose : « 12 victoires sochaliennes… », face à un adversaire). */
    private static function record(int $V, int $N, int $D, bool $en, bool $whose = false): string
    {
        $nb = fn (int $n, string $one, string $many, string $zero) => $n === 0 ? $zero : $n . ' ' . ($n > 1 ? $many : $one);
        return $en ? $nb($V, $whose ? 'Sochaux win' : 'win', $whose ? 'Sochaux wins' : 'wins', 'no wins') . ', ' . $nb($N, 'draw', 'draws', 'no draws') . ' and ' . $nb($D, 'defeat', 'defeats', 'no defeats')
            : $nb($V, $whose ? 'victoire sochalienne' : 'victoire', $whose ? 'victoires sochaliennes' : 'victoires', 'aucune victoire') . ', ' . $nb($N, 'nul', 'nuls', 'aucun nul') . ' et ' . $nb($D, 'défaite', 'défaites', 'aucune défaite');
    }

    private static function goals(int $gf, int $ga, bool $en): string
    {
        if ($en) {
            return 'In all, Sochaux scored ' . $gf . ' ' . ($gf === 1 ? 'goal' : 'goals') . ' and conceded ' . $ga . '.';
        }
        return 'Au total, ' . ($gf ? 'Sochaux a marqué ' . $gf . ' ' . ($gf > 1 ? 'buts' : 'but') : 'Sochaux n’a marqué aucun but')
            . ($ga ? ' et en a encaissé ' . $ga : ' et n’en a encaissé aucun') . '.';
    }

    /** Compétitions jouées : « 30 fois en championnat, 4 en Coupe de France et 2 en match amical ». */
    private static function comps(array $comps, bool $en): string
    {
        arsort($comps);
        $name = fn (string $c) => match ($c) {
            'Championnat' => $en ? 'in the league' : 'en championnat',
            'Amical' => $en ? 'in friendlies' : 'en match amical',
            'Barrages' => $en ? 'in play-offs' : 'en barrages',
            'Coupes diverses' => $en ? 'in other cups' : 'dans d’autres coupes',
            default => $en ? 'in the ' . $c : 'en ' . $c,
        };
        if (count($comps) === 1) {
            return $en ? 'All these matches were played ' . $name((string) array_key_first($comps)) . '.' : 'Toutes ces rencontres se sont jouées ' . $name((string) array_key_first($comps)) . '.';
        }
        $parts = [];
        $i = 0;
        foreach ($comps as $c => $k) {
            $parts[] = ($i++ === 0 ? $k . ($en ? ($k > 1 ? ' times ' : ' time ') : ' fois ') : $k . ' ') . $name((string) $c);
        }
        return ($en ? 'They met ' : 'Ces duels se sont joués ') . self::join($parts, $en) . '.';
    }

    /** [plus large victoire, plus lourde défaite, plus grosse affluence] (mêmes règles que les pages). */
    private static function extremes(array $list): array
    {
        $best = $worst = $crowd = null;
        foreach ($list as $x) {
            if ($x['us'] !== null && $x['us'] !== '') {
                $diff = (int) $x['us'] - (int) $x['them'];
                if ($x['result'] === 'V' && (!$best || $diff > $best['_d'] || ($diff === $best['_d'] && $x['us'] > $best['us']))) {
                    $best = $x + ['_d' => $diff];
                }
                if ($x['result'] === 'D' && (!$worst || -$diff > $worst['_d'] || (-$diff === $worst['_d'] && $x['them'] > $worst['them']))) {
                    $worst = $x + ['_d' => -$diff];
                }
            }
            if (($x['spectators'] ?? 0) > 1000 && ($x['spectators'] ?? 0) > ($crowd['spectators'] ?? 0)) {
                $crowd = $x;
            }
        }
        return [$best, $worst, $crowd];
    }

    private static function bestWin(array $x, bool $en, ?string $opp = null): string
    {
        $vs = $opp === null ? ($en ? ' against ' : ' contre ') . $x['opp'] : '';
        return $en ? 'The biggest win: ' . self::score($x, true) . self::place($x, true) . $vs . ', ' . self::inComp($x, true) . ', ' . self::on($x, true) . '.'
            : 'La plus large victoire : ' . self::score($x, false) . self::place($x, false) . $vs . ', ' . self::inComp($x, false) . ', ' . self::on($x, false) . '.';
    }

    private static function worstLoss(array $x, bool $en, ?string $opp = null): string
    {
        $vs = $opp === null ? ($en ? ' against ' : ' contre ') . $x['opp'] : '';
        return $en ? 'The heaviest defeat: ' . self::score($x, true) . self::place($x, true) . $vs . ', ' . self::inComp($x, true) . ', ' . self::on($x, true) . '.'
            : 'La plus lourde défaite : ' . self::score($x, false) . self::place($x, false) . $vs . ', ' . self::inComp($x, false) . ', ' . self::on($x, false) . '.';
    }

    private static function crowd(array $x, bool $en, ?string $opp = null): string
    {
        $n = (int) $x['spectators'];
        $vs = $opp === null ? ($en ? ', against ' : ', contre ') . $x['opp'] : '';
        return $en ? "The biggest crowd: $n spectators, " . self::on($x, true) . $vs . ', ' . self::inComp($x, true) . '.'
            : "La plus forte affluence : $n spectateurs, " . self::on($x, false) . $vs . ', ' . self::inComp($x, false) . '.';
    }

    /** Meilleurs buteurs sochaliens dans une série de matchs (compositions des fiches). */
    private static function scorers(array $matchIds, int $limit = 3): array
    {
        $want = array_flip(array_map('intval', $matchIds));
        $acc = [];
        foreach (Derived::get()['apps'] ?? [] as $a) {
            if (isset($want[(int) $a[1]]) && ($a[6] ?? '') === 'player' && (int) $a[2] > 0) {
                $acc[(int) $a[0]] = ($acc[(int) $a[0]] ?? 0) + (int) $a[2];
            }
        }
        arsort($acc);
        $out = [];
        foreach ($acc as $pid => $g) {
            $s = Index::get($pid);
            if (!$s || !Index::visible($s)) {
                continue;
            }
            $out[] = ['name' => (string) $s['p']['name'], 'g' => $g];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    private static function scorerSentence(array $sc, bool $en, ?string $against): string
    {
        $g = $sc[0]['g'];
        $tops = array_column(array_filter($sc, fn ($x) => $x['g'] === $g), 'name');
        $rest = array_map(fn ($x) => $x['name'] . ' (' . $x['g'] . ')', array_filter($sc, fn ($x) => $x['g'] !== $g));
        $vs = $against !== null ? ($en ? " against $against" : " contre $against") : '';
        if (count($tops) > 1) {
            $s = $en ? 'Among the scorers, ' . self::join($tops, true) . " found the net $g times each$vs" : 'Côté buteurs, ' . self::join($tops, false) . " ont marqué $g buts chacun$vs";
        } else {
            $s = $en ? "Among the scorers, {$tops[0]} found the net $g times$vs" : "Côté buteurs, {$tops[0]} a marqué $g buts$vs";
        }
        return $s . ($rest ? ($en ? ', ahead of ' : ', devant ') . self::join(array_values($rest), $en) : '') . '.';
    }

    /** Plus longue série sans défaite (4 matchs au moins). */
    private static function unbeaten(array $chrono): ?array
    {
        $best = [];
        $cur = [];
        foreach ($chrono as $x) {
            if (!$x['result']) {
                continue;
            }
            if ($x['result'] === 'D') {
                $cur = [];
                continue;
            }
            $cur[] = $x;
            if (count($cur) > count($best)) {
                $best = $cur;
            }
        }
        return count($best) >= 4 ? ['n' => count($best), 'from' => $best[0], 'to' => $best[count($best) - 1]] : null;
    }

    /** « une victoire 3 à 1 à Bonal », « a 3–1 win at Bonal » (tirs au but et prolongation compris). */
    private static function result(array $x, bool $en, bool $withPlace = true): string
    {
        if ($x['us'] === null || $x['us'] === '') {
            return $en ? 'score unknown' : 'score inconnu';
        }
        $us = (int) $x['us'];
        $them = (int) $x['them'];
        $extra = mb_strtolower((string) ($x['extra'] ?? ''));
        $aet = (bool) preg_match('/\ba\.?\s?p\b|prol/u', $extra);
        $pens = null;
        if (str_contains($extra, 'tab') && preg_match('/(\d+)\s*-\s*(\d+)/', $extra, $m)) {
            $pens = [max((int) $m[1], (int) $m[2]), min((int) $m[1], (int) $m[2])];
        }
        if ($us === $them && $pens && in_array($x['result'], ['V', 'D'], true)) {
            $won = $x['result'] === 'V';
            $s = $en ? "a {$us}–{$them} draw" . ($aet ? ' after extra time' : '') . ', then a penalty shoot-out ' . ($won ? 'won' : 'lost') . " {$pens[0]}–{$pens[1]}"
                : "un match nul $us à $them" . ($aet ? ' après prolongation' : '') . ', puis une séance de tirs au but ' . ($won ? 'gagnée' : 'perdue') . " {$pens[0]} à {$pens[1]}";
        } else {
            $sc = self::score($x, $en);
            $s = match ($x['result']) {
                'V' => $en ? "a $sc win" : "une victoire $sc",
                'D' => $en ? "a $sc defeat" : "une défaite $sc",
                default => $en ? "a $sc draw" : "un match nul $sc",
            } . ($aet ? ($en ? ' after extra time' : ' après prolongation') : '');
        }
        return $withPlace ? $s . self::place($x, $en) : $s;
    }

    /** Score, le plus grand nombre d'abord : « 3 à 1 », « 3–1 ». */
    private static function score(array $x, bool $en): string
    {
        $hi = max((int) $x['us'], (int) $x['them']);
        $lo = min((int) $x['us'], (int) $x['them']);
        return $en ? "{$hi}–{$lo}" : "$hi à $lo";
    }

    /** « à Bonal », « à domicile », « à l’extérieur », « au Parc des Princes » (finale). */
    private static function place(array $x, bool $en): string
    {
        if (self::rank((string) ($x['round'] ?? '')) === 100 && !empty($x['stade'])) {
            return ' ' . self::stadiumAt(Explore::stadiumName((string) $x['stade']), $en);
        }
        if (!empty($x['sh'])) {
            return ($x['stade'] ?? '') === 'auguste-bonal' ? ($en ? ' at Bonal' : ' à Bonal') : ($en ? ' at home' : ' à domicile');
        }
        return $en ? ' away' : ' à l’extérieur';
    }

    /** « au stade Louis II », « at the Stade Louis II ». */
    private static function stadiumAt(string $name, bool $en): string
    {
        $name = trim($name);
        if ($en) {
            return 'at the ' . $name;
        }
        return 'au ' . (preg_match('/^stade\b/iu', $name) ? 'stade' . mb_substr($name, 5) : $name);
    }

    /** « en Coupe de France », « en huitième de finale de la Coupe de France », « en match amical ». */
    private static function inComp(array $x, bool $en): string
    {
        $label = trim((string) (($x['label'] ?? '') ?: ($x['comp'] ?? '')));
        if ($label === '') {
            return '';
        }
        if (($x['comp'] ?? '') === 'Amical' || stripos($label, 'amical') !== false) {
            return $en ? 'in a friendly' : 'en match amical';
        }
        $round = self::round((string) ($x['round'] ?? ''), $en);
        $cup = (bool) preg_match('/^(coupe|trophée|tournoi|challenge)\b/iu', $label) || stripos($label, 'cup') !== false;
        if ($round !== '' && $cup) {
            return $en ? (preg_match('/^round \d/', $round) ? "in $round of the $label" : "in the $round of the $label")
                : (ctype_digit($round[0]) ? 'au ' : 'en ') . "$round de " . (preg_match('/^coupe\b/iu', $label) ? 'la ' : '') . $label;
        }
        if ($en) {
            return ($cup ? 'in the ' : 'in ') . $label;
        }
        return (preg_match('/^(trophée|tournoi|challenge)\b/iu', $label) ? 'au ' : 'en ') . $label;
    }

    /** Tour de coupe dit en toutes lettres : « huitième de finale », « round of 16 », « 7e tour ». */
    private static function round(string $r, bool $en): string
    {
        $r = trim((string) preg_replace('/\b(aller|retour)\b|de finale/u', '', mb_strtolower(trim($r))));
        if (preg_match('#^1\s*/\s*(\d+)#', $r, $m)) {
            return match ((int) $m[1]) {
                2 => $en ? 'semi-final' : 'demi-finale',
                4 => $en ? 'quarter-final' : 'quart de finale',
                8 => $en ? 'round of 16' : 'huitième de finale',
                16 => $en ? 'round of 32' : 'seizième de finale',
                32 => $en ? 'round of 64' : 'trente-deuxième de finale',
                default => '',
            };
        }
        if (preg_match('/^finale?\b/u', $r)) {
            return $en ? 'final' : 'finale';
        }
        if (preg_match('/^(\d+)\s*(e|è|ème|eme)\s*tour/u', $r, $m)) {
            return $en ? 'round ' . $m[1] : $m[1] . 'e tour';
        }
        return '';
    }

    /** Rang d'un tour de coupe (finale 100, demi 90… tours préliminaires 10 + n ; 0 si inconnu). */
    private static function rank(string $r): int
    {
        $r = mb_strtolower(trim($r));
        if (preg_match('#^1\s*/\s*(\d+)#', $r, $m)) {
            return match ((int) $m[1]) { 2 => 90, 4 => 80, 8 => 70, 16 => 60, 32 => 50, default => 40 };
        }
        if (preg_match('/^finale?\b/u', $r)) {
            return 100;
        }
        return preg_match('/^(\d+)\s*(e|è|ème|eme)\s*tour/u', $r, $m) ? 10 + (int) $m[1] : 0;
    }

    /** « le 7 avril 2017 », « on 7 April 2017 » ; l'année seule si la date est incomplète. */
    private static function on(array $x, bool $en): string
    {
        $d = self::date($x, $en);
        if ($d !== '') {
            return ($en ? 'on ' : 'le ') . $d;
        }
        return ($en ? 'in ' : 'en ') . self::year($x);
    }

    private static function date(array $x, bool $en): string
    {
        $iso = (string) ($x['date'] ?? '');
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])/', $iso) ? FicheAudio::date($iso, $en, false) : (string) self::year($x);
    }

    /** Saison « 1987‑1988 » : trait d'union insécable, pour qu'elle ne soit pas lue « de 1987 à 1988 ». */
    private static function seasonLabel(string $s): string
    {
        return str_replace('-', "\u{2011}", $s);
    }

    /** « A, B et C », « A, B and C ». */
    private static function join(array $items, bool $en, string $sep = ', '): string
    {
        $items = array_values(array_filter(array_map('strval', $items), fn ($s) => trim($s) !== ''));
        if (count($items) < 2) {
            return $items[0] ?? '';
        }
        $last = array_pop($items);
        return implode($sep, $items) . ($en ? ' and ' : ' et ') . $last;
    }

    private static function ucfirst(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }

    /** Titre ou portée en milieu de phrase : « meilleurs buteurs », « toutes époques ». */
    private static function lcfirst(string $s, bool $en): string
    {
        // Pas pour un nom propre (« Années 80 » → « années 80 », mais « Coupe de France » reste).
        return preg_match('/^(Meilleurs|Joueurs|Plus|Entraîneurs|Toutes|Années|Top|Biggest|Most|Longest|All|The)\b/u', $s) ? mb_strtolower(mb_substr($s, 0, 1)) . mb_substr($s, 1) : $s;
    }

    /**
     * Texte prêt pour la voix : nombres sans espace de milliers (« 19 994 » → « 19994 »), scores
     * dits (« 7-0 » → « 7 à 0 »), dates « 12/09/1989 » en toutes lettres, tirets et puces en virgules.
     */
    public static function speakable(string $s, bool $en): string
    {
        $s = (string) preg_replace('/(?<=\d)[ \x{00A0}\x{202F}](?=\d{3}(?!\d))/u', '', $s);
        $s = (string) preg_replace_callback('#\b(0?[1-9]|[12]\d|3[01])/(0?[1-9]|1[0-2])/(\d{4})\b#', fn ($m) => FicheAudio::date(sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]), $en, false), $s);
        $s = (string) preg_replace('/(?<![\d\/])(\d{1,2})\s?[-–]\s?(\d{1,2})(?![\d\/])/u', $en ? '$1–$2' : '$1 à $2', $s);
        // Années « 1933–1952 » : de… à… ; deux années qui se suivent sont une saison (« 1987‑1988 »).
        $s = (string) preg_replace_callback('/(?<!\d)(\d{4})\s?[–-]\s?(\d{4})(?!\d)/u', fn ($m) => (int) $m[2] === (int) $m[1] + 1 ? $m[1] . "\u{2011}" . $m[2] : ($en ? "from {$m[1]} to {$m[2]}" : "de {$m[1]} à {$m[2]}"), $s);
        $s = str_replace([' – ', ' — ', ' · '], [$en ? ' against ' : ' contre ', ', ', ', '], $s);
        return trim((string) preg_replace('/\s+([,.])/u', '$1', $s));
    }
}
