// styles could be imported from node_modules for example, or the component itself could be in node_modules
import '../../scss/app/components/example-component-1.scss';
import { initExampleComponent2 } from './example-component-2.js'; // component with styles

export function initExampleComponent1(){
    console.log('example-component-1');
    initExampleComponent2();
}
