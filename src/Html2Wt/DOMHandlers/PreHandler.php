<?php
declare( strict_types = 1 );

namespace Parsoid\Html2Wt\DOMHandlers;

use DOMElement;
use DOMNode;
use Parsoid\Html2Wt\SerializerState;
use Parsoid\Utils\DOMDataUtils;
use Parsoid\Utils\PHPUtils;
use Parsoid\Utils\Util;

class PreHandler extends DOMHandler {

	public function __construct() {
		parent::__construct( false );
	}

	/** @inheritDoc */
	public function handle(
		DOMElement $node, SerializerState $state, bool $wrapperUnmodified = false
	): ?DOMNode {
		// Handle indent pre

		// XXX: Use a pre escaper?
		$content = $state->serializeIndentPreChildrenToString( $node );
		// Strip (only the) trailing newline
		preg_match( '/\n$/', $content, $trailingNL );
		$content = preg_replace( '/\n$/', '', $content, 1 );

		// Insert indentation
		$solRE = '/'
			. '(\n('
			// SSS FIXME: What happened to the includeonly seen
			// in wts.separators.js?
			. PHPUtils::reStrip( Util::COMMENT_REGEXP )
			. ')*)'
		. '/';
		$content = ' ' . preg_replace( $solRE, '$1 ', $content );

		// But skip "empty lines" (lines with 1+ comment and
		// optional whitespace) since empty-lines sail through all
		// handlers without being affected.
		//
		// See empty_line_with_comments rule in pegTokenizer.pegjs
		//
		// We could use 'split' to split content into lines and
		// selectively add indentation, but the code will get
		// unnecessarily complex for questionable benefits. So, going
		// this route for now.
		$emptyLinesRE = '/'
			// This space comes from what we inserted earlier
			. '(^|\n) '
			. '((?:'
			. '[ \t]*'
			. PHPUtils::reStrip( Util::COMMENT_REGEXP )
			. '[ \t]*'
			. ')+)'
			. '(?=\n|$)'
		. '/';
		$content = preg_replace( $emptyLinesRE, '$1$2', $content, 1 );

		$state->emitChunk( $content, $node );

		// Preserve separator source
		$state->appendSep( $trailingNL[0] ?? '' );
		return $node->nextSibling;
	}

	/** @inheritDoc */
	public function before( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		if ( $otherNode->nodeName === 'pre'
			&& $otherNode instanceof DOMElement // for static analyzers
			&& ( DOMDataUtils::getDataParsoid( $otherNode )->stx ?? null ) !== 'html'
		) {
			return [ 'min' => 2 ];
		} else {
			return [ 'min' => 1 ];
		}
	}

	/** @inheritDoc */
	public function after( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		if ( $otherNode->nodeName === 'pre'
			&& $otherNode instanceof DOMElement // for static analyzers
			&& ( DOMDataUtils::getDataParsoid( $otherNode )->stx ?? null ) !== 'html'
		) {
			return [ 'min' => 2 ];
		} else {
			return [ 'min' => 1 ];
		}
	}

	/** @inheritDoc */
	public function firstChild( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		return [];
	}

	/** @inheritDoc */
	public function lastChild( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		return [];
	}

}
