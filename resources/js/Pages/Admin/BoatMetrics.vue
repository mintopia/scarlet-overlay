<template>
    <AdminLayout>
        <Head title="Boat Metrics" />

        <div class="flex items-baseline justify-between mb-6">
            <h1 class="font-sans text-2xl font-extrabold tracking-tight">Boat Metrics</h1>
            <div class="flex items-center gap-2 text-[13px] text-text-dim">
                <span class="w-2 h-2 rounded-full bg-green inline-block" :class="lastUpdate ? 'opacity-100' : 'opacity-30'"></span>
                <span class="tabular-nums">{{ timeSinceUpdate }}</span>
            </div>
        </div>

        <!-- Critical readings -->
        <div class="strip">
            <div class="strip-cell">
                <div class="strip-label">SOG</div>
                <div class="strip-value text-scarlet">{{ fmt(live?.speed_sog) }}</div>
                <div class="strip-unit">kn</div>
            </div>
            <div class="strip-cell">
                <div class="strip-label">Depth</div>
                <div class="strip-value strip-value--depth">{{ fmt(live?.depth) }}</div>
                <div class="strip-unit">m</div>
            </div>
            <div class="strip-cell">
                <div class="strip-label">Wind</div>
                <div class="strip-value text-green">{{ fmt(live?.wind_speed_true) }}</div>
                <div class="strip-unit">kn true</div>
            </div>
            <div class="strip-cell">
                <div class="strip-label">Battery</div>
                <div class="strip-value text-green">{{ fmt(live?.house_battery_voltage) }}</div>
                <div class="strip-unit">V</div>
            </div>
            <div class="strip-cell">
                <div class="strip-label">Heading</div>
                <div class="strip-value">{{ fmt(live?.heading, 0) }}</div>
                <div class="strip-unit">° mag</div>
            </div>
        </div>

        <!-- Battery + Speed history -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4 mb-3">
            <Link href="/admin/explore?metric=battery_voltage&range=24h" class="panel explore-link p-4">
                <svg class="explore-icon" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2H2v4M14 10v4h-4M2 6l4-4M10 14l4-4"/></svg>
                <div class="panel-head">
                    <span class="panel-title">Battery Voltage</span>
                    <span class="tabular-nums text-[12px] font-medium text-green">{{ batteryVal }}</span>
                </div>
                <svg viewBox="0 0 400 100" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="battGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--color-green)" stop-opacity="0.18"/>
                            <stop offset="100%" stop-color="var(--color-green)" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <polygon v-if="batteryHistory?.length" :points="toArea(batteryHistory, 400, 100, batteryMin, batteryMax)" fill="url(#battGrad)"/>
                    <polyline v-if="batteryHistory?.length" :points="toLine(batteryHistory, 400, 100, batteryMin, batteryMax)" fill="none" stroke="var(--color-green)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                    <text v-if="!batteryHistory?.length" x="200" y="55" text-anchor="middle" font-size="12" fill="var(--color-text-dim)">No data</text>
                </svg>
                <div class="chart-axis"><span>24h ago</span><span>now</span></div>
            </Link>

            <Link href="/admin/explore?metric=speed&range=24h" class="panel explore-link p-4">
                <svg class="explore-icon" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2H2v4M14 10v4h-4M2 6l4-4M10 14l4-4"/></svg>
                <div class="panel-head">
                    <span class="panel-title">Speed</span>
                    <span class="tabular-nums text-[12px] font-medium text-scarlet">{{ live?.speed_sog != null ? fmt(live.speed_sog) + ' kn' : '—' }}</span>
                </div>
                <svg viewBox="0 0 400 100" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="spdGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--color-scarlet)" stop-opacity="0.18"/>
                            <stop offset="100%" stop-color="var(--color-scarlet)" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <polygon v-if="speedHistory?.length" :points="toArea(speedHistory, 400, 100, 0, speedMax)" fill="url(#spdGrad)"/>
                    <polyline v-if="speedHistory?.length" :points="toLine(speedHistory, 400, 100, 0, speedMax)" fill="none" stroke="var(--color-scarlet)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                    <text v-if="!speedHistory?.length" x="200" y="55" text-anchor="middle" font-size="12" fill="var(--color-text-dim)">No data</text>
                </svg>
                <div class="chart-axis"><span>24h ago</span><span>now</span></div>
            </Link>
        </div>

        <!-- Battery Power (charge/discharge in watts) -->
        <Link href="/admin/explore?metric=battery_power&range=24h" class="panel explore-link p-4 mb-6">
            <svg class="explore-icon" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2H2v4M14 10v4h-4M2 6l4-4M10 14l4-4"/></svg>
            <div class="panel-head">
                <span class="panel-title">Battery Power</span>
                <span class="tabular-nums text-[12px] font-medium" :class="livePower != null && livePower >= 0 ? 'text-green' : 'text-amber'">{{ livePowerLabel }}</span>
            </div>
            <div class="chart-legend">
                <span><span class="legend-dot text-green">&#9679;</span> Charging</span>
                <span><span class="legend-dot text-amber">&#9679;</span> Discharging</span>
            </div>
            <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                <defs>
                    <linearGradient id="chargeGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="var(--color-green)" stop-opacity="0.22"/>
                        <stop offset="100%" stop-color="var(--color-green)" stop-opacity="0.02"/>
                    </linearGradient>
                    <linearGradient id="dischargeGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="var(--color-amber)" stop-opacity="0.02"/>
                        <stop offset="100%" stop-color="var(--color-amber)" stop-opacity="0.22"/>
                    </linearGradient>
                    <clipPath id="clipCharge"><rect x="0" y="0" width="400" height="60"/></clipPath>
                    <clipPath id="clipDischarge"><rect x="0" y="60" width="400" height="60"/></clipPath>
                </defs>
                <line x1="0" y1="60" x2="400" y2="60" stroke="var(--color-text-dim)" stroke-width="0.5" stroke-dasharray="4,3" v-if="batteryPowerHistory?.length"/>
                <polygon v-if="batteryPowerHistory?.length" :points="toPowerArea(batteryPowerHistory, 400, 120, powerAbsMax, true)" fill="url(#chargeGrad)"/>
                <polygon v-if="batteryPowerHistory?.length" :points="toPowerArea(batteryPowerHistory, 400, 120, powerAbsMax, false)" fill="url(#dischargeGrad)"/>
                <polyline v-if="batteryPowerHistory?.length" :points="toPowerLine(batteryPowerHistory, 400, 120, powerAbsMax)" fill="none" stroke="var(--color-green)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" clip-path="url(#clipCharge)"/>
                <polyline v-if="batteryPowerHistory?.length" :points="toPowerLine(batteryPowerHistory, 400, 120, powerAbsMax)" fill="none" stroke="var(--color-amber)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" clip-path="url(#clipDischarge)"/>
                <text v-if="!batteryPowerHistory?.length" x="200" y="65" text-anchor="middle" font-size="12" fill="var(--color-text-dim)">No data</text>
            </svg>
            <div class="chart-axis">
                <span>24h ago</span>
                <span class="text-center text-text-dim">0 W</span>
                <span>now</span>
            </div>
        </Link>

        <!-- Temperature + Humidity history -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4 mb-6">
            <Link href="/admin/explore?metric=temp_forepeak&range=24h" class="panel explore-link p-4">
                <svg class="explore-icon" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2H2v4M14 10v4h-4M2 6l4-4M10 14l4-4"/></svg>
                <div class="panel-title mb-0.5">Cabin Temperature</div>
                <div class="chart-legend">
                    <span><span class="legend-dot text-amber">&#9679;</span> Forepeak {{ live?.cabin_temp_forepeak != null ? fmt(live.cabin_temp_forepeak) + '°C' : '—' }}</span>
                    <span><span class="legend-dot text-blue">&#9679;</span> Quarterberth {{ live?.cabin_temp_quarterberth != null ? fmt(live.cabin_temp_quarterberth) + '°C' : '—' }}</span>
                    <span><span class="legend-dot text-pink">&#9679;</span> Main Cabin {{ live?.cabin_temp_main != null ? fmt(live.cabin_temp_main) + '°C' : '—' }}</span>
                </div>
                <svg viewBox="0 0 400 100" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="tmpGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--color-amber)" stop-opacity="0.10"/>
                            <stop offset="100%" stop-color="var(--color-amber)" stop-opacity="0.01"/>
                        </linearGradient>
                    </defs>
                    <polygon v-if="tempHistoryForepeak?.length" :points="toArea(tempHistoryForepeak, 400, 100, tempMin, tempMax)" fill="url(#tmpGrad)"/>
                    <polyline v-if="tempHistoryForepeak?.length" :points="toLine(tempHistoryForepeak, 400, 100, tempMin, tempMax)" fill="none" stroke="var(--color-amber)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                    <polyline v-if="tempHistoryQuarterberth?.length" :points="toLine(tempHistoryQuarterberth, 400, 100, tempMin, tempMax)" fill="none" stroke="var(--color-blue)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                    <polyline v-if="tempHistoryMainCabin?.length" :points="toLine(tempHistoryMainCabin, 400, 100, tempMin, tempMax)" fill="none" stroke="var(--color-pink)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                    <text v-if="!hasTempData" x="200" y="55" text-anchor="middle" font-size="12" fill="var(--color-text-dim)">No data</text>
                </svg>
                <div class="chart-axis"><span>24h ago</span><span>now</span></div>
            </Link>

            <Link href="/admin/explore?metric=humidity_forepeak&range=24h" class="panel explore-link p-4">
                <svg class="explore-icon" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2H2v4M14 10v4h-4M2 6l4-4M10 14l4-4"/></svg>
                <div class="panel-title mb-0.5">Cabin Humidity</div>
                <div class="chart-legend">
                    <span><span class="legend-dot text-amber">&#9679;</span> Forepeak {{ live?.cabin_humidity_forepeak != null ? fmt(live.cabin_humidity_forepeak, 0) + '%' : '—' }}</span>
                    <span><span class="legend-dot text-blue">&#9679;</span> Quarterberth {{ live?.cabin_humidity_quarterberth != null ? fmt(live.cabin_humidity_quarterberth, 0) + '%' : '—' }}</span>
                    <span><span class="legend-dot text-pink">&#9679;</span> Main Cabin {{ live?.cabin_humidity_main != null ? fmt(live.cabin_humidity_main, 0) + '%' : '—' }}</span>
                </div>
                <svg viewBox="0 0 400 100" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="humGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--color-amber)" stop-opacity="0.10"/>
                            <stop offset="100%" stop-color="var(--color-amber)" stop-opacity="0.01"/>
                        </linearGradient>
                    </defs>
                    <polygon v-if="humidityHistoryForepeak?.length" :points="toArea(humidityHistoryForepeak, 400, 100, humidityMin, humidityMax)" fill="url(#humGrad)"/>
                    <polyline v-if="humidityHistoryForepeak?.length" :points="toLine(humidityHistoryForepeak, 400, 100, humidityMin, humidityMax)" fill="none" stroke="var(--color-amber)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                    <polyline v-if="humidityHistoryQuarterberth?.length" :points="toLine(humidityHistoryQuarterberth, 400, 100, humidityMin, humidityMax)" fill="none" stroke="var(--color-blue)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                    <polyline v-if="humidityHistoryMainCabin?.length" :points="toLine(humidityHistoryMainCabin, 400, 100, humidityMin, humidityMax)" fill="none" stroke="var(--color-pink)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                    <text v-if="!hasHumidityData" x="200" y="55" text-anchor="middle" font-size="12" fill="var(--color-text-dim)">No data</text>
                </svg>
                <div class="chart-axis"><span>24h ago</span><span>now</span></div>
            </Link>
        </div>

        <!-- Compass + Power & Tanks -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
            <div class="panel p-4">
                <div class="panel-title mb-3">Compass</div>
                <div class="compass-layout">
                    <svg viewBox="0 0 140 140" class="compass-svg">
                        <circle cx="70" cy="70" r="64" fill="none" stroke="var(--color-border)" stroke-width="1.5"/>
                        <g v-once>
                            <line
                                v-for="tick in compassTicks"
                                :key="tick"
                                :x1="70 + 58 * Math.sin(tick * Math.PI / 180)"
                                :y1="70 - 58 * Math.cos(tick * Math.PI / 180)"
                                :x2="70 + 64 * Math.sin(tick * Math.PI / 180)"
                                :y2="70 - 64 * Math.cos(tick * Math.PI / 180)"
                                stroke="var(--color-text-dim)"
                                stroke-width="1"
                            />
                        </g>
                        <text x="70" y="13" text-anchor="middle" font-size="11" font-weight="600" fill="var(--color-text-secondary)">N</text>
                        <text x="127" y="74" text-anchor="middle" font-size="11" font-weight="600" fill="var(--color-text-secondary)">E</text>
                        <text x="70" y="135" text-anchor="middle" font-size="11" font-weight="600" fill="var(--color-text-secondary)">S</text>
                        <text x="13" y="74" text-anchor="middle" font-size="11" font-weight="600" fill="var(--color-text-secondary)">W</text>
                        <circle cx="70" cy="70" r="3" fill="var(--color-text-dim)"/>
                        <!-- Heading (blue) -->
                        <g :style="`transform: rotate(${live?.heading ?? 0}deg)`" style="transform-origin: 70px 70px">
                            <polygon points="70,14 64,34 76,34" fill="var(--color-blue)"/>
                            <line x1="70" y1="34" x2="70" y2="90" stroke="var(--color-blue)" stroke-width="2"/>
                        </g>
                        <!-- True wind direction (scarlet, shorter) -->
                        <g :style="`transform: rotate(${live?.wind_direction_true ?? 0}deg)`" style="transform-origin: 70px 70px">
                            <polygon points="70,18 66,32 74,32" fill="var(--color-scarlet)" opacity="0.85"/>
                            <line x1="70" y1="32" x2="70" y2="56" stroke="var(--color-scarlet)" stroke-width="1.5" opacity="0.85"/>
                        </g>
                    </svg>
                    <div class="flex-1 space-y-2 text-[13px]">
                        <div class="data-row">
                            <span>Heading</span>
                            <span>{{ fmt(live?.heading, 0) }}°</span>
                        </div>
                        <div class="data-row">
                            <span>COG</span>
                            <span>{{ fmt(live?.cog, 0) }}°</span>
                        </div>
                        <div class="data-row">
                            <span>SOG</span>
                            <span class="text-blue">{{ fmt(live?.speed_sog) }} kn</span>
                        </div>
                        <div class="data-row">
                            <span>STW</span>
                            <span>{{ fmt(live?.speed_stw) }} kn</span>
                        </div>
                        <div class="data-row">
                            <span>True Wind</span>
                            <span class="text-scarlet">{{ live?.wind_speed_true != null ? fmt(live.wind_speed_true) + ' kn @ ' + fmt(live.wind_direction_true, 0) + '°' : '—' }}</span>
                        </div>
                        <div class="data-row">
                            <span>Apparent</span>
                            <span>{{ live?.wind_speed_apparent != null ? fmt(live.wind_speed_apparent) + ' kn @ ' + fmt(live.wind_angle_apparent, 0) + '°' : '—' }}</span>
                        </div>
                        <div class="data-row">
                            <span>Heel</span>
                            <span>{{ fmt(live?.heel) }}°</span>
                        </div>
                        <div class="data-row">
                            <span>Trip</span>
                            <span>{{ fmt(adjustedTrip) }} nm</span>
                        </div>
                        <template v-if="live?.nav_wp_distance > 0 && live?.nav_wp_ttg > 0">
                            <div class="data-row">
                                <span>Next WP</span>
                                <span class="text-blue">{{ fmt(live.nav_wp_distance) }} nm</span>
                            </div>
                            <div class="data-row">
                                <span>TTG</span>
                                <span>{{ formatTtg(live.nav_wp_ttg) }}</span>
                            </div>
                            <div class="data-row">
                                <span>ETA</span>
                                <span>{{ formatEta(live.nav_wp_ttg) }}</span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="panel p-4">
                <div class="panel-title mb-3">Power & Tanks</div>

                <div class="section-label">House Battery</div>
                <div class="space-y-2 text-[13px] mb-4">
                    <div class="data-row">
                        <span>Voltage</span>
                        <span>{{ fmt(live?.house_battery_voltage, 2) }} V</span>
                    </div>
                    <div class="data-row">
                        <span>State of Charge</span>
                        <span class="text-green">{{ fmt(live?.house_battery_soc, 0) }}%</span>
                    </div>
                    <div class="data-row">
                        <span>Current</span>
                        <span :class="live?.house_battery_current > 0 ? 'text-green' : 'text-amber'">{{ fmt(live?.house_battery_current) }} A</span>
                    </div>
                    <div class="data-row">
                        <span>Time Remaining</span>
                        <span>{{ fmt(live?.house_battery_time_remaining, 0) }} h</span>
                    </div>
                </div>

                <div class="section-label">Engine Battery</div>
                <div class="space-y-2 text-[13px] mb-4">
                    <div class="data-row">
                        <span>Voltage</span>
                        <span>{{ fmt(live?.engine_battery_voltage, 2) }} V</span>
                    </div>
                </div>

                <div class="section-label">Tanks</div>
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-[12px] mb-1.5">
                            <span class="text-text-secondary">Fuel</span>
                            <span class="tabular-nums font-semibold text-amber">{{ fmt(live?.fuel_level, 0) }}%</span>
                        </div>
                        <div class="tank-track">
                            <div class="tank-fill bg-amber" :style="{ transform: 'scaleX(' + (clamp(live?.fuel_level) / 100) + ')' }"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-[12px] mb-1.5">
                            <span class="text-text-secondary">Fresh Water</span>
                            <span class="tabular-nums font-semibold text-blue">{{ fmt(live?.water_level, 0) }}%</span>
                        </div>
                        <div class="tank-track">
                            <div class="tank-fill bg-blue" :style="{ transform: 'scaleX(' + (clamp(live?.water_level) / 100) + ')' }"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tank Level History -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4 mt-3">
            <Link href="/admin/explore?metric=fuel_level&range=24h" class="panel explore-link p-4">
                <svg class="explore-icon" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2H2v4M14 10v4h-4M2 6l4-4M10 14l4-4"/></svg>
                <div class="panel-head">
                    <span class="panel-title">Diesel Level</span>
                    <span class="tabular-nums text-[12px] font-medium text-amber">{{ live?.fuel_level != null ? fmt(live.fuel_level, 0) + '%' : '—' }}</span>
                </div>
                <svg viewBox="0 0 400 100" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="fuelGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--color-amber)" stop-opacity="0.18"/>
                            <stop offset="100%" stop-color="var(--color-amber)" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <polygon v-if="fuelHistory?.length" :points="toArea(fuelHistory, 400, 100, 0, 100)" fill="url(#fuelGrad)"/>
                    <polyline v-if="fuelHistory?.length" :points="toLine(fuelHistory, 400, 100, 0, 100)" fill="none" stroke="var(--color-amber)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                    <text v-if="!fuelHistory?.length" x="200" y="55" text-anchor="middle" font-size="12" fill="var(--color-text-dim)">No data</text>
                </svg>
                <div class="chart-axis"><span>24h ago</span><span>now</span></div>
            </Link>

            <Link href="/admin/explore?metric=water_level&range=24h" class="panel explore-link p-4">
                <svg class="explore-icon" viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2H2v4M14 10v4h-4M2 6l4-4M10 14l4-4"/></svg>
                <div class="panel-head">
                    <span class="panel-title">Fresh Water Level</span>
                    <span class="tabular-nums text-[12px] font-medium text-blue">{{ live?.water_level != null ? fmt(live.water_level, 0) + '%' : '—' }}</span>
                </div>
                <svg viewBox="0 0 400 100" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="waterGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--color-blue)" stop-opacity="0.18"/>
                            <stop offset="100%" stop-color="var(--color-blue)" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <polygon v-if="waterHistory?.length" :points="toArea(waterHistory, 400, 100, 0, 100)" fill="url(#waterGrad)"/>
                    <polyline v-if="waterHistory?.length" :points="toLine(waterHistory, 400, 100, 0, 100)" fill="none" stroke="var(--color-blue)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                    <text v-if="!waterHistory?.length" x="200" y="55" text-anchor="middle" font-size="12" fill="var(--color-text-dim)">No data</text>
                </svg>
                <div class="chart-axis"><span>24h ago</span><span>now</span></div>
            </Link>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { fmt } from '@/composables/useFormatters.js';

