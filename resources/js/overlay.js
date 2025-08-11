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
    updateGps: () => {
        fetch('/api/v1/gps').then(response => {
            if (!response.ok) {
                throw new Error('Unable to fetch GPS from API', response);
            }
            return response.json();
        }).then(payload => {
            Overlay.updateGpsDOM(payload);
        }).catch(error => {
            console.log('Unable to fetch GPS from API', error);
        });
    },
    updateWeather: () => {
        fetch('/api/v1/weather').then(response => {
            if (!response.ok) {
                throw new Error('Unable to fetch weather from API', response);
            }
            return response.json();
        }).then(payload => {
            Overlay.updateWeatherDOM(payload);
        }).catch(error => {
            console.log('Unable to fetch weather from API', error);
        });
    },

    updateWeatherDOM: (payload) => {
        console.log(payload);
        Overlay.getElement('weather-summary').innerHTML = '<i class="wi wi-' + payload.summary + '"></i>';
        Overlay.getElement('air-temperature').innerHTML = payload.temp + '&deg;C';
        Overlay.getElement('wind-direction').innerHTML = '<i class="wi wi-wind from-' + payload.wind.direction + '-deg"></i>';
        Overlay.getElement('wind-speed').innerHTML = '' + payload.wind.speed + 'kt';
        Overlay.getElement('sea-temperature').innerHTML = payload.seaTemp + '&deg;C';
        Overlay.getElement('wave-height').innerHTML = payload.waves.height + 'm';
        Overlay.getElement('wave-period').innerHTML = payload.waves.period + 's';
    },

    updateGpsDOM: (payload) => {
        // Modify this to update any HTML as required
        Overlay.getElement('heading').innerHTML = payload.course.toFixed(1) + '&deg;';
        Overlay.getElement('compass-bg').style.transform = 'rotate(-' + payload.course + 'deg)';
        Overlay.getElement('speed').innerHTML = payload.speed.toFixed(1) + 'kt';
        Overlay.getElement('long').innerHTML = payload.longitude.toFixed(6);
        Overlay.getElement('lat').innerHTML = payload.latitude.toFixed(6);
    },
    updateClock: () => {
        Overlay.getElement('time').innerHTML = moment().format('HH:mm');
        Overlay.getElement('date').innerHTML = moment().format('D MMMM YYYY');
    },
    init: () => {
        Overlay.clockTimer = setInterval(() => {
            Overlay.updateClock();
        }, 1000);
        Overlay.dataTimer = setInterval(() => {
            Overlay.updateGps();
        }, 30 * 1000);
        Overlay.weatherTimer = setInterval(() => {
            Overlay.updateWeather();
        }, 5 * 60 * 1000)
        Overlay.updateClock();
        Overlay.updateGps();
        Overlay.updateWeather();
    },
}

document.addEventListener('DOMContentLoaded', () => {
    Overlay.init();
});
