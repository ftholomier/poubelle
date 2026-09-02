import * as THREE from '../vendor/three.module.min.js';

/**
 * Poussière d'ambiance.
 *
 * Une seconde nappe de particules, indépendante du dessin, répartie dans tout
 * le volume visible et bien au-delà en profondeur. Elle donne au fond sa
 * matière : les points les plus proches suivent franchement la souris et le
 * défilement, les plus lointains bougent à peine — c'est ce décalage qui crée
 * la sensation d'espace.
 */

const vertexShader = /* glsl */ `
  precision highp float;

  attribute float aSize;
  attribute float aDepth;   // 0 = lointain, 1 = tout proche
  attribute vec3  aTint;
  attribute float aPhase;

  uniform float uTime;
  uniform float uPixelRatio;
  uniform float uSizeScale;
  uniform vec2  uPointer;
  uniform float uPointerForce;
  uniform float uScroll;
  uniform vec3  uBounds;

  varying vec3  vTint;
  varying float vAlpha;

  void main() {
    vec3 pos = position;

    // Dérive lente et continue, propre à chaque grain.
    pos.x += sin(uTime * 0.08 + aPhase * 6.28) * 0.22 * aDepth;
    pos.y += cos(uTime * 0.06 + aPhase * 4.71) * 0.18 * aDepth;

    // Parallaxe : le défilement et la souris déplacent d'autant plus une
    // particule qu'elle est proche de l'objectif.
    pos.y += uScroll * (0.35 + aDepth * 1.5);
    pos.xy += uPointer * uPointerForce * (0.15 + aDepth * 0.85);

    // Le nuage est cyclique : ce qui sort d'un côté rentre de l'autre,
    // la nappe reste donc dense quelle que soit la longueur de la page.
    pos.x = mod(pos.x + uBounds.x, uBounds.x * 2.0) - uBounds.x;
    pos.y = mod(pos.y + uBounds.y, uBounds.y * 2.0) - uBounds.y;

    vec4 mvPosition = modelViewMatrix * vec4(pos, 1.0);
    gl_Position = projectionMatrix * mvPosition;
    gl_PointSize = aSize * uPixelRatio * (uSizeScale / -mvPosition.z);

    vTint = aTint;
    // Les grains lointains restent en retrait : ils suggèrent, ils n'appuient pas.
    vAlpha = 0.15 + aDepth * 0.75;
  }
`;

const fragmentShader = /* glsl */ `
  precision highp float;

  uniform float uOpacity;

  varying vec3  vTint;
  varying float vAlpha;

  void main() {
    vec2 uv = gl_PointCoord - 0.5;
    float d = dot(uv, uv);
    if (d > 0.25) discard;
    // Bord très fondu : la poussière ne doit pas se lire comme des disques nets.
    // Bornes croissantes, puis inversion : smoothstep est indéfini à l'envers.
    float alpha = 1.0 - smoothstep(0.0, 0.25, d);
    gl_FragColor = vec4(vTint, alpha * vAlpha * uOpacity);
  }
`;

export class DustField {
  /**
   * @param {THREE.Scene} scene
   * @param {object}      options
   */
  constructor(scene, { count = 1500, colors = [], opacity = 0.75, reducedMotion = false } = {}) {
    this.scene = scene;
    this.count = count;
    this.reducedMotion = reducedMotion;
    this.bounds = new THREE.Vector3(9, 6, 7);

    const positions = new Float32Array(count * 3);
    const sizes = new Float32Array(count);
    const depths = new Float32Array(count);
    const tints = new Float32Array(count * 3);
    const phases = new Float32Array(count);

    const palette = (colors.length ? colors : ['#7b01f7', '#25d5ff', '#ffffff'])
      .map((hex) => new THREE.Color(hex));

    for (let i = 0; i < count; i++) {
      // La profondeur suit une puissance : beaucoup de grains lointains,
      // quelques-uns au premier plan. C'est ce déséquilibre qui fait le relief.
      const depth = Math.pow(Math.random(), 1.7);

      positions[i * 3] = (Math.random() * 2 - 1) * this.bounds.x;
      positions[i * 3 + 1] = (Math.random() * 2 - 1) * this.bounds.y;
      positions[i * 3 + 2] = -this.bounds.z + depth * (this.bounds.z + 2.5);

      depths[i] = depth;
      sizes[i] = 0.7 + Math.pow(Math.random(), 2.4) * 4.6;
      phases[i] = Math.random();

      // Une teinte dominante, une contrastante, et quelques grains blancs :
      // la même recette que le fond étoilé des sites d'agence.
      const pick = Math.random();
      const color = pick < 0.55 ? palette[0] : pick < 0.85 ? palette[1] : palette[2];
      tints[i * 3] = color.r;
      tints[i * 3 + 1] = color.g;
      tints[i * 3 + 2] = color.b;
    }

    this.geometry = new THREE.BufferGeometry();
    this.geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    this.geometry.setAttribute('aSize', new THREE.BufferAttribute(sizes, 1));
    this.geometry.setAttribute('aDepth', new THREE.BufferAttribute(depths, 1));
    this.geometry.setAttribute('aTint', new THREE.BufferAttribute(tints, 3));
    this.geometry.setAttribute('aPhase', new THREE.BufferAttribute(phases, 1));
    this.geometry.boundingSphere = new THREE.Sphere(new THREE.Vector3(), 24);

    this.uniforms = {
      uTime:         { value: 0 },
      uPixelRatio:   { value: 1 },
      uSizeScale:    { value: 4 },
      uPointer:      { value: new THREE.Vector2(0, 0) },
      uPointerForce: { value: reducedMotion ? 0 : 0.55 },
      uScroll:       { value: 0 },
      uBounds:       { value: this.bounds },
      uOpacity:      { value: opacity },
    };

    this.material = new THREE.ShaderMaterial({
      vertexShader,
      fragmentShader,
      uniforms: this.uniforms,
      transparent: true,
      depthWrite: false,
      blending: THREE.AdditiveBlending,
    });

    this.points = new THREE.Points(this.geometry, this.material);
    // La poussière est un décor : elle passe derrière le dessin principal.
    this.points.renderOrder = -1;
    scene.add(this.points);
  }

  /**
   * @param {number} delta   secondes écoulées
   * @param {THREE.Vector2} pointer  curseur amorti, en unités du monde
   * @param {number} scroll  décalage de défilement, en unités du monde
   */
  update(delta, pointer, scroll) {
    if (!this.reducedMotion) {
      this.uniforms.uTime.value += delta;
    }
    this.uniforms.uPointer.value.copy(pointer);
    this.uniforms.uScroll.value = scroll;
  }

  resize(pixelRatio, sizeScale, halfWidth, halfHeight) {
    this.uniforms.uPixelRatio.value = pixelRatio;
    this.uniforms.uSizeScale.value = sizeScale;
    // La nappe déborde largement du cadre : aucun bord vide ne doit apparaître
    // quand la souris ou le défilement la déplacent.
    this.bounds.set(halfWidth * 2.2, halfHeight * 2.4, this.bounds.z);
  }

  dispose() {
    this.scene.remove(this.points);
    this.geometry.dispose();
    this.material.dispose();
  }
}
