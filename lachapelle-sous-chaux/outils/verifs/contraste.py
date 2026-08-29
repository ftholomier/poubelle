"""Contraste de chaque texte du site, mesuré et non estimé.

Deux méthodes, parce qu'aucune ne suffit seule :

  1. par composition — on remonte les ancêtres en aplatissant les couches
     translucides jusqu'à un fond opaque. Exact et rapide, mais aveugle dès
     que le fond est une photo, un dégradé, ou un flou d'arrière-plan.
  2. par échantillonnage des pixels peints — on masque le texte, on capture,
     et on lit le fond réel sous chaque bloc. Seule méthode valable sur un
     bandeau photographique ou sous une barre translucide.

La première tranche quand elle le peut, la seconde prend le relais sinon. On
retient le PIRE pixel de la zone et non la moyenne : un titre dont un seul mot
passe sur une éclaircie est illisible sur ce mot-là.

Usage :
    php -S 127.0.0.1:8081 -t public &
    python3 outils/verifs/contraste.py
    python3 outils/verifs/contraste.py --base http://127.0.0.1:8081 / /contact

Sort en code 1 s'il reste un écart : utilisable tel quel dans une chaîne
d'intégration.
"""
import argparse
import io
import json
import os
import sys

from PIL import Image
from playwright.sync_api import sync_playwright

# Les pages du site Baron. Pour un autre projet, passer la liste en argument
# ou réécrire cette constante — ce sont les slugs de Seo::PAGES.
PAGES = ('/', '/la-mairie', '/conseil-municipal', '/commissions-et-comites',
         '/comptes-rendus-du-conseil', '/budget-communal', '/urbanisme',
         '/demarches', '/demarches/carte-d-identite-passeport',
         '/demarches/declaration-prealable-de-travaux',
         '/demarches-en-ligne', '/services-de-l-etat', '/ccas', '/vie-scolaire',
         '/le-village', '/histoire-du-village', '/associations', '/actualites',
         '/actualites/concert-pour-les-anciens-du-village', '/agenda',
         '/flash-info', '/vie-pratique', '/gerer-mes-dechets',
         '/eau-et-assainissement', '/intercommunalite', '/numeros-utiles',
         '/demande-en-ligne', '/contact', '/mentions-legales',
         '/politique-de-confidentialite', '/accessibilite', '/plan-du-site')

LARGEURS = (390, 768, 1440)

# Chromium fourni par Playwright. Adapter si le poste l'a ailleurs.
NAVIGATEUR = os.environ.get('CHROMIUM', '/opt/pw-browsers/chromium')

SELECTEURS = ('p, h1, h2, h3, h4, a, li, span, summary, figcaption, address, '
              'blockquote, button, label, td, th, dt, dd')

# Ce que le masque éteint en plus du texte : un ornement peint dans la boîte
# d'un bloc mais sous aucune lettre fausserait le pire pixel. Et l'animation
# d'entrée doit être neutralisée AVANT le relevé des coordonnées.
MASQUE = ('.surtitre::before{background:transparent !important}'
          '.reveler{opacity:1 !important;transform:none !important}'
          # Le défilement doux est une animation : scrollTo rend la main
          # aussitôt et l'on capture pendant le trajet. Demandé 2 000 px, la
          # page en était à 339 — tout l'échantillonnage tombait à côté.
          'html{scroll-behavior:auto !important}')

EFFACER_TEXTE = '*{color:transparent !important}'

