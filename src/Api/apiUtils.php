<?php
// phpcs:disable Generic.Files.LineLength.TooLong
/* REMOVE THIS COMMENT AFTER PORTING */
/** @module */

namespace Parsoid;

use Parsoid\semver as semver;
use Parsoid\qs as qs;
use Parsoid\cType as cType;

$ContentUtils = require '../utils/ContentUtils.js'::ContentUtils;
$DOMDataUtils = require '../utils/DOMDataUtils.js'::DOMDataUtils;
$DOMUtils = require '../utils/DOMUtils.js'::DOMUtils;
$Promise = require '../utils/promise.js';
$Util = require '../utils/Util.js'::Util;
$PegTokenizer = require '../wt2html/tokenizer.js'::PegTokenizer;
$PHPParseRequest = require '../mw/ApiRequest.js'::PHPParseRequest;

/**
 * @alias module:api/apiUtils
 */
$apiUtils = $module->exports = [];

/**
 * Send a redirect response with optional code and a relative URL.
 *
 * @param {Response} res The response object from our routing function.
 * @param {string} path
 * @param {number} [httpStatus]
 */
$apiUtils->relativeRedirect = function ( $res, $path, $httpStatus ) {
	if ( $res->headersSent ) { return;
 }
	$args = [ $path ];
	if ( gettype( $httpStatus ) === 'number' ) {
		array_unshift( $args, $httpStatus );
	}
	call_user_func_array( [ $res, 'redirect' ], $args );
};

/**
 * Set header, but only if response hasn't been sent.
 *
 * @param {Response} res The response object from our routing function.
 * @param {string} field
 * @param {string} value
 */
$apiUtils->setHeader = function ( $res, $field, $value ) {
	Assert::invariant( $value !== null );
	if ( $res->headersSent ) { return;
 }
	$res->set( $field, $value );
};

/**
 * Send an html response, but only if response hasn't been sent.
 *
 * @param {Response} res The response object from our routing function.
 * @param {string} body
 * @param {number} [status] HTTP status code.
 * @param {Object} [headers] HTTP headers to include.
 * @param {string} [headers.content-type] A more specific type to use.
 * @param {string} [headers.content-language] Content language of response.
 * @param {string} [headers.vary] Vary header contents.
 * @param {boolean} [omitEscape] Be explicit about omitting escaping.
 */
$apiUtils->htmlResponse = function ( $res, $body, $status, $headers, $omitEscape ) use ( &$apiUtils, &$Util ) {
	if ( $res->headersSent ) { return;
 }
	if ( gettype( $status ) === 'number' ) {
		$res->status( $status );
	}
	// The `headers` arg will only be null if we are on the error response path,
	// in which case precise content-language/vary values don't matter much.
	// They should not be `undefined`, though!
	// The given values match the defaults in
	// MWParserEnvironment#htmlContentLanguage() and
	// MWParserEnvironment#htmlVary()
	if ( !$headers ) { $headers = [ 'content-language' => 'en', 'vary' => 'Accept' ];
 }
	$contentType = $headers[ 'content-type' ] || 'text/html; charset=utf-8';
	Assert::invariant( preg_match( '/^text\/html;/', $contentType ) );
	$apiUtils->setHeader( $res, 'Content-Type', $contentType );
	$apiUtils->setHeader( $res, 'Content-Language', $headers[ 'content-language' ] );
	$apiUtils->setHeader( $res, 'Vary', $headers->vary );
	// Explicit cast, since express varies response encoding by argument type
	// though that's probably offset by setting the header above
	$body = String( $body );
	if ( !$omitEscape ) {
		$body = Util::escapeHtml( $body );
	}
	$res->send( $body ); // Default string encoding for send is text/html
};

/**
 * Send a plaintext response, but only if response hasn't been sent.
 *
 * @param {Response} res The response object from our routing function.
 * @param {string} text
 * @param {number} [status] HTTP status code.
 * @param {string} [contentType] A more specific type to use.
 */
