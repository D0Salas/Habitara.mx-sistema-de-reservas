
const nav = document.getElementById('mainNav');

window.addEventListener('scroll', () => {
  nav.classList.toggle('scrolled', window.scrollY > 40);
});

const menuBtn    = document.getElementById('menuBtn');
const mobileMenu = document.getElementById('mobileMenu');
const closeBtn   = document.getElementById('closeMenu');

menuBtn.addEventListener('click', () => {
  mobileMenu.classList.add('open');
  document.body.style.overflow = 'hidden'; // evita scroll de fondo
});

closeBtn.addEventListener('click', closeMobile);

mobileMenu.querySelectorAll('a').forEach(link => {
  link.addEventListener('click', closeMobile);
});

function closeMobile() {
  mobileMenu.classList.remove('open');
  document.body.style.overflow = '';
}

const fadeObserver = new IntersectionObserver(
  (entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        fadeObserver.unobserve(entry.target); // solo anima una vez
      }
    });
  },
  { threshold: 0.12 }
);

document.querySelectorAll('.fade-in').forEach(el => fadeObserver.observe(el));

const INTERVAL_MS = 6000;

function initSlideshow(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

  const slides = container.querySelectorAll('.dest-slide');
  if (slides.length < 2) return;

  let current = 0;

  setInterval(() => {
    slides[current].classList.remove('active');
    current = (current + 1) % slides.length;
    slides[current].classList.add('active');
  }, INTERVAL_MS);
}

initSlideshow('slideshow-tab');
initSlideshow('slideshow-qr');

const contactForm = document.querySelector('.contact-form');

if (contactForm) {
  contactForm.addEventListener('submit', handleSubmit);
}

function handleSubmit(e) {
  e.preventDefault();

  const btn = e.target.querySelector('.btn-submit');
  const originalText = btn.textContent;

  btn.textContent = 'Enviando...';
  btn.disabled = true;

  setTimeout(() => {

    btn.textContent = '¡Mensaje enviado!';

    setTimeout(() => {
      btn.textContent = originalText;
      btn.disabled = false;
      e.target.reset();
    }, 3000);

  }, 1200);
}
