import * as THREE from '../vendor/three.module.min.js';
import { vertexShader, fragmentShader } from './shaders.js';
import { loadShape } from './shapeLoader.js';
import { DustField } from './DustField.js';

/**
 * Le nuage de particules qui occupe le fond du site.
 *
 * Un seul objet THREE.Points est créé au démarrage, dimensionné sur la forme la
 * plus dense. À chaque changement de section on ne remplace que l'attribut
 * « cible » et on relance le curseur de morphing : aucune allocation, aucun
 * à-coup, quelle que soit la longueur de la page.
 */
export class ParticleField {
  #dustPointer;

  constructor(canvas, theme = {}) {
    this.canvas = canvas;
    this.theme = theme;
    this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    this.pointer = new THREE.Vector2(0, 0);
    this.pointerTarget = new THREE.Vector2(0, 0);
    this.spin = 0;
    this.spinTarget = 0;
    this.dust = null;
    this.scrollDistance = 0;
    // Décalage du dessin dans le cadre, en fractions de demi-écran.
    this.offset = { x: 0, y: 0 };
    this.offsetTarget = { x: 0, y: 0 };
    // Un dessin plat vu de profil disparaît : il tourne dans son plan (axe z),
    // là où une forme volumique tourne sur elle-même (axe y).
    this.spinAxis = 'y';
    this.scrollTilt = 0;

    this.progress = 1;
    this.morphDuration = this.reducedMotion ? 0.001 : 1.5;
    this.morphElapsed = 0;
    this.morphing = false;

    // Valeurs de repli tant que resize() n'a pas mesuré le cadrage réel.
    this.halfHeight = 1.45;
    this.halfWidth = 1.45;

    this.#dustPointer = new THREE.Vector2();
    this.clock = new THREE.Clock();
    this.running = false;
    this.currentId = null;

    this.#initScene();
  }

  #initScene() {
    this.renderer = new THREE.WebGLRenderer({
      canvas: this.canvas,
      antialias: false,
      alpha: true,
      powerPreference: 'high-performance',
    });
    this.renderer.setClearColor(0x000000, 0);
    this.#applySize();

    this.scene = new THREE.Scene();
    this.camera = new THREE.PerspectiveCamera(45, this.#aspect(), 0.1, 100);
    this.camera.position.set(0, 0, 4);

    const colors = this.theme.particles || {};
    this.uniforms = {
      uProgress:      { value: 1 },
      uTime:          { value: 0 },
      uSize:          { value: colors.size || 2.4 },
      uPixelRatio:    { value: this.renderer.getPixelRatio() },
      uSizeScale:     { value: 4 },
      uScatter:       { value: this.reducedMotion ? 0 : 0.34 },
      uTurbulence:    { value: this.reducedMotion ? 0 : 0.011 },
      uPointer:       { value: new THREE.Vector2(999, 999) },
      uPointerForce:  { value: 0.22 },
      uPointerRadius: { value: 0.62 },
      uColorA:        { value: new THREE.Color(colors.colorStart || '#7b01f7') },
      uColorB:        { value: new THREE.Color(colors.colorMid || '#c001f7') },
      uColorC:        { value: new THREE.Color(colors.colorEnd || '#25d5ff') },
      uOpacity:       { value: colors.opacity ?? 0.92 },
    };

    this.material = new THREE.ShaderMaterial({
      vertexShader,
      fragmentShader,
      uniforms: this.uniforms,
      transparent: true,
      depthWrite: false,
      // L'accumulation additive fait rougeoyer les zones denses du dessin.
      blending: THREE.AdditiveBlending,
    });
  }

  /**
   * Ajoute la poussière d'ambiance qui occupe tout l'écran, en plus du dessin.
   *
   * @param {object} options
   */
  enableDust(options = {}) {
    const colors = this.theme.particles || {};
    this.dust = new DustField(this.scene, {
      // Moins de grains sur petit écran : le gain visuel n'y compense pas le coût.
      count: options.count ?? (window.innerWidth < 700 ? 700 : 1600),
      colors: [
        colors.dustA || colors.colorStart || '#7b01f7',
        colors.dustB || colors.colorEnd || '#25d5ff',
        colors.dustC || '#ffffff',
      ],
      opacity: options.opacity ?? 0.7,
      reducedMotion: this.reducedMotion,
    });
    this.resize();

    return this.dust;
  }

