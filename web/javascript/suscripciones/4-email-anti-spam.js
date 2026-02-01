$('.email-anti-spam').each(function() {
    var addr = $(this).text().replace(/ at /, '@').replace(/ dot /g, '.');
    $(this).text('');
    $(document.createElement('a')).attr('href', 'mailto:' + addr).text(addr).appendTo(this);
});