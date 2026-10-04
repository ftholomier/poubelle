<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Data\Activity;
use App\Data\Fiches as Store;
use App\Services\AiCosts;
use App\Services\FicheAudio;
use App\Services\Gemini;
use App\Services\PageAudio;

/**
 * Système › Fiches audio : état des résumés de 30 secondes, passage de tout le musée en voix
 * IA par traitement groupé (moitié prix), suivi des travaux ; actions fiche par fiche (éditeur).
 */
final class Audio extends Base
{
    private const STATES = [
        'attente' => ['En attente d’envoi', 'warn'], 'envoye' => ['Chez Google', 'info'], 'recup' => ['Rangement', 'info'],
        'termine' => ['Terminé', 'ok'], 'echec' => ['Échec', 'ko'], 'annule' => ['Annulé', 'brouillon'],
    ];

    public static function index(Request $req): Response
    {
        // Calcul sur tout le musée : les autres pages du back-office restent utilisables pendant ce temps.
        Session::release();
        $plan = FicheAudio::plan(['fr', 'en']);
        $planText = FicheAudio::plan(['fr', 'en'], false, null, true);
        $aiText = (bool) Settings::get('audio.ai_text', true);
        $nText = count($plan['text']);
        $nVoice = count($plan['voice']);
        // Texte rédigé par l'IA : en moyenne 60 % de la durée maximale (une fiche courte reste courte).
        $aiWords = FicheAudio::maxWords() * 0.6;
        $words = $aiText ? $aiWords : $plan['words'];
        $spent = 0.0;
        foreach (AiCosts::totals() as $t) {
            $spent += (float) ($t['uses']['audio']['usd'] ?? 0);
        }
        return self::html('admin/system/audio', [
            'stats' => FicheAudio::stats(), 'plan' => $plan, 'aiText' => $aiText,
            'texts' => ['n' => count($planText['text']), 'batch' => FicheAudio::estimate(count($planText['text']), 0, $aiWords, true, false), 'model' => Gemini::ready() ? FicheAudio::textModel() : null],
            'batch' => FicheAudio::estimate($nText, $nVoice, $words, true), 'direct' => FicheAudio::estimate($nText, $nVoice, $words, false),
            'jobs' => array_reverse(FicheAudio::jobs()), 'states' => self::STATES, 'recent' => self::recent(8),
            'ready' => Gemini::ready(), 'model' => Gemini::ready() ? Gemini::ttsModel() : null, 'voice' => FicheAudio::voice(),
            'spent' => AiCosts::fmt(AiCosts::eur($spent)), 'admin' => Auth::isAdmin(), 'enabled' => FicheAudio::enabled(),
            'pages' => self::pagesCard(),
        ], ['title' => 'Fiches audio', 'crumb' => 'Système', 'nav' => 'audio', 'scripts' => ['admin/audio.js']]);
    }

    /** Carte « Pages de synthèse » : récits rédigés, dernier calcul, coût estimé (sans recalculer : 10 s). */
    private static function pagesCard(): array
    {
        $last = PageAudio::lastPlan();
        $max = count(PageAudio::slugs()) * count(PageAudio::LANGS);
        $todo = $last['todo'] ?? null;
        $running = (bool) array_filter(FicheAudio::jobs(), fn ($j) => !empty($j['pages']) && in_array($j['state'], ['attente', 'envoye', 'recup'], true));
        $all = ($last['pages'] ?? (int) ($max / 2)) * 2;
        $voices = PageAudio::voicesOn();
        return [
            'stats' => PageAudio::stats(), 'last' => $last, 'running' => $running, 'auto' => (bool) Settings::get('audio.pages_ai', true),
            'estimate' => PageAudio::estimate($todo ?? $max), 'all' => PageAudio::estimate($all), 'upper' => $todo === null,
            'voices' => $voices, 'voiceEstimate' => PageAudio::voiceEstimate($voices ? ($todo ?? $max) + (int) ($last['voices'] ?? 0) : 0),
            'voiceAll' => PageAudio::voiceEstimate($all), 'activated' => PageAudio::activated(),
        ];
    }

