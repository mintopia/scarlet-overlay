/*
 * LCARS presentation contract — single source of truth for what each Bridge Station
 * shows and how (PLAN.md Appendix A). Each panel declares:
 *   key   unique id (matches the backend registry key for `historical` line metrics)
 *   label LCARS-cased label
 *   cls   'historical' | 'instantaneous' | 'categorical' | 'identity'  (drives data source)
 *   viz   'line' | 'dial' | 'compass' | 'bar' | 'stat' | 'signal' | 'badge'
 *         | 'navfix' | 'sunarc' | 'climate'   (drives rendering)
 *   size  'hero' | 'lg' | 'md' | 'sm'   (drives the bento grid span → hierarchy)
 *   src   live payload path 'boat.x'|'gps.x'|'tracker.x'|'weather.x'  (single-value vizes)
 *   unit, range, wrap, dp, fmt('duration'|'bytes'|'lat'|'lon')  display hints
 *
 * Aggregate vizes pull their own data: navfix (gps+heading), sunarc (sun),
 * climate (tempSrc+humSrc). Backend STATION_HISTORY must equal each station's
 * cls==='historical' keys.
 */

export const STATIONS = [
    { id: 'msd', label: 'MSD', title: 'MASTER SYSTEMS DISPLAY' },
    { id: 'conn', label: 'CONN', title: 'FLIGHT CONTROL · HELM' },
    { id: 'navigation', label: 'NAV', title: 'NAVIGATION' },
    { id: 'engineering', label: 'ENG', title: 'ENGINEERING · POWER' },
    { id: 'ops', label: 'OPS', title: 'OPERATIONS' },
    { id: 'science', label: 'SCI', title: 'SCIENCE · CONDITIONS' },
];

