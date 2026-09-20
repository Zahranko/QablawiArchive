/*
 * In-browser preview for the gallery.
 *
 * Why the work happens here rather than on the server:
 *
 *  - Browsers render images and PDFs natively. Nothing else.
 *  - The hosted viewers (Microsoft Office Online, Google Docs) render
 *    everything, but they fetch the file from a public URL with their own
 *    servers. Files here sit behind a login, so those viewers cannot reach
 *    them — and making them reachable would undo the login.
 *  - Converting on the server needs LibreOffice, which shared hosting does
 *    not provide.
 *
 * So the file is fetched with the session cookie and parsed in the browser:
 *
 *    docx        mammoth.js  -> HTML
 *    xlsx / xls  SheetJS     -> HTML table
 *    csv         SheetJS     -> HTML table
 *    txt         read as text
 *
 * .doc, .ppt and .pptx have no usable browser-side renderer. Those tiles
 * offer a download instead of a broken preview.
 *
 * Rendered document HTML goes into a sandboxed iframe with no allow-scripts,
 * so anything a crafted document manages to smuggle through the converter is
 * inert: no script runs, and it has no access to this page or the session.
 */
(function () {
  'use strict';

  var CDN = {
    mammoth: 'https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.8.0/mammoth.browser.min.js',
    xlsx: 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js'
  };

  var dialog = document.getElementById('preview');
  var body = document.getElementById('preview-body');
  var title = document.getElementById('preview-title');
  var dl = document.getElementById('preview-download');
  var closeBtn = document.getElementById('preview-close');

  if (!dialog || !dialog.showModal) {
    return; // No <dialog> support: tiles still download and delete fine.
  }

  var loaded = {};

  function escapeHtml(value) {
    var holder = document.createElement('span');
    holder.textContent = String(value);
    return holder.innerHTML;
  }

  function loadScript(url) {
    if (loaded[url]) {
      return loaded[url];
    }
    loaded[url] = new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = url;
      s.onload = resolve;
      s.onerror = function () {
        reject(new Error('Could not load the viewer library.'));
      };
      document.head.appendChild(s);
    });
    return loaded[url];
  }

  function fetchBuffer(url) {
    return fetch(url, { credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) {
        throw new Error('The file could not be read (' + r.status + ').');
      }
      return r.arrayBuffer();
    });
  }

  function setStatus(message) {
    body.innerHTML = '';
    var p = document.createElement('p');
    p.className = 'preview-status';
    p.textContent = message;
    body.appendChild(p);
  }

  /* Documents are only ever shown inside this sandbox. */
  function showDocument(html) {
    body.innerHTML = '';
    var frame = document.createElement('iframe');
    frame.className = 'preview-doc';
    frame.setAttribute('sandbox', '');
    frame.srcdoc =
      '<!DOCTYPE html><html><head><meta charset="utf-8">' +
      '<style>' +
      'body{margin:0;padding:28px 32px;font:15px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#16181d;background:#fff}' +
      'img{max-width:100%;height:auto}' +
      'table{border-collapse:collapse;margin:12px 0;font-size:14px}' +
      'td,th{border:1px solid #d8dbe0;padding:6px 10px;text-align:left}' +
      'th{background:#f4f5f7}' +
      'h1,h2,h3{line-height:1.3}' +
      'h2.sheet-name{margin:28px 0 4px;font-size:15px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em}' +
      '</style></head><body>' + html + '</body></html>';
    body.appendChild(frame);
  }

  var render = {
    image: function (url) {
      body.innerHTML = '';
      var img = document.createElement('img');
      img.className = 'preview-image';
      img.src = url;
      img.alt = '';
      body.appendChild(img);
      return Promise.resolve();
    },

    pdf: function (url) {
      body.innerHTML = '';
      var frame = document.createElement('iframe');
      frame.className = 'preview-doc';
      frame.src = url;
      body.appendChild(frame);
      return Promise.resolve();
    },

    text: function (url) {
      return fetch(url, { credentials: 'same-origin' })
        .then(function (r) {
          if (!r.ok) {
            throw new Error('The file could not be read (' + r.status + ').');
          }
          return r.text();
        })
        .then(function (text) {
          body.innerHTML = '';
          var pre = document.createElement('pre');
          pre.className = 'preview-text';
          pre.textContent = text; // textContent, never innerHTML
          body.appendChild(pre);
        });
    },

    word: function (url) {
      return Promise.all([loadScript(CDN.mammoth), fetchBuffer(url)])
        .then(function (results) {
          return window.mammoth.convertToHtml({ arrayBuffer: results[1] });
        })
        .then(function (result) {
          showDocument(result.value || '<p><em>This document has no readable text.</em></p>');
        });
    },

    sheet: function (url) {
      return Promise.all([loadScript(CDN.xlsx), fetchBuffer(url)])
        .then(function (results) {
          var wb = window.XLSX.read(results[1], { type: 'array' });
          var many = wb.SheetNames.length > 1;
          var parts = [];

          wb.SheetNames.forEach(function (name, i) {
            /* Sheet names come out of the file, so they are escaped. */
            if (many) {
              parts.push('<h2 class="sheet-name">' + escapeHtml(name) + '</h2>');
            }
            parts.push(window.XLSX.utils.sheet_to_html(wb.Sheets[name]));
            if (many && i < wb.SheetNames.length - 1) {
              parts.push('<hr>');
            }
          });

          showDocument(parts.join('\n'));
        });
    }
  };

  function open(name, kind, url) {
    title.textContent = name;
    dl.href = url + '&dl=1';
    dl.setAttribute('aria-label', 'Download ' + name);
    setStatus('Loading preview…');
    dialog.showModal();

    var run = render[kind];
    if (!run) {
      setStatus('No preview is available for this format. Download it to open.');
      return;
    }

    run(url).catch(function (err) {
      setStatus(err && err.message ? err.message : 'The preview could not be shown.');
    });
  }

  document.addEventListener('click', function (ev) {
    var trigger = ev.target.closest('[data-preview]');
    if (!trigger) {
      return;
    }
    ev.preventDefault();
    open(
      trigger.getAttribute('data-name'),
      trigger.getAttribute('data-kind'),
      trigger.getAttribute('data-url')
    );
  });

  closeBtn.addEventListener('click', function () {
    dialog.close();
  });

  /* Click the backdrop to dismiss. */
  dialog.addEventListener('click', function (ev) {
    if (ev.target === dialog) {
      dialog.close();
    }
  });

  /* Drop the rendered document so a large file is not held in memory. */
  dialog.addEventListener('close', function () {
    body.innerHTML = '';
  });
})();