$apiUtils->plainResponse = function ( $res, $text, $status, $contentType ) use ( &$apiUtils ) {
	if ( $res->headersSent ) { return;
 }
	if ( gettype( $status ) === 'number' ) {
		$res->status( $status );
	}
	$contentType = $contentType || 'text/plain; charset=utf-8';
	Assert::invariant( preg_match( '/^text\/plain;/', $contentType ) );
	$apiUtils->setHeader( $res, 'Content-Type', $contentType );
	// Explicit cast, since express varies response encoding by argument type
	// though that's probably offset by setting the header above
	$res->send( String( $text ) );
};

/**
 * Send a JSON response, but only if response hasn't been sent.
 *
 * @param {Response} res The response object from our routing function.
 * @param {Object} json
 * @param {number} [status] HTTP status code.
 * @param {string} [contentType] A more specific type to use.
 */
$apiUtils->jsonResponse = function ( $res, $json, $status, $contentType ) use ( &$apiUtils ) {
	if ( $res->headersSent ) { return;
 }
	if ( gettype( $status ) === 'number' ) {
		$res->status( $status );
	}
	$contentType = $contentType || 'application/json; charset=utf-8';
	Assert::invariant( preg_match( '/^application\/json;/', $contentType ) );
	$apiUtils->setHeader( $res, 'Content-Type', $contentType );
	$res->json( $json );
};

/**
 * Render response, but only if response hasn't been sent.
 *
 * @param {Response} res The response object from our routing function.
 * @param {string} view
 * @param {Object} locals
 */
$apiUtils->renderResponse = function ( $res, $view, $locals ) {
	if ( $res->headersSent ) { return;
 }
	$res->render( $view, $locals );
};

/**
 * Error response.
 *
 * @param {Response} res The response object from our routing function.
 * @param {string} text
 * @param {number} [status]
 */
$apiUtils->errorResponse = function ( $res, $text, $status ) use ( &$apiUtils ) {
	if ( gettype( $status ) !== 'number' ) {
		$status = 500;
	}
	switch ( $res->locals->errorEnc ) {
		case 'html':
		$apiUtils->htmlResponse( $res, $text, $status );
		break;
		case 'json':
		$text = [ 'error' => $text ];
		$apiUtils->jsonResponse( $res, $text, $status );
		break;
		case 'plain':
		$apiUtils->plainResponse( $res, $text, $status );
		break;
		default:
		throw new Error( 'Unknown response type: ' . $res->locals->errorEnc );
	}
};

/**
 * Generic error response handler.
 *
 * @param {MWParserEnvironment} env
 * @param {Error} err
 */
$apiUtils->errorHandler = function ( $env, $err ) {
	if ( $err->type === 'MaxConcurrentCallsError' ) {
		$err->suppressLoggingStack = true;
		$err->httpStatus = 503;
	} elseif ( $err->type === 'TimeoutError' ) {
		$err->suppressLoggingStack = true;
		$err->httpStatus = 504;
	}
	$env->log( 'fatal/request', $err );
};

/**
 * Wrap a promised value with a catch that invokes {@link #errorHandler}.
 *
 * @param {MWParserEnvironment} env
 * @param {Promise|any} promiseOrValue
 */
$apiUtils->errorWrapper = function ( $env, $promiseOrValue ) use ( &$Promise, &$apiUtils ) {
	return Promise::resolve( $promiseOrValue )->catch( function ( $err ) use ( &$apiUtils, &$env ) {
			$apiUtils->errorHandler( $env, $err );
	}
	);
};

/**
 * To support the 'subst' API parameter, we need to prefix each
 * top-level template with 'subst'. To make sure we do this for the
 * correct templates, tokenize the starting wikitext and use that to
 * detect top-level templates. Then, substitute each starting '{{' with
 * '{{subst' using the template token's tsr.
 *
 * @param {MWParserEnvironment} env
 * @param {string} target
 * @param {string} wt
 */
