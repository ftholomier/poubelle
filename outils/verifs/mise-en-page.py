"""Ce qui casse une page sans qu'on le voie sur son propre écran.

Cinq contrôles, à cinq largeurs, tous rédhibitoires :

  - débordement horizontal, et l'élément qui le cause. Un scrollWidth
    supérieur au clientWidth veut dire que la page se balade latéralement sur
    téléphone : c'est le défaut le plus fréquent et le plus mal jugé ;
  - cibles tactiles sous 44 px de haut, mesurées sous « hover: none » ;
  - un seul <h1> par page, sans saut dans la hiérarchie des titres ;
  - un alt sur chaque image, ni vide ni réduit au nom du fichier ;
  - un lien d'évitement en premier élément focalisable.

Deux faux positifs sont écartés d'office, parce qu'ils sont voulus : un
élément volontairement hors écran (lien d'évitement replié, piège à robots) et
une piste qui défile horizontalement par construction.

**Le back-office aussi**, avec `--admin identifiant:motdepasse`. Il n'était
pas mesuré, et c'est là qu'un défaut est passé : une pastille de code faite
pour occuper sa propre ligne, posée au fil d'un paragraphe, recouvrait la
ligne du dessus sur deux écrans. Personne ne regardait. Les écrans visités
sont ceux d'`alertes.py`, pour qu'il n'existe qu'un seul inventaire, et les
largeurs sont celles du site — téléphone compris.

Trois des contrôles ne s'y appliquent pas et sont écartés : le back-office n'a
pas de lien d'évitement (il n'est pas un site public parcouru au clavier par
des visiteurs), ses écrans portent parfois plusieurs titres de même rang, et
ses images sont des aperçus de médiathèque dont l'alt est le nom du fichier —
ce qui est ici l'information utile.

Usage :
    php -S 127.0.0.1:8081 -t public &
    python3 outils/verifs/mise-en-page.py
    python3 outils/verifs/mise-en-page.py --base http://127.0.0.1:8081 / /contact
    python3 outils/verifs/mise-en-page.py --admin secretaire:motdepasse

Sort en code 1 s'il reste un souci.
"""
import argparse
import json
import os
import re
import sys
import urllib.parse
import urllib.request

from playwright.sync_api import sync_playwright

# Les écrans du back-office sont déclarés une seule fois, dans alertes.py, qui
# vérifie en outre que la liste n'a pas pris de retard sur les routes. Deux
# inventaires du même ensemble finiraient par diverger.
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from alertes import ECRANS_ADMIN  # noqa: E402


def pages_du_site(base):
    """Les adresses à mesurer, lues dans le plan du site.

    Une liste écrite en dur ici se périme dès qu'une page est ajoutée ou
    qu'un slug est changé depuis le back-office — et une page non mesurée
    est exactement celle où l'écart passera. Le sitemap, lui, est produit
    par Seo::PAGES : il ne peut pas diverger de ce que le site sert.
    """
    from urllib.request import urlopen
    xml = urlopen(base + '/sitemap.xml', timeout=20).read().decode('utf-8')
    vues = []
    for loc in re.findall(r'<loc>([^<]+)</loc>', xml):
        chemin = loc.split(base, 1)[-1] if loc.startswith(base) else loc
        chemin = re.sub(r'^https?://[^/]+', '', chemin) or '/'
        if chemin not in vues:
            vues.append(chemin)
    return vues


LARGEURS = (320, 390, 768, 1024, 1440)

# Le back-office est mesuré aux mêmes largeurs que le site. Il l'a d'abord été
# à partir de 768 px seulement, son panneau latéral ne se repliant pas ; il se
# replie désormais en tiroir sous 900 px, et il n'y a plus de raison de
# l'épargner. Un secrétaire de mairie corrige une coquille depuis son
# téléphone comme n'importe qui.
LARGEURS_ADMIN = LARGEURS
NAVIGATEUR = os.environ.get('CHROMIUM', '/opt/pw-browsers/chromium')

