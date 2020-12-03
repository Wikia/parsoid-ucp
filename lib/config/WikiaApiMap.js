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
	let isHydraFeatures = false;

	if (key.includes('/hydraFeatures')) {
		isHydraFeatures = true;
		key = key.replace('/hydraFeatures', '');
	}

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
	if (!isHydraFeatures) {
		allNativeExtensions = allNativeExtensions.filter(function(extension) {
			return !(extension.name === 'Gallery');
		});
	}

	return {
		uri: 'http://' + key + isHydraFeatures + '/api.php',
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
