<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Core\Settings;
use App\Data\Derived;
use App\Front\Community;
use App\Front\Site;

/**
 * Newsletter hebdomadaire « Ce jour-là » : les matchs de la semaine dans l'histoire
 * du FCSM, le compte à rebours du centenaire et un rappel aux dons. Envoyée par la
 * tâche planifiée le jour et à l'heure réglés, par lots (reprise au passage suivant).
 */
final class Newsletter
{
    private const STATE = STORAGE_PATH . '/newsletter/envoi.json';
    /**
     * Rythme d'envoi : des lots de 10 à 30 e-mails, séparés par des pauses de 3 à 40 secondes
     * tirées au hasard, pendant 2 minutes au plus par passage de la tâche planifiée (qui revient
     * toutes les 5 minutes ; 45 secondes depuis le bouton « Envoyer maintenant », pour ne pas
     * faire attendre la page). Plafond horaire : la limite d'envoi de l'hébergeur n'est jamais atteinte.
     */
    private const LOT = [10, 30];
    private const PAUSE = [3, 40];
    private const BUDGET_CRON = 120;
    private const BUDGET_WEB = 45;
    private const PER_HOUR = 300;

    /** Matchs des 7 prochains jours (dans l'histoire), 2 au plus par jour, 7 au total. */
    public static function items(?int $from = null): array
    {
        $from ??= time();
        $out = [];
        for ($i = 0; $i < 7 && count($out) < 7; $i++) {
            $ts = strtotime("+$i day", $from);
            foreach (array_slice(array_filter(Derived::onThisDay(date('m-d', $ts)), fn ($m) => $m['v']), 0, 2) as $m) {
                $out[] = $m;
            }
        }
        return array_slice($out, 0, 7);
    }

    public static function subject(?int $ts = null): string
    {
        $ts ??= time();
        // Sujet traduit pour les abonnés anglophones (écran Traductions s'il a été personnalisé).
        $tpl = t((string) Settings::get('newsletter.subject', 'Ce jour-là · la semaine du {semaine}'));
        return str_replace('{semaine}', Site::dayMonth($ts), $tpl);
    }

    /** Marque reconnue par Mailer::layout : gabarit « lettre » (bandeau animé, fond nuit). */
    public const MARK = '<!--lettre-->';

