<?php

/**
 * At present, this script is just used for testing the library and uses a
 * public MediaWiki API, which means it's expected to be slow.
 */

require_once __DIR__ . '/../tools/Maintenance.php';

use Parsoid\PageBundle;
use Parsoid\Parsoid;
use Parsoid\Selser;

use Parsoid\Config\Api\ApiHelper;
use Parsoid\Config\Api\DataAccess;
use Parsoid\Config\Api\PageConfig;
use Parsoid\Config\Api\SiteConfig;

// phpcs:ignore MediaWiki.Files.ClassMatchesFilename.WrongCase
class Parse extends \Parsoid\Tools\Maintenance {
	use \Parsoid\Tools\ExtendedOptsProcessor;

	public function __construct() {
		parent::__construct();
		$this->addDescription(
			"Omnibus script to convert between wikitext and HTML, and roundtrip wikitext or HTML. "
			. "Supports a number of options pertaining to pointing at a specific wiki "
			. "or enabling various features during these transformations.\n\n"
			. "If no options are provided, --wt2html is enabled by default.\n"
			. "See --help for detailed usage help." );
		$this->addOption( 'wt2html', 'Wikitext -> HTML' );
		$this->addOption( 'html2wt', 'HTML -> Wikitext' );
		$this->addOption( 'wt2wt', 'Wikitext -> Wikitext' );
		$this->addOption( 'html2html', 'HTML -> HTML' );
		$this->addOption( 'body_only',
						 'Just return the body, without any normalizations as in --normalize' );
		$this->addOption( 'selser',
						 'Use the selective serializer to go from HTML to Wikitext.' );
		$this->addOption(
			'oldtext',
			'The old page text for a selective-serialization (see --selser)',
			false,
			true
		);
		$this->addOption( 'oldtextfile',
						 'File containing the old page text for a selective-serialization (see --selser)',
						 false, true );
		$this->addOption( 'oldhtmlfile',
						 'File containing the old HTML for a selective-serialization (see --selser)',
						 false, true );
		$this->addOption( 'inputfile', 'File containing input as an alternative to stdin', false, true );
		$this->addOption(
			'pageName',
			'The page name, returned for {{PAGENAME}}. If no input is given ' .
			'(ie. empty/stdin closed), it downloads and parses the page. ' .
			'This should be the actual title of the article (that is, not ' .
			'including any URL-encoding that might be necessary in wikitext).',
			false,
			true
		);
		$this->addOption(
			'scrubWikitext',
			'Apply wikitext scrubbing while serializing.  This is also used ' .
			'for a mode of normalization (--normalize) applied when parsing.'
		);
		$this->addOption(
			'wrapSections',
			// Override the default in Env since the wrappers are annoying in dev-mode
			'Output <section> tags (default false)'
		);
		$this->addOption(
			'rtTestMode',
			'Test in rt test mode (changes some parse & serialization strategies)'
		);
		$this->addOption(
			'addHTMLTemplateParameters',
			'Parse template parameters to HTML and add them to template data'
		);
		$this->setAllowUnregisteredOptions( false );
	}

	/**
	 * @param array $configOpts
	 * @param array $parsoidOpts
	 * @param string|null $wt
	 * @return PageBundle
	 */
	public function wt2Html(
		array $configOpts, array $parsoidOpts, ?string $wt
	): PageBundle {
		if ( $wt !== null ) {
			$configOpts["pageContent"] = $wt;
		}

		$api = new ApiHelper( $configOpts );

		$siteConfig = new SiteConfig( $api, $configOpts );
		$dataAccess = new DataAccess( $api, $configOpts );

		$parsoid = new Parsoid( $siteConfig, $dataAccess );

		$pageConfig = new PageConfig( $api, $configOpts );

		return $parsoid->wikitext2html( $pageConfig, $parsoidOpts );
	}

