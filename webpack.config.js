const Encore = require('@symfony/webpack-encore');

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .addEntry('app', './assets/js/app.js')
    .addEntry('dashboard-settings', './assets/js/pages/dashboard/settings.js')
    .addEntry('dashboard-external', './assets/js/pages/dashboard/external.js')
    .addEntry('dashboard-display', './assets/js/pages/dashboard/display.js')
    .autoProvidejQuery()

    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()

    // will require an extra script tag for runtime.js
    // but, you probably want this, unless you're building a single-page app
    .enableSingleRuntimeChunk()

    /*
     * FEATURE CONFIG
     *
     * Enable & configure other features below. For a full
     * list of features, see:
     * https://symfony.com/doc/current/frontend.html#adding-more-features
     */
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())

    // enables @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = 3;
    })
    .configureBabel((config)=>{
        config.plugins.push('@babel/plugin-proposal-class-properties');
    })
    .addLoader({
        test: /\.js$/,
        loader: 'babel-loader',
        options: {
            presets: ['@babel/preset-env']
        }
    })

    // enables Sass/SCSS support
    .enableSassLoader();

module.exports = Encore.getWebpackConfig();
