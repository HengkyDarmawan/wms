/* NexaDash — plugins/inputs.html (Tom Select, signature_pad, Pickr, Cleave, Autosize) */
(function ($) {
  'use strict';

  var PEOPLE = [
    { value: '47', name: 'Sarah Chen', role: 'Head of Product' },
    { value: '68', name: 'Marcus Webb', role: 'Staff Engineer' },
    { value: '32', name: 'Lena Iversen', role: 'Design Lead' },
    { value: '15', name: 'Tom Baker', role: 'Support Lead' },
    { value: '5', name: 'Ava Novak', role: 'Product Designer' }
  ];

  var sigPad = null;

  function resizeCanvas(canvas) {
    // signature_pad menggambar pada koordinat piksel nyata, jadi skala DPR wajib disetel.
    var ratio = Math.max(window.devicePixelRatio || 1, 1);
    var data = sigPad && !sigPad.isEmpty() ? sigPad.toData() : null;
    canvas.width = canvas.offsetWidth * ratio;
    canvas.height = canvas.offsetHeight * ratio;
    canvas.getContext('2d').scale(ratio, ratio);
    if (sigPad) { sigPad.clear(); if (data) sigPad.fromData(data); }
  }

  document.addEventListener('nx:layout-ready', function () {

    /* ===================== Tom Select ===================== */
    new TomSelect('#tsSingle', { create: false });
    new TomSelect('#tsMulti', { plugins: ['remove_button'], create: false });
    new TomSelect('#tsTags', { persist: false, create: true, plugins: ['remove_button'], delimiter: ',' });
    new TomSelect('#tsPeople', {
      options: PEOPLE,
      items: ['47'],
      valueField: 'value',
      labelField: 'name',
      searchField: ['name', 'role'],
      plugins: ['remove_button'],
      render: {
        option: function (d, escape) {
          return '<div class="d-flex align-items-center gap-2 py-1">' +
            '<span class="nx-avatar nx-avatar-sm"><img src="https://i.pravatar.cc/48?img=' + escape(d.value) + '" alt=""></span>' +
            '<span><strong class="d-block">' + escape(d.name) + '</strong>' +
            '<span class="small text-muted">' + escape(d.role) + '</span></span></div>';
        },
        item: function (d, escape) {
          return '<div class="d-inline-flex align-items-center gap-1">' +
            '<span class="nx-avatar nx-avatar-xs"><img src="https://i.pravatar.cc/40?img=' + escape(d.value) + '" alt=""></span>' +
            escape(d.name) + '</div>';
        }
      }
    });

    /* ===================== Signature Pad ===================== */
    var canvas = document.getElementById('sigCanvas');
    sigPad = new SignaturePad(canvas, {
      penColor: '#0f172a',
      backgroundColor: 'rgba(0,0,0,0)',
      minWidth: 0.8,
      maxWidth: 2.4
    });
    resizeCanvas(canvas);
    $(window).on('resize', function () { resizeCanvas(canvas); });

    $('#sigColor').on('input', function () { sigPad.penColor = this.value; });
    $('#sigClear').on('click', function () { sigPad.clear(); });
    $('#sigUndo').on('click', function () {
      var data = sigPad.toData();
      if (data.length) { data.pop(); sigPad.fromData(data); }
    });
    function showSignature(url) {
      $('#sigPreview').attr('src', url).show();
      $('#sigEmpty').hide();
    }
    $('#sigPng').on('click', function () {
      if (sigPad.isEmpty()) return;
      showSignature(sigPad.toDataURL('image/png'));
    });
    $('#sigSvg').on('click', function () {
      if (sigPad.isEmpty()) return;
      showSignature(sigPad.toDataURL('image/svg+xml'));
    });

    /* ===================== Pickr ===================== */
    function makePickr(el, start) {
      return Pickr.create({
        el: el,
        theme: 'nano',
        default: start,
        swatches: ['#6366f1', '#22d3ee', '#10b981', '#f59e0b', '#ef4444', '#f472b6', '#a855f7', '#0f172a'],
        components: {
          preview: true, opacity: true, hue: true,
          interaction: { hex: true, rgba: true, hsla: true, input: true, save: true }
        }
      }).on('change', function (color) {
        var hex = color.toHEXA().toString();
        $('#pickrValue').text(hex);
        $('#pickrPreview').css('background', hex);
      }).on('save', function (color, instance) {
        instance.hide();
      });
    }
    makePickr('#pickrBrand', '#6366f1');
    makePickr('#pickrAccent', '#22d3ee');

    /* ===================== Cleave.js ===================== */
    new Cleave('#clCard', {
      creditCard: true,
      onCreditCardTypeChanged: function (type) {
        var map = { visa: 'bi-credit-card-2-front', mastercard: 'bi-credit-card-2-back', amex: 'bi-credit-card-fill' };
        var label = type && type !== 'unknown' ? type : '';
        $('#clBrand').html('<i class="bi ' + (map[type] || 'bi-credit-card') + '"></i>' +
          (label ? '<span class="small ms-1 text-capitalize">' + label + '</span>' : ''));
      }
    });
    new Cleave('#clDate', { date: true, datePattern: ['m', 'y'] });
    // Mode phone Cleave menuntut berkas formatter per negara; pola blok cukup di sini.
    new Cleave('#clPhone', { blocks: [4, 4, 4], delimiter: ' ', numericOnly: true });
    new Cleave('#clMoney', { numeral: true, numeralThousandsGroupStyle: 'thousand', delimiter: '.' });

    /* ===================== Autosize ===================== */
    autosize(document.getElementById('clNote'));
  });
})(jQuery);
