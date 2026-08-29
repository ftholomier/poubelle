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

Usage :
    php -S 127.0.0.1:8081 -t public &
    python3 outils/verifs/mise-en-page.py
    python3 outils/verifs/mise-en-page.py --base http://127.0.0.1:8081 / /contact

Sort en code 1 s'il reste un souci.
"""
import argparse
import json
import os
import sys

from playwright.sync_api import sync_playwright

PAGES = ('/', '/a-propos', '/nos-prestations', '/nos-prestations/entretien',
         '/realisations', '/nos-valeurs', '/faq', '/demander-un-devis',
         '/contact', '/mentions-legales')

LARGEURS = (320, 390, 768, 1024, 1440)
NAVIGATEUR = os.environ.get('CHROMIUM', '/opt/pw-browsers/chromium-1194/chrome-linux/chrome')

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
      // Une case à cocher se tape par son étiquette, qui lui est associée :
      // c'est l'étiquette qui porte la cible, pas la case de 22 px. On mesure
      // alors les deux ensemble.
      if (e.type === 'checkbox' || e.type === 'radio') {
        const lab = e.id && document.querySelector('label[for="' + CSS.escape(e.id) + '"]');
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

  // --- lien d'évitement
  const premier = document.querySelector('body a[href], body button');
  if (!premier || !/#/.test(premier.getAttribute('href') || '')) {
    soucis.push('pas de lien d’évitement en tête de body');
  }

  return soucis;
}"""


def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('pages', nargs='*', default=list(PAGES))
    ap.add_argument('--base', default='http://127.0.0.1:8081')
    args = ap.parse_args()
    pages = args.pages or list(PAGES)

    consentement = json.dumps({'v': 1, 'd': '2026-01-01', 'mesure': False, 'externes': False})

    total = 0
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path=NAVIGATEUR, args=['--no-sandbox'])
        for largeur in LARGEURS:
            # sous 780 px on simule un écran tactile : c'est là que les règles
            # de cible s'appliquent
            tactile = largeur <= 780
            ctx = b.new_context(viewport={'width': largeur, 'height': 900},
                                has_touch=tactile, is_mobile=tactile)
            ctx.add_cookies([{'name': 'cv_consentement', 'value': consentement, 'url': args.base}])
            pg = ctx.new_page()
            for chemin in pages:
                pg.goto(args.base + chemin, wait_until='domcontentloaded')
                pg.add_style_tag(content='.reveler{opacity:1 !important;transform:none !important}')
                pg.wait_for_timeout(500)
                soucis = pg.evaluate(CONTROLE)
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