    /** Corps HTML (le lien de désinscription est ajouté par abonné). */
    public static function html(?int $ts = null): string
    {
        $ts ??= time();
        $base = base_url();
        $items = self::items($ts);
        $D = "font-family:'Big Shoulders Display','Arial Narrow',Arial,sans-serif;text-transform:uppercase";
        // Titres forts (titre de la lettre, scores, compte à rebours) : Impact partout, à sa graisse
        // naturelle (jamais forcée en gras, sinon les lettres s'empâtent) ; même rendu dans toutes
        // les messageries, la plupart ne chargeant pas la police du site.
        $H = "font-family:Impact,'Arial Narrow Bold','Arial Black',sans-serif;text-transform:uppercase;font-weight:normal;letter-spacing:.5px";
        $html = self::MARK
            . '<div style="' . $D . ';font-size:14px;letter-spacing:3px;color:#1F3FA8;font-weight:700">' . e(t('La semaine du')) . ' ' . e(Site::dayMonth($ts)) . '</div>'
            . '<div style="' . $H . ';font-size:36px;line-height:1;color:#0E1F4D;margin:6px 0 14px">' . e(t('Cette semaine-là, dans l’histoire')) . '</div>';
        $intro = safe_html((string) Settings::get('newsletter.intro', ''));
        if (trim(strip_tags($intro)) !== '') {
            $html .= '<div style="font-size:17px;line-height:1.55;margin:0 0 8px">' . $intro . '</div>';
        }
        $cells = [];
        foreach ($items as $m) {
            $href = e($base . url($m['path']));
            $img = ($m['image'] ? self::photo((string) $m['image']) : null) ?? self::yearCard((int) substr((string) $m['date'], 0, 4));
            $score = $m['us'] !== null ? ($m['sh'] ? $m['us'] . ' – ' . $m['them'] : $m['them'] . ' – ' . $m['us']) : '–';
            $ago = (int) date('Y', $ts) - (int) substr((string) $m['date'], 0, 4);
            $cells[] = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFFDF6;border:3px solid #0E1F4D">'
                . '<tr><td style="padding:0;border-bottom:3px solid #F6C400;background:#0E1F4D;line-height:0">'
                . ($img ? '<a href="' . $href . '"><img src="' . e($base . $img) . '" width="252" alt="" style="display:block;width:100%;height:auto;border:0"></a>'
                    : '<a href="' . $href . '" style="display:block;' . $D . ';color:#F6C400;font-size:34px;font-weight:700;line-height:1;padding:52px 0;text-align:center;text-decoration:none">' . e(substr((string) $m['date'], 0, 4)) . '</a>')
                . '</td></tr><tr><td style="padding:12px 14px 14px">'
                . ($ago > 0 ? '<span style="' . $D . ';display:inline-block;background:#F6C400;color:#0E1F4D;font-size:12px;font-weight:700;letter-spacing:1px;padding:3px 7px">' . e(sprintf(t('Il y a %d ans'), $ago)) . '</span>' : '')
                . '<div style="' . $D . ';font-size:13px;letter-spacing:1px;color:#1F3FA8;font-weight:700;margin-top:8px">' . e(date_fr((string) $m['date'], true)) . '<br>' . e($m['label'] ?: $m['comp']) . '</div>'
                . '<div style="' . $H . ';font-size:23px;line-height:1.08;color:#0E1F4D;margin:6px 0 12px">' . e($m['home']) . ' <span style="color:#1F3FA8;white-space:nowrap">' . e($score) . '</span> ' . e($m['away']) . '</div>'
                . '<a href="' . $href . '" style="' . $D . ';display:inline-block;background:#0E1F4D;color:#F6C400;font-size:14px;font-weight:700;letter-spacing:1px;text-decoration:none;padding:8px 12px;border-bottom:3px solid #F6C400">' . e(t('Lire la fiche')) . ' →</a>'
                . '</td></tr></table>';
        }
        // Deux colonnes (une seule sur téléphone, voir .nl-col dans Mailer::letter).
        foreach (array_chunk($cells, 2) as $pair) {
            $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px"><tr>'
                . '<td class="nl-col" width="50%" valign="top" style="width:50%;padding-right:8px;vertical-align:top">' . $pair[0] . '</td>'
                . '<td class="nl-col" width="50%" valign="top" style="width:50%;padding-left:8px;vertical-align:top">' . ($pair[1] ?? '') . '</td>'
                . '</tr></table>';
        }
        if (!$items) {
            $html .= '<p style="font-size:17px">' . e(t('Aucun match fiché cette semaine dans l’histoire : profitez-en pour explorer les saisons du club !')) . '</p><p><a href="' . e($base . url('/saisons/')) . '" style="color:#1F3FA8;font-weight:bold">' . e(t('Toutes les saisons')) . ' →</a></p>';
        }
        $days = Site::daysToCentenary();
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;background:#0E1F4D;border:3px solid #F6C400"><tr>'
            . '<td style="padding:18px 20px"><div style="' . $D . ';font-size:46px;line-height:.95;font-weight:700;color:#F6C400">J-' . (int) $days . '</div>'
            . '<div style="' . $D . ';font-size:16px;letter-spacing:2px;font-weight:700;color:#F3EDDF">' . e(t('avant les 100 ans')) . '</div></td>'
            . '<td align="right" style="padding:18px 20px"><a href="' . e($base . url('/faire-un-don/')) . '" style="' . $D . ';display:inline-block;background:#F6C400;color:#0E1F4D;font-size:16px;font-weight:700;letter-spacing:1px;padding:12px 16px;text-decoration:none">♥ ' . e(t('Faire un don')) . '</a></td></tr></table>';
        return $html;
    }

