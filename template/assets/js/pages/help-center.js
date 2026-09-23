/* NexaDash — pages/help-center.html */
(function ($) {
  'use strict';

  var CATEGORIES = [
    { name: 'Getting started', icon: 'bi-rocket-takeoff', color: 'primary', count: 18 },
    { name: 'Dashboards', icon: 'bi-grid-1x2', color: 'info', count: 32 },
    { name: 'Billing', icon: 'bi-credit-card', color: 'success', count: 21 },
    { name: 'Security', icon: 'bi-shield-lock', color: 'warning', count: 17 },
    { name: 'API & webhooks', icon: 'bi-code-slash', color: 'danger', count: 34 },
    { name: 'Integrations', icon: 'bi-plug', color: 'secondary', count: 20 }
  ];

  var ARTICLES = [
    ['Getting started', 'Create your first dashboard', 'Pick a template, drag widgets in, publish. Four minutes end to end.', 4210],
    ['Getting started', 'Invite your team and set roles', 'Send an invitation, choose a role, and control what each person sees.', 3184],
    ['Dashboards', 'Choosing the right chart', 'Trends need lines, comparisons need bars, and pies almost never help.', 2960],
    ['Dashboards', 'Showing data freshness', 'How to surface staleness without cluttering the page.', 1842],
    ['API & webhooks', 'Rate limits explained', '600 requests per minute per key on Pro, 6,000 on Enterprise.', 5120],
    ['API & webhooks', 'Verifying webhook signatures', 'Compute an HMAC-SHA256 of the raw body using your endpoint secret.', 3402],
    ['API & webhooks', 'Retry behaviour on failures', 'Exponential backoff for 24 hours, then the endpoint is disabled.', 2210],
    ['Security', 'Setting up SSO with SAML', 'Map identity provider groups onto workspace roles automatically.', 2884],
    ['Security', 'Enforcing two-factor authentication', 'Require 2FA workspace-wide from Settings → Security.', 1960],
    ['Security', 'Data residency regions', 'Choose eu-west-1, us-east-1 or ap-southeast-1 when creating a workspace.', 1408],
    ['Billing', 'How seats are counted', 'Anyone who signs in during a billing period counts as an active seat.', 2640],
    ['Billing', 'Why a card was declined', 'Most declines come from the issuer; we retry three times over five days.', 2102],
    ['Billing', 'Adding your VAT number to invoices', 'Tax details entered under Billing appear on every future invoice.', 1188],
    ['Integrations', 'Connecting Stripe', 'Read charges and issue refunds without leaving the dashboard.', 1744],
    ['Integrations', 'Exporting to Slack', 'Post a scheduled summary into any channel.', 1290],
    ['Dashboards', 'Scheduled report exports', 'Email a CSV or PDF daily, weekly or monthly.', 1622]
  ];

  var category = '';

  function esc(s) { return $('<span>').text(s).html(); }

  function renderCategories() {
    $('#hcCategories').html(CATEGORIES.map(function (c) {
      return '<div class="col-xl-2 col-md-4 col-6"><button class="card w-100 h-100 nx-card-lift hc-cat border-0 text-start" data-cat="' + esc(c.name) + '" type="button">' +
        '<div class="card-body text-center">' +
        '<span class="nx-icon-sq ' + c.color + ' mx-auto mb-2"><i class="bi ' + c.icon + '"></i></span>' +
        '<div class="fw-semibold small">' + esc(c.name) + '</div>' +
        '<div class="small text-muted">' + c.count + ' articles</div></div></button></div>';
    }).join(''));
  }

  function renderArticles() {
    var q = ($('#hcSearch').val() || '').toLowerCase().trim();
    var list = ARTICLES.filter(function (a) {
      return (!category || a[0] === category) &&
        (a[1] + ' ' + a[2] + ' ' + a[0]).toLowerCase().indexOf(q) > -1;
    });

    $('#hcResultTitle').text(q ? 'Results for “' + q + '”' : (category || 'Popular articles'));
    $('#hcCount').text(list.length);
    $('#hcArticles').html(list.length ? list.map(function (a) {
      return '<a class="nx-row-item px-4 text-decoration-none" href="page-faq.html">' +
        '<span class="nx-icon-sq secondary"><i class="bi bi-file-earmark-text"></i></span>' +
        '<div class="nx-row-main"><div class="nx-row-title">' + esc(a[1]) + '</div>' +
        '<div class="nx-row-sub">' + esc(a[2]) + '</div></div>' +
        '<div class="text-end"><span class="badge badge-soft-secondary">' + esc(a[0]) + '</span>' +
        '<div class="small text-muted mt-1">' + a[3].toLocaleString() + ' views</div></div></a>';
    }).join('') : '<p class="text-muted small text-center py-5 mb-0"><i class="bi bi-search d-block fs-3 mb-2"></i>Nothing matched. Try a broader term or open a ticket.</p>');
  }

  document.addEventListener('nx:layout-ready', function () {
    renderCategories();
    renderArticles();

    $('#hcSearch').on('input', function () { category = ''; renderArticles(); });

    $('.hc-chip').on('click', function () {
      $('#hcSearch').val($(this).text());
      category = '';
      renderArticles();
    });

    $('#hcCategories').on('click', '.hc-cat', function () {
      category = category === $(this).data('cat') ? '' : $(this).data('cat');
      $('#hcSearch').val('');
      $('.hc-cat').removeClass('border border-primary');
      if (category) $(this).addClass('border border-primary');
      renderArticles();
    });
  });
})(jQuery);