$apiUtils->substTopLevelTemplates = function ( $env, $target, $wt ) use ( &$PegTokenizer, &$PHPParseRequest ) {
	$tokenizer = new PegTokenizer( $env );
	$tokens = $tokenizer->tokenizeSync( $wt );
	$tsrIncr = 0;
	for ( $i = 0;  $i < count( $tokens );  $i++ ) {
		if ( $tokens[ $i ]->name === 'template' ) {
			$tsr = $tokens[ $i ]->dataAttribs->tsr;
			$wt = substr( $wt, 0, $tsr[ 0 ] + $tsrIncr/*CHECK THIS*/ )
. '{{subst:'
. substr( $wt, $tsr[ 0 ] + $tsrIncr + 2 );
			$tsrIncr += 6;
		}
	}
	// Now pass it to the MediaWiki API with onlypst set so that it
	// subst's the templates.
	return PHPParseRequest::promise( $env, $target, $wt, true );
};

/**
 * Return the appropriate content-type string for wikitext.
 *
 * @param {MWParserEnvironment} env
 */
$apiUtils->wikitextContentType = function ( $env ) {
	return 'text/plain; charset=utf-8; profile="https://www.mediawiki.org/wiki/Specs/wikitext/' . $env->wikitextVersion . '"';
};

/**
 * Return the appropriate content-type string for Parsoid HTML.
 *
 * @param {string} outputContentVersion
 */
$apiUtils->htmlContentType = function ( $outputContentVersion ) {
	return 'text/html; charset=utf-8; profile="https://www.mediawiki.org/wiki/Specs/HTML/' . $outputContentVersion . '"';
};

/**
 * Return the appropriate content-type string for a Parsoid page bundle.
 *
 * @param {string} outputContentVersion
 */
$apiUtils->pagebundleContentType = function ( $outputContentVersion ) {
	return 'application/json; charset=utf-8; profile="https://www.mediawiki.org/wiki/Specs/pagebundle/' . $outputContentVersion . '"';
};

/**
 * Return the appropriate content-type string for a data-parsoid JSON blob.
 *
 * @param {string} outputContentVersion
 */
$apiUtils->dataParsoidContentType = function ( $outputContentVersion ) {
	return 'application/json; charset=utf-8; profile="https://www.mediawiki.org/wiki/Specs/data-parsoid/' . $outputContentVersion . '"';
};

/**
 * Return the appropriate content-type string for a data-mw JSON blob.
 *
 * @param {string} outputContentVersion
 */
$apiUtils->dataMwContentType = function ( $outputContentVersion ) {
	return 'application/json; charset=utf-8; profile="https://www.mediawiki.org/wiki/Specs/data-mw/' . $outputContentVersion . '"';
};

/**
 * Extracts a pagebundle from a revision.
 *
 * @param {Object} revision
 * @return {Object}
 */
$apiUtils->extractPageBundle = function ( $revision ) {
	return [
		'parsoid' => $revision[ 'data-parsoid' ] && $revision[ 'data-parsoid' ]->body,
		'mw' => $revision[ 'data-mw' ] && $revision[ 'data-mw' ]->body
	];
};

/**
 * Validates the pagebundle was provided in the expected format.
 *
 * @param {Object} pb
 * @param {string} originalVersion
 */
$apiUtils->validatePageBundle = function ( $pb, $originalVersion ) use ( &$semver ) {
	$err = null;
	if ( !$pb->parsoid || $pb->parsoid->constructor !== $Object || !$pb->parsoid->ids ) {
		$err = new Error( 'Invalid data-parsoid was provided.' );
		$err->httpStatus = 400;
		$err->suppressLoggingStack = true;
		throw $err;
	}
	if ( semver::satisfies( $originalVersion, '^999.0.0' )
&& ( !$pb->mw || $pb->mw->constructor !== $Object || !$pb->mw->ids )
	) {
		$err = new Error( 'Invalid data-mw was provided.' );
		$err->httpStatus = 400;
		$err->suppressLoggingStack = true;
		throw $err;
	}
};

/**
 * Log a fatal/request.
 *
 * @param {MWParserEnvironment} env
 * @param {string} text
 * @param {number} [httpStatus]
 */
$apiUtils->fatalRequest = function ( $env, $text, $httpStatus ) {
	$err = new Error( $text );
	$err->httpStatus = $httpStatus || 404;
	$err->suppressLoggingStack = true;
	$env->log( 'fatal/request', $err );
};

