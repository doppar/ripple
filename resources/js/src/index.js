import { send } from './http.js';
import { bindListeners } from './listeners.js';
import { applyPatches } from './patch.js';
import { showError, hideError } from './error.js';

bindListeners();

window.Ripple = { send, applyPatches, showError, hideError };
