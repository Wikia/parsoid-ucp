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
		parsoidConfig.defaultAPIProxyURI = 'http://border.service.consul:80/';
	}
};
