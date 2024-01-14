import Modal from 'bootstrap/js/dist/modal'; // BS5 doesn't have esm for plugins, so it's umd

import '@/scss/frontpage-entrypoint.scss'; // check @ alias
import '../scss/app/frontpage-partial.scss'; // test import from js instead of importing it from scss
import { initExampleComponent1 } from '@/js/components/example-component-1.js'; // component with styles
import { testFunctionImportedOnlyOnce } from "./pages/frontpage.js";
import { testFunctionImportedInManyEntrypoints } from "./pages/other.js";
import { testFunctionImportedInManyEntrypointsV2 } from "./pages/other2.js";

document.addEventListener('DOMContentLoaded', () => {
    console.log('frontpage entrypoint');
    testFunctionImportedOnlyOnce(); // imported only here, shouldn't create separate chunk
    initExampleComponent1(); // component 1 loads component 2
    testFunctionImportedInManyEntrypoints();
    testFunctionImportedInManyEntrypointsV2();

    // open modal via js, close via data attributes
    const openModalButtons = document.querySelectorAll('.js__open-modal');
    openModalButtons.forEach((openModalButton) => {
        openModalButton.addEventListener('click', function () {
            const modal = document.querySelector('.modal-example');
            Modal.getOrCreateInstance(modal).show(); // modal could be created from different buttons
        });
    });

});
