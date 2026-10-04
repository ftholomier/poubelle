<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Core\Settings;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Front\Fiche;
use App\Front\Unknown;

/**
 * Fiches audio : chaque fiche est expliquée à voix haute, en entier, aussi longuement que son
 * contenu le demande, sans dépasser la durée réglée (3 minutes par défaut, 150 mots par minute).
 *
 * Texte lu, par ordre de priorité : écrit à la main par un historien, rédigé par l'IA (s'il
 * correspond toujours à la fiche), sinon résumé automatique construit à partir des données
 * (date, score, buteurs, carrière, introduction…), gratuit.
 * Voix : celle du navigateur du visiteur (gratuit), ou la voix IA de Gemini enregistrée en
 * fichier (public/media/audio/, MP3 si ffmpeg est présent, sinon WAV) — fiche par fiche depuis
 * l'éditeur (tarif normal) ou pour tout le musée en traitement groupé (API Batch de Google :
 * moitié prix, résultats en quelques heures, récupérés par la tâche planifiée).
 *
 * État par fiche : storage/audio/{id}.json = ['fr' => ['text', 'src' (ai|manual), 'sig'
 * (empreinte de la fiche résumée par l'IA), 'audio' => ['file', 'th' (empreinte du texte lu),
 * 'voice', 'model', 'dur', 'at']], 'en' => …]. Travaux groupés : storage/audio/jobs.json.
 */
final class FicheAudio
{
    /** Débit de lecture retenu pour passer d'une durée à un nombre de mots. */
    public const WPM = 150;
    /** Version de la consigne de rédaction : la changer fait refaire les textes rédigés par l'IA. */
    private const TEXT_VERSION = 3;
    public const LANGS = ['fr' => 'fr-FR', 'en' => 'en-GB'];
    /** Voix de Gemini proposées (nom => caractère). */
    public const VOICES = [
        'Charon' => 'informative', 'Sadaltager' => 'savante', 'Gacrux' => 'mûre', 'Sulafat' => 'chaleureuse',
        'Achird' => 'amicale', 'Iapetus' => 'claire', 'Schedar' => 'égale', 'Kore' => 'ferme', 'Orus' => 'ferme',
        'Algenib' => 'rocailleuse', 'Vindemiatrix' => 'douce', 'Puck' => 'enjouée',
    ];
    /**
     * Version de l'enregistrement des voix IA : 2 = le texte seul. Avant, une consigne de ton
     * (« Lis d'une voix chaleureuse… ») précédait le texte et le modèle la lisait à voix haute :
     * ces voix ne sont plus jouées et sont refaites la nuit suivante.
     */
    public const VOICE_VERSION = 2;
    /** Débit des voix en MP3 avec l'encodeur du site (kbit/s) : sans modèle psychoacoustique, un peu plus que les 48 de ffmpeg. */
    public const MP3_KBPS = 64;
    /** Fiches par traitement groupé : 30 secondes de voix pèsent environ 2 Mo dans les résultats. */
    public const BATCH_VOICE = 150;
    public const BATCH_TEXT = 800;

    public static string $dir = STORAGE_PATH . '/audio';
    /** Dossier public des médias : les voix vont dans media/audio/ (servies directement par Apache). */
    public static string $media = PUBLIC_PATH . '/media';
    /** Compression MP3 : null = ffmpeg détecté automatiquement, false = WAV (essais). */
    public static ?bool $mp3 = null;
    private static ?string $ffmpeg = null;

    /** Mots au plus du texte lu : durée maximale réglée (minutes) × 150 mots par minute. */
    public static function maxWords(): int
    {
        return (int) round(self::maxMinutes() * self::WPM);
    }

    public static function maxMinutes(): float
    {
        return max(0.5, min(10.0, (float) Settings::get('audio.max_minutes', 3)));
    }

    /** Fiches par lot de voix : environ 300 Mo de résultats au plus, quelle que soit la durée. */
    public static function voiceBatch(): int
    {
        return max(10, (int) floor(self::BATCH_VOICE * 75 / self::maxWords()));
    }

    public static function enabled(): bool
    {
        return (bool) Settings::get('audio.enabled', true);
    }

    public static function voice(): string
    {
        $v = (string) Settings::get('audio.voice', 'Charon');
        return isset(self::VOICES[$v]) ? $v : 'Charon';
    }


    // ------------------------------------------------------------------ état

    public static function state(int $id): array
    {
        $s = JsonStore::read(self::$dir . "/$id.json", []);
        return is_array($s) ? $s : [];
    }

    private static function update(int $id, callable $fn): array
    {
        return JsonStore::update(self::$dir . "/$id.json", fn ($s) => $fn(is_array($s) ? $s : []), []);
    }

    /** Langues d'une fiche : le français, et l'anglais si la fiche est traduite. */
    public static function langs(array $doc): array
    {
        return !empty($doc['i18n']['en']['title']) ? ['fr', 'en'] : ['fr'];
    }

    // ------------------------------------------------------------------ texte lu

    /**
     * Texte lu dans une langue : ['text', 'src' => auto|ai|manual, 'outdated' => texte IA
     * devenu trop ancien (la fiche a changé : on lit le résumé automatique en attendant)].
     */
    public static function current(array $doc, string $lang): array
    {
        $st = self::state((int) $doc['id'])[$lang] ?? [];
        $text = trim((string) ($st['text'] ?? ''));
        if ($text !== '' && ($st['src'] ?? '') === 'manual') {
            return ['text' => $text, 'src' => 'manual', 'outdated' => false];
        }
        if ($text !== '' && ($st['src'] ?? '') === 'ai') {
            if (($st['sig'] ?? '') === self::sig($doc, $lang)) {
                return ['text' => $text, 'src' => 'ai', 'outdated' => false];
            }
            return ['text' => self::template($doc, $lang), 'src' => 'auto', 'outdated' => true];
        }
        return ['text' => self::template($doc, $lang), 'src' => 'auto', 'outdated' => false];
    }

    /** Empreinte de ce que résume l'IA (titres, textes, faits) : change si la fiche change vraiment. */
    public static function sig(array $doc, string $lang): string
    {
        $d = self::localized($doc, $lang);
        $facts = [self::TEXT_VERSION, self::maxWords(), $d['title'] ?? '', $d['intro'] ?? '', array_column($d['sections'] ?? [], 'html')];
        if (isset($d['match'])) {
            $m = $d['match'];
            $facts[] = [$m['date'] ?? '', $m['home']['name'] ?? '', $m['away']['name'] ?? '', $m['score'] ?? '', $m['goals'] ?? '', $m['stadium'] ?? '', $m['spectators'] ?? '', $m['highlights'] ?? '', $m['breves'] ?? ''];
        }
        if (isset($d['personne'])) {
            $p = $d['personne'];
            $facts[] = [$p['display_name'] ?? '', $p['position'] ?? '', $p['birth'] ?? '', $p['roles'] ?? '', $p['subtitle'] ?? '', $p['arrival'] ?? '', $p['departure'] ?? ''];
        }
        return substr(sha1((string) json_encode($facts, JSON_UNESCAPED_UNICODE)), 0, 16);
    }

    /** La fiche dans la langue voulue (champs traduits quand ils existent). */
    private static function localized(array $doc, string $lang): array
    {
        if ($lang !== 'en' || empty($doc['i18n']['en']['title'])) {
            return $doc;
        }
        $en = $doc['i18n']['en'];
        foreach (['title', 'intro', 'sections'] as $k) {
            if (!empty($en[$k])) {
                $doc[$k] = $en[$k];
            }
        }
        foreach (['highlights', 'breves'] as $k) {
            if (!empty($en['match'][$k]) && isset($doc['match'])) {
                $doc['match'][$k] = $en['match'][$k];
            }
        }
        if (isset($doc['personne']) && !empty($en['personne']['subtitle'])) {
            $doc['personne']['subtitle'] = $en['personne']['subtitle'];
        }
        return $doc;
    }

    /** Résumés automatiques déjà calculés (écran Fiches audio, traitements groupés : 4 s pour tout le musée sinon). */
    public static string $templates = STORAGE_PATH . '/cache/audio-resumes.ser';
    /** @var array<string,array{0:string,1:string}> clé fiche-langue => [empreinte de la fiche, texte] */
    private static array $tpl = [];
    private static bool $tplLoaded = false;
    private static bool $tplChanged = false;

