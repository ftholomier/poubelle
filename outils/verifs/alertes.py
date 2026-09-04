"""Ce que PHP dit tout bas, et que personne n'écoute.

Pourquoi un neuvième auditeur : les huit autres regardent la page rendue.
Aucun ne regarde le **journal d'erreurs du serveur**. Or une alerte PHP ne
sort pas dans la page — `display_errors` est éteint en production, et il doit
l'être : afficher une trace d'erreur à un administré serait à la fois laid et
imprudent. La vérification que le socle proposait,

    curl -s localhost:8080/ | grep -ci "warning\\|fatal\\|notice"

ne mesure donc que ce qui ne devrait jamais arriver, et rate tout le reste.

Ce que ce trou a laissé passer sur ce site, jusqu'à ce que ce script existe :

  · `foreach` sur une chaîne dans la section « le village » de l'accueil —
    trois paragraphes d'histoire de la commune ne s'affichaient pas du tout,
    et la page paraissait simplement un peu courte ;
  · le même défaut, en sens inverse, sur les fiches d'association : l'écran
    d'édition déclare le champ en texte riche et écrit donc une chaîne, que le
    gabarit parcourait comme un tableau.

Deux fois le même malentendu entre ce qu'écrit le back-office et ce qu'attend
le gabarit — c'est-à-dire exactement ce qu'un auditeur doit attraper, puisque
aucun œil ne relit les cinquante et une pages après chaque modification.

Le script lance **son propre serveur**, avec son propre journal : c'est le seul
moyen d'être sûr de lire les alertes de la visite en cours, et non celles d'un
serveur lancé la veille. Il parcourt le plan du site, puis lit le journal.

Avec `--admin identifiant:motdepasse`, il parcourt aussi les écrans du
back-office. Sans, il s'en tient au site public — on ne fabrique pas un compte
d'administration à l'insu de qui lance un audit.

Usage :
    python3 outils/verifs/alertes.py
    python3 outils/verifs/alertes.py --admin marie:mon-mot-de-passe

Sort en code 1 s'il trouve quelque chose.
"""
import argparse
import http.cookiejar
import os
import re
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

RACINE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
PORT = 8099

# Ce qu'on cherche dans le journal. « Deprecated » compte : c'est ce qui casse
# à la montée de version de PHP chez l'hébergeur, sans prévenir.
ALERTES = re.compile(r'PHP (Warning|Notice|Fatal error|Parse error|Deprecated|Recoverable)', re.I)

# Les écrans du back-office, quand on donne de quoi s'y connecter. Ceux qui
# écrivent ne sont pas visités : un auditeur ne modifie pas le contenu.
ECRANS_ADMIN = (
    '/admin', '/admin/accueil', '/admin/site', '/admin/coordonnees', '/admin/pages',
    '/admin/demarches', '/admin/actualites',
    '/admin/listes/actualites', '/admin/listes/agenda', '/admin/listes/documents',
    '/admin/listes/demarches', '/admin/listes/associations', '/admin/listes/commissions',
    '/admin/listes/numeros', '/admin/listes/services-etat',
    '/admin/conseil', '/admin/contact', '/admin/demande', '/admin/conversations',
    '/admin/photos', '/admin/apparence', '/admin/avis', '/admin/assistant',
    '/admin/reseaux', '/admin/referencement', '/admin/langues', '/admin/avance',
    '/admin/parametres', '/admin/mises-a-jour',
)


# Les routes GET du back-office qui ne sont pas des écrans à mesurer, et
# pourquoi. Sans cette liste, le contrôle ci-dessous crierait à chaque passage
# sur des adresses qu'il ne faut surtout pas visiter connecté.
HORS_MESURE = {
    # Montrés seulement hors session : connecté, ils redirigent. C'est /admin
    # qui mène à l'un ou à l'autre, et /admin est déjà mesuré.
    '/admin/configuration': 'écran de première configuration',
    '/admin/connexion': 'écran de connexion',
    # Point de retour du dialogue Meta : sans code ni jeton d'état, il ne peut
    # que refuser. Ce refus est le comportement voulu, pas un écran.
    '/admin/reseaux/retour': 'retour OAuth',
}


