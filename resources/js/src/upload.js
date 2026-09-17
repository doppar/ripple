import { sendForm } from './http.js';
import { uploadUri } from './dom.js';

/** Upload files to /ripple/upload; returns server JSON. */
export async function uploadFile(componentId, property, file) {
  const fd = new FormData();
  fd.append('i', componentId);
  fd.append('p', property);
  fd.append('file', file);
  return sendForm(uploadUri(), fd);
}
