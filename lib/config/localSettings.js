var WikiaApiMap = require('./WikiaApiMap');
var WikiaReverseApiMap = require('./WikiaReverseApiMap');

module.exports = {
	/**
	 * @param {ParsoidConfig} parsoidConfig
	 */
	setup: function (parsoidConfig) {
		// epic hack
		parsoidConfig.mwApiMap = new WikiaApiMap();
		parsoidConfig.reverseMwApiMap = new WikiaReverseApiMap();

		parsoidConfig.devAPI = (process.env.ENV === 'dev');
	}
};