def listes_a_jour() -> int:
    """Les deux inventaires écrits à la main disent-ils encore la vérité ?

    Deux listes de ce dépôt ne se mettent pas à jour toutes seules, et rien ne
    signale qu'elles ont pris du retard :

    · ECRANS_ADMIN ci-dessus. Un écran ajouté au back-office et oublié ici
      n'est jamais visité, donc jamais mesuré : ses alertes PHP restent
      invisibles aussi longtemps que personne n'y pense.
    · Seo::CONTENUS. C'est la liste des fichiers où l'on réécrit les liens
      internes quand un slug change au back-office. Une page absente de cette
      liste garde ses vieux liens, qui mènent en 404 — sans erreur, sans
      alerte, et sans que la mairie sache pourquoi.

    Les comparer coûte trente lignes et ferme les deux trous d'un coup.
    """
    ecarts = 0

    with open(os.path.join(RACINE, 'app', 'routes-admin.php'), encoding='utf-8') as f:
        routes = re.findall(r"\$router->get\(\s*'(/admin[^']*)'", f.read())
    concretes = [r for r in routes
                 # Les routes à paramètre ne se visitent pas telles quelles, et
                 # celles qui finissent par « / » sont des morceaux de
                 # concaténation ('/admin/' . $collection) : le nom est calculé,
                 # et les adresses qui en sortent sont listées à la main
                 # ci-dessus.
                 if '{' not in r and not r.endswith('/') and r not in HORS_MESURE]
    for route in [r for r in concretes if r not in ECRANS_ADMIN]:
        ecarts += 1
        print('  écran non mesuré : %s — à ajouter à ECRANS_ADMIN' % route)

    with open(os.path.join(RACINE, 'app', 'Core', 'Seo.php'), encoding='utf-8') as f:
        bloc = re.search(r'CONTENUS\s*=\s*\[(.*?)\];', f.read(), re.S)
    declares = set(re.findall(r"'([^']+)'", bloc.group(1))) if bloc else set()

    dossier = os.path.join(RACINE, 'data-modele', 'pages')
    for nom in sorted(os.listdir(dossier)) if os.path.isdir(dossier) else []:
        if not nom.endswith('.json'):
            continue
        cle = 'pages/' + nom[:-5]
        if cle not in declares:
            ecarts += 1
            print('  page absente de Seo::CONTENUS : %s — ses liens internes '
                  'ne suivront pas un changement de slug' % cle)

    return ecarts


def pages_du_site(base: str) -> list:
    xml = urllib.request.urlopen(base + '/sitemap.xml', timeout=20).read().decode('utf-8')
    vues = []
    for loc in re.findall(r'<loc>([^<]+)</loc>', xml):
        chemin = re.sub(r'^https?://[^/]+', '', loc) or '/'
        if chemin not in vues:
            vues.append(chemin)
    return vues


def jeton(html: str) -> str:
    m = re.search(r'name="_csrf"\s+value="([^"]+)"', html)
    return m.group(1) if m else ''


