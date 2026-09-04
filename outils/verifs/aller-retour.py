"""Enregistrer un écran d'édition sans rien changer, et compter ce qui reste.

`CLAUDE.md` le dit depuis le début : « Ouvrir un écran ne suffit pas : ce qui
casse, c'est l'enregistrement. Un champ mal nommé vide une clé sans rien
signaler. » La consigne était de le faire à la main après chaque modification
d'un écran d'édition — c'est-à-dire de l'oublier une fois sur deux.

Ce script le fait pour de bon. Il crée un compte d'administration dans un
`data/` neuf, ouvre chaque écran, **rejoue le formulaire exactement tel que la
page le rend** — sans rien changer — et compare le fichier de contenu avant et
après. Le nombre de feuilles non vides ne doit pas baisser.

Rejouer le formulaire rendu, et non des champs devinés, est le point : c'est
le seul moyen de voir qu'un `name=` du gabarit ne correspond plus à ce que le
contrôleur relit. Un écart de nom ne produit aucune erreur — la valeur arrive
simplement vide, et la clé disparaît du JSON à l'enregistrement suivant.

Il a trouvé, le jour où il a existé, une erreur 500 sur l'enregistrement de la
page d'accueil : une classe utilisée sans son `use`. La page s'affichait très
bien ; seul l'enregistrement tombait.

    python3 outils/verifs/aller-retour.py

Il lance son propre serveur sur un port à lui, avec un `data/` **à lui**
(variable `APP_DATA`) : le contenu de la machine n'est jamais touché. La
première version faisait le ménage dans `data/`, ce qui détruisait le contenu
d'un poste de développement et, lancée pendant qu'un autre auditeur tournait,
écrasait la configuration que celui-ci venait d'y écrire.

Sort en code 1 au premier écart.
"""
import html
import http.cookiejar
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
import time
import urllib.parse
import urllib.request

RACINE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
PORT = 8097
BASE = 'http://127.0.0.1:%d' % PORT

# Un compte jetable : le back-office n'a pas d'identifiant par défaut, et c'est
# une bonne chose. On en crée un dans le data/ neuf, on l'efface à la fin.
IDENTIFIANT = 'verif-aller-retour'
MOT_DE_PASSE = 'Verification-Aller-Retour-2026'

# Écran, action du formulaire à rejouer, fichier de contenu à surveiller.
# N'y mettre que des formulaires dont le renvoi ne crée rien : publier une
# actualité ou envoyer un message n'a rien à faire dans un auditeur.
ECRANS = (
    ('/admin/site',       '/admin/site',                'site.json'),
    ('/admin/accueil',    '/admin/accueil',             'pages/accueil.json'),
    ('/admin/contact',    '/admin/contact',             'site.json'),
    ('/admin/langues',    '/admin/langues/cle',         'admin/parametres.json'),
    ('/admin/parametres', '/admin/parametres/messagerie', 'admin/parametres.json'),
    # Une page de blocs, et la plus chargée en photos : c'est là que passe
    # Blocs::relireChamp(), le point où un chemin d'image se perd le plus
    # facilement — et où il se perdrait sans un mot.
    ('/admin/pages/album-photos', '/admin/pages/album-photos', 'pages/album-photos.json'),
    ('/admin/pages/le-village',   '/admin/pages/le-village',   'pages/le-village.json'),
)

# Le tableau de bord n'enregistre rien : il n'a pas sa place ci-dessus. Il est
# en revanche le premier écran ouvert chaque matin, et c'est celui qui lit le
# plus de fichiers — la fréquentation, la file des réseaux, les conversations,
# les réglages. `alertes.py` et `mise-en-page.py --admin` le mesurent.


def dossier_neuf() -> str:
    """Un data/ jetable, hors du dépôt. data-modele/ s'y recopie tout seul."""
    chemin = tempfile.mkdtemp(prefix='aller-retour-')
    os.makedirs(os.path.join(chemin, 'admin'), exist_ok=True)
    return chemin


