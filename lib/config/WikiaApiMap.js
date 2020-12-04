'use strict';

var ParsoidConfig = require('./ParsoidConfig').ParsoidConfig;
var path = require('path');
var request = require('request');

function WikiaApiMap() {
	this.apiMap = new Map();
}

WikiaApiMap.prototype.has = function(key) {
	return true;
};

WikiaApiMap.prototype.get = function(key, ...rest) {
	request.get({
		url: `https://${key}/wikia.php`,
		qs: {
			controller: 'WikiVariables',
			method: 'getIsGamepedia',
			format: 'json',
			uselang: 'en',
		}
	}, (error, response, body) => {
		const isGamepedia = JSON.parse(body).isGamepedia;

		const parsoidLog = {
			key: key,
			error,
			isGamepedia,
			isExtensionsWithoutGallery: true,
			env: '',
			keyAfterEnv: '',
			...rest
		}

		if (this.apiMap.has(key)) {
			return this.apiMap.get(key);
		}

		const allNativeExtensions = [];

		ParsoidConfig._collectExtensions(
			allNativeExtensions,
			path.resolve(__dirname, '../ext'),
			true /* don't require a 'parsoid' subdirectory */
		);

		let extensions = [];

		// Disable native gallery handling for wikis other then on gamepedia
		if (isGamepedia) {
			extensions = allNativeExtensions;
			parsoidLog.isExtensionsWithoutGallery = false;
		} else {
			extensions = allNativeExtensions.filter(function(extension) {
				return !(extension.name === 'Gallery');
			});
		}

		// const envMatch = /^.*\.(verify|preview|sandbox[^\.]*)\.(fandom\.com|gamepedia\.com|wikia\.(com|org))/.exec(key);
		// if (envMatch) {
		// 	const env = envMatch[1];
		// 	parsoidLog.env = env;
		// 	key = key.replace(`.${env}`, '');
		// 	parsoidLog.keyAfterEnv = key;
		// }

		console.log('parsoid log' + JSON.stringify(parsoidLog));

		return {
			uri: 'http://' + key + '/api.php',
			domain: encodeURIComponent(key),
			extensions,
		};
	})
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
