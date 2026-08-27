"""Ce qui casse une maquette sans qu'on le voie sur son propre écran.

Cinq contrôles, tous rédhibitoires si l'un échoue :

  - débordement horizontal, à cinq largeurs. Un scrollWidth supérieur au
    clientWidth signifie que la page se balade latéralement sur téléphone.
    C'est le défaut le plus fréquent et le plus mal jugé.
  - cibles tactiles sous 44 px de haut, mesurées sous « hover: none ».
  - un seul <h1> par page, et pas de saut dans la hiérarchie des titres.
  - un alt sur chaque image, non vide et non réduit au nom du fichier.
  - un lien d'évitement en premier élément focalisable.

Usage :
    cd maquettes && python3 -m http.server 8090 &
    python3 verif-mise-en-page.py
"""
import sys

from playwright.sync_api import sync_playwright

BASE = 'http://127.0.0.1:8090'
PAGES = ('accueil', 'a-propos', 'prestations')
LARGEURS = (320, 390, 768, 1024, 1440)
NAVIGATEUR = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome'

CONTROLE = """() => {
  const de = document.documentElement;
  const soucis = [];

  // --- débordement horizontal, et qui le cause
  if (de.scrollWidth > de.clientWidth + 1) {
    const coupables = [...document.querySelectorAll('*')].filter(e => {
      const b = e.getBoundingClientRect();
      return b.width > 0 && (b.left < -2 || b.right > de.clientWidth + 2);
    }).slice(0, 4).map(e => e.tagName.toLowerCase() + '.' + String(e.className).split(' ')[0]);
    soucis.push('déborde de ' + (de.scrollWidth - de.clientWidth) + ' px — ' + coupables.join(', '));
  }

  // --- cibles tactiles
  if (matchMedia('(hover: none)').matches) {
    document.querySelectorAll('a[href], button, summary, input, select').forEach(e => {
      const b = e.getBoundingClientRect();
      // une cible hors écran à dessein (lien d'évitement, piège) ne compte pas
      if (b.height === 0 || b.left < -1000) return;
      if (b.height < 44) {
        soucis.push('cible de ' + Math.round(b.height) + ' px : ' +
                    e.tagName.toLowerCase() + ' « ' + e.textContent.trim().slice(0, 24) + ' »');
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
  document.querySelectorAll('img').forEach(i => {
    const alt = (i.getAttribute('alt') || '').trim();
    const src = (i.getAttribute('src') || '').split('/').pop().replace(/\\.\\w+$/, '');
    if (!alt) soucis.push('image sans alt : ' + src);
    else if (alt.toLowerCase().replace(/[^a-z]/g, '') === src.toLowerCase().replace(/[^a-z]/g, '')) {
      soucis.push('alt qui recopie le nom de fichier : ' + src);
    }
  });

  // --- lien d'évitement
  const premier = document.querySelector('body a[href], body button');
  if (!premier || !/#/.test(premier.getAttribute('href') || '')) {
    soucis.push('pas de lien d’évitement en tête de body');
  }

  return soucis;
}"""


def main():
    total = 0
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path=NAVIGATEUR, args=['--no-sandbox'])
        for largeur in LARGEURS:
            # sous 780 px on simule un écran tactile : c'est là que les
            # règles de cible s'appliquent
            tactile = largeur <= 780
            ctx = b.new_context(viewport={'width': largeur, 'height': 900},
                                has_touch=tactile, is_mobile=tactile)
            pg = ctx.new_page()
            for page in PAGES:
                pg.goto('%s/%s.html' % (BASE, page), wait_until='domcontentloaded')
                pg.add_style_tag(content='.reveler{opacity:1 !important;transform:none !important}')
                pg.wait_for_timeout(500)
                soucis = pg.evaluate(CONTROLE)
                total += len(soucis)
                print('%5d px  %-13s %s' % (largeur, page,
                                            'ok' if not soucis else '%d souci(s)' % len(soucis)))
                for s in soucis:
                    print('           · %s' % s)
            ctx.close()
        b.close()

    print('---')
    print('%d souci(s) au total.' % total)
    return 1 if total else 0


if __name__ == '__main__':
    sys.exit(main())
