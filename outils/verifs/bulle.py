"""Le bouton de l'assistant, sous chacun de ses réglages.

Pourquoi un septième auditeur : la mairie règle elle-même la bulle en bas à
droite — sa forme, son fond, la couleur de son texte, son intitulé et sa
taille (Assistant IA → Le bouton sur le site). Le socle exige qu'un réglage
laissé à la mairie ait son auditeur qui en force les bornes, et celui-ci a
une raison de plus d'exister : **les six autres auditeurs ne voient jamais ce
bouton.** L'assistant est éteint tant qu'aucune clé n'est renseignée, donc la
bulle n'est pas dans la page qu'ils mesurent.

Ce n'est pas une hypothèse. C'est ce trou qui a laissé passer, pendant toute
la vie du socle, un libellé à 2,57:1 : la bulle livrée composait l'encre sur
la couleur de marque, et personne ne l'avait mesuré.

Ce script allume donc l'assistant le temps de la mesure — clé factice, aucun
appel réseau, seule la bulle est rendue — et force :

  · les cinq formes ;
  · les deux bornes de taille, plus la valeur livrée ;
  · six couples de couleurs, dont quatre volontairement mauvais (blanc sur
    jaune pâle, noir sur noir, blanc sur blanc, texte et fond identiques) ;
  · les cinq animations d'appel, chacune sur chaque forme ;
  · les quatre coins du rythme — vitesse la plus vive et la plus lente,
    croisées avec un et trois rappels — plus le cas où le nombre de rappels
    doit être réduit pour tenir dans le budget.

Il vérifie sur chacun : le contraste réel du libellé peint sur son fond
peint, la cible tactile, l'absence de débordement horizontal, le nom
accessible du bouton, et qu'un libellé montré n'est pas tronqué.

Sur les animations, il mesure trois choses que l'œil ne mesure pas :

  · le **budget de mouvement**, durée × nombre de cycles, qui doit rester
    sous cinq secondes — quelle que soit la vitesse demandée, et c'est là
    tout l'intérêt : la mairie règle librement les deux, et c'est le nombre
    de rappels qui cède. Au-delà, une animation qui démarre seule doit pouvoir
    être mise en pause par le visiteur (WCAG 2.2.2) — et il faudrait donc
    ajouter un bouton d'arrêt à côté du bouton de discussion. Le jour où
    quelqu'un passera le nombre de cycles à dix, ce script le dira ;
  · la **boîte pendant le mouvement**, relevée à chaque image pendant les deux
    premiers cycles : un balancement de quelques degrés de trop sort
    l'étiquette de l'écran par le coin — huit pixels, mesurés —, et une cible
    tactile qui se réduit en cours d'animation n'est plus une cible ;
  · le **respect du réglage « moins d'animations »** du système, mesuré dans
    un contexte qui le déclare : aucune animation ne doit y courir.

Il vérifie aussi qu'allumer l'assistant n'appelle personne. `traceurs.py` ne
peut pas le voir non plus, pour la même raison que les autres : il mesure un
site dont l'assistant est éteint. Or c'est précisément la fonctionnalité qui
aurait une raison de contacter Google — et elle ne doit pas, puisque l'appel
part du serveur.

Il rend la main sur les réglages d'origine, même s'il échoue.

Usage :
    php -S 127.0.0.1:8081 -t public public/index.php &
    python3 outils/verifs/bulle.py
    python3 outils/verifs/bulle.py --base http://127.0.0.1:8081

Sort en code 1 s'il trouve quelque chose.
"""
import argparse
import copy
import json
import os
import sys
from urllib.parse import urlparse

from playwright.sync_api import sync_playwright

RACINE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
PARAMETRES = os.path.join(RACINE, 'data', 'admin', 'parametres.json')
NAVIGATEUR = os.environ.get('CHROMIUM', '/opt/pw-browsers/chromium')

# Une seule page suffit, et c'est voulu : la bulle est en position fixe, tirée
# du même fragment, identique d'une page à l'autre. La multiplier par trente
# pages coûterait vingt minutes pour trente fois la même mesure. Ce qui varie,
# et qu'il faut donc multiplier, ce sont les RÉGLAGES.
PAGE = '/'

LARGEURS = (390, 1440)
FORMES = ('barre', 'pilule', 'rond', 'pastille', 'onglet')
ANIMATIONS = ('aucune', 'halo', 'rebond', 'balancement', 'respiration')

