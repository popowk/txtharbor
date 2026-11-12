/* TxtHarbor theme controller v1.4.1: main button switches immediately, arrow opens choices. */
(function () {
  'use strict';
  var KEY = 'txtharbor.theme';
  var options = { auto: true, light: true, dark: true };
  var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  var selected = 'auto';
  try {
    var saved = window.localStorage.getItem(KEY);
    if (Object.prototype.hasOwnProperty.call(options, saved)) selected = saved;
  } catch (_) { /* unavailable storage: use automatic mode */ }

  function scheme() {
    return selected === 'auto' ? (media && media.matches ? 'dark' : 'light') : selected;
  }
  function paint() {
    var root = document.documentElement;
    root.setAttribute('data-theme-mode', selected);
    root.setAttribute('data-theme', scheme());
    var label = document.getElementById('theme-label');
    if (label) label.textContent = selected === 'auto' ? '自动' : selected === 'light' ? '浅色' : '深色';
    var icon = document.querySelector('#theme-toggle .theme-icon');
    if (icon) icon.textContent = selected === 'auto' ? '◐' : selected === 'light' ? '☀' : '☾';
    var btn = document.getElementById('theme-toggle');
    if (btn) btn.title = '点击切换主题（当前：' + (selected === 'auto' ? '自动 · ' : '') + (scheme() === 'light' ? '浅色' : '深色') + '）';
    var choices = document.querySelectorAll('[data-theme-choice]');
    for (var i = 0; i < choices.length; i++) {
      var active = choices[i].getAttribute('data-theme-choice') === selected;
      choices[i].setAttribute('aria-pressed', active ? 'true' : 'false');
      choices[i].classList.toggle('active', active);
    }
  }
  function choose(mode) {
    if (!Object.prototype.hasOwnProperty.call(options, mode)) return;
    selected = mode;
    try { window.localStorage.setItem(KEY, mode); } catch (_) { }
    paint();
  }
  function cycle() {
    // The first click ALWAYS visibly changes the screen, including when auto is the OS theme.
    if (selected === 'auto') choose(scheme() === 'light' ? 'dark' : 'light');
    else choose(selected === 'light' ? 'dark' : 'light');
    closeMenu();
  }
  function closeMenu() {
    var menu = document.getElementById('theme-menu');
    var trigger = document.getElementById('theme-menu-toggle');
    if (menu) menu.hidden = true;
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
  }
  function toggleMenu() {
    var menu = document.getElementById('theme-menu');
    var trigger = document.getElementById('theme-menu-toggle');
    if (!menu || !trigger) return;
    var opening = menu.hidden;
    menu.hidden = !opening;
    trigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
  }
  function onReady() {
    paint();
    var toggle = document.getElementById('theme-toggle');
    var arrow = document.getElementById('theme-menu-toggle');
    var menu = document.getElementById('theme-menu');
    if (toggle) toggle.addEventListener('click', cycle);
    if (arrow) arrow.addEventListener('click', toggleMenu);
    if (menu) menu.addEventListener('click', function (event) {
      var target = event.target;
      var option = target && typeof target.closest === 'function' ? target.closest('[data-theme-choice]') : null;
      if (!option) return;
      choose(option.getAttribute('data-theme-choice'));
      closeMenu();
      if (toggle) toggle.focus();
    });
    document.addEventListener('click', function (event) {
      var target = event.target;
      if (!target || typeof target.closest !== 'function' || !target.closest('.theme-control')) closeMenu();
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') closeMenu();
    });
  }
  // Apply before first paint; do not wait for the body to finish loading.
  paint();
  if (media) {
    var refresh = function () { if (selected === 'auto') paint(); };
    if (typeof media.addEventListener === 'function') media.addEventListener('change', refresh);
    else if (typeof media.addListener === 'function') media.addListener(refresh);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', onReady);
  else onReady();
  window.TxtHarborTheme = { choose: choose, cycle: cycle, getPreference: function () { return selected; }, getScheme: scheme };
})();