'use strict';

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

	return {
		uri: 'http://' + key + '/api.php',
		domain: encodeURIComponent(key),
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
