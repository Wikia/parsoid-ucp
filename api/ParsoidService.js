/**
 * Simple Parsoid web service.
 */
"use strict";

require('../lib/core-upgrade.js');

// global includes
var express = require('express'),
	hbs = require('handlebars'),
	cluster = require('cluster'),
	path = require('path');

// relative includes
var mp = '../lib/',
	ParsoidConfig = require( mp + 'mediawiki.ParsoidConfig' ).ParsoidConfig;


function ParsoidService(options) {

	var instanceName = cluster.isWorker
		? 'worker(' + process.pid + ')'
		: 'master';

	console.log( " - %s loading ...", instanceName );

	// The global parsoid configuration object.
	var parsoidConfig = new ParsoidConfig( options, null );

	// Load routes
	var routes = require('./routes')( parsoidConfig );

	var app = express.createServer();

	// view engine
	app.set('views', path.join(__dirname, '/views'));
	app.set('view engine', 'html');
	app.register('html', hbs);

	// block helper to reference js files in page head.
	hbs.registerHelper('jsFiles', function(options){
		this.javascripts = options.fn(this);
	});

	// serve static files
	app.use("/static", express.static(path.join(__dirname, "/static")));

	// favicon
	app.use(express.favicon(path.join(__dirname, "favicon.ico")));

	// Increase the form field size limit from the 2M default.
	app.use(express.bodyParser({ maxFieldsSize: 15 * 1024 * 1024 }));

	// Support gzip / deflate transfer-encoding
	app.use(express.compress());

	// limit upload file size
	app.use(express.limit('15mb'));

	// Catch errors
	app.on('error', function( err ) {
		if ( err.errno === "EADDRINUSE" ) {
			console.error( "Port %d is already in use. Exiting.", port );
			cluster.worker.disconnect();
		} else {
			console.error( err.message );
		}
	});


	// Routes

	var i = routes.interParams,
		p = routes.parserEnvMw,
		v = routes.v2Middle;

	function re(str) { return new RegExp(str); }

	// Regexp that matches to all interwikis accepted by the API.
	var iwRe = parsoidConfig.interwikiRegexp;

	app.get( '/', routes.home );
	app.get( "/_version", routes.version );
	app.get( /^\/robots.txt$/, routes.robots );

	app.get(  re( '^/((?:_rt|_rtve)/)?(' + iwRe + '):(.*)$' ), routes.redirectOldStyle );
	app.get(  re('/_html/(?:(' + iwRe + ')/(.*))?'), i, p, routes.html2wtForm );
	app.get(  re('/_wikitext/(?:(' + iwRe + ')/(.*))?'), i, p, routes.wt2htmlForm );
	app.get(  re('/_rt/(?:(' + iwRe + ')/(.*))?'), i, p, routes.roundtripTesting );
	app.get(  re('/_rtve/(' + iwRe + ')/(.*)'), i, p, routes.roundtripTestingNL );
	app.get(  re('/_rtselser/(' + iwRe + ')/(.*)'), i, p, routes.roundtripSelser );
	app.get(  re('/_rtform/(?:(' + iwRe + ')/(.*))?'), i, p, routes.get_rtForm );
	app.post( re('/_rtform/(?:(' + iwRe + ')/(.*))?'), i, p, routes.post_rtForm );
	app.get(  re('/(' + iwRe + ')/(.*)'), i, p, routes.wt2html );
	app.post( re('/(' + iwRe + ')/(.*)'), i, p, routes.html2wt );

	// Attempt to define a new version of the API
	// /v2/{domain}/{title}/{format}/{revision}

	app.get('/v2/:domain/:title/:format?/:revision?', v, p, routes.v2_wt2html );


	// Get host and port from the environment, if available
	// VCAP_APP_PORT is for appfog.com support
	var port = parsoidConfig.serverPort ||
		process.env.VCAP_APP_PORT || process.env.PORT || 8000;
	// default bind all
	var host = parsoidConfig.serverInterface || process.env.INTERFACE;

	app.listen( port, host );

	console.log( " - %s ready on %s:%s", instanceName, host || "", port );
}

module.exports = {
	ParsoidService: ParsoidService
};
