(function (document, console, timeago) {
    var timeagoInstance = timeago();
    var nodes = document.querySelectorAll('time');

    console.log("initializing timeago");

    // use render method to render nodes in real time
    timeagoInstance.render(nodes, 'en_US');

})(document, console, timeago);
