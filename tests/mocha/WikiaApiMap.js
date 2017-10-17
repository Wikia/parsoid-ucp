/* global describe, it, beforeEach */

'use strict';

require('chai').should();

var WikiaApiMap = require('../../lib/config/WikiaApiMap');

describe('WikiaApiMap', function() {
	var wikiaApiMap;

	beforeEach(function() {
		wikiaApiMap = new WikiaApiMap();
	});

	it('returns stored value if key exists in underlying map', function() {
		wikiaApiMap.apiMap.set('foo', 'bar');

		wikiaApiMap.get('foo').should.equal('bar');
	});

	it('returns configuration if key does not exist but is valid URL', function() {
		var testCases = [];

		testCases.forEach(function(url) {

		});
	});
});
