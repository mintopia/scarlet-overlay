<?php

declare(strict_types=1);

namespace App\Support;

class CanonicalBaseline
{
    /**
     * The code-defined canonical catalog baseline (structured descriptors).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        $serial = (string) config('scarlet.canonical.ecoflow_serial', 'P231ZE1APJ3P0446');
        $ecoTopic = fn (string $field): string => "ecoflow/{$serial}_BMSHeartBeatReport/{$field}";

        return [
            [
                'key' => 'fuel_level', 'label' => 'Diesel', 'group' => 'tank',
                'storage_unit' => 'pct', 'display_unit' => '%', 'volatile' => true,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Diesel tank level (SignalK preferred, MQTT tanklevel fallback).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_tanks_fuel_0_currentLevel', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'ratio', 'select_fn' => 'last', 'unit_transform' => [['op' => 'multiply', 'value' => 100]], 'staleness_threshold_s' => 1800],
                    ['priority' => 2, 'source_metric_name' => 'scarlet_mqtt_percent', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => 'tanklevel']], 'source_class' => 'both', 'source_kind' => 'percent', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'water_fresh_level', 'label' => 'Fresh Water', 'group' => 'tank',
                'storage_unit' => 'pct', 'display_unit' => '%', 'volatile' => true,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.5,
                'enabled' => true, 'description' => 'Fresh water tank level (SignalK preferred, MQTT watertank fallback).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_signalk_tanks_freshWater_0_currentLevel', 'label_matchers' => [], 'source_class' => 'both', 'source_kind' => 'ratio', 'select_fn' => 'last', 'unit_transform' => [['op' => 'multiply', 'value' => 100]], 'staleness_threshold_s' => 1800],
                    ['priority' => 2, 'source_metric_name' => 'scarlet_mqtt_percent', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => 'watertank']], 'source_class' => 'both', 'source_kind' => 'percent', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'ecoflow_soc', 'label' => 'EcoFlow SOC', 'group' => 'power',
                'storage_unit' => 'pct', 'display_unit' => '%', 'volatile' => false,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'EcoFlow Delta state of charge (BMS actSoc).',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_mqtt_value', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => $ecoTopic('actSoc')]], 'source_class' => 'both', 'source_kind' => 'percent', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'ecoflow_input_watts', 'label' => 'EcoFlow Input', 'group' => 'power',
                'storage_unit' => 'w', 'display_unit' => 'W', 'volatile' => false,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'EcoFlow Delta total input power.',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_mqtt_value', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => $ecoTopic('inputWatts')]], 'source_class' => 'both', 'source_kind' => 'watts', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
            [
                'key' => 'ecoflow_output_watts', 'label' => 'EcoFlow Output', 'group' => 'power',
                'storage_unit' => 'w', 'display_unit' => 'W', 'volatile' => false,
                'trend_fn' => 'median', 'trend_window' => '10m',
                'staleness_threshold_s' => 3600, 'coverage_window_s' => 3600, 'coverage_min' => 0.3,
                'enabled' => true, 'description' => 'EcoFlow Delta total output power.',
                'sources' => [
                    ['priority' => 1, 'source_metric_name' => 'scarlet_mqtt_value', 'label_matchers' => [['label' => 'topic', 'op' => 'equals', 'value' => $ecoTopic('outputWatts')]], 'source_class' => 'both', 'source_kind' => 'watts', 'select_fn' => 'last', 'unit_transform' => [], 'staleness_threshold_s' => null],
                ],
            ],
        ];
    }
}
