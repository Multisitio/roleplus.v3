console.log('Escuchando nuevos conectados...');

document.addEventListener("DOMContentLoaded", function() {
    Kumbia.utils.fetch('/usuarios/conectado');
    console.log('Connected!');
});

setInterval(function() {
    Kumbia.utils.fetch('/usuarios/conectado');
    console.log('Connected!');
}, 540000);