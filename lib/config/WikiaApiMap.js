'use strict';

var URL = require('url').URL;
var ParsoidConfig = require('./ParsoidConfig').ParsoidConfig;

function WikiaApiMap() {
	this.apiMap = new Map();
}

WikiaApiMap.prototype.has = function(key) {
	if (this.apiMap.has(key)) {
		return true;
	}

	return key.startsWith('http://') || key.startsWith('https://');
};

WikiaApiMap.prototype.get = function(key) {
	if (this.apiMap.has(key)) {
		return this.apiMap.get(key);
	}

	var apiUrl = new URL(key);

	var config = {
		uri: key,
		domain: apiUrl.hostname,
		extensions: []
	};

	ParsoidConfig._collectExtensions(config.extensions);

	return config;
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
