<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\Club;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Services\Babyfoot;
use App\Services\Carnet;
use App\Services\I18n;
use App\Services\QuizChampionship;

/**
 * Le babyfoot du musée : la page /interactif/babyfoot/ (jeu 3D en plein écran,
 * public/assets/js/babyfoot.js) et l'API /api/babyfoot (jeton de match, résultat, tableau
 * d'honneur par niveau, entrée au classement avec le compte supporter). Jamais en cache : la page
 * dépend du compte ouvert sur l'appareil.
 */
final class BabyfootPages
{
    public static function page(Request $req): Response
    {
        $c = Carnet::current();
        $me = $c ? QuizChampionship::player($c['id']) : null;
        $cfg = Babyfoot::config();
        $res = Pages::render('interactif/babyfoot', [
            'data' => self::data($cfg, $me !== null, (bool) $c),
            'title' => t($cfg['title']),
            'welcome' => isset($req->query['bienvenue']) && $me,
            'pseudo' => $me['pseudo'] ?? null,
        ], [
            'title' => t($cfg['title']),
            'description' => t('Un vrai babyfoot en 3D aux couleurs {club_de} : contre l’ordinateur, à deux sur le même écran, ou pour rejouer les grands matchs du musée.'),
            'active' => 'interactif', 'body_class' => 'page-babyfoot', 'bare' => true, 'no_cookie' => true, 'no_chat' => true,
            'styles' => ['css/babyfoot.css'], 'scripts' => ['js/babyfoot.js'],
        ]);
        $res->headers['Cache-Control'] = 'private, no-store';
        return $res;
    }

    /** Données du jeu pour le navigateur : équipes, adversaires, grands matchs, textes traduits. */
    private static function data(array $cfg, bool $member, bool $account): array
    {
        $nick = Club::s('nickname');
        return [
            'lang' => I18n::lang(),
            'api' => '/api/babyfoot',
            'url' => base_url() . url('/interactif/babyfoot/'),
            'title' => t($cfg['title']),
            'site' => Club::s('site'),
            'logo' => (string) Club::get('logo_400', '/assets/img/logo-sochaux-retro-400.png'),
            'colors' => ['navy' => Club::color('navy'), 'yellow' => Club::color('yellow'), 'blue' => Club::color('blue')],
            'us' => ['short' => $cfg['short'], 'kit' => $cfg['kit'], 'glow' => $cfg['glow'], 'nickname' => $nick],
            'opponents' => Babyfoot::opponents(),
            'history' => Babyfoot::history(),
            'levels' => array_map(fn ($l) => t($l), Babyfoot::LEVELS),
            'member' => $member,
            'account' => $account,
            'ui' => [
                'half' => t('Mi-temps'), 'golden' => t('But en or !'), 'goldenSub' => t('Prolongation : le premier but gagne.'), 'kickoff' => t('Engagement'),
                'goal' => t('But !'), 'ownGoal' => t('Contre son camp'), 'replay' => t('Ralenti'), 'skip' => t('Passer'), 'dead' => t('Balle morte'),
                'win' => t('Victoire !'), 'lose' => t('Défaite'), 'draw' => t('Match nul'), 'winDuo' => t('{t} gagne !'),
                'endKick' => t('Fin du match'), 'replayed' => t('Rejoue l’histoire · {m}'), 'vs' => t('contre l’ordinateur · {l}'), 'duo' => t('à deux'),
                'shots' => t('{n} tirs'), 'shot1' => t('1 tir'), 'saves' => t('{n} arrêts'), 'save1' => t('1 arrêt'), 'best' => t('Plus beau but : {w}, {a}'),
                'num' => t('le n°{n}'), 'csc' => t('contre son camp'),
                'readMatch' => t('Lire la fiche du match'),
                'thisTimeWin' => t('Cette fois, victoire des {n} !', ['n' => $nick]), 'sameWin' => t('Comme en vrai, victoire des {n} !', ['n' => $nick]),
                'thisTimeLose' => t('Cette fois, l’histoire tourne mal.'), 'sameLose' => t('Comme en vrai, défaite. Revanche ?'),
                'thisTimeDraw' => t('Cette fois, match nul.'), 'sameDraw' => t('Comme en vrai, match nul.'),
                'wins' => t('{n} vict.'), 'win1' => t('1 vict.'), 'boardSub' => t('Niveau {l} · cette semaine'), 'boardAll' => t('Niveau {l} · de tous les temps'),
                'empty' => t('Personne encore : à vous d’ouvrir le tableau !'), 'loading' => t('Chargement…'), 'offline' => t('Connexion perdue, réessayez.'),
                'duoNoRank' => t('Les matchs à deux ne comptent pas au tableau d’honneur.'),
                'shareText' => t('{a} {s} {b} au {t} !', ['t' => t($cfg['title'])]), 'copied' => t('Score copié : collez-le où vous voulez.'), 'saved' => t('Image du score enregistrée.'),
                'mailed' => t('Un lien vient d’être envoyé à cette adresse : ouvrez-le sur cet appareil pour entrer au tableau d’honneur (pensez aux indésirables).'),
                'joined' => t('C’est noté : vos prochaines victoires comptent au tableau d’honneur.'),
                'soundOn' => t('Allumer le son'), 'soundOff' => t('Couper le son'),
                'glide' => t('Glisse'), 'shoot' => t('Tir'), 'shootSub' => t('Appui long = fort'), 'camera' => t('Caméra'),
                'shootSimple' => t('Ou touchez la table'), 'hintTouch' => t('Touchez la table là où vous visez : votre figurine tire'),
                'hintMouse' => t('Cliquez là où vous visez : votre figurine tire'),
                'cams' => ['joueur' => t('Vue du joueur'), 'dessus' => t('Vue de dessus'), 'basse' => t('Vue basse')],
                'rotate' => t('Tournez le téléphone pour jouer en grand.'),
                'p1' => t('Joueur 1'), 'p2' => t('Joueur 2'),
            ],
        ];
    }