    /** POST /admin/audio : lancer le traitement groupé, annuler un travail. */
    public static function action(Request $req): Response
    {
        if ($deny = self::denyUnlessAdmin()) {
            return $deny;
        }
        $action = $req->str('action');
        if ($action === 'pages-essai') {
            if (!Gemini::ready()) {
                return self::back('/admin/audio', null, 'Il faut une clé Gemini (Réglages › Assistant IA).');
            }
            $page = PageAudio::slugFromUrl($req->str('page'));
            if (!$page) {
                return self::back('/admin/audio', null, 'Adresse non reconnue : collez celle d’un face-à-face, d’une saison, d’un bilan, des records ou des chiffres (par exemple /face-a-face/nancy/).');
            }
            session_write_close();
            @set_time_limit(360);
            try {
                $r = PageAudio::tryPage($page[0], $page[1], $req->str('voix') !== '' && PageAudio::voicesOn());
            } catch (\Throwable $e) {
                return self::back('/admin/audio', null, 'Essai impossible : ' . $e->getMessage());
            }
            Activity::log(self::actor(), 'a essayé le récit IA de la page ' . PageAudio::urlFor($page[0], $page[1]), null);
            return self::back('/admin/audio', 'Récit rédigé (' . FicheAudio::words($r['text']) . ' mots)' . ($r['voice'] ? ' et voix IA enregistrée (' . (int) round((float) $r['voice']['dur']) . ' s)' : '') . ' : écoutez-le sur la page ' . PageAudio::urlFor($page[0], $page[1]) . ' (bouton « Écouter »).' . self::aiCost());
        }
        if ($action === 'pages') {
            if (!Gemini::ready()) {
                return self::back('/admin/audio', null, 'Il faut une clé Gemini (Réglages › Assistant IA).');
            }
            @set_time_limit(180);
            Session::release();
            PageAudio::activate(self::actor()); // la rédaction de nuit prend le relais ensuite
            $r = PageAudio::launch($req->str('refaire') !== '', self::actor());
            if (!$r['text'] && !$r['voice']) {
                return self::back('/admin/audio', 'Les ' . $r['pages'] . ' pages de synthèse ont déjà leur récit rédigé par l’IA, à jour' . (PageAudio::voicesOn() ? ', et sa voix IA.' : '.'));
            }
            Activity::log(self::actor(), 'a confié à l’IA les récits de ' . $r['text'] . ' page(s) de synthèse et ' . $r['voice'] . ' voix', null);
            return self::back('/admin/audio', ($r['text'] ? $r['text'] . ' récit(s)' : 'Aucun récit') . ' à rédiger et ' . $r['voice'] . ' voix IA à enregistrer, confiés au traitement groupé (français et anglais) : la tâche planifiée les envoie à Google puis les range dès qu’ils sont prêts, en général en quelques heures (les voix suivent les récits). En attendant, le récit automatique est lu.');
        }
        if ($action === 'annuler') {
            return FicheAudio::cancel($req->str('job')) ? self::back('/admin/audio', 'Traitement annulé.') : self::back('/admin/audio', null, 'Traitement introuvable ou déjà terminé.');
        }
        if ($action === 'lancer') {
            if (!Gemini::ready()) {
                return self::back('/admin/audio', null, 'Il faut une clé Gemini (Réglages › Assistant IA).');
            }
            $langs = array_values(array_intersect((array) ($req->post['langues'] ?? []), ['fr', 'en'])) ?: ['fr'];
            $textOnly = $req->str('quoi') === 'textes';
            Session::release();
            $r = FicheAudio::launch($langs, $req->str('refaire') !== '', self::actor(), null, $req->str('nombre') === 'essai' ? 20 : 0, $textOnly);
            if (!$r['jobs']) {
                return self::back('/admin/audio', $textOnly ? 'Tous les textes sont déjà rédigés.' : 'Toutes les fiches ont déjà leur voix IA à jour.');
            }
            if ($textOnly) {
                Activity::log(self::actor(), 'a lancé la rédaction par l’IA des textes audio de ' . $r['text'] . ' fiche(s)', null);
                return self::back('/admin/audio', $r['text'] . ' texte(s) confiés à l’IA en traitement groupé : la tâche planifiée les envoie à Google puis les range dès qu’ils sont prêts, en général en quelques heures. Ils sont lus par la voix du navigateur, gratuite.');
            }
            Activity::log(self::actor(), 'a lancé la voix IA de ' . ($r['text'] + $r['voice']) . ' fiche(s) en traitement groupé', null);
            return self::back('/admin/audio', ($r['text'] + $r['voice']) . ' fiche(s) confiées au traitement groupé (' . $r['jobs'] . ' envoi' . ($r['jobs'] > 1 ? 's' : '') . ') : la tâche planifiée les envoie à Google puis range les voix dès qu’elles sont prêtes, en général en quelques heures.');
        }
        return self::back('/admin/audio', null, 'Action inconnue.');
    }

