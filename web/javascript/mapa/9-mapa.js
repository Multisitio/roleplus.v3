var map = L.map('map', {
    crs: L.CRS.Simple,
    minZoom: -2,
    maxZoom: 0
});

L.Map = L.Map.extend({
    openPopup: function(popup) {
        this._popup = popup;
        return this.addLayer(popup).fire('popupopen', {
            popup: this._popup
        });
    }
});

var bounds = [
    [0, 0],
    [image_height, image_width]
];
var image = L.imageOverlay(image_selected, bounds).addTo(map);

var window_height = window.innerHeight;
var window_width = window.innerWidth;
var image_set_top = parseInt(image_height) - window_height + 50;
map.setView([image_set_top, window_width - 50], -1);

var LeafIcon = L.Icon.extend({
    options: {
        iconAnchor: [18, 47],
        iconSize: [37, 55],
        popupAnchor: [0, -42],
        shadowAnchor: [7, 25],
        shadowSize: [36, 27],
        shadowUrl: '/img/aplicaciones/shadow.png'
    }
});
var iconBlue = new LeafIcon({ iconUrl: '/img/aplicaciones/icon_blue.png' }),
    iconGreen = new LeafIcon({ iconUrl: '/img/aplicaciones/icon_green.png' }),
    iconRed = new LeafIcon({ iconUrl: '/img/aplicaciones/icon_red.png' }),
    iconWhite = new LeafIcon({ iconUrl: '/img/aplicaciones/icon_white.png' }),
    iconYellow = new LeafIcon({ iconUrl: '/img/aplicaciones/icon_yellow.png' });

var marcadores = [];

var popup = L.popup();

function onMapClick(e) {
    var pContent = document.querySelector('.popup');
    popup
        .setLatLng(e.latlng)
        .setContent(pContent ? pContent.innerHTML : '')
        .openOn(map);

    var tLat = document.querySelector('[name="lat"]');
    var tLng = document.querySelector('[name="lng"]');
    if(tLat) tLat.value = e.latlng.lat;
    if(tLng) tLng.value = e.latlng.lng;
}
map.on('contextmenu', onMapClick);

Kumbia.utils.on('click', '.toggleMarkers', function() {
    var panes = document.querySelectorAll('.leaflet-marker-pane, .leaflet-shadow-pane, .leaflet-popup-pane');
    for(var i=0; i<panes.length; i++) {
        panes[i].style.display = panes[i].style.display === 'none' ? '' : 'none';
    }
});

Kumbia.utils.on('keyup', '.seeker', function() {
    var panes = document.querySelectorAll('.leaflet-marker-pane, .leaflet-shadow-pane, .leaflet-popup-pane');
    for(var i=0; i<panes.length; i++) panes[i].style.display = '';

    var txt = this.value;

    if (txt == '' || txt.length < 3) {
        marcadores.forEach(function(marcador, id) {
            marcadores[id].closePopup();
        });
    } else {
        marcadores.forEach(function(marcador, id) {
            marcadores[id]._popup._content.toUpperCase().includes(txt.toUpperCase()) ? marcadores[id].openPopup() : marcadores[id].closePopup();
        });
    }
});