// Should be imported manually at the beginning of the main app entry https://vitejs.dev/guide/backend-integration.html
// Broad native support starts from sept 2023
// eslint-disable-next-line import/no-unresolved -- somehow this is exported depending on the option build.modulePreload
import 'vite/modulepreload-polyfill';

import '../scss/main-entrypoint.scss'; // basic styles for the website

/**
 * Data from wordpress side
 * @typedef {Object} phpDataObject
 * @property {string} ajax_url - Path to admin-ajax.php
 */

document.addEventListener('DOMContentLoaded', () => {
    console.log('main entrypoint');

    /**  @type {phpDataObject} */
    const phpData = window.phpData; // to test ide autocompletion
    console.log(`wp ajax url: ${phpData.ajax_url}`);
});