    /**
     * Photo de la lettre : JPEG net (qualité 88, pris sur l'original), recadré en 3:2 sur
     * 560 × 374 (deux fois la taille affichée, pour les écrans fins). Les messageries ne lisent
     * pas toutes le WebP du site. Préparée une fois, gardée dans public/media/lettre/.
     */
    private static function photo(string $rel): ?string
    {
        $src = \App\Data\Media::file(\App\Data\Media::safeRel($rel));
        if (!$src || str_ends_with(strtolower($src), '.svg')) {
            return null;
        }
        $name = substr(sha1($rel . '|' . filemtime($src)), 0, 20) . '.jpg';
        $dest = PUBLIC_PATH . '/media/lettre/' . $name;
        if (!is_file($dest)) {
            $im = @imagecreatefromstring((string) file_get_contents($src));
            if (!$im) {
                return null;
            }
            [$w, $h] = [imagesx($im), imagesy($im)];
            $tw = 560;
            $th = 374;
            // Recadrage centré (un peu vers le haut : les visages).
            if ($w / $h > $tw / $th) {
                $cw = (int) round($h * $tw / $th);
                $ch = $h;
                $cx = (int) (($w - $cw) / 2);
                $cy = 0;
            } else {
                $cw = $w;
                $ch = (int) round($w * $th / $tw);
                $cx = 0;
                $cy = (int) (($h - $ch) / 3);
            }
            $out = imagecreatetruecolor($tw, $th);
            imagecopyresampled($out, $im, 0, 0, $cx, $cy, $tw, $th, $cw, $ch);
            @mkdir(dirname($dest), 0775, true);
            imageinterlace($out, true);
            imagejpeg($out, $dest, 88);
            imagedestroy($im);
            imagedestroy($out);
        }
        return '/media/lettre/' . $name;
    }

    /**
     * Match sans photo : une image de même format (560 × 374) avec l'année en grand, pour que
     * les deux cartes d'une ligne restent alignées.
     */
    private static function yearCard(int $year): ?string
    {
        $name = 'annee-' . $year . '.jpg';
        $dest = PUBLIC_PATH . '/media/lettre/' . $name;
        $font = APP_DIR . '/Resources/fonts/BigShouldersDisplay-Black.ttf';
        if (!is_file($dest)) {
            if (!$year || !is_file($font) || !function_exists('imagettftext')) {
                return null;
            }
            $im = imagecreatetruecolor(560, 374);
            imagefill($im, 0, 0, imagecolorallocate($im, 14, 31, 77));
            // Terrain en filigrane : ligne médiane et rond central.
            $line = imagecolorallocate($im, 32, 52, 108);
            imagesetthickness($im, 3);
            imageline($im, 280, 0, 280, 374, $line);
            imageellipse($im, 280, 187, 150, 150, $line);
            $yel = imagecolorallocate($im, 246, 196, 0);
            $box = imagettfbbox(120, 0, $font, (string) $year);
            $w = $box[2] - $box[0];
            imagettftext($im, 120, 0, (int) ((560 - $w) / 2), 187 + 50, $yel, $font, (string) $year);
            @mkdir(dirname($dest), 0775, true);
            imagejpeg($im, $dest, 90);
            imagedestroy($im);
        }
        return '/media/lettre/' . $name;
    }

