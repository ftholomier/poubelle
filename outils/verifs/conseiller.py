"""La pastille du conseiller, sous ses trois états, et les vôtres.

Ce script existe pour la raison que CLAUDE.md nomme déjà : **un réglage qui
décide de la PRÉSENCE d'un élément cache cet élément aux auditeurs.** Le
conseiller est éteint tant qu'aucune clé Gemini n'est renseignée, donc ni
`mise-en-page.py --admin` ni `contraste.py` ne l'ont jamais vu. C'est ce même
trou qui avait laissé passer, pendant toute la vie du socle, le libellé de la
bulle publique à 2,57:1.

Ici le trou est double, et c'est ce qui justifie un script à part plutôt qu'une
ligne dans un autre. Allumer le conseiller ne suffit pas : le panneau ouvert
est **vide**. Les bulles de conversation, le bloc de texte proposé, le message
d'erreur et les fiches du bilan n'existent qu'APRÈS un appel au modèle. Un
auditeur qui se contenterait d'ouvrir le panneau mesurerait un cadre et deux
onglets, et déclarerait le tout conforme.

Les réponses de Google sont donc jouées par une doublure : `page.route()`
intercepte /admin/conseiller et rend une réponse fabriquée qui contient tout
ce que le rendu sait produire — des paragraphes, une liste à puces, un bloc
`proposition`, et un bilan portant les trois niveaux d'urgence. Aucune requête
ne sort, rien n'est facturé, et c'est le VRAI code de rendu qui est mesuré.

Retenez-en la règle : quand ce qu'il faut mesurer n'apparaît qu'après une
réponse d'un service extérieur, on double le service, on ne saute pas la
mesure.

Ce qui est contrôlé, à cinq largeurs :

  · le contraste de chaque texte, par l'arithmétique de contraste.py ;
  · les cibles tactiles à 44 px — pastille, onglets, boutons, fermeture ;
  · le débordement latéral de la page, pastille posée ;
  · le panneau qui doit rester dans le cadre, y compris par le haut ;
  · les violations de la politique de sécurité et les erreurs de script ;
  · le recouvrement du contenu : la pastille est en position fixe, donc elle
    peut cacher le bouton « Enregistrer » d'un formulaire long. C'est le
    défaut le plus probable de cet ajout, et le moins visible.

Usage :
    php -S 127.0.0.1:8081 -t public &
    python3 outils/verifs/conseiller.py --admin identifiant:motdepasse

Sort en code 1 s'il trouve quelque chose.
"""
import argparse
import copy
import json
import os
import re
import sys
import urllib.parse
import urllib.request

from playwright.sync_api import sync_playwright

RACINE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
PARAMETRES = os.path.join(RACINE, 'data', 'admin', 'parametres.json')
NAVIGATEUR = os.environ.get('CHROMIUM', '/opt/pw-browsers/chromium')

# Clé factice : la doublure répond à sa place, aucune requête ne part vers
# Google. `Conseiller::actif()` ne demande qu'une clé non vide.
CLE_FACTICE = 'cle-factice-audit-conseiller'

LARGEURS = (320, 390, 768, 1024, 1440)
CIBLE_MINI = 44

# Deux écrans : le tableau de bord, qui est court, et un formulaire long — la
# pastille étant fixe, c'est sur un écran qui défile qu'elle recouvre quelque
# chose.
ECRANS = ('/admin', '/admin/site')

# Ce que la doublure renvoie. Le texte est écrit pour exercer tout le rendu :
# un paragraphe, une liste à puces, et un bloc de proposition.
REPONSE_DOUBLURE = (
    "Trois choses avant tout le reste.\n\n"
    "· La page Urbanisme n’a reçu que quatre visites en trois mois\n"
    "· Deux fiches de démarche n’ont aucune description de référencement\n"
    "· L’agenda ne porte plus de rendez-vous depuis mars\n\n"
    "Voici une description pour la page Démarches :\n\n"
    "```proposition\n"
    "Toutes les démarches administratives de la commune : état civil, "
    "urbanisme, élections, recensement. Pièces à fournir et délais.\n"
    "```\n\n"
    "Commencez par celle-là : c’est la page la plus consultée après l’accueil."
)

