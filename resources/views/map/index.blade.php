@section('title', 'Map')
@section('css_links')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css"/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css"/>
@endsection
@section('style')
    <style>
        #map {
            height: calc(100vh - 200px);
            min-height: 500px;
            width: 100%;
            border-radius: 8px;
        }
    </style>
@endsection
<x-app-layout>
    <div class="container" id="app">
        <div class="page-header">
            <h2 class="page-title"><i class="fas fa-globe"></i> Map</h2>
        </div>

        <x-response-alerts></x-response-alerts>

        <div class="content-card">
            <div id="map"></div>
        </div>
    </div>

    @section('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const map = L.map('map').setView([30, 0], 2);

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            const markers = L.markerClusterGroup();

            const typeColors = {
                'Server': '#206bc4',
                'Shared': '#2fb344',
                'Reseller': '#f76707',
                'Seed Box': '#d63939'
            };

            function createIcon(type) {
                const color = typeColors[type] || '#206bc4';
                return L.divIcon({
                    className: 'custom-marker',
                    html: '<div style="background:' + color + ';width:12px;height:12px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,.4)"></div>',
                    iconSize: [12, 12],
                    iconAnchor: [6, 6],
                    popupAnchor: [0, -8]
                });
            }

            fetch('{{ route("map.data") }}')
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        document.getElementById('map').insertAdjacentHTML('afterbegin',
                            '<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:1000;background:rgba(255,255,255,.9);padding:20px;border-radius:8px;text-align:center;">' +
                            '<p class="mb-1"><strong>No services with coordinates found.</strong></p>' +
                            '<p class="text-muted mb-0">Run <code>php artisan ips:backfill-coordinates</code> to populate coordinates.</p>' +
                            '</div>'
                        );
                        return;
                    }

                    data.forEach(function (item) {
                        const marker = L.marker([item.lat, item.lng], {icon: createIcon(item.type)});

                        const popup = '<div style="min-width:180px">' +
                            '<strong>' + escapeHtml(item.name) + '</strong>' +
                            '<span class="badge" style="background:' + (typeColors[item.type] || '#206bc4') + ';color:#fff;margin-left:6px;font-size:11px">' + escapeHtml(item.type) + '</span>' +
                            '<hr style="margin:6px 0">' +
                            '<div><i class="fas fa-network-wired"></i> ' + escapeHtml(item.ip) + '</div>' +
                            (item.city ? '<div><i class="fas fa-city"></i> ' + escapeHtml(item.city) + '</div>' : '') +
                            (item.country ? '<div><i class="fas fa-flag"></i> ' + escapeHtml(item.country) + '</div>' : '') +
                            '</div>';

                        marker.bindPopup(popup);
                        markers.addLayer(marker);
                    });

                    map.addLayer(markers);

                    if (data.length > 0) {
                        map.fitBounds(markers.getBounds().pad(0.1));
                    }
                });

            function escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.appendChild(document.createTextNode(text));
                return div.innerHTML;
            }
        });
    </script>
    @endsection
</x-app-layout>
