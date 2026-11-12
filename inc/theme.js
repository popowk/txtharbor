/* TxtHarbor themes: auto follows OS preference; manual choice is persisted locally. */
(function () {
  'use strict';
  var KEY = 'txtharbor.theme';
  var options = {auto: true, light: true, dark: true};
  var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  var selected = 'auto';

  try {
    var remembered = window.localStorage.getItem(KEY);
    if (Object.prototype.hasOwnProperty.call(options, remembered)) selected = remembered;
  } catch (_) { /* private mode: keep the automatic default */ }

  function currentScheme() {
    return selected === 'auto' ? (media && media.matches ? 'dark' : 'light') : selected;
  }
  function apply() {
    document.documentElement.setAttribute('data-theme-mode', selected);
    document.documentElement.setAttribute('data-theme', currentScheme());
    var label = document.getElementById('theme-label');
    if (label) label.textContent = selected === 'auto' ? '自动' : selected === 'light' ? '浅色' : '深色';
    var icon = document.querySelector('#theme-toggle .theme-icon');
    if (icon) icon.textContent = selected === 'auto' ? '◐' : selected === 'light' ? '☀' : '☾';
    var buttons = document.querySelectorAll('[data-theme-choice]');
    for (var i = 0; i < buttons.length; i++) {
      var active = buttons[i].getAttribute('data-theme-choice') === selected;
      buttons[i].setAttribute('aria-pressed', active ? 'true' : 'false');
      buttons[i].classList.toggle('active', active);
    }
    var toggle = document.getElementById('theme-toggle');
    if (toggle) toggle.title = selected === 'auto' ? '自动跟随系统（当前' + (currentScheme() === 'dark' ? '深色' : '浅色') + '）' : '当前' + (selected === 'light' ? '浅色' : '深色') + '主题';
  }
  function choose(next) {
    if (!Object.prototype.hasOwnProperty.call(options, next)) return;
    selected = next;
    try { window.localStorage.setItem(KEY, selected); } catch (_) { }
    apply();
  }
  function closeMenu() {
    var menu = document.getElementById('theme-menu');
    var toggle = document.getElementById('theme-toggle');
    if (menu) menu.hidden = true;
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
  }
  // Run immediately in <head> to avoid a theme flash.
  apply();
  if (media) {
    if (typeof media.addEventListener === 'function') media.addEventListener('change', function () { if (selected === 'auto') apply(); });
    else if (typeof media.addListener === 'function') media.addListener(function () { if (selected === 'auto') apply(); });
  }
  document.addEventListener('DOMContentLoaded', function () {
    apply();
    var toggle = document.getElementById('theme-toggle');
    var menu = document.getElementById('theme-menu');
    if (!toggle || !menu) return;
    toggle.addEventListener('click', function () {
      var open = menu.hidden;
      menu.hidden = !open;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    menu.addEventListener('click', function (event) {
      var button = event.target.closest('[data-theme-choice]');
      if (!button) return;
      choose(button.getAttribute('data-theme-choice'));
      closeMenu();
      toggle.focus();
    });
    document.addEventListener('click', function (event) {
      if (!event.target.closest('.theme-control')) closeMenu();
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') closeMenu();
    });
  });
  window.TxtHarborTheme = {choose: choose, getPreference: function () { return selected; }, getScheme: currentScheme};
})();
