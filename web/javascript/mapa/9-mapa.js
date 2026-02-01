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

var window_height = $(window).height();
var window_width = $(window).width();
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
    popup
        .setLatLng(e.latlng)
        .setContent($('.popup').html())
        .openOn(map);

    $('[name="lat"]').val(e.latlng.lat);
    $('[name="lng"]').val(e.latlng.lng);
}
map.on('contextmenu', onMapClick);

$('body').on('click', '.toggleMarkers', function() {
    $('.leaflet-marker-pane, .leaflet-shadow-pane, .leaflet-popup-pane').toggle();
});

$('body').on('keyup', '.seeker', function() {
    $('.leaflet-marker-pane, .leaflet-shadow-pane, .leaflet-popup-pane').show();

    var txt = $('.seeker').val();

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