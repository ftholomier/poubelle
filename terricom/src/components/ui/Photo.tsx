'use client';

import { useEffect, useRef, useState, type CSSProperties } from 'react';

/**
 * Image avec repli : sans photo (ou si elle ne se charge pas), on affiche un aplat de la
 * couleur de la catégorie et l'initiale — jamais d'image générique de banque d'images.
 */
export function Photo({
  src,
  alt = '',
  color = '#1F6B52',
  label,
  style,
  className,
  eager,
  sizes,
  srcSet,
}: {
  src?: string | null;
  alt?: string;
  color?: string;
  label?: string;
  style?: CSSProperties;
  className?: string;
  eager?: boolean;
  sizes?: string;
  srcSet?: string;
}) {
  const [failed, setFailed] = useState(false);
  const ref = useRef<HTMLImageElement>(null);

  // Une image en échec avant l'hydratation ne déclenche plus onError : on vérifie au montage.
  useEffect(() => {
    const img = ref.current;
    if (img && img.complete && img.naturalWidth === 0) setFailed(true);
  }, [src]);

  if (!src || failed) {
    return (
      <div
        className={`img-fallback ${className ?? ''}`}
        style={{ background: `linear-gradient(135deg, ${color}, color-mix(in srgb, ${color} 70%, #14201B))`, ...style }}
        role={alt ? 'img' : undefined}
        aria-label={alt || undefined}
      >
        <span aria-hidden="true">{(label ?? alt).trim().charAt(0).toUpperCase()}</span>
      </div>
    );
  }
  return (
    <img
      ref={ref}
      src={src}
      srcSet={srcSet}
      sizes={sizes}
      alt={alt}
      loading={eager ? 'eager' : 'lazy'}
      decoding="async"
      className={`cover ${className ?? ''}`}
      style={style}
      onError={() => setFailed(true)}
    />
  );
}
