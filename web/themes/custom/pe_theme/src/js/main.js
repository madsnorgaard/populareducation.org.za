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

  // Load more: on listing pages the pager becomes a button that fetches
  // the next page and appends its cards. No JS = the normal pager.
  document.querySelectorAll('nav.pager').forEach((pager) => {
    const container = pager.parentElement;
    const grid = container && container.querySelector('.poster-grid');
    const nextLink = () => pager.querySelector('.pager__item--next a');
    if (!grid || !nextLink()) {
      return;
    }
    pager.classList.add('pager--replaced');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'load-more';
    button.textContent = 'Load more';
    pager.insertAdjacentElement('afterend', button);
    // Scroll-to-load: when the button nears the viewport, press it.
    const io = new IntersectionObserver((entries) => {
      if (entries.some((e) => e.isIntersecting) && !button.disabled) {
        button.click();
      }
    }, { rootMargin: '400px 0px' });
    io.observe(button);
    button.addEventListener('click', async () => {
      const next = nextLink();
      if (!next) {
        return;
      }
      button.disabled = true;
      button.textContent = 'Loading…';
      try {
        const html = await (await fetch(next.href)).text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const newGrid = doc.querySelector('.poster-grid');
        if (newGrid) {
          grid.append(...newGrid.children);
        }
        const newPager = doc.querySelector('nav.pager');
        pager.innerHTML = newPager ? newPager.innerHTML : '';
        button.disabled = false;
        button.textContent = 'Load more';
        if (!nextLink()) {
          button.remove();
        }
      }
      catch (e) {
        // Network hiccup: fall back to the real pager.
        pager.classList.remove('pager--replaced');
        button.remove();
      }
    });
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
