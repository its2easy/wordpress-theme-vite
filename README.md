## todo

add readme

check modulepreload as script

## url() in css
```scss
background-image: url('/wp-content/themes/theme/assets/img/wp-logo.png');
background-image: url('#{$img-path}/wp-logo.png');
background-image: url('@img/wp-logo.png');
background-image: img-url('wp-logo.png');
```
## dev mode
To access your website via LAN (Wi-Fi) network enable `server.host: true` in `vite.config.js` and start the server.
Then replace `localhost` with your external ip (like 192.169.0.100) in `theme_enqueue_vite_assets().
This could be automated with [vite-plugin-dev-manifest](https://github.com/owlsdepartment/vite-plugin-dev-manifest) which
exposes an external host.
