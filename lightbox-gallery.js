/**
 * Galería / lightbox con navegación.
 * Funciona sobre cualquier página que tenga:
 *   <div class="lightbox" id="lightbox">
 *     <button class="lightbox-close" id="lightboxClose">×</button>
 *     <button class="lightbox-arrow lightbox-prev" id="lightboxPrev">‹</button>
 *     <img id="lightboxImg" src="" alt="" />
 *     <button class="lightbox-arrow lightbox-next" id="lightboxNext">›</button>
 *     <div class="lightbox-counter" id="lightboxCounter"></div>
 *   </div>
 * y varias imágenes con la clase .gallery-item img
 */
(function () {
  const lightbox   = document.getElementById('lightbox');
  const lightboxImg = document.getElementById('lightboxImg');
  const btnClose   = document.getElementById('lightboxClose');
  const btnPrev    = document.getElementById('lightboxPrev');
  const btnNext    = document.getElementById('lightboxNext');
  const counter    = document.getElementById('lightboxCounter');

  if (!lightbox || !lightboxImg) return; // esta página no tiene lightbox

  const items = Array.from(document.querySelectorAll('.gallery-item img'));
  let currentIndex = 0;

  function show(index) {
    if (!items.length) return;
    currentIndex = (index + items.length) % items.length; // wraparound en ambas direcciones
    const img = items[currentIndex];
    lightboxImg.src = img.src;
    lightboxImg.alt = img.alt || '';
    if (counter) counter.textContent = (currentIndex + 1) + ' / ' + items.length;
  }

  function open(index) {
    show(index);
    lightbox.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function close() {
    lightbox.classList.remove('open');
    document.body.style.overflow = '';
  }

  function next() { show(currentIndex + 1); }
  function prev() { show(currentIndex - 1); }

  items.forEach((img, i) => {
    img.addEventListener('click', () => open(i));
  });

  if (btnClose) btnClose.addEventListener('click', close);
  if (btnNext) btnNext.addEventListener('click', (e) => { e.stopPropagation(); next(); });
  if (btnPrev) btnPrev.addEventListener('click', (e) => { e.stopPropagation(); prev(); });

  // Clic en el fondo oscuro (fuera de la imagen) cierra, igual que antes.
  lightbox.addEventListener('click', (e) => { if (e.target === lightbox) close(); });

  // Navegación por teclado: flechas y Escape.
  document.addEventListener('keydown', (e) => {
    if (!lightbox.classList.contains('open')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowRight') next();
    if (e.key === 'ArrowLeft') prev();
  });

  // Swipe táctil (estilo galería móvil): desliza a la izquierda -> siguiente, a la derecha -> anterior.
  let touchStartX = null;
  lightbox.addEventListener('touchstart', (e) => {
    touchStartX = e.changedTouches[0].clientX;
  }, { passive: true });

  lightbox.addEventListener('touchend', (e) => {
    if (touchStartX === null) return;
    const deltaX = e.changedTouches[0].clientX - touchStartX;
    const THRESHOLD = 40; // px mínimos para considerarlo un swipe intencional
    if (Math.abs(deltaX) > THRESHOLD) {
      if (deltaX < 0) next(); else prev();
    }
    touchStartX = null;
  }, { passive: true });
})();