const props = defineProps({
    boat: Object,
    batteryHistory: Array,
    batteryPowerHistory: Array,
    speedHistory: Array,
    tempHistoryForepeak: Array,
    tempHistoryQuarterberth: Array,
    tempHistoryMainCabin: Array,
    humidityHistoryForepeak: Array,
    humidityHistoryQuarterberth: Array,
    humidityHistoryMainCabin: Array,
    fuelHistory: Array,
    waterHistory: Array,
    tripOffset: { type: Number, default: 0 },
});

const metrics = ref(null);
const lastUpdate = ref(null);
let echoChannel = null;
if (window.Echo) {
    echoChannel = window.Echo.channel('metrics');
    echoChannel.listen('.metrics.updated', (data) => {
        metrics.value = data;
        lastUpdate.value = Date.now();
    });
}
const live = computed(() => metrics.value?.boat ?? props.boat);
const adjustedTrip = computed(() => {
    const raw = live.value?.trip_log;
    if (raw == null) return null;
    return Math.max(0, raw - props.tripOffset);
});

const now = ref(Date.now());
let ticker = null;
onMounted(() => { ticker = setInterval(() => { now.value = Date.now(); }, 1000); });
onUnmounted(() => {
    if (ticker) clearInterval(ticker);
    if (echoChannel) window.Echo?.leave('metrics');
});