    /** Texte automatique (gratuit) : les faits tirés des données, puis le texte de la fiche, dans la durée maximale. */
    public static function template(array $doc, string $lang): string
    {
        $key = (int) ($doc['id'] ?? 0) . '-' . $lang;
        $hash = hash('xxh128', $lang . '|' . self::maxWords() . '|' . serialize($doc));
        if (($hit = self::$tpl[$key] ?? null) && $hit[0] === $hash) {
            return $hit[1];
        }
        $text = self::buildTemplate($doc, $lang);
        self::$tpl[$key] = [$hash, $text];
        self::$tplChanged = true;
        return $text;
    }

    /** Charge les résumés déjà calculés (calcul sur tout le musée), s'ils datent du code actuel. */
    private static function loadTemplates(): void
    {
        if (self::$tplLoaded) {
            return;
        }
        self::$tplLoaded = true;
        $raw = is_file(self::$templates) ? @file_get_contents(self::$templates) : false;
        $c = $raw ? @unserialize($raw, ['allowed_classes' => false]) : null;
        if (is_array($c) && ($c['code'] ?? null) === self::templateCode() && is_array($c['items'] ?? null)) {
            self::$tpl += $c['items'];
        }
    }

    /** Garde les résumés calculés pour le prochain affichage (seulement s'il y en a de nouveaux). */
    private static function saveTemplates(): void
    {
        if (!self::$tplLoaded || !self::$tplChanged) {
            return;
        }
        self::$tplChanged = false;
        try {
            $dir = dirname(self::$templates);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $tmp = self::$templates . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($tmp, serialize(['code' => self::templateCode(), 'items' => self::$tpl])) === false || !@rename($tmp, self::$templates)) {
                @unlink($tmp);
            }
        } catch (\Throwable) {
            // cache facultatif
        }
    }

    /** Version du code qui fabrique les résumés : un fichier changé (mise à jour, envoi FTP) les fait recalculer. */
    private static function templateCode(): string
    {
        return @filemtime(__FILE__) . ':' . @filemtime(APP_DIR . '/Front/Unknown.php');
    }

    private static function buildTemplate(array $doc, string $lang): string
    {
        // « xx » de l'ancien site (information inconnue) jamais lus à voix haute.
        $d = Unknown::doc(self::localized($doc, $lang));
        $en = $lang === 'en';
        $s = match ($d['type'] ?? '') {
            'match' => self::matchSentences($d, $en),
            'personne' => self::personSentences($d, $en),
            default => [rtrim(trim((string) $d['title']), '.') . '.'],
        };
        // Récit : l'introduction, puis tout le texte de la fiche et ses brèves, jusqu'à la durée maximale.
        $story = [self::plainText((string) ($d['intro'] ?? ''))];
        if (isset($d['personne'])) {
            $story[] = self::plainText((string) ($d['personne']['subtitle'] ?? ''));
        }
        $story[] = self::plainText(implode("\n", array_map(fn ($x) => (string) ($x['html'] ?? ''), $d['sections'] ?? [])));
        foreach ($d['match']['breves'] ?? [] as $b) {
            $story[] = self::plainText((string) $b);
        }
        // Pas de récit en français dans le texte anglais.
        if (!$en || !empty($doc['i18n']['en']['title'])) {
            $s = array_merge($s, array_filter($story, fn ($x) => $x !== ''));
        }
        return self::fit($s, self::maxWords());
    }

    private static function matchSentences(array $d, bool $en): array
    {
        $m = $d['match'];
        $s = [];
        $date = self::date($m['date'] ?? null, $en);
        $stadium = trim((string) ($m['stadium'] ?? ''));
        if ($date !== '' || $stadium !== '') {
            $stadium = trim((string) preg_replace('/\s*\(\d{2,3}\)/', '', $stadium));
            $s[] = self::ucfirst(trim($date . ($date !== '' && $stadium !== '' ? ', ' : '') . $stadium)) . '.';
        }
        $home = trim((string) ($m['home']['name'] ?? ''));
        $away = trim((string) ($m['away']['name'] ?? ''));
        if ($home !== '' && $away !== '' && isset($m['score']['home'], $m['score']['away'])) {
            $sochHome = !empty($m['sochaux_home']);
            [$soch, $opp] = $sochHome ? [$home, $away] : [$away, $home];
            [$gs, $go] = $sochHome ? [(int) $m['score']['home'], (int) $m['score']['away']] : [(int) $m['score']['away'], (int) $m['score']['home']];
            $comp = trim((string) (($m['competition_label'] ?? '') ?: ($m['competition'] ?? '')));
            $round = trim((string) preg_replace('/(\d+)\s*(?:ème|eme|è)\b/u', '$1e', (string) ($m['round_text'] ?? '')));
            // « 24e journée de D2 » dit tout ; sinon la compétition seule.
            $head = !$en && $round !== '' && str_contains($round, ' ') ? $round : $comp;
            if (preg_match('/amical/i', $comp)) {
                $head = $en ? 'Friendly' : 'Match amical';
            }
            if ($en) {
                $res = $gs > $go ? "$soch beat $opp {$gs}–{$go}" : ($gs < $go ? "$soch lost {$gs}–{$go} to $opp" : "$soch and $opp drew {$gs}–{$go}");
            } else {
                $res = $gs > $go ? "$soch bat $opp $gs à $go" : ($gs < $go ? "$soch s’incline $go à $gs face à $opp" : "$soch et $opp se quittent sur un nul, $gs partout");
            }
            if (!empty($m['score']['aet'])) {
                $res .= $en ? ' after extra time' : ' après prolongation';
            }
            $pens = $m['score']['pens'] ?? null;
            if (is_array($pens) && isset($pens['home'], $pens['away'])) {
                // Tirs au but : le vainqueur de la séance d'abord (« Sochaux l'emporte 5 à 4 aux tirs au but »).
                [$ps, $po] = $sochHome ? [(int) $pens['home'], (int) $pens['away']] : [(int) $pens['away'], (int) $pens['home']];
                [$win, $a, $b] = $ps >= $po ? [$soch, $ps, $po] : [$opp, $po, $ps];
                $res .= $en ? ", and $win won {$a}–{$b} on penalties" : ", et $win l’emporte $a à $b aux tirs au but";
            }
            if (!empty($m['spectators'])) {
                $res .= $en ? ', in front of ' . (int) $m['spectators'] . ' spectators' : ', devant ' . (int) $m['spectators'] . ' spectateurs';
            }
            $s[] = ($head !== '' ? $head . ($en ? ': ' : ' : ') : '') . $res . '.';
        }
        $goals = [];
        foreach ($m['goals'] ?? [] as $g) {
            $who = trim((string) ($g['scorers'] ?? ''));
            if ($who === '') {
                continue;
            }
            $who = (string) preg_replace_callback("/(\\d+)(?:\\s*\\+\\s*\\d+)?\\s*['’]/u", fn ($x) => $en ? self::ordinal((int) $x[1]) : $x[1] . 'e', $who);
            // Abréviations dites en toutes lettres : penalty, contre son camp.
            $who = (string) preg_replace(['/\s*\((?:s\.?\s*p\.?|sp|pen\.?|péno)\)/iu', '/\s*\((?:csc|c\.s\.c\.?)\)/iu'], $en ? [' (penalty)', ' (own goal)'] : [' sur penalty', ' contre son camp'], $who);
            $goals[] = ($en ? str_replace(' et ', ' and ', $who) : $who) . ($en ? ' for ' : ' pour ') . trim((string) ($g['team'] ?? ''));
        }
        if ($goals) {
            $s[] = ($en ? 'Goals: ' : 'Buts : ') . implode($en ? '; ' : ' ; ', $goals) . '.';
        }
        return $s;
    }

    private static function personSentences(array $d, bool $en): array
    {
        $p = $d['personne'];
        $id = (int) $d['id'];
        $name = trim((string) (($p['display_name'] ?? '') ?: $d['title']));
        $years = Fiche::personYears($p);
        $span = '';
        if ($years !== '') {
            $y = explode('-', $years);
            $span = count($y) === 2 ? ($en ? " from {$y[0]} to {$y[1]}" : " de {$y[0]} à {$y[1]}") : ($en ? " in {$y[0]}" : " en {$y[0]}");
        }
        $roles = $p['roles'] ?: ['joueur'];
        $s = [];
        if ($en) {
            $s[] = "$name, at FC Sochaux-Montbéliard$span.";
        } else {
            $role = ($roles[0] ?? 'joueur') === 'joueur' ? (trim((string) ($p['position'] ?? '')) ?: 'joueur') : str_replace('entraineur', 'entraîneur', (string) $roles[0]);
            $s[] = "$name, " . mb_strtolower($role) . " du FC Sochaux-Montbéliard$span.";
        }
        $story = mb_strtolower(self::plainText((string) ($d['intro'] ?? '') . ' ' . implode(' ', array_map(fn ($x) => (string) ($x['html'] ?? ''), array_slice($d['sections'] ?? [], 0, 1)))));
        $birthText = (string) ($p['birth']['text'] ?? '');
        $birthPlace = (string) ($p['birth']['place']['text'] ?? '');
        $place = mb_strtolower(trim((string) preg_replace('/\s*\(.*?\)/', '', $birthPlace)));
        if (!$en && $birthText !== '' && !($place !== '' && str_contains(mb_substr($story, 0, 200), $place))) {
            $s[] = sentence(self::ucfirst(trim((string) preg_replace('/\s*\(\d{2,3}\)/', '', $birthText))));
        } elseif ($en && ($p['birth']['date']['precision'] ?? '') === 'day') {
            $s[] = 'Born on ' . self::date((string) $p['birth']['date']['iso'], true, false) . ($birthPlace !== '' ? ' in ' . $birthPlace : '') . '.';
        }
        $tot = Derived::get()['person_totals'][$id] ?? null;
        // Comme la fiche : compositions du musée, sinon tableau de statistiques.
        // Entraîneur : son tableau de statistiques peut être celui du banc, il n'est pas lu comme des matchs joués.
        $coach = ($roles[0] ?? '') === 'entraineur';
        $st = $coach ? [] : Fiche::statsTotals($p['stats'] ?? null);
        $mt = max((int) ($tot['matches'] ?? 0), (int) ($st['matches'] ?? 0));
        $gl = max((int) ($tot['goals'] ?? 0), (int) ($st['goals'] ?? 0));
        if ($coach && !empty($tot['coached'])) {
            $n = (int) $tot['coached'];
            $s[] = $en ? $n . ($n > 1 ? ' matches' : ' match') . ' in charge of Sochaux.' : $n . ' match' . ($n > 1 ? 's' : '') . ' sur le banc sochalien.';
        } elseif ($mt > 0) {
            $s[] = $en
                ? "$mt " . ($mt > 1 ? 'matches' : 'match') . ($gl ? " and $gl " . ($gl > 1 ? 'goals' : 'goal') : '') . ' for Sochaux.'
                : "$mt match" . ($mt > 1 ? 's' : '') . ($gl ? " et $gl but" . ($gl > 1 ? 's' : '') : '') . ' sous le maillot sochalien.';
        }
        return $s;
    }

    /** Phrases assemblées dans la limite de mots ; la dernière est coupée à une fin de phrase. */
    public static function fit(array $sentences, int $max): string
    {
        $out = [];
        $n = 0;
        foreach ($sentences as $s) {
            $s = trim((string) preg_replace('/\s+/u', ' ', $s));
            if ($s === '') {
                continue;
            }
            $w = count(preg_split('/\s+/u', $s) ?: []);
            if ($n + $w <= $max) {
                $out[] = $s;
                $n += $w;
                continue;
            }
            // Trop long : on garde les phrases entières qui tiennent.
            $keep = '';
            foreach (preg_split('/(?<=[.!?…])\s+/u', $s) ?: [] as $part) {
                $pw = count(preg_split('/\s+/u', trim($part)) ?: []);
                if ($n + $pw > $max) {
                    break;
                }
                $keep .= ($keep !== '' ? ' ' : '') . trim($part);
                $n += $pw;
            }
            if ($keep !== '') {
                $out[] = $keep;
            }
            break;
        }
        return implode(' ', $out);
    }

    public static function words(string $t): int
    {
        return (int) preg_match_all('/\S+/u', $t);
    }

    private static function plainText(string $html): string
    {
        // Un intertitre ou un élément de liste se lit comme une phrase (« Carrière de joueur. C'est… »).
        $html = (string) preg_replace('#([^.!?:;…\s])(\s*(?:</(?:strong|em|b|i|u)>\s*)*)</(h[1-6]|li|p)>#u', '$1.$2</$3>', $html);
        // « xx » de l'ancien site (information inconnue) jamais lu à voix haute.
        return trim((string) preg_replace('/\s+/u', ' ', Unknown::text(plain($html))));
    }

    private static function ucfirst(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }

    private static function ordinal(int $n): string
    {
        $suffix = in_array($n % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th');
        return $n . $suffix;
    }

    /** « dimanche 28 février 1988 », « Sunday 28 February 1988 ». */
    public static function date(?string $iso, bool $en, bool $withDay = true): string
    {
        $ts = $iso ? strtotime($iso) : false;
        if (!$ts) {
            return '';
        }
        $months = $en ? ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
            : ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $days = $en ? ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] : ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $day = (int) date('j', $ts);
        $txt = ($day === 1 && !$en ? '1er' : (string) $day) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
        return $withDay ? $days[(int) date('w', $ts)] . ' ' . $txt : $txt;
    }

    // ------------------------------------------------------------------ texte rédigé par l'IA

    /** Consigne et données envoyées à Gemini pour rédiger le résumé. */
    public static function aiPrompt(array $doc, string $lang): array
    {
        $d = Unknown::doc(self::localized($doc, $lang));
        $en = $lang === 'en';
        $data = ['type' => Fiches::TYPES[$d['type']] ?? $d['type'], 'titre' => $d['title'], 'introduction' => self::plainText((string) ($d['intro'] ?? ''))];
        if (isset($d['match'])) {
            $m = $d['match'];
            $data['match'] = [
                'date' => $m['date'] ?? null, 'compétition' => ($m['competition_label'] ?? '') ?: ($m['competition'] ?? ''), 'tour' => $m['round_text'] ?? '',
                'domicile' => $m['home']['name'] ?? '', 'extérieur' => $m['away']['name'] ?? '', 'score' => $m['score'] ?? null,
                'stade' => $m['stadium'] ?? '', 'spectateurs' => $m['spectators'] ?? null, 'arbitre' => $m['referee'] ?? '', 'buteurs' => $m['goals'] ?? [],
                'temps forts' => array_map(fn ($h) => trim(($h['minute'] ?? '') . "' " . self::plainText((string) ($h['text'] ?? ''))), array_slice($m['highlights'] ?? [], 0, 80)),
                'brèves' => array_map(fn ($b) => self::plainText((string) $b), array_slice($m['breves'] ?? [], 0, 30)),
            ];
        }
        if (isset($d['personne'])) {
            $p = $d['personne'];
            $data['personne'] = ['nom' => ($p['display_name'] ?? '') ?: $d['title'], 'poste' => $p['position'] ?? '', 'rôles' => $p['roles'] ?? [], 'naissance' => (string) ($p['birth']['text'] ?? ''),
                'années au club' => Fiche::personYears($p), 'totaux' => Derived::get()['person_totals'][(int) $d['id']] ?? null, 'sous-titre' => self::plainText((string) ($p['subtitle'] ?? ''))];
        }
        $data['texte'] = mb_substr(self::plainText(implode("\n", array_map(fn ($x) => (string) ($x['html'] ?? ''), $d['sections'] ?? []))), 0, 40000);
        $max = self::maxWords();
        $min = rtrim(rtrim(number_format(self::maxMinutes(), 1, $en ? '.' : ',', ''), '0'), '.,');
        $system = $en
            ? "You are a historian of FC Sochaux-Montbéliard and a passionate storyteller. You tell, out loud, a page from the online museum Sochaux Rétro; your script will be read by a synthetic voice.\n"
                . "Bring the story back to life, as if you were telling it to supporters gathered around you:\n"
                . "- a hook that makes people want to listen;\n"
                . "- an introduction that sets the scene: the era, what was at stake, the atmosphere;\n"
                . "- the heart of the story in several paragraphs. For a match: the build-up, how the game unfolded, the goals and turning points, behind the scenes, the anecdotes, the men. For a person: the beginnings, the career at the club, the great moments, the style, the figures, the legacy. For any other subject: what there is to know, told the same way;\n"
                . "- a conclusion that puts things in perspective and leaves a strong image.\n"
                . "Rules:\n"
                . "- only facts found in the page: never invent a figure, a quote or an anecdote; a thin page gives a short story rather than a padded one;\n"
                . "- real sentences, varied and well punctuated, with natural transitions; paragraphs separated by a blank line; no list, no title, no emoji, no stage directions;\n"
                . "- write for the ear: scores as \"2–1\", clear dates;\n"
                . "- at most $max words (about $min minutes); the length follows the richness of the page;\n"
                . "- in English. Answer with the script only."
            : "Tu es historien du FC Sochaux-Montbéliard et conteur passionné. Tu racontes à voix haute une fiche du musée en ligne Sochaux Rétro ; ton texte sera lu par une voix de synthèse.\n"
                . "Fais revivre l’histoire, comme si tu la racontais à des supporters réunis autour de toi :\n"
                . "– une accroche qui donne envie d’écouter ;\n"
                . "– une introduction qui pose le décor : l’époque, l’enjeu, l’ambiance ;\n"
                . "– le cœur du récit en plusieurs paragraphes. Pour un match : l’avant-match, le déroulé, les buts et les tournants, les coulisses, les anecdotes, les hommes. Pour une personne : ses débuts, son parcours au club, ses grands moments, son style, ses chiffres, ce qu’elle a laissé. Pour un autre sujet : ce qu’il faut en savoir, raconté de la même façon ;\n"
                . "– une conclusion qui met en perspective et laisse une image forte.\n"
                . "Règles :\n"
                . "– uniquement des faits présents dans la fiche : n’invente rien, ni chiffre, ni citation, ni anecdote ; une fiche mince donne un récit court plutôt que délayé ;\n"
                . "– de vraies phrases, variées et bien ponctuées, avec des transitions naturelles ; paragraphes séparés par une ligne vide ; ni liste, ni titre, ni émoji, ni indication de mise en scène ;\n"
                . "– écris pour l’oreille : scores « 2 à 1 », dates claires ;\n"
                . "– au plus $max mots (environ $min minutes) ; la longueur suit la richesse de la fiche ;\n"
                . "– en français. Réponds seulement par le texte à dire.";
        return [$system, (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
    }

    /** Nettoie la réponse de l'IA : sans balisage ni guillemets d'encadrement, paragraphes gardés, dans la durée maximale (+ 15 %). */
    public static function cleanAi(string $t): string
    {
        $t = trim(self::paragraphs((string) preg_replace('/[*_#`]+/u', '', $t)), " \"«»“”'\n");
        $max = (int) round(self::maxWords() * 1.15);
        return self::words($t) > $max ? self::fitText($t, $max) : $t;
    }

    /** Texte en paragraphes (un par ligne de la saisie, séparés par une ligne vide), espaces simples à l'intérieur. */
    public static function paragraphs(string $t): string
    {
        $paras = array_map(fn ($p) => trim((string) preg_replace('/\s+/u', ' ', $p)), preg_split('/\s*\n\s*/u', str_replace(["\r\n", "\r"], "\n", $t)) ?: []);
        return implode("\n\n", array_values(array_filter($paras, fn ($p) => $p !== '')));
    }

    /** Coupe un texte en paragraphes à $max mots, à la fin d'une phrase. */
    public static function fitText(string $t, int $max): string
    {
        $out = [];
        $n = 0;
        foreach (preg_split('/\n\s*\n/u', $t) ?: [] as $p) {
            $w = self::words($p);
            if ($n + $w <= $max) {
                $out[] = trim($p);
                $n += $w;
                continue;
            }
            if (($keep = self::fit([$p], $max - $n)) !== '') {
                $out[] = $keep;
            }
            break;
        }
        return implode("\n\n", $out);
    }

    /** Modèle qui rédige les textes audio : réglage propre, sinon le « Modèle de réponse » de l'assistant. */
    public static function textModel(): string
    {
        $m = trim((string) Settings::get('audio.text_model', ''));
        return $m !== '' ? $m : Gemini::model();
    }

    /** Jetons de réponse accordés à l'IA : de quoi écrire le texte le plus long, avec de la marge. */
    public static function aiTokens(): int
    {
        return max(400, self::maxWords() * 4);
    }

    /** Rédige le texte avec Gemini (tarif normal) et l'enregistre. */
    public static function writeAiText(array $doc, string $lang): array
    {
        [$system, $user] = self::aiPrompt($doc, $lang);
        $g = Gemini::generate([['role' => 'user', 'text' => $user]], $system, ['model' => self::textModel(), 'temperature' => 0.6, 'max_tokens' => self::aiTokens(), 'for' => 'audio', 'ref' => 'fiche:' . (int) $doc['id']]);
        $text = self::cleanAi($g['text']);
        if ($text === '') {
            throw new \RuntimeException('Gemini n’a pas rédigé de résumé.');
        }
        self::saveText((int) $doc['id'], $lang, $text, 'ai', self::sig($doc, $lang));
        return self::current($doc, $lang);
    }

    public static function saveText(int $id, string $lang, string $text, string $src, string $sig = ''): void
    {
        self::update($id, function ($s) use ($lang, $text, $src, $sig) {
            $s[$lang] = ['text' => trim($text), 'src' => $src, 'sig' => $sig, 'at' => date('c')] + array_intersect_key($s[$lang] ?? [], ['audio' => 1]);
            return $s;
        });
    }

    /** Retour au résumé automatique (la voix IA éventuelle reste, mais ne correspond plus au texte). */
    public static function resetText(int $id, string $lang): void
    {
        self::update($id, function ($s) use ($lang) {
            $s[$lang] = array_intersect_key($s[$lang] ?? [], ['audio' => 1]);
            return $s;
        });
    }

    // ------------------------------------------------------------------ voix IA (fichier audio)

    /** Voix IA valable pour le texte lu actuellement, sinon null. */
    public static function audio(array $doc, string $lang, ?array $cur = null): ?array
    {
        $a = self::state((int) $doc['id'])[$lang]['audio'] ?? null;
        if (!$a || empty($a['file']) || (int) ($a['v'] ?? 1) < self::VOICE_VERSION || !is_file(self::$media . '/' . $a['file'])) {
            return null;
        }
        $cur ??= self::current($doc, $lang);
        return ($a['th'] ?? '') === sha1($cur['text']) ? $a + ['url' => '/media/' . $a['file']] : null;
    }

    /** Fabrique la voix IA d'une fiche tout de suite (tarif normal). */
    public static function makeVoice(array $doc, string $lang): array
    {
        $cur = self::current($doc, $lang);
        $voice = self::voice();
        $r = Gemini::speech($cur['text'], $voice, '', 'fiche:' . (int) $doc['id']);
        return self::storeVoice((int) $doc['id'], $lang, $r['pcm'], $r['rate'], $cur['text'], $r['model'], $voice);
    }

    /** Enregistre l'audio (MP3 si possible, sinon WAV) et remplace l'éventuel fichier précédent. */
    public static function storeVoice(int $id, string $lang, string $pcm, int $rate, string $text, string $model, string $voice): array
    {
        $base = sprintf('%d-%s-%s', $id, $lang, substr(sha1($text . '|' . $voice . '|' . $model . '|' . strlen($pcm)), 0, 10));
        $entry = self::encodeVoice($base, $pcm, $rate) + ['th' => sha1($text), 'voice' => $voice, 'model' => $model, 'v' => self::VOICE_VERSION, 'at' => date('c')];
        $old = null;
        self::update($id, function ($s) use ($lang, $entry, &$old) {
            $old = $s[$lang]['audio']['file'] ?? null;
            $s[$lang]['audio'] = $entry;
            return $s;
        });
        if ($old && $old !== $entry['file']) {
            @unlink(self::$media . '/' . $old);
        }
        return $entry + ['url' => '/media/' . $entry['file']];
    }

    /**
     * Range une voix dans public/media/{$sub}/ : MP3 si ffmpeg est présent, sinon WAV.
     * ['file' => chemin sous media/, 'dur' => secondes, 'bytes'].
     */
    public static function encodeVoice(string $base, string $pcm, int $rate, string $sub = 'audio'): array
    {
        $dir = self::$media . '/' . $sub;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Dossier impossible à créer : ' . $dir);
        }
        $pcm = self::trimTail($pcm, $rate);
        $file = null;
        if ($ff = self::ffmpeg()) {
            $tmp = self::$dir . "/tmp-$base.wav";
            @mkdir(self::$dir, 0775, true);
            file_put_contents($tmp, self::wav($pcm, $rate));
            $out = "$dir/$base.mp3";
            exec(escapeshellarg($ff) . ' -loglevel error -y -i ' . escapeshellarg($tmp) . ' -ac 1 -codec:a libmp3lame -b:a 48k ' . escapeshellarg($out) . ' 2>&1', $o, $code);
            @unlink($tmp);
            if ($code === 0 && is_file($out) && filesize($out) > 0) {
                $file = $base . '.mp3';
            } else {
                @unlink($out);
            }
        }
        // Sans ffmpeg (o2switch) : encodeur MP3 du site, en PHP (environ 10 s de calcul pour 3 min de voix).
        if ($file === null && self::$mp3 !== false && isset(Mp3Encoder::RATES[$rate])) {
            try {
                self::put("$dir/$base.mp3", Mp3Encoder::encode($pcm, $rate, self::MP3_KBPS));
                $file = $base . '.mp3';
            } catch (\Throwable $e) {
                error_log('[audio] MP3 : ' . $e->getMessage());
            }
        }
        if ($file === null) {
            self::put("$dir/$base.wav", self::wav($pcm, $rate));
            $file = $base . '.wav';
        }
        return ['file' => "$sub/$file", 'dur' => round(strlen($pcm) / (2 * max(1, $rate)), 1), 'bytes' => (int) filesize("$dir/$file")];
    }

    /** Écrit un fichier d'un coup (jamais servi à moitié écrit). */
    private static function put(string $path, string $data): void
    {
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $data) === false || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Écriture impossible : ' . $path);
        }
    }

    /**
     * Fin de la voix nettoyée : la synthèse vocale ajoute parfois, après la dernière phrase, du
     * bruit (grésillement) ou un long silence. Coupe 0,26 s après le dernier son voisé (voyelle,
     * consonne sonore : son périodique, au moins 60 ms de suite), plus tôt si le silence revient,
     * avec un fondu de 50 ms. $cut reçoit les secondes retirées.
     */
    public static function trimTail(string $pcm, int $rate, ?float &$cut = null): string
    {
        $cut = 0.0;
        $fs = intdiv($rate, 50); // trames de 20 ms
        $total = intdiv(strlen($pcm), 2);
        $nf = intdiv($total, max(1, $fs));
        if ($fs < 160 || $nf < 50) {
            return $pcm;
        }
        $db = [];
        for ($f = 0; $f < $nf; $f++) {
            $e = 0.0;
            foreach (unpack("v$fs", $pcm, $f * $fs * 2) as $v) {
                $v = $v > 32767 ? $v - 65536 : $v;
                $e += $v * $v;
            }
            $db[$f] = 10 * log10($e / $fs / 1073741824 + 1e-12);
        }
        // Niveau de la voix : les trames fortes (9e décile), silences exclus.
        $loud = array_values(array_filter($db, fn ($d) => $d > -60));
        if (!$loud) {
            return $pcm;
        }
        sort($loud);
        $ref = $loud[(int) floor(0.9 * (count($loud) - 1))];
        // Dernier son voisé, en remontant depuis la fin (30 s au plus) : 3 trames périodiques de suite.
        $last = -1;
        $run = 0;
        for ($f = $nf - 1; $f >= max(0, $nf - 1500); $f--) {
            $voiced = $db[$f] > $ref - 30 && self::periodicity($pcm, $f * $fs, $fs, $rate) >= 0.5;
            $run = $voiced ? $run + 1 : 0;
            if ($run === 3) {
                $last = $f + 2;
                break;
            }
        }
        if ($last < 0) {
            return $pcm;
        }
        $end = min($nf, $last + 14); // 0,26 s pour la consonne finale (« s », « ch »…)
        for ($f = $last + 1; $f < $end; $f++) {
            if ($db[$f] < $ref - 35) {
                $end = $f;
                break;
            }
        }
        $keep = $end * $fs;
        if ($total - $keep < intdiv($rate, 10)) {
            return $pcm; // moins de 0,1 s à retirer : rien à faire
        }
        $cut = round(($total - $keep) / $rate, 2);
        // Fondu de sortie (50 ms) sur la fin gardée.
        $fade = min($keep, intdiv($rate, 20));
        $tail = '';
        $i = 0;
        foreach (unpack("v$fade", $pcm, ($keep - $fade) * 2) as $v) {
            $v = $v > 32767 ? $v - 65536 : $v;
            $tail .= pack('v', (int) round($v * 0.5 * (1 + cos(M_PI * ++$i / $fade))) & 0xFFFF);
        }
        return substr($pcm, 0, ($keep - $fade) * 2) . $tail;
    }

    /**
     * Périodicité d'une trame (0 à 1) : autocorrélation normalisée la plus forte pour une hauteur
     * de voix de 70 à 400 Hz, sur le signal pré-accentué ramené vers 8 kHz. Une voyelle dépasse
     * 0,8 ; un bruit, blanc ou grave, reste sous 0,4.
     */
    private static function periodicity(string $pcm, int $start, int $len, int $rate): float
    {
        $d = max(1, intdiv($rate, 8000));
        $sr = $rate / $d;
        [$lo, $hi] = [(int) floor($sr / 400), (int) ceil($sr / 70)];
        $need = min($len + $d * ($hi + 1), intdiv(strlen($pcm), 2) - $start);
        if ($need < $len) {
            return 0.0;
        }
        $y = [];
        $prev = 0;
        $acc = 0.0;
        $k = 0;
        foreach (unpack("v$need", $pcm, $start * 2) as $v) {
            $v = $v > 32767 ? $v - 65536 : $v;
            $acc += $v - 0.95 * $prev;
            $prev = $v;
            if (++$k === $d) {
                $y[] = $acc;
                $acc = 0.0;
                $k = 0;
            }
        }
        $n = intdiv($len, $d);
        $hi = min($hi, count($y) - $n);
        $e0 = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $e0 += $y[$i] * $y[$i];
        }
        $best = 0.0;
        for ($t = $lo; $t <= $hi && $e0 > 0; $t++) {
            $c = $e1 = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $c += $y[$i] * $y[$i + $t];
                $e1 += $y[$i + $t] * $y[$i + $t];
            }
            if ($e1 > 0 && ($r = $c / sqrt($e0 * $e1)) > $best) {
                $best = $r;
            }
        }
        return $best;
    }

    /** PCM et fréquence d'un fichier WAV (16 bits mono), null s'il n'en est pas un. */
    public static function fromWav(string $wav): ?array
    {
        if (strlen($wav) < 44 || !str_starts_with($wav, 'RIFF') || substr($wav, 8, 4) !== 'WAVE') {
            return null;
        }
        $rate = null;
        for ($p = 12; $p + 8 <= strlen($wav);) {
            $id = substr($wav, $p, 4);
            $len = unpack('V', $wav, $p + 4)[1];
            if ($id === 'fmt ') {
                $f = unpack('vfmt/vch/Vrate/Vbps/valign/vbits', $wav, $p + 8);
                if ($f['fmt'] !== 1 || $f['ch'] !== 1 || $f['bits'] !== 16) {
                    return null;
                }
                $rate = $f['rate'];
            } elseif ($id === 'data' && $rate) {
                $pcm = substr($wav, $p + 8, $len);
                return ['pcm' => strlen($pcm) % 2 ? substr($pcm, 0, -1) : $pcm, 'rate' => $rate];
            }
            $p += 8 + $len + ($len % 2);
        }
        return null;
    }

    /**
     * Voix enregistrées en WAV (avant l'encodeur MP3 du site) : converties en MP3, fin nettoyée,
     * tant qu'il reste du temps ; l'ancien fichier est supprimé. Nombre de voix converties.
     */
    public static function convertWavs(float $deadline): int
    {
        if (self::$mp3 === false) {
            return 0;
        }
        $n = 0;
        $stores = array_merge(
            array_filter(glob(self::$dir . '/*.json') ?: [], fn ($f) => (bool) preg_match('#/\d+\.json$#', $f)),
            glob(PageAudio::$dir . '/*.json') ?: []
        );
        foreach ($stores as $store) {
            foreach (JsonStore::read($store, []) ?: [] as $lang => $x) {
                $old = $x['audio']['file'] ?? '';
                if (!is_string($old) || !str_ends_with($old, '.wav')) {
                    continue;
                }
                if (microtime(true) >= $deadline) {
                    return $n;
                }
                $w = is_file(self::$media . '/' . $old) ? self::fromWav((string) file_get_contents(self::$media . '/' . $old)) : null;
                if (!$w || (!self::ffmpeg() && !isset(Mp3Encoder::RATES[$w['rate']]))) {
                    continue;
                }
                $e = self::encodeVoice(basename($old, '.wav'), $w['pcm'], $w['rate'], dirname($old));
                if (str_ends_with($e['file'], '.wav')) {
                    return $n; // encodeur en échec : on réessaiera au prochain passage
                }
                $done = false;
                JsonStore::update($store, function ($s) use ($lang, $old, $e, &$done) {
                    if (($s[$lang]['audio']['file'] ?? null) === $old) {
                        $s[$lang]['audio'] = $e + $s[$lang]['audio'];
                        $done = true;
                    }
                    return $s;
                }, []);
                if ($done && $e['file'] !== $old) {
                    @unlink(self::$media . '/' . $old);
                    $n++;
                } elseif (!$done && $e['file'] !== $old) {
                    @unlink(self::$media . '/' . $e['file']); // voix remplacée entre-temps
                }
            }
        }
        return $n;
    }

    public static function deleteVoice(int $id, string $lang): void
    {
        $old = null;
        self::update($id, function ($s) use ($lang, &$old) {
            $old = $s[$lang]['audio']['file'] ?? null;
            unset($s[$lang]['audio']);
            return $s;
        });
        if ($old) {
            @unlink(self::$media . '/' . $old);
        }
    }

    /** En-tête WAV (PCM 16 bits mono) devant les octets bruts renvoyés par Gemini. */
    public static function wav(string $pcm, int $rate): string
    {
        $len = strlen($pcm);
        return 'RIFF' . pack('V', 36 + $len) . 'WAVE' . 'fmt ' . pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16) . 'data' . pack('V', $len) . $pcm;
    }

    private static function ffmpeg(): ?string
    {
        if (self::$mp3 === false) {
            return null;
        }
        if (self::$ffmpeg === null) {
            self::$ffmpeg = '';
            $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
            if (function_exists('exec') && !in_array('exec', $disabled, true)) {
                foreach (['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/bin/ffmpeg'] as $p) {
                    if (@is_executable($p)) {
                        self::$ffmpeg = $p;
                        break;
                    }
                }
            }
        }
        return self::$ffmpeg !== '' ? self::$ffmpeg : null;
    }

    // ------------------------------------------------------------------ sur le site

    /** Ce dont la page a besoin : texte lu, fichier audio éventuel, langue de la voix. */
    public static function forPage(array $doc, string $lang): ?array
    {
        if (!self::enabled() || empty($doc['id'])) {
            return null;
        }
        $lang = $lang === 'en' && in_array('en', self::langs($doc), true) ? 'en' : 'fr';
        $cur = self::current($doc, $lang);
        if ($cur['text'] === '') {
            return null;
        }
        $a = self::audio($doc, $lang, $cur);
        $sec = (float) ($a['dur'] ?? 0) ?: self::words($cur['text']) / self::WPM * 60;
        return ['text' => $cur['text'], 'url' => $a['url'] ?? null, 'lang' => self::LANGS[$lang], 'dur' => $a['dur'] ?? null, 'secs' => (int) round($sec)];
    }

    // ------------------------------------------------------------------ traitement groupé

    public static function jobs(): array
    {
        $j = JsonStore::read(self::$dir . '/jobs.json', []);
        return is_array($j['jobs'] ?? null) ? $j['jobs'] : [];
    }

    private static function saveJob(array $job): void
    {
        JsonStore::update(self::$dir . '/jobs.json', function ($j) use ($job) {
            $j = is_array($j) ? $j : [];
            $list = $j['jobs'] ?? [];
            $found = false;
            foreach ($list as $i => $x) {
                if ($x['id'] === $job['id']) {
                    // L'annulation demandée depuis le back-office l'emporte.
                    $list[$i] = ($x['state'] === 'annule' && !in_array($job['state'], ['annule', 'termine'], true)) ? $x : $job;
                    $found = true;
                }
            }
            if (!$found) {
                $list[] = $job;
            }
            $j['jobs'] = array_slice($list, -60);
            return $j;
        }, []);
    }

    /**
     * Ce qu'il reste à faire pour passer tout le musée en voix IA :
     * ['text' => clés à faire rédiger d'abord, 'voice' => clés à faire lire, 'words' => mots en moyenne].
     */
    public static function plan(array $langs, bool $redo = false, ?array $only = null, bool $textOnly = false): array
    {
        self::loadTemplates();
        try {
            return self::computePlan($langs, $redo, $only, $textOnly);
        } finally {
            self::saveTemplates();
        }
    }

    private static function computePlan(array $langs, bool $redo, ?array $only, bool $textOnly): array
    {
        $aiText = (bool) Settings::get('audio.ai_text', true);
        $text = $voice = [];
        $words = 0;
        $n = 0;
        $want = $only !== null ? array_flip($only) : null;
        $ids = $want !== null ? array_unique(array_map(fn ($k) => (int) $k, $only)) : array_keys(Index::published());
        foreach ($ids as $id) {
            $doc = Fiches::get((int) $id);
            if (!$doc || !Fiches::isVisible($doc)) {
                continue;
            }
            foreach (self::langs($doc) as $lang) {
                if ($want !== null ? !isset($want[(int) $id . '-' . $lang]) : !in_array($lang, $langs, true)) {
                    continue;
                }
                $cur = self::current($doc, $lang);
                if ($cur['text'] === '') {
                    continue;
                }
                $key = (int) $id . '-' . $lang;
                if ($textOnly) {
                    // Textes seulement : à rédiger (ou à refaire) par l'IA ; jamais un texte écrit à la main.
                    if ($cur['src'] === 'manual' || ($cur['src'] === 'ai' && !$redo)) {
                        continue;
                    }
                    $text[] = $key;
                    $words += self::words($cur['text']);
                    $n++;
                    continue;
                }
                if (!$redo && self::audio($doc, $lang, $cur)) {
                    continue;
                }
                if ($aiText && $cur['src'] === 'auto') {
                    $text[] = $key;
                } else {
                    $voice[] = $key;
                }
                $words += self::words($cur['text']);
                $n++;
            }
        }
        return ['text' => $text, 'voice' => $voice, 'words' => $n ? $words / $n : self::maxWords() * 0.6];
    }

    /** Coût estimé en dollars (traitement groupé : moitié prix) pour $nText rédactions et $nVoice voix. */
    public static function estimate(int $nText, int $nVoice, float $words, bool $batch = true, bool $voice = true): array
    {
        $tts = AiCosts::price(Gemini::ready() ? Gemini::ttsModel() : 'gemini-3.8-flash-tts');
        $txt = AiCosts::price(Gemini::ready() ? self::textModel() : 'gemini-2.5-flash-lite');
        $seconds = max(10.0, $words / 2.5); // environ 150 mots par minute
        $voiceUsd = ($words * 1.6 + 40) * $tts['in'] / 1e6 + $seconds * 25 * $tts['out'] / 1e6;
        $textUsd = 8000 * $txt['in'] / 1e6 + ($words * 1.7 + 60) * $txt['out'] / 1e6;
        $usd = ($nText * $textUsd + ($voice ? ($nVoice + $nText) * $voiceUsd : 0)) * ($batch ? 0.5 : 1);
        return ['usd' => $usd, 'eur' => AiCosts::eur($usd), 'per' => AiCosts::eur($voiceUsd * ($batch ? 0.5 : 1)), 'seconds' => $seconds];
    }

    /**
     * Lance le traitement groupé (créé ici, envoyé à Google par la tâche planifiée).
     * $limit : seulement les N premières fiches (pour écouter la voix avant de tout lancer).
     */
    public static function launch(array $langs, bool $redo, ?array $user, ?array $keys = null, int $limit = 0, bool $textOnly = false): array
    {
        $plan = self::plan($langs, $redo, $keys, $textOnly);
        if ($limit > 0) {
            $plan['text'] = array_slice($plan['text'], 0, $limit);
            $plan['voice'] = array_slice($plan['voice'], 0, max(0, $limit - count($plan['text'])));
        }
        $made = [];
        foreach (array_chunk($plan['text'], self::BATCH_TEXT) as $chunk) {
            $made[] = self::newJob('texte', $chunk, $user, !$textOnly);
        }
        foreach (array_chunk($plan['voice'], self::voiceBatch()) as $chunk) {
            $made[] = self::newJob('voix', $chunk, $user, false);
        }
        foreach ($made as $job) {
            self::saveJob($job);
        }
        return ['jobs' => count($made), 'text' => count($plan['text']), 'voice' => count($plan['voice'])];
    }

    /** Textes à faire rédiger en traitement groupé (pages de synthèse), voix IA ensuite si $thenVoice. Nombre d'envois créés. */
    public static function queueTexts(array $keys, ?array $user, bool $pages = false, bool $thenVoice = false): int
    {
        $n = 0;
        foreach (array_chunk(array_values($keys), self::BATCH_TEXT) as $chunk) {
            self::saveJob(self::newJob('texte', $chunk, $user, $thenVoice) + ($pages ? ['pages' => true] : []));
            $n++;
        }
        return $n;
    }

    /** Voix IA à enregistrer en traitement groupé (pages de synthèse). Nombre d'envois créés. */
    public static function queueVoices(array $keys, ?array $user, bool $pages = false): int
    {
        $n = 0;
        foreach (array_chunk(array_values($keys), self::voiceBatch()) as $chunk) {
            self::saveJob(self::newJob('voix', $chunk, $user, false) + ($pages ? ['pages' => true] : []));
            $n++;
        }
        return $n;
    }

    private static function newJob(string $kind, array $keys, ?array $user, bool $thenVoice): array
    {
        return [
            'id' => $kind[0] . date('ymdHis') . bin2hex(random_bytes(3)), 'kind' => $kind, 'keys' => array_values($keys), 'state' => 'attente',
            'model' => $kind === 'voix' ? Gemini::ttsModel() : self::textModel(), 'voice' => self::voice(), 'then_voice' => $thenVoice,
            'batch' => null, 'cursor' => 0, 'done' => 0, 'errors' => 0, 'created' => time(), 'updated' => time(), 'polled' => 0,
            'by' => (string) ($user['name'] ?? 'Tâche automatique'), 'message' => '',
        ];
    }

    public static function cancel(string $id): bool
    {
        foreach (self::jobs() as $job) {
            if ($job['id'] !== $id || in_array($job['state'], ['termine', 'echec', 'annule'], true)) {
                continue;
            }
            if (!empty($job['batch']) && $job['state'] === 'envoye') {
                try {
                    Gemini::batchCancel($job['batch']);
                } catch (\Throwable $e) {
                    error_log('[audio] annulation : ' . $e->getMessage());
                }
            }
            $job['state'] = 'annule';
            $job['updated'] = time();
            $job['message'] = 'Annulé.';
            JsonStore::update(self::$dir . '/jobs.json', function ($j) use ($job) {
                foreach ($j['jobs'] ?? [] as $i => $x) {
                    if ($x['id'] === $job['id']) {
                        $j['jobs'][$i] = $job;
                    }
                }
                return $j;
            }, []);
            self::cleanup($job);
            return true;
        }
        return false;
    }

    /**
     * Tâche planifiée : envoie les travaux en attente, interroge Google, récupère et range les
     * résultats ($seconds au plus), puis relance chaque nuit les voix devenues anciennes.
     */
    public static function run(int $seconds = 40): ?string
    {
        if (!Gemini::ready()) {
            return null;
        }
        $deadline = microtime(true) + $seconds;
        $log = [];
        foreach (self::jobs() as $job) {
            try {
                // Un même travail avance d'autant d'étapes que le temps le permet : envoi,
                // vérification chez Google, rangement des résultats.
                for ($step = 0; $step < 4 && microtime(true) < $deadline; $step++) {
                    $before = [$job['state'], $job['cursor'], $job['done']];
                    $job = match ($job['state']) {
                        'attente' => AiCosts::paused('audio') ? $job : self::submit($job),
                        'envoye' => self::poll($job),
                        'recup' => self::process($job, $deadline),
                        default => $job,
                    };
                    if ([$job['state'], $job['cursor'], $job['done']] === $before && $job['state'] !== 'envoye') {
                        break;
                    }
                    $job['updated'] = time();
                    self::saveJob($job);
                    if ($job['state'] === 'envoye' && $before[0] === 'envoye') {
                        break; // pas encore prêt chez Google : on repassera
                    }
                    if (in_array($job['state'], ['termine', 'echec', 'annule'], true)) {
                        $log[] = $job['kind'] . ' ' . $job['id'] . ' : ' . $job['state'] . ' (' . $job['done'] . '/' . count($job['keys']) . ')';
                        break;
                    }
                }
            } catch (\Throwable $e) {
                $job['message'] = $e->getMessage();
                $job['tries'] = (int) ($job['tries'] ?? 0) + 1;
                if ($job['tries'] >= 5) {
                    $job['state'] = 'echec';
                    self::cleanup($job);
                }
                $job['updated'] = time();
                self::saveJob($job);
                $log[] = $job['id'] . ' : ' . $e->getMessage();
            }
        }
        if ($r = self::nightly()) {
            $log[] = $r;
        }
        if ($n = self::convertWavs($deadline)) {
            $log[] = $n . ' voix converties en MP3';
        }
        return $log ? implode(' ; ', $log) : null;
    }

    /** Prépare le fichier des demandes, l'envoie et crée le traitement groupé chez Google. */
    private static function submit(array $job): array
    {
        @mkdir(self::$dir . '/jobs', 0775, true);
        $in = self::$dir . "/jobs/{$job['id']}-demandes.jsonl";
        $side = [];
        $fp = fopen($in, 'wb');
        foreach ($job['keys'] as $key) {
            // Récit d'une page de synthèse (face-à-face, saison, bilan, records, chiffres).
            if (PageAudio::isKey($key)) {
                $p = $job['kind'] === 'texte' ? PageAudio::request($key, (string) $job['model']) : PageAudio::speechRequest($key, (string) $job['voice']);
                if ($p) {
                    [$req, $side[$key]] = $p;
                    fwrite($fp, json_encode(['key' => $key, 'request' => $req], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
                }
                continue;
            }
            [$id, $lang] = explode('-', $key) + [1 => 'fr'];
            $doc = Fiches::get((int) $id);
            if (!$doc) {
                continue;
            }
            if ($job['kind'] === 'texte') {
                [$system, $user] = self::aiPrompt($doc, $lang);
                $req = Gemini::requestBody([['role' => 'user', 'text' => $user]], $system, ['temperature' => 0.6, 'max_tokens' => self::aiTokens()], $job['model']);
                $side[$key] = self::sig($doc, $lang);
            } else {
                $text = self::current($doc, $lang)['text'];
                $req = Gemini::speechRequest($text, $job['voice']);
                $side[$key] = $text;
            }
            fwrite($fp, json_encode(['key' => $key, 'request' => $req], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }
        fclose($fp);
        if (!$side) {
            @unlink($in);
            $job['state'] = 'termine';
            $job['message'] = 'Rien à envoyer : les textes ont changé entre-temps.';
            return $job;
        }
        JsonStore::write(self::$dir . "/jobs/{$job['id']}-textes.json", $side);
        $file = Gemini::uploadFile($in, 'application/jsonl', 'sochaux-retro-' . $job['id']);
        $job['batch'] = Gemini::batchCreate($job['model'], $file, 'Sochaux Rétro · ' . ($job['kind'] === 'voix' ? 'voix' : 'résumés') . ' · ' . $job['id']);
        $job['state'] = 'envoye';
        $job['polled'] = time();
        $job['message'] = 'Envoyé à Google : ' . count($side) . (!empty($job['pages']) ? ($job['kind'] === 'voix' ? ' voix de pages.' : ' récit(s) de pages.') : ' fiche(s).');
        @unlink($in);
        return $job;
    }

    private static function poll(array $job): array
    {
        if (time() - (int) $job['polled'] < (Gemini::isMock() ? 0 : 120)) {
            return $job;
        }
        $job['polled'] = time();
        $r = Gemini::batchGet((string) $job['batch']);
        if ($r['state'] === 'succeeded' && $r['file']) {
            $local = self::$dir . "/jobs/{$job['id']}-resultats.jsonl";
            Gemini::download($r['file'], $local);
            $job['state'] = 'recup';
            $job['cursor'] = 0;
            $job['message'] = 'Résultats reçus, rangement en cours.';
        } elseif (in_array($r['state'], ['failed', 'expired', 'cancelled'], true)) {
            $job['state'] = $r['state'] === 'cancelled' ? 'annule' : 'echec';
            $job['message'] = $r['error'] ?: ($r['state'] === 'expired' ? 'Google n’a pas traité la demande à temps.' : 'Échec chez Google.');
            self::cleanup($job);
        } else {
            $job['message'] = $r['state'] === 'running' ? 'En cours chez Google.' : 'En attente chez Google.';
        }
        return $job;
    }

    /** Range les résultats ligne par ligne (reprend là où il s'était arrêté). Public pour les essais. */
    public static function process(array $job, float $deadline): array
    {
        $local = self::$dir . "/jobs/{$job['id']}-resultats.jsonl";
        $side = JsonStore::read(self::$dir . "/jobs/{$job['id']}-textes.json", []) ?: [];
        $fp = is_file($local) ? fopen($local, 'rb') : false;
        if (!$fp) {
            $job['state'] = 'envoye'; // fichier perdu : on le retélécharge
            $job['polled'] = 0;
            return $job;
        }
        fseek($fp, (int) $job['cursor']);
        $ready = [];
        while (($line = fgets($fp)) !== false) {
            $row = json_decode($line, true);
            $key = (string) ($row['key'] ?? ($row['metadata']['key'] ?? ''));
            $resp = $row['response'] ?? null;
            if (PageAudio::isKey($key)) {
                $pk = PageAudio::parseKey($key);
                if (!$pk || !is_array($resp) || !isset($side[$key])) {
                    $job['errors']++;
                } elseif ($job['kind'] === 'texte') {
                    AiCosts::record('audio', (string) $job['model'], AiCosts::usage($resp) + ['batch' => true], 'page:' . $pk[0]);
                    $text = self::cleanAi(Gemini::responseText($resp));
                    if ($text !== '') {
                        PageAudio::saveText($pk[0], $pk[1], $text, (string) $side[$key], (string) $job['model']);
                        $ready[] = $key;
                        $job['done']++;
                    } else {
                        $job['errors']++;
                    }
                } else {
                    AiCosts::record('audio', (string) $job['model'], AiCosts::usage($resp) + ['batch' => true], 'page:' . $pk[0]);
                    $a = Gemini::speechAudio($resp);
                    if ($a) {
                        PageAudio::storeVoice($pk[0], $pk[1], $a['pcm'], $a['rate'], (string) $side[$key], (string) $job['model'], (string) $job['voice']);
                        $job['done']++;
                    } else {
                        $job['errors']++;
                    }
                }
                $job['cursor'] = ftell($fp);
                if (microtime(true) > $deadline) {
                    break;
                }
                continue;
            }
            [$id, $lang] = explode('-', $key) + [1 => 'fr'];
            if ($key === '' || !is_array($resp) || !isset($side[$key])) {
                $job['errors']++;
            } elseif ($job['kind'] === 'texte') {
                AiCosts::record('audio', (string) $job['model'], AiCosts::usage($resp) + ['batch' => true], "fiche:$id");
                $text = self::cleanAi(Gemini::responseText($resp));
                if ($text !== '') {
                    $st = self::state((int) $id)[$lang] ?? [];
                    if (($st['src'] ?? '') !== 'manual') { // un texte écrit à la main entre-temps l'emporte
                        self::saveText((int) $id, $lang, $text, 'ai', (string) $side[$key]);
                    }
                    $ready[] = $key;
                    $job['done']++;
                } else {
                    $job['errors']++;
                }
            } else {
                AiCosts::record('audio', (string) $job['model'], AiCosts::usage($resp) + ['batch' => true], "fiche:$id");
                $a = Gemini::speechAudio($resp);
                if ($a) {
                    self::storeVoice((int) $id, $lang, $a['pcm'], $a['rate'], (string) $side[$key], (string) $job['model'], (string) $job['voice']);
                    $job['done']++;
                } else {
                    $job['errors']++;
                }
            }
            $job['cursor'] = ftell($fp);
            if (microtime(true) > $deadline) {
                break;
            }
        }
        $eof = feof($fp) || fgets($fp) === false;
        fclose($fp);
        if ($ready && $job['then_voice']) {
            $job['ready'] = array_values(array_unique(array_merge($job['ready'] ?? [], $ready)));
        }
        if ($eof) {
            $job['state'] = 'termine';
            $what = empty($job['pages']) ? ' fiche(s) traitée(s)' : ($job['kind'] === 'voix' ? ' voix de pages enregistrée(s)' : ' récit(s) rédigé(s)');
            $job['message'] = $job['done'] . $what . ($job['errors'] ? ', ' . $job['errors'] . ' en échec' : '') . '.';
            if (!empty($job['ready'])) {
                foreach (array_chunk($job['ready'], self::voiceBatch()) as $chunk) {
                    self::saveJob(self::newJob('voix', $chunk, ['name' => $job['by']], false) + (!empty($job['pages']) ? ['pages' => true] : []));
                }
            }
            unset($job['ready']);
            self::cleanup($job);
        }
        return $job;
    }

    private static function cleanup(array $job): void
    {
        foreach (['demandes.jsonl', 'resultats.jsonl', 'textes.json'] as $f) {
            @unlink(self::$dir . "/jobs/{$job['id']}-$f");
        }
    }

    /**
     * Chaque nuit (après 2 h) : refait les voix IA devenues anciennes (réglage « auto_update ») et
     * fait rédiger les récits des pages de synthèse manquants ou dépassés (réglage « pages_ai »).
     */
    private static function nightly(): ?string
    {
        $voices = (bool) Settings::get('audio.auto_update', true);
        $pages = (bool) Settings::get('audio.pages_ai', true) && (bool) Settings::get('audio.ai_text', true);
        if ((!$voices && !$pages) || (int) date('G') < 2) {
            return null;
        }
        $meta = JsonStore::read(self::$dir . '/jobs.json', []) ?: [];
        if (($meta['nightly'] ?? '') === date('Y-m-d')) {
            return null;
        }
        JsonStore::update(self::$dir . '/jobs.json', function ($j) {
            $j = is_array($j) ? $j : [];
            $j['nightly'] = date('Y-m-d');
            return $j;
        }, []);
        $log = [];
        if ($pages && PageAudio::activated() && !AiCosts::paused('audio')) {
            $r = PageAudio::launch(false, null);
            if ($r['text'] || $r['voice']) {
                $log[] = $r['text'] . ' récit(s) de pages de synthèse et ' . $r['voice'] . ' voix confiés à l’IA (traitement groupé)';
            }
        }
        if (!$voices) {
            return $log ? implode(' ; ', $log) : null;
        }
        $stale = [];
        foreach (glob(self::$dir . '/*.json') ?: [] as $f) {
            if (!preg_match('#/(\d+)\.json$#', $f, $m)) {
                continue;
            }
            $doc = Fiches::get((int) $m[1]);
            $st = self::state((int) $m[1]);
            foreach ($st as $lang => $x) {
                if (!is_array($x) || empty($x['audio'])) {
                    continue;
                }
                if (!$doc || !Fiches::isVisible($doc)) {
                    continue;
                }
                if (!self::audio($doc, (string) $lang)) {
                    $stale[] = (int) $m[1] . '-' . $lang;
                }
            }
        }
        if ($stale) {
            self::launch([], false, null, $stale);
            $log[] = count($stale) . ' voix IA à refaire (traitement groupé)';
        }
        return $log ? implode(' ; ', $log) : null;
    }

    /** Chiffres de l'écran de suivi (fiches publiées, voix IA, textes IA…). */
    public static function stats(): array
    {
        $out = ['fiches' => 0, 'fr_voice' => 0, 'en_voice' => 0, 'ai_text' => 0, 'manual' => 0, 'en' => 0, 'bytes' => 0, 'wav' => 0];
        foreach (Index::published() as $id => $s) {
            $out['fiches']++;
            $st = self::state((int) $id);
            if (!$st) {
                continue;
            }
            foreach ($st as $lang => $x) {
                if (!is_array($x)) {
                    continue;
                }
                if (!empty($x['audio']['file']) && is_file(self::$media . '/' . $x['audio']['file'])) {
                    $out[$lang === 'en' ? 'en_voice' : 'fr_voice']++;
                    $out['bytes'] += (int) ($x['audio']['bytes'] ?? 0);
                    $out['wav'] += str_ends_with((string) $x['audio']['file'], '.wav') ? 1 : 0;
                }
                if (($x['src'] ?? '') === 'ai') {
                    $out['ai_text']++;
                }
                if (($x['src'] ?? '') === 'manual') {
                    $out['manual']++;
                }
            }
        }
        return $out;
    }
}
