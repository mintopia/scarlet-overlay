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

            <!-- House Battery -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="text-[11px] font-semibold text-text-dim uppercase tracking-wide mb-2">House Battery</div>
                <div class="text-[24px] font-bold tabular-nums text-green leading-none mb-1">
                    {{ live?.house_battery_soc != null ? live.house_battery_soc.toFixed(0) : '—' }}
                </div>
                <div class="text-[11px] text-text-dim mb-2">% SOC</div>
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

        <!-- 3 & 4. Barometric Pressure + Battery SOC charts -->
        <div class="grid grid-cols-2 gap-4 mb-4">
            <!-- Barometric Pressure -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="text-[15px] font-semibold mb-0.5">Barometric Pressure</div>
                <div class="text-[12px] text-text-dim mb-3 tabular-nums">
                    <span style="color: oklch(0.55 0.15 240)" class="font-medium">
                        {{ live?.pressure != null ? live.pressure.toFixed(1) + ' hPa' : (props.boat?.pressure != null ? props.boat.pressure.toFixed(1) + ' hPa' : '—') }}
                    </span>
                    &nbsp;current
                </div>
                <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="pressureGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="oklch(0.55 0.15 240)" stop-opacity="0.20"/>
                            <stop offset="100%" stop-color="oklch(0.55 0.15 240)" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <polygon
                        v-if="props.pressureHistory?.length"
                        :points="toAreaPolygon(props.pressureHistory, 400, 120, pressureMin, pressureMax)"
                        fill="url(#pressureGrad)"
                    />
                    <polyline
                        v-if="props.pressureHistory?.length"
                        :points="toPolyline(props.pressureHistory, 400, 120, pressureMin, pressureMax)"
                        fill="none"
                        stroke="oklch(0.55 0.15 240)"
                        stroke-width="1.5"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                    <text v-if="!props.pressureHistory?.length" x="200" y="65" text-anchor="middle" font-size="12" fill="oklch(0.70 0.005 40)">No data</text>
                </svg>
                <div class="flex justify-between text-[10px] text-text-dim mt-1">
                    <span>24h ago</span><span>now</span>
                </div>
            </div>

            <!-- Battery SOC -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="text-[15px] font-semibold mb-0.5">Battery State of Charge</div>
                <div class="text-[12px] text-text-dim mb-3 tabular-nums">
                    <span class="text-green font-medium">
                        {{ live?.house_battery_soc != null ? live.house_battery_soc.toFixed(0) + '%' : (props.boat?.house_battery_soc != null ? props.boat.house_battery_soc.toFixed(0) + '%' : '—') }}
                    </span>
                    &nbsp;current
                </div>
                <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="batteryGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="oklch(0.62 0.15 155)" stop-opacity="0.20"/>
                            <stop offset="100%" stop-color="oklch(0.62 0.15 155)" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <polygon
                        v-if="props.batteryHistory?.length"
                        :points="toAreaPolygon(props.batteryHistory, 400, 120, 0, 100)"
                        fill="url(#batteryGrad)"
                    />
                    <polyline
                        v-if="props.batteryHistory?.length"
                        :points="toPolyline(props.batteryHistory, 400, 120, 0, 100)"
                        fill="none"
                        stroke="oklch(0.62 0.15 155)"
                        stroke-width="1.5"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                    <text v-if="!props.batteryHistory?.length" x="200" y="65" text-anchor="middle" font-size="12" fill="oklch(0.70 0.005 40)">No data</text>
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

        <!-- 6 & 7. Wind compass + Navigation compass -->
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

        <!-- 8. Power Balance -->
        <div class="bg-surface border border-border rounded-[10px] p-4 mb-4">
            <div class="flex items-center justify-between mb-4">
                <div class="text-[15px] font-semibold">Power Balance</div>
                <span
                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[12px] font-semibold"
                    :class="isCharging ? 'bg-green-bg text-green' : 'bg-amber-bg text-amber'"
                >
                    <span class="w-1.5 h-1.5 rounded-full inline-block" :class="isCharging ? 'bg-green' : 'bg-amber'"></span>
                    {{ isCharging ? 'Charging' : 'Discharging' }}
                </span>
            </div>

            <!-- Power bars -->
            <div class="space-y-3 mb-4">
                <div>
                    <div class="flex justify-between text-[12px] mb-1.5">
                        <span class="text-text-secondary">Solar Generation</span>
                        <span class="tabular-nums font-semibold text-green">{{ solarWatts != null ? solarWatts.toFixed(0) + ' W' : '—' }}</span>
                    </div>
                    <div class="h-3 rounded-full bg-bg overflow-hidden">
                        <div
                            class="h-full rounded-full bg-green transition-all duration-500"
                            :style="`width: ${powerBarWidth(solarWatts)}%`"
                        ></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-[12px] mb-1.5">
                        <span class="text-text-secondary">Load Consumption</span>
                        <span class="tabular-nums font-semibold text-amber">{{ loadWatts != null ? loadWatts.toFixed(0) + ' W' : '—' }}</span>
                    </div>
                    <div class="h-3 rounded-full bg-bg overflow-hidden">
                        <div
                            class="h-full rounded-full bg-amber transition-all duration-500"
                            :style="`width: ${powerBarWidth(loadWatts)}%`"
                        ></div>
                    </div>
                </div>
            </div>

            <!-- Details grid -->
            <div class="grid grid-cols-4 gap-4 pt-3 border-t border-border text-[13px]">
                <div>
                    <div class="text-[11px] text-text-dim uppercase tracking-wide mb-1">House Battery</div>
                    <div class="tabular-nums font-semibold">{{ live?.house_battery_voltage != null ? live.house_battery_voltage.toFixed(2) + ' V' : (props.boat?.house_battery_voltage != null ? props.boat.house_battery_voltage.toFixed(2) + ' V' : '—') }}</div>
                </div>
                <div>
                    <div class="text-[11px] text-text-dim uppercase tracking-wide mb-1">Engine Battery</div>
                    <div class="tabular-nums font-semibold">{{ live?.engine_battery_voltage != null ? live.engine_battery_voltage.toFixed(2) + ' V' : (props.boat?.engine_battery_voltage != null ? props.boat.engine_battery_voltage.toFixed(2) + ' V' : '—') }}</div>
                </div>
                <div>
                    <div class="text-[11px] text-text-dim uppercase tracking-wide mb-1">Solar Current</div>
                    <div class="tabular-nums font-semibold text-green">{{ live?.solar_current != null ? live.solar_current.toFixed(1) + ' A' : (props.boat?.solar_current != null ? props.boat.solar_current.toFixed(1) + ' A' : '—') }}</div>
                </div>
                <div>
                    <div class="text-[11px] text-text-dim uppercase tracking-wide mb-1">Load Current</div>
                    <div class="tabular-nums font-semibold text-amber">{{ live?.load_current != null ? live.load_current.toFixed(1) + ' A' : (props.boat?.load_current != null ? props.boat.load_current.toFixed(1) + ' A' : '—') }}</div>
                </div>
            </div>
        </div>

        <!-- 9 & 10. Water/Air temp chart + Engine panel -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <!-- Water & Air Temperature chart -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="text-[15px] font-semibold mb-0.5">Water & Air Temperature</div>
                <div class="text-[12px] text-text-dim mb-3 tabular-nums">
                    Water
                    <span style="color: oklch(0.55 0.15 240)" class="font-medium">
                        {{ live?.water_temp != null ? live.water_temp.toFixed(1) + '°C' : (props.boat?.water_temp != null ? props.boat.water_temp.toFixed(1) + '°C' : '—') }}
                    </span>
                    &nbsp;·&nbsp;Air
                    <span class="text-amber font-medium">
                        {{ live?.air_temp != null ? live.air_temp.toFixed(1) + '°C' : (props.boat?.air_temp != null ? props.boat.air_temp.toFixed(1) + '°C' : '—') }}
                    </span>
                </div>
                <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="waterTempGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="oklch(0.55 0.15 240)" stop-opacity="0.15"/>
                            <stop offset="100%" stop-color="oklch(0.55 0.15 240)" stop-opacity="0.02"/>
                        </linearGradient>
                        <linearGradient id="airTempGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="oklch(0.70 0.14 70)" stop-opacity="0.12"/>
                            <stop offset="100%" stop-color="oklch(0.70 0.14 70)" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <!-- Water temp fill -->
                    <polygon
                        v-if="props.waterTempHistory?.length"
                        :points="toAreaPolygon(props.waterTempHistory, 400, 120, tempMin, tempMax)"
                        fill="url(#waterTempGrad)"
                    />
                    <!-- Air temp fill -->
                    <polygon
                        v-if="props.airTempHistory?.length"
                        :points="toAreaPolygon(props.airTempHistory, 400, 120, tempMin, tempMax)"
                        fill="url(#airTempGrad)"
                    />
                    <!-- Water temp line -->
                    <polyline
                        v-if="props.waterTempHistory?.length"
                        :points="toPolyline(props.waterTempHistory, 400, 120, tempMin, tempMax)"
                        fill="none"
                        stroke="oklch(0.55 0.15 240)"
                        stroke-width="1.5"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                    <!-- Air temp line (dashed) -->
                    <polyline
                        v-if="props.airTempHistory?.length"
                        :points="toPolyline(props.airTempHistory, 400, 120, tempMin, tempMax)"
                        fill="none"
                        stroke="oklch(0.70 0.14 70)"
                        stroke-width="1.5"
                        stroke-dasharray="4 3"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                    <text v-if="!props.waterTempHistory?.length && !props.airTempHistory?.length" x="200" y="65" text-anchor="middle" font-size="12" fill="oklch(0.70 0.005 40)">No data</text>
                </svg>
                <div class="flex justify-between text-[10px] text-text-dim mt-1">
                    <span>24h ago</span><span>now</span>
                </div>
            </div>

            <!-- Engine panel -->
            <div class="bg-surface border border-border rounded-[10px] p-4">
                <div class="flex items-center justify-between mb-4">
                    <div class="text-[15px] font-semibold">Engine</div>
                    <span
                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[12px] font-semibold"
                        :class="engineRunning ? 'bg-green-bg text-green' : 'bg-bg text-text-dim'"
                    >
                        <span class="w-1.5 h-1.5 rounded-full inline-block" :class="engineRunning ? 'bg-green' : 'bg-text-dim'"></span>
                        {{ engineRunning ? 'Running' : 'Off' }}
                    </span>
                </div>
                <div class="space-y-2.5 text-[13px]">
                    <div class="flex justify-between">
                        <span class="text-text-secondary">RPM</span>
                        <span class="tabular-nums font-semibold" :class="engineRunning ? 'text-green' : 'text-text-dim'">
                            {{ engineRunning ? (live?.engine_rpm ?? props.boat?.engine_rpm)?.toFixed(0) : '—' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-secondary">Coolant Temp</span>
                        <span class="tabular-nums font-semibold">
                            {{ engineRunning && (live?.engine_coolant_temp ?? props.boat?.engine_coolant_temp) != null ? (live?.engine_coolant_temp ?? props.boat?.engine_coolant_temp).toFixed(0) + '°C' : '—' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-secondary">Engine Hours</span>
                        <span class="tabular-nums font-semibold">{{ (live?.engine_hours ?? props.boat?.engine_hours) != null ? (live?.engine_hours ?? props.boat?.engine_hours).toFixed(1) + ' h' : '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-secondary">Rudder Angle</span>
                        <span class="tabular-nums font-semibold">{{ (live?.rudder ?? props.boat?.rudder) != null ? (live?.rudder ?? props.boat?.rudder).toFixed(1) + '°' : '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-secondary">Rate of Turn</span>
                        <span class="tabular-nums font-semibold">{{ (live?.rate_of_turn ?? props.boat?.rate_of_turn) != null ? (live?.rate_of_turn ?? props.boat?.rate_of_turn).toFixed(1) + '°/min' : '—' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useMetrics } from '@/composables/useEcho.js';
import { computed, ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    boat: Object,
    pressureHistory: Array,
    batteryHistory: Array,
    waterTempHistory: Array,
    airTempHistory: Array,
});

const { metrics, lastUpdate } = useMetrics();
const live = computed(() => metrics.value?.boat ?? props.boat);

// Tick every second to refresh "Xs ago"
const now = ref(Date.now());
let ticker = null;
onMounted(() => { ticker = setInterval(() => { now.value = Date.now(); }, 1000); });
onUnmounted(() => { if (ticker) clearInterval(ticker); });

const timeSinceUpdate = computed(() => {
    if (!lastUpdate.value) return 'Live';
    const seconds = Math.floor((now.value - lastUpdate.value) / 1000);
    return `last update ${seconds}s ago`;
});

// Compass tick marks at 30° intervals
const compassTicks = [0, 30, 60, 90, 120, 150, 180, 210, 240, 270, 300, 330];

// Engine running check: RPM > 0 and not null
const engineRunning = computed(() => {
    const rpm = live.value?.engine_rpm ?? props.boat?.engine_rpm;
    return rpm != null && rpm > 0;
});

// Power calculations
const solarWatts = computed(() => live.value?.solar_power ?? props.boat?.solar_power ?? null);
const loadWatts = computed(() => {
    const current = live.value?.load_current ?? props.boat?.load_current;
    const voltage = live.value?.house_battery_voltage ?? props.boat?.house_battery_voltage;
    if (current != null && voltage != null) return Math.abs(current) * voltage;
    return null;
});
const isCharging = computed(() => {
    const s = solarWatts.value ?? 0;
    const l = loadWatts.value ?? 0;
    return s > l;
});

// Power bar width (max 500W = 100%)
function powerBarWidth(watts) {
    if (watts == null) return 0;
    return Math.min(100, (Math.abs(watts) / 500) * 100);
}

// Temperature chart range (shared for water + air)
const tempMin = computed(() => {
    const water = (props.waterTempHistory ?? []).map(d => d.value);
    const air = (props.airTempHistory ?? []).map(d => d.value);
    const all = [...water, ...air];
    return all.length ? Math.min(...all) - 2 : 0;
});
const tempMax = computed(() => {
    const water = (props.waterTempHistory ?? []).map(d => d.value);
    const air = (props.airTempHistory ?? []).map(d => d.value);
    const all = [...water, ...air];
    return all.length ? Math.max(...all) + 2 : 40;
});

// Pressure chart range
const pressureMin = computed(() => {
    const vals = (props.pressureHistory ?? []).map(d => d.value);
    return vals.length ? Math.min(...vals) - 2 : 980;
});
const pressureMax = computed(() => {
    const vals = (props.pressureHistory ?? []).map(d => d.value);
    return vals.length ? Math.max(...vals) + 2 : 1030;
});

// Battery sparkline (small, from 24h history)
const batterySparkline = computed(() => {
    const data = props.batteryHistory;
    if (!data || data.length < 2) return { line: '', area: '' };
    const line = toPolyline(data, 80, 24, 0, 100);
    const area = `0,24 ${line} 80,24`;
    return { line, area };
});

// Speed sparkline (placeholder — no history prop, just show flat or nothing)
const speedSparkline = computed(() => '');

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
