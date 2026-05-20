const Overlay = {
    clockTimer: null,
    dataTimer: null,
    weatherTimer: null,
    elements: {},
    getElement: (id) => {
        if (Overlay.elements[id] === undefined) {
            Overlay.elements[id] = document.getElementById(id);
        }
        return Overlay.elements[id];
    },
    updateWeather: () => {
        fetch('/api/v1/weather/home').then(response => {
            if (!response.ok) {
                throw new Error('Unable to fetch weather from API', response);
            }
            return response.json();
        }).then(payload => {
            Overlay.updateWeatherDOM(payload);
        }).catch(error => {
            // Silently ignore — weather will retry on next interval
        });
    },

    updateWeatherDOM: (payload) => {
        Overlay.getElement('weather-summary').innerHTML = '<i class="wi wi-' + payload.summary + '"></i>';
        Overlay.getElement('air-temperature').innerHTML = payload.temp + '&deg;C';
    },
    updateClock: () => {
        Overlay.getElement('time').innerHTML = moment().utcOffset(window.scarletConfig.utcOffset).format('HH:mm');
        Overlay.getElement('date').innerHTML = moment().utcOffset(window.scarletConfig.utcOffset).format('D MMMM YYYY');
    },
    init: () => {
        Overlay.clockTimer = setInterval(() => {
            Overlay.updateClock();
        }, 1000);
        Overlay.weatherTimer = setInterval(() => {
            Overlay.updateWeather();
        }, 5 * 60 * 1000)
        Overlay.updateClock();
        Overlay.updateWeather();
    },
}

document.addEventListener('DOMContentLoaded', () => {
    Overlay.init();
});
