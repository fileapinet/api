var webshot = require('webshot');

var url = process.argv[2];
var saveTo = process.argv[3];
if (!url || !saveTo) {
    throw 'Must set URL and save-to location';
}

webshot(url, saveTo, {defaultWhiteBackground: true}, function(err) {
});
