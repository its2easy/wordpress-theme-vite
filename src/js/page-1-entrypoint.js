import '../scss/page-1-entrypoint.scss';
import { initExampleComponent1 } from './components/example-component-1.js'; // component with styles

document.addEventListener('DOMContentLoaded', () => {
    console.log('page 1 entrypoint');
    initExampleComponent1(); // component 1 loads component 2
});