def action(html: str) -> str:
    """L'adresse du premier formulaire de la page.

    Lue et non devinée : selon qu'un compte existe ou non, `/admin` mène à la
    connexion ou à la première configuration, et les deux ne postent pas au
    même endroit. Un auditeur qui devine se met à échouer le jour où l'un des
    deux change de route, pour une raison qui n'a rien à voir avec ce qu'il
    mesure.
    """
    m = re.search(r'<form[^>]*action="([^"]+)"', html)
    return m.group(1) if m else ''


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--admin', default='',
                    help='identifiant:motdepasse pour parcourir aussi le back-office')
    args = ap.parse_args()

    journal = os.path.join(RACINE, 'storage', 'cache', 'alertes-audit.log')
    os.makedirs(os.path.dirname(journal), exist_ok=True)
    if os.path.exists(journal):
        os.remove(journal)

    base = 'http://127.0.0.1:%d' % PORT
    serveur = subprocess.Popen(
        ['php', '-d', 'error_reporting=E_ALL', '-d', 'display_errors=0',
         '-S', '127.0.0.1:%d' % PORT, '-t', 'public', 'public/index.php'],
        cwd=RACINE, stdout=open(journal, 'w'), stderr=subprocess.STDOUT)

    total = 0
    try:
        for _ in range(40):
            try:
                urllib.request.urlopen(base + '/', timeout=2).read()
                break
            except Exception:
                time.sleep(0.25)
        else:
            print('Le serveur d’essai n’a pas démarré.')
            return 1

        biscuits = http.cookiejar.CookieJar()
        client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(biscuits))

        chemins = pages_du_site(base)
        print('%d pages publiques' % len(chemins))

        if args.admin:
            identifiant, _, motdepasse = args.admin.partition(':')
            # `/admin` redirige vers la connexion, ou vers la première
            # configuration tant qu'aucun compte n'existe. On poste au
            # formulaire que la page présente, quel qu'il soit.
            page = client.open(base + '/admin', timeout=20).read().decode('utf-8', 'replace')
            corps = {'_csrf': jeton(page), 'identifiant': identifiant, 'mot_de_passe': motdepasse}
            if 'name="confirmation"' in page:
                corps['confirmation'] = motdepasse
            client.open(base + (action(page) or '/admin/connexion'),
                        urllib.parse.urlencode(corps).encode(), timeout=20).read()
            suite = client.open(base + '/admin', timeout=20).read().decode('utf-8', 'replace')
            if 'name="mot_de_passe"' in suite:
                print('Connexion au back-office refusée : les écrans ne sont pas parcourus.')
            else:
                chemins += list(ECRANS_ADMIN)
                print('%d écrans du back-office' % len(ECRANS_ADMIN))

        for chemin in chemins:
            try:
                client.open(base + chemin, timeout=25).read()
            except urllib.error.HTTPError as e:
                # Une page absente est l'affaire d'un autre auditeur ; ce qui
                # compte ici, c'est que PHP n'ait rien à dire. Une 500, en
                # revanche, est toujours un défaut.
                if e.code >= 500:
                    total += 1
                    print('  %-3d %s' % (e.code, chemin))
            except Exception as e:
                total += 1
                print('  ERR %s — %s' % (chemin, e))
    finally:
        serveur.terminate()
        try:
            serveur.wait(timeout=5)
        except subprocess.TimeoutExpired:
            serveur.kill()

    total += listes_a_jour()

    with open(journal, encoding='utf-8', errors='replace') as f:
        lignes = [l.strip() for l in f if ALERTES.search(l)]

    # Une même alerte se répète à chaque page qui l'appelle : on la compte une
    # fois, avec son nombre, sans quoi un défaut unique noierait tous les
    # autres sous cinquante lignes identiques.
    vues = {}
    for ligne in lignes:
        cle = re.sub(r'^\[[^\]]*\]\s*', '', ligne)
        vues[cle] = vues.get(cle, 0) + 1

    for cle, n in sorted(vues.items(), key=lambda kv: -kv[1]):
        total += 1
        print('  ×%-3d %s' % (n, cle))

    print('---')
    print('%d alerte(s) distincte(s), %d ligne(s) au journal.' % (len(vues), len(lignes)))
    if total:
        print('Une alerte PHP ne sort pas dans la page : elle se voit ici, ou nulle part. '
              'Corrigez le gabarit ou le contenu, jamais le niveau de rapport d’erreurs.')
    return 1 if total else 0


if __name__ == '__main__':
    sys.exit(main())
