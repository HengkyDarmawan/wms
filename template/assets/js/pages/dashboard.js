/* NexaDash — index.html (SaaS dashboard) */
(function ($) {
  'use strict';

  var MONTHS = ['Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'];
  var REVENUE = [28.4, 30.1, 31.8, 33.2, 34.9, 36.4, 38.0, 40.2, 41.8, 43.5, 45.9, 48.2];
  var EXPENSES = [18.2, 18.9, 19.4, 20.1, 20.8, 21.2, 22.0, 22.8, 23.1, 23.9, 24.6, 25.2];
  var DAYS30 = Array.from({ length: 30 }, function (_, i) { return 'Aug ' + (i + 13 > 31 ? 'Sep ' + (i - 18) : i + 13); });
  var currentRange = 12;

  function sliceRange(arr) {
    if (currentRange === 1) {
      // 30 hari: interpolasi sederhana dari 2 bulan terakhir.
      return Array.from({ length: 30 }, function (_, i) {
        var base = arr[arr.length - 2];
        var end = arr[arr.length - 1];
        return +(base + (end - base) * (i / 29) + Math.sin(i / 3) * 0.6).toFixed(1);
      });
    }
    return arr.slice(-currentRange);
  }
  function rangeLabels() {
    if (currentRange === 1) return DAYS30;
    return MONTHS.slice(-currentRange);
  }

  function buildRevenue() {
    var base = nxApexBase();
    return $.extend(true, base, {
      chart: { type: 'area', height: 320 },
      series: [
        { name: 'Revenue', data: sliceRange(REVENUE) },
        { name: 'Expenses', data: sliceRange(EXPENSES) }
      ],
      colors: [nxCss('--nx-chart-1'), nxCss('--nx-chart-2')],
      stroke: { curve: 'smooth', width: 2.5 },
      fill: {
        type: 'gradient',
        gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 95] }
      },
      dataLabels: { enabled: false },
      xaxis: { categories: rangeLabels(), axisBorder: { show: false }, axisTicks: { show: false }, tickAmount: 11 },
      yaxis: { labels: { formatter: function (v) { return '$' + v.toFixed(0) + 'K'; } } },
      legend: { position: 'top', horizontalAlign: 'right', markers: { radius: 12 } },
      tooltip: $.extend(base.tooltip, { y: { formatter: function (v) { return '$' + v.toFixed(1) + 'K'; } } })
    });
  }

  function buildDonut() {
    var base = nxApexBase();
    return $.extend(true, base, {
      chart: { type: 'donut', height: 300 },
      series: [42, 28, 18, 12],
      labels: ['Direct', 'Affiliate', 'Marketplace', 'Partners'],
      colors: nxChartColors().slice(0, 4),
      dataLabels: { enabled: false },
      legend: { position: 'bottom' },
      stroke: { colors: [nxCss('--nx-card-bg')] },
      plotOptions: {
        pie: {
          donut: {
            size: '76%',
            labels: {
              show: true,
              name: { fontSize: '13px', color: nxCss('--nx-text-muted') },
              value: { fontSize: '24px', fontWeight: 800, color: nxCss('--nx-text'), formatter: function (v) { return v + '%'; } },
              total: { show: true, label: 'Total Sales', color: nxCss('--nx-text-muted'), formatter: function () { return '$48.2K'; } }
            }
          }
        }
      }
    });
  }

  function buildSprint() {
    var base = nxApexBase();
    return $.extend(true, base, {
      chart: { type: 'radialBar', height: 210 },
      series: [68],
      labels: ['Complete'],
      colors: [nxCss('--nx-chart-1')],
      plotOptions: {
        radialBar: {
          hollow: { size: '62%' },
          track: { background: nxCss('--nx-secondary-subtle') },
          dataLabels: {
            name: { fontSize: '12px', color: nxCss('--nx-text-muted'), offsetY: 18 },
            value: { fontSize: '26px', fontWeight: 800, color: nxCss('--nx-text'), offsetY: -12 }
          }
        }
      }
    });
  }

  function buildSpark(data, colorVar) {
    return function () {
      return {
        chart: { type: 'area', height: 46, sparkline: { enabled: true }, fontFamily: 'Inter, sans-serif' },
        series: [{ name: '', data: data }],
        colors: [nxCss(colorVar)],
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0 } },
        tooltip: { enabled: false }
      };
    };
  }

  function initCharts() {
    nxMountChart('#revenueChart', buildRevenue);
    nxMountChart('#channelDonut', buildDonut);
    nxMountChart('#sprintRadial', buildSprint);
    nxMountChart('#spark1', buildSpark([30, 34, 32, 38, 36, 42, 48], '--nx-chart-1'));
    nxMountChart('#spark2', buildSpark([9, 10, 11, 10, 12, 12.4, 12.8], '--nx-chart-2'));
    nxMountChart('#spark3', buildSpark([40, 36, 38, 34, 35, 33, 34], '--nx-chart-3'));
    nxMountChart('#spark4', buildSpark([99.9, 99.95, 99.92, 99.97, 99.96, 99.98, 99.98], '--nx-chart-4'));
  }

  document.addEventListener('nx:layout-ready', function () {
    initCharts();

    // Date range picker (default: last 30 days).
    var end = new Date();
    var start = new Date();
    start.setDate(end.getDate() - 29);
    flatpickr('#dashRange', { mode: 'range', dateFormat: 'M j, Y', defaultDate: [start, end] });

    // Tab range revenue chart.
    $('#revRange').on('click', '.nav-link', function () {
      $('#revRange .nav-link').removeClass('active');
      $(this).addClass('active');
      currentRange = parseInt($(this).data('range'), 10);
      // Rebuild chart pertama pada registry (revenue) via event tema-safe:
      var el = document.querySelector('#revenueChart');
      el.innerHTML = '';
      new ApexCharts(el, buildRevenue()).render();
    });

    // Export dummy.
    $('#btnExport').on('click', function () {
      Swal.fire({ icon: 'success', title: 'Report exported', text: 'dashboard-report.csv has been generated.', timer: 1800, showConfirmButton: false });
    });

    // Search filter tabel.
    $('#orderSearch').on('input', function () {
      var q = this.value.toLowerCase();
      $('#ordersTable tbody tr').each(function () {
        $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1);
      });
    });

    // Delete row + konfirmasi.
    $('#ordersTable').on('click', '.btn-delete-row', function () {
      var $tr = $(this).closest('tr');
      Swal.fire({
        title: 'Delete this order?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        confirmButtonColor: nxCss('--nx-danger')
      }).then(function (res) {
        if (res.isConfirmed) $tr.fadeOut(200, function () { $tr.remove(); });
      });
    });
  });
})(jQuery);
