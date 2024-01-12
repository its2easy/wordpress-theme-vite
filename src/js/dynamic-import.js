// chunk shouldn't be loaded at page load and shouldn't be in modulepreload tags
export function testDynamicImport(){
    console.log('function from dynamically imported module');
}
