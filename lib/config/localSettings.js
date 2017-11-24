'use strict';

var WikiaApiMap = require('./WikiaApiMap');
var WikiaReverseApiMap = require('./WikiaReverseApiMap');

module.exports = {
	/**
	 * @param {ParsoidConfig} parsoidConfig
	 */
	setup: function(parsoidConfig) {
		const isDevEnv = (process.env.ENV === 'dev');
		// epic hack
		parsoidConfig.mwApiMap = new WikiaApiMap();
		parsoidConfig.reverseMwApiMap = new WikiaReverseApiMap();

		parsoidConfig.devAPI = isDevEnv;

		if ( !isDevEnv ) {
			parsoidConfig.parsoidCacheURI = `http://${envName}.parsoid-cache/`;
			parsoidConfig.parsoidCacheProxy = `http://${envName}.icache.service.consul:80/`;
			parsoidConfig.defaultAPIProxyURI = `http://${envName}.icache.service.consul:80/`;
		}
	}
};
