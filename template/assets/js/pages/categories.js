/* NexaDash — commerce/categories.html */
(function ($) {
  'use strict';

  var TREE = [
    { name: 'Audio', slug: 'audio', count: 24, visible: true, children: [
      { name: 'Headphones', slug: 'headphones', count: 12, visible: true, children: [
        { name: 'Over-ear', slug: 'over-ear', count: 7, visible: true },
        { name: 'In-ear', slug: 'in-ear', count: 5, visible: true }
      ] },
      { name: 'Speakers', slug: 'speakers', count: 8, visible: true },
      { name: 'Microphones', slug: 'microphones', count: 4, visible: false }
    ] },
    { name: 'Wearables', slug: 'wearables', count: 11, visible: true, children: [
      { name: 'Smartwatches', slug: 'smartwatches', count: 7, visible: true },
      { name: 'Fitness bands', slug: 'fitness-bands', count: 4, visible: true }
    ] },
    { name: 'Accessories', slug: 'accessories', count: 31, visible: true, children: [
      { name: 'Keyboards', slug: 'keyboards', count: 9, visible: true },
      { name: 'Mice', slug: 'mice', count: 8, visible: true },
      { name: 'Bags', slug: 'bags', count: 14, visible: true }
    ] },
    { name: 'Home', slug: 'home', count: 9, visible: true, children: [
      { name: 'Lighting', slug: 'lighting', count: 6, visible: true },
      { name: 'Desk', slug: 'desk', count: 3, visible: false }
    ] }
  ];

  function esc(s) { return $('<span>').text(s).html(); }

  function branch(nodes, path) {
    return nodes.map(function (n) {
      var full = path ? path + ' / ' + n.name : n.name;
      var kids = n.children && n.children.length;
      return '<li' + (kids ? ' class="has-kids"' : '') + '>' +
        '<div class="nx-tree-row" data-name="' + esc(n.name) + '" data-slug="' + esc(n.slug) + '" data-path="' + esc(full) + '">' +
        '<i class="bi bi-chevron-right nx-tree-caret' + (kids ? '' : ' empty') + '"></i>' +
        '<i class="bi ' + (kids ? 'bi-folder' : 'bi-tag') + '"></i>' +
        '<span class="flex-grow-1">' + esc(n.name) + '</span>' +
        '<span class="badge badge-soft-secondary">' + n.count + '</span>' +
        (n.visible ? '' : '<i class="bi bi-eye-slash text-muted" title="Hidden"></i>') +
        '</div>' +
        (kids ? '<ul>' + branch(n.children, full) + '</ul>' : '') +
        '</li>';
    }).join('');
  }

  function flatten(nodes, depth, out) {
    out = out || [];
    depth = depth || 0;
    nodes.forEach(function (n) {
      out.push({ name: n.name, slug: n.slug, count: n.count, visible: n.visible, depth: depth });
      if (n.children) flatten(n.children, depth + 1, out);
    });
    return out;
  }

  document.addEventListener('nx:layout-ready', function () {
    $('#catTree').html(branch(TREE, ''));

    var flat = flatten(TREE);
    $('#catTable').html(flat.map(function (c) {
      return '<tr><td><span style="padding-left:' + (c.depth * 18) + 'px">' +
        (c.depth ? '<i class="bi bi-arrow-return-right text-muted me-1"></i>' : '') + esc(c.name) + '</span></td>' +
        '<td class="small text-muted"><code>' + esc(c.slug) + '</code></td>' +
        '<td class="nx-num">' + c.count + '</td>' +
        '<td><div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox"' + (c.visible ? ' checked' : '') + ' aria-label="Visible"></div></td></tr>';
    }).join(''));
    $('#catTotal').text(flat.length + ' categories');

    // Buka / tutup cabang.
    $('#catTree').on('click', '.nx-tree-row', function (e) {
      var $li = $(this).parent();
      if ($(e.target).hasClass('nx-tree-caret') || $li.hasClass('has-kids')) {
        $li.toggleClass('open');
        $(this).toggleClass('open');
      }
      $('.nx-tree-row').removeClass('selected');
      $(this).addClass('selected');

      $('#catName').val($(this).data('name'));
      $('#catSlug').val($(this).data('slug'));
      $('#catPath').text($(this).data('path'));
      $('#catFormTitle').text('Edit “' + $(this).data('name') + '”');
    });

    $('#catExpandAll').on('click', function () {
      var expand = $(this).text() === 'Expand all';
      $('#catTree li.has-kids').toggleClass('open', expand);
      $('#catTree .nx-tree-row').toggleClass('open', expand);
      $(this).text(expand ? 'Collapse all' : 'Expand all');
    });

    $('#catSearch').on('input', function () {
      var q = this.value.toLowerCase();
      if (!q) {
        $('#catTree li').show();
        return;
      }
      $('#catTree li').each(function () {
        var hit = $(this).children('.nx-tree-row').text().toLowerCase().indexOf(q) > -1;
        var childHit = $(this).find('li .nx-tree-row').filter(function () {
          return $(this).text().toLowerCase().indexOf(q) > -1;
        }).length > 0;
        $(this).toggle(hit || childHit);
        if (childHit) $(this).addClass('open').children('.nx-tree-row').addClass('open');
      });
    });

    $('#catName').on('input', function () {
      $('#catSlug').val(this.value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''));
    });

    $('#catSave').on('click', function () {
      var name = $('#catName').val().trim();
      if (!name) { $('#catName').addClass('is-invalid'); return; }
      $('#catName').removeClass('is-invalid');
      $('.nx-tree-row.selected span').first().text(name);
      Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Category saved', showConfirmButton: false, timer: 1600 });
    });

    $('#catDelete').on('click', function () {
      Swal.fire({
        title: 'Delete this category?',
        text: 'Products inside it are moved to Uncategorised.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Delete', confirmButtonColor: nxCss('--nx-danger')
      }).then(function (r) {
        if (r.isConfirmed) $('.nx-tree-row.selected').parent().remove();
      });
    });
  });
})(jQuery);
