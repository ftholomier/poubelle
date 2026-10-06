<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Data\Fiches;
use App\Front\Fiche;

/**
 * Rétro-Direct commenté façon radio : pour un match programmé, l'IA écrit le commentaire d'un
 * reporter radio d'époque, réplique par réplique, calé sur le déroulé du direct (coup d'envoi,
 * buts, temps forts, mi-temps, coup de sifflet final, quelques moments d'ambiance). Chaque
 * réplique est lue par la voix IA, passée dans un « poste radio » (bande étroite, souffle,
 * craquements, rumeur de la foule qui gronde aux buts) et gardée en MP3 dans media/radio/.
 *
 * Fabriqué une seule fois par match et par langue, à la demande de l'équipe (back-office), une
 * étape à la fois (le texte, puis une réplique par étape) : par la page, ou par la tâche planifiée
 * si la page est fermée. Coût compté dans Coûts IA (usage « radio », référence radio:{id}).
 *
 * storage/radio/{id}-{lang}.json : {sig, voice, requested, script: [{t, kind, text, file?, dur?}],
 * error?, at}. sig = empreinte du déroulé (instants et scores) : si la fiche change ses buts ou
 * ses minutes, le commentaire n'est plus joué et l'écran le signale « à refaire ».
 */
final class RetroRadio
{
    public static string $dir = STORAGE_PATH . '/radio';
    public static string $media = PUBLIC_PATH . '/media';
    /** Essais : rédaction (fn(string $system, string $user): string JSON) et voix (fn(string $text): array{pcm,rate}). */
    public static ?\Closure $writer = null;
    public static ?\Closure $speaker = null;

    public const MAX_SEGMENTS = 75;
    /**
     * Version de la fabrication des voix : 2 = fin de phrase gardée, voix trop courte redemandée ;
     * 3 = consigne de lecture retirée (la version 2 la faisait entendre au début de chaque réplique).
     */
    public const VOICE_V = 3;
    public const LANGS = ['fr', 'en'];
    /** Voix de Gemini qui conviennent à un reporter (nom => caractère). */
    public const VOICES = ['Fenrir' => 'enflammée', 'Puck' => 'enjouée', 'Orus' => 'ferme', 'Algenib' => 'rocailleuse', 'Charon' => 'posée'];
    /** Décalage (s) entre l'événement à l'écran et le début de la réplique. */
    public const LAG = 1;

    private static function file(int $id, string $lang): string
    {
        return self::$dir . '/' . $id . '-' . $lang . '.json';
    }

    public static function enabled(): bool
    {
        return (bool) \App\Core\Settings::get('audio.radio', true) && (Gemini::ready() || self::$writer !== null);
    }

    public static function voice(): string
    {
        $v = (string) \App\Core\Settings::get('audio.radio_voice', 'Fenrir');
        return isset(self::VOICES[$v]) ? $v : 'Fenrir';
    }

    public static function get(int $id, string $lang): ?array
    {
        JsonStore::forget(self::file($id, $lang));
        $r = JsonStore::read(self::file($id, $lang), null);
        return is_array($r) ? $r : null;
    }

    /** Déroulé du direct dans la langue du commentaire. */
    public static function timeline(int $id, string $lang): array
    {
        $prev = I18n::lang();
        I18n::set($lang);
        try {
            return RetroDirect::timelineFor($id);
        } finally {
            I18n::set($prev);
        }
    }

    /** Empreinte du déroulé : instants, types et scores (pas les textes, qu'une correction ne doit pas invalider). */
    public static function sig(array $tl): string
    {
        return substr(sha1(json_encode(array_map(fn ($e) => [$e['t'], $e['type'], $e['score'] ?? null], $tl['events']))), 0, 16);
    }

