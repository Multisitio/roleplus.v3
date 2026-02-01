console.log('Escuchando nuevos conectados...');

$(function() {
    $.get('/usuarios/conectado');
    console.log('Connected!');
});

setInterval(function() {
    $.get('/usuarios/conectado');
    console.log('Connected!');
}, 540000);