const timeSinceUpdate = computed(() => {
    if (!lastUpdate.value) return 'Live';
    const seconds = Math.floor((now.value - lastUpdate.value) / 1000);
    return `last update ${seconds}s ago`;
});

const compassTicks = [0, 30, 60, 90, 120, 150, 180, 210, 240, 270, 300, 330];

const allTempValues = computed(() => {
    const fp = (props.tempHistoryForepeak ?? []).map(d => d.value);
    const qb = (props.tempHistoryQuarterberth ?? []).map(d => d.value);
    const mc = (props.tempHistoryMainCabin ?? []).map(d => d.value);
    return [...fp, ...qb, ...mc];
});
const tempMin = computed(() => allTempValues.value.length ? Math.min(...allTempValues.value) - 2 : 0);
const tempMax = computed(() => allTempValues.value.length ? Math.max(...allTempValues.value) + 2 : 40);
const hasTempData = computed(() => allTempValues.value.length > 0);

const allHumidityValues = computed(() => {
    const fp = (props.humidityHistoryForepeak ?? []).map(d => d.value);
    const qb = (props.humidityHistoryQuarterberth ?? []).map(d => d.value);
    const mc = (props.humidityHistoryMainCabin ?? []).map(d => d.value);
    return [...fp, ...qb, ...mc];
});
const humidityMin = computed(() => allHumidityValues.value.length ? Math.min(...allHumidityValues.value) - 5 : 0);
const humidityMax = computed(() => allHumidityValues.value.length ? Math.max(...allHumidityValues.value) + 5 : 100);
const hasHumidityData = computed(() => allHumidityValues.value.length > 0);