/**
 * Determine the content version from the html's content type.
 *
 * @param {Object} html
 * @return {string|null}
 */
$apiUtils->versionFromType = function ( $html ) use ( &$cType, &$apiUtils ) {
	$ct = $html->headers && $html->headers[ 'content-type' ];
	if ( $ct ) {
		try {
			$t = cType::parse( $ct );
			$profile = $t->parameters && $t->parameters->profile;
			if ( $profile ) {
				$p = $apiUtils->parseProfile( $profile, 'html' );
				return $p && $p->version;
			} else {
				return null;
			}
		} catch ( Exception $e ) {
			return null;
		}
	} else {
		return null;
	}
};

$oldSpec = /* RegExp */ '/^mediawiki.org\/specs\/(html)\/(\d+\.\d+\.\d+)$/';
$newSpec = /* RegExp */ '/^https:\/\/www.mediawiki.org\/wiki\/Specs\/(HTML|pagebundle)\/(\d+\.\d+\.\d+)$/';

/**
 * Used to extract the format and content version from a profile.
 *
 * @param {string} profile
 * @param {string} format
 *   Just used for backwards compatibility w/ <= 1.2.0
 *   where the pagebundle didn't have a spec.
 * @return {Object|null}
 */
$apiUtils->parseProfile = function ( $profile, $format ) use ( &$newSpec, &$oldSpec ) {
	$match = $newSpec->exec( $profile );
	// TODO(arlolra): Remove when this version is no longer supported.
	if ( !$match ) {
		$match = $oldSpec->exec( $profile );
		if ( $match ) { $match[ 1 ] = $format;
  }
	}
	if ( $match ) {
		return [
			'format' => strtolower( $match[ 1 ] ),
			'version' => $match[ 2 ]
		];
	} else {
		return null;
	}
};

/**
 * Set the content version to an acceptable version.
 * Returns false if Parsoid is unable to supply one.
 *
 * @param {Response} res
 * @param {Array} acceptableTypes
 * @return {boolean}
 */
$apiUtils->validateAndSetOutputContentVersion = function ( $res, $acceptableTypes ) use ( &$oldSpec, &$apiUtils ) {
	$env = $res->locals->env;
	$opts = $res->locals->opts;

	// `acceptableTypes` is already sorted by quality.
	return !count( $acceptableTypes ) || $acceptableTypes->some( function ( $t ) use ( &$opts, &$oldSpec, &$apiUtils, &$env ) {
				$profile = $t->parameters && $t->parameters->profile;
				if ( ( $opts->format === 'html' && $t->type === 'text/html' )
|| ( $opts->format === 'pagebundle' && $t->type === 'application/json' )
|| // 'pagebundle' is sending 'text/html' in older versions
						$oldSpec->exec( $profile )
				) {
					if ( $profile ) {
						$p = $apiUtils->parseProfile( $profile, $opts->format );
						if ( $p && ( $opts->format === $p->format ) ) {
							$contentVersion = $env->resolveContentVersion( $p->version );
							if ( $contentVersion !== null ) {
								$env->setOutputContentVersion( $contentVersion );
								return true;
							} else {
								return false;
							}
						} else {
							return false;
						}
					} else {
						return true;
					}
				} elseif ( $t->type === '*/*'
|| ( $opts->format === 'html' && $t->type === 'text/*' )
				) {
					return true;
				} else {
					return false;
				}
	}
		);
};

/**
 * Generate an HTTP redirect.
 *
 * @private
 */
$apiUtils->_redirect = function ( $req, $res, $target, $httpStatus, $processRedirect ) use ( &$apiUtils ) {
	$locals = $res->locals;
	$path = $processRedirect( implode(

			'/', [
				'',
				$locals->env->conf->parsoid->mwApiMap->get( $locals->iwp )->domain,
				'v3',
				( ( $req->method === 'POST' ) ? 'transform/' . $locals->opts->from . '/to' : 'page' ),
				$locals->opts->format,
				encodeURIComponent( $target )
			]
		)
	);

	// Don't cache redirect requests
	$apiUtils->setHeader( $res, 'Cache-Control', 'private,no-cache,s-maxage=0' );
	$apiUtils->relativeRedirect( $res, $path, $httpStatus );
};