    /**
     * État pour le back-office : none (rien), waiting (demandé, rien de fait), script (texte écrit,
     * voix en cours), ready, stale (la fiche a changé), error. + done/total, dur (s), cost (€).
     */
    public static function status(int $id, string $lang): array
    {
        $r = self::get($id, $lang);
        if (!$r) {
            return ['state' => 'none'];
        }
        $total = count($r['script'] ?? []);
        $done = count(array_filter($r['script'] ?? [], fn ($s) => !empty($s['file']) && !self::spoiled($s)));
        $state = match (true) {
            !empty($r['error']) && empty($r['requested']) => 'error',
            $total === 0 => 'waiting',
            $done < $total => 'script',
            default => 'ready',
        };
        if ($state === 'ready' && ($r['sig'] ?? '') !== self::sig(self::timeline($id, $lang))) {
            $state = 'stale';
        }
        // Voix fabriquées avant les dernières corrections (fins de phrase coupées) : à refaire.
        $old = $state === 'ready' && (bool) array_filter($r['script'] ?? [], fn ($s) => (int) ($s['v'] ?? 1) < self::VOICE_V);
        return ['state' => $state, 'old' => $old, 'done' => $done, 'total' => $total, 'voice' => $r['voice'] ?? '', 'error' => $r['error'] ?? null,
            'dur' => (int) round(array_sum(array_map(fn ($s) => (float) ($s['dur'] ?? 0), $r['script'] ?? []))), 'cost' => self::cost($id), 'at' => $r['at'] ?? null];
    }

