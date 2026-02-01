(function ($) {
    $.KumbiaPHP = {
        aAjax: function (eve) {
            eve.preventDefault();
            /*var mensaje = $(this).data('confirm');
            if (mensaje && !confirm(mensaje)) {
                return false;
            }*/
            var to = $(this).data('ajax');
            loadHtml(to, this.href);
            console.log([to, this.href]);
        },

        active: function () {
            var to = $(this).data('active');
            $(to).removeClass('active');
            $(this).addClass('active');
        },

        alert: function () {
            alert($(this).data('alert'));
        },

        clone_append: function () {
            var params = $(this).data('clone_append');
            var el = params.split(', ')[0];
            var to = params.split(', ')[1];
            $(el).clone().appendTo(to);
        },

        confirm: function (eve) {
            if (!confirm($(this).data('confirm'))) {
                eve.preventDefault();
                eve.stopImmediatePropagation();
            }
        },

        effect: function (effect) {
            return function () {
                var to = $(this).data(effect);
                $(to)[effect]();
            };
        },

        formAjax: function (eve) {
            eve.preventDefault();

            var form = $(this).parents('form');
            $(form).find('[style*="none"]').remove();

            var url = form.attr('action');
            var to;
            if (form.data('ajax_append')) {
                to = form.attr('data-ajax_append');
            } else if (form.data('ajax_prepend')) {
                to = form.attr('data-ajax_prepend');
            } else {
                to = form.attr('data-ajax');
            }

            var formData = new FormData(form[0]);

            var buttons = form.find('[type="submit"]');
            buttons.attr('disabled', 'disabled');

            var btn_name = $(this).attr('name');
            if (btn_name !== undefined) {
                var btn_val = $(this).val();
                formData.append(btn_name, btn_val);
            }

            var fileData = form.find('[type="file"]');
            if (fileData.length) {
                // ya se añade con FormData(form[0])
                fileData.prop('files')[0];
                formData.append('file', fileData);
            }

            $.ajax({
                url: url,
                type: "POST",
                dataType: "html",
                data: formData,
                cache: false,
                contentType: false,
                processData: false
            }).done(function (html) {
                var mode;
                if (form.data('ajax_append')) {
                    // en append/prepend también bloqueamos scripts
                    var nodes = $.parseHTML(html, document, false);
                    $(to).append(nodes).show();
                    mode = 'append';
                } else if (form.data('ajax_prepend')) {
                    var nodes2 = $.parseHTML(html, document, false);
                    $(to).prepend(nodes2).show();
                    mode = 'prepend';
                } else {
                    injectHtml(to, html);
                    mode = 'normal';
                }
                buttons.attr('disabled', null);
                console.log([to, url, mode]);
            });
        },

        live: function () {
            var to = $(this).data('live');
            var href = $(this).data('href');
            loadHtml(to, href, { 'keywords': this.value });
            console.log([to, href, this.value]);
        },

        remove: function () {
            var to = $(this).data('remove');
            var el = to;

            if (to === 'parent parent') {
                el = $(this).parent().parent();
            } else if (to === 'parent') {
                el = $(this).parent();
            }

            $(el).remove();
        },

        selectAjax: function () {
            var to = $(this).data('ajax');
            var href = $(this).data('href') + this.value;
            loadHtml(to, href);
            console.log([to, href]);
        },

        selectRedirect: function () {
            var href = $(this).data('redirect') + this.value;
            location.href = href;
        },

        selectToggle: function () {
            var to = $(this).data('change_toggle');
            var val = $(this).val();
            if (val && $(to + '.' + val).length) {
                $(to + '.' + val + ' input').val('');
                $(to).toggle();
            }
        },

        style: function () {
            var params = $(this).data('style');
            var selector = params.split(', ')[0];
            var style = params.split(', ')[1];
            console.log([selector, style]);
            $(selector).attr('style', style);
        },

        toggleClass: function () {
            var params = $(this).data('toggle_class');
            var class_name = params.split(', ')[0];
            var to = params.split(', ')[1];
            $(to).toggleClass(class_name);
        },

        toggleDisplay: function () {
            var to = $(this).data('toggle_display');
            $(to).each(function () {
                if ($(this).css('display') === "none") {
                    if ($(this).hasClass('flex')) {
                        this.style.display = "flex";
                    } else {
                        $(this).show();
                    }
                } else {
                    $(this).hide();
                }
            });
        },

        bind: function () {
            $('body').on('click', '[data-active]', this.active);

            $('body').on('click', 'a[data-ajax]', this.aAjax);

            $('body').on('click', 'form[data-ajax] [type="submit"]', this.formAjax);
            $('body').on('click', 'form[data-ajax_append] [type="submit"]', this.formAjax);
            $('body').on('click', 'form[data-ajax_prepend] [type="submit"]', this.formAjax);

            $('body').on('change', 'select[data-ajax]', this.selectAjax);

            $('body').on('change', 'select[data-redirect]', this.selectRedirect);

            $('body').on('change', 'select[data-change_toggle]', this.selectToggle);

            $('body').on('click', '[data-alert]', this.alert);

            $('body').on('click', '[data-click]', this.effect('click'));

            $('body').on('click', '[data-clone_append]', this.clone_append);

            $('body').on('click', '[data-confirm]', this.confirm);

            $('body').on('click', '[data-fadeOut]', this.effect('fadeOut'));

            $('body').on('click', '[data-hide]', this.effect('hide'));

            $('body').on('keyup', '[data-live]', this.live);

            $('body').on('click', '[data-remove]', this.remove);

            $('body').on('click', '[data-show]', this.effect('show'));

            $('body').on('click', '[data-slideDown]', this.effect('slideDown'));

            $('body').on('click', '[data-style]', this.style);

            $('body').on('click', '[data-toggle]', this.effect('toggle'));

            $('body').on('click', '[data-toggle_class]', this.toggleClass);

            $('body').on('click', '[data-toggle_display]', this.toggleDisplay);
        }
    };

    $.KumbiaPHP.bind();
})(jQuery);
