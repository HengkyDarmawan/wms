/* NexaDash — commerce/product-add.html */
(function ($) {
  'use strict';

  Dropzone.autoDiscover = false;

  function slugify(s) {
    return s.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  }

  document.addEventListener('nx:layout-ready', function () {
    new Quill('#paEditor', {
      theme: 'snow',
      placeholder: 'Describe the product…',
      modules: { toolbar: [[{ header: [2, 3, false] }], ['bold', 'italic'], [{ list: 'bullet' }, { list: 'ordered' }], ['link'], ['clean']] }
    });

    new Dropzone('#paDropzone', {
      url: '#', autoProcessQueue: false, addRemoveLinks: true, maxFilesize: 10,
      dictDefaultMessage: '<i class="bi bi-image fs-1 d-block mb-2"></i>Drop product photos here or click to browse'
    });

    $('#paCategory, #paVendor, #paStatus, #paShipClass').select2({ theme: 'bootstrap-5', width: '100%', minimumResultsForSearch: -1 });
    $('#paTags').select2({ theme: 'bootstrap-5', width: '100%', tags: true, placeholder: 'Add tags' });

    // Pratinjau listing mengikuti nama dan meta.
    $('#paName').on('input', function () {
      var v = this.value || 'Aurora Wireless Headphones';
      $('#paTitlePreview').text(v);
      $('#paSlug').val(slugify(v));
      $('#paSlugPreview').text(slugify(v));
    });
    $('#paSlug').on('input', function () { $('#paSlugPreview').text(slugify(this.value)); });

    function metaCount() {
      var v = $('#paMeta').val();
      $('#paMetaCount').text(v.length);
      $('#paDescPreview').text(v || 'No meta description yet.');
      $('#paMetaCount').toggleClass('text-danger', v.length > 160);
    }
    $('#paMeta').on('input', metaCount);
    metaCount();

    // Margin dihitung dari harga dan biaya.
    function margin() {
      var price = parseFloat($('#paPrice').val()) || 0;
      var cost = parseFloat($('#paCost').val()) || 0;
      var profit = price - cost;
      $('#paProfit').text('$' + profit.toFixed(2));
      $('#paMargin').text(price ? (profit / price * 100).toFixed(1) + '%' : '—');
    }
    $('#paPrice, #paCost').on('input', margin);
    margin();

    // Varian.
    $('#paAddVariant').on('click', function () {
      $('#paVariants').append('<tr>' +
        '<td><input class="form-control form-control-sm" placeholder="Option"></td>' +
        '<td><input class="form-control form-control-sm" placeholder="Value"></td>' +
        '<td><input class="form-control form-control-sm" placeholder="0.00"></td>' +
        '<td><input class="form-control form-control-sm" placeholder="0"></td>' +
        '<td class="text-end"><button class="btn btn-xs btn-soft-danger pa-del-variant" type="button" aria-label="Remove"><i class="bi bi-x"></i></button></td></tr>');
    });
    $('#paVariants').on('click', '.pa-del-variant', function () { $(this).closest('tr').remove(); });

    $('#btnPublish').on('click', function () {
      var name = $('#paName').val().trim();
      if (!name) {
        $('#paName').addClass('is-invalid').trigger('focus');
        Swal.fire({ icon: 'warning', title: 'Product name is required', timer: 1800, showConfirmButton: false });
        return;
      }
      $('#paName').removeClass('is-invalid');
      Swal.fire({ icon: 'success', title: 'Product published', text: name, timer: 1800, showConfirmButton: false });
    });
  });
})(jQuery);
