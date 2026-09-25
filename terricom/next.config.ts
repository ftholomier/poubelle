import type { NextConfig } from 'next';

const isProd = process.env.NODE_ENV === 'production';

const securityHeaders = [
  { key: 'X-Content-Type-Options', value: 'nosniff' },
  { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
  { key: 'X-Frame-Options', value: 'SAMEORIGIN' },
  { key: 'Cross-Origin-Opener-Policy', value: 'same-origin' },
  { key: 'Permissions-Policy', value: 'camera=(), microphone=(), payment=(), usb=(), geolocation=(self)' },
  ...(isProd ? [{ key: 'Strict-Transport-Security', value: 'max-age=63072000; includeSubDomains; preload' }] : []),
];

const nextConfig: NextConfig = {
  output: 'standalone',
  // Polices de la charte lues à l'exécution pour les PDF (factures, kit vitrine, courriers, rapports).
  outputFileTracingIncludes: {
    '/**/*': [
      './node_modules/@fontsource/bricolage-grotesque/files/bricolage-grotesque-latin-800-normal.woff',
      './node_modules/@fontsource/instrument-sans/files/instrument-sans-latin-400-normal.woff',
      './node_modules/@fontsource/instrument-sans/files/instrument-sans-latin-600-normal.woff',
      './node_modules/@fontsource/instrument-sans/files/instrument-sans-latin-700-normal.woff',
      './drizzle/**/*',
    ],
  },
  poweredByHeader: false,
  reactStrictMode: true,
  serverExternalPackages: ['sharp', 'pg', 'pg-native', 'nodemailer', 'qrcode', '@anthropic-ai/sdk'],
  experimental: {
    serverActions: {
      bodySizeLimit: '12mb',
    },
  },
  images: {
    remotePatterns: [{ protocol: 'https', hostname: 'images.unsplash.com' }],
  },
  allowedDevOrigins: ['*.localhost', '127.0.0.1'],
  async headers() {
    return [
      { source: '/:path*', headers: securityHeaders },
      {
        source: '/fonts/:path*',
        headers: [{ key: 'Cache-Control', value: 'public, max-age=31536000, immutable' }],
      },
    ];
  },
};

export default nextConfig;
