'use strict';

var extapi = module.parent.require('./extapi.js').versionCheck('^0.8.0');
var Util = extapi.Util;
var DU = extapi.DOMUtils;

function tokenHandler(manager, pipelineOpts, extToken, cb) {
	console.log(JSON.stringify(Util.getArgInfo(extToken)));
	cb({tokens:[]});
}

// Translate constructor
module.exports = function() {
	this.config = {
		tags: [
			{
				name: 'infobox',
				tokenHandler: tokenHandler
			}
		],
	};
};