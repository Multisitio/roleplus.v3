$('body').on('click', '.theme-picker button', function(eve) {
    eve.preventDefault();
    let theme = $(this).data('theme');
    $('html').attr('data-theme', theme);
    storage('save', 'theme', theme);
    let code = `<html data-theme="` + theme + `">`;
    updatePrismContainer('code.theme_picker', code);
});

theme = $('html').data('theme');
code = `<html data-theme="` + theme + `">`;
updatePrismContainer('code.theme_picker', code);