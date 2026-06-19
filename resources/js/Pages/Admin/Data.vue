<template>
    <AdminLayout>
        <Head title="Data" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-[22px] font-bold">Data</h1>
                <p class="text-[13px] text-text-secondary mt-0.5">Catalog v{{ version }} &mdash; {{ metrics.length }} metrics across {{ groupNames.length }} groups</p>
            </div>
            <button type="button" @click="openAdd" class="btn btn--primary">
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Metric
            </button>
        </div>

        <!-- Metrics table grouped by group -->
        <div v-for="group in groupNames" :key="group" class="panel mb-6">
            <div class="px-5 py-3.5 border-b border-border flex items-center gap-2">
                <span class="panel-title">{{ group }}</span>
                <span class="text-[11px] text-text-dim ml-auto">{{ metricsByGroup[group].length }} metric{{ metricsByGroup[group].length !== 1 ? 's' : '' }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[700px]">
                    <thead>
                        <tr class="border-b border-border">
                            <th class="px-5 py-2.5 text-left text-[11px] font-semibold text-text-secondary uppercase tracking-wide">Key</th>
                            <th class="px-5 py-2.5 text-left text-[11px] font-semibold text-text-secondary uppercase tracking-wide">Label</th>
                            <th class="px-5 py-2.5 text-left text-[11px] font-semibold text-text-secondary uppercase tracking-wide">Unit</th>
                            <th class="px-5 py-2.5 text-left text-[11px] font-semibold text-text-secondary uppercase tracking-wide">Sources</th>
                            <th class="px-5 py-2.5 text-left text-[11px] font-semibold text-text-secondary uppercase tracking-wide">Status</th>
                            <th class="px-5 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="metric in metricsByGroup[group]" :key="metric.id">
                            <!-- Metric row -->
                            <tr class="border-b border-border last:border-0 hover:bg-bg transition-colors duration-75" :class="{ 'bg-bg/60': expandedMetric === metric.id }">
                                <td class="px-5 py-3">
                                    <code class="text-[12px] font-mono bg-bg border border-border rounded px-1.5 py-0.5 text-text-primary">{{ metric.key }}</code>
                                </td>
                                <td class="px-5 py-3 text-[13px] font-medium">{{ metric.label }}</td>
                                <td class="px-5 py-3 text-[13px] text-text-secondary">
                                    <span v-if="metric.display_unit">{{ metric.display_unit }}</span>
                                    <span v-else class="text-text-dim italic">—</span>
                                    <span v-if="metric.storage_unit && metric.storage_unit !== metric.display_unit" class="text-[11px] text-text-dim ml-1">({{ metric.storage_unit }})</span>
                                </td>
                                <td class="px-5 py-3">
                                    <button
                                        type="button"
                                        @click="toggleExpand(metric.id)"
                                        class="inline-flex items-center gap-1.5 text-[12px] text-text-secondary hover:text-text-primary transition-colors"
                                    >
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold"
                                            :class="metric.sources.length > 0 ? 'bg-blue-bg text-blue' : 'bg-bg text-text-dim'">
                                            {{ metric.sources.length }}
                                        </span>
                                        source{{ metric.sources.length !== 1 ? 's' : '' }}
                                        <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                                            class="transition-transform duration-150" :class="expandedMetric === metric.id ? 'rotate-180' : ''">
                                            <polyline points="6 9 12 15 18 9"/>
                                        </svg>
                                    </button>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2 py-0.5 rounded-full"
                                            :class="metric.enabled ? 'bg-green-bg text-green' : 'bg-bg text-text-dim'">
                                            <span class="w-1.5 h-1.5 rounded-full inline-block"
                                                :class="metric.enabled ? 'bg-green' : 'bg-text-dim'"></span>
                                            {{ metric.enabled ? 'Enabled' : 'Disabled' }}
                                        </span>
                                        <span v-if="metricDrift(metric) === 'missing'"
                                            class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-error-bg text-error"
                                            title="Highest-priority source not found in live VictoriaMetrics">
                                            <svg viewBox="0 0 24 24" width="11" height="11" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                            drift
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button" @click="openEdit(metric)" class="btn btn--ghost" style="height:30px;padding:0 10px;font-size:12px;">Edit</button>
                                        <button type="button" @click="promptDelete(metric)" class="text-[12px] font-medium text-error hover:text-scarlet-hover transition-colors">Delete</button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Expanded sources sub-rows -->
                            <tr v-if="expandedMetric === metric.id" :key="'src-' + metric.id">
                                <td colspan="6" class="px-5 py-0 bg-bg border-b border-border">
                                    <div class="py-3 space-y-2">
                                        <div v-if="metric.sources.length === 0" class="text-[13px] text-text-dim italic py-2">No sources configured.</div>
                                        <div
                                            v-for="src in metric.sources"
                                            :key="src.id"
                                            class="flex items-start gap-3 bg-surface border border-border rounded-[8px] p-3"
                                        >
                                            <div class="flex-shrink-0 w-6 h-6 rounded-full bg-scarlet-light text-scarlet flex items-center justify-center text-[10px] font-bold mt-0.5">
                                                {{ src.priority }}
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <code class="text-[11px] font-mono text-text-primary bg-bg border border-border rounded px-1 py-0.5">{{ src.source_metric_name }}</code>
                                                    <span class="text-[11px] text-text-dim">{{ src.source_class }}</span>
                                                    <span v-if="fmtTransforms(src.unit_transform)" class="text-[11px] text-blue bg-blue-bg px-1.5 rounded">{{ fmtTransforms(src.unit_transform) }}</span>
                                                    <span v-if="src.staleness_threshold_s" class="text-[11px] text-text-dim">stale&gt;{{ src.staleness_threshold_s }}s</span>
                                                </div>
                                                <div v-if="fmtMatchers(src.label_matchers)" class="mt-1 text-[11px] text-text-dim font-mono">{{ fmtMatchers(src.label_matchers) }}</div>

                                                <!-- Test result -->
                                                <div v-if="testResults[src.id]" class="mt-2">
                                                    <span v-if="testResults[src.id].ok" class="inline-flex items-center gap-1.5 text-[12px] text-green bg-green-bg px-2 py-0.5 rounded">
                                                        <svg viewBox="0 0 24 24" width="11" height="11" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                                        {{ testResults[src.id].value }}
                                                        <span class="text-[10px] text-green/70">{{ testResults[src.id].age }}s ago</span>
                                                    </span>
                                                    <span v-else class="inline-flex items-center gap-1.5 text-[12px] text-error bg-error-bg px-2 py-0.5 rounded">
                                                        <svg viewBox="0 0 24 24" width="11" height="11" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                        No data
                                                        <span v-if="testResults[src.id].selector" class="text-[10px] opacity-70">{{ testResults[src.id].selector }}</span>
                                                    </span>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                @click="testSource(src)"
                                                :disabled="testingSourceId === src.id"
                                                class="btn btn--ghost flex-shrink-0"
                                                style="height:28px;padding:0 10px;font-size:11px;"
                                            >
                                                {{ testingSourceId === src.id ? 'Testing…' : 'Test' }}
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="metrics.length === 0" class="panel p-12 text-center">
            <svg class="mx-auto mb-4 text-text-dim" viewBox="0 0 24 24" width="40" height="40" stroke="currentColor" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7-7H4a2 2 0 0 0-2 2v4"/>
                <polyline points="14 2 14 8 20 8"/>
                <path d="M12 18v-6"/><path d="M8 18v-1"/><path d="M16 18v-3"/>
            </svg>
            <p class="text-[14px] font-medium text-text-secondary mb-1">No metrics in catalog</p>
            <p class="text-[13px] text-text-dim mb-4">Add your first metric to start mapping data sources.</p>
            <button type="button" @click="openAdd" class="btn btn--primary">Add Metric</button>
        </div>

        <!-- Version History -->
        <div v-if="versions.length > 0" class="panel mb-6">
            <div class="px-5 py-3.5 border-b border-border">
                <span class="panel-title">Version History</span>
            </div>
            <div class="divide-y divide-border">
                <div
                    v-for="v in versions"
                    :key="v.version"
                    class="px-5 py-3 flex items-center gap-4"
                >
                    <span class="text-[11px] font-bold text-text-dim w-10 flex-shrink-0">v{{ v.version }}</span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[12px] font-semibold text-text-primary">{{ v.action }}</span>
                            <span class="text-[11px] text-text-dim">by {{ v.actor }}</span>
                        </div>
                        <div v-if="v.note" class="text-[12px] text-text-secondary mt-0.5">{{ v.note }}</div>
                        <div class="text-[11px] text-text-dim mt-0.5">{{ formatDate(v.created_at) }}</div>
                    </div>
                    <button
                        v-if="v.version < version"
                        type="button"
                        @click="promptRollback(v)"
                        class="btn btn--ghost flex-shrink-0"
                        style="height:30px;padding:0 10px;font-size:12px;"
                    >
                        Rollback
                    </button>
                    <span v-else class="text-[11px] text-green font-semibold flex-shrink-0">Current</span>
                </div>
            </div>
        </div>
    </AdminLayout>

    <!-- ── Add / Edit Metric Modal ──────────────────────────────── -->
    <Teleport to="body">
        <div
            v-if="showEditor"
            class="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto py-10 px-4"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="editingMetric ? 'editor-title-edit' : 'editor-title-add'"
            @keydown.escape="showEditor = false"
        >
            <div class="absolute inset-0 bg-black/50" @click="showEditor = false"></div>
            <div
                ref="editorModalRef"
                tabindex="-1"
                class="relative bg-surface rounded-xl w-full max-w-[640px] shadow-[0_20px_60px_rgba(0,0,0,0.25)] text-text-primary"
                @keydown.tab="trapFocus($event, editorModalRef)"
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-border">
                    <h3 :id="editingMetric ? 'editor-title-edit' : 'editor-title-add'" class="text-[16px] font-bold">
                        {{ editingMetric ? 'Edit Metric' : 'Add Metric' }}
                    </h3>
                    <button type="button" @click="showEditor = false" class="w-8 h-8 flex items-center justify-center rounded-lg text-text-dim hover:text-text-primary hover:bg-bg transition-colors">
                        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="px-6 py-5 space-y-4">
                    <!-- Basic fields -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="field-label" for="ed-key">Key <span class="text-error">*</span></label>
                            <input id="ed-key" v-model="metricForm.key" type="text" class="field-input" placeholder="e.g. wind_speed" required />
                            <p v-if="metricForm.errors.key" class="field-error">{{ metricForm.errors.key }}</p>
                        </div>
                        <div>
                            <label class="field-label" for="ed-label">Label <span class="text-error">*</span></label>
                            <input id="ed-label" v-model="metricForm.label" type="text" class="field-input" placeholder="e.g. Wind Speed" required />
                            <p v-if="metricForm.errors.label" class="field-error">{{ metricForm.errors.label }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="field-label" for="ed-group">Group <span class="text-error">*</span></label>
                            <input id="ed-group" v-model="metricForm.group" type="text" class="field-input" placeholder="e.g. wind" required />
                            <p v-if="metricForm.errors.group" class="field-error">{{ metricForm.errors.group }}</p>
                        </div>
                        <div>
                            <label class="field-label" for="ed-storage-unit">Storage Unit</label>
                            <input id="ed-storage-unit" v-model="metricForm.storage_unit" type="text" class="field-input" placeholder="e.g. m/s" />
                        </div>
                        <div>
                            <label class="field-label" for="ed-display-unit">Display Unit</label>
                            <input id="ed-display-unit" v-model="metricForm.display_unit" type="text" class="field-input" placeholder="e.g. kn" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="field-label" for="ed-trend-fn">Trend Fn</label>
                            <input id="ed-trend-fn" v-model="metricForm.trend_fn" type="text" class="field-input" placeholder="e.g. avg" />
                        </div>
                        <div>
                            <label class="field-label" for="ed-trend-window">Trend Window (s)</label>
                            <input id="ed-trend-window" v-model="metricForm.trend_window" type="number" min="0" class="field-input" />
                        </div>
                        <div>
                            <label class="field-label" for="ed-staleness">Staleness (s)</label>
                            <input id="ed-staleness" v-model="metricForm.staleness_threshold_s" type="number" min="0" class="field-input" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="field-label" for="ed-coverage-window">Coverage Window (s)</label>
                            <input id="ed-coverage-window" v-model="metricForm.coverage_window_s" type="number" min="0" class="field-input" />
                        </div>
                        <div>
                            <label class="field-label" for="ed-coverage-min">Coverage Min (0–1)</label>
                            <input id="ed-coverage-min" v-model="metricForm.coverage_min" type="number" min="0" max="1" step="0.01" class="field-input" />
                        </div>
                    </div>

                    <!-- Validity / filtering -->
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="field-label" for="ed-valid-min">Valid Min</label>
                            <input id="ed-valid-min" v-model="metricForm.valid_min" type="number" step="any" class="field-input" placeholder="optional" />
                        </div>
                        <div>
                            <label class="field-label" for="ed-valid-max">Valid Max</label>
                            <input id="ed-valid-max" v-model="metricForm.valid_max" type="number" step="any" class="field-input" placeholder="optional" />
                        </div>
                        <div class="flex items-end pb-1.5">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" v-model="metricForm.reject_null_island" class="w-4 h-4 rounded accent-scarlet" />
                                <span class="text-[13px] font-medium">Reject Null Island</span>
                            </label>
                        </div>
                    </div>
                    <p class="text-[11px] text-text-dim -mt-2">Readings outside Valid Min/Max are dropped (e.g. inactive-route sentinels). Null Island drops lat/long&nbsp;≈&nbsp;0,0 (no GPS fix).</p>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <label class="field-label" for="ed-gate">Route Gate Series <span class="text-text-dim font-normal">— only resolve while this series is present &amp; ≤ max (optional)</span></label>
                            <input id="ed-gate" v-model="metricForm.gate_metric_name" type="text" class="field-input" list="vm-series-list" autocomplete="off" placeholder="e.g. scarlet_signalk_navigation_course_calcValues_timeToGo" />
                            <p v-if="metricForm.gate_metric_name && inventoryLoaded && !seriesInVm(metricForm.gate_metric_name)" class="text-[11px] text-error mt-1">Not found in live VictoriaMetrics</p>
                        </div>
                        <div>
                            <label class="field-label" for="ed-gate-max">Gate Max</label>
                            <input id="ed-gate-max" v-model="metricForm.gate_max_value" type="number" step="any" class="field-input" placeholder="optional" />
                        </div>
                    </div>

                    <div>
                        <label class="field-label" for="ed-description">Description</label>
                        <textarea id="ed-description" v-model="metricForm.description" class="field-input" rows="2" placeholder="Optional description"></textarea>
                    </div>

                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" v-model="metricForm.enabled" class="w-4 h-4 rounded accent-scarlet" />
                            <span class="text-[13px] font-medium">Enabled</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" v-model="metricForm.volatile" class="w-4 h-4 rounded accent-scarlet" />
                            <span class="text-[13px] font-medium">Volatile</span>
                        </label>
                    </div>

                    <!-- Sources section -->
                    <div class="border-t border-border pt-4">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-[13px] font-semibold">Sources</span>
                            <button type="button" @click="addSource" class="btn btn--ghost" style="height:28px;padding:0 10px;font-size:12px;">
                                + Add Source
                            </button>
                        </div>

                        <div v-if="metricForm.sources.length === 0" class="text-[13px] text-text-dim italic py-2">No sources yet.</div>

                        <div
                            v-for="(src, idx) in metricForm.sources"
                            :key="idx"
                            class="bg-bg border border-border rounded-[8px] p-3 mb-2"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[11px] font-bold text-text-dim uppercase tracking-wide">Source {{ idx + 1 }}</span>
                                <div class="flex items-center gap-2">
                                    <button v-if="idx > 0" type="button" @click="moveSource(idx, -1)" class="text-[11px] text-text-dim hover:text-text-primary">↑</button>
                                    <button v-if="idx < metricForm.sources.length - 1" type="button" @click="moveSource(idx, 1)" class="text-[11px] text-text-dim hover:text-text-primary">↓</button>
                                    <button type="button" @click="removeSource(idx)" class="text-[11px] text-error hover:text-scarlet-hover">Remove</button>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="field-label" :for="'src-name-' + idx">Source Metric Name <span class="text-error">*</span></label>
                                    <input :id="'src-name-' + idx" v-model="src.source_metric_name" type="text" class="field-input" list="vm-series-list" autocomplete="off" placeholder="e.g. scarlet_signalk_environment_wind_speedApparent" />
                                    <p v-if="src.source_metric_name && inventoryLoaded && !seriesInVm(src.source_metric_name)" class="text-[11px] text-error mt-1 flex items-center gap-1">
                                        <svg viewBox="0 0 24 24" width="11" height="11" stroke="currentColor" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                        Not found in live VictoriaMetrics
                                    </p>
                                </div>
                                <div>
                                    <label class="field-label" :for="'src-class-' + idx">Source Class</label>
                                    <input :id="'src-class-' + idx" v-model="src.source_class" type="text" class="field-input" placeholder="e.g. signalk" />
                                </div>
                                <div>
                                    <label class="field-label" :for="'src-kind-' + idx">Source Kind</label>
                                    <input :id="'src-kind-' + idx" v-model="src.source_kind" type="text" class="field-input" placeholder="e.g. gauge" />
                                </div>
                                <div>
                                    <label class="field-label" :for="'src-staleness-' + idx">Staleness (s)</label>
                                    <input :id="'src-staleness-' + idx" v-model="src.staleness_threshold_s" type="number" min="0" class="field-input" />
                                </div>
                                <div>
                                    <label class="field-label" :for="'src-select-' + idx">Select Fn</label>
                                    <input :id="'src-select-' + idx" v-model="src.select_fn" type="text" class="field-input" placeholder="e.g. last" />
                                </div>
                            </div>

                            <!-- Label matchers (structured) -->
                            <div class="mt-3">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-[11px] font-bold text-text-dim uppercase tracking-wide">Label Matchers</span>
                                    <button type="button" @click="addMatcher(src)" class="text-[11px] text-scarlet hover:underline">+ Add matcher</button>
                                </div>
                                <p v-if="!src.label_matchers || src.label_matchers.length === 0" class="text-[11px] text-text-dim italic">None — matches every series of this name.</p>
                                <div v-for="(m, mi) in src.label_matchers" :key="mi" class="flex items-center gap-2 mb-1.5">
                                    <input v-model="m.label" type="text" class="field-input flex-1" placeholder="label e.g. topic" />
                                    <select v-model="m.op" class="field-input w-28">
                                        <option value="equals">equals</option>
                                        <option value="absent">absent</option>
                                    </select>
                                    <input v-model="m.value" :disabled="m.op === 'absent'" type="text" class="field-input flex-1 disabled:opacity-40" placeholder="value" />
                                    <button type="button" @click="removeMatcher(src, mi)" class="text-[12px] text-error hover:text-scarlet-hover px-1.5">✕</button>
                                </div>
                            </div>

                            <!-- Unit transforms (structured, applied in order) -->
                            <div class="mt-3">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-[11px] font-bold text-text-dim uppercase tracking-wide">Unit Transforms</span>
                                    <button type="button" @click="addTransform(src)" class="text-[11px] text-scarlet hover:underline">+ Add transform</button>
                                </div>
                                <p v-if="!src.unit_transform || src.unit_transform.length === 0" class="text-[11px] text-text-dim italic">None — raw value.</p>
                                <div v-for="(t, ti) in src.unit_transform" :key="ti" class="flex items-center gap-2 mb-1.5">
                                    <select v-model="t.op" class="field-input w-36">
                                        <option value="multiply">× multiply</option>
                                        <option value="divide">÷ divide</option>
                                        <option value="add">+ add</option>
                                        <option value="subtract">− subtract</option>
                                    </select>
                                    <input v-model.number="t.value" type="number" step="any" class="field-input flex-1" placeholder="value e.g. 1.94384" />
                                    <button type="button" @click="removeTransform(src, ti)" class="text-[12px] text-error hover:text-scarlet-hover px-1.5">✕</button>
                                </div>
                            </div>
                        </div>

                        <!-- Series picker options sourced from live VM inventory -->
                        <datalist id="vm-series-list">
                            <option v-for="name in seriesOptions" :key="name" :value="name" />
                        </datalist>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-border flex items-center justify-end gap-2.5">
                    <p v-if="metricForm.errors.sources" class="text-[12px] text-error mr-auto">{{ metricForm.errors.sources }}</p>
                    <button type="button" @click="showEditor = false" class="btn btn--ghost">Cancel</button>
                    <button
                        type="button"
                        @click="saveMetric"
                        :disabled="metricForm.processing"
                        class="btn btn--primary"
                    >
                        {{ metricForm.processing ? 'Saving…' : (editingMetric ? 'Save Changes' : 'Add Metric') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- ── Delete Confirm Modal ──────────────────────────────────── -->
    <Teleport to="body">
        <div
            v-if="deletingMetric"
            class="fixed inset-0 z-[100] flex items-center justify-center px-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-title"
            @keydown.escape="deletingMetric = null"
        >
            <div class="absolute inset-0 bg-black/50" @click="deletingMetric = null"></div>
            <div
                ref="deleteModalRef"
                tabindex="-1"
                class="relative bg-surface rounded-xl p-7 max-w-[420px] w-full shadow-[0_20px_60px_rgba(0,0,0,0.25)] text-text-primary"
                @keydown.tab="trapFocus($event, deleteModalRef)"
            >
                <h3 id="delete-title" class="text-base font-bold mb-2">Delete &ldquo;{{ deletingMetric?.key }}&rdquo;?</h3>
                <p class="text-sm text-text-secondary leading-normal mb-6">
                    This will permanently remove the metric and all its configured sources from the catalog. A new catalog version will be created.
                </p>
                <div class="flex gap-2.5 justify-end">
                    <button type="button" @click="deletingMetric = null" class="btn btn--ghost">Cancel</button>
                    <button type="button" @click="confirmDelete" :disabled="deleting" class="btn btn--danger">
                        {{ deleting ? 'Deleting…' : 'Delete Metric' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- ── Rollback Confirm Modal ────────────────────────────────── -->
    <Teleport to="body">
        <div
            v-if="rollbackTarget"
            class="fixed inset-0 z-[100] flex items-center justify-center px-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="rollback-title"
            @keydown.escape="rollbackTarget = null"
        >
            <div class="absolute inset-0 bg-black/50" @click="rollbackTarget = null"></div>
            <div
                ref="rollbackModalRef"
                tabindex="-1"
                class="relative bg-surface rounded-xl p-7 max-w-[420px] w-full shadow-[0_20px_60px_rgba(0,0,0,0.25)] text-text-primary"
                @keydown.tab="trapFocus($event, rollbackModalRef)"
            >
                <h3 id="rollback-title" class="text-base font-bold mb-2">Roll back to v{{ rollbackTarget?.version }}?</h3>
                <p class="text-sm text-text-secondary leading-normal mb-2">
                    This will restore the catalog to the state at version {{ rollbackTarget?.version }}
                    <template v-if="rollbackTarget?.action">&nbsp;({{ rollbackTarget.action }})</template>.
                    The current state will be saved as a new version first.
                </p>
                <p class="text-[12px] text-text-dim mb-6">Recorded {{ rollbackTarget ? formatDate(rollbackTarget.created_at) : '' }}</p>
                <div class="flex gap-2.5 justify-end">
                    <button type="button" @click="rollbackTarget = null" class="btn btn--ghost">Cancel</button>
                    <button type="button" @click="confirmRollback" :disabled="rollingBack" class="btn btn--danger">
                        {{ rollingBack ? 'Rolling back…' : 'Roll Back' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    metrics: { type: Array, default: () => [] },
    versions: { type: Array, default: () => [] },
    version: { type: Number, default: 0 },
});

// ── Live VM inventory (series picker + drift detection) ─────

const liveSeries = ref(new Set());
const liveTopics = ref([]);
const inventoryLoaded = ref(false);

onMounted(async () => {
    try {
        const response = await fetch(route('admin.data.inventory'), {
            headers: { Accept: 'application/json' },
        });
        if (response.ok) {
            const data = await response.json();
            liveSeries.value = new Set(data.names ?? []);
            liveTopics.value = data.topics ?? [];
        }
    } catch {
        // Inventory is advisory — the editor still works without it.
    } finally {
        inventoryLoaded.value = true;
    }
});

const seriesOptions = computed(() => [...liveSeries.value]);

function seriesInVm(name) {
    return !!name && liveSeries.value.has(name);
}

// A metric drifts if its highest-priority source name is absent from live VM.
function metricDrift(metric) {
    if (!inventoryLoaded.value || !metric.sources?.length) {
        return null;
    }
    const primary = [...metric.sources].sort((a, b) => a.priority - b.priority)[0];
    return seriesInVm(primary.source_metric_name) ? 'ok' : 'missing';
}

// ── Grouping ───────────────────────────────────────────────

const groupNames = computed(() => {
    const seen = new Set();
    const order = [];
    for (const m of props.metrics) {
        if (!seen.has(m.group)) {
            seen.add(m.group);
            order.push(m.group);
        }
    }
    return order;
});

const metricsByGroup = computed(() => {
    const map = {};
    for (const m of props.metrics) {
        if (!map[m.group]) {
            map[m.group] = [];
        }
        map[m.group].push(m);
    }
    return map;
});

// ── Expand / collapse sources ──────────────────────────────

const expandedMetric = ref(null);

function toggleExpand(id) {
    expandedMetric.value = expandedMetric.value === id ? null : id;
}

// ── Test endpoint ──────────────────────────────────────────

const testResults = ref({});
const testingSourceId = ref(null);

async function testSource(src) {
    testingSourceId.value = src.id;
    try {
        const response = await fetch(route('admin.data.test'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                source_metric_name: src.source_metric_name,
                label_matchers: src.label_matchers ?? null,
            }),
        });
        const data = response.ok
            ? await response.json()
            : { ok: false, value: null, age: null, selector: `HTTP ${response.status}` };
        testResults.value = { ...testResults.value, [src.id]: data };
    } catch {
        testResults.value = { ...testResults.value, [src.id]: { ok: false, value: null, age: null, selector: 'Network error' } };
    } finally {
        testingSourceId.value = null;
    }
}

// ── Add / Edit metric modal ────────────────────────────────

const showEditor = ref(false);
const editingMetric = ref(null);
const editorModalRef = ref(null);

const blankSource = () => ({
    source_metric_name: '',
    source_class: '',
    source_kind: '',
    select_fn: '',
    unit_transform: [],
    label_matchers: [],
    staleness_threshold_s: '',
});

function addMatcher(src) {
    src.label_matchers = [...(src.label_matchers || []), { label: '', op: 'equals', value: '' }];
}
function removeMatcher(src, i) {
    src.label_matchers = src.label_matchers.filter((_, idx) => idx !== i);
}
function addTransform(src) {
    src.unit_transform = [...(src.unit_transform || []), { op: 'multiply', value: 1 }];
}
function removeTransform(src, i) {
    src.unit_transform = src.unit_transform.filter((_, idx) => idx !== i);
}

// Readable summaries for the collapsed source rows.
function fmtTransforms(arr) {
    if (!Array.isArray(arr) || arr.length === 0) {
        return '';
    }
    const sym = { multiply: '×', divide: '÷', add: '+', subtract: '−' };
    return arr.map((t) => `${sym[t.op] ?? t.op}${t.value}`).join(' ');
}
function fmtMatchers(arr) {
    if (!Array.isArray(arr) || arr.length === 0) {
        return '';
    }
    return arr.map((m) => (m.op === 'absent' ? `${m.label} absent` : `${m.label}=${m.value}`)).join(', ');
}

const metricForm = useForm({
    key: '',
    label: '',
    group: '',
    storage_unit: '',
    display_unit: '',
    volatile: false,
    enabled: true,
    trend_fn: '',
    trend_window: '',
    staleness_threshold_s: '',
    coverage_window_s: '',
    coverage_min: '',
    valid_min: '',
    valid_max: '',
    reject_null_island: false,
    gate_metric_name: '',
    gate_label_matchers: [],
    gate_max_value: '',
    description: '',
    sources: [],
});

function openAdd() {
    editingMetric.value = null;
    metricForm.reset();
    metricForm.enabled = true;
    metricForm.volatile = false;
    metricForm.sources = [];
    showEditor.value = true;
}

function openEdit(metric) {
    editingMetric.value = metric;
    metricForm.key = metric.key;
    metricForm.label = metric.label;
    metricForm.group = metric.group;
    metricForm.storage_unit = metric.storage_unit ?? '';
    metricForm.display_unit = metric.display_unit ?? '';
    metricForm.volatile = metric.volatile ?? false;
    metricForm.enabled = metric.enabled ?? true;
    metricForm.trend_fn = metric.trend_fn ?? '';
    metricForm.trend_window = metric.trend_window ?? '';
    metricForm.staleness_threshold_s = metric.staleness_threshold_s ?? '';
    metricForm.coverage_window_s = metric.coverage_window_s ?? '';
    metricForm.coverage_min = metric.coverage_min ?? '';
    metricForm.valid_min = metric.valid_min ?? '';
    metricForm.valid_max = metric.valid_max ?? '';
    metricForm.reject_null_island = metric.reject_null_island ?? false;
    metricForm.gate_metric_name = metric.gate_metric_name ?? '';
    metricForm.gate_label_matchers = Array.isArray(metric.gate_label_matchers) ? metric.gate_label_matchers.map((m) => ({ ...m })) : [];
    metricForm.gate_max_value = metric.gate_max_value ?? '';
    metricForm.description = metric.description ?? '';
    metricForm.sources = metric.sources.map((s) => ({
        source_metric_name: s.source_metric_name ?? '',
        source_class: s.source_class ?? '',
        source_kind: s.source_kind ?? '',
        select_fn: s.select_fn ?? '',
        unit_transform: Array.isArray(s.unit_transform) ? s.unit_transform.map((t) => ({ ...t })) : [],
        label_matchers: Array.isArray(s.label_matchers) ? s.label_matchers.map((m) => ({ ...m })) : [],
        staleness_threshold_s: s.staleness_threshold_s ?? '',
    }));
    showEditor.value = true;
}

watch(showEditor, (open) => {
    if (open) {
        nextTick(() => editorModalRef.value?.focus());
    }
});

function addSource() {
    metricForm.sources = [...metricForm.sources, blankSource()];
}

function removeSource(idx) {
    metricForm.sources = metricForm.sources.filter((_, i) => i !== idx);
}

function moveSource(idx, dir) {
    const list = [...metricForm.sources];
    const target = idx + dir;
    if (target < 0 || target >= list.length) {
        return;
    }
    [list[idx], list[target]] = [list[target], list[idx]];
    metricForm.sources = list;
}

function saveMetric() {
    if (editingMetric.value) {
        metricForm.put(route('admin.data.update', { metric: editingMetric.value.id }), {
            onSuccess: () => {
                showEditor.value = false;
            },
        });
    } else {
        metricForm.post(route('admin.data.store'), {
            onSuccess: () => {
                showEditor.value = false;
                metricForm.reset();
                metricForm.sources = [];
            },
        });
    }
}

// ── Delete ─────────────────────────────────────────────────

const deletingMetric = ref(null);
const deleting = ref(false);
const deleteModalRef = ref(null);

function promptDelete(metric) {
    deletingMetric.value = metric;
}

watch(deletingMetric, (val) => {
    if (val) {
        nextTick(() => deleteModalRef.value?.focus());
    }
});

function confirmDelete() {
    deleting.value = true;
    router.delete(route('admin.data.destroy', { metric: deletingMetric.value.id }), {
        onSuccess: () => {
            deletingMetric.value = null;
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
}

// ── Rollback ───────────────────────────────────────────────

const rollbackTarget = ref(null);
const rollingBack = ref(false);
const rollbackModalRef = ref(null);

function promptRollback(v) {
    rollbackTarget.value = v;
}

watch(rollbackTarget, (val) => {
    if (val) {
        nextTick(() => rollbackModalRef.value?.focus());
    }
});

function confirmRollback() {
    rollingBack.value = true;
    router.post(route('admin.data.rollback'), { version: rollbackTarget.value.version }, {
        onSuccess: () => {
            rollbackTarget.value = null;
        },
        onFinish: () => {
            rollingBack.value = false;
        },
    });
}

// ── Utilities ──────────────────────────────────────────────

function formatDate(dateStr) {
    if (!dateStr) {
        return '';
    }
    return new Date(dateStr).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function trapFocus(event, containerRef) {
    const modal = containerRef;
    if (!modal) {
        return;
    }
    const focusable = modal.querySelectorAll('input, button, textarea, select, [tabindex]:not([tabindex="-1"])');
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
}
</script>
