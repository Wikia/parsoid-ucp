'use strict';

var WikiaApiMap = require('./WikiaApiMap');
var util = require('util');

function WikiaReverseApiMap() {
	WikiaApiMap.call(this);
}

util.inherits(WikiaReverseApiMap, WikiaApiMap);

WikiaReverseApiMap.prototype.get = function(key) {
	if (this.apiMap.has(key)) {
		return this.apiMap.get(key);
	}

	return key;
};

module.exports = WikiaReverseApiMap;
