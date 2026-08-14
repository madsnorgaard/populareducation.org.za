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
      const scrolled = window.scrollY || 0;
      // The dancers rise and rock as you scroll: a visible kick, not a
      // tremor. One full sway per ~700px of scroll, drifting upward.
      const y = Math.min(120, scrolled * 0.22);
      const sway = Math.sin(scrolled / 110) * 6;
      art.style.setProperty('--dance-x', '0%');
      art.style.setProperty('--dance-y', `${-y}px`);
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
