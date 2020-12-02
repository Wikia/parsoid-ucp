'use strict';

var ParsoidConfig = require('./ParsoidConfig').ParsoidConfig;
var path = require('path');

function WikiaApiMap() {
	this.apiMap = new Map();
}

WikiaApiMap.prototype.has = function(key) {
	return true;
};

WikiaApiMap.prototype.get = function(key) {
	if (this.apiMap.has(key)) {
		return this.apiMap.get(key);
	}

	let allNativeExtensions = [];

	ParsoidConfig._collectExtensions(
		allNativeExtensions,
		path.resolve(__dirname, '../ext'),
		true /* don't require a 'parsoid' subdirectory */
	);

	// Disable native gallery handling for wikis other then on gamepedia
	if (!key.includes('.gamepedia.com')) {
		allNativeExtensions = allNativeExtensions.filter(function(extension) {
			return !(extension.name === 'Gallery');
		});
	}

	return {
		uri: 'http://' + key + '/api.php',
		domain: encodeURIComponent(key),
		extensions: allNativeExtensions,
	};
};

WikiaApiMap.prototype.delete = function(key) {
	if (this.apiMap.has(key)) {
		this.apiMap.delete(key);
	}
};

WikiaApiMap.prototype.set = function(key, value) {
	this.apiMap.set(key, value);
};

module.exports = WikiaApiMap;