const batteryMin = computed(() => {
    const vals = (props.batteryHistory ?? []).map(d => d.value);
    return vals.length ? Math.min(...vals) - 0.2 : 10;
});
const batteryMax = computed(() => {
    const vals = (props.batteryHistory ?? []).map(d => d.value);
    return vals.length ? Math.max(...vals) + 0.2 : 15;
});
const batteryVal = computed(() => {
    const v = live.value?.house_battery_voltage;
    return v != null ? Number(v).toFixed(2) + ' V' : '—';
});

const speedMax = computed(() => {
    const vals = (props.speedHistory ?? []).map(d => d.value);
    return vals.length ? Math.max(...vals, 1) * 1.15 : 10;
});

const powerVals = computed(() => (props.batteryPowerHistory ?? []).map(d => d.value));
const powerAbsMax = computed(() => {
    if (!powerVals.value.length) return 300;
    const absMax = Math.max(Math.abs(Math.min(...powerVals.value)), Math.abs(Math.max(...powerVals.value)));
    return Math.max(absMax * 1.15, 50);
});
const livePower = computed(() => {
    const v = live.value?.house_battery_voltage;
    const a = live.value?.house_battery_current;
    if (v == null || a == null) return null;
    return v * a;
});
const livePowerLabel = computed(() => {
    if (livePower.value == null) return '—';
    const w = livePower.value;
    const sign = w >= 0 ? '+' : '';
    return `${sign}${Math.round(w)} W`;
});

