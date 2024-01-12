import { initExampleComponent2 } from './components/example-component-2.js'; // component with styles
import { testFunctionImportedInManyEntrypointsV2 } from "./pages/other2.js";
import { testFunctionImportedInManyEntrypoints } from "./pages/other.js";

document.addEventListener('DOMContentLoaded', () => {
    console.log('page 2 entrypoint');
    initExampleComponent2();
    testFunctionImportedInManyEntrypoints();
    testFunctionImportedInManyEntrypointsV2();
});
