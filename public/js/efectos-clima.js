/**
 * Efectos de Clima con Canvas API
 *
 * Implementa animaciones de clima usando Canvas para efectos visuales inmersivos.
 */

class ClimaKomorebi {
  constructor(canvasId) {
    this.canvas = document.getElementById(canvasId);
    if (!this.canvas) {
      console.warn(`Canvas "${canvasId}" no encontrado`);
      return;
    }

    this.ctx = this.canvas.getContext('2d');
    this.particles = [];
    this.animationId = null;
    this.isActive = false;

    // Configuración por defecto
    this.config = {
      particleCount: 100,
      particleSize: 2,
      particleSpeed: 1,
      particleOpacity: 0.6
    };

    this.setupCanvas();
    this.setupResponsive();
  }

  /**
   * Configura el canvas al tamaño del contenedor
   */
  setupCanvas() {
    const container = this.canvas.parentElement;
    this.canvas.width = container.offsetWidth;
    this.canvas.height = container.offsetHeight;
  }

  /**
   * Maneja el redimensionamiento responsive
   */
  setupResponsive() {
    let resizeTimeout;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(() => {
        this.setupCanvas();
        this.iniciarAnimacion(this.currentEffect);
      }, 250);
    });
  }

  /**
   * Inicia animación según tipo de clima
   * @param {string} effect - Tipo de efecto: 'rain', 'snow', 'fog', 'clouds', 'clear', 'thunderstorm'
   */
  iniciarAnimacion(effect) {
    this.detener();
    this.currentEffect = effect;
    this.particles = [];

    // Respetar prefers-reduced-motion
    if (globalThis.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      this.renderStaticEffect(effect);
      return;
    }

    switch (effect) {
      case 'rain':
        this.animarLluvia();
        break;
      case 'snow':
        this.animarNieve();
        break;
      case 'fog':
        this.animarNiebla();
        break;
      case 'clouds':
        this.animarNubes();
        break;
      case 'thunderstorm':
        this.animarTormenta();
        break;
      case 'clear':
      default:
        this.animarDespejado();
        break;
    }
  }

  /**
   * Efecto estático para usuarios con prefers-reduced-motion
   */
  renderStaticEffect(effect) {
    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

    const gradient = this.ctx.createLinearGradient(0, 0, 0, this.canvas.height);
    switch (effect) {
      case 'rain':
        gradient.addColorStop(0, 'rgba(70, 130, 180, 0.1)');
        gradient.addColorStop(1, 'rgba(95, 158, 160, 0.2)');
        break;
      case 'snow':
        gradient.addColorStop(0, 'rgba(240, 248, 255, 0.15)');
        gradient.addColorStop(1, 'rgba(224, 255, 255, 0.1)');
        break;
      case 'fog':
        gradient.addColorStop(0, 'rgba(211, 211, 211, 0.3)');
        gradient.addColorStop(1, 'rgba(192, 192, 192, 0.2)');
        break;
      default:
        gradient.addColorStop(0, 'rgba(255, 215, 0, 0.05)');
        gradient.addColorStop(1, 'rgba(255, 165, 0, 0.1)');
    }

    this.ctx.fillStyle = gradient;
    this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
  }

  /**
   * Animación de lluvia
   */
  animarLluvia() {
    this.config.particleCount = 150;
    this.config.particleSpeed = 8;

    // Crear gotas
    for (let i = 0; i < this.config.particleCount; i++) {
      this.particles.push({
        x: Math.random() * this.canvas.width,
        y: Math.random() * this.canvas.height,
        length: Math.random() * 20 + 10,
        speed: Math.random() * 3 + this.config.particleSpeed,
        opacity: Math.random() * 0.5 + 0.3
      });
    }

    this.isActive = true;
    this.animateLluviaFrame();
  }

  animateLluviaFrame() {
    if (!this.isActive) return;

    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    this.ctx.strokeStyle = 'rgba(174, 194, 224, 0.5)';
    this.ctx.lineWidth = 1;

    this.particles.forEach(drop => {
      this.ctx.beginPath();
      this.ctx.globalAlpha = drop.opacity;
      this.ctx.moveTo(drop.x, drop.y);
      this.ctx.lineTo(drop.x, drop.y + drop.length);
      this.ctx.stroke();

      // Mover gota
      drop.y += drop.speed;

      // Reiniciar si sale del canvas
      if (drop.y > this.canvas.height) {
        drop.y = -drop.length;
        drop.x = Math.random() * this.canvas.width;
      }
    });

    this.ctx.globalAlpha = 1;
    this.animationId = requestAnimationFrame(() => this.animateLluviaFrame());
  }

  /**
   * Animación de nieve
   */
  animarNieve() {
    this.config.particleCount = 100;
    this.config.particleSpeed = 1;

    for (let i = 0; i < this.config.particleCount; i++) {
      this.particles.push({
        x: Math.random() * this.canvas.width,
        y: Math.random() * this.canvas.height,
        radius: Math.random() * 3 + 1,
        speed: Math.random() * 0.5 + 0.5,
        drift: Math.random() * 0.5 - 0.25,
        opacity: Math.random() * 0.6 + 0.3
      });
    }

    this.isActive = true;
    this.animateNieveFrame();
  }

  animateNieveFrame() {
    if (!this.isActive) return;

    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    this.ctx.fillStyle = 'rgba(240, 248, 255, 0.8)';

    this.particles.forEach(flake => {
      this.ctx.globalAlpha = flake.opacity;
      this.ctx.beginPath();
      this.ctx.arc(flake.x, flake.y, flake.radius, 0, Math.PI * 2);
      this.ctx.fill();

      // Mover copo
      flake.y += flake.speed;
      flake.x += flake.drift;

      // Reiniciar
      if (flake.y > this.canvas.height) {
        flake.y = -flake.radius;
        flake.x = Math.random() * this.canvas.width;
      }
    });

    this.ctx.globalAlpha = 1;
    this.animationId = requestAnimationFrame(() => this.animateNieveFrame());
  }

  /**
   * Animación de niebla
   */
  animarNiebla() {
    this.config.particleCount = 8;

    for (let i = 0; i < this.config.particleCount; i++) {
      this.particles.push({
        x: Math.random() * this.canvas.width,
        y: Math.random() * this.canvas.height,
        radius: Math.random() * 100 + 80,
        speed: Math.random() * 0.3 + 0.1,
        opacity: Math.random() * 0.15 + 0.05
      });
    }

    this.isActive = true;
    this.animateNieblaFrame();
  }

  animateNieblaFrame() {
    if (!this.isActive) return;

    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

    this.particles.forEach(fog => {
      const gradient = this.ctx.createRadialGradient(
        fog.x, fog.y, 0,
        fog.x, fog.y, fog.radius
      );
      gradient.addColorStop(0, `rgba(211, 211, 211, ${fog.opacity})`);
      gradient.addColorStop(1, 'rgba(211, 211, 211, 0)');

      this.ctx.fillStyle = gradient;
      this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

      // Mover niebla
      fog.x += fog.speed;
      if (fog.x - fog.radius > this.canvas.width) {
        fog.x = -fog.radius;
      }
    });

    this.animationId = requestAnimationFrame(() => this.animateNieblaFrame());
  }

  /**
   * Animación de nubes
   */
  animarNubes() {
    this.config.particleCount = 5;

    for (let i = 0; i < this.config.particleCount; i++) {
      this.particles.push({
        x: Math.random() * this.canvas.width,
        y: Math.random() * (this.canvas.height * 0.5),
        width: Math.random() * 150 + 100,
        height: Math.random() * 50 + 30,
        speed: Math.random() * 0.2 + 0.1,
        opacity: Math.random() * 0.3 + 0.2
      });
    }

    this.isActive = true;
    this.animateNubesFrame();
  }

  animateNubesFrame() {
    if (!this.isActive) return;

    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

    this.particles.forEach(cloud => {
      this.ctx.globalAlpha = cloud.opacity;
      this.ctx.fillStyle = '#B0C4DE';

      // Dibujar nube (3 círculos)
      this.ctx.beginPath();
      this.ctx.arc(cloud.x, cloud.y, cloud.height * 0.5, 0, Math.PI * 2);
      this.ctx.arc(cloud.x + cloud.width * 0.3, cloud.y - cloud.height * 0.2, cloud.height * 0.6, 0, Math.PI * 2);
      this.ctx.arc(cloud.x + cloud.width * 0.6, cloud.y, cloud.height * 0.5, 0, Math.PI * 2);
      this.ctx.fill();

      // Mover nube
      cloud.x += cloud.speed;
      if (cloud.x - cloud.width > this.canvas.width) {
        cloud.x = -cloud.width;
      }
    });

    this.ctx.globalAlpha = 1;
    this.animationId = requestAnimationFrame(() => this.animateNubesFrame());
  }

  /**
   * Animación de tormenta
   */
  animarTormenta() {
    this.animarLluvia();
    this.config.particleSpeed = 12;

    // Agregar rayos ocasionales
    this.lightningInterval = setInterval(() => {
      if (Math.random() > 0.7) {
        this.renderLightning();
      }
    }, 2000);
  }

  renderLightning() {
    const x = Math.random() * this.canvas.width;

    this.ctx.strokeStyle = 'rgba(255, 255, 255, 0.9)';
    this.ctx.lineWidth = 2;
    this.ctx.beginPath();
    this.ctx.moveTo(x, 0);

    let currentX = x;
    let currentY = 0;

    while (currentY < this.canvas.height) {
      currentX += (Math.random() - 0.5) * 50;
      currentY += Math.random() * 50 + 20;
      this.ctx.lineTo(currentX, currentY);
    }

    this.ctx.stroke();

    // Flash breve
    setTimeout(() => {
      if (this.isActive) {
        this.ctx.fillStyle = 'rgba(255, 255, 255, 0.1)';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
      }
    }, 50);
  }

  /**
   * Animación de día despejado (rayos de sol)
   */
  animarDespejado() {
    this.config.particleCount = 50;

    for (let i = 0; i < this.config.particleCount; i++) {
      this.particles.push({
        x: Math.random() * this.canvas.width,
        y: Math.random() * this.canvas.height,
        radius: Math.random() * 1.5 + 0.5,
        speed: Math.random() * 0.1 + 0.05,
        opacity: Math.random() * 0.3 + 0.1
      });
    }

    this.isActive = true;
    this.animateDespejadoFrame();
  }

  animateDespejadoFrame() {
    if (!this.isActive) return;

    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

    // Gradiente sutil
    const gradient = this.ctx.createLinearGradient(0, 0, this.canvas.width, this.canvas.height);
    gradient.addColorStop(0, 'rgba(255, 215, 0, 0.03)');
    gradient.addColorStop(1, 'rgba(255, 165, 0, 0.05)');
    this.ctx.fillStyle = gradient;
    this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

    // Partículas de luz
    this.ctx.fillStyle = 'rgba(255, 215, 0, 0.3)';
    this.particles.forEach(particle => {
      this.ctx.globalAlpha = particle.opacity;
      this.ctx.beginPath();
      this.ctx.arc(particle.x, particle.y, particle.radius, 0, Math.PI * 2);
      this.ctx.fill();

      particle.y += particle.speed;
      particle.x += particle.speed * 0.5;

      if (particle.y > this.canvas.height) {
        particle.y = 0;
        particle.x = Math.random() * this.canvas.width;
      }
    });

    this.ctx.globalAlpha = 1;
    this.animationId = requestAnimationFrame(() => this.animateDespejadoFrame());
  }

  /**
   * Detiene la animación actual
   */
  detener() {
    this.isActive = false;

    if (this.animationId) {
      cancelAnimationFrame(this.animationId);
      this.animationId = null;
    }

    if (this.lightningInterval) {
      clearInterval(this.lightningInterval);
      this.lightningInterval = null;
    }

    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
  }

  /**
   * Destruye la instancia y limpia recursos
   */
  destruir() {
    this.detener();
    window.removeEventListener('resize', this.setupResponsive);
    this.canvas = null;
    this.ctx = null;
  }
}

// Exportar para uso global
if (typeof module !== 'undefined' && module.exports) {
  module.exports = ClimaKomorebi;
}
