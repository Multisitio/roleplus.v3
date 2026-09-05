var partidas_idu_node = document.querySelector('[data-partidas_idu]');
var partidas_idu = partidas_idu_node ? partidas_idu_node.dataset.partidas_idu : null;

if (partidas_idu && partidas_idu.length) {
    console.log('Escuchando nuevos jugadores...');

    document.addEventListener("DOMContentLoaded", function() {
        Kumbia.utils.fetch('/ev/panel/conectado/' + partidas_idu);
        console.log('Connected onload!');
    });

    setInterval(function() {
        Kumbia.utils.fetch('/ev/panel/conectado/' + partidas_idu);
        console.log('Connected oninterval!');
    }, 540000);
}