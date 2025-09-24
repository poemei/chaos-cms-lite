// =========================================================
// ChaosCMS – Universal site helpers
// Path: /app/assets/site.js
// Load at the end of <body> (after DOM).
// =========================================================

document.addEventListener('DOMContentLoaded', () => {
  // 1) Add copy buttons to <pre><code> blocks
  document.querySelectorAll('pre code').forEach(code => {
    const wrap = document.createElement('div');
    wrap.className = 'code-wrap';
    const pre = code.parentElement;
    pre.parentElement.insertBefore(wrap, pre);
    wrap.appendChild(pre);

    const btn = document.createElement('button');
    btn.className = 'code-copy';
    btn.type = 'button';
    btn.textContent = 'Copy';
    btn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(code.innerText);
        const old = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(() => (btn.textContent = old), 1200);
      } catch {
        alert('Copy failed');
      }
    });
    wrap.appendChild(btn);
  });

  // 2) Smooth scroll for on-page anchors
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
      const id = a.getAttribute('href').slice(1);
      const el = document.getElementById(id);
      if (el) {
        e.preventDefault();
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.replaceState(null, '', '#' + id);
      }
    });
  });

  // 3) External links: open in new tab safely
  document.querySelectorAll('a[href^="http"]').forEach(a => {
    const host = location.host;
    try {
      const url = new URL(a.href);
      if (url.host !== host) {
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
      }
    } catch {}
  });

  // 4) Dismissible alerts (for .alert[data-close])
  document.querySelectorAll('.alert[data-close]').forEach(al => {
    const x = document.createElement('button');
    x.type = 'button';
    x.className = 'btn btn-sm';
    x.textContent = '×';
    x.style.cssText = 'float:right;margin-left:.5rem';
    x.addEventListener('click', () => al.remove());
    al.prepend(x);
  });

  // 5) Confirm links/buttons (data-confirm="message")
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', (e) => {
      const msg = el.getAttribute('data-confirm') || 'Are you sure?';
      if (!confirm(msg)) e.preventDefault();
    });
  });

  // 6) Password visibility toggle
  // Usage: wrap input[type=password] in .input-group and add
  // <button type="button" class="input-append" data-toggle="password" aria-label="Show password">??</button>
  document.querySelectorAll('[data-toggle="password"]').forEach(btn => {
    btn.addEventListener('click', () => {
      const group = btn.closest('.input-group');
      if (!group) return;
      const input = group.querySelector('input[type="password"], input[type="text"]');
      if (!input) return;
      const isPass = input.type === 'password';
      input.type = isPass ? 'text' : 'password';
      btn.setAttribute('aria-label', isPass ? 'Hide password' : 'Show password');
    });
  });

  // 7) Mobile nav toggle (optional)
  // Add a button#nav-toggle and a nav#site-nav if you want this.
  const navBtn = document.getElementById('nav-toggle');
  const nav = document.getElementById('site-nav');
  if (navBtn && nav) {
    navBtn.addEventListener('click', () => {
      nav.classList.toggle('hidden');
    });
  }
});