CONTROLE = """() => {
  const de = document.documentElement;
  const soucis = [];
  const nom = e => e.tagName.toLowerCase() + (e.className ? '.' + String(e.className).split(' ')[0] : '');

  // --- débordement horizontal, et qui le cause
  if (de.scrollWidth > de.clientWidth + 1) {
    const coupables = [...document.querySelectorAll('*')].filter(e => {
      const b = e.getBoundingClientRect();
      if (b.width === 0) return false;
      // une piste qui défile déborde par construction : ce n'est pas la page
      let p = e.parentElement;
      while (p) { const st = getComputedStyle(p);
                  if (st.overflowX === 'auto' || st.overflowX === 'scroll') return false;
                  p = p.parentElement; }
      return b.left < -2 || b.right > de.clientWidth + 2;
    }).slice(0, 4).map(nom);
    soucis.push('déborde de ' + (de.scrollWidth - de.clientWidth) + ' px — ' + coupables.join(', '));
  }

  // --- cibles tactiles
  if (matchMedia('(hover: none)').matches) {
    document.querySelectorAll('a[href], button, summary, input, select').forEach(e => {
      const b = e.getBoundingClientRect();
      if (b.height === 0 || b.left < -1000) return;    // replié ou piège à robots
      /* L'exception « en ligne » du critère 2.5.8 : un lien posé au milieu
         d'une phrase ne peut pas être agrandi sans casser l'interligne du
         paragraphe, et la norme l'écarte pour cela. On la reconnaît à ce que
         le lien est en ligne ET que son bloc porte d'autre texte que lui.
         Sans cette exception, chaque « voir la page Contact » d'une aide en
         faisait un défaut, et le vrai signal — un bouton trop petit — se
         noyait dedans. */
      if (e.tagName === 'A' && getComputedStyle(e).display === 'inline') {
        const bloc = e.closest('p, li, td, dd, figcaption');
        if (bloc && bloc.textContent.trim().length > e.textContent.trim().length + 2) return;
      }

      // Un curseur se traîne, il ne se tape pas : sa cible est la poignée, que
      // le navigateur dimensionne lui-même, et la règle des 44 px ne s'y
      // applique pas. Le mesurer produisait un écart sur chaque réglage à
      // glissière du back-office.
      if (e.type === 'range') return;

      // Une case à cocher se tape par son étiquette : c'est l'étiquette qui
      // porte la cible, pas la case de 13 px. Elle peut lui être associée de
      // DEUX façons — un `for` qui pointe l'identifiant, ou une étiquette qui
      // l'ENVELOPPE. Ne chercher que la première rendait un écart sur chaque
      // case écrite « <label><input> texte</label> », qui est pourtant la
      // forme la plus courante et la plus sûre.
      if (e.type === 'checkbox' || e.type === 'radio') {
        const lab = (e.id && document.querySelector('label[for="' + CSS.escape(e.id) + '"]'))
                 || e.closest('label');
        if (lab && lab.getBoundingClientRect().height >= 43.5) return;
      }
      if (b.height < 43.5) {
        soucis.push('cible de ' + Math.round(b.height) + ' px : ' + nom(e) +
                    ' « ' + e.textContent.trim().slice(0, 24) + ' »');
      }
    });
  }

  // --- hiérarchie des titres
  const h1 = document.querySelectorAll('h1');
  if (h1.length !== 1) soucis.push(h1.length + ' balise(s) h1 — il en faut exactement une');
  let precedent = 0;
  document.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach(h => {
    const n = +h.tagName[1];
    if (precedent && n > precedent + 1) {
      soucis.push('saut de h' + precedent + ' à h' + n + ' — « ' + h.textContent.trim().slice(0, 30) + ' »');
    }
    precedent = n;
  });

  // --- textes de remplacement
  //
  // alt="" n'est pas un oubli mais une déclaration : l'image est décorative,
  // et un lecteur d'écran doit la sauter. C'est le balisage correct d'un logo
  // posé à côté du nom écrit en toutes lettres. Seul un attribut ABSENT est
  // un défaut — l'outil d'assistance annonce alors le nom du fichier.
  document.querySelectorAll('img').forEach(i => {
    const brut = i.getAttribute('alt');
    const src = (i.getAttribute('src') || '').split('/').pop().split('?')[0].replace(/\\.\\w+$/, '');
    const nu = s => s.toLowerCase().replace(/[^a-z]/g, '');
    if (brut === null) { soucis.push('image sans attribut alt : ' + src); return; }
    const alt = brut.trim();
    if (alt === '') {
      // décorative : elle doit alors être retirée de l'arbre d'accessibilité
      // ou porter un texte équivalent à côté d'elle
      if (i.getAttribute('aria-hidden') !== 'true' && !i.closest('a, figure')) {
        soucis.push('alt vide sans aria-hidden ni texte voisin : ' + src);
      }
      return;
    }
    // Un fichier nommé d'après son sujet — « benjamin-baron.jpg » pour le
    // portrait de Benjamin Baron — donne un alt légitime qui ressemble au nom
    // du fichier. Ce qui trahit un alt recopié, c'est sa FORME : un slug sans
    // espace, ou une extension restée dedans.
    const recopie = nu(alt) === nu(src) && (!/\s/.test(alt) || /\.(jpe?g|png|webp|svg|gif)$/i.test(alt));
    if (recopie) soucis.push('alt qui recopie le nom de fichier : ' + src);
  });

  // --- une boîte en ligne qui sort de son paragraphe
  //
  // Le rembourrage vertical d'un élément EN LIGNE ne pousse pas la ligne : la
  // boîte grandit, la ligne non, et le surplus se peint par-dessus le texte
  // voisin. C'est ainsi qu'une pastille de code faite pour occuper sa propre
  // ligne, posée au fil d'un paragraphe, s'est mise à recouvrir la ligne du
  // dessus — 46 px de haut dans une ligne de 22, dans le back-office, où
  // aucun auditeur ne regardait. La parade est `display: inline-block`, qui
  // rend la boîte mesurable par la ligne.
  //
  // On ne compare qu'au bloc qui contient : deux pixels de tolérance pour les
  // jambages et l'anticrénelage, et on écarte ce qui est positionné, donc
  // sorti du flux à dessein.
  document.querySelectorAll('*').forEach(e => {
    const st = getComputedStyle(e);
    if (st.display !== 'inline') return;              // seul « inline » a ce défaut
    if (st.position !== 'static' && st.position !== 'relative') return;
    const px = v => parseFloat(v) || 0;
    const sup = px(st.paddingTop) + px(st.borderTopWidth);
    const inf = px(st.paddingBottom) + px(st.borderBottomWidth);
    if (sup < 2 && inf < 2) return;                   // rien qui puisse déborder
    const b = e.getBoundingClientRect();
    if (b.height === 0 || b.left < -1000) return;
    // La hauteur de ligne du parent est ce que la ligne réserve réellement.
    const ligne = px(getComputedStyle(e.parentElement).lineHeight) || px(st.fontSize) * 1.2;
    const trop = b.height - ligne;
    if (trop > 2) {
      soucis.push('rembourrage vertical sur une boîte « inline » : ' + nom(e) +
                  ' fait ' + Math.round(b.height) + ' px dans une ligne de ' +
                  Math.round(ligne) + ' — il déborde de ' + Math.round(trop) +
                  ' px sur le texte voisin (mettre display: inline-block)');
    }
  });

  // --- lien d'évitement
  const premier = document.querySelector('body a[href], body button');
  if (!premier || !/#/.test(premier.getAttribute('href') || '')) {
    soucis.push('pas de lien d’évitement en tête de body');
  }

  return soucis;
}"""


