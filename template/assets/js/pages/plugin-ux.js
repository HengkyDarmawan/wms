/* NexaDash — plugins/ux.html (driver.js, Tippy, NProgress, clipboard.js, Toastify, AOS, Typed) */
(function ($) {
  'use strict';

  var npTimer = null;

  function toast(text, bg) {
    Toastify({
      text: text,
      duration: 3200,
      close: true,
      gravity: 'top',
      position: 'right',
      stopOnFocus: true,
      style: { background: bg, borderRadius: '10px', boxShadow: 'var(--nx-shadow-lift)', fontWeight: '500' }
    }).showToast();
  }

  document.addEventListener('nx:layout-ready', function () {

    /* ===================== Driver.js ===================== */
    var drv = window.driver.js.driver;

    $('#tourStart').on('click', function () {
      drv({
        showProgress: true,
        nextBtnText: 'Next',
        prevBtnText: 'Back',
        doneBtnText: 'Finish',
        steps: [
          { element: '#nxSidebar', popover: { title: 'Navigation', description: 'Every section of the dashboard lives here. It collapses to an icon rail when you need more room.', side: 'right' } },
          { element: '#nxPaletteTrigger', popover: { title: 'Command palette', description: 'Press ⌘K anywhere to jump to a page or run an action without touching the mouse.', side: 'bottom' } },
          { element: '#tourStep1', popover: { title: 'First stop', description: 'Tours can point at any element, including ones added dynamically.', side: 'top' } },
          { element: '#tourStep2', popover: { title: 'Second stop', description: 'The overlay dims everything else so attention lands where you want it.', side: 'top' } },
          { element: '#tourStep3', popover: { title: 'Final stop', description: 'That is the whole tour. Driver.js is about 5 KB gzipped.', side: 'top' } },
          { element: '#nxThemeToggle', popover: { title: 'One more thing', description: 'Dark mode follows your system setting by default.', side: 'bottom' } }
        ]
      }).drive();
    });

    $('#tourHighlight').on('click', function () {
      drv().highlight({
        element: '#tourStep2',
        popover: { title: 'Single highlight', description: 'Use highlight() when you only need to draw attention to one thing — for example after an error.' }
      });
    });

    /* ===================== Tippy.js ===================== */
    tippy('#tipTop', { content: 'Placed above the trigger', placement: 'top' });
    tippy('#tipRight', { content: 'Placed to the right', placement: 'right' });
    tippy('#tipBottom', { content: 'Placed below', placement: 'bottom' });
    tippy('#tipLeft', { content: 'Placed to the left', placement: 'left' });

    tippy('#tipRich', {
      allowHTML: true,
      interactive: true,
      maxWidth: 280,
      content:
        '<div style="padding:4px">' +
        '<strong style="display:block;margin-bottom:4px">Pro plan</strong>' +
        '<span style="font-size:12px;opacity:.85">Unlimited projects, priority support and full API access.</span>' +
        '<div style="margin-top:8px"><a href="#" style="color:#a5b4fc;font-size:12px">See pricing →</a></div></div>'
    });
    tippy('#tipClick', { content: 'Opened on click, closes when you click away', trigger: 'click' });
    tippy('#tipFollow', { content: 'This one tracks the cursor', followCursor: true });
    tippy('#tipInline', { content: 'A glossary definition shown inline, without leaving the page.' });

    /* ===================== NProgress ===================== */
    NProgress.configure({ showSpinner: false, trickleSpeed: 160 });
    $('#npStart').on('click', function () {
      clearTimeout(npTimer);
      NProgress.start();
      npTimer = setTimeout(function () { NProgress.done(); toast('Page loaded', 'linear-gradient(135deg,#10b981,#059669)'); }, 2600);
    });
    $('#npInc').on('click', function () { NProgress.inc(0.2); });
    $('#npDone').on('click', function () { clearTimeout(npTimer); NProgress.done(); });

    /* ===================== clipboard.js ===================== */
    var clip = new ClipboardJS('#copyBtn');
    clip.on('success', function (e) {
      e.clearSelection();
      $('#copyBtn').html('<i class="bi bi-clipboard-check"></i>');
      setTimeout(function () { $('#copyBtn').html('<i class="bi bi-clipboard"></i>'); }, 1600);
      toast('Copied to clipboard', 'linear-gradient(135deg,#6366f1,#4338ca)');
    });
    clip.on('error', function () { toast('Press ⌘C to copy', 'linear-gradient(135deg,#f59e0b,#b45309)'); });

    /* ===================== Toastify ===================== */
    $('.toastify-demo').on('click', function () {
      var kind = $(this).data('kind');
      if (kind === 'success') return toast('✓ Your changes were saved', 'linear-gradient(135deg,#10b981,#059669)');
      if (kind === 'error') return toast('✕ We could not process that request', 'linear-gradient(135deg,#ef4444,#b91c1c)');
      if (kind === 'info') return toast('ℹ A new version is available', 'linear-gradient(135deg,#0ea5e9,#0369a1)');
      Toastify({
        text: 'Item moved to trash — Undo',
        duration: 5000,
        close: true,
        gravity: 'bottom',
        position: 'center',
        style: { background: 'linear-gradient(135deg,#334155,#0f172a)', borderRadius: '10px', cursor: 'pointer' },
        onClick: function () { toast('Restored', 'linear-gradient(135deg,#10b981,#059669)'); }
      }).showToast();
    });

    /* ===================== Typed.js ===================== */
    new Typed('#typedTarget', {
      strings: ['ship faster.', 'see every metric.', 'onboard your team.', 'stop exporting spreadsheets.'],
      typeSpeed: 55,
      backSpeed: 28,
      backDelay: 1600,
      loop: true
    });

    /* ===================== AOS ===================== */
    AOS.init({ duration: 650, once: false, offset: 40 });
  });
})(jQuery);
