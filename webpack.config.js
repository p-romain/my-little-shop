const Encore = require('@symfony/webpack-encore');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .addEntry('app', './assets/app.jsx')
    .enableReactPreset()
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(false)
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.38';
    })
    .enablePostCssLoader()
;

const config = Encore.getWebpackConfig();

config.resolve = config.resolve || {};
config.resolve.extensions = config.resolve.extensions || ['.js', '.json', '.wasm'];
config.resolve.extensions.push('.jsx');

config.watchOptions = {
    ignored: [
        '**/node_modules/**',
        '**/public/build/**',
        '**/var/**',
        '**/vendor/**',
    ],
};

module.exports = config;
