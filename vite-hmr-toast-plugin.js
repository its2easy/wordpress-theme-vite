/**
 * @typedef {Object} HmrToastPluginOptions
 * @property {string} [entryFile='main.js'] - The filename of your main JS entrypoint (e.g., 'main.js', 'app.js') to inject
 *                                          the plugin's script.
 * @property {string} [message='HMR Update'] - The text to display in the toast notification.
 * @property {number} [duration=2000] - How long (in milliseconds) the toast should remain visible.
 */

/**
 * Creates a Vite plugin that shows a toast notification on HMR updates. This plugin automatically injects the HMR
 * toast logic into your specified entry file during development. It uses a virtual module to keep the logic out
 * of your source code and ensures it is never included in production builds.
 * It exists because `vite-plugin-browser-sync` doesn't show its notifications after vite hmr updates.
 *
 * @example
 * // vite.config.js
 * import { defineConfig } from 'vite';
 * import { hmrToastPlugin } from './vite-hmr-toast-plugin';
 *
 * export default defineConfig({
 *   plugins: [
 *     hmrToastPlugin({
 *       entryFile: 'main.js',
 *       message: 'Styles Updated',
 *       duration: 2000,
 *     }),
 *   ]
 * });
 *
 * @param {HmrToastPluginOptions} [options] - Configuration options for the plugin
 * @returns {import('vite').Plugin} A Vite plugin object
 */
export function hmrToastPlugin(options = {}) {
    const { entryFile = 'main.js', message = 'HMR Update', duration = 2000 } = options;
    const virtualModuleId = 'virtual:hmr-toast';
    const resolvedVirtualModuleId = `\0${virtualModuleId}`; // \0 prevents resolution from disk

    return {
        name: 'vite-plugin-hmr-toast',
        apply: 'serve', // Only run this plugin during development (vite serve)

        // 1. Tell Vite how to resolve the virtual module ID
        resolveId(id) {
            if (id === virtualModuleId) {
                return resolvedVirtualModuleId;
            }
        },

        // 2. Provide the code for the virtual module
        load(id) {
            if (id === resolvedVirtualModuleId) {
                return `
          if (import.meta.hot) {
            import.meta.hot.on('vite:afterUpdate', () => {
              const existing = document.getElementById('vite-hmr-toast');
              if (existing) existing.remove();

              const toast = document.createElement('div');
              toast.id = 'vite-hmr-toast';
              toast.innerText = '${message}';
              Object.assign(toast.style, {
                position: 'fixed',
                top: '20px',
                right: '20px',
                backgroundColor: '#333',
                color: '#fff',
                padding: '5px 10px',
                borderRadius: '4px',
                zIndex: '9999',
                fontSize: '14px',
                boxShadow: 'rgb(197 197 197 / 50%) 0px 0px 5px',
              });

              document.body.appendChild(toast);
              setTimeout(() => {
                toast.remove();
              }, ${duration});
            });
          }
        `;
            }
        },
        // 3. Inject the import into your entry file automatically
        transform(code, id) {
            // Check if this file matches your entry point (e.g., ends with 'main.js'),
            // `id` is full system path
            if (id.endsWith(entryFile)) {
                // Prepend the import to the existing code
                // We add a newline to ensure it doesn't merge with the first line of your code
                return `import '${virtualModuleId}';\n${code}`;
            }
        },
    }; // return object
}
