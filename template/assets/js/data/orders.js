/* NexaDash — dummy data pesanan (dipakai orders.js & customers.js). */
window.NX_ORDERS = (function () {
  var customers = [
    ['Maria Gomez', 'maria@acme.io', 45], ['James Lee', 'james@nova.dev', 13],
    ['Chen Wei', 'chen@orbit.app', 25], ['Amina Diallo', 'amina@pulse.co', 31],
    ['Piotr Nowak', 'piotr@vertex.pl', 57], ['Hana Sato', 'hana@kumo.jp', 20],
    ['Lucas Meyer', 'lucas@delta.de', 8], ['Nadia Rahman', 'nadia@lumen.id', 36],
    ['Olivia Brown', 'olivia@atlas.uk', 44], ['Diego Alvarez', 'diego@sol.mx', 51],
    ['Emma Wilson', 'emma@north.ca', 24], ['Yusuf Kaya', 'yusuf@bora.tr', 60]
  ];
  var products = ['Pro Plan (annual)', 'Starter Plan', 'Enterprise (custom)', 'Pro Plan (monthly)', 'Add-on: Extra seats', 'Aurora Headphones', 'Orbit Keyboard', 'Nova Smartwatch'];
  var amounts = [588, 29, 2400, 59, 120, 129, 159, 249];
  var statuses = [
    ['Completed', 'success'], ['Pending', 'warning'], ['Cancelled', 'danger'], ['Refunded', 'secondary']
  ];
  var payments = ['Visa •••• 4821', 'PayPal', 'Mastercard •••• 1194', 'Bank transfer', 'Apple Pay'];

  // Tanggal menurun rapi dari 11 Sep 2026 ke belakang, dua pesanan per hari.
  var MONTH = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  function dateAt(i) {
    var d = new Date(2026, 8, 11);
    d.setDate(d.getDate() - Math.floor(i / 2));
    return MONTH[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
  }

  var rows = [];
  for (var i = 0; i < 48; i++) {
    var c = customers[i % customers.length];
    var pi = i % products.length;
    var st = statuses[i % 7 === 0 ? 2 : (i % 5 === 0 ? 3 : (i % 3 === 0 ? 1 : 0))];
    rows.push({
      id: 'ORD-' + (1042 - i),
      name: c[0], email: c[1], avatar: c[2],
      product: products[pi],
      date: dateAt(i),
      amount: amounts[pi],
      payment: payments[i % payments.length],
      status: st[0], statusColor: st[1]
    });
  }
  return rows;
})();