    public static function api(Request $req): Response
    {
        $in = $req->json() ?: [];
        if (($in['lang'] ?? '') === 'en') {
            I18n::set('en');
        }
        $action = (string) ($in['action'] ?? '');
        $c = Carnet::current();
        $me = $c ? QuizChampionship::player($c['id']) : null;
        $level = max(0, min(2, (int) ($in['level'] ?? 0)));
        switch ($action) {
            case 'commencer':
                if (!RateLimiter::hit('babyfoot', $req->ip(), 600, 3600)) {
                    return Response::json(['error' => t('Trop de demandes, réessayez plus tard.')], 429);
                }
                return Response::json(['ok' => true, 'tok' => Babyfoot::token($level, (string) ($in['format'] ?? 'temps'))]);
            case 'resultat':
                if (!RateLimiter::hit('babyfoot-res', $req->ip(), 300, 3600)) {
                    return Response::json(['error' => t('Trop de demandes, réessayez plus tard.')], 429);
                }
                $tok = (string) ($in['tok'] ?? '');
                $gf = (int) ($in['gf'] ?? -1);
                $ga = (int) ($in['ga'] ?? -1);
                $g = Babyfoot::check($tok);
                $lv = $g['level'] ?? $level;
                $week = Babyfoot::week();
                $top = fn () => Babyfoot::publicRows(Babyfoot::ranking($lv, $week), $c['id'] ?? null);
                if (!$c || !$me) {
                    // Pas encore de compte : il peut entrer au tableau ; le même jeton reste valable
                    // pour enregistrer ce résultat juste après.
                    $err = $g ? Babyfoot::plausible($gf, $ga, $g) : t('Partie expirée : rejouez pour entrer au tableau d’honneur.');
                    return Response::json(['ok' => true, 'ranked' => false, 'join' => $err === null && $gf > $ga, 'error' => $gf > $ga ? $err : null, 'level' => $lv, 'top' => $top()]);
                }
                $err = Babyfoot::record($c['id'], (string) $me['pseudo'], $gf, $ga, $tok);
                if ($err) {
                    return Response::json(['ok' => false, 'ranked' => false, 'error' => $err, 'level' => $lv, 'top' => $top()]);
                }
                $rank = Babyfoot::rankOf($lv, $week, $c['id']);
                $label = !QuizChampionship::confirmed($c['id'])
                    ? t('Dernière étape : ouvrez le lien reçu par e-mail (pensez aux indésirables). Vos victoires apparaîtront alors au tableau d’honneur.')
                    : ($rank ? t('{r} cette semaine au tableau d’honneur', ['r' => ordinal($rank)]) : '');
                return Response::json(['ok' => true, 'ranked' => true, 'rank' => $rank, 'label' => $label, 'level' => $lv, 'top' => $top()]);
            case 'classement':
                $mine = $c['id'] ?? null;
                return Response::json(['ok' => true, 'level' => $level, 'week' => Babyfoot::publicRows(Babyfoot::ranking($level, Babyfoot::week()), $mine),
                    'all' => Babyfoot::publicRows(Babyfoot::ranking($level, 'all'), $mine)]);
            case 'inscrire':
                $name = (string) ($in['name'] ?? '');
                if ($c) {
                    $err = QuizChampionship::setPseudo($c['id'], $name);
                    return $err ? Response::json(['error' => $err], 422) : Response::json(['ok' => true, 'member' => true]);
                }
                $acc = QuizLivePages::account($req, trim((string) ($in['email'] ?? '')), $name, 'babyfoot');
                if (isset($acc['error'])) {
                    return Response::json(['error' => $acc['error']], $acc['status']);
                }
                return Response::json(['ok' => true, 'member' => $acc['cid'] !== null, 'mailed' => $acc['mailed']]);
        }
        return Response::json(['error' => t('Ressource introuvable')], 404);
    }
}
