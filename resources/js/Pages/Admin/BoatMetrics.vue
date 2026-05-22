<template>
    <AdminLayout>
        <Head title="Boat Metrics" />
        <!-- 1. Page header -->
        <div class="flex items-baseline justify-between mb-6">
            <h1 class="text-[22px] font-bold">Boat Metrics</h1>
            <div class="flex items-center gap-2 text-[13px] text-text-dim">
                <span class="w-2 h-2 rounded-full bg-green inline-block" :class="lastUpdate ? 'opacity-100' : 'opacity-30'"></span>
                <span class="tabular-nums">{{ timeSinceUpdate }}</span>
            </div>
        </div>

        <!-- 2. Critical metrics strip -->
        <div class="grid grid-cols-5 gap-3 mb-6">
            <!-- Speed (SOG) -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">Speed</div>
                <div class="text-[24px] font-bold tabular-nums text-scarlet leading-none mb-1">
                    {{ live?.speed_sog != null ? live.speed_sog.toFixed(1) : '—' }}
                </div>
                <div class="text-[11px] text-text-dim mb-2">kn SOG</div>
                <svg viewBox="0 0 80 24" class="w-full h-[24px]" preserveAspectRatio="none">
                    <polyline
                        v-if="speedSparkline.length"
                        :points="speedSparkline"
                        fill="none"
                        stroke="oklch(0.54 0.22 27)"
                        stroke-width="1.5"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                </svg>
            </div>

            <!-- Depth -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">Depth</div>
                <div class="text-[24px] font-bold tabular-nums leading-none mb-1" style="color: oklch(0.55 0.15 240)">
                    {{ live?.depth != null ? live.depth.toFixed(1) : '—' }}
                </div>
                <div class="text-[11px] text-text-dim mb-2">m</div>
                <svg viewBox="0 0 80 24" class="w-full h-[24px]" preserveAspectRatio="none">
                    <text x="40" y="16" text-anchor="middle" font-size="9" fill="oklch(0.80 0.005 40)">
                        {{ live?.depth != null ? live.depth.toFixed(1) + 'm' : '' }}
                    </text>
                </svg>
            </div>

            <!-- Wind -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">Wind</div>
                <div class="text-[24px] font-bold tabular-nums text-green leading-none mb-1">
                    {{ live?.wind_speed_true != null ? live.wind_speed_true.toFixed(1) : '—' }}
                </div>
                <div class="text-[11px] text-text-dim mb-2">kn true</div>
                <svg viewBox="0 0 80 24" class="w-full h-[24px]" preserveAspectRatio="none">
                    <text x="40" y="16" text-anchor="middle" font-size="9" fill="oklch(0.80 0.005 40)">
                        {{ live?.wind_direction_true != null ? live.wind_direction_true.toFixed(0) + '°' : '' }}
                    </text>
                </svg>
            </div>

            <!-- Battery Voltage -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">Battery</div>
                <div class="text-[24px] font-bold tabular-nums text-green leading-none mb-1">
                    {{ live?.house_battery_voltage != null ? Number(live.house_battery_voltage).toFixed(1) : (props.boat?.house_battery_voltage != null ? Number(props.boat.house_battery_voltage).toFixed(1) : '—') }}
                </div>
                <div class="text-[11px] text-text-dim mb-2">V</div>
                <svg viewBox="0 0 80 24" class="w-full h-[24px]" preserveAspectRatio="none">
                    <polygon
                        v-if="batterySparkline.area"
                        :points="batterySparkline.area"
                        fill="oklch(0.62 0.15 155 / 0.15)"
                    />
                    <polyline
                        v-if="batterySparkline.line"
                        :points="batterySparkline.line"
                        fill="none"
                        stroke="oklch(0.62 0.15 155)"
                        stroke-width="1.5"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                </svg>
            </div>

            <!-- Heading -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">Heading</div>
                <div class="text-[24px] font-bold tabular-nums leading-none mb-1" style="color: oklch(0.60 0.005 40)">
                    {{ live?.heading != null ? live.heading.toFixed(0) : '—' }}
                </div>
                <div class="text-[11px] text-text-dim mb-2">° mag</div>
                <svg viewBox="0 0 80 24" class="w-full h-[24px]" preserveAspectRatio="none">
                    <text x="40" y="16" text-anchor="middle" font-size="9" fill="oklch(0.80 0.005 40)">
                        {{ live?.cog != null ? 'COG ' + live.cog.toFixed(0) + '°' : '' }}
                    </text>
                </svg>
            </div>
        </div>

        <!-- 3. Temperature + Humidity charts -->
        <div class="grid grid-cols-2 gap-4 mb-4">
            <!-- Temperature (3-zone) -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="text-[15px] font-semibold mb-0.5">Cabin Temperature</div>
                <div class="text-[12px] text-text-dim mb-3 tabular-nums flex gap-4">
                    <span><span class="font-medium" style="color: oklch(0.70 0.14 70)">&#9679;</span> Forepeak {{ live?.cabin_temp_forepeak != null ? Number(live.cabin_temp_forepeak).toFixed(1) + '°C' : '—' }}</span>
                    <span><span class="font-medium" style="color: oklch(0.60 0.16 240)">&#9679;</span> Quarterberth {{ live?.cabin_temp_quarterberth != null ? Number(live.cabin_temp_quarterberth).toFixed(1) + '°C' : '—' }}</span>
                    <span><span class="font-medium" style="color: oklch(0.65 0.18 330)">&#9679;</span> Main Cabin {{ live?.cabin_temp_main != null ? Number(live.cabin_temp_main).toFixed(1) + '°C' : '—' }}</span>
                </div>
                <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="tempGradFp" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="oklch(0.70 0.14 70)" stop-opacity="0.10"/>
                            <stop offset="100%" stop-color="oklch(0.70 0.14 70)" stop-opacity="0.01"/>
                        </linearGradient>
                    </defs>
                    <polygon v-if="props.tempHistoryForepeak?.length" :points="toAreaPolygon(props.tempHistoryForepeak, 400, 120, tempMin, tempMax)" fill="url(#tempGradFp)"/>
                    <polyline v-if="props.tempHistoryForepeak?.length" :points="toPolyline(props.tempHistoryForepeak, 400, 120, tempMin, tempMax)" fill="none" stroke="oklch(0.70 0.14 70)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                    <polyline v-if="props.tempHistoryQuarterberth?.length" :points="toPolyline(props.tempHistoryQuarterberth, 400, 120, tempMin, tempMax)" fill="none" stroke="oklch(0.60 0.16 240)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                    <polyline v-if="props.tempHistoryMainCabin?.length" :points="toPolyline(props.tempHistoryMainCabin, 400, 120, tempMin, tempMax)" fill="none" stroke="oklch(0.65 0.18 330)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                    <text v-if="!hasTempData" x="200" y="65" text-anchor="middle" font-size="12" fill="oklch(0.70 0.005 40)">No data</text>
                </svg>
                <div class="flex justify-between text-[10px] text-text-dim mt-1">
                    <span>24h ago</span><span>now</span>
                </div>
            </div>

            <!-- Humidity (3-zone) -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="text-[15px] font-semibold mb-0.5">Cabin Humidity</div>
                <div class="text-[12px] text-text-dim mb-3 tabular-nums flex gap-4">
                    <span><span class="font-medium" style="color: oklch(0.70 0.14 70)">&#9679;</span> Forepeak {{ live?.cabin_humidity_forepeak != null ? Number(live.cabin_humidity_forepeak).toFixed(0) + '%' : '—' }}</span>
                    <span><span class="font-medium" style="color: oklch(0.60 0.16 240)">&#9679;</span> Quarterberth {{ live?.cabin_humidity_quarterberth != null ? Number(live.cabin_humidity_quarterberth).toFixed(0) + '%' : '—' }}</span>
                    <span><span class="font-medium" style="color: oklch(0.65 0.18 330)">&#9679;</span> Main Cabin {{ live?.cabin_humidity_main != null ? Number(live.cabin_humidity_main).toFixed(0) + '%' : '—' }}</span>
                </div>
                <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="humidGradFp" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="oklch(0.70 0.14 70)" stop-opacity="0.10"/>
                            <stop offset="100%" stop-color="oklch(0.70 0.14 70)" stop-opacity="0.01"/>
                        </linearGradient>
                    </defs>
                    <polygon v-if="props.humidityHistoryForepeak?.length" :points="toAreaPolygon(props.humidityHistoryForepeak, 400, 120, humidityMin, humidityMax)" fill="url(#humidGradFp)"/>
                    <polyline v-if="props.humidityHistoryForepeak?.length" :points="toPolyline(props.humidityHistoryForepeak, 400, 120, humidityMin, humidityMax)" fill="none" stroke="oklch(0.70 0.14 70)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                    <polyline v-if="props.humidityHistoryQuarterberth?.length" :points="toPolyline(props.humidityHistoryQuarterberth, 400, 120, humidityMin, humidityMax)" fill="none" stroke="oklch(0.60 0.16 240)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                    <polyline v-if="props.humidityHistoryMainCabin?.length" :points="toPolyline(props.humidityHistoryMainCabin, 400, 120, humidityMin, humidityMax)" fill="none" stroke="oklch(0.65 0.18 330)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                    <text v-if="!hasHumidityData" x="200" y="65" text-anchor="middle" font-size="12" fill="oklch(0.70 0.005 40)">No data</text>
                </svg>
                <div class="flex justify-between text-[10px] text-text-dim mt-1">
                    <span>24h ago</span><span>now</span>
                </div>
            </div>
        </div>

        <!-- 5. Tank Levels -->
        <div class="bg-surface border border-border rounded-[10px] p-4 mb-4">
            <div class="text-[15px] font-semibold mb-4">Tank Levels</div>
            <div class="grid grid-cols-2 gap-6">
                <!-- Fuel -->
                <div>
                    <div class="flex justify-between text-[13px] mb-2">
                        <span class="text-text-secondary font-medium">Fuel</span>
                        <span class="tabular-nums font-semibold text-amber">
                            {{ live?.fuel_level != null ? live.fuel_level.toFixed(0) + '%' : (props.boat?.fuel_level != null ? props.boat.fuel_level.toFixed(0) + '%' : '—') }}
                        </span>
                    </div>
                    <div class="h-5 rounded-full bg-bg overflow-hidden">
                        <div
                            class="h-full rounded-full bg-amber transition-all duration-500"
                            :style="`width: ${Math.min(100, Math.max(0, live?.fuel_level ?? props.boat?.fuel_level ?? 0))}%`"
                        ></div>
                    </div>
                </div>
                <!-- Fresh Water -->
                <div>
                    <div class="flex justify-between text-[13px] mb-2">
                        <span class="text-text-secondary font-medium">Fresh Water</span>
                        <span class="tabular-nums font-semibold" style="color: oklch(0.55 0.15 240)">
                            {{ live?.water_level != null ? live.water_level.toFixed(0) + '%' : (props.boat?.water_level != null ? props.boat.water_level.toFixed(0) + '%' : '—') }}
                        </span>
                    </div>
                    <div class="h-5 rounded-full bg-bg overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-500"
                            style="background: oklch(0.55 0.15 240)"
                            :style="`width: ${Math.min(100, Math.max(0, live?.water_level ?? props.boat?.water_level ?? 0))}%`"
                        ></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Battery Voltage + Speed charts -->
        <div class="grid grid-cols-2 gap-4 mb-4">
            <!-- Battery Voltage -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="text-[15px] font-semibold mb-0.5">Battery Voltage</div>
                <div class="text-[12px] text-text-dim mb-3 tabular-nums">
                    <span class="text-green font-medium">{{ batteryVal }}</span>
                    &nbsp;current
                </div>
                <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="batteryGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="oklch(0.62 0.15 155)" stop-opacity="0.20"/>
                            <stop offset="100%" stop-color="oklch(0.62 0.15 155)" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <polygon v-if="props.batteryHistory?.length" :points="toAreaPolygon(props.batteryHistory, 400, 120, batteryMin, batteryMax)" fill="url(#batteryGrad)"/>
                    <polyline v-if="props.batteryHistory?.length" :points="toPolyline(props.batteryHistory, 400, 120, batteryMin, batteryMax)" fill="none" stroke="oklch(0.62 0.15 155)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                    <text v-if="!props.batteryHistory?.length" x="200" y="65" text-anchor="middle" font-size="12" fill="oklch(0.70 0.005 40)">No data</text>
                </svg>
                <div class="flex justify-between text-[10px] text-text-dim mt-1">
                    <span>24h ago</span><span>now</span>
                </div>
            </div>
            <!-- Speed -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="text-[15px] font-semibold mb-0.5">Speed</div>
                <div class="text-[12px] text-text-dim mb-3 tabular-nums">
                    <span class="text-scarlet font-medium">
                        {{ live?.speed_sog != null ? live.speed_sog.toFixed(1) + ' kn' : '—' }}
                    </span>
                    &nbsp;current
                </div>
                <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="speedGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="oklch(0.54 0.22 27)" stop-opacity="0.20"/>
                            <stop offset="100%" stop-color="oklch(0.54 0.22 27)" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <polygon v-if="props.speedHistory?.length" :points="toAreaPolygon(props.speedHistory, 400, 120, 0, speedMax)" fill="url(#speedGrad)"/>
                    <polyline v-if="props.speedHistory?.length" :points="toPolyline(props.speedHistory, 400, 120, 0, speedMax)" fill="none" stroke="oklch(0.54 0.22 27)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                    <text v-if="!props.speedHistory?.length" x="200" y="65" text-anchor="middle" font-size="12" fill="oklch(0.70 0.005 40)">No data</text>
                </svg>
                <div class="flex justify-between text-[10px] text-text-dim mt-1">
                    <span>24h ago</span><span>now</span>
                </div>
            </div>
        </div>

        <!-- 6. Batteries -->
        <div class="bg-surface border border-border rounded-[10px] p-4 mb-4">
            <div class="text-[15px] font-semibold mb-4">Batteries</div>
            <div class="grid grid-cols-2 gap-6">
                <div class="space-y-2.5 text-[13px]">
                    <div class="text-[12px] font-semibold text-text-dim uppercase tracking-wide mb-3">House Battery</div>
                    <div class="flex justify-between">
                        <span class="text-text-secondary">Voltage</span>
                        <span class="tabular-nums font-semibold">{{ live?.house_battery_voltage != null ? Number(live.house_battery_voltage).toFixed(2) + ' V' : '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-secondary">State of Charge</span>
                        <span class="tabular-nums font-semibold text-green">{{ live?.house_battery_soc != null ? Number(live.house_battery_soc).toFixed(0) + '%' : '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-secondary">Current</span>
                        <span class="tabular-nums font-semibold" :class="live?.house_battery_current != null && live.house_battery_current > 0 ? 'text-green' : 'text-amber'">{{ live?.house_battery_current != null ? Number(live.house_battery_current).toFixed(1) + ' A' : '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-secondary">Time Remaining</span>
                        <span class="tabular-nums font-semibold">{{ live?.house_battery_time_remaining != null ? Number(live.house_battery_time_remaining).toFixed(0) + ' h' : '—' }}</span>
                    </div>
                </div>
                <div class="space-y-2.5 text-[13px]">
                    <div class="text-[12px] font-semibold text-text-dim uppercase tracking-wide mb-3">Engine Battery</div>
                    <div class="flex justify-between">
                        <span class="text-text-secondary">Voltage</span>
                        <span class="tabular-nums font-semibold">{{ live?.engine_battery_voltage != null ? Number(live.engine_battery_voltage).toFixed(2) + ' V' : '—' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 7. Wind compass + Navigation compass -->
        <div class="grid grid-cols-2 gap-4 mb-4">
            <!-- Wind compass rose -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="text-[15px] font-semibold mb-4">Wind</div>
                <div class="flex items-center gap-6">
                    <svg viewBox="0 0 140 140" class="w-[140px] h-[140px] shrink-0">
                        <circle cx="70" cy="70" r="64" fill="none" stroke="oklch(0.90 0.005 70)" stroke-width="1.5"/>
                        <!-- Tick marks at 30° intervals -->
                        <g v-for="tick in compassTicks" :key="tick">
                            <line
                                :x1="70 + 58 * Math.sin(tick * Math.PI / 180)"
                                :y1="70 - 58 * Math.cos(tick * Math.PI / 180)"
                                :x2="70 + 64 * Math.sin(tick * Math.PI / 180)"
                                :y2="70 - 64 * Math.cos(tick * Math.PI / 180)"
                                stroke="oklch(0.80 0.005 40)"
                                stroke-width="1"
                            />
                        </g>
                        <!-- Cardinal labels -->
                        <text x="70" y="13" text-anchor="middle" font-size="11" font-weight="600" fill="oklch(0.45 0.005 40)">N</text>
                        <text x="127" y="74" text-anchor="middle" font-size="11" font-weight="600" fill="oklch(0.45 0.005 40)">E</text>
                        <text x="70" y="135" text-anchor="middle" font-size="11" font-weight="600" fill="oklch(0.45 0.005 40)">S</text>
                        <text x="13" y="74" text-anchor="middle" font-size="11" font-weight="600" fill="oklch(0.45 0.005 40)">W</text>
                        <!-- Wind arrow (scarlet) -->
                        <g :style="`transform: rotate(${live?.wind_direction_true ?? props.boat?.wind_direction_true ?? 0}deg)`" style="transform-origin: 70px 70px">
                            <polygon points="70,14 64,34 76,34" fill="oklch(0.54 0.22 27)"/>
                            <line x1="70" y1="34" x2="70" y2="90" stroke="oklch(0.54 0.22 27)" stroke-width="2"/>
                        </g>
                    </svg>
                    <div class="flex-1 space-y-2.5 text-[13px]">
                        <div class="flex justify-between">
                            <span class="text-text-secondary">True Direction</span>
                            <span class="tabular-nums font-semibold">{{ live?.wind_direction_true != null ? live.wind_direction_true.toFixed(0) + '°' : (props.boat?.wind_direction_true != null ? props.boat.wind_direction_true.toFixed(0) + '°' : '—') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-secondary">True Speed</span>
                            <span class="tabular-nums font-semibold text-scarlet">{{ live?.wind_speed_true != null ? live.wind_speed_true.toFixed(1) + ' kn' : (props.boat?.wind_speed_true != null ? props.boat.wind_speed_true.toFixed(1) + ' kn' : '—') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-secondary">Apparent Speed</span>
                            <span class="tabular-nums font-semibold">{{ live?.wind_speed_apparent != null ? live.wind_speed_apparent.toFixed(1) + ' kn' : (props.boat?.wind_speed_apparent != null ? props.boat.wind_speed_apparent.toFixed(1) + ' kn' : '—') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-secondary">Apparent Angle</span>
                            <span class="tabular-nums font-semibold">{{ live?.wind_angle_apparent != null ? live.wind_angle_apparent.toFixed(0) + '°' : (props.boat?.wind_angle_apparent != null ? props.boat.wind_angle_apparent.toFixed(0) + '°' : '—') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation compass -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="text-[15px] font-semibold mb-4">Navigation</div>
                <div class="flex items-center gap-6">
                    <svg viewBox="0 0 140 140" class="w-[140px] h-[140px] shrink-0">
                        <circle cx="70" cy="70" r="64" fill="none" stroke="oklch(0.90 0.005 70)" stroke-width="1.5"/>
                        <!-- Tick marks at 30° intervals -->
                        <g v-for="tick in compassTicks" :key="tick">
                            <line
                                :x1="70 + 58 * Math.sin(tick * Math.PI / 180)"
                                :y1="70 - 58 * Math.cos(tick * Math.PI / 180)"
                                :x2="70 + 64 * Math.sin(tick * Math.PI / 180)"
                                :y2="70 - 64 * Math.cos(tick * Math.PI / 180)"
                                stroke="oklch(0.80 0.005 40)"
                                stroke-width="1"
                            />
                        </g>
                        <!-- Cardinal labels -->
                        <text x="70" y="13" text-anchor="middle" font-size="11" font-weight="600" fill="oklch(0.45 0.005 40)">N</text>
                        <text x="127" y="74" text-anchor="middle" font-size="11" font-weight="600" fill="oklch(0.45 0.005 40)">E</text>
                        <text x="70" y="135" text-anchor="middle" font-size="11" font-weight="600" fill="oklch(0.45 0.005 40)">S</text>
                        <text x="13" y="74" text-anchor="middle" font-size="11" font-weight="600" fill="oklch(0.45 0.005 40)">W</text>
                        <!-- Heading arrow (blue) -->
                        <g :style="`transform: rotate(${live?.heading ?? props.boat?.heading ?? 0}deg)`" style="transform-origin: 70px 70px">
                            <polygon points="70,14 64,34 76,34" fill="oklch(0.55 0.15 240)"/>
                            <line x1="70" y1="34" x2="70" y2="90" stroke="oklch(0.55 0.15 240)" stroke-width="2"/>
                        </g>
                    </svg>
                    <div class="flex-1 space-y-2.5 text-[13px]">
                        <div class="flex justify-between">
                            <span class="text-text-secondary">COG</span>
                            <span class="tabular-nums font-semibold">{{ live?.cog != null ? live.cog.toFixed(0) + '°' : (props.boat?.cog != null ? props.boat.cog.toFixed(0) + '°' : '—') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-secondary">SOG</span>
                            <span class="tabular-nums font-semibold" style="color: oklch(0.55 0.15 240)">{{ live?.speed_sog != null ? live.speed_sog.toFixed(1) + ' kn' : (props.boat?.speed_sog != null ? props.boat.speed_sog.toFixed(1) + ' kn' : '—') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-secondary">STW</span>
                            <span class="tabular-nums font-semibold">{{ live?.speed_stw != null ? live.speed_stw.toFixed(1) + ' kn' : (props.boat?.speed_stw != null ? props.boat.speed_stw.toFixed(1) + ' kn' : '—') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-secondary">Heel</span>
                            <span class="tabular-nums font-semibold">{{ live?.heel != null ? live.heel.toFixed(1) + '°' : (props.boat?.heel != null ? props.boat.heel.toFixed(1) + '°' : '—') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-secondary">Trip Log</span>
                            <span class="tabular-nums font-semibold">{{ live?.trip_log != null ? live.trip_log.toFixed(1) + ' nm' : (props.boat?.trip_log != null ? props.boat.trip_log.toFixed(1) + ' nm' : '—') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 8. Environment -->
        <div class="bg-surface border border-border rounded-[10px] p-4 mb-6">
            <div class="text-[15px] font-semibold mb-4">Environment</div>
            <div class="space-y-4">
                <div>
                    <div class="text-[12px] font-semibold text-text-dim uppercase tracking-wide mb-2">Forepeak</div>
                    <div class="grid grid-cols-2 gap-4 text-[13px]">
                        <div class="flex justify-between">
                            <span class="text-text-secondary">Temperature</span>
                            <span class="tabular-nums font-semibold" style="color: oklch(0.70 0.14 70)">{{ live?.cabin_temp_forepeak != null ? Number(live.cabin_temp_forepeak).toFixed(1) + '°C' : '—' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-secondary">Humidity</span>
                            <span class="tabular-nums font-semibold" style="color: oklch(0.70 0.14 70)">{{ live?.cabin_humidity_forepeak != null ? Number(live.cabin_humidity_forepeak).toFixed(0) + '%' : '—' }}</span>
                        </div>
                    </div>
                </div>
                <div class="border-t border-border"></div>
                <div>
                    <div class="text-[12px] font-semibold text-text-dim uppercase tracking-wide mb-2">Quarterberth</div>
                    <div class="grid grid-cols-2 gap-4 text-[13px]">
                        <div class="flex justify-between">
                            <span class="text-text-secondary">Temperature</span>
                            <span class="tabular-nums font-semibold" style="color: oklch(0.60 0.16 240)">{{ live?.cabin_temp_quarterberth != null ? Number(live.cabin_temp_quarterberth).toFixed(1) + '°C' : '—' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-secondary">Humidity</span>
                            <span class="tabular-nums font-semibold" style="color: oklch(0.60 0.16 240)">{{ live?.cabin_humidity_quarterberth != null ? Number(live.cabin_humidity_quarterberth).toFixed(0) + '%' : '—' }}</span>
                        </div>
                    </div>
                </div>
                <div class="border-t border-border"></div>
                <div>
                    <div class="text-[12px] font-semibold text-text-dim uppercase tracking-wide mb-2">Main Cabin</div>
                    <div class="grid grid-cols-2 gap-4 text-[13px]">
                        <div class="flex justify-between">
                            <span class="text-text-secondary">Temperature</span>
                            <span class="tabular-nums font-semibold" style="color: oklch(0.65 0.18 330)">{{ live?.cabin_temp_main != null ? Number(live.cabin_temp_main).toFixed(1) + '°C' : '—' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-secondary">Humidity</span>
                            <span class="tabular-nums font-semibold" style="color: oklch(0.65 0.18 330)">{{ live?.cabin_humidity_main != null ? Number(live.cabin_humidity_main).toFixed(0) + '%' : '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { computed, ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    boat: Object,
    batteryHistory: Array,
    speedHistory: Array,
    tempHistoryForepeak: Array,
    tempHistoryQuarterberth: Array,
    tempHistoryMainCabin: Array,
    humidityHistoryForepeak: Array,
    humidityHistoryQuarterberth: Array,
    humidityHistoryMainCabin: Array,
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

// Tick every second to refresh "Xs ago"
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

// Compass tick marks at 30° intervals
const compassTicks = [0, 30, 60, 90, 120, 150, 180, 210, 240, 270, 300, 330];

// Temperature chart range (3-zone)
const allTempValues = computed(() => {
    const fp = (props.tempHistoryForepeak ?? []).map(d => d.value);
    const qb = (props.tempHistoryQuarterberth ?? []).map(d => d.value);
    const mc = (props.tempHistoryMainCabin ?? []).map(d => d.value);
    return [...fp, ...qb, ...mc];
});
const tempMin = computed(() => {
    const vals = allTempValues.value;
    return vals.length ? Math.min(...vals) - 2 : 0;
});
const tempMax = computed(() => {
    const vals = allTempValues.value;
    return vals.length ? Math.max(...vals) + 2 : 40;
});
const hasTempData = computed(() => allTempValues.value.length > 0);

// Humidity chart range (3-zone)
const allHumidityValues = computed(() => {
    const fp = (props.humidityHistoryForepeak ?? []).map(d => d.value);
    const qb = (props.humidityHistoryQuarterberth ?? []).map(d => d.value);
    const mc = (props.humidityHistoryMainCabin ?? []).map(d => d.value);
    return [...fp, ...qb, ...mc];
});
const humidityMin = computed(() => {
    const vals = allHumidityValues.value;
    return vals.length ? Math.min(...vals) - 5 : 0;
});
const humidityMax = computed(() => {
    const vals = allHumidityValues.value;
    return vals.length ? Math.max(...vals) + 5 : 100;
});
const hasHumidityData = computed(() => allHumidityValues.value.length > 0);

// Battery voltage chart range
const batteryMin = computed(() => {
    const vals = (props.batteryHistory ?? []).map(d => d.value);
    return vals.length ? Math.min(...vals) - 0.2 : 10;
});
const batteryMax = computed(() => {
    const vals = (props.batteryHistory ?? []).map(d => d.value);
    return vals.length ? Math.max(...vals) + 0.2 : 15;
});
const batteryVal = computed(() => {
    const v = live.value?.house_battery_voltage ?? props.boat?.house_battery_voltage;
    return v != null ? Number(v).toFixed(2) + ' V' : '—';
});

// Speed chart range
const speedMax = computed(() => {
    const vals = (props.speedHistory ?? []).map(d => d.value);
    return vals.length ? Math.max(...vals, 1) * 1.15 : 10;
});

// Battery sparkline (small, from 24h history)
const batterySparkline = computed(() => {
    const data = props.batteryHistory;
    if (!data || data.length < 2) return { line: '', area: '' };
    const bMin = batteryMin.value;
    const bMax = batteryMax.value;
    const line = toPolyline(data, 80, 24, bMin, bMax);
    const area = `0,24 ${line} 80,24`;
    return { line, area };
});

// Speed sparkline from 24h history
const speedSparkline = computed(() => {
    const data = props.speedHistory;
    if (!data || data.length < 2) return '';
    return toPolyline(data, 80, 24, 0, speedMax.value);
});

// SVG chart helpers
function toPolyline(data, viewWidth, viewHeight, minVal, maxVal) {
    if (!data || data.length === 0) return '';
    const range = maxVal - minVal || 1;
    return data.map((d, i) => {
        const x = (i / (data.length - 1)) * viewWidth;
        const y = viewHeight - ((d.value - minVal) / range) * (viewHeight - 10) - 5;
        return `${x},${y}`;
    }).join(' ');
}

function toAreaPolygon(data, viewWidth, viewHeight, minVal, maxVal) {
    if (!data || data.length === 0) return '';
    const line = toPolyline(data, viewWidth, viewHeight, minVal, maxVal);
    return `0,${viewHeight} ${line} ${viewWidth},${viewHeight}`;
}
</script>
