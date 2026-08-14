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
});
