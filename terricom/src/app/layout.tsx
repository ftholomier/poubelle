import '@fontsource-variable/bricolage-grotesque/opsz.css';
import '@fontsource-variable/instrument-sans';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import './globals.css';
import type { Metadata, Viewport } from 'next';
import type { ReactNode } from 'react';
import { PwaRegister } from '@/components/pwa/PwaRegister';
import { env } from '@/server/env';

export const metadata: Metadata = {
  metadataBase: new URL(env.APP_URL),
  title: { default: 'terricom — Le territoire, en vitrine.', template: '%s · terricom' },
  description:
    "La plateforme d'animation et de valorisation économique du territoire : une vitrine numérique pour chaque commerce, artisan et producteur, offerte par la collectivité.",
  applicationName: 'terricom',
  formatDetection: { telephone: false },
  manifest: '/manifest.webmanifest',
  appleWebApp: { capable: true, title: 'terricom', statusBarStyle: 'default' },
  icons: { apple: '/api/pwa/icon?size=180' },
};

export const viewport: Viewport = {
  themeColor: '#14201B',
  width: 'device-width',
  initialScale: 1,
};

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="fr">
      <body>
        {children}
        <PwaRegister />
      </body>
    </html>
  );
}
