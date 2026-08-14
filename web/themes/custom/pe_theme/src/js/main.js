// pe_theme behaviour: nothing but progressive niceties. The site must work
// fully with JavaScript disabled.

// Mark the current section in the masthead nav.
document.addEventListener('DOMContentLoaded', () => {
  const path = window.location.pathname;
  document.querySelectorAll('.masthead__nav a').forEach((a) => {
    const href = a.getAttribute('href');
    if (href && href !== '/' && path.startsWith(href)) {
      a.classList.add('is-active');
    }
  });

  // The ACTION dancers step in and sway with the scroll. Pure enhancement:
  // static image without JS, and stays still under prefers-reduced-motion.
  const art = document.querySelector('.manifesto__art img');
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  if (art && !reduced.matches) {
    let ticking = false;
    const dance = () => {
      ticking = false;
      const rect = art.getBoundingClientRect();
      const vh = window.innerHeight || 1;
      // 0 when the art enters from the bottom, 1 when it leaves at the top.
      const progress = Math.min(1, Math.max(0,
        (vh - rect.top) / (vh + rect.height)));
      // Step in from the right over the first third, then sway gently.
      const enter = Math.min(1, progress * 3);
      const x = (1 - enter) * 40;
      const sway = Math.sin(progress * Math.PI * 2) * 2.5;
      art.style.setProperty('--dance-x', `${x}%`);
      art.style.setProperty('--dance-r', `${sway}deg`);
    };
    const onScroll = () => {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(dance);
      }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
    dance();
  }
});
