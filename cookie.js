/* ============================================================
   Плашка про cookie.
   Показывается один раз: ответ запоминается в самом браузере
   посетителя и на сервер не уходит.
   ============================================================ */
(function () {
  var KEY = 'kl_cookie_ok';
  try { if (localStorage.getItem(KEY)) return; } catch (e) { return; }

  var css = document.createElement('style');
  css.textContent = [
    '.ck-bar{position:fixed;left:0;right:0;bottom:0;z-index:200;',
    '  display:flex;align-items:center;gap:18px;flex-wrap:wrap;justify-content:center;',
    '  padding:16px clamp(16px,4vw,40px);background:rgba(6,9,7,.95);backdrop-filter:blur(14px);',
    '  border-top:1px solid rgba(182,148,81,.35);',
    '  font:400 13px/1.5 Inter,system-ui,sans-serif;color:rgba(255,244,226,.68);',
    '  transform:translateY(100%);transition:transform .5s cubic-bezier(.16,1,.3,1)}',
    '.ck-bar.on{transform:none}',
    '.ck-bar p{margin:0;max-width:62ch;text-wrap:pretty}',
    '.ck-bar a{color:#f1ce89;text-decoration:none;border-bottom:1px solid rgba(182,148,81,.4)}',
    '.ck-bar a:hover{color:#d4ff00;border-color:#d4ff00}',
    '.ck-bar button{font:500 11px/1 Inter,system-ui,sans-serif;letter-spacing:.18em;text-transform:uppercase;',
    '  padding:12px 26px;border:1px solid #b69451;background:transparent;color:#f1ce89;cursor:pointer;',
    '  transition:color .3s,background .3s,border-color .3s;flex:none}',
    '.ck-bar button:hover{background:#d4ff00;border-color:#d4ff00;color:#060907}',
    '@media(max-width:640px){.ck-bar{font-size:12px;gap:12px}.ck-bar button{width:100%}}'
  ].join('');
  document.head.appendChild(css);

  var bar = document.createElement('div');
  bar.className = 'ck-bar';
  bar.innerHTML =
    '<p>Мы используем cookie: они нужны, чтобы сайт помнил твой заказ и оплата дошла до конца. ' +
    'Подробности в <a href="https://disk.yandex.ru/i/R0ZyhCkG86cKQg" target="_blank" rel="noopener">политике обработки данных</a>.</p>' +
    '<button type="button">Хорошо</button>';
  document.body.appendChild(bar);
  requestAnimationFrame(function () { bar.classList.add('on'); });

  bar.querySelector('button').addEventListener('click', function () {
    try { localStorage.setItem(KEY, '1'); } catch (e) {}
    bar.classList.remove('on');
    setTimeout(function () { bar.remove(); }, 500);
  });
})();
