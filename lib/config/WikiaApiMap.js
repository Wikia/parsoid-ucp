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
	const stack = new Error().stack;

	let isHydraFeatures = false;

	const parsoidLog = {
		stack,
		key: key,
		isHydraFeatures,
		keyAfterHydraCheck: '',
		allNativeExtensions: [],
		extensions: [],
		isExtensionsWithoutGallery: true,
		env: '',
		keyAfterEnv: '',
	}

	if (key.includes('/hydraFeatures')) {
		isHydraFeatures = true;
		key = key.replace('/hydraFeatures', '');
	}

	parsoidLog.isHydraFeatures = isHydraFeatures;
	parsoidLog.keyAfterHydraCheck = key;

	// if (this.apiMap.has(key)) {
	// 	return this.apiMap.get(key);
	// }

	const allNativeExtensions = [];

	ParsoidConfig._collectExtensions(
		allNativeExtensions,
		path.resolve(__dirname, '../ext'),
		true /* don't require a 'parsoid' subdirectory */
	);

	parsoidLog.allNativeExtensions = allNativeExtensions;

	let extensions = [];

	// Disable native gallery handling for wikis other then on gamepedia
	if (isHydraFeatures) {
		extensions = allNativeExtensions;
		parsoidLog.isExtensionsWithoutGallery = false;
	} else {
		extensions = allNativeExtensions.filter(function(extension) {
			return !(extension.name === 'Gallery');
		});
	}

	parsoidLog.extensions = extensions;

	const envMatch = /^.*\.(verify|preview|sandbox[^\.]*)\.(fandom\.com|gamepedia\.com|wikia\.(com|org))/.exec(key);
	if (envMatch) {
		const env = envMatch[1];
		parsoidLog.env = env;
		key = key.replace(`.${env}`, '');
		parsoidLog.keyAfterEnv = key;
	}

	console.log('parsoid log' + JSON.stringify(parsoidLog));

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