	/**
	 * @param array $configOpts
	 * @param array $parsoidOpts
	 * @param PageBundle $pb
	 * @param Selser|null $selser
	 * @return string
	 */
	public function html2Wt(
		array $configOpts, array $parsoidOpts, PageBundle $pb,
		?Selser $selser = null
	): string {
		// PORT-FIXME: Think about when is the right time for this to be set.
		if ( $selser ) {
			$configOpts["pageContent"] = $selser->oldText;
		}

		$api = new ApiHelper( $configOpts );

		$siteConfig = new SiteConfig( $api, $configOpts );
		$dataAccess = new DataAccess( $api, $configOpts );

		$parsoid = new Parsoid( $siteConfig, $dataAccess );

		$pageConfig = new PageConfig( $api, $configOpts );

		return $parsoid->html2wikitext(
			$pageConfig, $pb, $parsoidOpts, $selser
		);
	}

	public function execute() {
		$this->maybeHelp();

		if ( $this->hasOption( 'inputfile' ) ) {
			$input = file_get_contents( $this->getOption( 'inputfile' ) );
			if ( $input === false ) {
				return;
			}
		} else {
			$input = file_get_contents( 'php://stdin' );
			if ( strlen( $input ) === 0 ) {
				// Parse page if no input
				if ( $this->hasOption( 'html2wt' ) || $this->hasOption( 'html2html' ) ) {
					$this->error(
						'Fetching page content is only supported when starting at wikitext.'
					);
					return;
				} else {
					$input = null;
				}
			}
		}

		$configOpts = [
			"apiEndpoint" => "https://en.wikipedia.org/w/api.php",
			"title" => $this->hasOption( 'pageName' ) ?
				$this->getOption( 'pageName' ) : "Api",
			"rtTestMode" => $this->hasOption( 'rtTestMode' ),
			"addHTMLTemplateParameters" => $this->hasOption( 'addHTMLTemplateParameters' ),
		];

		$parsoidOpts = [
			"scrubWikitext" => $this->hasOption( 'scrubWikitext' ),
			"body_only" => $this->hasOption( 'body_only' ),
			"wrapSections" => $this->hasOption( 'wrapSections' ),
		];

		$startsAtHtml = $this->hasOption( 'html2wt' ) ||
			$this->hasOption( 'html2html' ) ||
			$this->hasOption( 'selser' );

		if ( $startsAtHtml ) {
			if ( $this->hasOption( 'selser' ) ) {
				if ( $this->hasOption( 'oldtext' ) ) {
					$oldText = $this->getOption( 'oldtext' );
				} elseif ( $this->hasOption( 'oldtextfile' ) ) {
					$oldText = file_get_contents( $this->getOption( 'oldtextfile' ) );
					if ( $oldText === false ) {
						return;
					}
				} else {
					$this->error(
						'Please provide original wikitext ' .
						'(--oldtext or --oldtextfile). Selser requires that.'
					);
					$this->maybeHelp();
					return;
				}
				$oldHTML = null;
				if ( $this->hasOption( 'oldhtmlfile' ) ) {
					$oldHTML = file_get_contents( $this->getOption( 'oldhtmlfile' ) );
					if ( $oldHTML === false ) {
						return;
					}
				}
				$selser = new Selser( $oldText, $oldHTML );
			} else {
				$selser = null;
			}
			$pb = new PageBundle( $input );
			$wt = $this->html2Wt( $configOpts, $parsoidOpts, $pb, $selser );
			if ( $this->hasOption( 'html2html' ) ) {
				$pb = $this->wt2Html( $configOpts, $parsoidOpts, $wt );
				$this->output( $pb->html . "\n" );
			} else {
				$this->output( $wt );
			}
		} else {
			$pb = $this->wt2Html( $configOpts, $parsoidOpts, $input );
			if ( $this->hasOption( 'wt2wt' ) ) {
				$wt = $this->html2Wt( $configOpts, $parsoidOpts, $pb );
				$this->output( $wt );
			} else {
				$this->output( $pb->html . "\n" );
			}
		}
	}
}

$maintClass = Parse::class;
require_once PARSOID_RUN_MAINTENANCE_IF_MAIN;
