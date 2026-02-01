var partidas_idu = document.querySelector('[data-partidas_idu]').dataset.partidas_idu;

if (partidas_idu.length) {
    console.log('Escuchando nuevos jugadores...');

    $(function() {
        $.get('/ev/panel/conectado/' + partidas_idu);
        console.log('Connected onload!');
    });

    setInterval(function() {
        $.get('/ev/panel/conectado/' + partidas_idu);
        console.log('Connected oninterval!');
    }, 540000);
}