/**
 * Generate an HTTP redirect to a specific revision.
 *
 * @param {Request} req
 * @param {Response} res
 */
$apiUtils->redirectToOldid = function ( $req, $res ) use ( &$qs ) {
	$env = $res->locals->env;
	$target = $env->normalizeAndResolvePageTitle();
	// Preserve the request method since we currently don't allow GETing the
	// "lint" format.  See T169006
	$httpStatus = ( $req->method === 'GET' ) ? 302 : 307;
	return $this->_redirect( $req, $res, $target, $httpStatus, function ( $redirPath ) use ( &$env, &$qs, &$req, &$res ) {
			$revid = $env->page->meta->revision->revid;
			$redirPath += '/' . $revid;
			if ( count( Object::keys( $req->query ) ) > 0 ) {
				$redirPath += '?' . qs::stringify( $req->query );
			}
			$format = $res->locals->opts->format;
			$env->log( 'info', 'redirecting to revision', $revid, 'for', $format );
			$metrics = $env->conf->parsoid->metrics;
			if ( $metrics ) {
				$metrics->increment( 'redirectToOldid.' . strtolower( $format ) );
			}
			return $redirPath;
	}
	);
};

/**
 * @private
 * @param {string} title
 * @param {Request} req
 * @param {Response} res
 */
$apiUtils->_redirectToPage = function ( $title, $req, $res ) {
	return $this->_redirect( $req, $res, $title, null, function ( $path ) use ( &$res ) {
			$res->locals->env->log( 'info', 'redirecting to ', $path );
			return $path;
	}
	);
};

/**
 * Downgrade content from 999.x to 2.x.
 *
 * @param {Document} doc
 * @param {Object} pb
 */
$downgrade999to2 = function ( $doc, $pb ) use ( &$DOMDataUtils ) {
	// Effectively, skip applying data-parsoid.  Note that if we were to
	// support a pb2html downgrade, we'd need to apply the full thing,
	// but that would create complications where ids would be left behind.
	// See the comment in around `DOMDataUtils.applyPageBundle`
	DOMDataUtils::applyPageBundle( $doc, [ 'parsoid' => [ 'ids' => [] ], 'mw' => $pb->mw ] );
	// Now, modify the pagebundle to the expected form.  This is important
	// since, at least in the serialization path, the original pb will be
	// applied to the modified content and its presence could cause lost
	// deletions.
	$pb->mw = [ 'ids' => [] ];
};

/**
 * Is this a transition we know how to handle?
 *
 * @param {string} from
 * @param {string} to
 * @return {Object|undefined}
 */
$apiUtils->findDowngrade = function ( $from, $to ) use ( &$downgrade999to2, &$semver ) {
	return [
		[ 'from' => '999.0.0', 'to' => '2.0.0', 'func' => $downgrade999to2 ]
	]->find( function ( $a ) use ( &$semver, &$from, &$to ) {return semver::satisfies( $from, '^' . $a->from )
&& semver::satisfies( $to, '^' . $a->to );
	}
	);
};

/**
 * Downgrade content
 *
 * @param {Object} downgrade
 * @param {Object} [metrics]
 * @param {MWParserEnvironment} env
 * @param {Object} revision
 * @param {string} version
 * @return {Object}
 */
$apiUtils->doDowngrade = function ( $downgrade, $metrics, $env, $revision, $version ) use ( &$apiUtils ) {
	if ( $metrics ) { $metrics->increment( "downgrade.from.{$downgrade->from}.to.{$downgrade->to}" );
 }
	$doc = $env->createDocument( $revision->html->body );
	$pb = $apiUtils->extractPageBundle( $revision );
	$apiUtils->validatePageBundle( $pb, $version );
	$start = time();
	$downgrade->func( $doc, $pb );
	if ( $metrics ) { $metrics->endTiming( 'downgrade.time', $start );
 }
	return [ 'doc' => $doc, 'pb' => $pb ];
};