export const CONTRACT = {
    // Order matters: [hero, rail-1, rail-2, ...body]. Hero (8×2) + the two rail tiles
    // (md=4) fill the top band; body rows below each total 12 columns.
    conn: [
        { key: 'speed_sog', label: 'Speed Over Ground', cls: 'historical', viz: 'line', size: 'hero', src: 'boat.speed_sog', unit: 'kn', range: [0, 12], color: 'var(--orange)' },
        { key: 'heading', label: 'Heading', cls: 'instantaneous', viz: 'compass', size: 'md', src: 'boat.heading', unit: '°', range: [0, 360], wrap: true, dp: 0 },
        { key: 'wind_angle_apparent', label: 'App Wind', cls: 'instantaneous', viz: 'compass', size: 'md', src: 'boat.wind_angle_apparent', unit: '°', range: [0, 360], wrap: true, dp: 0 },
        // row: 4+4+4 — dials get square tiles
        { key: 'cog', label: 'COG', cls: 'instantaneous', viz: 'dial', size: 'md', src: 'boat.cog', unit: '°', range: [0, 360], wrap: true, dp: 0, compass: true },
        { key: 'pitch', label: 'Pitch', cls: 'instantaneous', viz: 'dial', size: 'md', src: 'boat.pitch', unit: '°', range: [-30, 30] },
        { key: 'heel', label: 'Heel', cls: 'historical', viz: 'line', size: 'md', src: 'boat.heel', unit: '°', range: [-45, 45], color: 'var(--mauve)' },
        // row: 6+6 — line graphs get the wide tiles
        { key: 'speed_stw', label: 'Speed Thru Water', cls: 'historical', viz: 'line', size: 'lg', src: 'boat.speed_stw', unit: 'kn', range: [0, 12], color: 'var(--ice)' },
        { key: 'vmg', label: 'VMG', cls: 'historical', viz: 'line', size: 'lg', src: 'boat.vmg', unit: 'kn', range: [-8, 8], color: 'var(--amber)' },
    ],
    navigation: [
        { key: 'navfix', label: 'Navigational Fix', cls: 'instantaneous', viz: 'navfix', size: 'hero' },
        { key: 'nav_wp_distance', label: 'Distance To Waypoint', cls: 'instantaneous', viz: 'stat', size: 'md', src: 'boat.nav_wp_distance', unit: 'nm', dp: 1 },
        { key: 'nav_wp_ttg', label: 'Time To Waypoint', cls: 'instantaneous', viz: 'stat', size: 'md', src: 'boat.nav_wp_ttg', fmt: 'duration' },
        // row: 4+4+4
        { key: 'depth', label: 'Depth Below Surface', cls: 'historical', viz: 'line', size: 'md', src: 'boat.depth', unit: 'm', range: [0, 40], color: 'var(--ice)' },
        { key: 'trip_log', label: 'Trip Log', cls: 'historical', viz: 'line', size: 'md', src: 'boat.trip_log', unit: 'nm', color: 'var(--orange)' },
        { key: 'magnetic_variation', label: 'Mag Variation', cls: 'instantaneous', viz: 'stat', size: 'md', src: 'boat.magnetic_variation', unit: '°', dp: 1 },
    ],
    engineering: [
        { key: 'house_battery_soc', label: 'House Battery · State Of Charge', cls: 'historical', viz: 'line', size: 'hero', src: 'boat.house_battery_soc', unit: '%', range: [0, 100], color: 'var(--orange)' },
        { key: 'house_battery_voltage', label: 'House Voltage', cls: 'historical', viz: 'line', size: 'md', src: 'boat.house_battery_voltage', unit: 'V', range: [10, 14.6], dp: 2, color: 'var(--blue)' },
        { key: 'house_battery_current', label: 'House Current', cls: 'historical', viz: 'line', size: 'md', src: 'boat.house_battery_current', unit: 'A', range: [-60, 60], dp: 1, color: 'var(--amber)' },
        // row: 4+4+4
        { key: 'battery_power', label: 'Power', cls: 'instantaneous', viz: 'stat', size: 'md', src: 'boat.battery_power', unit: 'W', dp: 0 },
        { key: 'house_battery_time_remaining', label: 'Time Remaining', cls: 'instantaneous', viz: 'stat', size: 'md', src: 'boat.house_battery_time_remaining', unit: 'h', dp: 1 },
        { key: 'engine_battery_voltage', label: 'Engine Battery', cls: 'historical', viz: 'line', size: 'md', src: 'boat.engine_battery_voltage', unit: 'V', range: [10, 14.6], dp: 2, color: 'var(--ice)' },
        // row: 4+4+4
        { key: 'tracker_cpu', label: 'Tracker CPU', cls: 'historical', viz: 'line', size: 'md', src: 'tracker.tracker_cpu', unit: '%', range: [0, 100], dp: 0, color: 'var(--mauve)' },
        { key: 'tracker_uptime', label: 'Uptime', cls: 'instantaneous', viz: 'stat', size: 'md', src: 'tracker.tracker_uptime', fmt: 'duration' },
        { key: 'tracker_heap', label: 'Free Heap', cls: 'instantaneous', viz: 'stat', size: 'md', src: 'tracker.tracker_heap', fmt: 'bytes' },
        // row: 12
        { key: 'tracker_mode', label: 'System Mode', cls: 'categorical', viz: 'badge', size: 'full', src: 'tracker.tracker_mode' },
    ],
    ops: [
        // row: 6+6
        { key: 'fuel_level', label: 'Diesel', cls: 'historical', viz: 'bar', size: 'lg', src: 'boat.fuel_level', unit: '%', range: [0, 100], dp: 0, color: 'var(--amber)' },
        { key: 'water_level', label: 'Fresh Water', cls: 'historical', viz: 'bar', size: 'lg', src: 'boat.water_level', unit: '%', range: [0, 100], dp: 0, color: 'var(--blue)' },
        // row: 4+4+4
        { key: 'climate_forepeak', label: 'Forepeak', cls: 'instantaneous', viz: 'climate', size: 'md', tempSrc: 'boat.cabin_temp_forepeak', humSrc: 'boat.cabin_humidity_forepeak' },
        { key: 'climate_main', label: 'Main Cabin', cls: 'instantaneous', viz: 'climate', size: 'md', tempSrc: 'boat.cabin_temp_main', humSrc: 'boat.cabin_humidity_main' },
        { key: 'climate_quarterberth', label: 'Quarterberth', cls: 'instantaneous', viz: 'climate', size: 'md', tempSrc: 'boat.cabin_temp_quarterberth', humSrc: 'boat.cabin_humidity_quarterberth' },
        // row: 6+6
        { key: 'tracker_lte_rssi', label: 'LTE Signal', cls: 'historical', viz: 'signal', size: 'lg', src: 'tracker.tracker_lte_rssi', range: [-110, -50] },
        { key: 'tracker_wifi_rssi', label: 'WiFi Signal', cls: 'historical', viz: 'signal', size: 'lg', src: 'tracker.tracker_wifi_rssi', range: [-100, -30] },
        // row: 4+4+4
        { key: 'tracker_lte_connected', label: 'LTE Link', cls: 'categorical', viz: 'badge', size: 'md', src: 'tracker.tracker_lte_connected' },
        { key: 'tracker_wifi_connected', label: 'WiFi Link', cls: 'categorical', viz: 'badge', size: 'md', src: 'tracker.tracker_wifi_connected' },
        { key: 'tracker_lte_rat', label: 'LTE RAT', cls: 'categorical', viz: 'badge', size: 'md', src: 'tracker.tracker_lte_rat' },
    ],
    science: [
        { key: 'sun', label: 'Solar Almanac', cls: 'identity', viz: 'sunarc', size: 'hero' },
        { key: 'water_temp', label: 'Sea Temperature', cls: 'historical', viz: 'line', size: 'md', src: 'boat.water_temp', unit: '°C', range: [0, 30], color: 'var(--ice)' },
        { key: 'cabin_pressure_forepeak', label: 'Barometric Pressure', cls: 'historical', viz: 'line', size: 'md', src: 'boat.cabin_pressure_forepeak', unit: 'hPa', range: [960, 1050], dp: 0, color: 'var(--mauve)' },
        // row: 4+4+4
        { key: 'air_temp', label: 'Air Temp', cls: 'instantaneous', viz: 'dial', size: 'md', src: 'weather.temperature', unit: '°C', range: [-5, 40] },
        { key: 'wx_wind_speed', label: 'Wind Speed', cls: 'instantaneous', viz: 'dial', size: 'md', src: 'weather.wind_speed', unit: 'kn', range: [0, 50] },
        { key: 'wx_wind_dir', label: 'Wind Dir', cls: 'instantaneous', viz: 'compass', size: 'md', src: 'weather.wind_direction', unit: '°', range: [0, 360], wrap: true, dp: 0 },
        // row: 4+4+4
        { key: 'wx_wave_height', label: 'Wave Height', cls: 'instantaneous', viz: 'dial', size: 'md', src: 'weather.wave_height', unit: 'm', range: [0, 8] },
        { key: 'wx_wave_period', label: 'Wave Period', cls: 'instantaneous', viz: 'stat', size: 'md', src: 'weather.wave_period', unit: 's', dp: 1 },
        { key: 'wx_current_speed', label: 'Current', cls: 'instantaneous', viz: 'stat', size: 'md', src: 'weather.current_speed', unit: 'kn', dp: 1 },
    ],
};