function toPowerLine(data, w, h, absMax) {
    if (!data?.length) return '';
    const mid = h / 2;
    return data.map((d, i) => {
        const x = (i / (data.length - 1)) * w;
        const y = mid - (d.value / absMax) * (mid - 5);
        return `${x},${y}`;
    }).join(' ');
}

function toPowerArea(data, w, h, absMax, positive) {
    if (!data?.length) return '';
    const mid = h / 2;
    const filtered = data.map((d, i) => {
        const clamped = positive ? Math.max(0, d.value) : Math.min(0, d.value);
        const x = (i / (data.length - 1)) * w;
        const y = mid - (clamped / absMax) * (mid - 5);
        return `${x},${y}`;
    }).join(' ');
    return `0,${mid} ${filtered} ${w},${mid}`;
}

function clamp(val) {
    if (val == null || isNaN(val)) return 0;
    return Math.min(100, Math.max(0, Number(val)));
}

function toLine(data, w, h, min, max) {
    if (!data?.length) return '';
    const range = max - min || 1;
    return data.map((d, i) => {
        const x = (i / (data.length - 1)) * w;
        const y = h - ((d.value - min) / range) * (h - 10) - 5;
        return `${x},${y}`;
    }).join(' ');
}

function toArea(data, w, h, min, max) {
    if (!data?.length) return '';
    return `0,${h} ${toLine(data, w, h, min, max)} ${w},${h}`;
}

