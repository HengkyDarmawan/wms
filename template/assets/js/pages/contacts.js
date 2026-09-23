/* NexaDash — apps/contacts.html */
(function ($) {
  'use strict';

  var CONTACTS = [
    ['Sarah Chen', 'Head of Product', 'Nexa Technologies', 'Product', 'sarah@nexa.io', '+65 8123 4455', 'Singapore', 47],
    ['Marcus Webb', 'Staff Engineer', 'Nexa Technologies', 'Engineering', 'marcus@nexa.io', '+49 170 5550 118', 'Berlin', 68],
    ['Lena Iversen', 'Design Lead', 'Nexa Technologies', 'Design', 'lena@nexa.io', '+1 512 555 0142', 'Austin', 32],
    ['Tom Baker', 'Support Lead', 'Nexa Technologies', 'Support', 'tom@nexa.io', '+1 206 555 0139', 'Seattle', 15],
    ['Ava Novak', 'Product Designer', 'Nexa Technologies', 'Design', 'ava@nexa.io', '+62 812 5555 118', 'Jakarta', 5],
    ['Chen Wei', 'CTO', 'Orbit Labs', 'Engineering', 'chen@orbit.app', '+65 8123 9902', 'Singapore', 25],
    ['Amina Diallo', 'Head of Ops', 'Pulse Co.', 'Sales', 'amina@pulse.co', '+221 77 555 0113', 'Dakar', 31],
    ['Piotr Nowak', 'IT Director', 'Vertex GmbH', 'Engineering', 'piotr@vertex.pl', '+48 601 555 022', 'Warsaw', 57],
    ['Hana Sato', 'Support Lead APAC', 'Kumo Inc.', 'Support', 'hana@kumo.jp', '+81 90 5555 8842', 'Tokyo', 20],
    ['Nadia Rahman', 'Group PM', 'Lumen ID', 'Product', 'nadia@lumen.id', '+62 812 5555 118', 'Jakarta', 36],
    ['Olivia Brown', 'CISO', 'Atlas UK', 'Engineering', 'olivia@atlas.uk', '+44 7700 900118', 'London', 44],
    ['Ravi Menon', 'Engineering Manager', 'Indra Systems', 'Engineering', 'ravi@indra.in', '+91 98765 55012', 'Bangalore', 52],
    ['Sofia Rossi', 'Design Systems Lead', 'Vela Studio', 'Design', 'sofia@vela.it', '+39 333 555 0177', 'Milan', 26],
    ['Diego Alvarez', 'VP Revenue', 'Sol Group', 'Sales', 'diego@sol.mx', '+52 55 5555 0188', 'Toronto', 51],
    ['Emma Wilson', 'Data Analyst', 'North Data', 'Data', 'emma@north.ca', '+1 604 555 0164', 'Vancouver', 24],
    ['Ingrid Larsen', 'PM Onboarding', 'Fjord AS', 'Product', 'ingrid@fjord.no', '+47 400 55 118', 'Oslo', 28]
  ];

  var DEPT_COLOR = { Product: 'primary', Engineering: 'info', Design: 'warning', Support: 'success', Sales: 'danger', Data: 'secondary' };

  function esc(s) { return $('<span>').text(s).html(); }

  function card(c) {
    return '<div class="col-xl-3 col-lg-4 col-sm-6 contact-item" data-dept="' + c[3] + '">' +
      '<div class="card h-100 nx-card-lift nx-contact-card"><div class="card-body">' +
      '<span class="nx-avatar nx-avatar-xl mb-3"><img src="https://i.pravatar.cc/128?img=' + c[7] + '" alt=""></span>' +
      '<h3 class="h5 mb-0">' + esc(c[0]) + '</h3>' +
      '<p class="small text-muted mb-2">' + esc(c[1]) + '</p>' +
      '<span class="badge badge-soft-' + (DEPT_COLOR[c[3]] || 'secondary') + ' mb-3">' + esc(c[3]) + '</span>' +
      '<ul class="list-unstyled small text-muted text-start mb-3">' +
      '<li class="d-flex gap-2 py-1"><i class="bi bi-building"></i><span class="text-truncate">' + esc(c[2]) + '</span></li>' +
      '<li class="d-flex gap-2 py-1"><i class="bi bi-envelope"></i><span class="text-truncate">' + esc(c[4]) + '</span></li>' +
      '<li class="d-flex gap-2 py-1"><i class="bi bi-telephone"></i><span class="text-truncate">' + esc(c[5]) + '</span></li>' +
      '<li class="d-flex gap-2 py-1"><i class="bi bi-geo-alt"></i><span class="text-truncate">' + esc(c[6]) + '</span></li>' +
      '</ul>' +
      '<div class="d-flex gap-2"><a class="btn btn-sm btn-soft-primary flex-grow-1" href="apps-mail.html"><i class="bi bi-envelope me-1"></i>Email</a>' +
      '<a class="btn btn-sm btn-soft-secondary flex-grow-1" href="apps-chat.html"><i class="bi bi-chat-dots me-1"></i>Chat</a></div>' +
      '</div></div></div>';
  }

  function row(c) {
    return '<tr class="contact-item" data-dept="' + c[3] + '">' +
      '<td><div class="nx-table-user"><span class="nx-avatar"><img src="https://i.pravatar.cc/64?img=' + c[7] + '" alt=""></span>' +
      '<div><div class="nx-tu-name">' + esc(c[0]) + '</div><div class="nx-tu-sub">' + esc(c[4]) + '</div></div></div></td>' +
      '<td>' + esc(c[2]) + '</td>' +
      '<td><span class="badge badge-soft-' + (DEPT_COLOR[c[3]] || 'secondary') + '">' + esc(c[3]) + '</span></td>' +
      '<td class="small nx-num">' + esc(c[5]) + '</td>' +
      '<td class="small">' + esc(c[6]) + '</td>' +
      '<td class="text-end"><div class="dropdown"><button class="btn btn-icon btn-sm btn-soft-secondary" data-bs-toggle="dropdown" aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>' +
      '<div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="apps-mail.html"><i class="bi bi-envelope"></i>Email</a>' +
      '<a class="dropdown-item" href="apps-chat.html"><i class="bi bi-chat-dots"></i>Chat</a>' +
      '<button class="dropdown-item text-danger btn-del-contact" type="button"><i class="bi bi-trash"></i>Delete</button></div></div></td></tr>';
  }

  function render() {
    $('#contactGrid').html(CONTACTS.map(card).join(''));
    $('#contactTableBody').html(CONTACTS.map(row).join(''));
    filter();
  }

  function filter() {
    var q = ($('#contactSearch').val() || '').toLowerCase();
    var dept = $('#contactDept').val();
    var shown = 0;
    $('#contactGrid .contact-item').each(function (i) {
      var ok = (!dept || $(this).data('dept') === dept) && $(this).text().toLowerCase().indexOf(q) > -1;
      $(this).toggle(ok);
      $('#contactTableBody .contact-item').eq(i).toggle(ok);
      if (ok) shown++;
    });
    $('#contactCount').text(shown);
  }

  document.addEventListener('nx:layout-ready', function () {
    render();

    $('#contactSearch').on('input', filter);
    $('#contactDept').on('change', filter);

    $('#cvGrid').on('click', function () {
      $(this).addClass('active'); $('#cvTable').removeClass('active');
      $('#contactGrid').removeClass('d-none'); $('#contactTable').addClass('d-none');
    });
    $('#cvTable').on('click', function () {
      $(this).addClass('active'); $('#cvGrid').removeClass('active');
      $('#contactTable').removeClass('d-none'); $('#contactGrid').addClass('d-none');
    });

    $('#ncSave').on('click', function () {
      var name = $('#ncName').val().trim();
      if (!name) { $('#ncName').addClass('is-invalid'); return; }
      $('#ncName').removeClass('is-invalid');
      CONTACTS.unshift([
        name,
        $('#ncRole').val().trim() || 'Contact',
        $('#ncCompany').val().trim() || '—',
        $('#ncDept').val(),
        $('#ncEmail').val().trim() || '—',
        $('#ncPhone').val().trim() || '—',
        $('#ncCity').val().trim() || '—',
        (CONTACTS.length * 3) % 70 + 1
      ]);
      render();
      bootstrap.Modal.getInstance(document.getElementById('contactModal')).hide();
      $('#contactModal input').val('');
      Swal.fire({ icon: 'success', title: 'Contact added', text: name, timer: 1600, showConfirmButton: false });
    });

    $(document).on('click', '.btn-del-contact', function () {
      var $tr = $(this).closest('tr');
      var idx = $('#contactTableBody tr').index($tr);
      Swal.fire({ title: 'Delete this contact?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: nxCss('--nx-danger') })
        .then(function (r) { if (r.isConfirmed) { CONTACTS.splice(idx, 1); render(); } });
    });
  });
})(jQuery);
