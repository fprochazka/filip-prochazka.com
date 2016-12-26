/**
 * Webpack Plugins
 */
const DedupePlugin = require('webpack/lib/optimize/DedupePlugin');
const LoaderOptionsPlugin = require('webpack/lib/LoaderOptionsPlugin');
const ProvidePlugin = require('webpack/lib/ProvidePlugin');
const UglifyJsPlugin = require('webpack/lib/optimize/UglifyJsPlugin');
const FaviconsWebpackPlugin = require('favicons-webpack-plugin');
const ExtractTextPlugin = require("extract-text-webpack-plugin");

const path = require('path');
const autoprefixer = require('autoprefixer');

const publicPath = '/dist/';

module.exports = {
	entry: [
		'tether',
		'./www/src/css/style.scss',
		'./www/src/js/app.js'
	],
	output: {
		path: path.resolve(__dirname, 'www/dist'),
		publicPath: publicPath,
		filename: 'app.js'
	},
	resolve: {
		extensions: ['.js', '.jsx', '.css', '.scss'],
		modules: [
			path.resolve(__dirname, 'www/src'),
			path.resolve(__dirname, 'node_modules'),
		],
	},
	performance: {
		hints: false,
	},
	plugins: [
		new UglifyJsPlugin({
			beautify: false, //prod
			output: {
				comments: false
			}, //prod
			mangle: {
				screw_ie8: true
			}, //prod
			compress: {
				screw_ie8: true,
				warnings: false,
				conditionals: true,
				unused: true,
				comparisons: true,
				sequences: true,
				dead_code: true,
				evaluate: true,
				if_return: true,
				join_vars: true,
				negate_iife: false // we need this for lazy v8
			},
		}),
		new LoaderOptionsPlugin({
			minimize: true,
			debug: false,
		}),
		new ExtractTextPlugin({
			filename: 'styles.css',
			publicPath: '/dist/',
			allChunks: true,
		}),
		new ProvidePlugin({
			jQuery: 'jquery',
			$: 'jquery',
			jquery: 'jquery',
			'Tether': 'tether',
			'window.Tether': 'tether',
		}),
		new FaviconsWebpackPlugin({
			logo: path.resolve(__dirname, 'www/favicon.png'),
			prefix: 'icons/',
			emitStats: true,
			statsFilename: 'icons-stats.json',
			title: 'Filip Procházka',
		}),
	],
	module: {
		rules: [
			{
				test: /\.(js|jsx)$/,
				exclude: /(node_modules|bower_components)/,
				loader: 'babel-loader',
				query: {
					presets: ['es2015'],
					plugins: ['transform-runtime'],
				}
			},
			{test: /\.json$/, loader: 'json-loader'},
			{
				test: /\.css$/,
				loader: ExtractTextPlugin.extract({
					fallbackLoader: 'style-loader',
					loader: [
						{loader: 'css-loader', options: {modules: true}},
						{loader: 'postcss-loader'},
					],
				}),
			},
			{
				test: /\.scss$/,
				loader: ExtractTextPlugin.extract({
					fallbackLoader: 'style-loader',
					loader: [
						{loader: 'css-loader', options: {modules: true}},
						{loader: 'postcss-loader'},
						{loader: 'sass-loader'},
					],
				}),
			},
			// Limiting the size of the woff fonts breaks font-awesome ONLY for the extract text plugin
			{test: /\.woff(\?v=\d+\.\d+\.\d+)?$/, loader: 'url-loader?limit=10000&mimetype=application/font-woff'},
			{test: /\.woff2(\?v=\d+\.\d+\.\d+)?$/, loader: 'url-loader?limit=10000&mimetype=application/font-woff'},
			{test: /\.ttf(\?v=\d+\.\d+\.\d+)?$/, loader: 'url-loader?limit=10000&mimetype=application/octet-stream'},
			{test: /\.eot(\?v=\d+\.\d+\.\d+)?$/, loader: 'file-loader'},
			{test: /\.svg(\?v=\d+\.\d+\.\d+)?$/, loader: 'url-loader?limit=10000&mimetype=image/svg+xml'},
			{test: /\.(jpe?g|png|gif)$/, use: 'file-loader'},
			// Bootstrap 4
			{test: /bootstrap\/dist\/js\/umd\//, loader: 'imports-loader?jQuery=jquery'},
		]
	}
};
