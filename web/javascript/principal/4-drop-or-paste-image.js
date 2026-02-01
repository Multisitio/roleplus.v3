$('body').on('paste', 'textarea', function(eve) {
    const clipboard = (eve.clipboardData || eve.originalEvent.clipboardData);
    const form = $(this).parents('form');
    const img_box = $(form).find('.dropimage:last');
    const label = $(img_box).parent();
    const box_container = $(label).parent();

    const input = $(img_box).find('[type="file"]')[0];
    if (!clipboard.files.length) {
        return;
    }
    input.files = clipboard.files;

    const data = clipboard.items[0].getAsFile();
    const reader = new FileReader;
    reader.onloadend = function() {
        /*if (img.length) {
            $(img).remove();
        }*/
        $(img_box).css('background-image', 'url(' + reader.result + ')');
        $(img_box).css('background-repeat', 'no-repeat');
        $(img_box).css('background-size', 'cover');
    };
    reader.readAsDataURL(data);

    if ($(label).hasClass('multiple')) {
        var images = $('.modal .dropimage').length;
        if (images < 4) {
            var other = $(label).clone().appendTo(box_container)
                .find('.dropimage').removeClass('dropimagehover');
            $(other).find('[type="file"]').val('');
        }
    }

    $(img_box).addClass('dropnocontent');
});

$('body').on('change', '.dropimage [type="file"]', function(eve) {
    var img = $(this).parent().children('img');
    var img_box = $(this).parent();
    var label = $(img_box).parent();
    var box_container = $(label).parent();
    var reader = new FileReader();

    reader.onloadend = function() {
        /*if (img.length) {
            $(img).remove(); 
        }*/
        $(img_box).css('background-image', 'url(' + reader.result + ')');
        $(img_box).css('background-repeat', 'no-repeat');
        $(img_box).css('background-size', 'cover');
    }
    reader.readAsDataURL(eve.target.files[0]);

    if ($(label).hasClass('multiple')) {
        var images = $('.modal .dropimage').length;
        if (images < 4) {
            var other = $(label).clone().appendTo(box_container)
                .find('.dropimage').removeClass('dropimagehover');
            $(other).find('[type="file"]').val('');
        }
    }

    $(img_box).addClass('dropnocontent');
});

$('body').on('click', '.dropimage button', function(eve) {
    eve.preventDefault();
    var img_box = $(this).parent();
    var label = $(img_box).parent();
    var box_container = $(label).parent();
    var clean = 0;

    if ($(label).hasClass('multiple')) {
        var images = $('.modal .dropimage').length;

        (images > 1) ? $(label).remove(): clean = 1;

        var empty_box = $('.modal .dropimage:not(.dropnocontent)').length;
        if (empty_box < 1 && images < 5) {
            var new_box = $(label).clone().appendTo(box_container);
            img_box = $(new_box).find('.dropimage');
            clean = 1;
        }
    } else {
        clean = 1;
    }

    if (clean == 1) {
        $(img_box).removeAttr('style');
        $(img_box).removeClass('dropimagehover');
        $(img_box).removeClass('dropnocontent');
        $(img_box).find('>img').remove();
        $(img_box).find('input').val('');
    }
});

$(document).on('dragenter, dragover', '.dropimage', function(eve) {
    eve.preventDefault();
    $(this).addClass('dropimagehover');
});

$(document).on('dragleave', '.dropimage', function() {
    $(this).removeClass('dropimagehover');
});