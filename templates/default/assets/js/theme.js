/**
 * templates/default/assets/js/theme.js
 * Ljust/mörkt läge (localStorage) + dropdown-menyer i huvudmenyn,
 * i stil med goteborg-dw-template.
 */

var gbgLinkSettings = document.currentScript ? document.currentScript.dataset : {};

function gbgApplyThemeIcon(theme) {
  var moon = document.querySelector('.gbg-icon-moon');
  var sun  = document.querySelector('.gbg-icon-sun');
  if (moon && sun) {
    moon.hidden = theme === 'dark';
    sun.hidden  = theme !== 'dark';
  }

  // Header-logotypen byter bakgrund med ljust/mörkt läge (vit / nästan
  // svart) — visa den logga (ljust/mörkt-variant) som faktiskt syns mot
  // den bakgrunden. Sidfoten är alltid mörk och har bara en egen logga,
  // ingen växling där. Se config.php:s 'header_logo'/'header_logo_dark'.
  var logoLight = document.querySelector('.gbg-logo-light');
  var logoDark  = document.querySelector('.gbg-logo-dark');
  if (logoLight && logoDark) {
    logoLight.hidden = theme === 'dark';
    logoDark.hidden  = theme !== 'dark';
  }
}

function gbgSetTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  try { localStorage.setItem('theme', theme); } catch (e) {}
  gbgApplyThemeIcon(theme);
  // Låter andra skript (t.ex. assets/js/mermaid.js på wikisidor, eller
  // chat/index.php:s egen mermaid-rendering) rita om redan renderade
  // Mermaid-diagram i det nya läget utan att sidan behöver laddas om.
  window.dispatchEvent(new CustomEvent('gbg-theme-change', { detail: { theme: theme } }));
}

function gbgToggleTheme() {
  var current = document.documentElement.getAttribute('data-theme');
  gbgSetTheme(current === 'dark' ? 'light' : 'dark');
}

