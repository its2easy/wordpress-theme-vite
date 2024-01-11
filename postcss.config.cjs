// no cssnano because vite has its own minifier, which works almost the same as cssnano. It doesn't need any setup
module.exports = ({env}) => {
    // const isProd = env === "production";
    return {
        plugins: [
            // intentionally (and optionally) enabled for prod ('npx autoprefixer --info' to check what is prefixing)
            require('autoprefixer'),
        ],
    }
}

