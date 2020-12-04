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
	console.log('parsoid key', key);

	let isHydraFeatures = false;

	if (key.includes('/hydraFeatures')) {
		isHydraFeatures = true;
		key = key.replace('/hydraFeatures', '');
	}

	console.log('parsoid isHydraFeatures', isHydraFeatures);
	console.log('parsoid updated key', key);

	// if (this.apiMap.has(key)) {
	// 	return this.apiMap.get(key);
	// }

	const allNativeExtensions = [];

	ParsoidConfig._collectExtensions(
		allNativeExtensions,
		path.resolve(__dirname, '../ext'),
		true /* don't require a 'parsoid' subdirectory */
	);

	console.log('parsoid allNativeExtensions', JSON.stringify(allNativeExtensions));

	let extensions = [];

	// Disable native gallery handling for wikis other then on gamepedia
	if (isHydraFeatures) {
		console.log('parsoid assign allNativeExtensions')
		extensions = allNativeExtensions;
	} else {
		console.log('parsoid assign allNativeExtensions without Gallery');
		extensions = allNativeExtensions.filter(function(extension) {
			return !(extension.name === 'Gallery');
		});
	}

	console.log('parsoid extensions', JSON.stringify(extensions));

	const envMatch = /^.*\.(verify|preview|sandbox[^\.]*)\.(fandom\.com|gamepedia\.com|wikia\.(com|org))/.exec(key);
	if (envMatch) {
		const env = envMatch[1];
		console.log('parsoid env', env);
		key = key.replace(`.${env}`, '');
		console.log('parsoid updated key with env', key);
	}

	return {
		uri: 'http://' + key + '/api.php',
		domain: encodeURIComponent(key),
		extensions,
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