function formatTtg(seconds) {
    if (seconds == null || seconds <= 0) return '—';
    const s = Math.floor(seconds);
    const d = Math.floor(s / 86400);
    const h = Math.floor((s % 86400) / 3600);
    const m = Math.floor((s % 3600) / 60);
    if (d > 0) return `${d}d ${h}h`;
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
}

function formatEta(seconds) {
    if (seconds == null || seconds <= 0) return '—';
    const eta = new Date(Date.now() + seconds * 1000);
    return eta.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.strip {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 16px;
    margin-bottom: 24px;
    overflow: hidden;
}

@media (min-width: 640px) {
    .strip { grid-template-columns: repeat(5, 1fr); }
}

.strip-cell {
    padding: 14px 16px;
    text-align: center;
    border-bottom: 1px solid var(--color-border-light);
    border-right: 1px solid var(--color-border-light);
}

.strip-cell:nth-child(even) { border-right: none; }
.strip-cell:nth-last-child(-n+2) { border-bottom: none; }
.strip-cell:last-child { grid-column: 1 / -1; border-right: none; }

@media (min-width: 640px) {
    .strip-cell { border-bottom: none; border-right: none; }
    .strip-cell + .strip-cell { border-left: 1px solid var(--color-border-light); }
    .strip-cell:last-child { grid-column: auto; }
}

.strip-label {
    font-size: 11px;
    font-weight: 800;
    font-family: var(--font-body);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-dim);
    margin-bottom: 4px;
}

