/* NexaDash — plugins/diagrams.html (Mermaid) */
(function ($) {
  'use strict';

  var SAMPLES = {
    flow: 'flowchart LR\n' +
      '  A[Pull request] --> B{CI passes?}\n' +
      '  B -- no --> C[Fix and push again]\n' +
      '  C --> A\n' +
      '  B -- yes --> D[Review]\n' +
      '  D --> E{Approved?}\n' +
      '  E -- no --> C\n' +
      '  E -- yes --> F[Merge to main]\n' +
      '  F --> G[Deploy to staging]\n' +
      '  G --> H{Smoke tests}\n' +
      '  H -- pass --> I[Deploy to production]\n' +
      '  H -- fail --> J[Roll back]',

    sequence: 'sequenceDiagram\n' +
      '  autonumber\n' +
      '  actor C as Customer\n' +
      '  participant W as Web app\n' +
      '  participant A as API\n' +
      '  participant S as Stripe\n' +
      '  C->>W: Submit checkout\n' +
      '  W->>A: POST /orders\n' +
      '  A->>S: Create payment intent\n' +
      '  S-->>A: requires_action\n' +
      '  A-->>W: 3DS challenge\n' +
      '  W-->>C: Show bank prompt\n' +
      '  C->>S: Approve\n' +
      '  S-->>A: payment_intent.succeeded\n' +
      '  A-->>W: Order confirmed\n' +
      '  W-->>C: Receipt',

    state: 'stateDiagram-v2\n' +
      '  [*] --> Trialing\n' +
      '  Trialing --> Active: card added\n' +
      '  Trialing --> Expired: 14 days pass\n' +
      '  Active --> PastDue: payment fails\n' +
      '  PastDue --> Active: retry succeeds\n' +
      '  PastDue --> Cancelled: 3 retries fail\n' +
      '  Active --> Cancelled: user cancels\n' +
      '  Cancelled --> [*]\n' +
      '  Expired --> [*]',

    er: 'erDiagram\n' +
      '  CUSTOMER ||--o{ ORDER : places\n' +
      '  ORDER ||--|{ ORDER_ITEM : contains\n' +
      '  PRODUCT ||--o{ ORDER_ITEM : "appears in"\n' +
      '  CUSTOMER ||--o{ INVOICE : receives\n' +
      '  INVOICE ||--|| ORDER : bills\n' +
      '  CUSTOMER {\n' +
      '    string id PK\n' +
      '    string email\n' +
      '    string country\n' +
      '  }\n' +
      '  ORDER {\n' +
      '    string id PK\n' +
      '    datetime placed_at\n' +
      '    string status\n' +
      '  }'
  };

  var STATIC = {
    mmPipeline: 'flowchart TD\n' +
      '  A([Commit]) --> B[Build]\n' +
      '  B --> C[Unit tests]\n' +
      '  C --> D[Container image]\n' +
      '  D --> E{Branch}\n' +
      '  E -- main --> F[Staging]\n' +
      '  E -- tag --> G[Production]\n' +
      '  F --> H[Smoke tests]\n' +
      '  H --> G\n' +
      '  G --> I([Done])',
    mmSequence: SAMPLES.sequence,
    mmGantt: 'gantt\n' +
      '  title Release 2.5 plan\n' +
      '  dateFormat YYYY-MM-DD\n' +
      '  axisFormat %d %b\n' +
      '  section Billing\n' +
      '  Retry pipeline      :done,    b1, 2026-09-01, 10d\n' +
      '  Stripe migration    :active,  b2, 2026-09-08, 14d\n' +
      '  section Onboarding\n' +
      '  Wizard design       :done,    o1, 2026-09-01, 7d\n' +
      '  Wizard build        :active,  o2, 2026-09-09, 12d\n' +
      '  Copy review         :         o3, after o2, 4d\n' +
      '  section Analytics\n' +
      '  Discovery           :active,  a1, 2026-09-05, 12d\n' +
      '  Prototype           :         a2, after a1, 10d\n' +
      '  Release             :milestone, m1, 2026-10-05, 0d',
    mmState: SAMPLES.state,
    mmPie: 'pie showData\n' +
      '  title Active subscriptions by plan\n' +
      '  "Pro" : 486\n' +
      '  "Starter" : 312\n' +
      '  "Enterprise" : 94\n' +
      '  "Trial" : 168'
  };

  var counter = 0;

  function config() {
    var dark = nxTheme() === 'dark';
    return {
      startOnLoad: false,
      securityLevel: 'strict',
      theme: dark ? 'dark' : 'default',
      fontFamily: 'Inter, system-ui, sans-serif',
      themeVariables: {
        primaryColor: nxCss('--nx-primary-subtle'),
        primaryTextColor: nxCss('--nx-text'),
        primaryBorderColor: nxCss('--nx-primary'),
        lineColor: nxCss('--nx-text-muted'),
        background: nxCss('--nx-card-bg'),
        mainBkg: nxCss('--nx-primary-subtle'),
        secondaryColor: nxCss('--nx-success-subtle'),
        tertiaryColor: nxCss('--nx-warning-subtle')
      }
    };
  }

  function draw(targetId, source) {
    var el = document.getElementById(targetId);
    if (!el) return Promise.resolve();
    counter++;
    return mermaid.render('mmid-' + counter, source)
      .then(function (res) { el.innerHTML = res.svg; })
      .catch(function (err) {
        el.innerHTML = '<p class="text-danger small mb-0">' + $('<span>').text(String(err.message || err)).html() + '</p>';
      });
  }

  function drawAll() {
    mermaid.initialize(config());
    Object.keys(STATIC).forEach(function (id) { draw(id, STATIC[id]); });
    draw('mmOutput', $('#mmSource').val());
  }

  document.addEventListener('nx:layout-ready', function () {
    $('#mmSource').val(SAMPLES.flow);
    drawAll();

    $('#mmRender').on('click', function () {
      $('#mmError').addClass('d-none');
      mermaid.parse($('#mmSource').val())
        .then(function () { draw('mmOutput', $('#mmSource').val()); })
        .catch(function (err) {
          $('#mmError').removeClass('d-none').text(String(err.message || err).split('\n')[0]);
        });
    });

    $('.mm-sample').on('click', function () {
      $('#mmSource').val(SAMPLES[$(this).data('sample')]);
      $('#mmRender').trigger('click');
    });

    $('#mmSvg').on('click', function () {
      var svg = document.querySelector('#mmOutput svg');
      if (!svg) return;
      var blob = new Blob([svg.outerHTML], { type: 'image/svg+xml' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'diagram.svg';
      a.click();
      URL.revokeObjectURL(a.href);
    });
  });

  document.addEventListener('nx:theme-changed', function () {
    if (window.mermaid) drawAll();
  });
})(jQuery);