    /**
     * POST /admin/api/audio (éditeur d'une fiche) : {id, lang, action: etat|enregistrer|ia-texte|
     * automatique|voix|supprimer-voix, text}.
     */
    public static function api(Request $req): Response
    {
        $in = $req->json();
        $doc = Store::get((int) ($in['id'] ?? 0));
        $lang = ($in['lang'] ?? 'fr') === 'en' ? 'en' : 'fr';
        if (!$doc || !in_array($lang, FicheAudio::langs($doc), true)) {
            return self::json(['ok' => false, 'error' => 'Fiche introuvable.'], 404);
        }
        $id = (int) $doc['id'];
        session_write_close();
        @set_time_limit(360);
        try {
            switch ((string) ($in['action'] ?? 'etat')) {
                case 'enregistrer':
                    $text = FicheAudio::paragraphs(strip_tags((string) ($in['text'] ?? '')));
                    $max = FicheAudio::maxWords() * 9;
                    if ($text === '' || mb_strlen($text) > $max) {
                        return self::json(['ok' => false, 'error' => 'Le texte lu doit faire entre 1 et ' . number_format($max, 0, ',', ' ') . ' caractères (environ ' . FicheAudio::maxWords() . ' mots, la durée maximale réglée).'], 422);
                    }
                    FicheAudio::saveText($id, $lang, $text, 'manual');
                    $msg = 'Texte enregistré : c’est lui qui sera lu.';
                    break;
                case 'automatique':
                    FicheAudio::resetText($id, $lang);
                    $msg = 'Retour au résumé automatique.';
                    break;
                case 'ia-texte':
                    if (!Gemini::ready()) {
                        return self::json(['ok' => false, 'error' => 'Clé Gemini non réglée (Réglages › Assistant IA).'], 422);
                    }
                    FicheAudio::writeAiText($doc, $lang);
                    $msg = 'Texte rédigé par l’IA : relisez-le avant de lui donner une voix IA.';
                    break;
                case 'voix':
                    if (!Gemini::ready()) {
                        return self::json(['ok' => false, 'error' => 'Clé Gemini non réglée (Réglages › Assistant IA).'], 422);
                    }
                    FicheAudio::makeVoice($doc, $lang);
                    $msg = 'Voix IA enregistrée : c’est elle que les visiteurs entendront.';
                    break;
                case 'supprimer-voix':
                    FicheAudio::deleteVoice($id, $lang);
                    $msg = 'Voix IA supprimée : la voix du navigateur reprend.';
                    break;
                default:
                    $msg = '';
            }
        } catch (\Throwable $e) {
            return self::json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
        return self::json(['ok' => true, 'message' => $msg . self::aiCost(), 'state' => self::stateOf($doc)]);
    }

    /** État des deux langues pour la carte « Écouter » de l'éditeur. */
    public static function stateOf(array $doc): array
    {
        $out = [];
        foreach (FicheAudio::langs($doc) as $lang) {
            $cur = FicheAudio::current($doc, $lang);
            $a = FicheAudio::audio($doc, $lang, $cur);
            $out[$lang] = $cur + [
                'words' => FicheAudio::words($cur['text']), 'url' => $a['url'] ?? null, 'dur' => $a['dur'] ?? null,
                'voice' => $a['voice'] ?? null, 'at' => isset($a['at']) ? self::ago($a['at']) : null,
                'hasVoice' => !empty(FicheAudio::state((int) $doc['id'])[$lang]['audio']), 'lang' => FicheAudio::LANGS[$lang],
            ];
        }
        return $out;
    }

    /** Dernières voix IA enregistrées (pour les écouter depuis l'écran). */
    private static function recent(int $n): array
    {
        $files = glob(FicheAudio::$dir . '/*.json') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $out = [];
        foreach ($files as $f) {
            if (!preg_match('#/(\d+)\.json$#', $f, $m)) {
                continue;
            }
            foreach (FicheAudio::state((int) $m[1]) as $lang => $x) {
                if (!empty($x['audio']['file']) && is_file(FicheAudio::$media . '/' . $x['audio']['file'])) {
                    $s = \App\Data\Index::get((int) $m[1]);
                    $out[] = ['id' => (int) $m[1], 'title' => $s['title'] ?? ('Fiche ' . $m[1]), 'lang' => strtoupper((string) $lang), 'url' => '/media/' . $x['audio']['file'], 'dur' => $x['audio']['dur'] ?? null, 'at' => $x['audio']['at'] ?? null, 'voice' => $x['audio']['voice'] ?? ''];
                }
            }
            if (count($out) >= $n) {
                break;
            }
        }
        return array_slice($out, 0, $n);
    }
}
