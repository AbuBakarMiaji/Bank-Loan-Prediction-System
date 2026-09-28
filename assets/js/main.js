// Ledger — small progressive-enhancement behaviors (no framework needed).

document.addEventListener('DOMContentLoaded', () => {
  // -------- Fixed navbar scroll shadow --------
  const siteHeader = document.querySelector('.site-header');
  if (siteHeader) {
    const onScroll = () => {
      siteHeader.classList.toggle('scrolled', window.scrollY > 10);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll(); // run once on load in case page is already scrolled
  }

  // -------- Home page full-screen image slider --------
  const slider = document.getElementById('home-slider');
  if (slider) {
    const slides = Array.from(slider.querySelectorAll('.slide'));
    const dots = Array.from(slider.querySelectorAll('.slider-dots button'));
    const prevBtn = slider.querySelector('.slider-arrow.prev');
    const nextBtn = slider.querySelector('.slider-arrow.next');
    const progressBar = document.getElementById('slider-progress-bar');
    let current = 0;
    let timer = null;
    let animFrame = null;
    let startTime = null;
    const INTERVAL = 6000;

    function resetProgress() {
      if (progressBar) {
        progressBar.style.transition = 'none';
        progressBar.style.width = '0%';
      }
    }

    function animateProgress() {
      if (!progressBar) return;
      startTime = Date.now();

      function update() {
        const elapsed = Date.now() - startTime;
        const pct = Math.min((elapsed / INTERVAL) * 100, 100);
        progressBar.style.width = `${pct}%`;
        if (elapsed < INTERVAL) {
          animFrame = requestAnimationFrame(update);
        }
      }
      resetProgress();
      requestAnimationFrame(update);
    }

    function goTo(index) {
      slides[current].classList.remove('is-active');
      slides[current].setAttribute('aria-hidden', 'true');
      if (dots[current]) {
        dots[current].classList.remove('active');
        dots[current].setAttribute('aria-selected', 'false');
      }

      current = (index + slides.length) % slides.length;

      slides[current].classList.add('is-active');
      slides[current].setAttribute('aria-hidden', 'false');
      if (dots[current]) {
        dots[current].classList.add('active');
        dots[current].setAttribute('aria-selected', 'true');
      }
    }

    function next() { goTo(current + 1); }
    function prev() { goTo(current - 1); }

    function startAuto() {
      stopAuto();
      animateProgress();
      timer = setInterval(() => {
        next();
        animateProgress();
      }, INTERVAL);
    }

    function stopAuto() {
      if (timer) clearInterval(timer);
      if (animFrame) cancelAnimationFrame(animFrame);
      resetProgress();
    }

    nextBtn?.addEventListener('click', () => { next(); startAuto(); });
    prevBtn?.addEventListener('click', () => { prev(); startAuto(); });
    dots.forEach((dot, i) => dot.addEventListener('click', () => { goTo(i); startAuto(); }));

    slider.addEventListener('mouseenter', stopAuto);
    slider.addEventListener('mouseleave', startAuto);

    slider.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight') { next(); startAuto(); }
      if (e.key === 'ArrowLeft') { prev(); startAuto(); }
    });

    // Touch swipe support
    let touchStartX = null;
    slider.addEventListener('touchstart', (e) => { touchStartX = e.touches[0].clientX; }, { passive: true });
    slider.addEventListener('touchend', (e) => {
      if (touchStartX === null) return;
      const dx = e.changedTouches[0].clientX - touchStartX;
      if (Math.abs(dx) > 40) { dx < 0 ? next() : prev(); startAuto(); }
      touchStartX = null;
    });

    startAuto();
  }

  // Animate confidence bar fill-in on the prediction result panel.
  document.querySelectorAll('.confidence-bar > span').forEach((bar) => {
    const target = bar.style.width;
    bar.style.width = '0%';
    requestAnimationFrame(() => {
      bar.style.transition = 'width .8s ease';
      requestAnimationFrame(() => { bar.style.width = target; });
    });
  });

  // Inline "passwords match" feedback on the registration form.
  const pw = document.getElementById('password');
  const pw2 = document.getElementById('password_confirm');
  if (pw && pw2) {
    const check = () => {
      pw2.setCustomValidity(pw2.value && pw2.value !== pw.value ? 'Passwords do not match' : '');
    };
    pw.addEventListener('input', check);
    pw2.addEventListener('input', check);
  }

  // Confirm before leaving the loan application form with unsaved input.
  const applyForm = document.querySelector('.main-content form');
  if (applyForm && document.getElementById('loan_amount')) {
    let submitted = false;
    applyForm.addEventListener('submit', () => { submitted = true; });
    window.addEventListener('beforeunload', (e) => {
      if (!submitted && applyForm.querySelector('input[type=number]').value) {
        e.preventDefault();
        e.returnValue = '';
      }
    });
  }
});
