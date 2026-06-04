import { ref, watch, computed } from 'vue';
import L from 'leaflet';
import { theme } from './useTheme.js';

export function useMapLayers(options = {}) {
    const base = ref(options.defaultBase ?? 'map');
    const seamark = ref(options.defaultSeamark !== false);
    const contours = ref(options.defaultContours ?? false);

    let map = null;
    let tileBase = null;
    let tileSeamark = null;
    let tileContours = null;

    const isDark = computed(() => theme.value === 'dark' || theme.value === 'night');

    function baseUrl() {
        if (base.value === 'satellite') return '/satellite/{z}/{y}/{x}';
        if (seamark.value) {
            return isDark.value ? '/openseamap-dark/{z}/{x}/{y}' : '/openseamap/{z}/{x}/{y}';
        }
        return isDark.value ? '/cartodb-dark/{z}/{x}/{y}' : '/osm/{z}/{x}/{y}';
    }

    function sync() {
        if (!map) return;

        if (tileBase) map.removeLayer(tileBase);
        if (tileSeamark) { map.removeLayer(tileSeamark); tileSeamark = null; }
        if (tileContours) { map.removeLayer(tileContours); tileContours = null; }

        tileBase = L.tileLayer(baseUrl(), { maxZoom: 18 }).addTo(map);

        if (base.value === 'satellite' && seamark.value) {
            tileSeamark = L.tileLayer('/seamark/{z}/{x}/{y}', { maxZoom: 18 }).addTo(map);
        }

        if (contours.value) {
            tileContours = L.tileLayer('/depth/{z}/{x}/{y}', { maxZoom: 15, opacity: 0.7 }).addTo(map);
        }
    }

    function attach(leafletMap) {
        map = leafletMap;
        sync();
    }

    watch([base, seamark, contours, isDark], sync);

    return { base, seamark, contours, attach };
}
