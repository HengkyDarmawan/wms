/* NexaDash — plugins/documents.html (jsPDF, AutoTable, PDF.js, html2canvas, SheetJS) */
(function ($) {
  'use strict';

  var jsPDF = window.jspdf ? window.jspdf.jsPDF : null;
  var pdfDoc = null, pdfPage = 1, pdfScale = 1.1, lastBlobUrl = null, lastFileName = 'document.pdf';

  var SAMPLE = [
    { name: 'Sarah Chen', dept: 'Product', city: 'Singapore', salary: 128000 },
    { name: 'Marcus Webb', dept: 'Engineering', city: 'Berlin', salary: 121000 },
    { name: 'Lena Iversen', dept: 'Design', city: 'Austin', salary: 112000 },
    { name: 'Tom Baker', dept: 'Support', city: 'Tokyo', salary: 88000 },
    { name: 'Ava Novak', dept: 'Design', city: 'Jakarta', salary: 96000 },
    { name: 'Chen Wei', dept: 'Engineering', city: 'London', salary: 134000 }
  ];

  function renderSampleTable(rows, note) {
    $('#xlsxTable tbody').html(rows.map(function (r) {
      var vals = Object.values(r);
      return '<tr>' + vals.slice(0, 4).map(function (v, i) {
        return '<td' + (i === 3 ? ' class="text-end nx-num"' : '') + '>' +
          $('<span>').text(typeof v === 'number' ? v.toLocaleString() : v).html() + '</td>';
      }).join('') + '</tr>';
    }).join(''));
    if (note) $('#xlsxNote').text(note);
  }

  /* ===================== PDF.js ===================== */
  function renderPdfPage() {
    if (!pdfDoc) return;
    pdfDoc.getPage(pdfPage).then(function (page) {
      var viewport = page.getViewport({ scale: pdfScale });
      var canvas = document.getElementById('pdfCanvas');
      var ctx = canvas.getContext('2d');
      canvas.width = viewport.width;
      canvas.height = viewport.height;
      canvas.style.display = 'block';
      $('#pdfEmpty').hide();
      page.render({ canvasContext: ctx, viewport: viewport });
      $('#pdfPageNo').text(pdfPage);
      $('#pdfPageCount').text(pdfDoc.numPages);
      $('#pdfPrev').prop('disabled', pdfPage <= 1);
      $('#pdfNext').prop('disabled', pdfPage >= pdfDoc.numPages);
      $('#pdfZoomIn, #pdfZoomOut').prop('disabled', false);
    });
  }

  function previewPdf(doc, fileName) {
    var blob = doc.output('blob');
    if (lastBlobUrl) URL.revokeObjectURL(lastBlobUrl);
    lastBlobUrl = URL.createObjectURL(blob);
    lastFileName = fileName;
    $('#pdfDownload').prop('disabled', false);

    var data = doc.output('arraybuffer');
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
    pdfjsLib.getDocument({ data: data }).promise.then(function (d) {
      pdfDoc = d;
      pdfPage = 1;
      renderPdfPage();
    });
  }

  document.addEventListener('nx:layout-ready', function () {
    renderSampleTable(SAMPLE);

    /* ===================== jsPDF invoice ===================== */
    $('#pdfInvoice').on('click', function () {
      var doc = new jsPDF({ unit: 'pt', format: 'a4' });
      var client = $('#pdfClient').val() || 'Client';
      var number = $('#pdfNumber').val() || 'INV-0001';
      var count = Math.max(1, Math.min(40, parseInt($('#pdfRows').val(), 10) || 6));

      doc.setFont('helvetica', 'bold'); doc.setFontSize(20);
      doc.text('NexaDash', 40, 54);
      doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor(120);
      doc.text('Nexa Technologies Ltd.\n88 Harbour Road, Level 12\nSingapore 049908', 40, 70);

      doc.setFontSize(16); doc.setTextColor(30); doc.setFont('helvetica', 'bold');
      doc.text('Invoice ' + number, 555, 54, { align: 'right' });
      doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor(120);
      doc.text('Issued: September 1, 2026\nDue: September 30, 2026\nTerms: Net 30', 555, 70, { align: 'right' });

      doc.setTextColor(30); doc.setFontSize(10); doc.setFont('helvetica', 'bold');
      doc.text('Billed to', 40, 140);
      doc.setFont('helvetica', 'normal'); doc.setTextColor(90);
      doc.text(client, 40, 156);

      var items = [], subtotal = 0;
      for (var i = 0; i < count; i++) {
        var qty = (i % 5) + 1;
        var unit = [180, 59, 29, 120, 249][i % 5];
        var amount = qty * unit;
        subtotal += amount;
        items.push([
          ['Enterprise licence', 'Pro seat', 'Starter seat', 'Extra seats add-on', 'Priority support'][i % 5],
          qty,
          '$' + unit.toFixed(2),
          '$' + amount.toFixed(2)
        ]);
      }
      var tax = subtotal * 0.08;

      doc.autoTable({
        startY: 186,
        head: [['Description', 'Qty', 'Unit price', 'Amount']],
        body: items,
        theme: 'grid',
        headStyles: { fillColor: [99, 102, 241], textColor: 255, fontStyle: 'bold' },
        columnStyles: { 1: { halign: 'center' }, 2: { halign: 'right' }, 3: { halign: 'right' } },
        styles: { fontSize: 9, cellPadding: 6 }
      });

      var y = doc.lastAutoTable.finalY + 24;
      doc.setFontSize(10); doc.setTextColor(120);
      doc.text('Subtotal', 420, y); doc.text('$' + subtotal.toFixed(2), 555, y, { align: 'right' });
      doc.text('GST (8%)', 420, y + 16); doc.text('$' + tax.toFixed(2), 555, y + 16, { align: 'right' });
      doc.setFont('helvetica', 'bold'); doc.setTextColor(30); doc.setFontSize(12);
      doc.text('Total due', 420, y + 40); doc.text('$' + (subtotal + tax).toFixed(2), 555, y + 40, { align: 'right' });

      previewPdf(doc, number + '.pdf');
    });

    $('#pdfTable').on('click', function () {
      var doc = new jsPDF({ unit: 'pt', format: 'a4', orientation: 'landscape' });
      doc.setFont('helvetica', 'bold'); doc.setFontSize(14);
      doc.text('Team roster', 40, 44);
      doc.autoTable({
        startY: 62,
        html: '#xlsxTable',
        theme: 'striped',
        headStyles: { fillColor: [99, 102, 241], textColor: 255 },
        styles: { fontSize: 9, cellPadding: 6 }
      });
      previewPdf(doc, 'team-roster.pdf');
    });

    $('#pdfDownload').on('click', function () {
      if (!lastBlobUrl) return;
      var a = document.createElement('a');
      a.href = lastBlobUrl; a.download = lastFileName; a.click();
    });

    $('#pdfPrev').on('click', function () { if (pdfPage > 1) { pdfPage--; renderPdfPage(); } });
    $('#pdfNext').on('click', function () { if (pdfDoc && pdfPage < pdfDoc.numPages) { pdfPage++; renderPdfPage(); } });
    $('#pdfZoomIn').on('click', function () { pdfScale = Math.min(2.4, pdfScale + 0.2); renderPdfPage(); });
    $('#pdfZoomOut').on('click', function () { pdfScale = Math.max(0.5, pdfScale - 0.2); renderPdfPage(); });

    /* ===================== html2canvas ===================== */
    function capture() {
      return html2canvas(document.getElementById('captureTarget'), {
        backgroundColor: nxCss('--nx-card-bg'),
        scale: 2,
        logging: false
      });
    }
    $('#capShot').on('click', function () {
      capture().then(function (canvas) {
        var url = canvas.toDataURL('image/png');
        $('#capResult').attr('src', url).show();
        $('#capEmpty').hide();
        $('#capDownload').attr('href', url).removeClass('disabled');
      });
    });
    $('#capPdf').on('click', function () {
      capture().then(function (canvas) {
        var doc = new jsPDF({ unit: 'pt', format: 'a4' });
        var w = 420;
        var h = canvas.height * w / canvas.width;
        doc.setFont('helvetica', 'bold'); doc.setFontSize(14);
        doc.text('Captured widget', 40, 48);
        doc.addImage(canvas.toDataURL('image/png'), 'PNG', 40, 68, w, h);
        previewPdf(doc, 'captured-widget.pdf');
      });
    });

    /* ===================== SheetJS ===================== */
    function currentRows() {
      return $('#xlsxTable tbody tr').map(function () {
        var c = $(this).children();
        return {
          Name: c.eq(0).text(), Department: c.eq(1).text(),
          City: c.eq(2).text(), Salary: c.eq(3).text()
        };
      }).get();
    }
    $('#xlsxExport').on('click', function () {
      var ws = XLSX.utils.json_to_sheet(currentRows());
      var wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Team');
      XLSX.writeFile(wb, 'team.xlsx');
    });
    $('#csvExport').on('click', function () {
      var ws = XLSX.utils.json_to_sheet(currentRows());
      var wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Team');
      XLSX.writeFile(wb, 'team.csv', { bookType: 'csv' });
    });
    $('#xlsxImport').on('change', function () {
      var f = this.files && this.files[0];
      if (!f) return;
      var reader = new FileReader();
      reader.onload = function (e) {
        var wb = XLSX.read(e.target.result, { type: 'array' });
        var rows = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]]);
        if (!rows.length) { $('#xlsxNote').text('That file had no readable rows.'); return; }
        renderSampleTable(rows.slice(0, 50), 'Imported ' + rows.length + ' row(s) from ' + f.name + ' — sheet "' + wb.SheetNames[0] + '".');
      };
      reader.readAsArrayBuffer(f);
    });
  });
})(jQuery);
