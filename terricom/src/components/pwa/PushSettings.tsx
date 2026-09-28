'use client';

import { useEffect, useState } from 'react';
import { removePushSubscriptionAction, savePushSubscriptionAction, testPushAction } from '@/app/compte/actions';

type Support = 'checking' | 'unsupported' | 'denied' | 'ready';
type InstallEvent = Event & { prompt: () => Promise<void>; userChoice: Promise<{ outcome: string }> };

function keyBytes(base64: string): Uint8Array<ArrayBuffer> {
  const pad = '='.repeat((4 - (base64.length % 4)) % 4);
  const raw = atob((base64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
  const out = new Uint8Array(new ArrayBuffer(raw.length));
  for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
  return out;
}

/** Notifications sur cet appareil (Web Push) et installation de l'application. */
export function PushSettings({ vapidKey, devices }: { vapidKey: string | null; devices: number }) {
  const [support, setSupport] = useState<Support>('checking');
  const [subscribed, setSubscribed] = useState(false);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [install, setInstall] = useState<InstallEvent | null>(null);
  const [ios, setIos] = useState(false);

  useEffect(() => {
    const onPrompt = (e: Event) => {
      e.preventDefault();
      setInstall(e as InstallEvent);
    };
    window.addEventListener('beforeinstallprompt', onPrompt);
    const standalone = window.matchMedia('(display-mode: standalone)').matches;
    const timer = window.setTimeout(async () => {
      setIos(/iphone|ipad|ipod/i.test(navigator.userAgent) && !standalone);
      if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return setSupport('unsupported');
      if (Notification.permission === 'denied') return setSupport('denied');
      setSupport('ready');
      const reg = await navigator.serviceWorker.getRegistration('/');
      const sub = await reg?.pushManager.getSubscription();
      setSubscribed(Boolean(sub));
    }, 0);
    return () => {
      window.removeEventListener('beforeinstallprompt', onPrompt);
      window.clearTimeout(timer);
    };
  }, []);

  async function enable() {
    if (!vapidKey) return;
    setBusy(true);
    setMessage(null);
    try {
      const permission = await Notification.requestPermission();
      if (permission !== 'granted') {
        setSupport(permission === 'denied' ? 'denied' : 'ready');
        setMessage({ ok: false, text: 'Autorisation refusée : vous pourrez la modifier dans les réglages du navigateur.' });
        return;
      }
      const reg = (await navigator.serviceWorker.getRegistration('/')) ?? (await navigator.serviceWorker.register('/sw.js', { scope: '/' }));
      await navigator.serviceWorker.ready;
      const sub =
        (await reg.pushManager.getSubscription()) ?? (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(vapidKey) }));
      const res = await savePushSubscriptionAction(JSON.stringify(sub.toJSON()));
      setSubscribed(res.ok);
      setMessage({ ok: res.ok, text: res.message });
    } catch {
      setMessage({ ok: false, text: 'L’activation a échoué sur ce navigateur.' });
    } finally {
      setBusy(false);
    }
  }

  async function disable() {
    setBusy(true);
    try {
      const reg = await navigator.serviceWorker.getRegistration('/');
      const sub = await reg?.pushManager.getSubscription();
      if (sub) {
        await removePushSubscriptionAction(sub.endpoint);
        await sub.unsubscribe();
      }
      setSubscribed(false);
      setMessage({ ok: true, text: 'Notifications désactivées sur cet appareil.' });
    } finally {
      setBusy(false);
    }
  }

  async function test() {
    setBusy(true);
    const res = await testPushAction();
    setMessage({ ok: res.ok, text: res.message });
    setBusy(false);
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
      {!vapidKey ? (
        <div className="alert alert-info">Les notifications ne sont pas activées sur ce serveur (clés VAPID absentes).</div>
      ) : support === 'unsupported' ? (
        <div className="alert alert-info">
          Ce navigateur ne gère pas les notifications.{ios ? ' Sur iPhone, installez d’abord l’application sur l’écran d’accueil (voir ci-dessous).' : ''}
        </div>
      ) : support === 'denied' ? (
        <div className="alert alert-info">Les notifications sont bloquées pour ce site : autorisez-les dans les réglages du navigateur.</div>
      ) : (
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
          {subscribed ? (
            <>
              <span className="tag" style={{ background: 'var(--ok-bg)' }}>
                Activées sur cet appareil
              </span>
              <button type="button" className="btn btn-outline btn-sm" onClick={test} disabled={busy}>
                Envoyer un essai
              </button>
              <button type="button" className="btn btn-ghost btn-sm" onClick={disable} disabled={busy}>
                Désactiver
              </button>
            </>
          ) : (
            <button type="button" className="btn btn-brand btn-sm" onClick={enable} disabled={busy || support === 'checking'}>
              {busy ? 'Activation…' : 'Activer les notifications'}
            </button>
          )}
          <span style={{ fontSize: 12, color: 'var(--muted)' }}>
            {devices} appareil{devices > 1 ? 's' : ''} abonné{devices > 1 ? 's' : ''} sur votre compte
          </span>
        </div>
      )}
      {message ? (
        <div className={`alert ${message.ok ? 'alert-ok' : 'alert-error'}`} role="status">
          {message.text}
        </div>
      ) : null}
      {install ? (
        <button
          type="button"
          className="btn btn-dark btn-sm"
          style={{ alignSelf: 'flex-start' }}
          onClick={async () => {
            await install.prompt();
            setInstall(null);
          }}
        >
          Installer l’application
        </button>
      ) : ios ? (
        <p style={{ margin: 0, fontSize: 13, color: 'var(--muted)' }}>
          Sur iPhone ou iPad : touchez « Partager » puis « Sur l’écran d’accueil » pour installer l’application et recevoir les notifications.
        </p>
      ) : null}
    </div>
  );
}
