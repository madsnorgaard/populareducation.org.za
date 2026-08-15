// Gallery lightbox: PhotoSwipe over the grid's plain links. Loaded as a
// dynamic chunk only on pages that have a gallery - without JavaScript the
// anchors keep working as links to the 2000px derivative.
import PhotoSwipeLightbox from 'photoswipe/lightbox';

// Flat stroke icons, per the design rules: no fills, no rounding. Arrow
// icons render at 60x60 (see PhotoSwipe's arrow CSS), so they get their own
// viewBox and weight; each points its own way - no CSS mirroring.
const ARROW_PREV = '<svg aria-hidden="true" class="pswp__icn" viewBox="0 0 60 60" width="60" height="60"><path d="M36 16L22 30l14 14" fill="none" stroke="currentColor" stroke-width="3.5"/></svg>';
const ARROW_NEXT = '<svg aria-hidden="true" class="pswp__icn" viewBox="0 0 60 60" width="60" height="60"><path d="M24 16l14 14-14 14" fill="none" stroke="currentColor" stroke-width="3.5"/></svg>';
const CLOSE = '<svg aria-hidden="true" class="pswp__icn" viewBox="0 0 32 32" width="32" height="32"><path d="M9 9l14 14M23 9L9 23" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
const ZOOM = '<svg aria-hidden="true" class="pswp__icn" viewBox="0 0 32 32" width="32" height="32"><rect x="8" y="8" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 12v8M12 16h8" fill="none" stroke="currentColor" stroke-width="2" class="pswp__zoom-icn-bar"/></svg>';

export const init = () => {
  const lightbox = new PhotoSwipeLightbox({
    gallery: '.gallery-grid',
    children: 'a.gallery-item__link',
    pswpModule: () => import('photoswipe'),
    mainClass: 'pswp--pe',
    showHideAnimationType: 'fade',
    bgOpacity: 1,
    wheelToZoom: true,
    padding: { top: 24, bottom: 72, left: 16, right: 16 },
    arrowPrevSVG: ARROW_PREV,
    arrowNextSVG: ARROW_NEXT,
    closeSVG: CLOSE,
    zoomSVG: ZOOM,
  });

  lightbox.on('uiRegister', () => {
    const { pswp } = lightbox;

    // Caption bar: the tile's figcaption plus an optional credit, read from
    // the DOM as text - never markup.
    pswp.ui.registerElement({
      name: 'pe-caption',
      order: 9,
      appendTo: 'root',
      onInit: (el) => {
        pswp.on('change', () => {
          const link = pswp.currSlide?.data?.element;
          const caption = link?.closest('figure')?.querySelector('figcaption')?.textContent ?? '';
          const credit = link?.dataset.credit ? ` — ${link.dataset.credit}` : '';
          el.textContent = caption + credit;
          el.hidden = el.textContent === '';
        });
      },
    });

    // Original file, when the archive recovered one beyond the derivative.
    pswp.ui.registerElement({
      name: 'pe-original',
      order: 10,
      tagName: 'a',
      appendTo: 'root',
      onInit: (el) => {
        el.textContent = 'Original';
        el.setAttribute('target', '_blank');
        el.setAttribute('rel', 'noopener');
        pswp.on('change', () => {
          const download = pswp.currSlide?.data?.element?.dataset.download;
          el.hidden = !download;
          if (download) {
            el.setAttribute('href', download);
          }
        });
      },
    });
  });

  // The Back button closes the lightbox instead of leaving the page.
  let viaHistory = false;
  lightbox.on('beforeOpen', () => {
    window.history.pushState({ peLightbox: true }, '');
  });
  lightbox.on('close', () => {
    if (!viaHistory && window.history.state?.peLightbox) {
      window.history.back();
    }
  });
  window.addEventListener('popstate', () => {
    viaHistory = true;
    lightbox.pswp?.close();
    viaHistory = false;
  });

  lightbox.init();
};