/**
 * Downgrade and return content
 *
 * @param {Object} downgrade
 * @param {Object} [metrics]
 * @param {MWParserEnvironment} env
 * @param {Object} revision
 * @param {Response} res
 * @param {string} [contentmodel]
 */
$apiUtils->returnDowngrade = function ( $downgrade, $metrics, $env, $revision, $res, $contentmodel ) use ( &$apiUtils, &$ContentUtils, &$DOMUtils ) {
	$temp0 = $apiUtils->doDowngrade( $downgrade, $metrics, $env, $revision, $env->inputContentVersion );
$doc = $temp0->doc;
$pb = $temp0->pb;
	// Match the http-equiv meta to the content-type header
	$meta = $doc->querySelector( 'meta[property="mw:html:version"]' );
	if ( $meta ) { $meta->setAttribute( 'content', $env->outputContentVersion );
 }
	// No need to `ContentUtils.extractDpAndSerialize`, it wasn't applied.
	$html = ContentUtils::toXML( ( $res->locals->body_only ) ? $doc->body : $doc, [
			'innerXML' => $res->locals->body_only
		]
	);
	$apiUtils->wt2htmlRes( $res, $html, $pb, $contentmodel, DOMUtils::findHttpEquivHeaders( $doc ), $env->outputContentVersion );
};

/**
 * Send an appropriate response with the right content types for wt2html.
 *
 * @param {Object} res
 * @param {string} html
 * @param {Object} pb
 * @param {string} [contentmodel]
 * @param {Object} headers
 * @param {string} outputContentVersion
 */
$apiUtils->wt2htmlRes = function ( $res, $html, $pb, $contentmodel, $headers, $outputContentVersion ) use ( &$apiUtils, &$semver ) {
	if ( $pb ) {
		$response = [
			'contentmodel' => $contentmodel,
			'html' => [
				'headers' => Object::assign( [
						'content-type' => $apiUtils->htmlContentType( $outputContentVersion )
					], $headers
				),
				'body' => $html
			],
			'data-parsoid' => [
				'headers' => [ 'content-type' => $apiUtils->dataParsoidContentType( $outputContentVersion ) ],
				'body' => $pb->parsoid
			]
		];
		if ( semver::satisfies( $outputContentVersion, '^999.0.0' ) ) {
			$response[ 'data-mw' ] = [
				'headers' => [ 'content-type' => $apiUtils->dataMwContentType( $outputContentVersion ) ],
				'body' => $pb->mw
			];
		}
		$apiUtils->jsonResponse( $res, $response, null, $apiUtils->pagebundleContentType( $outputContentVersion ) );
	} else {
		$apiUtils->htmlResponse( $res, $html, null, Object::assign( [
					'content-type' => $apiUtils->htmlContentType( $outputContentVersion )
				], $headers
			), true
		);
	}
};

/**
 * @return {boolean}
 */
$apiUtils->shouldScrub = function ( $req, $def ) {
	// Check hasOwnProperty to avoid overwriting the default when
	// this isn't set.  `scrubWikitext` was renamed in RESTBase to
	// `scrub_wikitext`.  Support both for backwards compatibility,
	// but prefer the newer form.
	if ( $req->body->hasOwnProperty( 'scrub_wikitext' ) ) {
		return !( !$req->body->scrub_wikitext || $req->body->scrub_wikitext === 'false' );
	} elseif ( $req->query->hasOwnProperty( 'scrub_wikitext' ) ) {
		return !( !$req->query->scrub_wikitext || $req->query->scrub_wikitext === 'false' );
	} elseif ( $req->body->hasOwnProperty( 'scrubWikitext' ) ) {
		return !( !$req->body->scrubWikitext || $req->body->scrubWikitext === 'false' );
	} elseif ( $req->query->hasOwnProperty( 'scrubWikitext' ) ) {
		return !( !$req->query->scrubWikitext || $req->query->scrubWikitext === 'false' );
	} else {
		return $def;
	}
};
