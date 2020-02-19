'use strict';

var WikiaApiMap = require('./WikiaApiMap');
var WikiaReverseApiMap = require('./WikiaReverseApiMap');

module.exports = {
	/**
	 * @param {ParsoidConfig} parsoidConfig
	 */
	setup: function(parsoidConfig) {
		if (!process.env.ENV || process.env.CI) {
			return; // no environment given - parser tests?
		}

		const isDevEnv = (process.env.ENV === 'dev');

		parsoidConfig.mwApiMap = new WikiaApiMap();
		parsoidConfig.reverseMwApiMap = new WikiaReverseApiMap();

		parsoidConfig.devAPI = isDevEnv;
		parsoidConfig.defaultAPIProxyURI = 'http://border.service.consul:80/';
	}
};