.strip-value {
    font-family: var(--font-sans);
    font-size: 24px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    line-height: 1;
    color: var(--color-text-primary);
}

.strip-value--depth {
    color: var(--color-blue);
}

.strip-unit {
    font-size: 11px;
    color: var(--color-text-dim);
    margin-top: 3px;
}

.panel-head {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 8px;
}

.chart-axis {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    color: var(--color-text-dim);
    margin-top: 4px;
}

.chart-legend {
    display: flex;
    gap: 16px;
    font-size: 12px;
    color: var(--color-text-dim);
    font-variant-numeric: tabular-nums;
    margin-bottom: 10px;
}

.legend-dot {
    font-weight: 500;
}

.section-label {
    font-size: 11px;
    font-weight: 800;
    font-family: var(--font-body);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-dim);
    margin-bottom: 8px;
}

.data-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}

.data-row span:first-child {
    font-family: var(--font-body);
    color: var(--color-text-secondary);
}

.data-row span:last-child {
    font-family: var(--font-sans);
    font-variant-numeric: tabular-nums;
    font-weight: 600;
}

.tank-track {
    height: 8px;
    border-radius: 9999px;
    background: var(--color-bg);
    overflow: hidden;
}

.tank-fill {
    height: 100%;
    width: 100%;
    border-radius: 9999px;
    transform-origin: left;
    transition: transform 0.5s ease-out;
}

.compass-layout {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
}

.compass-svg {
    width: 140px;
    height: 140px;
    flex-shrink: 0;
}

@media (min-width: 480px) {
    .compass-layout {
        flex-direction: row;
        align-items: flex-start;
        gap: 20px;
    }
}
</style>
