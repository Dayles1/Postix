import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import ApexCharts from 'apexcharts';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import { Calendar } from '@fullcalendar/core';
import { requestJson, requestRaw } from "./request";

window.requestJson = requestJson;
window.requestRaw = requestRaw;
Alpine.plugin(collapse);

const getCsrfToken = () => {
    const el = document.querySelector('meta[name="csrf-token"]');
    return el ? el.getAttribute('content') : '';
};

export default async function request({
    method = 'get',
    url,
    data = null,
    headers = {},
    asFormData = false,
    showToast = null,
    onSuccess = null,
    onError = null,
    returnRaw = false,
}) {
    try {
        const config = {
            method: method.toLowerCase(),
            url,
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json',
                ...headers,
            },
            withCredentials: true,
        };

        if (data !== null) {
            if (asFormData) {
                const fd = new FormData();
                Object.keys(data).forEach((k) => {
                    const v = data[k];
                    if (Array.isArray(v)) {
                        v.forEach(val => fd.append(`${k}[]`, val));
                    } else {
                        fd.append(k, v);
                    }
                });
                config.data = fd;
            } else {
                config.data = data;
            }
        }

        const res = await axios(config);

        if (res?.data?.message && typeof showToast === 'function') {
            showToast(res.data.message, 'success');
        }

        if (typeof onSuccess === 'function') onSuccess(res.data, res);

        return returnRaw ? res : res.data;
    } catch (error) {
        let payload = {
            ok: false,
            status: error?.response?.status ?? null,
            message: error?.response?.data?.message ?? error.message,
            errors: error?.response?.data?.errors ?? null,
            raw: error,
        };

        if (payload.status === 422 && payload.errors) {
        }

        if (typeof showToast === 'function') {
            showToast(payload.message || 'Xatolik yuz berdi', 'error');
        }
        if (typeof onError === 'function') onError(payload);

        throw payload;
    }
}




window.Alpine = Alpine;
window.ApexCharts = ApexCharts;
window.flatpickr = flatpickr;
window.FullCalendar = Calendar;

Alpine.start();

// Initialize components on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    // Map imports
    if (document.querySelector('#mapOne')) {
        import('./components/map').then(module => module.initMap());
    }

    // Chart imports
    if (document.querySelector('#chartOne')) {
        import('./components/chart/chart-1').then(module => module.initChartOne());
    }
    if (document.querySelector('#chartTwo')) {
        import('./components/chart/chart-2').then(module => module.initChartTwo());
    }
    if (document.querySelector('#chartThree')) {
        import('./components/chart/chart-3').then(module => module.initChartThree());
    }
    if (document.querySelector('#chartSix')) {
        import('./components/chart/chart-6').then(module => module.initChartSix());
    }
    if (document.querySelector('#chartEight')) {
        import('./components/chart/chart-8').then(module => module.initChartEight());
    }
    if (document.querySelector('#chartThirteen')) {
        import('./components/chart/chart-13').then(module => module.initChartThirteen());
    }

    // Calendar init
    if (document.querySelector('#calendar')) {
        import('./components/calendar-init').then(module => module.calendarInit());
    }
});