    /**
     * Envoi programmé : le jour et l'heure réglés, une fois par semaine, par lots.
     * @return array{sent:int,remaining:int}|null null si ce n'est pas le moment
     */
    public static function tick(bool $force = false): ?array
    {
        if (!$force && !Settings::get('newsletter.enabled', false)) {
            return null;
        }
        // Un seul envoi à la fois (tâche planifiée et « Envoyer maintenant » simultanés).
        $dir = dirname(self::STATE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $fp = fopen(self::STATE . '.lock', 'c');
        if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
            return null;
        }
        try {
            return self::send($force);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /** La lettre de la semaine est déjà entièrement partie. */
    public static function sentThisWeek(): bool
    {
        $state = JsonStore::read(self::STATE, []) ?: [];
        return ($state['week'] ?? '') === date('o-W') && empty($state['pending']);
    }

    private static function send(bool $force): ?array
    {
        $week = date('o-W');
        JsonStore::forget(self::STATE);
        $state = JsonStore::read(self::STATE, []) ?: [];
        $inProgress = ($state['week'] ?? '') === $week && !empty($state['pending']);
        if (!$inProgress) {
            $due = (int) date('N') === (int) Settings::get('newsletter.weekday', 1) && (int) date('G') >= (int) Settings::get('newsletter.hour', 8);
            if (!$force && (!$due || ($state['week'] ?? '') === $week)) {
                return null;
            }
            $subs = Community::subscribers();
            $prev = I18n::lang();
            $subject = [];
            $html = [];
            foreach (I18n::enabled() as $l) {
                I18n::set($l);
                $subject[$l] = self::subject();
                $html[$l] = self::html();
            }
            I18n::set($prev);
            $state = ['week' => $week, 'started' => date('c'), 'subject' => $subject, 'html' => $html, 'pending' => array_column($subs, 'token'), 'sent' => 0, 'failed' => 0];
        }
        $byToken = [];
        foreach (Community::subscribers() as $s) {
            $byToken[$s['token']] = $s;
        }
        $start = time();
        $budget = PHP_SAPI === 'cli' ? self::BUDGET_CRON : self::BUDGET_WEB;
        @set_time_limit($budget + 60);
        $hour = date('Y-m-d H');
        if (($state['hour'][0] ?? '') !== $hour) {
            $state['hour'] = [$hour, 0];
        }
        while ($state['pending'] && time() - $start < $budget && $state['hour'][1] < self::PER_HOUR) {
            $lot = min(random_int(...self::LOT), self::PER_HOUR - $state['hour'][1]);
            for ($n = 0; $state['pending'] && $n < $lot; $n++) {
                // Adresse retirée de la file et état enregistré avant l'envoi : jamais deux fois la même lettre.
                $token = array_shift($state['pending']);
                JsonStore::write(self::STATE, $state);
                $s = $byToken[$token] ?? null;
                if (!$s) {
                    continue; // désinscrit entre-temps
                }
                $prev = I18n::lang();
                $l = isset($state['html'][$s['lang'] ?? '']) ? $s['lang'] : 'fr';
                I18n::set($l);
                $unsub = base_url() . url('/newsletter/desinscription/' . $token . '/');
                $body = ($state['html'][$l] ?? reset($state['html'])) . '<p style="font-size:12px;color:#3A4A75;margin-top:24px">' . e(t('Vous recevez ce message car vous êtes inscrit(e) à la newsletter « Ce jour-là » de Sochaux Rétro.')) . ' <a href="' . e($unsub) . '" style="color:#3A4A75">' . e(t('Se désinscrire')) . '</a></p>';
                $ok = Mailer::send((string) $s['email'], (string) ($state['subject'][$l] ?? reset($state['subject'])), $body, null, [], ['List-Unsubscribe' => '<' . $unsub . '>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']);
                I18n::set($prev);
                $state[$ok ? 'sent' : 'failed']++;
                $state['hour'][1]++;
            }
            // Pause au hasard avant le lot suivant, si le temps du passage le permet.
            $pause = random_int(...self::PAUSE);
            if ($state['pending'] && time() - $start + $pause < $budget) {
                JsonStore::write(self::STATE, $state);
                sleep($pause);
            } else {
                break;
            }
        }
        if (!$state['pending']) {
            $state['finished'] = date('c');
            unset($state['html']);
        }
        JsonStore::write(self::STATE, $state);
        return ['sent' => (int) $state['sent'], 'remaining' => count($state['pending'])];
    }
}
