const mix = require('laravel-mix')
const path = require('path')

const COMTRA_ROOT = (function() {
    let r = path.resolve(__dirname, '../');
    return (process.platform === 'win32' ? r.replace(/\\/g, '/') : r).replace(/\/$/, '');
})();

mix.webpackConfig({
    externals: {
        jquery: 'jQuery',
        bootstrap: true,
    },
});

mix.options({
    processCssUrls: false,
});

mix.setPublicPath('../');

mix.copy(
    `node_modules/markdown-it/dist/${mix.inProduction() ? 'markdown-it.min.js' : 'markdown-it.js'}`,
    `${COMTRA_ROOT}/js/markdown-it.js`
);
mix.copy(
    `node_modules/bootstrap/dist/js/${mix.inProduction() ? 'bootstrap.bundle.min.js' : 'bootstrap.bundle.js'}`,
    `${COMTRA_ROOT}/js/bootstrap.js`
);
mix.sass('scss/progress-bar.scss', 'css/')
mix.sass('scss/table-sortable.scss', 'css/')
mix.js('js/table-sortable.js', 'js/')
mix.sass('scss/online-translation.scss', 'css/')
