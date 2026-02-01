$("body").on("click", "[data-add]", function(e) {
    e.preventDefault();
    var t = $(this).data("add"),
        n = $(this).parent().data("add_to"),
        r = $(n).val();
    console.log([t, n, r]);
    $(n).val(r + t);
});

$('body').on('change', '[data-change_toggle]', function() {
    var to = $(this).data('change_toggle');
    var val = $(this).val();
    if ($(to + '.' + val).length) {
        $(to + '.' + val + ' input').val('');
        $(to).hide();
        $(to + '.' + val).show();
    }
});

$('body').on('click', '[data-clone]', function() {
    var el = $(this).data('clone');
    var to = $(this).data('to');
    $(el).clone().appendTo(to).show();
});

$('body').on('click', 'a[data-load_append_to]', function(eve) {
    eve.preventDefault();
    var pagina = $(this).attr('data-pagina');
    ++pagina;
    $(this).attr('data-pagina', pagina);
    var url = $(this).attr('href') + pagina;
    var to = $(this).attr('data-load_append_to');
    console.log([to, url]);
    $('.ajax.hide').load(url, function() {
        var grid = document.querySelector('#grid');
        $('.ajax.hide .caja').each(function() {
            salvattore.appendElements(grid, [$(this)[0]]);
        });
        $('.ajax.hide').text('');
    });
});

$('body').on('click', '[data-remove]', function(eve) {
    eve.preventDefault();
    var to = $(this).data('remove');
    if (to == 'parent') {
        $(this).parent().remove();
    } else {
        $(to).remove();
    }
});

$('body').on('click', '[data-selected]', function() {
    var to = $(this).data('selected');
    $(to).removeClass('selected');
    $(this).addClass('selected');
});

$('body').on('click', '[data-show_pass]', function(eve) {
    eve.preventDefault();
    var input_password = $(this).data('show_pass');
    var img = $(this).parent().find('[src*="eye"]');
    if ($(input_password).attr('type') == 'text') {
        $(img).attr('src', '/img/icons/eye-s.svg');
        $(input_password).attr('type', 'password');
    } else {
        $(img).attr('src', '/img/icons/eye-off-s.svg');
        $(input_password).attr('type', 'text');
    }
});

$('body').on('click', '[data-toast]', function() {
    var toast = $(this).data('toast');
    $('.ajax.show').load('/index/toast', { 'toast': toast });
});