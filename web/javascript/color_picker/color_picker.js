$('body').on('click', '.color-picker button', function(eve) {
    eve.preventDefault();
    let primary_color = $(this).data('primary_color');
    $('html').attr('data-primary_color', primary_color);
    storage('save', 'primary_color', primary_color);
    let code = `<html data-primary_color="` + primary_color + `">`;
    updatePrismContainer('code.color_picker', code);
});

primary_color = $('html').data('primary_color');
code = `<html data-primary_color="` + primary_color + `">`;
updatePrismContainer('code.color_picker', code);