// Gemensam zoom- och panoreringsvisare för Mermaid och SVG-bilder.
(function () {
  let renderQueue = Promise.resolve(), renderSequence = 0;
  // Serialize configuration + rendering: Mermaid shares configuration globally.
  window.renderWikiMermaid = function (source, id, availableWidth = 960, securityLevel = 'strict', container) {
    const task = renderQueue.then(async () => {
      window.mermaid.initialize({
        startOnLoad: false, securityLevel,
        theme: document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'base',
        flowchart: { htmlLabels: true, useMaxWidth: true, wrappingWidth: Math.round(Math.max(240, Math.min(640, availableWidth / 3))) },
      });
      return window.mermaid.render(id, source, container);
    });
    renderQueue = task.catch(() => {});
    return task;
  };
  function createWikiViewer() {
    const dialog = document.createElement('dialog');
    dialog.className = 'mmd-viewer';
    dialog.setAttribute('aria-label', 'Diagramvisare');
    dialog.innerHTML = '<div class="mmd-toolbar"><strong>Diagram</strong>' +
      '<button type="button" data-action="out" aria-label="Zooma ut">−</button>' +
      '<output aria-live="polite">100%</output>' +
      '<button type="button" data-action="in" aria-label="Zooma in">+</button>' +
      '<button type="button" data-action="fit">Anpassa</button>' +
      '<button type="button" data-action="refresh" hidden>Uppdatera diagram</button>' +
      '<button type="button" data-action="close" aria-label="Stäng diagram">Stäng ×</button></div>' +
      '<div class="mmd-viewport" tabindex="0" aria-label="Diagram. Dra för att panorera. Använd plus och minus för zoom, piltangenter för panorering och 0 för att anpassa."><div class="mmd-canvas"></div></div>' +
      '<p class="mmd-help">Dra för att panorera · Scrolla eller använd + / − för zoom · 0 anpassar · Esc stänger</p>';
    document.body.appendChild(dialog);
    const viewport = dialog.querySelector('.mmd-viewport');
    const canvas = dialog.querySelector('.mmd-canvas');
    const output = dialog.querySelector('output');
    const refreshButton = dialog.querySelector('[data-action="refresh"]');
    const help = dialog.querySelector('.mmd-help');
    const defaultHelp = help.textContent;
    let diagramOptions, revision = 0, resizeTimer;
    let scale = 1, x = 0, y = 0, width = 1, height = 1, opener, oldOverflow;
    let drag = null;
    function showSvg(svg) {
      const box = svg.viewBox?.baseVal || {};
      width = svg.naturalWidth || box.width || svg.getBoundingClientRect().width || 800;
      height = svg.naturalHeight || box.height || svg.getBoundingClientRect().height || 600;
      svg.style.width = width + 'px'; svg.style.height = height + 'px'; svg.style.maxWidth = 'none';
      canvas.replaceChildren(svg);
    }
    async function redraw() {
      clearTimeout(resizeTimer);
      if (!dialog.open || !diagramOptions?.source) return;
      const current = ++revision;
      const options = diagramOptions;
      const host = document.createElement('div');
      host.style.cssText = 'position:absolute;visibility:hidden;pointer-events:none;left:0;top:0;';
      host.style.width = Math.max(1, viewport.clientWidth - 48) + 'px';
      dialog.appendChild(host);
      refreshButton.disabled = true;
      viewport.setAttribute('aria-busy', 'true');
      try {
        const result = await window.renderWikiMermaid(options.source, 'viewer-mmd-' + (++renderSequence), viewport.clientWidth - 48, options.securityLevel, host);
        if (!dialog.open || current !== revision) return;
        host.innerHTML = result.svg;
        const svg = host.querySelector('svg');
        if (!svg) throw new Error('Diagrammet saknar SVG.');
        showSvg(svg); fit();
        help.textContent = defaultHelp;
      } catch (error) {
        if (dialog.open && current === revision) help.textContent = 'Kunde inte rita om diagrammet. Föregående vy visas. Försök med Uppdatera diagram.';
      } finally {
        host.remove();
        if (current === revision) {
          refreshButton.disabled = false;
          viewport.removeAttribute('aria-busy');
        }
      }
    }
    function paint() {
      canvas.style.transform = 'translate(' + x + 'px,' + y + 'px) scale(' + scale + ')';
      output.textContent = Math.round(scale * 100) + '%';
    }
    function fit() {
      scale = Math.min((viewport.clientWidth - 48) / width, (viewport.clientHeight - 48) / height, 2);
      scale = Math.max(0.01, scale);
      x = (viewport.clientWidth - width * scale) / 2;
      y = (viewport.clientHeight - height * scale) / 2;
      paint();
    }
    function zoom(factor, cx = viewport.clientWidth / 2, cy = viewport.clientHeight / 2) {
      const next = Math.min(20, Math.max(0.01, scale * factor));
      x = cx - (cx - x) * next / scale;
      y = cy - (cy - y) * next / scale;
      scale = next; paint();
    }
    dialog.addEventListener('click', e => {
      const action = e.target.closest('button')?.dataset.action;
      if (action === 'close' || e.target === dialog) dialog.close();
      if (action === 'fit') fit();
      if (action === 'refresh') redraw();
      if (action === 'in') zoom(1.25);
      if (action === 'out') zoom(0.8);
    });
    dialog.addEventListener('close', () => {
      ++revision; clearTimeout(resizeTimer);
      diagramOptions = null;
      refreshButton.disabled = false;
      viewport.removeAttribute('aria-busy');
      document.body.style.overflow = oldOverflow;
      canvas.replaceChildren(); drag = null;
      if (opener?.isConnected) opener.focus();
    });
    viewport.addEventListener('wheel', e => {
      e.preventDefault();
      const rect = viewport.getBoundingClientRect();
      zoom(Math.exp(-Math.max(-100, Math.min(100, e.deltaY)) * 0.005), e.clientX - rect.left, e.clientY - rect.top);
    }, { passive: false });
    viewport.addEventListener('pointerdown', e => {
      if (e.button !== 0 || drag) return;
      viewport.focus(); viewport.setPointerCapture(e.pointerId);
      drag = { id: e.pointerId, x: e.clientX, y: e.clientY };
      viewport.classList.add('is-dragging');
      e.preventDefault();
    });
    viewport.addEventListener('pointermove', e => {
      if (!drag || drag.id !== e.pointerId) return;
      x += e.clientX - drag.x; y += e.clientY - drag.y;
      drag.x = e.clientX; drag.y = e.clientY; paint();
    });
    ['pointerup', 'pointercancel', 'lostpointercapture'].forEach(type => viewport.addEventListener(type, () => {
      drag = null; viewport.classList.remove('is-dragging');
    }));
    viewport.addEventListener('keydown', e => {
      if (e.key === '+' || e.key === '=') zoom(1.25);
      else if (e.key === '-') zoom(0.8);
      else if (e.key === '0' || e.key === 'Home') fit();
      else if (e.key === 'ArrowLeft') { x += 40; paint(); }
      else if (e.key === 'ArrowRight') { x -= 40; paint(); }
      else if (e.key === 'ArrowUp') { y += 40; paint(); }
      else if (e.key === 'ArrowDown') { y -= 40; paint(); }
      else return;
      e.preventDefault();
    });
    new ResizeObserver(() => {
      if (!dialog.open) return;
      fit();
      if (diagramOptions?.source) {
        ++revision;
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(redraw, 200);
      }
    }).observe(viewport);
    return { open(svg, trigger, options) {
      ++revision;
      diagramOptions = options;
      refreshButton.hidden = !options?.source;
      refreshButton.disabled = false;
      help.textContent = defaultHelp;
      opener = trigger;
      const isImage = svg.tagName.toLowerCase() === 'img';
      dialog.querySelector('strong').textContent = isImage ? (svg.alt || 'SVG-bild') : 'Diagram';
      dialog.setAttribute('aria-label', isImage ? 'Bildvisare' : 'Diagramvisare');
      const clone = svg.cloneNode(true);
      if (isImage) {
        clone.classList.remove('gbg-lightbox-img');
        clone.removeAttribute('tabindex');
        clone.removeAttribute('role');
        clone.loading = 'eager';
        clone.draggable = false;
      }
      // Keep SVG marker/label IDs unique while both copies are in the document.
      const ids = new Map();
      clone.querySelectorAll('[id]').forEach(el => ids.set(el.id, 'popup-' + el.id));
      if (clone.id) ids.set(clone.id, 'popup-' + clone.id);
      [clone, ...clone.querySelectorAll('*')].forEach(el => {
        for (const attr of [...el.attributes]) {
          let value = attr.value;
          if (attr.name === 'id') value = ids.get(value) || value;
          else ids.forEach((next, old) => {
            value = value.split('url(#' + old + ')').join('url(#' + next + ')');
            if (value === '#' + old) value = '#' + next;
            if (attr.name === 'aria-labelledby' || attr.name === 'aria-describedby') value = value.split(' ').map(id => id === old ? next : id).join(' ');
          });
          el.setAttribute(attr.name, value);
        }
        if (el.tagName.toLowerCase() === 'style') ids.forEach((next, old) => { el.textContent = el.textContent.split('#' + old).join('#' + next); });
      });
      showSvg(clone);
      oldOverflow = document.body.style.overflow;
      document.body.style.overflow = 'hidden';
      dialog.showModal(); fit(); viewport.focus();
      redraw();
    } };
  }


window.createWikiViewer = createWikiViewer;
let imageViewer;
function isSvgImage(img) {
  try { return /\.svg$/i.test(new URL(img.currentSrc || img.src, location.href).pathname) || /^data:image\/svg\+xml/i.test(img.src); } catch { return false; }
}
function openSvg(img) {
  if (!img.complete || !img.naturalWidth) return;
  imageViewer ||= createWikiViewer();
  imageViewer.open(img, img);
}
document.addEventListener('click', event => {
  const img = event.target.closest?.('img.gbg-lightbox-img');
  if (!img || !isSvgImage(img)) return;
  event.preventDefault(); event.stopImmediatePropagation();
  openSvg(img);
}, true);
document.addEventListener('keydown', event => {
  if (!event.target.matches?.('img.gbg-lightbox-img') || !isSvgImage(event.target)) return;
  if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openSvg(event.target); }
});
function enhanceSvgImages(root) {
  const images = [...(root.matches?.('img.gbg-lightbox-img') ? [root] : []), ...root.querySelectorAll('img.gbg-lightbox-img')];
  images.filter(isSvgImage).forEach(img => { img.tabIndex = 0; img.setAttribute('role', 'button'); img.setAttribute('aria-label', (img.alt || 'SVG-bild') + ' – öppna med zoom och panorering'); });
}
enhanceSvgImages(document);
new MutationObserver(records => records.forEach(record => {
  if (record.type === 'attributes') enhanceSvgImages(record.target);
  else record.addedNodes.forEach(node => { if (node.nodeType === 1) enhanceSvgImages(node); });
})).observe(document.body, {childList:true, subtree:true, attributes:true, attributeFilter:['src']});
})();