/** Historical keys per station — must equal the backend STATION_HISTORY allow-list. */
export function historicalKeys(stationId) {
    return (CONTRACT[stationId] ?? []).filter(m => m.cls === 'historical').map(m => m.key);
}

/** MSD call-outs (live snapshot pinned to the schematic). */
export const MSD_CALLOUTS = [
    { key: 'house_battery_soc', label: 'BATT', src: 'boat.house_battery_soc', unit: '%', dp: 0 },
    { key: 'fuel_level', label: 'FUEL', src: 'boat.fuel_level', unit: '%', dp: 0 },
    { key: 'water_level', label: 'WATER', src: 'boat.water_level', unit: '%', dp: 0 },
    { key: 'depth', label: 'DEPTH', src: 'boat.depth', unit: 'm', dp: 1 },
    { key: 'speed_sog', label: 'SOG', src: 'boat.speed_sog', unit: 'kn', dp: 1 },
    { key: 'heading', label: 'HDG', src: 'boat.heading', unit: '°', dp: 0 },
    { key: 'cabin_temp_forepeak', label: 'FORE', src: 'boat.cabin_temp_forepeak', unit: '°', dp: 0 },
    { key: 'cabin_temp_main', label: 'MAIN', src: 'boat.cabin_temp_main', unit: '°', dp: 0 },
    { key: 'cabin_temp_quarterberth', label: 'QTR', src: 'boat.cabin_temp_quarterberth', unit: '°', dp: 0 },
];

export function pluck(metrics, path) {
    if (!metrics || !path) return null;
    const [group, key] = path.split('.');
    if (!key) return metrics[group] ?? null;
    return metrics[group]?.[key] ?? null;
}