RELEVE = """(sel) => {
  const rgb = s => (s.match(/[\\d.]+/g) || []).slice(0, 3).map(Number);
  const alpha = s => { const m = s.match(/[\\d.]+/g); return m && m.length > 3 ? +m[3] : 1; };

  // Fond effectif par composition, ou null quand elle ne peut pas conclure.
  //
  // Le piège : le corps de page est opaque, si bien qu'une chaîne entièrement
  // translucide finit toujours par y trouver du blanc — et l'on croit mesurer
  // un texte sur blanc alors qu'une photo est peinte entre les deux. Un aplat
  // trouvé sur <body> ou <html> ne prouve donc rien : on rend la main à
  // l'échantillonnage.
  const fond = el => {
    let e = el; const pile = []; let ancre = null;
    while (e) {
      const st = getComputedStyle(e);
      if (st.backgroundImage !== 'none') return null;
      if (st.backdropFilter && st.backdropFilter !== 'none') return null;
      const a = alpha(st.backgroundColor);
      if (a > 0) pile.push([rgb(st.backgroundColor), a]);
      if (a >= 1) { ancre = e; break; }
      e = e.parentElement;
    }
    if (!ancre || ancre === document.body || ancre === document.documentElement) return null;
    let f = [255, 255, 255];
    for (let i = pile.length - 1; i >= 0; i--) {
      const [c, a] = pile[i];
      f = f.map((x, k) => Math.round(c[k] * a + x * (1 - a)));
    }
    return f;
  };

  const releve = [];
  document.querySelectorAll(sel).forEach(el => {
    // seuls les nœuds de texte propres à l'élément comptent : sinon un
    // conteneur hérite du texte de ses enfants et on mesure deux fois
    const t = [...el.childNodes].filter(n => n.nodeType === 3)
                                .map(n => n.textContent.trim()).join(' ').trim();
    if (!t) return;
    const st = getComputedStyle(el);
    if (st.visibility === 'hidden' || st.display === 'none' || +st.opacity === 0) return;

    const r = el.getBoundingClientRect();
    const pg = parseFloat(st.paddingLeft), pd = parseFloat(st.paddingRight);
    const ph = parseFloat(st.paddingTop),  pb = parseFloat(st.paddingBottom);
    if (r.width - pg - pd < 3 || r.height - ph - pb < 3) return;
    if (r.left < -1000) return;                        // hors écran à dessein

    const px = parseFloat(st.fontSize);
    releve.push({
      texte: t.slice(0, 40),
      sel: el.tagName.toLowerCase() + (el.className ? '.' + String(el.className).split(' ')[0] : ''),
      couleur: rgb(st.color),
      opacite: alpha(st.color),
      seuil: (px >= 24 || (px >= 18.66 && +st.fontWeight >= 700)) ? 3 : 4.5,
      // Un élément fixe ne se mesure qu'en haut de page : ailleurs ses
      // coordonnées documentaires n'ont pas de sens, et il recouvrirait les
      // autres blocs dans la capture.
      fixe: (() => { let e = el; while (e) { if (getComputedStyle(e).position === 'fixed') return true;
                                             e = e.parentElement; } return false; })(),
      fond: fond(el),
      // La boîte de CONTENU, rembourrage exclu : une légende sur dégradé a
      // souvent 2,6 rem de rembourrage haut, où le dégradé n'est pas encore
      // dense mais où aucune lettre n'est tracée.
      boite: [Math.round(r.left + scrollX + pg), Math.round(r.top + scrollY + ph),
              Math.round(r.width - pg - pd), Math.round(r.height - ph - pb)],
    });
  });
  return releve;
}"""


def luminance(c):
    v = []
    for x in c:
        x /= 255
        v.append(x / 12.92 if x <= .03928 else ((x + .055) / 1.055) ** 2.4)
    return .2126 * v[0] + .7152 * v[1] + .0722 * v[2]


def rapport(avant, arriere):
    a, b = luminance(avant), luminance(arriere)
    return (max(a, b) + .05) / (min(a, b) + .05)


def composer(couleur, opacite, fond):
    return [round(c * opacite + f * (1 - opacite)) for c, f in zip(couleur, fond)]