  /**
   * Alloue les tampons une fois pour toutes, à la taille de la plus grosse forme.
   * @param {number} capacity nombre maximal de particules
   */
  allocate(capacity) {
    this.capacity = capacity;
    this.from = new Float32Array(capacity * 3);
    this.to = new Float32Array(capacity * 3);
    this.scratch = new Float32Array(capacity * 3);

    const seeds = new Float32Array(capacity * 3);
    for (let i = 0; i < seeds.length; i++) seeds[i] = Math.random();

    this.geometry = new THREE.BufferGeometry();
    this.geometry.setAttribute('position', new THREE.BufferAttribute(this.from, 3));
    this.geometry.setAttribute('aTarget', new THREE.BufferAttribute(this.to, 3));
    this.geometry.setAttribute('aSeed', new THREE.BufferAttribute(seeds, 3));
    // La forme peut sortir du volume de vue standard : on impose la sphère englobante.
    this.geometry.boundingSphere = new THREE.Sphere(new THREE.Vector3(), 4);

    this.points = new THREE.Points(this.geometry, this.material);
    this.scene.add(this.points);
  }

  /**
   * Bascule vers une nouvelle forme.
   * @param {object} descriptor entrée « shape » d'une section
   */
  async morphTo(descriptor) {
    if (!descriptor || descriptor.id === this.currentId) return;
    this.currentId = descriptor.id;

    const cloud = await loadShape(descriptor);
    // Une réponse tardive ne doit pas écraser une section entre-temps devenue active.
    if (descriptor.id !== this.currentId) return;

    // Fige la position réellement affichée : un morphing interrompu repart d'où il en était.
    this.#captureCurrent();
    this.from.set(this.scratch);
    this.#fill(this.to, cloud);

    this.geometry.attributes.position.needsUpdate = true;
    this.geometry.attributes.aTarget.needsUpdate = true;

    this.progress = 0;
    this.morphElapsed = 0;
    this.morphing = true;
    this.spinTarget = descriptor.spin || 0;
    this.spinAxis = descriptor.spinAxis === 'z' ? 'z' : 'y';
    // Une section chargée en texte peut pousser son dessin sur le côté.
    this.offsetTarget.x = Number(descriptor.offsetX) || 0;
    this.offsetTarget.y = Number(descriptor.offsetY) || 0;
  }

