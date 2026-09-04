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
    jaune pâle, noir sur noir, blanc sur blanc, texte et fond identiques).

Il vérifie sur chacun : le contraste réel du libellé peint sur son fond
peint, la cible tactile, l'absence de débordement horizontal, le nom
accessible du bouton, et qu'un libellé montré n'est pas tronqué.

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

# Les bornes, et la valeur livrée entre les deux. Rien au-delà : le contrôleur
# et la classe bornent toutes deux, et un réglage hors bornes ne peut pas
# atteindre la page — c'est ce que vérifie `test_bornes`.
TAILLES = (44, 52, 76)

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
  };
}"""


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
           libelle: str) -> None:
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
                          'texte': texte, 'libelle': libelle}
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

            for fond, texte, etiquette in COULEURS:
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
                print('  %-6s %s' % ('ok' if not total else '·', etiquette))

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

    mesures = len(COULEURS) * len(FORMES) * len(TAILLES) * len(LARGEURS)
    print('---')
    print('%d formes × %d tailles × %d couples de couleurs × %d largeurs '
          '— %d réglages, %d écart(s).'
          % (len(FORMES), len(TAILLES), len(COULEURS), len(LARGEURS), mesures, total))
    if total:
        print('Corrigez App\\Core\\Bulle ou la feuille de style, jamais le seuil : '
              'c’est la résolution de contraste qui doit tenir, pas le choix de '
              'la mairie.')
    return 1 if total else 0


if __name__ == '__main__':
    sys.exit(main())