def formulaire(page: str, action: str):
    """Les champs d'un formulaire, tels que la page les rend.

    Les cases et boutons radio non cochés sont écartés — un navigateur ne les
    envoie pas non plus —, et un `<select>` rend l'option marquée `selected`,
    à défaut la première : c'est ce que le navigateur enverrait.
    """
    for cible, corps in re.findall(r'<form[^>]*action="([^"]*)"[^>]*>(.*?)</form>', page, re.S):
        if action not in cible:
            continue
        champs = []
        for balise in re.findall(r'<input\b[^>]*>', corps):
            nom = re.search(r'name="([^"]+)"', balise)
            if nom is None:
                continue
            genre = (re.search(r'type="([^"]+)"', balise) or [None, 'text'])[1]
            if genre == 'submit' or (genre in ('checkbox', 'radio') and 'checked' not in balise):
                continue
            valeur = re.search(r'value="([^"]*)"', balise)
            champs.append((nom.group(1), html.unescape(valeur.group(1)) if valeur else ''))
        for balise, contenu in re.findall(r'(<textarea\b[^>]*>)(.*?)</textarea>', corps, re.S):
            nom = re.search(r'name="([^"]+)"', balise)
            if nom is not None:
                champs.append((nom.group(1), html.unescape(contenu)))
        for balise, contenu in re.findall(r'(<select\b[^>]*>)(.*?)</select>', corps, re.S):
            nom = re.search(r'name="([^"]+)"', balise)
            if nom is None:
                continue
            choisi = re.search(r'<option[^>]*value="([^"]*)"[^>]*selected', contenu) \
                  or re.search(r'<option[^>]*value="([^"]*)"', contenu)
            champs.append((nom.group(1), html.unescape(choisi.group(1)) if choisi else ''))
        return cible, champs
    return None, None


def feuilles(objet) -> int:
    """Les valeurs non vides du JSON. C'est ce nombre qui ne doit pas baisser."""
    if isinstance(objet, dict):
        return sum(feuilles(v) for v in objet.values())
    if isinstance(objet, list):
        return sum(feuilles(v) for v in objet)
    return 0 if objet in ('', None, []) else 1


def aplatir(objet, chemin=''):
    if isinstance(objet, dict):
        for cle, valeur in objet.items():
            yield from aplatir(valeur, chemin + '/' + str(cle))
    elif isinstance(objet, list):
        for rang, valeur in enumerate(objet):
            yield from aplatir(valeur, chemin + '/' + str(rang))
    else:
        yield chemin, objet


def main() -> int:
    donnees = dossier_neuf()
    environnement = dict(os.environ, APP_DATA=donnees)
    serveur = subprocess.Popen(
        ['php', '-S', '127.0.0.1:%d' % PORT, '-t', 'public', 'public/index.php'],
        cwd=RACINE, env=environnement,
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    time.sleep(1.5)

    bocal = http.cookiejar.CookieJar()
    session = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(bocal))

    def lire_page(chemin: str) -> str:
        return session.open(BASE + chemin, timeout=20).read().decode('utf-8')

    def poster(chemin: str, champs) -> None:
        corps = urllib.parse.urlencode(champs, doseq=True).encode('utf-8')
        session.open(urllib.request.Request(BASE + chemin, data=corps), timeout=30)

    def contenu(fichier: str):
        chemin = os.path.join(donnees, fichier)
        if not os.path.isfile(chemin):
            return None
        with open(chemin, encoding='utf-8') as f:
            return json.load(f)

    ecarts = 0
    try:
        page = lire_page('/admin')
        jeton = (re.search(r'name="_csrf"\s+value="([^"]+)"', page) or [None, ''])[1]
        poster('/admin/configuration', {
            'identifiant': IDENTIFIANT, 'mot_de_passe': MOT_DE_PASSE,
            'confirmation': MOT_DE_PASSE, '_csrf': jeton,
        })

        for ecran, action, fichier in ECRANS:
            try:
                page = lire_page(ecran)
            except Exception as souci:
                ecarts += 1
                print('  ÉCHEC %-20s ouverture impossible — %s' % (ecran, souci))
                continue

            cible, champs = formulaire(page, action)
            if cible is None:
                ecarts += 1
                print('  ÉCHEC %-20s aucun formulaire vers %s' % (ecran, action))
                continue

            avant = contenu(fichier)
            try:
                poster(cible, champs)
            except Exception as souci:
                ecarts += 1
                print('  ÉCHEC %-20s enregistrement refusé — %s' % (ecran, souci))
                continue
            apres = contenu(fichier)

            a, b = feuilles(avant), feuilles(apres)
            if b < a:
                ecarts += 1
                print('  ÉCART %-20s %s : %d valeurs avant, %d après' % (ecran, fichier, a, b))
                avant_plat, apres_plat = dict(aplatir(avant)), dict(aplatir(apres))
                for cle, valeur in avant_plat.items():
                    if valeur not in ('', None) and apres_plat.get(cle) in ('', None):
                        print('        perdu : %s = %r' % (cle, valeur))
            else:
                print('     ok %-20s %s : %d valeurs, intactes' % (ecran, fichier, b))
    finally:
        serveur.terminate()
        try:
            serveur.wait(timeout=5)
        except subprocess.TimeoutExpired:
            serveur.kill()
        shutil.rmtree(donnees, ignore_errors=True)

    print('---')
    print('%d écran(s) rejoué(s) — %d écart(s).' % (len(ECRANS), ecarts))
    if ecarts:
        print('Un enregistrement qui fait maigrir le JSON vient presque toujours d’un '
              '« name= » du gabarit que le contrôleur ne relit pas sous ce nom.')
    return 1 if ecarts else 0


if __name__ == '__main__':
    sys.exit(main())