def pire_pixel(peint, boite, couleur, opacite):
    x, y, w, h = boite
    pire = None
    for dx in range(2, max(3, w), max(6, w // 24)):
        for dy in range(2, max(3, h), max(4, h // 6)):
            px, py = x + dx, y + dy
            if 0 <= px < peint.width and 0 <= py < peint.height:
                f = list(peint.getpixel((px, py)))
                r = rapport(composer(couleur, opacite, f), f)
                if pire is None or r < pire:
                    pire = r
    return pire


def auditer(pg, hauteur):
    """Relève, masque, puis échantillonne — par tranches de la hauteur de
    fenêtre.

    Surtout pas de capture pleine page : Chromium l'obtient en agrandissant la
    fenêtre, si bien qu'un « min-height: 88vh » passe de 880 à 4 700 px et que
    tout ce qui suit le bandeau se décale de milliers de pixels.
    """
    # Le masque d'abord, le relevé ensuite : l'animation d'entrée décale les
    # blocs, et des boîtes relevées avant sa neutralisation désigneraient le
    # fond de page à côté du bloc.
    pg.add_style_tag(content=MASQUE)
    pg.wait_for_timeout(300)

    # Puis les photos, avant toute mesure. Sans elles le fond échantillonné
    # est le blanc de la page, et un titre clair sur un bandeau non encore
    # chargé ressort à 1,00:1 — faussement illisible. Il faut descendre la
    # page pour déclencher les images différées, sinon on attend en vain
    # celles qui ne sont jamais entrées dans le cadre.
    # Par paliers, et non d'un saut : le chargement différé de Chromium ne se
    # déclenche que pour les images qui approchent du cadre. Sur une page de
    # sept mille pixels, un saut direct au bas de page laisse dormir tout ce
    # qui se trouve au milieu — et l'attente qui suit expire pour rien.
    pg.evaluate("""() => new Promise(fini => {
        let y = 0;
        const pas = window.innerHeight * 0.8;
        const t = setInterval(() => {
            window.scrollTo(0, y);
            y += pas;
            if (y > document.body.scrollHeight) { clearInterval(t); window.scrollTo(0, 0); fini(); }
        }, 60);
    })""")
    pg.wait_for_timeout(500)
    try:
        pg.wait_for_function(
            '() => [...document.images].every(i => i.complete && i.naturalWidth > 0)',
            timeout=12000)
    except Exception:
        # une image absente ne doit pas arrêter l'audit : elle sera signalée
        # par l'auditeur de mise en page, pas par celui-ci
        print('              (au moins une image ne s’est pas chargée)')
    pg.wait_for_timeout(400)

    releve = pg.evaluate(RELEVE, SELECTEURS)

    # Le texte s'efface une fois pour toutes, APRÈS le relevé des couleurs.
    # Le poser juste avant chaque capture ne laisse pas au navigateur le temps
    # de l'appliquer : on échantillonne alors les lettres blanches elles-mêmes
    # et tout texte clair et dense — un menu — ressort faussement illisible.
    pg.add_style_tag(content=EFFACER_TEXTE)
    pg.wait_for_timeout(250)

    for i, bloc in enumerate(releve):
        bloc['rang'] = i
    a_peindre = [b for b in releve if b['fond'] is None]
    mesures = {}

    for t in sorted({max(0, b['boite'][1] - 40) // hauteur for b in a_peindre}):
        # On demande la tranche, mais on relit où la page s'est réellement
        # arrêtée : en bas de document le défilement est borné, et calculer
        # les coordonnées sur la position demandée ferait échantillonner
        # plusieurs centaines de pixels à côté — donc n'importe quel bloc.
        pg.evaluate('window.scrollTo(0, %d)' % (t * hauteur))
        pg.wait_for_timeout(120)
        # On relit où la page s'est réellement arrêtée : en bas de document le
        # défilement est borné, et calculer sur la position demandée ferait
        # échantillonner plusieurs centaines de pixels à côté.
        haut = pg.evaluate('() => Math.round(window.scrollY)')
        # Passé la première tranche, la barre collante recouvre le haut de la
        # fenêtre et masquerait le vrai fond des blocs qui passent dessous.
        # Ses propres textes ont été mesurés en tranche zéro, sur la photo,
        # c'est-à-dire au pire.
        if t > 0:
            pg.add_style_tag(content='.entete{visibility:hidden !important}')
        pg.wait_for_timeout(220)
        peint = Image.open(io.BytesIO(pg.screenshot())).convert('RGB')

        for bloc in a_peindre:
            x, y, w, h = bloc['boite']
            if bloc['fixe'] and t > 0:
                continue
            if y < haut or y + h > haut + hauteur:
                continue
            r = pire_pixel(peint, (x, y - haut, w, h), bloc['couleur'], bloc['opacite'])
            rang = bloc['rang']
            if r is not None and (rang not in mesures or r < mesures[rang]):
                mesures[rang] = r

    ecarts = []
    for bloc in releve:
        if bloc['fond'] is not None:
            pire = rapport(composer(bloc['couleur'], bloc['opacite'], bloc['fond']), bloc['fond'])
        else:
            pire = mesures.get(bloc['rang'])
            if pire is None:
                continue                       # jamais capturé entier : on s'abstient
        if pire < bloc['seuil'] - .02:
            ecarts.append((bloc['sel'], pire, bloc['seuil'], bloc['texte']))
    return ecarts


def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('pages', nargs='*', default=list(PAGES))
    ap.add_argument('--base', default='http://127.0.0.1:8081')
    args = ap.parse_args()
    pages = args.pages or list(PAGES)

    # Consentement déjà donné : on veut mesurer la page telle qu'elle est
    # servie, bandeau cookies replié.
    consentement = json.dumps({'v': 1, 'd': '2026-01-01', 'mesure': False, 'externes': False})

    total = 0
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path=NAVIGATEUR, args=['--no-sandbox'])
        for largeur in LARGEURS:
            ctx = b.new_context(viewport={'width': largeur, 'height': 1000})
            ctx.add_cookies([{'name': 'cv_consentement', 'value': consentement, 'url': args.base}])
            pg = ctx.new_page()
            for chemin in pages:
                pg.goto(args.base + chemin, wait_until='domcontentloaded')
                pg.wait_for_timeout(400)
                ecarts = auditer(pg, 1000)
                total += len(ecarts)
                print('%5d px  %-30s %s' % (largeur, chemin,
                                            'ok' if not ecarts else '%d écart(s)' % len(ecarts)))
                for sel, r, seuil, texte in ecarts:
                    print('              %-26s %5.2f:1  (seuil %s)  « %s »' % (sel, r, seuil, texte))
            ctx.close()
        b.close()

    print('---')
    print('%d écart(s) au total.' % total)
    return 1 if total else 0


if __name__ == '__main__':
    sys.exit(main())
