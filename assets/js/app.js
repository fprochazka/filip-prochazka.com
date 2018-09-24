// import 'jquery';
// const hljs = require('highlight.js');
// // import 'bootstrap/js/src/button.js'
//
// hljs.registerLanguage('neon', require('highlight.js/lib/languages/yaml.js'));
// hljs.registerLanguage('shell', require('highlight.js/lib/languages/bash.js'));
//
// $(document).ready(function () {
// 	$('pre code').each(function (i, block) {
// 		hljs.highlightBlock(block);
// 	});
// });

(function (document, console, timeago) {
    // the local dict example is below.
    var csDict = function(number, index, total_sec) {
        // number: the timeago / timein number;
        // index: the index of array below;
        // total_sec: total seconds between date to be formatted and today's date;
        return [
            ['před okamžikem', 'right now'],
            ['před minutou', 'in %s seconds'],
            ['před minutou', 'in 1 minute'],
            ['před %s minutami', 'in %s minutes'],
            ['před hodinou', 'in 1 hour'],
            ['před %s hodinami', 'in %s hours'],
            ['včera', 'in 1 day'],
            ['před %s dny', 'in %s days'],
            ['před týdnem', 'in 1 week'],
            ['před měsíce', 'in %s weeks'],
            ['před měsíce', 'in 1 month'],
            ['před %s měsíci', 'in %s months'],
            ['před rokem', 'in 1 year'],
            ['před %s lety', 'in %s years']
        ][index];
    };

    timeago.register('cs_CZ', csDict);

    var timeagoInstance = timeago();
    var nodes = document.querySelectorAll('time');

    console.log("initializing timeago");

    // use render method to render nodes in real time
    timeagoInstance.render(nodes, 'cs_CZ');

})(document, console, timeago);
