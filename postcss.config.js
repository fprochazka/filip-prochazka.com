module.exports = {
	plugins: [
		// require('postcss-smart-import')({/* ...options */}),
		require('precss')({/* ...options */}),
		require('autoprefixer')({
			cascade: false,
			browsers: [
				'last 3 version',
				'ie >= 10',
			]
		})
	]
};
