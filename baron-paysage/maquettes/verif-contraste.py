"""Contraste de chaque texte des maquettes, mesuré et non estimé.

Deux méthodes, parce qu'aucune ne suffit seule :

  1. par composition — on remonte les ancêtres en aplatissant les couches
     translucides jusqu'à un fond opaque. Exact, rapide, mais aveugle dès que
     le fond est une photo ou un dégradé.
  2. par échantillonnage des pixels peints — on masque le texte, on capture la
     page, et on lit le fond réel sous chaque bloc. C'est la seule méthode
     valable sur un bandeau photographique ou derrière un flou d'arrière-plan.

La première tranche quand elle le peut, la seconde prend le relais sinon. On
retient le PIRE pixel de la zone et non la moyenne : un titre dont un seul mot
passe sur une éclaircie est illisible sur ce mot-là.

Usage :
    cd maquettes && python3 -m http.server 8090 &
    python3 verif-contraste.py
"""
import io
import sys

from PIL import Image
from playwright.sync_api import sync_playwright

BASE = 'http://127.0.0.1:8090'
PAGES = ('accueil', 'a-propos', 'prestations')
LARGEURS = (390, 768, 1440)
NAVIGATEUR = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome'

SELECTEURS = 'p, h1, h2, h3, h4, a, li, span, summary, figcaption, address, blockquote, button, label'

# Ce que le masque doit éteindre en plus du texte : les ornements peints dans
# la boîte d'un bloc mais sous aucune lettre fausseraient le pire pixel.
MASQUE = ('.surtitre::before{background:transparent !important}'
          '.reveler{opacity:1 !important;transform:none !important}')

# Posé juste avant chaque capture, une fois les couleurs relevées : ne doit
# pas être en place au moment du relevé, sinon on lirait « transparent ».
EFFACER_TEXTE = '*{color:transparent !important}'

