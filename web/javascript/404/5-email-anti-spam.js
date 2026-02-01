var emails = document.querySelectorAll('.email-anti-spam');
for (var i = 0; i < emails.length; i++) {
    var addr = emails[i].textContent.replace(/ at /, '@').replace(/ dot /g, '.');
    emails[i].textContent = '';
    var link = document.createElement('a');
    link.href = 'mailto:' + addr;
    link.textContent = addr;
    emails[i].appendChild(link);
}