// pe_theme behaviour: nothing but progressive niceties. The site must work
// fully with JavaScript disabled.

// Scroll reveal: posters and section rules ink onto the paper as they
// arrive. Shared with load-more so appended cards reveal too.
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
let revealObserver = null;
const observeReveal = (root) => {
  if (reducedMotion.matches) {
    return;
  }
  if (!revealObserver) {
    document.documentElement.classList.add('has-reveal');
    revealObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-inked');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -5% 0px' });
  }
  const targets = root.querySelectorAll
    ? root.querySelectorAll('.poster:not(.is-inked), .register:not(.is-inked)')
    : [];
  let i = 0;
  targets.forEach((el) => {
    if (el.classList.contains('poster')) {
      el.style.setProperty('--ink-delay', `${(i % 4) * 70}ms`);
      i += 1;
    }
    revealObserver.observe(el);
  });
};

// Mark the current section in the masthead nav.
document.addEventListener('DOMContentLoaded', () => {
  observeReveal(document);
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
          const added = [...newGrid.children];
          grid.append(...added);
          observeReveal(grid);
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
