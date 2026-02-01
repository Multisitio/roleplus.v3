$(function() {
    /* AJAX EN FORMULARIOS */
    $('body').on('click', 'form[data-sajax] button', function(e) {
        e.preventDefault();
        var form = $(this).parents('form');
        var url = form.attr('action');
        var to = form.attr('data-sajax');
        $(to).html('<div class="progress grey"><div class="indeterminate yellow"></div></div>');
        var formData = form.serialize();
        // Para capturar el boton:
        var btn_name = $(this).attr('name');
        if (btn_name != undefined) {
            var btn_val = $(this).val();
            var formData = btn_name + '=' + btn_val + '&' + formData;
            console.log([btn_name, btn_val]);
        }
        console.log([to, url, formData]);
        $.post(url, formData, function(data) {
            $(to).html(data);
            //console.log(data);
        });
    });

    /* AJAX EN FORMULARIOS */
    $('body').on('click', 'form[data-ajax] button', function(e) {
        e.preventDefault();
        var form = $(this).parents('form');
        var url = form.attr('action');
        var to = form.attr('data-ajax');
        $(to).html('<div class="progress grey"><div class="indeterminate yellow"></div></div>');
        var formData = new FormData(form[0]);
        // Para capturar el boton:
        var btn_name = $(this).attr('name');
        if (btn_name != undefined) {
            var btn_val = $(this).val();
            formData.append(btn_name, btn_val);
        }
        console.log([to, url]);
        $.post({
            url: url,
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            success: function(data) {
                if (data == '') {
                    $(to).html('<div class="progress grey"><div class="indeterminate red"></div></div>').load(url);
                } else {
                    $(to).html(data);
                }
            }
        });
        for (var val of formData.values()) {
            console.log(val);
        }
    });

    /* AJAX EN FORMULARIOS */
    $('body').on('click', 'form[data-addjax] button', function(e) {
        e.preventDefault();
        var form = $(this).parents('form');
        var url = form.attr('action');
        var to = form.attr('data-addjax');
        var formData = new FormData(form[0]);
        // Para capturar el boton:
        var btn_name = $(this).attr('name');
        if (btn_name != undefined) {
            var btn_val = $(this).val();
            formData.append(btn_name, btn_val);
        }
        $.post({
            url: url,
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            success: function(data) {
                $(to).append(data);
            }
        });
    });

    /* AJAX EN ENLACES */
    $('body').on('click', 'a[data-ajax]', function(e) {
        e.preventDefault();
        var url = $(this).attr('href');
        //$('.url a').attr('href', url).text(url);
        var to = $(this).attr('data-ajax');
        console.log([to, url]);

        $(to).html('<div class="progress grey"><div class="indeterminate yellow"></div></div>').load(url);

        /* GO TO TAB !!
        var tab = url.split('#')[1];
        if (tab != 'undefined')
        {
            $('ul.tabs').tabs('select_tab', tab);
        } */
    });
});