    /** Coût IA du commentaire d'un match (toutes langues, € au taux du jour). */
    public static function cost(int $id): float
    {
        $usd = 0.0;
        foreach (glob(AiCosts::$dir . '/*.jsonl') ?: [] as $f) {
            if (filemtime($f) < time() - 400 * 86400) {
                continue;
            }
            foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if (str_contains($line, '"radio:' . $id . '"')) {
                    $usd += (float) (json_decode($line, true)['usd'] ?? 0);
                }
            }
        }
        return round(AiCosts::eur($usd), 3);
    }

    /**
     * Coût estimé d'un commentaire (€) : le texte (environ 3 000 jetons lus, 6 000 écrits) et la
     * voix (environ 10 minutes : le reporter parle au moins toutes les 3 minutes, 25 jetons audio
     * par seconde), aux tarifs de Coûts IA.
     */
    public static function estimate(): float
    {
        $text = AiCosts::cost(['in' => 3000, 'out' => 6000], AiCosts::price(FicheAudio::textModel()));
        $voice = AiCosts::cost(['in' => 70 * 60, 'out' => 600 * 25], AiCosts::price(Gemini::ttsModel()));
        return round(AiCosts::eur($text + $voice), 2);
    }

    /** L'équipe demande le commentaire (ou le refait) : la fabrication commence. */
    public static function request(int $id, string $lang, ?string $voice = null): void
    {
        $lang = in_array($lang, self::LANGS, true) ? $lang : 'fr';
        self::delete($id, $lang);
        JsonStore::write(self::file($id, $lang), ['id' => $id, 'lang' => $lang, 'voice' => isset(self::VOICES[(string) $voice]) ? $voice : self::voice(),
            'requested' => date('c'), 'script' => [], 'sig' => '', 'at' => date('c')]);
    }

    /** Efface le commentaire (fichier d'état et MP3). */
    public static function delete(int $id, string $lang): void
    {
        foreach (glob(self::$media . '/radio/' . $id . '-' . $lang . '-*') ?: [] as $f) {
            @unlink($f);
        }
        @unlink(self::file($id, $lang));
        @unlink(self::file($id, $lang) . '.lock');
        @unlink(self::file($id, $lang) . '.work');
    }

    /** Commentaires à fabriquer (tâche planifiée). @return list<array{0:int,1:string}> */
    public static function pending(): array
    {
        $out = [];
        foreach (glob(self::$dir . '/*-*.json') ?: [] as $f) {
            if (preg_match('/^(\d+)-(fr|en)\.json$/', basename($f), $m)) {
                $r = self::get((int) $m[1], $m[2]);
                if ($r && (!empty($r['requested']) || array_filter($r['script'] ?? [], [self::class, 'spoiled']))) {
                    $out[] = [(int) $m[1], $m[2]];
                }
            }
        }
        return $out;
    }

    /**
     * Une étape de fabrication : le texte (une demande à l'IA), sinon la réplique suivante (une
     * voix). Un seul travail à la fois par commentaire (verrou). @return array status()
     */
    public static function step(int $id, string $lang): array
    {
        $r = self::get($id, $lang);
        if (!$r || (empty($r['requested']) && !array_filter($r['script'] ?? [], [self::class, 'spoiled']))) {
            return self::status($id, $lang);
        }
        @mkdir(self::$dir, 0775, true);
        // Verrou de fabrication à part (« .work ») : « .lock » est celui de JsonStore::update().
        $lock = fopen(self::file($id, $lang) . '.work', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return self::status($id, $lang) + ['busy' => true];
        }
        try {
            if (AiCosts::paused('radio')) {
                throw new \RuntimeException('Budget IA du mois atteint (Système › Coûts IA).');
            }
            if (!$r['script']) {
                $tl = self::timeline($id, $lang);
                $script = self::write($id, $lang, $tl);
                JsonStore::update(self::file($id, $lang), function ($x) use ($script, $tl) {
                    if (is_array($x)) {
                        $x['script'] = $script;
                        $x['sig'] = self::sig($tl);
                        unset($x['error']);
                    }
                    return $x;
                }, null);
            } else {
                foreach ($r['script'] as $i => $s) {
                    if (empty($s['file']) || self::spoiled($s)) {
                        $entry = self::voiceSegment($id, $lang, $i, $s, (string) $r['voice']);
                        JsonStore::update(self::file($id, $lang), function ($x) use ($i, $entry) {
                            if (is_array($x) && isset($x['script'][$i])) {
                                $x['script'][$i] = $entry + $x['script'][$i]; // nouvelle voix (fichier, durée, version)
                                $done = count(array_filter($x['script'], fn ($s) => !empty($s['file']) && !self::spoiled($s)));
                                if ($done === count($x['script'])) {
                                    unset($x['requested']);
                                    $x['at'] = date('c');
                                }
                            }
                            return $x;
                        }, null);
                        break;
                    }
                }
                self::ambiance();
            }
        } catch (\Throwable $e) {
            JsonStore::update(self::file($id, $lang), function ($x) use ($e) {
                if (is_array($x)) {
                    $x['error'] = mb_substr($e->getMessage(), 0, 300);
                    $x['fails'] = (int) ($x['fails'] ?? 0) + 1;
                    if ($x['fails'] >= 3) {
                        unset($x['requested']); // trois échecs de suite : on s'arrête, l'équipe relance
                    }
                }
                return $x;
            }, null);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return self::status($id, $lang);
    }

    /** Tâche planifiée : avance les commentaires demandés, dans le temps donné. @return int étapes */
    public static function work(float $seconds = 40.0): int
    {
        $end = microtime(true) + $seconds;
        $n = 0;
        foreach (self::pending() as [$id, $lang]) {
            while (microtime(true) < $end) {
                $s = self::step($id, $lang);
                $n++;
                if (!empty($s['busy']) || in_array($s['state'], ['ready', 'stale', 'error', 'none'], true)) {
                    break;
                }
            }
        }
        return $n;
    }

    /** Répliques prêtes pour la page du direct : [{t, url, dur, kind}], ou null. */
    public static function playlist(int $id, string $lang): ?array
    {
        $r = self::get($id, $lang);
        if (!$r || !empty($r['requested']) || !$r['script'] || ($r['sig'] ?? '') !== self::sig(self::timeline($id, $lang))) {
            return null;
        }
        $out = [];
        foreach ($r['script'] as $s) {
            if (empty($s['file']) || self::spoiled($s) || !is_file(self::$media . '/' . $s['file'])) {
                return null;
            }
            $out[] = ['t' => (int) $s['t'] + self::LAG, 'url' => '/media/' . $s['file'], 'dur' => (float) $s['dur'], 'kind' => $s['kind']];
        }
        return $out;
    }

    // ------------------------------------------------------------------ le texte

    /** Consigne et liste numérotée des événements. @return array{0:string,1:string} */
    public static function prompt(int $id, string $lang, array $tl): array
    {
        $doc = Fiches::get($id);
        $prev = I18n::lang();
        I18n::set($lang);
        $doc = $doc ? Fiche::localizeDoc($doc) : [];
        I18n::set($prev);
        $m = (array) ($doc['match'] ?? []);
        $home = (string) ($m['home']['name'] ?? '');
        $away = (string) ($m['away']['name'] ?? '');
        $year = (int) substr((string) ($m['date'] ?? ''), 0, 4);
        $en = $lang === 'en';
        $ctx = array_filter([
            ($en ? 'Match: ' : 'Match : ') . $home . ' – ' . $away,
            ($en ? 'Date: ' : 'Date : ') . (string) ($m['date'] ?? ''),
            trim((string) ($m['competition'] ?? '') . ' ' . (string) ($m['round'] ?? '')) !== '' ? ($en ? 'Competition: ' : 'Compétition : ') . trim((string) ($m['competition'] ?? '') . ' ' . (string) ($m['round'] ?? '')) : '',
            !empty($m['stadium']) ? ($en ? 'Stadium: ' : 'Stade : ') . (is_array($m['stadium']) ? (string) ($m['stadium']['name'] ?? '') : (string) $m['stadium']) : '',
            !empty($m['spectators']) ? ($en ? 'Attendance: ' : 'Spectateurs : ') . (string) $m['spectators'] : '',
            $tl['final'] ? ($en ? 'Final score: ' : 'Score final : ') . $tl['final'][0] . '-' . $tl['final'][1] . ($tl['aet'] ? ($en ? ' after extra time' : ' après prolongation') : '') . ($tl['pens'] ? ($en ? ', penalties ' : ', tirs au but ') . $tl['pens'][0] . '-' . $tl['pens'][1] : '') : '',
        ]);
        // Textes des temps forts sans les restes de tweets collés dans certaines fiches.
        $clean = function (string $t): string {
            $t = (string) preg_replace('~(https?://|pic\.twitter\.com/)\S+|[@#][\p{L}\d_]+|[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]~u', '', $t);
            $t = (string) preg_replace('/\s*—\s*[^—]*\(\s*\)\s*\w+\s+\d{1,2},\s*\d{4}\s*$/u', '', $t);
            $t = trim((string) preg_replace('/\s+/u', ' ', $t));
            return mb_strlen($t) > 260 ? mb_substr($t, 0, 260) . '…' : $t;
        };
        foreach ($tl['events'] as &$ev) {
            if (isset($ev['text'])) {
                $ev['text'] = $clean((string) $ev['text']);
            }
        }
        unset($ev);
        $lines = [];
        foreach ($tl['events'] as $i => $e) {
            $min = isset($e['min']) ? $e['min'] . "'" : '';
            $d = match ($e['type']) {
                'kickoff' => $en ? 'KICK-OFF' : 'COUP D’ENVOI',
                'goal' => ($en ? 'GOAL' : 'BUT') . (($e['who'] ?? '') !== '' ? ' ' . $e['who'] : '') . (isset($e['side']) && $e['side'] !== null ? ($en ? ' for ' : ' pour ') . [$home, $away][$e['side']] : '') . ($en ? ', score ' : ', score ') . $e['score'][0] . '-' . $e['score'][1] . (($e['text'] ?? '') !== '' ? ' — ' . $e['text'] : ''),
                'action' => ($en ? 'HIGHLIGHT — ' : 'TEMPS FORT — ') . ($e['text'] ?? ''),
                'sub' => ($en ? 'SUBSTITUTION: ' : 'REMPLACEMENT : ') . $e['who'] . (!empty($e['out']) ? ($en ? ' replaces ' : ' remplace ') . $e['out'] : ''),
                'yellow' => ($en ? 'YELLOW CARD: ' : 'CARTON JAUNE : ') . $e['who'],
                'red' => ($en ? 'RED CARD: ' : 'CARTON ROUGE : ') . $e['who'],
                'halftime' => $en ? 'HALF-TIME' : 'MI-TEMPS',
                'kickoff2' => $en ? 'SECOND HALF UNDER WAY' : 'REPRISE DE LA SECONDE PÉRIODE',
                'fulltime90' => $en ? 'END OF NORMAL TIME' : 'FIN DU TEMPS RÉGLEMENTAIRE',
                'extratime' => $en ? 'EXTRA TIME BEGINS' : 'DÉBUT DE LA PROLONGATION',
                'pens' => $en ? 'PENALTY SHOOT-OUT' : 'SÉANCE DE TIRS AU BUT',
                'fulltime' => $en ? 'FINAL WHISTLE' : 'COUP DE SIFFLET FINAL',
                default => strtoupper((string) $e['type']),
            };
            $lines[] = '#' . $i . ' ' . ($min !== '' ? $min . ' ' : '') . $d;
        }
        $decade = $year ? (int) (floor($year / 10) * 10) : 1980;
        $max = self::MAX_SEGMENTS;
        $system = $en
            ? "You are a radio football commentator in the {$decade}s, reporting live from the ground for FC Sochaux-Montbéliard supporters. Write what you say into the microphone, line by line, timed on the numbered events given. Rules: invent no fact (no name, score, minute, weather, figure) beyond those given; you may add emotion, the general atmosphere of the crowd and classic radio phrases. Scores must be exactly those given. Short sentences, easy to say aloud, no abbreviations, no emoji, no stage directions, no brackets. Answer in JSON: {\"segments\":[{\"e\":event number,\"text\":\"...\"},{\"m\":minute,\"text\":\"...\"}]}. One segment for: the kick-off (teams and what is at stake, 40 to 70 words), each goal (20 to 45 words, euphoric when Sochaux scores), each highlight (15 to 35 words), half-time (summary, 30 to 60 words), the second half (10 to 25 words), the final whistle (summary, 40 to 80 words); red cards and notable substitutions only if relevant. Like a real radio commentator, never stay silent for more than 3 minutes of play: between events, add atmosphere segments (\"m\" = a minute without event, 15 to 35 words): the crowd, the tension, the pressure of one side, the score recalled, without inventing any fact. At most {$max} segments, in chronological order. English only."
            : "Tu es reporter radio de football dans les années {$decade}, en direct du stade pour les supporters du FC Sochaux-Montbéliard. Tu écris ce que tu dis au micro, réplique par réplique, calé sur les événements numérotés fournis. Règles : n'invente aucun fait (ni nom, ni score, ni minute, ni météo, ni chiffre) au-delà de ceux fournis ; tu peux ajouter de l'émotion, l'ambiance générale de la foule et des formules radio d'époque. Les scores doivent être exactement ceux indiqués. Phrases courtes, faciles à dire à voix haute, sans abréviations, sans emoji, sans didascalies, sans crochets. Réponds en JSON : {\"segments\":[{\"e\":numéro d'événement,\"text\":\"...\"},{\"m\":minute,\"text\":\"...\"}]}. Un segment pour : le coup d'envoi (présentation des équipes et de l'enjeu, 40 à 70 mots), chaque but (20 à 45 mots, exalté quand Sochaux marque), chaque temps fort (15 à 35 mots), la mi-temps (bilan, 30 à 60 mots), la reprise (10 à 25 mots), le coup de sifflet final (bilan, 40 à 80 mots) ; cartons rouges et remplacements marquants seulement s'ils comptent. Comme un vrai reporter, ne reste jamais plus de 3 minutes de jeu sans parler : entre les événements, ajoute des segments d'ambiance (« m » = une minute sans événement, 15 à 35 mots) : la foule, la tension, la pression d'une équipe, le rappel du score, sans inventer de fait. Au plus {$max} segments, dans l'ordre du match. En français.";
        $user = implode("\n", $ctx) . "\n\n" . ($en ? 'Events:' : 'Événements :') . "\n" . implode("\n", $lines);
        return [$system, $user];
    }

    /** Fait écrire le commentaire et le cale sur le déroulé. @return list<array{t:int,kind:string,text:string}> */
    private static function write(int $id, string $lang, array $tl): array
    {
        [$system, $user] = self::prompt($id, $lang, $tl);
        if (self::$writer) {
            $raw = (self::$writer)($system, $user);
        } else {
            $g = Gemini::generate([['role' => 'user', 'text' => $user]], $system, ['model' => FicheAudio::textModel(), 'temperature' => 0.8, 'max_tokens' => 12000,
                'json' => true, 'timeout' => 120, 'for' => 'radio', 'ref' => 'radio:' . $id]);
            $raw = $g['text'];
        }
        $script = self::parse($raw, $tl);
        if (count($script) < 3) {
            throw new \RuntimeException('Commentaire trop court ou illisible : réessayez.');
        }
        return $script;
    }

    /** Lit la réponse JSON de l'IA : répliques valides, dans l'ordre, au plus MAX_SEGMENTS. */
    public static function parse(string $raw, array $tl): array
    {
        $raw = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($raw)));
        $d = json_decode($raw, true);
        $segs = is_array($d) ? ($d['segments'] ?? (array_is_list($d) ? $d : [])) : [];
        $M = $tl['marks'];
        $out = [];
        $seen = [];
        foreach ($segs as $s) {
            $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($s['text'] ?? ''))));
            $text = trim((string) preg_replace(['/[\[\(][^\]\)]*[\]\)]/u', '/\s{2,}/u'], ['', ' '], $text)); // didascalies
            if (mb_strlen($text) < 8) {
                continue;
            }
            $text = mb_substr($text, 0, 700);
            if (isset($s['e']) && is_numeric($s['e']) && isset($tl['events'][(int) $s['e']])) {
                $e = $tl['events'][(int) $s['e']];
                $key = 'e' . (int) $s['e'];
                $t = (int) $e['t'];
                $kind = $e['type'];
            } elseif (isset($s['m']) && is_numeric($s['m'])) {
                $min = max(1, min(120, (int) $s['m']));
                $t = (int) round(match (true) {
                    $min <= 45 => ($min - 0.5) * 60,
                    $min <= 90 || $M['extratime'] === null => $M['kickoff2'] + ($min - 45.5) * 60,
                    default => $M['extratime'] + ($min - 90.5) * 60,
                });
                $key = 'm' . $min;
                $kind = 'ambiance';
            } else {
                continue;
            }
            if (isset($seen[$key]) || $t > $tl['end'] + 60) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['t' => max(0, $t), 'kind' => $kind, 'text' => $text];
        }
        usort($out, fn ($a, $b) => $a['t'] <=> $b['t']);
        return array_slice($out, 0, self::MAX_SEGMENTS);
    }

    // ------------------------------------------------------------------ la voix et le poste radio

    /** Fait lire une réplique, la passe dans le poste radio, l'enregistre en MP3. */
    private static function voiceSegment(int $id, string $lang, int $i, array $s, string $voice): array
    {
        $speak = fn () => self::$speaker
            ? (self::$speaker)($s['text'])
            : Gemini::speech(self::speakable($s['text']), $voice, '', 'radio:' . $id, 'radio'); // aucune consigne dans le texte : la synthèse la lisait à voix haute
        // Fin de phrase gardée large : un reporter exalté finit souvent plus bas qu'il n'a commencé.
        $trim = fn (array $a) => FicheAudio::trimTail((string) $a['pcm'], (int) $a['rate'], $cut, 0.6, 48.0);
        $cut = null;
        $a = $speak();
        $pcm = $trim($a);
        // La synthèse s'arrête parfois avant le dernier mot : une voix trop courte pour son texte
        // (plus de 4,2 mots par seconde) est redemandée une fois, la plus longue est gardée.
        $words = count(preg_split('/\s+/u', trim($s['text']), -1, PREG_SPLIT_NO_EMPTY));
        if ($words >= 5 && strlen($pcm) / (2 * (int) $a['rate']) < $words / 4.2) {
            $b = $speak();
            $pcm2 = $trim($b);
            if (strlen($pcm2) / (2 * (int) $b['rate']) > strlen($pcm) / (2 * (int) $a['rate'])) {
                [$a, $pcm] = [$b, $pcm2];
            }
        }
        $pcm = self::radioize($pcm, (int) $a['rate'], $s['kind'] === 'goal', crc32($id . $lang . $i));
        $dir = self::$media . '/radio';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Dossier impossible à créer : ' . $dir);
        }
        $base = sprintf('%d-%s-%02d-%s', $id, $lang, $i, substr(sha1($s['text'] . $voice), 0, 8));
        $file = self::encode($dir . '/' . $base, $pcm, (int) $a['rate']);
        return ['file' => 'radio/' . basename($file), 'dur' => round(strlen($pcm) / (2 * (int) $a['rate']), 1), 'v' => self::VOICE_V];
    }

    /**
     * Réplique abîmée : voix de la version 2, où la consigne de lecture était entendue au début.
     * Jamais jouée ; la tâche planifiée refait sa voix d'office (même texte, même voix).
     */
    public static function spoiled(array $s): bool
    {
        return !empty($s['file']) && (int) ($s['v'] ?? 1) === 2;
    }

    /** Texte lu : toujours terminé par une ponctuation (sans elle, la synthèse avale parfois le dernier mot). */
    public static function speakable(string $text): string
    {
        $text = rtrim($text);
        return preg_match('/[.!?…»"]$/u', $text) ? $text : $text . '.';
    }

    /** MP3 (encodeur du site), sinon WAV. @return string chemin écrit */
    private static function encode(string $base, string $pcm, int $rate): string
    {
        $data = null;
        if (isset(Mp3Encoder::RATES[$rate])) {
            try {
                $data = Mp3Encoder::encode($pcm, $rate, FicheAudio::MP3_KBPS);
                $path = $base . '.mp3';
            } catch (\Throwable $e) {
                error_log('[radio] MP3 : ' . $e->getMessage());
            }
        }
        if ($data === null) {
            $data = FicheAudio::wav($pcm, $rate);
            $path = $base . '.wav';
        }
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $data) === false || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Écriture impossible : ' . $path);
        }
        return $path;
    }

    /**
     * Le poste radio : bande étroite (350 Hz – 3,4 kHz), légère saturation, souffle, craquements,
     * rumeur de la foule dessous (qui gronde au début d'un but). Ajoute 0,4 s avant et 0,7 s après.
     */
    public static function radioize(string $pcm, int $rate, bool $goal = false, int $seed = 1): string
    {
        mt_srand($seed);
        $voice = array_values(unpack('s*', $pcm) ?: []);
        $pre = (int) (0.4 * $rate);
        $post = (int) (0.7 * $rate);
        $n = $pre + count($voice) + $post;
        $hp = self::biquad('hp', 350, $rate);
        $lp = self::biquad('lp', 3400, $rate);
        $crowdLp = self::biquad('lp', 900, $rate);
        $crowdHp = self::biquad('hp', 160, $rate);
        $s1 = $s2 = $c1 = $c2 = [0.0, 0.0, 0.0, 0.0];
        $out = [];
        $pop = 0.0;
        $k = tanh(1.8);
        for ($i = 0; $i < $n; $i++) {
            $x = ($i >= $pre && $i < $pre + count($voice)) ? $voice[$i - $pre] / 32768 : 0.0;
            $x = self::run($hp, $x, $s1);
            $x = self::run($lp, $x, $s2);
            $x = tanh(1.8 * $x) / $k;
            // Foule : bruit filtré qui ondule ; plus fort et qui retombe au début d'un but.
            $noise = (mt_rand() / mt_getrandmax()) * 2 - 1;
            $c = self::run($crowdHp, self::run($crowdLp, $noise, $c1), $c2);
            $sec = $i / $rate;
            $level = 0.05 + 0.015 * sin($sec * 1.7) + 0.01 * sin($sec * 0.6 + 1);
            if ($goal) {
                $level += 0.55 * exp(-$sec / 2.2) * min(1, $sec / 0.25);
            }
            $y = 0.82 * $x + $level * $c;
            // Souffle et craquements du poste.
            $y += 0.0025 * ((mt_rand() / mt_getrandmax()) * 2 - 1);
            if (mt_rand(0, 90000) === 0) {
                $pop = (mt_rand(0, 1) ? 1 : -1) * (0.03 + 0.05 * mt_rand() / mt_getrandmax());
            }
            $y += $pop;
            $pop *= 0.82;
            // Fondu d'entrée et de sortie de la rumeur.
            $fade = min(1, $i / max(1, $pre), ($n - $i) / max(1, $post));
            if ($i < $pre || $i >= $pre + count($voice)) {
                $y *= $fade;
            }
            $out[] = (int) max(-32767, min(32767, round($y * 30000)));
        }
        mt_srand();
        return pack('s*', ...$out);
    }

    /** Coefficients d'un filtre biquad (passe-haut ou passe-bas, Q = 0,707). */
    private static function biquad(string $type, float $f, int $rate): array
    {
        $w = 2 * M_PI * $f / $rate;
        $alpha = sin($w) / (2 * 0.7071);
        $cos = cos($w);
        [$b0, $b1, $b2] = $type === 'hp' ? [(1 + $cos) / 2, -(1 + $cos), (1 + $cos) / 2] : [(1 - $cos) / 2, 1 - $cos, (1 - $cos) / 2];
        $a0 = 1 + $alpha;
        return [$b0 / $a0, $b1 / $a0, $b2 / $a0, (-2 * $cos) / $a0, (1 - $alpha) / $a0];
    }

    /** Un échantillon dans un biquad ($st : x1, x2, y1, y2). */
    private static function run(array $c, float $x, array &$st): float
    {
        $y = $c[0] * $x + $c[1] * $st[0] + $c[2] * $st[1] - $c[3] * $st[2] - $c[4] * $st[3];
        $st = [$x, $st[0], $y, $st[2]];
        return $y;
    }

    /** Boucle d'ambiance du stade (20 s, jouée en fond pendant le direct), fabriquée une fois. */
    public static function ambiance(): string
    {
        $path = self::$media . '/radio/ambiance.mp3';
        if (is_file($path) || is_file(self::$media . '/radio/ambiance.wav')) {
            return is_file($path) ? '/media/radio/ambiance.mp3' : '/media/radio/ambiance.wav';
        }
        @mkdir(self::$media . '/radio', 0775, true);
        $rate = 24000;
        $pcm = self::radioize(str_repeat("\0\0", $rate * 19), $rate, false, 1928);
        // Boucle sans couture : les 0,5 dernières secondes fondues dans les premières.
        $s = array_values(unpack('s*', $pcm));
        $x = (int) (0.5 * $rate);
        $len = count($s) - $x;
        for ($i = 0; $i < $x; $i++) {
            $a = $i / $x;
            $s[$i] = (int) round($s[$i] * $a + $s[$len + $i] * (1 - $a));
        }
        $file = self::encode(self::$media . '/radio/ambiance', pack('s*', ...array_slice($s, 0, $len)), $rate);
        return '/media/' . substr($file, strlen(self::$media) + 1);
    }

    /**
     * Ambiance de stade livrée avec le site : vraie prise de son dans le public (stade Ernst-Happel,
     * Vienne, 2014), « Work With Sounds / Torsten Nilsson », CC BY 4.0 (crédit affiché sur la page).
     */
    public const STADE = '/assets/audio/stade-ambiance.mp3';

    /** URL de la boucle d'ambiance : celle du site, sinon celle fabriquée sur le serveur. */
    public static function ambianceUrl(): ?string
    {
        if (is_file(PUBLIC_PATH . self::STADE)) {
            // Adresse versionnée : les MP3 sont gardés un an par le navigateur.
            return asset(substr(self::STADE, strlen('/assets/')));
        }
        foreach (['mp3', 'wav'] as $ext) {
            if (is_file(self::$media . '/radio/ambiance.' . $ext)) {
                return '/media/radio/ambiance.' . $ext;
            }
        }
        return null;
    }
}
