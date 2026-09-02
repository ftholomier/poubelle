/**
 * Programmes GLSL du nuage de particules.
 *
 * Le morphing se joue entièrement sur la carte graphique : chaque particule
 * connaît son point de départ et son point d'arrivée, et un curseur unique
 * (uProgress) fait voyager les 16 000 points d'un dessin à l'autre.
 */

export const vertexShader = /* glsl */ `
  precision highp float;

  attribute vec3 aTarget;   // position visée par la particule
  attribute vec3 aSeed;     // aléa propre à la particule, figé une fois pour toutes

  uniform float uProgress;      // avancement du morphing, de 0 à 1
  uniform float uTime;
  uniform float uSize;
  uniform float uPixelRatio;
  uniform float uSizeScale;   // compense la distance de la caméra
  uniform float uScatter;       // ampleur de la dispersion à mi-parcours
  uniform float uTurbulence;    // frémissement permanent
  uniform vec2  uPointer;       // curseur, en coordonnées du monde
  uniform float uPointerForce;
  uniform float uPointerRadius;

  varying float vMix;
  varying float vFade;

  const float STAGGER = 0.4;    // part du temps consacrée au décalage entre particules

  // Adoucit le départ et l'arrivée : la particule accélère puis se repose.
  float easeInOutCubic(float t) {
    return t < 0.5 ? 4.0 * t * t * t : 1.0 - pow(-2.0 * t + 2.0, 3.0) / 2.0;
  }

  void main() {
    // Chaque particule démarre avec un léger retard : le nuage se déplie au lieu de sauter.
    float delay = aSeed.x * STAGGER;
    float t = clamp((uProgress - delay) / (1.0 - STAGGER), 0.0, 1.0);
    float eased = easeInOutCubic(t);

    vec3 pos = mix(position, aTarget, eased);

    // En plein vol, les particules s'écartent de la ligne droite : le trajet respire.
    float flight = sin(eased * 3.14159265);
    vec3 drift = normalize(aSeed - 0.5 + 0.0001);
    pos += drift * flight * uScatter * (0.4 + aSeed.z);

    // Frémissement permanent, pour que la forme au repos reste vivante.
    pos += vec3(
      sin(uTime * 0.7 + aSeed.x * 24.0),
      cos(uTime * 0.6 + aSeed.y * 24.0),
      sin(uTime * 0.5 + aSeed.z * 24.0)
    ) * uTurbulence;

    // Le curseur repousse les particules proches, avec une décroissance douce.
    vec2 away = pos.xy - uPointer;
    float dist = length(away);
    if (dist < uPointerRadius) {
      float push = (1.0 - dist / uPointerRadius);
      pos.xy += normalize(away + 0.0001) * push * push * uPointerForce;
    }

    vec4 mvPosition = modelViewMatrix * vec4(pos, 1.0);
    gl_Position = projectionMatrix * mvPosition;

    // Taille perspective : les particules lointaines rapetissent naturellement.
    gl_PointSize = uSize * uPixelRatio * (0.55 + aSeed.y * 0.75) * (uSizeScale / -mvPosition.z);

    vMix = clamp(aSeed.z * 0.6 + length(pos.xy) * 0.35 + flight * 0.3, 0.0, 1.0);
    vFade = 0.45 + 0.55 * (1.0 - flight * 0.5);
  }
`;

export const fragmentShader = /* glsl */ `
  precision highp float;

  uniform vec3  uColorA;
  uniform vec3  uColorB;
  uniform vec3  uColorC;
  uniform float uOpacity;

  varying float vMix;
  varying float vFade;

  void main() {
    // Découpe le carré du point en disque, avec un bord fondu.
    vec2 uv = gl_PointCoord - 0.5;
    float d = dot(uv, uv);
    if (d > 0.25) discard;
    // Les bornes de smoothstep doivent aller en croissant : la spécification
    // GLSL déclare le résultat indéfini dans le cas contraire, et les pilotes
    // qui traduisent vers Direct3D en profitent pour rendre n'importe quoi.
    float alpha = 1.0 - smoothstep(0.02, 0.25, d);

    // Dégradé sur trois teintes : violet, magenta, cyan.
    vec3 color = vMix < 0.5
      ? mix(uColorA, uColorB, vMix * 2.0)
      : mix(uColorB, uColorC, (vMix - 0.5) * 2.0);

    gl_FragColor = vec4(color, alpha * uOpacity * vFade);
  }
`;
