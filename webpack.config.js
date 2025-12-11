const defaultConfig                                = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		unzer_global: path.resolve( process.cwd(), 'assets/src', 'unzer_global.jsx' ),
		unzer_simple: path.resolve( process.cwd(), 'assets/src', 'unzer_simple.jsx' ),
		unzer_open_banking: path.resolve( process.cwd(), 'assets/src/payment_methods', 'unzer_open_banking.jsx' ),
		unzer_apple_pay_v2: path.resolve( process.cwd(), 'assets/src/payment_methods', 'unzer_apple_pay_v2.jsx' ),
		unzer_card: path.resolve( process.cwd(), 'assets/src/payment_methods', 'unzer_card.jsx' ),
		unzer_direct_debit: path.resolve( process.cwd(), 'assets/src/payment_methods', 'unzer_direct_debit.jsx' ),
		unzer_direct_debit_secured: path.resolve( process.cwd(), 'assets/src/payment_methods', 'unzer_direct_debit_secured.jsx' ),
		unzer_google_pay: path.resolve( process.cwd(), 'assets/src/payment_methods', 'unzer_google_pay.jsx' ),
		unzer_installment: path.resolve( process.cwd(), 'assets/src/payment_methods', 'unzer_installment.jsx' ),
		unzer_invoice: path.resolve( process.cwd(), 'assets/src/payment_methods', 'unzer_invoice.jsx' ),
	},
	output: {
		path: path.resolve( process.cwd(), 'assets/build' ),
		filename: '[name].js',
	},
	plugins: [
		...defaultConfig.plugins.filter(
			(plugin) =>
			plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new WooCommerceDependencyExtractionWebpackPlugin(),
	],
};
