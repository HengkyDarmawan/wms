/* NexaDash — commerce/customer-detail.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    nxMountChart('#cdSpendChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'bar', height: 260 },
        series: [{ name: 'Spend', data: [220, 0, 411, 742, 129, 288, 515, 0, 340, 462, 0, 598] }],
        colors: [nxCss('--nx-chart-1')],
        plotOptions: { bar: { borderRadius: 5, columnWidth: '55%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: ['Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep'], axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { formatter: function (v) { return '$' + Math.round(v); } } }
      });
    });

    $('#cdEdit').on('click', function () {
      Swal.fire({
        title: 'Edit customer',
        input: 'text', inputValue: 'Maria Gomez', inputLabel: 'Display name',
        showCancelButton: true, confirmButtonText: 'Save', confirmButtonColor: nxCss('--nx-primary')
      }).then(function (r) {
        if (r.isConfirmed && r.value) Swal.fire({ icon: 'success', title: 'Saved', timer: 1400, showConfirmButton: false });
      });
    });

    $('#cdAddAddress').on('click', function () {
      Swal.fire({ icon: 'info', title: 'Add address', text: 'This would open the address form.', confirmButtonColor: nxCss('--nx-primary') });
    });
  });
})(jQuery);
