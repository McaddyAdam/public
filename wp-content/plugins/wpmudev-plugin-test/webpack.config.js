const path = require('path')
let TerserPlugin = null;
try {
	TerserPlugin = require('terser-webpack-plugin');
} catch (e) {
	console.warn('terser-webpack-plugin not installed; skipping JS minimizer');
}

let CleanWebpackPlugin = null;
try {
	// prefer top-level install; fall back gracefully if not present
	CleanWebpackPlugin = require('clean-webpack-plugin').CleanWebpackPlugin;
} catch (e) {
	console.warn('clean-webpack-plugin not installed at top level; skipping clean step');
}

let MiniCssExtractPlugin = null;
try {
	MiniCssExtractPlugin = require('mini-css-extract-plugin');
} catch (e) {
	console.warn('mini-css-extract-plugin not installed; CSS extraction will fall back to style-loader');
}

let defaultConfig = {};
try {
	defaultConfig = require("@wordpress/scripts/config/webpack.config");
} catch (e) {
	console.warn('@wordpress/scripts webpack config not found; falling back to minimal config');
	defaultConfig = { plugins: [], module: { rules: [] }, resolve: { extensions: ['.js'] } };
}

// Resolve babel-loader path if possible (handles nested installs)
let BABEL_LOADER = 'babel-loader';
const babelCandidates = [
	'babel-loader',
	'@wordpress/scripts/node_modules/babel-loader',
];
for (const candidate of babelCandidates) {
	try {
		BABEL_LOADER = require.resolve(candidate);
		console.log('Using babel-loader:', BABEL_LOADER);
		break;
	} catch (e) {
		// continue
	}
}
if (!BABEL_LOADER) {
	console.warn('babel-loader not resolvable; webpack will try to load it by name');
}

module.exports = {
    ...defaultConfig,

	// Treat common libraries as externals so they are not bundled into plugin JS
	externals: {
		react: 'React',
		'react-dom': 'ReactDOM',
		'@wpmudev/shared-ui': 'WPMUDEV_SHARED_UI',
		// WP provided libs
		'@wordpress/element': ['wp', 'element'],
		'@wordpress/api-fetch': ['wp', 'apiFetch'],
	},
	entry: {
		'drivetestpage': './src/googledrive-page/main.jsx',
	},

	output: {
		path: path.resolve(__dirname, 'assets/js'),
		filename: '[name].min.js',
		publicPath: '../../',
		assetModuleFilename: 'images/[name][ext][query]',
	},

	resolve: {
		extensions: ['.js', '.jsx'],
	},

	module: {
		...(defaultConfig.module || {}),
		rules: [
            //...defaultConfig.module.rules,
			{
				test: /\.(js|jsx)$/,
				exclude: /node_modules/,
				use: {
					loader: BABEL_LOADER,
					options: {
						presets: (() => {
							const presets = [];
							const presetCandidates = [
								'@babel/preset-env',
								'@wordpress/scripts/node_modules/@babel/preset-env',
							];
							let resolved = false;
							for (const c of presetCandidates) {
								try { presets.push(require.resolve(c)); resolved = true; break; } catch (e) {}
							}
							if (!resolved) presets.push('@babel/preset-env');
							// react preset
							const reactCandidates = [
								'@babel/preset-react',
								'@wordpress/scripts/node_modules/@babel/preset-react',
							];
							resolved = false;
							for (const c of reactCandidates) {
								try { presets.push(require.resolve(c)); resolved = true; break; } catch (e) {}
							}
							if (!resolved) presets.push('@babel/preset-react');
							return presets;
						})(),
					}
				},
			},
			{
				test: /\.(css|scss)$/,
				exclude: /node_modules/,
				use: [
					'style-loader',
					...(MiniCssExtractPlugin ? [{
						loader: MiniCssExtractPlugin.loader,
						options: { esModule: false },
					}] : []),
					{ loader: 'css-loader' },
					'sass-loader',
				],
			},
			{
				test: /\.svg/,
				type: 'asset/inline',
			},
			{
				test: /\.(png|jpg|gif)$/,
				type: 'asset/resource',
				generator: {
					filename: '../images/[name][ext][query]',
				},
			},
			{
				test: /\.(woff|woff2|eot|ttf|otf)$/,
				type: 'asset/resource',
				generator: {
					filename: '../fonts/[name][ext][query]',
				},
			},
		],
	},

	// start from WP scripts plugins and conditionally add extras if available
	plugins: (() => {
		const plugins = [ ...(defaultConfig.plugins || []) ];
		if (CleanWebpackPlugin) {
			plugins.push(new CleanWebpackPlugin());
		}
		if (MiniCssExtractPlugin) {
			plugins.push(new MiniCssExtractPlugin({ filename: '../css/[name].min.css' }));
		}
		return plugins;
	})(),

	optimization: {
		minimize: true,
		minimizer: [
			...(TerserPlugin ? [new TerserPlugin({
				terserOptions: {
					format: {
						comments: false,
					},
				},
				extractComments: false,
			})] : []),
		],
	},
}