# Le budget de mouvement, en secondes. Voir la note de durée dans site.css.
MOUVEMENT_MAX = 5.0

# Les quatre coins du rythme, plus la valeur livrée et les deux cas où le
# nombre de rappels doit céder. Un réglage dont on n'a mesuré que la valeur
# livrée est un défaut en attente : c'est la règle du socle, et elle vaut pour
# celui-ci comme pour la taille du logo.
#
# (vitesse en ms, rappels demandés, rappels attendus une fois le budget appliqué)
RYTHMES = (
    (800, 1, 1),
    (800, 3, 3),
    (1600, 3, 3),      # la valeur livrée : 4,8 s
    (2500, 2, 2),      # exactement 5,0 s — la borne, atteinte
    (2600, 3, 1),      # 7,8 s demandées : deux rappels doivent tomber
    (3000, 1, 1),
    (3000, 3, 1),
)

# Les bornes, et la valeur livrée entre les deux. Rien au-delà : le contrôleur
# et la classe bornent toutes deux, et un réglage hors bornes ne peut pas
# atteindre la page — c'est ce que vérifie `test_bornes`.
TAILLES = (44, 52, 76)
TAILLE_LIVREE = 52

# Les quatre derniers couples sont des pièges : ils ne doivent PAS produire un
# bouton illisible, puisque la couleur de texte est résolue avant d'être
# peinte. Si l'un d'eux passe sous 4,5:1, c'est la résolution qui est en
# défaut, pas le choix de la mairie.
COULEURS = (
    ('',        '#ffffff', 'livrée — fond de la commune, texte blanc'),
    ('#7a1f1f', '#f4e3c8', 'bordeaux et crème, choix plausible'),
    ('#f7e58a', '#ffffff', 'blanc sur jaune pâle — 1,26:1 sans correction'),
    ('#101010', '#0a0a0a', 'noir sur noir'),
    ('#ffffff', '#f2f2f2', 'blanc sur blanc'),
    ('#3d7ea6', '#3d7ea6', 'texte et fond identiques'),
)

CONTRASTE_MINI = 4.5
CIBLE_MINI = 44

# Ce que le navigateur rapporte du bouton, une fois la page servie. Tout est lu
# sur les styles calculés : le fond de la bulle est opaque, donc la composition
# est exacte et il n'y a pas lieu d'échantillonner des pixels comme le fait
# l'auditeur de contraste sur les bandeaux photographiques.
RELEVE = """() => {
  const b = document.querySelector('[data-assistant] .assistant__bulle');
  if (!b) return null;
  const st = getComputedStyle(b);
  const r = b.getBoundingClientRect();
  const t = b.querySelector('.assistant__bulle-texte');
  const stt = t ? getComputedStyle(t) : null;
  const rt = t ? t.getBoundingClientRect() : null;
  const rgb = s => (s.match(/[\\d.]+/g) || []).slice(0, 3).map(Number);
  const alpha = s => { const m = s.match(/[\\d.]+/g); return m && m.length > 3 ? +m[3] : 1; };
  return {
    fond: rgb(st.backgroundColor),
    fondAlpha: alpha(st.backgroundColor),
    couleur: rgb(st.color),
    couleurAlpha: alpha(st.color),
    corps: parseFloat(st.fontSize),
    graisse: +st.fontWeight,
    boite: [r.left, r.top, r.width, r.height],
    fenetre: [innerWidth, innerHeight],
    debordePage: document.documentElement.scrollWidth > innerWidth + 1,
    nom: (b.getAttribute('aria-label') || b.textContent || '').trim(),
    titre: (b.getAttribute('title') || '').trim(),
    // Un libellé « montré » est celui dont la boîte fait plus d'un pixel :
    // les formes qui ne l'affichent pas le réduisent à 1 px et le rognent,
    // ce qui le garde lisible par un lecteur d'écran.
    libelleMontre: !!rt && rt.width > 2 && rt.height > 2,
    libelleTronque: !!t && t.scrollWidth > t.clientWidth + 1,
    libelle: t ? t.textContent.trim() : '',
    animation: st.animationName,
    // Le budget de mouvement se lit sur les styles calculés et non dans la
    // feuille : c'est ce que le navigateur va réellement jouer.
    duree: parseFloat(st.animationDuration) || 0,
    cycles: st.animationIterationCount === 'infinite'
      ? Infinity : (parseFloat(st.animationIterationCount) || 0),
  };
}"""


