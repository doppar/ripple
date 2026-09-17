import { send } from './http.js';
import { bindListeners } from './listeners.js';
import { applyPatches } from './patch.js';
import { softNavigate, hardNavigate } from './navigate.js';

bindListeners();

window.Ripple = { send, applyPatches, softNavigate, hardNavigate };
