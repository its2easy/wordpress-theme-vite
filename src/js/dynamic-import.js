// if dynamically imported module has its own dependencies, vite handles this module differently. Before loading, this
// js file and its css deps are added as rel=modulepreload. This won't work if `base` in vite.config.js doesn't point to
// `build.outDir` folder
import '../scss/app/dynamic-import-dependency.scss';

// chunk shouldn't be loaded at page load and shouldn't be in modulepreload tags at page load
export function testDynamicImport(){
    console.log('function from dynamically imported module');

    setTimeout(() => {
        import("./components/dynamic-import-dependency.js").then((mod) => {
            mod.testDynamicImportDep();
        });
    }, 100);
}
