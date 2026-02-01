function registrarVisita() {
    var fecha = new Date().toString();
    var pagina = window.location.href;
    var visitas = JSON.parse(localStorage.getItem("visitas")) || [];
    visitas.push({ fecha: fecha, pagina: pagina });
    localStorage.setItem("visitas", JSON.stringify(visitas));
    mostrarVisitas();
}

function registrarEvento() {
    var fecha = new Date().toString();
    var pagina = window.location.href;
    var eventos = JSON.parse(localStorage.getItem("eventos")) || [];
    eventos.push({ fecha: fecha, pagina: pagina });
    localStorage.setItem("eventos", JSON.stringify(eventos));
    mostrarEventos();
}

function mostrarVisitas() {
    var visitas = JSON.parse(localStorage.getItem("visitas")) || [];
    var visitasHtml = "";
    for (var i = 0; i < visitas.length; i++) {
        visitasHtml += "<p>" + visitas[i].fecha + " - " + visitas[i].pagina + "</p>";
    }
    document.getElementById("visitas").innerHTML = visitasHtml;
}

function mostrarEventos() {
    var eventos = JSON.parse(localStorage.getItem("eventos")) || [];
    var eventosHtml = "";
    for (var i = 0; i < eventos.length; i++) {
        eventosHtml += "<p>" + eventos[i].fecha + " - " + eventos[i].pagina + "</p>";
    }
    document.getElementById("eventos").innerHTML = eventosHtml;
}

mostrarVisitas();
mostrarEventos();