RELEVE = """(sel) => {
  const rgb = s => (s.match(/[\\d.]+/g) || []).slice(0, 3).map(Number);
  const alpha = s => { const m = s.match(/[\\d.]+/g); return m && m.length > 3 ? +m[3] : 1; };

  // Fond effectif par composition, ou null quand elle ne peut pas conclure.
  //
  // Le piège : le corps de page est opaque, si bien qu'une chaîne entièrement
  // translucide finit toujours par y trouver du blanc — et l'on croit mesurer
  // un texte sur blanc alors qu'une photo est peinte entre les deux. Un aplat
  // trouvé sur <body> ou <html> ne prouve donc rien sur ce qui est peint
  // derrière CE texte : on rend la main à l'échantillonnage.
  const fond = el => {
    let e = el; const pile = [];
    let ancre = null;
    while (e) {
      const st = getComputedStyle(e);
      if (st.backgroundImage !== 'none') return null;
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
    if (r.width < 3 || r.height < 3) return;
    if (r.width - parseFloat(st.paddingLeft) - parseFloat(st.paddingRight) < 3) return;
    if (r.height - parseFloat(st.paddingTop) - parseFloat(st.paddingBottom) < 3) return;
    if (r.left < -1000) return;                       // hors écran à dessein

    const px = parseFloat(st.fontSize);
    releve.push({
      texte: t.slice(0, 40),
      sel: el.tagName.toLowerCase() + (el.className ? '.' + String(el.className).split(' ')[0] : ''),
      couleur: rgb(st.color),
      opacite: alpha(st.color),
      seuil: (px >= 24 || (px >= 18.66 && +st.fontWeight >= 700)) ? 3 : 4.5,
      // Un élément fixe ne se mesure qu'en haut de page : ses coordonnées
      // documentaires n'ont pas de sens ailleurs, et il recouvrirait les
      // autres blocs dans la capture.
      fixe: (() => { let e = el; while (e) { if (getComputedStyle(e).position === 'fixed') return true;
                                             e = e.parentElement; } return false; })(),
      fond: fond(el),
      // La boîte de CONTENU, rembourrage exclu : une légende posée sur un
      // dégradé a souvent 2,4 rem de rembourrage haut, au-dessus duquel le
      // dégradé n'est pas encore dense — mais où aucune lettre n'est tracée.
      // Mesurer là ferait échouer un texte parfaitement lisible.
      boite: [Math.round(r.left + scrollX + parseFloat(st.paddingLeft)),
              Math.round(r.top + scrollY + parseFloat(st.paddingTop)),
              Math.round(r.width - parseFloat(st.paddingLeft) - parseFloat(st.paddingRight)),
              Math.round(r.height - parseFloat(st.paddingTop) - parseFloat(st.paddingBottom))],
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
    """Le plus mauvais contraste rencontré sous un bloc, et non la moyenne :
    un titre dont un seul mot passe sur une éclaircie est illisible sur ce
    mot-là."""
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
    tout ce qui suit le bandeau se décale de plusieurs milliers de pixels. Les
    coordonnées relevées avant ne désignent alors plus rien.
    """
    # Le masque D'ABORD, le relevé ensuite. L'animation d'entrée décale les
    # blocs de quatorze pixels : des boîtes relevées avant sa neutralisation
    # désignent, une fois la page figée, le fond de page à côté du bloc.
    pg.add_style_tag(content=MASQUE)
    pg.wait_for_timeout(400)
    releve = pg.evaluate(RELEVE, SELECTEURS)

    # Le texte s'efface une fois pour toutes, APRÈS le relevé des couleurs et
    # avant la première capture. Le poser juste avant chaque capture ne
    # laissait pas au navigateur le temps de l'appliquer : on échantillonnait
    # alors les lettres blanches elles-mêmes, et tout texte clair et dense —
    # un menu, typiquement — ressortait faussement illisible.
    pg.add_style_tag(content=EFFACER_TEXTE)
    pg.wait_for_timeout(250)

    # ce que la composition a tranché n'a pas besoin d'être capturé
    for i, bloc in enumerate(releve):
        bloc['rang'] = i
    a_peindre = [b for b in releve if b['fond'] is None]
    mesures = {}

    tranches = sorted({max(0, b['boite'][1] - 40) // hauteur for b in a_peindre})
    for t in tranches:
        haut = t * hauteur
        pg.evaluate('window.scrollTo(0, %d)' % haut)
        # Passé la première tranche, la barre fixe recouvre le haut de la
        # fenêtre : elle masquerait le vrai fond des blocs qui passent
        # dessous. On l'efface — ses propres textes ont été mesurés en
        # tranche zéro, là où ils sont sur la photo, c'est-à-dire au pire.
        if haut > 0:
            pg.add_style_tag(content='.entete{visibility:hidden !important}')
        pg.wait_for_timeout(220)
        peint = Image.open(io.BytesIO(pg.screenshot())).convert('RGB')
        for bloc in a_peindre:
            x, y, w, h = bloc['boite']
            if bloc['fixe'] and haut > 0:
                continue
            # le bloc doit tenir entier dans la tranche visible
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
                continue                  # bloc jamais capturé entier : on s'abstient
        if pire < bloc['seuil'] - .02:
            ecarts.append((bloc['sel'], pire, bloc['seuil'], bloc['texte']))
    return ecarts


def main():
    total = 0
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path=NAVIGATEUR, args=['--no-sandbox'])
        for largeur in LARGEURS:
            ctx = b.new_context(viewport={'width': largeur, 'height': 1000})
            pg = ctx.new_page()
            for page in PAGES:
                pg.goto('%s/%s.html' % (BASE, page), wait_until='domcontentloaded')
                pg.wait_for_timeout(600)
                ecarts = auditer(pg, 1000)
                total += len(ecarts)
                print('%5d px  %-13s %s' % (largeur, page,
                                            'ok' if not ecarts else '%d écart(s)' % len(ecarts)))
                for sel, r, seuil, texte in ecarts:
                    print('           %-26s %5.2f:1  (seuil %s)  « %s »' % (sel, r, seuil, texte))
            ctx.close()
        b.close()

    print('---')
    print('%d écart(s) au total.' % total)
    return 1 if total else 0


if __name__ == '__main__':
    sys.exit(main())
