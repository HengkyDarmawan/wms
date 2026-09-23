/* NexaDash — pages/user-detail.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    nxMountChart('#udChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'bar', height: 260, stacked: true },
        series: [
          { name: 'Commits', data: [42, 58, 36, 64, 71, 55, 82, 68, 91, 74, 86, 96] },
          { name: 'Reviews', data: [18, 24, 16, 28, 31, 22, 36, 29, 41, 33, 38, 44] },
          { name: 'Comments', data: [12, 16, 10, 18, 20, 14, 24, 19, 28, 22, 25, 30] }
        ],
        colors: nxChartColors().slice(0, 3),
        plotOptions: { bar: { borderRadius: 4, columnWidth: '58%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: ['Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep'], axisBorder: { show: false }, axisTicks: { show: false } },
        legend: { position: 'top', horizontalAlign: 'right' }
      });
    });

    $('#udEdit').on('click', function () {
      Swal.fire({
        title: 'Edit user', input: 'text', inputValue: 'Sarah Chen', inputLabel: 'Display name',
        showCancelButton: true, confirmButtonText: 'Save', confirmButtonColor: nxCss('--nx-primary')
      });
    });

    $('#udSuspend').on('click', function () {
      Swal.fire({
        title: 'Suspend this user?',
        text: 'They lose access immediately but the account is kept.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Suspend', confirmButtonColor: nxCss('--nx-danger')
      });
    });
  });
})(jQuery);
