$('body').on('click', '[data-clone]', function() {
    var el = $(this).data('clone');
    var to = $(this).data('to');
    $(el).clone().appendTo(to).show();
});