/* NexaDash — pages/connections.html */
(function ($) {
  'use strict';

  var CONNECTED = [
    { name: 'Slack', icon: 'bi-slack', color: 'primary', account: 'nexa-product.slack.com', scope: 'Post messages, read channel list', since: 'Mar 2024' },
    { name: 'Google Analytics', icon: 'bi-google', color: 'warning', account: 'GA4 · 284019442', scope: 'Read reports', since: 'Jun 2024' },
    { name: 'Stripe', icon: 'bi-credit-card', color: 'info', account: 'acct_1PqX…', scope: 'Read charges, write refunds', since: 'Jan 2023' },
    { name: 'GitHub', icon: 'bi-github', color: 'secondary', account: 'nexa-technologies', scope: 'Read repositories and deployments', since: 'Feb 2024' }
  ];

  var AVAILABLE = [
    { name: 'Notion', icon: 'bi-journal-text', color: 'secondary', desc: 'Embed dashboards in your docs.' },
    { name: 'Figma', icon: 'bi-pencil-square', color: 'danger', desc: 'Sync design tokens both ways.' },
    { name: 'Jira', icon: 'bi-kanban', color: 'primary', desc: 'Link issues to roadmap items.' },
    { name: 'Zendesk', icon: 'bi-headset', color: 'success', desc: 'Pull ticket volume into the support dashboard.' },
    { name: 'HubSpot', icon: 'bi-people', color: 'warning', desc: 'Two-way sync of contacts and deals.' },
    { name: 'Segment', icon: 'bi-diagram-3', color: 'info', desc: 'Route events to every downstream tool.' },
    { name: 'Snowflake', icon: 'bi-snow', color: 'info', desc: 'Query the warehouse directly.' },
    { name: 'PagerDuty', icon: 'bi-bell', color: 'danger', desc: 'Page the on-call when a metric breaks.' }
  ];

  var SOCIAL = [
    { name: 'Google', icon: 'bi-google', color: 'danger', handle: 'aigars@nexadash.io', linked: true },
    { name: 'GitHub', icon: 'bi-github', color: 'secondary', handle: 'aigars', linked: true },
    { name: 'X', icon: 'bi-twitter-x', color: 'secondary', handle: '', linked: false },
    { name: 'LinkedIn', icon: 'bi-linkedin', color: 'primary', handle: '', linked: false }
  ];

  function esc(s) { return $('<span>').text(s).html(); }

  function renderConnected() {
    $('#cnConnected').html(CONNECTED.map(function (c, i) {
      return '<div class="nx-row-item px-4 cn-row" data-i="' + i + '">' +
        '<span class="nx-icon-sq ' + c.color + '"><i class="bi ' + c.icon + '"></i></span>' +
        '<div class="nx-row-main"><div class="nx-row-title">' + esc(c.name) + '</div>' +
        '<div class="nx-row-sub">' + esc(c.account) + ' &middot; connected ' + c.since + '</div>' +
        '<div class="small text-muted mt-1"><i class="bi bi-key me-1"></i>' + esc(c.scope) + '</div></div>' +
        '<div class="d-flex gap-2"><button class="btn btn-xs btn-soft-secondary cn-config" type="button">Configure</button>' +
        '<button class="btn btn-xs btn-soft-danger cn-disconnect" type="button">Disconnect</button></div></div>';
    }).join('') || '<p class="text-muted small text-center py-4 mb-0">Nothing connected yet.</p>');
    $('#cnCount').text(CONNECTED.length + ' connected');
  }

  function renderAvailable() {
    var q = ($('#cnSearch').val() || '').toLowerCase();
    var list = AVAILABLE.filter(function (a) {
      return (a.name + ' ' + a.desc).toLowerCase().indexOf(q) > -1;
    });
    $('#cnAvailable').html(list.length ? list.map(function (a, i) {
      return '<div class="col-md-6"><div class="card h-100 nx-card-lift"><div class="card-body d-flex align-items-start gap-3">' +
        '<span class="nx-icon-sq ' + a.color + '"><i class="bi ' + a.icon + '"></i></span>' +
        '<div class="flex-grow-1 min-w-0"><div class="fw-semibold">' + esc(a.name) + '</div>' +
        '<div class="small text-muted">' + esc(a.desc) + '</div></div>' +
        '<button class="btn btn-xs btn-soft-primary cn-connect" data-name="' + esc(a.name) + '" data-icon="' + a.icon + '" data-color="' + a.color + '" type="button">Connect</button>' +
        '</div></div></div>';
    }).join('') : '<div class="col-12"><p class="text-muted small text-center py-4 mb-0">No integration matches that search.</p></div>');
  }

  function renderSocial() {
    $('#cnSocial').html(SOCIAL.map(function (s, i) {
      return '<div class="nx-row-item px-4"><span class="nx-icon-sq ' + s.color + '"><i class="bi ' + s.icon + '"></i></span>' +
        '<div class="nx-row-main"><div class="nx-row-title">' + esc(s.name) + '</div>' +
        '<div class="nx-row-sub">' + (s.linked ? esc(s.handle) : 'Not connected') + '</div></div>' +
        '<div class="form-check form-switch mb-0"><input class="form-check-input cn-social" type="checkbox" role="switch" data-i="' + i + '"' +
        (s.linked ? ' checked' : '') + ' aria-label="' + esc(s.name) + '"></div></div>';
    }).join(''));
  }

  document.addEventListener('nx:layout-ready', function () {
    renderConnected();
    renderAvailable();
    renderSocial();

    $('#cnSearch').on('input', renderAvailable);

    $('#cnAvailable').on('click', '.cn-connect', function () {
      var name = $(this).data('name');
      CONNECTED.push({
        name: name, icon: $(this).data('icon'), color: $(this).data('color'),
        account: name.toLowerCase() + '-workspace', scope: 'Read only', since: 'just now'
      });
      renderConnected();
      Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: name + ' connected', showConfirmButton: false, timer: 1800 });
    });

    $('#cnConnected').on('click', '.cn-disconnect', function () {
      var i = $(this).closest('.cn-row').data('i');
      var name = CONNECTED[i].name;
      Swal.fire({
        title: 'Disconnect ' + name + '?',
        text: 'The access token is revoked immediately.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Disconnect', confirmButtonColor: nxCss('--nx-danger')
      }).then(function (r) {
        if (!r.isConfirmed) return;
        CONNECTED.splice(i, 1);
        renderConnected();
      });
    });

    $('#cnConnected').on('click', '.cn-config', function () {
      var i = $(this).closest('.cn-row').data('i');
      Swal.fire({ icon: 'info', title: CONNECTED[i].name + ' settings', text: CONNECTED[i].scope, confirmButtonColor: nxCss('--nx-primary') });
    });

    $('#cnSocial').on('change', '.cn-social', function () {
      var s = SOCIAL[$(this).data('i')];
      s.linked = this.checked;
      if (s.linked && !s.handle) s.handle = 'aigars';
      renderSocial();
    });

    $('#cnAddHook').on('click', function () {
      Swal.fire({
        title: 'Add a webhook', input: 'url', inputPlaceholder: 'https://example.com/hooks/nexa',
        showCancelButton: true, confirmButtonText: 'Add', confirmButtonColor: nxCss('--nx-primary')
      }).then(function (r) {
        if (!r.isConfirmed || !r.value) return;
        var url = r.value.replace(/^https?:\/\//, '');
        var $row = $('<div class="nx-row-item"><span class="nx-icon-sq secondary"><i class="bi bi-arrow-repeat"></i></span>' +
          '<div class="nx-row-main"><div class="nx-row-title text-truncate hook-url"></div><div class="nx-row-sub">No events yet</div></div>' +
          '<span class="badge badge-soft-secondary">New</span></div>');
        $row.find('.hook-url').text(url);
        $('#cnHooks').append($row);
      });
    });
  });
})(jQuery);
