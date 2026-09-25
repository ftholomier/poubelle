import { createECDH, randomBytes } from 'node:crypto';
import webpush from 'web-push';
import { describe, expect, it } from 'vitest';

describe('notifications push (Web Push, VAPID)', () => {
  it('chiffre la charge utile et signe la requête avec les clés VAPID', () => {
    const vapid = webpush.generateVAPIDKeys();
    const client = createECDH('prime256v1');
    client.generateKeys();
    const subscription = {
      endpoint: 'https://push.exemple.test/abonnement/123',
      keys: { p256dh: client.getPublicKey().toString('base64url'), auth: randomBytes(16).toString('base64url') },
    };
    const payload = JSON.stringify({ title: 'Nouveau message pour Boulangerie Martin', body: 'Julie : deux galettes ?', url: '/pro/x/messages' });
    const req = webpush.generateRequestDetails(subscription, payload, {
      vapidDetails: { subject: 'mailto:contact@terricom.fr', publicKey: vapid.publicKey, privateKey: vapid.privateKey },
      TTL: 86_400,
    });
    expect(req.endpoint).toBe(subscription.endpoint);
    expect(req.method).toBe('POST');
    expect(req.headers['Content-Encoding']).toBe('aes128gcm');
    expect(String(req.headers.Authorization)).toMatch(/^vapid t=.+, k=/);
    expect(req.headers.TTL).toBe(86_400);
    // Le contenu est chiffré : le texte en clair n'apparaît pas dans le corps.
    expect(Buffer.from(req.body as Buffer).toString('utf8')).not.toContain('Boulangerie');
  });
});
