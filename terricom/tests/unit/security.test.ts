import { describe, expect, it } from 'vitest';
import { base32Decode, base32Encode, hotp, verifyTotp } from '@/server/auth/totp';
import { decrypt, encrypt, hashPassword, passwordIssues, safeEqual, sha256, verifyPassword } from '@/server/crypto';
import { toCsv } from '@/server/csv';
import { completeSiret, isValidSiret } from '@/server/integrations/public-data';

describe('double authentification (RFC 4226 / 6238)', () => {
  // Secret de référence de la RFC 4226 : « 12345678901234567890 »
  const secret = base32Encode(Buffer.from('12345678901234567890'));

  it('encode et décode en base32', () => {
    expect(base32Decode(secret).toString()).toBe('12345678901234567890');
  });

  it('respecte les vecteurs de test HOTP', () => {
    expect(hotp(secret, 0)).toBe('755224');
    expect(hotp(secret, 1)).toBe('287082');
    expect(hotp(secret, 9)).toBe('520489');
  });

  it('accepte le code courant et refuse un code erroné', () => {
    const at = Date.UTC(2026, 8, 25, 12, 0, 0);
    const step = Math.floor(at / 1000 / 30);
    expect(verifyTotp(secret, hotp(secret, step), at)).toBe(step);
    expect(verifyTotp(secret, '000000', at)).toBeNull();
  });
});

describe('chiffrement et mots de passe', () => {
  it('chiffre de façon authentifiée et non déterministe', () => {
    const a = encrypt('JBSWY3DPEHPK3PXP');
    const b = encrypt('JBSWY3DPEHPK3PXP');
    expect(a).not.toBe(b);
    expect(decrypt(a)).toBe('JBSWY3DPEHPK3PXP');
    const tampered = a.slice(0, -2) + (a.endsWith('A') ? 'B' : 'A') + a.slice(-1);
    expect(() => decrypt(tampered)).toThrow();
  });

  it('hache les mots de passe (scrypt) et les vérifie', async () => {
    const hash = await hashPassword('Terricom2026!');
    expect(hash).not.toContain('Terricom2026!');
    expect(await verifyPassword('Terricom2026!', hash)).toBe(true);
    expect(await verifyPassword('mauvais', hash)).toBe(false);
  });

  it('refuse les mots de passe trop faibles', () => {
    expect(passwordIssues('court')).not.toBeNull();
    expect(passwordIssues('Terricom2026!')).toBeNull();
  });

  it('compare en temps constant', () => {
    expect(safeEqual(sha256('a'), sha256('a'))).toBe(true);
    expect(safeEqual(sha256('a'), sha256('b'))).toBe(false);
  });
});

describe('exports CSV', () => {
  it('neutralise les formules (injection CSV) et échappe les séparateurs', () => {
    const csv = toCsv(
      ['Nom', 'Note'],
      [
        ['=HYPERLINK("http://x")', 'a;b'],
        ['Boulangerie "Martin"', '+33'],
      ],
    );
    expect(csv.startsWith('﻿')).toBe(true);
    expect(csv).toContain(`"'=HYPERLINK(""http://x"")"`);
    expect(csv).toContain('"a;b"');
    expect(csv).toContain(`'+33`);
    expect(csv).toContain('"Boulangerie ""Martin"""');
  });
});

describe('SIRET', () => {
  it('valide la clé de Luhn', () => {
    expect(isValidSiret('73282932000074')).toBe(true);
    expect(isValidSiret('73282932000075')).toBe(false);
    expect(isValidSiret('123')).toBe(false);
  });

  it('complète un SIRET à partir de ses 13 premiers chiffres', () => {
    const s = completeSiret('7328293200007');
    expect(s).toHaveLength(14);
    expect(isValidSiret(s)).toBe(true);
  });
});
