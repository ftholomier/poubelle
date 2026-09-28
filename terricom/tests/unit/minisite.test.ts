import { describe, expect, it } from 'vitest';
import { blend, contrastRatio, isHexColor, isLight, readableOnLight } from '@/lib/color';
import { DEFAULT_SECTIONS, themeStyle, visibleSections } from '@/lib/minisite';
import { renderNewsletter } from '@/server/mail/newsletter';

describe('couleurs du mini-site', () => {
  it('valide les couleurs hexadécimales', () => {
    expect(isHexColor('#7a2e3b')).toBe(true);
    expect(isHexColor('7a2e3b')).toBe(false);
    expect(isHexColor('#fff')).toBe(false);
    expect(isHexColor(null)).toBe(false);
  });

  it('mélange deux couleurs', () => {
    expect(blend('#000000', '#ffffff', 0.5)).toBe('#808080');
    expect(blend('#ff0000', '#ffffff', 1)).toBe('#ff0000');
  });

  it('assombrit une couleur claire jusqu’à un contraste lisible', () => {
    const yellow = '#f4d03f';
    expect(isLight(yellow)).toBe(true);
    const readable = readableOnLight(yellow);
    expect(contrastRatio(readable, '#fffdf8')).toBeGreaterThanOrEqual(4.5);
    // Une couleur déjà lisible n'est pas modifiée.
    expect(readableOnLight('#14201b')).toBe('#14201b');
  });

  it('ne produit des variables que pour une couleur définie', () => {
    expect(themeStyle(null)).toBeUndefined();
    const vars = themeStyle('#2c5a86') as Record<string, string>;
    expect(vars['--brand']).toBe(vars['--green']);
    expect(vars['--mint']).toMatch(/^#[0-9a-f]{6}$/);
  });
});

describe('sections du mini-site', () => {
  it('garde l’ordre par défaut hors mini-site', () => {
    expect(visibleSections({ sections: ['contact', 'produits'] }, false)).toEqual(DEFAULT_SECTIONS);
  });

  it('respecte l’ordre choisi et ignore les valeurs inconnues ou en double', () => {
    const order = visibleSections({ sections: ['produits', 'inconnue' as never, 'presentation', 'produits'] }, true);
    expect(order).toEqual(['produits', 'presentation']);
  });

  it('renvoie une copie modifiable', () => {
    const a = visibleSections({}, true);
    a.push('contact');
    expect(DEFAULT_SECTIONS.filter((s) => s === 'contact')).toHaveLength(1);
  });
});

describe('lettre d’une entreprise', () => {
  const base = {
    brandName: 'La Cave Comtoise',
    number: null,
    color: '#7a2e3b',
    accent: '#f4b266',
    title: 'Les coffrets sont prêts',
    intro: '',
    heroImageUrl: null,
    blocks: [{ type: 'text' as const, text: 'Bonjour <b>à tous</b>' }],
    newsletterName: 'La Cave Comtoise',
    unsubscribeUrl: 'https://exemple.test/suivre/desinscription?token=abc',
  };

  it('affiche l’expéditeur, la raison de l’envoi et le lien de désinscription, sans lien de préférences', () => {
    const { html, text } = renderNewsletter({
      ...base,
      preferencesUrl: null,
      reason: 'Vous suivez La Cave Comtoise.',
      sender: 'Envoyé par La Cave Comtoise · Ornans',
    });
    expect(html).toContain('Envoyé par La Cave Comtoise · Ornans');
    expect(html).toContain('Vous suivez La Cave Comtoise.');
    expect(html).toContain('Se désinscrire');
    expect(html).not.toContain('Gérer mes préférences');
    expect(html).toContain('Bonjour &lt;b&gt;à tous&lt;/b&gt;');
    expect(text).toContain('Envoyé par La Cave Comtoise · Ornans');
  });

  it('conserve le lien de préférences des lettres du territoire', () => {
    const { html } = renderNewsletter({ ...base, preferencesUrl: 'https://exemple.test/preferences' });
    expect(html).toContain('Gérer mes préférences');
    expect(html).toContain('inscrit·e à la lettre La Cave Comtoise');
  });
});

describe('texte mis en forme des pages', () => {
  it('rend intertitres, listes, gras et liens sans interpréter le HTML', async () => {
    const { createElement } = await import('react');
    const { renderToStaticMarkup } = await import('react-dom/server');
    const { RichText } = await import('@/components/portal/RichText');
    const html = renderToStaticMarkup(
      createElement(RichText, {
        text: 'Intro <script>alert(1)</script>\n\n## Titre\n- **Un** point\n- [Lien](https://exemple.test) et [piège](javascript:alert(1))',
      }),
    );
    expect(html).toContain('<h2>Titre</h2>');
    expect(html).toContain('<li><strong>Un</strong> point</li>');
    expect(html).toContain('href="https://exemple.test"');
    expect(html).toContain('&lt;script&gt;');
    expect(html).not.toContain('<script>');
    expect(html).not.toContain('href="javascript:');
  });
});
