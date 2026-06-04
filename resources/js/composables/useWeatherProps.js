import { computed } from 'vue';

export function useWeatherProps(weather, { wxIcon, wxTemp, wxCondition, wxSeaTemp, wxWindSpeed, wxWindDir, wxWaveHeight, wxWavePeriod }) {
    return computed(() => ({
        wxIcon: wxIcon.value,
        wxTemp: wxTemp.value,
        wxCondition: wxCondition.value,
        wxSeaTemp: wxSeaTemp.value,
        wxWindSpeed: wxWindSpeed.value,
        wxWindDir: wxWindDir.value,
        wxWaveHeight: wxWaveHeight.value,
        wxWavePeriod: wxWavePeriod.value,
        rawTemp: weather.value?.temp != null ? Number(weather.value.temp) : null,
        rawSeaTemp: weather.value?.seaTemp != null ? Number(weather.value.seaTemp) : null,
        rawWindSpeed: weather.value?.wind?.speed != null ? Number(weather.value.wind.speed) : null,
        rawWaveHeight: weather.value?.waves?.height != null ? Number(weather.value.waves.height) : null,
        rawWavePeriod: weather.value?.waves?.period != null ? Number(weather.value.waves.period) : null,
    }));
}