  /**
   * Recopie un nuage dans le tampon de destination.
   * Si la forme compte moins de points que la capacité, les particules
   * excédentaires reprennent des positions existantes, très légèrement décalées,
   * pour épaissir le dessin plutôt que d'empiler des doublons exacts.
   */
  #fill(buffer, cloud) {
    const available = Math.floor(cloud.length / 3);
    for (let i = 0; i < this.capacity; i++) {
      const source = (i % available) * 3;
      const jitter = i < available ? 0 : 0.012;
      buffer[i * 3]     = cloud[source]     + (jitter ? (Math.random() - 0.5) * jitter : 0);
      buffer[i * 3 + 1] = cloud[source + 1] + (jitter ? (Math.random() - 0.5) * jitter : 0);
      buffer[i * 3 + 2] = cloud[source + 2] + (jitter ? (Math.random() - 0.5) * jitter : 0);
    }
  }

  /**
   * Reproduit sur le processeur le calcul que fait le nuanceur, afin de connaître
   * la position visible de chaque particule à l'instant présent.
   */
  #captureCurrent() {
    const seeds = this.geometry?.attributes.aSeed.array;
    if (!seeds || !this.morphing) {
      this.scratch.set(this.progress >= 1 ? this.to : this.from);
      return;
    }

    const STAGGER = 0.4;
    for (let i = 0; i < this.capacity; i++) {
      const delay = seeds[i * 3] * STAGGER;
      let t = (this.progress - delay) / (1 - STAGGER);
      t = t < 0 ? 0 : t > 1 ? 1 : t;
      const eased = t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
      for (let axis = 0; axis < 3; axis++) {
        const k = i * 3 + axis;
        this.scratch[k] = this.from[k] + (this.to[k] - this.from[k]) * eased;
      }
    }
  }

  /** Position du curseur, en coordonnées normalisées (-1 à 1). */
  setPointer(nx, ny) {
    this.pointerTarget.set(nx, ny);
  }

  clearPointer() {
    this.pointerTarget.set(999, 999);
  }

  /** Avancement global du défilement, de 0 à 1 : incline légèrement le nuage. */
  setScroll(ratio) {
    this.scrollTilt = ratio;
  }

  /**
   * Distance réellement parcourue, en pixels : la poussière s'en sert pour sa
   * parallaxe, qui doit suivre le geste et non l'avancement dans la page.
   */
  setScrollDistance(pixels) {
    const viewport = Math.max(1, window.innerHeight);
    this.scrollDistance = (pixels / viewport) * this.halfHeight * 0.65;
  }

  start() {
    if (this.running) return;
    this.running = true;
    this.clock.start();
    this.#loop();
  }

  stop() {
    this.running = false;
    if (this.frame) cancelAnimationFrame(this.frame);
  }

  #loop = () => {
    if (!this.running) return;
    this.frame = requestAnimationFrame(this.#loop);

    const delta = Math.min(this.clock.getDelta(), 0.05);
    this.uniforms.uTime.value += delta;

    if (this.morphing) {
      this.morphElapsed += delta;
      this.progress = Math.min(1, this.morphElapsed / this.morphDuration);
      this.uniforms.uProgress.value = this.progress;
      if (this.progress >= 1) {
        this.morphing = false;
        // Le vol est terminé : la destination devient le nouveau point de départ.
        this.from.set(this.to);
        this.geometry.attributes.position.needsUpdate = true;
        this.uniforms.uProgress.value = 1;
      }
    }

    // Le curseur et la rotation rejoignent leur cible par amortissement.
    const ease = 1 - Math.pow(0.001, delta);
    if (this.pointerTarget.x < 900) {
      if (this.pointer.x > 900) this.pointer.copy(this.pointerTarget);
      this.pointer.lerp(this.pointerTarget, ease);
      this.uniforms.uPointer.value.set(
        this.pointer.x * this.halfWidth,
        this.pointer.y * this.halfHeight
      );
    } else {
      this.uniforms.uPointer.value.set(999, 999);
      this.pointer.set(999, 999);
    }

    if (this.dust) {
      // La poussière reçoit le curseur en repère normalisé : son propre nuanceur
      // décide de l'amplitude, différente de celle du dessin principal.
      this.#dustPointer.set(
        this.pointer.x < 900 ? this.pointer.x : 0,
        this.pointer.x < 900 ? this.pointer.y : 0
      );
      this.dust.update(delta, this.#dustPointer, this.scrollDistance);
    }

    this.spin += (this.spinTarget - this.spin) * ease;
    if (this.points) {
      const rotation = this.points.rotation;
      rotation[this.spinAxis] += this.spin * delta;

      // Inclinaison douce pilotée par le curseur et le défilement.
      const hasPointer = this.pointer.x < 900;
      const targetX = hasPointer ? -this.pointer.y * 0.12 : 0;
      const targetY = hasPointer ? this.pointer.x * 0.12 : 0;

      rotation.x += (targetX + this.scrollTilt * 0.25 - rotation.x) * ease * 0.5;
      // La rotation permanente et l'inclinaison ne se disputent pas le même axe.
      if (this.spinAxis !== 'y' || !this.spinTarget) {
        rotation.y += (targetY - rotation.y) * ease * 0.5;
      }

      // Le décalage rejoint sa cible en douceur : le dessin glisse d'un côté
      // à l'autre au fil des sections au lieu de sauter.
      this.offset.x += (this.offsetTarget.x - this.offset.x) * ease * 0.35;
      this.offset.y += (this.offsetTarget.y - this.offset.y) * ease * 0.35;
      this.points.position.x = this.offset.x * this.halfWidth;
      this.points.position.y = this.offset.y * this.halfHeight - this.scrollTilt * 0.18;
    }

    this.renderer.render(this.scene, this.camera);
  };

  resize() {
    this.#applySize();
    this.camera.aspect = this.#aspect();

    // Les formes tiennent dans une sphère de rayon 1. On recule la caméra juste
    // assez pour que cette sphère occupe la marge voulue, en hauteur comme en
    // largeur : le dessin reste entier de l'écran large au téléphone.
    const margin = 1.45;
    this.halfHeight = Math.max(margin, margin / this.camera.aspect);
    this.halfWidth = this.halfHeight * this.camera.aspect;
    this.camera.position.z = this.halfHeight / Math.tan((this.camera.fov * Math.PI) / 360);
    this.camera.updateProjectionMatrix();

    this.uniforms.uPixelRatio.value = this.renderer.getPixelRatio();
    // Le rayon d'influence du curseur suit le cadrage, pas la résolution.
    this.uniforms.uPointerRadius.value = this.halfHeight * 0.45;
    this.uniforms.uPointerForce.value = this.halfHeight * 0.16;
    this.uniforms.uSizeScale.value = this.camera.position.z * 1.05;

    this.dust?.resize(
      this.renderer.getPixelRatio(),
      this.camera.position.z * 1.05,
      this.halfWidth,
      this.halfHeight
    );
  }

  #applySize() {
    const width = this.canvas.clientWidth || window.innerWidth;
    const height = this.canvas.clientHeight || window.innerHeight;
    // Au-delà de 2, la densité de pixels coûte cher sans gain visible.
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    this.renderer.setSize(width, height, false);
  }

  #aspect() {
    const width = this.canvas.clientWidth || window.innerWidth;
    const height = this.canvas.clientHeight || window.innerHeight;
    return width / Math.max(1, height);
  }

  dispose() {
    this.stop();
    this.dust?.dispose();
    this.geometry?.dispose();
    this.material.dispose();
    this.renderer.dispose();
  }
}

/** Vrai si le navigateur sait ouvrir un contexte WebGL. */
export function supportsWebGL() {
  try {
    const canvas = document.createElement('canvas');
    return Boolean(
      window.WebGLRenderingContext &&
      (canvas.getContext('webgl2') || canvas.getContext('webgl'))
    );
  } catch {
    return false;
  }
}