# Ce qui n'a pas de sens dans le back-office. Trois contrôles sur cinq y sont
# hors sujet, et les écarter ici vaut mieux que d'affaiblir le contrôle pour
# tout le monde : le site public, lui, doit les tenir.
HORS_SUJET_ADMIN = (
    # Pas de lien d'évitement : ce n'est pas un site parcouru par des visiteurs,
    # et sa navigation tient dans un panneau latéral atteignable au clavier.
    'lien d’évitement',
    # Plusieurs titres de même rang par écran, et pas toujours de h1 : la
    # hiérarchie d'un formulaire n'est pas celle d'une page de contenu.
    'balise(s) h1',
    'saut de h',
    # Les aperçus de la médiathèque : leur nom de fichier est écrit à côté
    # d'eux, c'est lui l'information utile, et l'aperçu lui-même est
    # décoratif. Un alt qui le recopierait serait du bruit pour un lecteur
    # d'écran, un alt vide est ici le balisage juste.
    'alt qui recopie le nom de fichier',
    'alt vide sans aria-hidden',
)


def hors_sujet_admin(souci):
    return any(motif in souci for motif in HORS_SUJET_ADMIN)


def connexion(base, identifiant, mot_de_passe):
    """Ouvre une session d'administration et rend ses biscuits.

    Le compte est celui qu'on lui donne : cet auditeur ne crée rien et
    n'écrit rien. Il lit l'adresse du premier formulaire plutôt que de la
    deviner — selon qu'un compte existe ou non, /admin mène à la connexion ou
    à la première configuration.
    """
    bocal = urllib.request.HTTPCookieProcessor()
    session = urllib.request.build_opener(bocal)
    page = session.open(base + '/admin', timeout=20).read().decode('utf-8')

    action = re.search(r'<form[^>]*action="([^"]*)"', page)
    jeton = re.search(r'name="_csrf"\s+value="([^"]+)"', page)
    if action is None or jeton is None:
        print('Le formulaire de connexion est introuvable : rien à mesurer côté back-office.')
        sys.exit(1)

    champs = {'identifiant': identifiant, 'mot_de_passe': mot_de_passe, '_csrf': jeton.group(1)}
    session.open(urllib.request.Request(
        base + action.group(1) if action.group(1).startswith('/') else action.group(1),
        data=urllib.parse.urlencode(champs).encode('utf-8')), timeout=30)

    return [{'name': c.name, 'value': c.value, 'url': base} for c in bocal.cookiejar]


