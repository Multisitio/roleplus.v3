$('body').on('paste', '[data-preview]', function(eve) {
    var pastedData = eve.originalEvent.clipboardData.getData('text');
    preview(pastedData, $(this));
});

function preview(url, el) {
    if (strstr(url, 'http')) {
        el.parent().find('.preview').html('<progress class="indeterminate"></progress>').load('/index/preview?u=' + base64_encode(url));
    }
}

function removeHideElements(el) {
    $(el).find('[style="display:none"]').remove();
}