/* ==========================================================================
   Colegio José Manuel Balmaceda · San Javier
   Interactividad general, formularios de contacto e integración Instagram
   ========================================================================== */
(function () {
  'use strict';

  const SEVEN_DAYS_MS = 7 * 24 * 60 * 60 * 1000;
  const LOCAL_MSG_KEY = 'jmb_mensajes_local_v1';
  const LOCAL_IG_KEY  = 'jmb_instagram_videos_v1';

  /* 1. Menú móvil accesible */
  const navToggle = document.querySelector('.nav-toggle');
  const siteNav = document.querySelector('nav.site-nav');
  if (navToggle && siteNav) {
    navToggle.addEventListener('click', () => {
      const open = siteNav.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', String(open));
    });
  }

  /* 2. Respaldo de imágenes */
  document.querySelectorAll('.foto img, .document-cover img').forEach((img) => {
    const box = img.parentElement;
    const onLoad = () => box.classList.add('con-imagen');
    const onError = () => {
      img.style.display = 'none';
    };
    img.addEventListener('load', onLoad);
    img.addEventListener('error', onError);
    if (img.complete) {
      if (img.naturalWidth > 0) onLoad();
      else onError();
    }
  });

  /* 3. Animaciones sutiles al desplazarse */
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!reduceMotion && 'IntersectionObserver' in window) {
    const sectionWraps = document.querySelectorAll('main section > .wrap');
    const staggerCards = document.querySelectorAll('.card, .video-card, .staff-card, .document-card, .workshop-link');
    const targets = new Set([...sectionWraps, ...staggerCards]);

    staggerCards.forEach((item) => {
      const siblings = [...item.parentElement.children].filter((el) =>
        el.matches('.card, .video-card, .staff-card, .document-card, .workshop-link')
      );
      item.style.setProperty('--reveal-delay', `${Math.min(siblings.indexOf(item), 6) * 60}ms`);
    });

    targets.forEach((el) => el.classList.add('reveal'));
    document.body.classList.add('reveal-ready');

    const observer = new IntersectionObserver((entries, obs) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -24px 0px' });

    targets.forEach((el) => observer.observe(el));
  }

  /* 4. Modal informativo institucional */
  const informativoModal = document.getElementById('informativo-modal');
  if (informativoModal) {
    const closeBtn = informativoModal.querySelector('.informativo-cerrar');
    let closeTimer = null;

    const openModal = () => {
      window.clearTimeout(closeTimer);
      informativoModal.hidden = false;
      informativoModal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('informativo-abierto');
      window.requestAnimationFrame(() => {
        informativoModal.classList.add('is-open');
        if (closeBtn) closeBtn.focus();
      });
    };

    const closeModal = () => {
      if (informativoModal.hidden || !informativoModal.classList.contains('is-open')) return;
      informativoModal.classList.remove('is-open');
      informativoModal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('informativo-abierto');
      closeTimer = window.setTimeout(() => {
        informativoModal.hidden = true;
      }, 260);
    };

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    informativoModal.addEventListener('click', (e) => {
      if (e.target === informativoModal) closeModal();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeModal();
    });

    if (sessionStorage.getItem('informativo-mostrado') !== 'true') {
      sessionStorage.setItem('informativo-mostrado', 'true');
      window.requestAnimationFrame(openModal);
    }
  }

  /* 5. Utilidades de almacenamiento local con expiración semanal (7 días) */
  function leerMensajesLocales() {
    try {
      const raw = JSON.parse(localStorage.getItem(LOCAL_MSG_KEY) || '[]');
      const ahora = Date.now();
      const vigentes = Array.isArray(raw)
        ? raw.filter((m) => ahora - new Date(m.creado_en).getTime() < SEVEN_DAYS_MS)
        : [];
      if (vigentes.length !== raw.length) {
        localStorage.setItem(LOCAL_MSG_KEY, JSON.stringify(vigentes));
      }
      return vigentes;
    } catch {
      return [];
    }
  }

  function guardarMensajeLocal(payload) {
    const actuales = leerMensajesLocales();
    actuales.unshift({
      id: Date.now(),
      nombre: payload.nombre,
      correo: payload.correo,
      origen_pagina: payload.origen_pagina,
      mensaje: payload.mensaje,
      leido: 0,
      creado_en: new Date().toISOString(),
    });
    localStorage.setItem(LOCAL_MSG_KEY, JSON.stringify(actuales));
  }

  /* 6. Envío de formularios "Escríbenos" hacia MySQL (DBeaver) */
  document.querySelectorAll('form.js-contact-form').forEach((form) => {
    const statusBox = form.querySelector('.form-status');
    const submitBtn = form.querySelector('button[type="submit"]');

    form.addEventListener('submit', async (event) => {
      event.preventDefault();

      const nombre = (form.querySelector('[name="nombre"]')?.value || '').trim();
      const correo = (form.querySelector('[name="correo"]')?.value || '').trim();
      const mensaje = (form.querySelector('[name="mensaje"]')?.value || '').trim();
      const origen = form.getAttribute('data-origen') || document.title.split('|')[0].trim() || 'Inicio';

      if (!nombre || !correo || !mensaje) return;

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Enviando...';
      }

      if (statusBox) {
        statusBox.hidden = true;
        statusBox.className = 'form-status';
      }

      const payload = { nombre, correo, origen_pagina: origen, mensaje };

      try {
        const resp = await fetch('api/mensajes.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });

        const data = await resp.json();

        if (!resp.ok || !data.ok) {
          throw new Error(data.mensaje || 'No se pudo registrar el mensaje.');
        }

        form.reset();
        if (statusBox) {
          statusBox.textContent = data.mensaje || 'Mensaje enviado y registrado correctamente.';
          statusBox.classList.add('is-success');
          statusBox.hidden = false;
        }
      } catch (err) {
        // Respaldo si se abre sin servidor PHP/MySQL activo (ej. vista previa estática)
        guardarMensajeLocal(payload);
        form.reset();
        if (statusBox) {
          statusBox.textContent = 'Mensaje registrado localmente. Para sincronizarlo con DBeaver (MySQL), ejecuta el proyecto en un servidor PHP con base de datos activa.';
          statusBox.classList.add('is-success');
          statusBox.hidden = false;
        }
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Enviar mensaje';
        }
      }
    });
  });

  /* 7. Carga dinámica de Reels/Videos recientes de Instagram (@balmaceda403san_javier) */
  const igDynamicContainer = document.getElementById('ig-dynamic-reels');
  if (igDynamicContainer) {
    const escapeHtml = (str) =>
      String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const renderIgItems = (items) => {
      if (!Array.isArray(items) || items.length === 0) return;

      igDynamicContainer.innerHTML = items
        .map((item) => {
          const match = String(item.url_instagram || '').match(/instagram\.com\/(reel|p|tv)\/([A-Za-z0-9_-]+)/i);
          const embedUrl = match ? `https://www.instagram.com/${match[1].toLowerCase()}/${match[2]}/embed` : '';
          return `
            <article class="ig-reel-item">
              <h4>${escapeHtml(item.titulo)}</h4>
              ${item.descripcion ? `<p>${escapeHtml(item.descripcion)}</p>` : ''}
              <a href="${escapeHtml(item.url_instagram)}" target="_blank" rel="noopener">Ver publicación en @balmaceda403san_javier →</a>
              ${embedUrl ? `<div class="ig-reel-embed-wrapper"><iframe src="${escapeHtml(embedUrl)}" title="${escapeHtml(item.titulo)}" loading="lazy" allowtransparency="true"></iframe></div>` : ''}
            </article>
          `;
        })
        .join('');
    };

    fetch('api/instagram.php')
      .then((r) => (r.ok ? r.json() : Promise.reject()))
      .then((data) => {
        if (data && data.ok && Array.isArray(data.videos) && data.videos.length > 0) {
          renderIgItems(data.videos);
        } else {
          const locales = JSON.parse(localStorage.getItem(LOCAL_IG_KEY) || '[]');
          if (locales.length > 0) renderIgItems(locales);
        }
      })
      .catch(() => {
        try {
          const locales = JSON.parse(localStorage.getItem(LOCAL_IG_KEY) || '[]');
          if (locales.length > 0) renderIgItems(locales);
        } catch {
          // Mantiene tarjetas por defecto
        }
      });
  }

  window.JMBStorage = {
    leerMensajesLocales,
    LOCAL_MSG_KEY,
    LOCAL_IG_KEY,
    SEVEN_DAYS_MS,
  };
})();