def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('pages', nargs='*')
    ap.add_argument('--base', default='http://127.0.0.1:8081')
    ap.add_argument('--admin', metavar='ID:MDP',
                    help='mesurer aussi les écrans du back-office')
    args = ap.parse_args()
    pages = args.pages or pages_du_site(args.base.rstrip("/"))

    consentement = json.dumps({'v': 1, 'd': '2026-01-01', 'mesure': False, 'externes': False})

    biscuits_admin = []
    if args.admin:
        identifiant, _, mot_de_passe = args.admin.partition(':')
        biscuits_admin = connexion(args.base.rstrip('/'), identifiant, mot_de_passe)

    total = 0
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path=NAVIGATEUR, args=['--no-sandbox'])
        for largeur in LARGEURS:
            # sous 780 px on simule un écran tactile : c'est là que les règles
            # de cible s'appliquent
            tactile = largeur <= 780
            ctx = b.new_context(viewport={'width': largeur, 'height': 900},
                                has_touch=tactile, is_mobile=tactile)
            ctx.add_cookies([{'name': 'cv_consentement', 'value': consentement, 'url': args.base}]
                            + biscuits_admin)
            pg = ctx.new_page()
            ecrans = list(ECRANS_ADMIN) if biscuits_admin and largeur in LARGEURS_ADMIN else []
            for chemin in pages + ecrans:
                administration = chemin.startswith('/admin')
                pg.goto(args.base + chemin, wait_until='domcontentloaded')
                pg.add_style_tag(content='.reveler{opacity:1 !important;transform:none !important}')
                pg.wait_for_timeout(500)
                soucis = [x for x in pg.evaluate(CONTROLE)
                          if not (administration and hors_sujet_admin(x))]
                total += len(soucis)
                print('%5d px  %-30s %s' % (largeur, chemin,
                                            'ok' if not soucis else '%d souci(s)' % len(soucis)))
                for s in soucis:
                    print('              · %s' % s)
            ctx.close()
        b.close()

    print('---')
    print('%d souci(s) au total.' % total)
    return 1 if total else 0


if __name__ == '__main__':
    sys.exit(main())