# Relève la boîte du bouton pendant que l'animation se joue. En
# `requestAnimationFrame` plutôt qu'à intervalle fixe : on veut des images, pas
# des instants, et c'est le seul moyen d'attraper l'extrémité d'un mouvement de
# quatre dixièmes de seconde.
SUIVRE = """(duree) => new Promise(fini => {
  const b = document.querySelector('[data-assistant] .assistant__bulle');
  if (!b) { fini(null); return; }
  const boites = [];
  const t0 = performance.now();
  const tic = () => {
    const r = b.getBoundingClientRect();
    boites.push([r.left, r.top, r.width, r.height]);
    if (performance.now() - t0 < duree) requestAnimationFrame(tic);
    else fini({
      boites: boites,
      fenetre: [innerWidth, innerHeight],
      deborde: document.documentElement.scrollWidth > innerWidth + 1,
    });
  };
  tic();
})"""


def reglages() -> dict:
    if os.path.isfile(PARAMETRES):
        with open(PARAMETRES, encoding='utf-8') as f:
            try:
                return json.load(f)
            except json.JSONDecodeError:
                return {}
    return {}


def ecrire(donnees: dict) -> None:
    os.makedirs(os.path.dirname(PARAMETRES), exist_ok=True)
    with open(PARAMETRES, 'w', encoding='utf-8') as f:
        json.dump(donnees, f, ensure_ascii=False, indent=2)


def regler(origine: dict, forme: str, taille: int, fond: str, texte: str,
           libelle: str, animation: str = 'halo',
           vitesse: int = 1600, rappels: int = 3) -> None:
    """Allume l'assistant et pose un réglage de bulle.

    La clé est factice et le restera : aucune requête ne part vers Google,
    puisque seul l'affichage du bouton est mesuré. `Assistant::actif()` ne
    demande qu'une clé non vide.
    """
    donnees = copy.deepcopy(origine)
    assistant = dict(donnees.get('assistant', {}))
    assistant['actif'] = True
    assistant['cle'] = assistant.get('cle') or 'audit-bulle-sans-appel'
    assistant['bulle'] = {'forme': forme, 'taille': taille, 'fond': fond,
                          'texte': texte, 'libelle': libelle,
                          'animation': animation,
                          'vitesse': vitesse, 'rappels': rappels}
    donnees['assistant'] = assistant
    ecrire(donnees)


def charger_contraste():
    """Réutilise l'arithmétique de contraste de l'auditeur de contraste.

    Deux auditeurs qui mesureraient le contraste de deux façons différentes
    seraient pires qu'un seul : le jour où l'un trouve ce que l'autre rate, on
    ne sait plus lequel croire.
    """
    chemin = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'contraste.py')
    espace: dict = {'__name__': 'contraste_importe'}
    with open(chemin, encoding='utf-8') as f:
        exec(compile(f.read(), chemin, 'exec'), espace)
    return espace


def controler(v: dict, forme: str, taille: int, largeur: int, rapport) -> list:
    """Les cinq contrôles, sur un relevé. Rend la liste des écarts."""
    ecarts = []

    if v['fondAlpha'] < 1 or v['couleurAlpha'] < 1:
        # Une couche translucide rendrait la composition ci-dessous fausse :
        # mieux vaut le dire que mesurer à côté.
        ecarts.append('fond ou texte translucide — la mesure ne vaudrait rien')

    r = rapport(v['couleur'], v['fond'])
    # Le seuil est celui de l'auditeur de contraste : 3:1 pour un grand texte,
    # 4,5:1 sinon. Le libellé de la bulle est gras, donc « grand » dès
    # 18,66 px — mais on ne relâche pas pour autant, la résolution vise 4,5.
    if r < CONTRASTE_MINI:
        ecarts.append('libellé à %.2f:1 sur son fond (seuil %.1f)' % (r, CONTRASTE_MINI))

    l, h = v['boite'][2], v['boite'][3]
    if l < CIBLE_MINI or h < CIBLE_MINI:
        ecarts.append('cible tactile %.0f × %.0f px (minimum %d)' % (l, h, CIBLE_MINI))

    x, fenetre = v['boite'][0], v['fenetre'][0]
    if x < -0.5 or x + l > fenetre + 0.5:
        ecarts.append('bouton hors de la fenêtre : %.0f → %.0f px pour %d'
                      % (x, x + l, fenetre))
    if v['debordePage']:
        ecarts.append('la page déborde horizontalement')

    if v['nom'] == '' and v['titre'] == '':
        ecarts.append('bouton sans nom accessible')

    if v['libelleMontre'] and v['libelleTronque']:
        ecarts.append('libellé tronqué : « %s »' % v['libelle'])

    # Les formes qui montrent le libellé doivent le montrer, celles qui ne le
    # montrent pas doivent le garder dans le document. Une inversion silencieuse
    # est exactement le genre de régression qu'un remaniement de CSS produit.
    #
    # L'onglet est l'exception, et elle est voulue : sous 640 px les deux formes
    # horizontales se replient sur leur picto, parce qu'un libellé couché en
    # travers d'un iPhone SE recouvrait le bouton d'appel du bandeau. L'onglet,
    # lui, écrit à la verticale : il est étroit par construction et n'a rien à
    # replier. Attendre son repli était une erreur de ce script, corrigée ici
    # plutôt qu'en rognant la forme.
    attendu = forme == 'onglet' or (forme in ('barre', 'pilule') and largeur > 640)
    if attendu and not v['libelleMontre']:
        ecarts.append('libellé attendu visible et absent')
    if not attendu and v['libelleMontre']:
        ecarts.append('libellé visible alors que la forme ne le montre pas')

    return ecarts


