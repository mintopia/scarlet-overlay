<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CanonicalCatalogVersion;
use App\Models\CanonicalMetric;
use App\Services\CanonicalCatalog;
use App\Services\PrometheusService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CanonicalCatalogController extends Controller
{
    public function index(CanonicalCatalog $catalog)
    {
        return Inertia::render('Admin/Catalog', [
            'metrics' => CanonicalMetric::with(['sources' => fn ($q) => $q->orderBy('priority')])
                ->orderBy('group')->orderBy('key')->get(),
            'versions' => CanonicalCatalogVersion::orderByDesc('version')->limit(20)->get(['version', 'action', 'actor', 'note', 'created_at']),
            'version' => $catalog->version(),
        ]);
    }

    public function inventory(PrometheusService $prometheus)
    {
        $names = array_values(array_filter(
            $prometheus->labelValues('__name__'),
            fn (string $n): bool => str_starts_with($n, 'scarlet_'),
        ));
        sort($names);

        return response()->json([
            'names' => $names,
            'topics' => $prometheus->labelValues('topic'),
        ]);
    }

    public function store(Request $request, CanonicalCatalog $catalog)
    {
        $data = $this->validateMetric($request);
        $metric = CanonicalMetric::create(collect($data)->except('sources')->all());
        foreach ($data['sources'] as $s) {
            $metric->sources()->create($s);
        }
        $catalog->recordVersion('edit', $request->user()->email);

        return back()->with('success', 'Metric created.');
    }

    public function update(Request $request, CanonicalMetric $metric, CanonicalCatalog $catalog)
    {
        $data = $this->validateMetric($request);
        $metric->update(collect($data)->except('sources')->all());
        $metric->sources()->delete();
        foreach ($data['sources'] as $s) {
            $metric->sources()->create($s);
        }
        $catalog->recordVersion('edit', $request->user()->email);

        return back()->with('success', 'Metric updated.');
    }

    public function destroy(Request $request, CanonicalMetric $metric, CanonicalCatalog $catalog)
    {
        $metric->delete();
        $catalog->recordVersion('delete', $request->user()->email);

        return back()->with('success', 'Metric deleted.');
    }

    public function test(Request $request, CanonicalCatalog $catalog)
    {
        $validated = $request->validate([
            'source_metric_name' => ['required', 'string'],
            'label_matchers' => ['nullable', 'array'],
        ]);

        return response()->json($catalog->testSource($validated));
    }

    public function rollback(Request $request, CanonicalCatalog $catalog)
    {
        $validated = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $catalog->rollback($validated['version'], $request->user()->email);

        return back()->with('success', "Rolled back to v{$validated['version']}.");
    }

    /** @return array<string,mixed> */
    private function validateMetric(Request $request): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'regex:/^[a-z0-9_]+$/'],
            'label' => ['required', 'string'],
            'group' => ['nullable', 'string'],
            'storage_unit' => ['required', 'string'],
            'display_unit' => ['required', 'string'],
            'volatile' => ['boolean'],
            'trend_fn' => ['nullable', 'in:median,avg,min,max'],
            'trend_window' => ['nullable', 'string'],
            'staleness_threshold_s' => ['required', 'integer', 'min:1'],
            'coverage_window_s' => ['integer', 'min:1'],
            'coverage_min' => ['numeric', 'between:0,1'],
            'valid_min' => ['nullable', 'numeric'],
            'valid_max' => ['nullable', 'numeric'],
            'reject_null_island' => ['boolean'],
            'enabled' => ['boolean'],
            'description' => ['nullable', 'string'],
            'sources' => ['required', 'array', 'min:1'],
            'sources.*.priority' => ['required', 'integer', 'min:1'],
            'sources.*.source_metric_name' => ['required', 'string'],
            'sources.*.label_matchers' => ['nullable', 'array'],
            'sources.*.source_class' => ['nullable', 'string'],
            'sources.*.source_kind' => ['nullable', 'string'],
            'sources.*.select_fn' => ['nullable', 'string'],
            'sources.*.unit_transform' => ['nullable', 'array'],
            'sources.*.staleness_threshold_s' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
