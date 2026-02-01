$('body').on('change', '[type="file"]', function(eve) {

    var img = $(this).parent().find('img'),
        label = $(this).parent(),
        reader = new FileReader();

    console.log([img, eve, label, reader]);

    reader.onloadend = function() {
        if (img.length) {
            $(img).attr('src', reader.result);
        } else {
            $(label).css('background-image', 'url(' + reader.result + ')');
            $(label).css('background-repeat', 'no-repeat');
            $(label).css('background-size', 'cover');
        }
    }
    reader.readAsDataURL(eve.target.files[0]);
});