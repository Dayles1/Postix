/*
|--------------------------------------------------------------------------
| Driver check entry
|--------------------------------------------------------------------------
|
| The driver-check layout loads this instead of resources/js/app.js: Alpine,
| the panel's own components and nothing from postix (no charts, maps,
| calendars).
|
*/

import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { requestJson, requestRaw } from './request';
import { registerDriverCheck } from './driver-check';
import { registerShell } from './driver-check-shell';

window.requestJson = requestJson;
window.requestRaw = requestRaw;
window.Alpine = Alpine;

Alpine.plugin(collapse);

/* Must run before start(): Alpine only resolves x-data names registered by then. */
registerDriverCheck(Alpine);
registerShell(Alpine);

Alpine.start();