document.addEventListener('DOMContentLoaded', function () {
  function configureLink(link) {
    if (!(link instanceof HTMLAnchorElement)) return;
    var url;
    try { url = new URL(link.getAttribute('href'), location.href); } catch (_) { return; }
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return;
    var external = url.origin !== location.origin;
    link.classList.toggle('gbg-link-external', external);
    link.classList.toggle('gbg-link-internal', !external);
    if (external) {
      if (gbgLinkSettings.externalNewTab !== 'false') {
        link.target = '_blank';
        link.relList.add('noopener', 'noreferrer');
      } else {
        link.removeAttribute('target');
      }
    } else if (!link.hasAttribute('download')) {
      link.removeAttribute('target');
    }
  }
  function configureLinks(root) {
    if (root.nodeType !== 1) return;
    if (root.matches('a[href]')) configureLink(root);
    root.querySelectorAll('a[href]').forEach(configureLink);
  }
  ['internal', 'external'].forEach(function (kind) {
    var color = gbgLinkSettings[kind + 'Color'];
    if (/^#[0-9a-f]{6}$/i.test(color || '')) {
      document.documentElement.style.setProperty('--configured-' + kind + '-link', color);
    }
  });
  configureLinks(document.body);
  new MutationObserver(function (records) {
    records.forEach(function (record) {
      if (record.type === 'attributes') configureLink(record.target);
      else record.addedNodes.forEach(configureLinks);
    });
  }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['href'] });

  var saved = 'light';
  try { saved = localStorage.getItem('theme') || 'light'; } catch (e) {}
  gbgSetTheme(saved);

  var toggleBtn = document.getElementById('gbg-theme-toggle');
  if (toggleBtn) {
    toggleBtn.addEventListener('click', gbgToggleTheme);
  }

  // ── Dropdown-menyer (sök / meny i huvudmenyn, "..."-menyn på sidan) ──
  // Triggerknappen kan vara .gbg-tool-btn (header) eller en vanlig
  // .gbg-btn (t.ex. sidverktygsraden) — [aria-haspopup] täcker båda.
  var dropdowns = Array.prototype.slice.call(document.querySelectorAll('.gbg-dropdown'));

  var searchMenu = document.querySelector('.gbg-dropdown-menu--search');
  if (searchMenu) {
    var searchInput = searchMenu.querySelector('input[type="search"]');
    var suggestions = document.getElementById('gbg-search-suggestions');
    var searchStatus = document.getElementById('gbg-search-status');
    var searchTimer, searchController;
    var searchVersion = 0, selectedSuggestion = -1;
    function clearSuggestions() {
      suggestions.replaceChildren();
      suggestions.hidden = true;
      selectedSuggestion = -1;
      searchInput.setAttribute('aria-expanded', 'false');
      searchInput.removeAttribute('aria-activedescendant');
    }
    function cancelSuggestions() {
      clearTimeout(searchTimer);
      if (searchController) searchController.abort();
      searchVersion++;
      clearSuggestions();
    }
    function requestSuggestions() {
      cancelSuggestions();
      var query = searchInput.value.trim();
      var version = searchVersion;
      if (!query) {
        searchStatus.textContent = 'Skriv för att hitta sidor. Välj med ↓ ↑ och Enter.';
        return;
      }
      searchStatus.textContent = 'Söker…';
      searchTimer = setTimeout(async function () {
        searchController = new AbortController();
        try {
          var response = await fetch('/?do=search&format=suggest&q=' + encodeURIComponent(query), {
            signal: searchController.signal, headers: { Accept: 'application/json' }
          });
          if (!response.ok) throw new Error('search');
          var data = await response.json();
          if (version !== searchVersion) return;
          data.results.forEach(function (result, index) {
            var option = document.createElement('a');
            option.className = 'gbg-search-option';
            option.id = 'gbg-search-option-' + index;
            option.href = result.url;
            option.role = 'option';
            option.tabIndex = -1;
            option.setAttribute('aria-selected', 'false');
            var title = document.createElement('strong');
            title.textContent = result.title;
            var path = document.createElement('small');
            path.textContent = result.id;
            option.append(title, path);
            suggestions.appendChild(option);
          });
          suggestions.hidden = !data.results.length;
          searchInput.setAttribute('aria-expanded', String(!!data.results.length));
          searchStatus.textContent = data.total
            ? 'Visar ' + data.results.length + ' av ' + data.total + ' träffar. ↓ ↑ och Enter öppnar sidan. Sök visar alla.'
            : 'Inga sidor hittades. Tryck Enter för vanlig sökning.';
        } catch (error) {
          if (version === searchVersion && error.name !== 'AbortError') {
            searchStatus.textContent = 'Förslagen kunde inte hämtas. Tryck Enter för vanlig sökning.';
          }
        }
      }, 180);
    }
    searchMenu.addEventListener('click', function (event) { event.stopPropagation(); });
    searchInput.addEventListener('input', function (event) {
      if (!event.isComposing) requestSuggestions();
    });
    searchInput.addEventListener('compositionend', requestSuggestions);
    searchInput.addEventListener('focus', requestSuggestions);
    searchInput.addEventListener('keydown', function (event) {
      if (event.isComposing) return;
      var options = suggestions.children;
      if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && options.length) {
        event.preventDefault();
        selectedSuggestion = event.key === 'ArrowDown'
          ? (selectedSuggestion + 1) % options.length
          : (selectedSuggestion < 0 ? options.length - 1 : (selectedSuggestion + options.length - 1) % options.length);
        Array.from(options).forEach(function (option, index) {
          option.setAttribute('aria-selected', String(index === selectedSuggestion));
        });
        searchInput.setAttribute('aria-activedescendant', options[selectedSuggestion].id);
        options[selectedSuggestion].scrollIntoView({ block: 'nearest' });
      } else if (event.key === 'Enter' && selectedSuggestion >= 0) {
        event.preventDefault();
        options[selectedSuggestion].click();
      } else if (event.key === 'Escape') {
        event.preventDefault();
        cancelSuggestions();
        searchMenu.parentElement.querySelector('button[aria-haspopup]').focus();
      }
    });
    searchMenu.addEventListener('focusout', function (event) {
      if (!searchMenu.contains(event.relatedTarget)) cancelSuggestions();
    });
  }

  function closeAll(except) {
    dropdowns.forEach(function (dd) {
      if (dd === except) return;
      if (dd.dataset.dropdown === 'search' && searchMenu) cancelSuggestions();
      dd.classList.remove('is-open');
      var btn = dd.querySelector('[aria-haspopup]');
      if (btn) btn.setAttribute('aria-expanded', 'false');
    });
  }

  dropdowns.forEach(function (dd) {
    var btn = dd.querySelector('[aria-haspopup]');
    var menu = dd.querySelector('.gbg-dropdown-menu');
    if (!btn || !menu) return;

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var willOpen = !dd.classList.contains('is-open');
      closeAll(dd);
      closeNav();
      dd.classList.toggle('is-open', willOpen);
      btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      if (willOpen) {
        var input = menu.querySelector('input[type="search"]');
        if (input) input.focus();
      } else if (dd.dataset.dropdown === 'search' && searchMenu) {
        cancelSuggestions();
      }
    });
  });

  document.addEventListener('click', function () { closeAll(null); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeAll(null); closeNav(); }
  });

  // ── Hamburgermeny för huvudnavigeringen (_topbar.md/config/menu.php) —
  // bara synlig under mobilbrytpunkten (se style.css). ──────────────────
  var navToggle = document.getElementById('gbg-nav-toggle');
  var navLinks  = document.getElementById('gbg-nav-links');

  function closeNav() {
    if (!navToggle || !navLinks) return;
    navLinks.classList.remove('is-open');
    navToggle.setAttribute('aria-expanded', 'false');
  }

  if (navToggle && navLinks) {
    navToggle.addEventListener('click', function (e) {
      e.stopPropagation();
      var willOpen = !navLinks.classList.contains('is-open');
      closeAll(null);
      navLinks.classList.toggle('is-open', willOpen);
      navToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
      if (!navLinks.contains(e.target) && e.target !== navToggle && !navToggle.contains(e.target)) {
        closeNav();
      }
    });
  }

  // ── Lightbox för bilder — sidinnehållets media-embeds ({{ns:bild.png}},
  // se core/Parser.php) och /images-galleriets miniatyrer (se media.php),
  // båda taggade med .gbg-lightbox-img. Klick öppnar bilden i fullskärm
  // istället för att navigera bort (media-galleriets <a target="_blank">
  // avbryts med preventDefault när målet faktiskt är en sådan bild).
  var lightbox = document.createElement('div');
  lightbox.className = 'gbg-lightbox';
  lightbox.setAttribute('role', 'dialog');
  lightbox.setAttribute('aria-modal', 'true');
  lightbox.hidden = true;
  lightbox.innerHTML =
    '<button type="button" class="gbg-lightbox-close" aria-label="Stäng">' +
    '<svg viewBox="0 0 24 24" aria-hidden="true"><line x1="5" y1="5" x2="19" y2="19"/><line x1="19" y1="5" x2="5" y2="19"/></svg>' +
    '</button>' +
    '<img class="gbg-lightbox-full" alt="">';
  document.body.appendChild(lightbox);
  var lightboxImg = lightbox.querySelector('.gbg-lightbox-full');

  function openLightbox(src, alt) {
    lightboxImg.src = src;
    lightboxImg.alt = alt || '';
    lightbox.hidden = false;
  }
  function closeLightbox() {
    lightbox.hidden = true;
    lightboxImg.src = ''; // sluta ladda/hålla kvar bilden i minnet när den är stängd
  }

  document.addEventListener('click', function (e) {
    var img = e.target.closest && e.target.closest('.gbg-lightbox-img');
    if (img) {
      e.preventDefault(); // avbryter ev. omslutande länk (t.ex. /images-galleriets "öppna i ny flik")
      openLightbox(img.currentSrc || img.src, img.alt);
      return;
    }
    if (e.target === lightbox || e.target.closest('.gbg-lightbox-close')) {
      closeLightbox();
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !lightbox.hidden) closeLightbox();
  });
});
