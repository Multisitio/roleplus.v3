/*document.addEventListener("DOMContentLoaded", function() {
    [].forEach.call(document.querySelectorAll('.dropimage'), function(label) {
        label.onchange = function(eve) {
            var inputfile = this,
                reader = new FileReader();
            reader.onloadend = function() {
                var img = inputfile.children[1];
                if (img !== undefined) {
                    img.src = reader.result;
                } else {
                    inputfile.style['background-image'] = 'url(' + reader.result + ')';
                    inputfile.style['background-repeat'] = 'no-repeat';
                    inputfile.style['background-size'] = 'cover';
                }
            }
            reader.readAsDataURL(eve.target.files[0]);
        }
    }); 
});*/

$('body').on('change', '.dropimage [type="file"]', function(eve) {

    var img = $(this).parent().children('img');
    var label = $(this).parent();
    var reader = new FileReader();

    reader.onloadend = function() {
        if (img.length) {
            $(img).remove();
        }
        $(label).css('background-image', 'url(' + reader.result + ')');
        $(label).css('background-repeat', 'no-repeat');
        $(label).css('background-size', 'cover');

    }

    reader.readAsDataURL(eve.target.files[0]);

    if ($(label).hasClass('multiple') && ($('.dropimage').length < 4)) {
        var other = $(label).removeClass('dropimagehover').clone().appendTo($(label).parent());
        $(other).find('[type="file"]').val('');
    }

    $(label).addClass('dropnocontent');
});

$('body').on('click', '.dropimage button', function() {
    var parent = $(this).parents('.dropimage');
    $(parent).removeAttr('style');
    $(parent).removeClass('dropimagehover');
    $(parent).removeClass('dropnocontent');
    $(parent).find('input').val('');
});

$(document).on('dragenter, dragover', '.dropimage', function(eve) {
    eve.preventDefault();
    $(this).addClass('dropimagehover');
});

$(document).on('dragleave', '.dropimage', function() {
    $(this).removeClass('dropimagehover');
});