BILAN_DOUBLURE = {
    'date': 1788600000,
    'recommandations': [
        {'titre': 'La page Urbanisme est introuvable depuis l’accueil',
         'urgence': 'forte', 'domaine': 'contenu',
         'constat': 'Quatre visites en trois mois, alors que c’est la deuxième démarche du village.',
         'geste': 'Ajouter un bouton « Déclarer des travaux » dans la bande d’accueil.',
         'ecran': '/admin/accueil'},
        {'titre': 'Deux fiches n’ont aucune description',
         'urgence': 'moyenne', 'domaine': 'referencement',
         'constat': 'Google compose alors lui-même un extrait, souvent mal choisi.',
         'geste': 'Référencement → fiches concernées → remplir la description.',
         'ecran': '/admin/referencement'},
        {'titre': 'L’agenda ne porte aucun rendez-vous à venir',
         'urgence': 'faible', 'domaine': 'strategie',
         'constat': 'Un agenda vide donne l’impression d’une commune endormie.',
         'geste': 'Demander leurs dates aux associations une fois par trimestre.',
         'ecran': '/admin/listes/agenda'},
    ],
}


def charger_contraste() -> dict:
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


def allumer(origine: dict) -> None:
    """Allume le conseiller, sans toucher à l'assistant public.

    Les deux interrupteurs sont distincts, et le rester est justement ce que
    cet auditeur vérifie au passage : `assistant.actif` n'est pas modifié.
    """
    donnees = copy.deepcopy(origine)
    assistant = dict(donnees.get('assistant', {}))
    assistant['cle'] = assistant.get('cle') or CLE_FACTICE
    assistant['conseiller'] = True
    donnees['assistant'] = assistant
    ecrire(donnees)


def connexion(base: str, identifiant: str, mot_de_passe: str) -> list:
    """Ouvre une session d'administration et rend ses biscuits."""
    bocal = urllib.request.HTTPCookieProcessor()
    session = urllib.request.build_opener(bocal)
    page = session.open(base + '/admin', timeout=20).read().decode('utf-8')

    action = re.search(r'<form[^>]*action="([^"]*)"', page)
    jeton = re.search(r'name="_csrf"\s+value="([^"]+)"', page)
    if action is None or jeton is None:
        print('Le formulaire de connexion est introuvable : rien à mesurer.')
        sys.exit(1)

    champs = {'identifiant': identifiant, 'mot_de_passe': mot_de_passe, '_csrf': jeton.group(1)}
    session.open(urllib.request.Request(
        base + action.group(1),
        data=urllib.parse.urlencode(champs).encode('utf-8')), timeout=30)

    return [{'name': c.name, 'value': c.value, 'url': base} for c in bocal.cookiejar]


def doubler(pg) -> None:
    """Meta n'a rien à faire ici : Google non plus.

    Les deux adresses du conseiller sont interceptées et répondent une réponse
    fabriquée. Le rendu mesuré est le vrai — c'est conseiller.js qui construit
    les nœuds — mais rien ne sort de la machine et rien n'est facturé.
    """
    pg.route('**/admin/conseiller', lambda route: route.fulfill(
        status=200, content_type='application/json',
        body=json.dumps({'reponse': REPONSE_DOUBLURE})))
    pg.route('**/admin/conseiller/bilan', lambda route: route.fulfill(
        status=200, content_type='application/json',
        body=json.dumps(BILAN_DOUBLURE)))


CIBLES = """() => {
  const boite = document.querySelector('[data-conseil]');
  if (!boite) return [];
  const sel = 'button, a, [role=tab], input, textarea';
  return [...boite.querySelectorAll(sel)].filter(el => {
    const st = getComputedStyle(el);
    return st.display !== 'none' && st.visibility !== 'hidden' && el.offsetParent !== null;
  }).map(el => {
    const r = el.getBoundingClientRect();
    return {nom: (el.textContent || el.getAttribute('aria-label') || el.tagName).trim().slice(0, 30),
            l: Math.round(r.width), h: Math.round(r.height)};
  });
}"""

