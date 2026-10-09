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
    private const PER_RUN = 150;

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
        $D = "font-family:'Big Shoulders Display',Impact,'Arial Narrow',Arial,sans-serif;text-transform:uppercase";
        $html = self::MARK
            . '<div style="' . $D . ';font-size:14px;letter-spacing:3px;color:#1F3FA8;font-weight:800">' . e(t('La semaine du')) . ' ' . e(Site::dayMonth($ts)) . '</div>'
            . '<div style="' . $D . ';font-size:34px;line-height:1;font-weight:900;color:#0E1F4D;margin:6px 0 14px">' . e(t('Cette semaine-là, dans l’histoire')) . '</div>';
        $intro = safe_html((string) Settings::get('newsletter.intro', ''));
        if (trim(strip_tags($intro)) !== '') {
            $html .= '<div style="font-size:17px;line-height:1.55;margin:0 0 8px">' . $intro . '</div>';
        }
        foreach ($items as $m) {
            $href = e($base . url($m['path']));
            $img = $m['image'] ? $base . img($m['image'], 800) : null;
            $score = $m['us'] !== null ? ($m['sh'] ? $m['us'] . ' – ' . $m['them'] : $m['them'] . ' – ' . $m['us']) : '–';
            $ago = (int) date('Y', $ts) - (int) substr((string) $m['date'], 0, 4);
            $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0 0;background:#FFFDF6;border:3px solid #0E1F4D">'
                . ($img ? '<tr><td style="padding:0;border-bottom:3px solid #F6C400"><a href="' . $href . '"><img src="' . e($img) . '" width="546" alt="" style="display:block;width:100%;max-width:546px;height:auto;border:0"></a></td></tr>' : '')
                . '<tr><td style="padding:16px 18px 18px">'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
                . '<td style="' . $D . ';font-size:14px;letter-spacing:2px;color:#1F3FA8;font-weight:800">' . e(date_fr((string) $m['date'], true)) . ' · ' . e($m['label'] ?: $m['comp']) . '</td>'
                . ($ago > 0 ? '<td align="right" style="white-space:nowrap"><span style="' . $D . ';display:inline-block;background:#F6C400;color:#0E1F4D;font-size:13px;font-weight:900;letter-spacing:1px;padding:4px 8px">' . e(sprintf(t('Il y a %d ans'), $ago)) . '</span></td>' : '')
                . '</tr></table>'
                . '<div style="' . $D . ';font-size:30px;line-height:1.05;font-weight:900;color:#0E1F4D;margin:8px 0 14px">' . e($m['home']) . ' <span style="color:#1F3FA8;white-space:nowrap">' . e($score) . '</span> ' . e($m['away']) . '</div>'
                . '<a href="' . $href . '" style="' . $D . ';display:inline-block;background:#0E1F4D;color:#F6C400;font-size:16px;font-weight:900;letter-spacing:1px;text-decoration:none;padding:10px 16px;border-bottom:4px solid #F6C400">' . e(t('Lire la fiche du match')) . ' →</a>'
                . '</td></tr></table>';
        }
        if (!$items) {
            $html .= '<p style="font-size:17px">' . e(t('Aucun match fiché cette semaine dans l’histoire : profitez-en pour explorer les saisons du club !')) . '</p><p><a href="' . e($base . url('/saisons/')) . '" style="color:#1F3FA8;font-weight:bold">' . e(t('Toutes les saisons')) . ' →</a></p>';
        }
        $days = Site::daysToCentenary();
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;background:#0E1F4D;border:3px solid #F6C400"><tr>'
            . '<td style="padding:18px 20px"><div style="' . $D . ';font-size:46px;line-height:.95;font-weight:900;color:#F6C400">J-' . (int) $days . '</div>'
            . '<div style="' . $D . ';font-size:16px;letter-spacing:2px;font-weight:800;color:#F3EDDF">' . e(t('avant les 100 ans')) . '</div></td>'
            . '<td align="right" style="padding:18px 20px"><a href="' . e($base . url('/faire-un-don/')) . '" style="' . $D . ';display:inline-block;background:#F6C400;color:#0E1F4D;font-size:16px;font-weight:900;letter-spacing:1px;padding:12px 16px;text-decoration:none">♥ ' . e(t('Faire un don')) . '</a></td></tr></table>';
        return $html;
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
        for ($n = 0; $state['pending'] && $n < self::PER_RUN; $n++) {
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
            $ok = Mailer::send((string) $s['email'], (string) ($state['subject'][$l] ?? reset($state['subject'])), $body);
            I18n::set($prev);
            $state[$ok ? 'sent' : 'failed']++;
        }
        if (!$state['pending']) {
            $state['finished'] = date('c');
            unset($state['html']);
        }
        JsonStore::write(self::STATE, $state);
        return ['sent' => (int) $state['sent'], 'remaining' => count($state['pending'])];
    }
}
