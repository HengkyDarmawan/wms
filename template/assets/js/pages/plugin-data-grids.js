/* NexaDash — plugins/data-grids.html (Grid.js + Tabulator) */
(function ($) {
  'use strict';

  var NAMES = ['Sarah Chen', 'Marcus Webb', 'Lena Iversen', 'Tom Baker', 'Ava Novak', 'Chen Wei',
    'Amina Diallo', 'Piotr Nowak', 'Hana Sato', 'Lucas Meyer', 'Nadia Rahman', 'Olivia Brown'];
  var DEPTS = ['Product', 'Engineering', 'Design', 'Support', 'Data', 'Sales'];
  var CITIES = ['Singapore', 'Berlin', 'Austin', 'Tokyo', 'Jakarta', 'London'];
  var STATUS = ['Active', 'On leave', 'Probation'];

  function rows(n) {
    return Array.from({ length: n }, function (_, i) {
      return {
        id: i + 1,
        name: NAMES[i % NAMES.length],
        email: NAMES[i % NAMES.length].toLowerCase().replace(/[^a-z]/g, '.') + '@nexa.io',
        dept: DEPTS[i % DEPTS.length],
        city: CITIES[i % CITIES.length],
        salary: 62000 + (i % 9) * 8400,
        status: STATUS[i % 3],
        avatar: (i * 7) % 70 + 1
      };
    });
  }

  var DATA = rows(48);
  var tabTable = null, tabWide = null, grouped = false;

  function tabulatorTheme() {
    // Tabulator membaca warna dari CSS, jadi cukup samakan variabelnya di sini.
    return {
      layout: 'fitColumns',
      height: '420px',
      placeholder: 'No matching rows'
    };
  }

  document.addEventListener('nx:layout-ready', function () {

    /* ===================== Grid.js ===================== */
    new gridjs.Grid({
      columns: ['Name', 'Email', 'Department', 'City', { name: 'Salary', formatter: function (c) { return '$' + c.toLocaleString(); } }],
      data: DATA.slice(0, 24).map(function (r) { return [r.name, r.email, r.dept, r.city, r.salary]; }),
      search: true,
      sort: true,
      pagination: { limit: 8, summary: true },
      className: { table: 'table' }
    }).render(document.getElementById('gridBasic'));

    new gridjs.Grid({
      columns: [
        {
          name: 'Member',
          formatter: function (_, row) {
            return gridjs.html(
              '<div class="nx-table-user"><span class="nx-avatar nx-avatar-md">' +
              '<img src="https://i.pravatar.cc/56?img=' + row.cells[3].data + '" alt=""></span>' +
              '<div><div class="nx-tu-name">' + row.cells[0].data + '</div>' +
              '<div class="nx-tu-sub">' + row.cells[4].data + '</div></div></div>'
            );
          }
        },
        {
          name: 'Status',
          formatter: function (cell) {
            var map = { 'Active': 'success', 'On leave': 'warning', 'Probation': 'info' };
            return gridjs.html('<span class="badge badge-soft-' + map[cell] + '">' + cell + '</span>');
          }
        },
        'Department',
        { name: 'avatar', hidden: true },
        { name: 'email', hidden: true }
      ],
      data: DATA.slice(0, 18).map(function (r) { return [r.name, r.status, r.dept, r.avatar, r.email]; }),
      sort: true,
      pagination: { limit: 6 },
      className: { table: 'table' }
    }).render(document.getElementById('gridRich'));

    var big = rows(10000);
    new gridjs.Grid({
      columns: ['#', 'Name', 'Department', 'City'],
      data: function () {
        return Promise.resolve(big.map(function (r) { return [r.id, r.name, r.dept, r.city]; }));
      },
      search: true,
      sort: true,
      pagination: { limit: 6, summary: true },
      className: { table: 'table' }
    }).render(document.getElementById('gridBig'));

    /* ===================== Tabulator ===================== */
    tabTable = new Tabulator('#tabTable', $.extend(tabulatorTheme(), {
      data: DATA.slice(0, 30),
      columns: [
        { title: 'Name', field: 'name', editor: 'input', headerFilter: false, minWidth: 160 },
        { title: 'Email', field: 'email', editor: 'input', minWidth: 200 },
        { title: 'Department', field: 'dept', editor: 'list', editorParams: { values: DEPTS } },
        { title: 'City', field: 'city', editor: 'list', editorParams: { values: CITIES } },
        {
          title: 'Salary', field: 'salary', editor: 'number', hozAlign: 'right',
          formatter: 'money', formatterParams: { symbol: '$', precision: 0 },
          bottomCalc: 'sum', bottomCalcFormatter: 'money', bottomCalcFormatterParams: { symbol: '$', precision: 0 }
        },
        {
          title: 'Status', field: 'status', editor: 'list', editorParams: { values: STATUS },
          formatter: function (cell) {
            var map = { 'Active': 'success', 'On leave': 'warning', 'Probation': 'info' };
            return '<span class="badge badge-soft-' + map[cell.getValue()] + '">' + cell.getValue() + '</span>';
          }
        },
        {
          title: '', width: 54, hozAlign: 'center', headerSort: false,
          formatter: function () { return '<i class="bi bi-trash text-danger"></i>'; },
          cellClick: function (e, cell) { cell.getRow().delete(); }
        }
      ]
    }));

    tabTable.on('cellEdited', function (cell) {
      $('#tabLastEdit').text(cell.getField() + ' → ' + cell.getValue() + ' (row ' + (cell.getRow().getPosition()) + ')');
    });

    $('#tabAddRow').on('click', function () {
      tabTable.addRow({ name: 'New member', email: 'new@nexa.io', dept: 'Product', city: 'Singapore', salary: 60000, status: 'Probation' }, true);
    });
    $('#tabCsv').on('click', function () { tabTable.download('csv', 'team.csv'); });
    $('#tabJson').on('click', function () { tabTable.download('json', 'team.json'); });
    $('#tabGroup').on('click', function () {
      grouped = !grouped;
      tabTable.setGroupBy(grouped ? 'dept' : false);
    });
    $('#tabFilter').on('input', function () {
      var v = this.value;
      if (v) tabTable.setFilter('name', 'like', v);
      else tabTable.clearFilter();
    });

    tabWide = new Tabulator('#tabWide', {
      data: DATA.slice(0, 20),
      layout: 'fitDataFill',
      height: '360px',
      columns: [
        { title: 'Name', field: 'name', frozen: true, minWidth: 180 },
        { title: 'Email', field: 'email', width: 220 },
        { title: 'Department', field: 'dept', width: 150 },
        { title: 'City', field: 'city', width: 140 },
        { title: 'Status', field: 'status', width: 130 },
        { title: 'Salary', field: 'salary', hozAlign: 'right', width: 140, formatter: 'money', formatterParams: { symbol: '$', precision: 0 }, bottomCalc: 'sum', bottomCalcFormatter: 'money', bottomCalcFormatterParams: { symbol: '$', precision: 0 } },
        { title: 'Avg. salary', field: 'salary', hozAlign: 'right', width: 150, formatter: 'money', formatterParams: { symbol: '$', precision: 0 }, bottomCalc: 'avg', bottomCalcFormatter: 'money', bottomCalcFormatterParams: { symbol: '$', precision: 0 } },
        { title: 'Headcount', field: 'id', hozAlign: 'right', width: 130, bottomCalc: 'count' }
      ]
    });
  });
})(jQuery);