# Ce que la pastille recouvre. On regarde le point situé au centre de la
# pastille : si le document y place autre chose qu'elle-même, c'est caché.
RECOUVREMENT = """() => {
  const past = document.querySelector('[data-conseil-ouvrir]');
  if (!past) return null;
  const r = past.getBoundingClientRect();
  past.style.pointerEvents = 'none';
  const dessous = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
  past.style.pointerEvents = '';
  if (!dessous) return null;
  // Un fond de page ou un conteneur ne cache rien de lisible.
  const utile = dessous.closest('button, a, input, textarea, select, label, h1, h2, h3, p, li, td');
  if (!utile) return null;
  const t = (utile.textContent || '').replace(/\\s+/g, ' ').trim();
  return t ? utile.tagName.toLowerCase() + ' « ' + t.slice(0, 50) + ' »' : null;
}"""


def mesurer(pg, base, chemin, largeur, contraste) -> list:
    """Un écran, une largeur, les trois états. Rend la liste des écarts."""
    ecarts = []
    pg.goto(base + chemin, wait_until='domcontentloaded')
    pg.wait_for_timeout(400)

    if pg.locator('[data-conseil]').count() == 0:
        return ['la pastille est absente alors que le conseiller est allumé']

    # --- état 1 : pastille fermée ------------------------------------------
    debord = pg.evaluate('document.documentElement.scrollWidth - document.documentElement.clientWidth')
    if debord > 0:
        ecarts.append('la page déborde de %d px, pastille posée' % debord)

    cache = pg.evaluate(RECOUVREMENT)
    if cache:
        ecarts.append('la pastille recouvre %s' % cache)

    # --- état 2 : panneau ouvert, conversation jouée ------------------------
    pg.click('[data-conseil-ouvrir]')
    pg.wait_for_timeout(250)
    pg.fill('[data-conseil-question]', 'Par quoi devrais-je commencer ?')
    pg.click('[data-conseil-envoyer]')
    pg.wait_for_selector('.bo-conseil__proposition', timeout=8000)
    pg.wait_for_timeout(250)

    ecarts += hors_cadre(pg)
    ecarts += cibles_trop_petites(pg)
    ecarts += contrastes(pg, contraste)

    # --- état 3 : le bilan --------------------------------------------------
    pg.click('[data-conseil-onglet="bilan"]')
    pg.wait_for_timeout(200)
    pg.click('[data-conseil-lancer]')
    pg.wait_for_selector('.bo-conseil__reco', timeout=8000)
    pg.wait_for_timeout(250)

    ecarts += hors_cadre(pg)
    ecarts += cibles_trop_petites(pg)
    ecarts += contrastes(pg, contraste)

    pg.click('[data-conseil-fermer]')
    pg.wait_for_timeout(150)

    return ecarts


def hors_cadre(pg) -> list:
    """Le panneau doit tenir dans la fenêtre, par les quatre côtés.

    Le bas et la droite sont ceux auxquels on pense ; c'est le HAUT qui casse,
    quand la fenêtre est basse et le panneau haut de trente-quatre rem.
    """
    r = pg.evaluate("""() => {
      const p = document.querySelector('#bo-conseil-panneau');
      if (!p || p.hidden) return null;
      const b = p.getBoundingClientRect();
      return {haut: Math.round(b.top), bas: Math.round(b.bottom - innerHeight),
              gauche: Math.round(b.left), droite: Math.round(b.right - innerWidth)};
    }""")
    if r is None:
        return ['le panneau ne s’ouvre pas']

    ecarts = []
    if r['haut'] < 0:
        ecarts.append('le panneau sort de %d px par le haut' % -r['haut'])
    if r['bas'] > 0:
        ecarts.append('le panneau sort de %d px par le bas' % r['bas'])
    if r['gauche'] < 0:
        ecarts.append('le panneau sort de %d px à gauche' % -r['gauche'])
    if r['droite'] > 0:
        ecarts.append('le panneau sort de %d px à droite' % r['droite'])

    debord = pg.evaluate('document.documentElement.scrollWidth - document.documentElement.clientWidth')
    if debord > 0:
        ecarts.append('la page déborde de %d px, panneau ouvert' % debord)

    return ecarts


