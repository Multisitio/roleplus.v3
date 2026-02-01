(function($) {
    // Objeto KumbiaPHP
    $.KumbiaPHP = {
        // Carga con AJAX
        aAjax: function(event) {
            event.preventDefault();
            var mensaje = $(this).data('confirm');
            if (mensaje && !confirm(mensaje)) {
                return false;
            }
            var to = $(this).data('ajax');
            $(to).load(this.href);
            console.log([to, this.href]);
        },

        // Aplica la clase active al elemento eliminandolo de la clase especificada
        active: function(event) {
            event.preventDefault();
            var to = $(this).data('active');
            $(to).removeClass('active');
            $(this).addClass('active');
        },

        // Muestra mensaje de alerta
        alert: function() {
            alert($(this).data('alert'));
        },

        // Muestra mensaje de confirmacion
        confirm: function(event) {
            if (!confirm($(this).data('confirm'))) {
                event.preventDefault();
            }
        },

        // Aplica un efecto a un elemento
        effect: function(effect) {
            return function() {
                var to = $(this).data(effect);
                $(to)[effect]();
            }
        },

        // Envia formularios de manera asincronica, via POST y los carga en un contenedor
        formAjax: function(event) {
            event.preventDefault();

            var form = $(this).parents('form');
            var url = form.attr('action');
            var to = form.attr('data-ajax');
            var formData = form.serialize();
            var buttons = form.find('[type=submit]');
            buttons.attr('disabled', 'disabled');

            var btn_name = $(this).attr('name');
            if (btn_name != undefined) {
                var btn_val = $(this).val();
                formData = btn_name + '=' + btn_val + '&' + formData;
            }

            $.post(url, formData, function(data) {
                $(to).html(data).hide().show('slow');
                buttons.attr('disabled', null);
                console.log([to, this.href]);
            });
        },

        // Enlaza los eventos a las clases
        bind: function() {
            // Quita active a una clase para darla al elemento
            $('body').on('click', '[data-active]', this.active);

            $('body').on('click', 'a[data-ajax]', this.aAjax);

            $('body').on('submit', 'form[data-ajax] button', this.formAjax);

            $('body').on('click', '[data-alert]', this.alert);

            $('body').on('click', '[data-confirm]', this.confirm);

            $('body').on('click', '[data-hide]', this.effect('hide'));

            $('body').on('click', '[data-show]', this.effect('show'));

            $('body').on('click', '[data-toggle]', this.effect('toggle'));
        }
    };
    // Inicializa el plugin
    $.KumbiaPHP.bind();
})(jQuery);