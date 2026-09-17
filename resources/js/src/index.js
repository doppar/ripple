import { send } from './http.js';
import { bindListeners } from './listeners.js';
import { applyPatches } from './patch.js';

bindListeners();

window.Ripple = { send, applyPatches };