def cibles_trop_petites(pg) -> list:
    """Les cibles tactiles, sous « hover: none » seulement.

    C'est la règle de mise-en-page.py, et il n'y a aucune raison d'en inventer
    une autre ici : le critère 2.5.8 demande 24 px à la souris, et ce dépôt
    vise 44 px là où le doigt remplace le pointeur. Un auditeur plus sévère que
    ses voisins finit par être celui qu'on désactive.
    """
    if not pg.evaluate("matchMedia('(hover: none)').matches"):
        return []

    ecarts = []
    for c in pg.evaluate(CIBLES):
        # Une zone de saisie n'est pas une cible tactile au sens de la règle :
        # on y écrit, on ne la vise pas. Sa hauteur suit son contenu.
        if c['h'] < CIBLE_MINI and c['l'] < CIBLE_MINI:
            ecarts.append('cible de %dx%d px : « %s »' % (c['l'], c['h'], c['nom']))
        elif c['h'] < CIBLE_MINI and c['nom'] not in ('', 'TEXTAREA'):
            ecarts.append('cible de %d px de haut : « %s »' % (c['h'], c['nom']))
    return ecarts


def contrastes(pg, contraste) -> list:
    """Le contraste de chaque texte de la pastille et du panneau.

    Le relevé est celui de contraste.py, sélecteur compris : il rend la
    couleur du texte, son opacité et le fond composé sous lui. Un fond `null`
    signifie que la composition n'a pas pu conclure — une image, un filtre —,
    et c'est un écart en soi ici : la pastille est posée sur des aplats, et si
    la mesure ne conclut pas c'est que quelque chose a changé.
    """
    ecarts = []
    for v in pg.evaluate(contraste['RELEVE'], '[data-conseil], [data-conseil] *'):
        if v['fond'] is None:
            ecarts.append('fond non composable sous « %s » — %s' % (v['texte'], v['sel']))
            continue
        couleur = contraste['composer'](v['couleur'], v['opacite'], v['fond'])
        rapport = contraste['rapport'](couleur, v['fond'])
        if rapport < v['seuil']:
            ecarts.append('%.2f:1 (seuil %.1f) sur « %s » — %s'
                          % (rapport, v['seuil'], v['texte'], v['sel']))
    return ecarts


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--base', default='http://127.0.0.1:8081')
    ap.add_argument('--admin', required=True, metavar='identifiant:motdepasse')
    args = ap.parse_args()
    base = args.base.rstrip('/')

    identifiant, _, mot_de_passe = args.admin.partition(':')
    contraste = charger_contraste()
    origine = reglages()
    allumer(origine)

    total = 0
    try:
        biscuits = connexion(base, identifiant, mot_de_passe)
        with sync_playwright() as p:
            b = p.chromium.launch(executable_path=NAVIGATEUR, args=['--no-sandbox'])
            for largeur in LARGEURS:
                tactile = largeur <= 780
                ctx = b.new_context(viewport={'width': largeur, 'height': 900},
                                    has_touch=tactile, is_mobile=tactile)
                ctx.add_cookies(biscuits)
                ctx.add_init_script("""
                    window.__csp = [];
                    document.addEventListener('securitypolicyviolation', function (e) {
                        window.__csp.push(e.effectiveDirective + ' ← ' + (e.blockedURI || 'en ligne'));
                    });
                """)
                pg = ctx.new_page()
                erreurs: list = []
                pg.on('pageerror', lambda e: erreurs.append(str(e)))
                doubler(pg)

                for chemin in ECRANS:
                    ecarts = mesurer(pg, base, chemin, largeur, contraste)
                    ecarts += ['politique de sécurité : %s' % v
                               for v in sorted(set(pg.evaluate('window.__csp || []')))]
                    ecarts += ['erreur de script : %s' % e for e in erreurs]
                    erreurs.clear()

                    total += len(ecarts)
                    print('%5d px  %-14s %s' % (largeur, chemin,
                                                'ok' if not ecarts else '%d écart(s)' % len(ecarts)))
                    for e in ecarts:
                        print('              · %s' % e)

                ctx.close()
            b.close()
    finally:
        # Les réglages d'origine reviennent quoi qu'il arrive : un auditeur qui
        # laisse une clé factice dans les paramètres parce qu'il a échoué au
        # milieu est un auditeur qu'on cesse de lancer.
        ecrire(origine)

    print('---')
    print('%d largeur(s) × %d écran(s) × 3 états — %d écart(s).'
          % (len(LARGEURS), len(ECRANS), total))

    return 1 if total else 0


if __name__ == '__main__':
    sys.exit(main())
