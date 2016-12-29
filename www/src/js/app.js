import 'jquery';
const hljs = require('highlight.js');
// import 'bootstrap/js/src/button.js'

hljs.registerLanguage('neon', require('highlight.js/lib/languages/yaml.js'));
hljs.registerLanguage('shell', require('highlight.js/lib/languages/bash.js'));

$(document).ready(function () {
	$('pre code').each(function (i, block) {
		hljs.highlightBlock(block);
	});
});