def controler_animation(v: dict, suivi: dict, forme: str, animation: str) -> list:
    """Ce qu'une animation doit tenir : un budget, et un cadre."""
    ecarts = []
    attendue = animation != 'aucune'
    jouee = v['animation'] not in ('none', '')

    if attendue and not jouee:
        ecarts.append('animation « %s » demandée, aucune servie' % animation)
    if not attendue and jouee:
        ecarts.append('animation « %s » servie alors qu’aucune n’est demandée' % v['animation'])

    if jouee:
        budget = v['duree'] * v['cycles']
        if budget > MOUVEMENT_MAX:
            # Ce n'est pas un détail de confort : au-delà de cinq secondes, il
            # faut offrir au visiteur de quoi arrêter le mouvement.
            ecarts.append('budget de mouvement %.1f s (maximum %.1f) — '
                          'au-delà, il faut un moyen de mettre en pause'
                          % (budget, MOUVEMENT_MAX))

    if suivi is None or not suivi['boites']:
        return ecarts + ['boîte non relevée pendant l’animation']

    largeur_f = suivi['fenetre'][0]
    gauche = min(b[0] for b in suivi['boites'])
    droite = max(b[0] + b[2] for b in suivi['boites'])
    haut = min(b[1] for b in suivi['boites'])
    bas = max(b[1] + b[3] for b in suivi['boites'])
    petite_l = min(b[2] for b in suivi['boites'])
    petite_h = min(b[3] for b in suivi['boites'])

    # Une tolérance d'un pixel : les rectangles sont fractionnaires et une
    # transformation en cours peut rendre 0,4 px de dépassement d'arrondi.
    if gauche < -1 or droite > largeur_f + 1:
        ecarts.append('sort de l’écran pendant l’animation : %.1f → %.1f px pour %d'
                      % (gauche, droite, largeur_f))
    if haut < -1 or bas > suivi['fenetre'][1] + 1:
        ecarts.append('sort de l’écran en hauteur pendant l’animation : %.1f → %.1f px'
                      % (haut, bas))
    if petite_l < CIBLE_MINI - 1 or petite_h < CIBLE_MINI - 1:
        ecarts.append('cible réduite à %.0f × %.0f px pendant l’animation'
                      % (petite_l, petite_h))
    if suivi['deborde']:
        ecarts.append('la page déborde horizontalement pendant l’animation')

    return ecarts


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--base', default='http://127.0.0.1:8081')
    args = ap.parse_args()
    base = args.base.rstrip('/')

    rapport = charger_contraste()['rapport']
    origine = reglages()
    total = 0

    try:
        with sync_playwright() as p:
            navigateur = p.chromium.launch(executable_path=NAVIGATEUR, args=['--no-sandbox'])
            consentement = json.dumps({'v': 1, 'd': '2026-01-01',
                                       'mesure': False, 'externes': False})
            contextes = {}
            for largeur in LARGEURS:
                ctx = navigateur.new_context(viewport={'width': largeur, 'height': 860})
                ctx.add_cookies([{'name': 'cv_consentement', 'value': consentement, 'url': base}])
                contextes[largeur] = (ctx, ctx.new_page())

            # Aucun appel vers l'extérieur ne doit partir du navigateur, bulle
            # allumée : la conversation passe par /api/assistant, sur ce
            # domaine. Un jour où quelqu'un ajouterait un script tiers dans le
            # fragment, c'est ici qu'on le verrait.
            interne = urlparse(base).hostname
            hotes: dict = {}
            for _, pg in contextes.values():
                pg.on('request', lambda r: hotes.setdefault(urlparse(r.url).hostname, 0))

            # PREMIÈRE PASSE — formes, tailles, couleurs.
            #
            # Trois passes plutôt qu'un seul produit cartésien : les animations
            # sont indépendantes des couleurs, et multiplier les deux ferait
            # neuf cents chargements pour n'apprendre que ce que cinquante
            # disent déjà. On croise ce qui interagit, on juxtapose le reste.
            for fond, texte, etiquette in COULEURS:
                avant = total
                for forme in FORMES:
                    for taille in TAILLES:
                        regler(origine, forme, taille, fond, texte,
                               'Une question ?')
                        for largeur, (_, pg) in contextes.items():
                            pg.goto(base + PAGE, wait_until='domcontentloaded')
                            pg.wait_for_timeout(120)
                            v = pg.evaluate(RELEVE)
                            if v is None:
                                total += 1
                                print('  ECART  %-9s %2d px %5d px  bulle absente de la page'
                                      % (forme, taille, largeur))
                                continue
                            for e in controler(v, forme, taille, largeur, rapport):
                                total += 1
                                print('  ECART  %-9s %2d px %5d px  %s  [%s]'
                                      % (forme, taille, largeur, e, etiquette))
                print('  %-6s %s' % ('ok' if total == avant else 'ECART', etiquette))

            # DEUXIÈME PASSE — les animations, sur chaque forme.
            #
            # La fenêtre de relevé doit couvrir le délai d'entrée ET un cycle
            # entier — les cycles étant identiques, un seul suffit, mais il le
            # faut en entier : à 3 000 ms, une fenêtre fixe de 3,6 s n'en
            # voyait que les trois quarts, et un extrême placé à la fin du
            # geste serait passé au travers.
            def fenetre(vitesse_ms: int) -> int:
                return 1500 + vitesse_ms + 200

            SUIVI_MS = fenetre(1600)
            for animation in ANIMATIONS:
                avant = total
                for forme in FORMES:
                    regler(origine, forme, TAILLE_LIVREE, '', '#ffffff',
                           'Une question ?', animation)
                    for largeur, (_, pg) in contextes.items():
                        pg.goto(base + PAGE, wait_until='domcontentloaded')
                        v = pg.evaluate(RELEVE)
                        suivi = pg.evaluate(SUIVRE, SUIVI_MS)
                        if v is None:
                            total += 1
                            print('  ECART  %-12s %-9s %5d px  bulle absente'
                                  % (animation, forme, largeur))
                            continue
                        for e in controler_animation(v, suivi, forme, animation):
                            total += 1
                            print('  ECART  %-12s %-9s %5d px  %s'
                                  % (animation, forme, largeur, e))
                print('  %-6s animation « %s »' % ('ok' if total == avant else 'ECART', animation))

            # QUATRIÈME PASSE — le rythme, aux quatre coins de ses deux réglages.
            #
            # Le budget est vérifié par le contrôle commun ; ce qui s'ajoute
            # ici, c'est que la durée SERVIE soit bien celle demandée. Sans
            # cette vérification, un jeton CSS mal branché ferait retomber
            # l'animation sur sa valeur par défaut — 1,6 s —, le budget serait
            # tenu, et personne ne verrait que le réglage ne sert à rien.
            for vitesse, demandes, attendus in RYTHMES:
                avant = total
                regler(origine, 'barre', TAILLE_LIVREE, '', '#ffffff',
                       'Une question ?', 'rebond', vitesse, demandes)
                for largeur, (_, pg) in contextes.items():
                    pg.goto(base + PAGE, wait_until='domcontentloaded')
                    v = pg.evaluate(RELEVE)
                    suivi = pg.evaluate(SUIVRE, fenetre(vitesse))
                    if v is None:
                        total += 1
                        print('  ECART  %4d ms × %d  %5d px  bulle absente' % (vitesse, demandes, largeur))
                        continue
                    for e in controler_animation(v, suivi, 'barre', 'rebond'):
                        total += 1
                        print('  ECART  %4d ms × %d  %5d px  %s' % (vitesse, demandes, largeur, e))
                    servie = round(v['duree'] * 1000)
                    if servie != vitesse:
                        total += 1
                        print('  ECART  %4d ms × %d  %5d px  vitesse servie %d ms — le réglage '
                              'ne passe pas jusqu’à la page'
                              % (vitesse, demandes, largeur, servie))
                    if v['cycles'] != attendus:
                        total += 1
                        print('  ECART  %4d ms × %d  %5d px  %g rappel(s) servi(s), %d attendu(s) '
                              'après application du budget'
                              % (vitesse, demandes, largeur, v['cycles'], attendus))
                print('  %-6s rythme %4d ms × %d demandé(s) → %d joué(s), %.1f s'
                      % ('ok' if total == avant else 'ECART', vitesse, demandes,
                         attendus, vitesse * attendus / 1000))

            # CINQUIÈME PASSE — le réglage « moins d'animations » du système.
            #
            # C'est une préférence d'accessibilité, pas une option de confort :
            # elle est cochée par des gens que le mouvement met mal à l'aise,
            # et parfois malades. La feuille de style la respecte par une règle
            # générale posée en tête ; encore faut-il le vérifier, parce qu'une
            # règle générale est exactement ce qu'une règle plus spécifique
            # écrite six mois plus tard peut défaire sans qu'on s'en aperçoive.
            sobre = navigateur.new_context(viewport={'width': 1440, 'height': 860},
                                           reduced_motion='reduce')
            sobre.add_cookies([{'name': 'cv_consentement', 'value': consentement, 'url': base}])
            pgs = sobre.new_page()
            avant = total
            for animation in ANIMATIONS:
                regler(origine, 'barre', TAILLE_LIVREE, '', '#ffffff',
                       'Une question ?', animation)
                pgs.goto(base + PAGE, wait_until='domcontentloaded')
                pgs.wait_for_timeout(200)
                v = pgs.evaluate(RELEVE)
                # Le socle neutralise le mouvement en ramenant sa durée à un
                # millième de seconde plutôt qu'en le supprimant : c'est ce qui
                # évite qu'une animation d'apparition laisse un bloc invisible.
                # Un mouvement d'un millième de seconde ne se voit pas.
                if v and v['animation'] not in ('none', '') and v['duree'] > 0.05:
                    total += 1
                    print('  ECART  « moins d’animations » ignoré : %s pendant %.3f s'
                          % (v['animation'], v['duree']))
            print('  %-6s réglage système « moins d’animations »'
                  % ('ok' if total == avant else 'ECART'))
            sobre.close()

            tiers = sorted(h for h in hotes if h and h != interne)
            if tiers:
                total += len(tiers)
                print('  ECART  hôte(s) tiers contacté(s), bulle allumée : %s'
                      % ', '.join(tiers))
            elif interne not in hotes:
                # Aucun hôte relevé du tout : ce n'est pas une bonne nouvelle,
                # c'est un écouteur qui n'a pas écouté. Le dire plutôt que de
                # conclure « aucun tiers » sur une mesure vide.
                total += 1
                print('  ECART  aucune requête relevée — l’écoute réseau n’a pas fonctionné')
            else:
                print('  ok     réseau : %d hôte(s) contacté(s), tous internes' % len(hotes))

            for ctx, _ in contextes.values():
                ctx.close()
            navigateur.close()
    finally:
        # Les réglages d'origine reviennent quoi qu'il arrive : un auditeur qui
        # laisse l'assistant allumé avec une clé factice parce qu'il a échoué
        # au milieu est un auditeur qu'on cesse de lancer.
        ecrire(origine)

    mesures = (len(COULEURS) * len(FORMES) * len(TAILLES) * len(LARGEURS)
               + len(ANIMATIONS) * len(FORMES) * len(LARGEURS)
               + len(RYTHMES) * len(LARGEURS)
               + len(ANIMATIONS))
    print('---')
    print('%d formes × %d tailles × %d couples de couleurs, puis %d animations '
          '× %d formes, puis %d rythmes, puis le réglage système '
          '— %d réglages, %d écart(s).'
          % (len(FORMES), len(TAILLES), len(COULEURS), len(ANIMATIONS),
             len(FORMES), len(RYTHMES), mesures, total))
    if total:
        print('Corrigez App\\Core\\Bulle ou la feuille de style, jamais le seuil : '
              'c’est la résolution de contraste qui doit tenir, pas le choix de '
              'la mairie.')
    return 1 if total else 0


if __name__ == '__main__':
    sys.exit(main())
