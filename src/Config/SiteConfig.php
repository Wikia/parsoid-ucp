<?php
declare( strict_types = 1 );

namespace Parsoid\Config;

use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;

use Parsoid\ContentModelHandler;
use Parsoid\WikitextContentModelHandler;
use Parsoid\Ext\Extension;
use Parsoid\Ext\ExtensionTag;
use Parsoid\Logger\LogData;
use Parsoid\Utils\Util;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Site-level configuration interface for Parsoid
 *
 * This includes both global configuration and wiki-level configuration.
 */
abstract class SiteConfig {

	private const OVERRIDE_UC_FIRST_CHARACTERS = [
		'ß' => 'ß',
		'ŉ' => 'ŉ',
		'ǅ' => 'ǅ',
		'ǆ' => 'ǅ',
		'ǈ' => 'ǈ',
		'ǉ' => 'ǈ',
		'ǋ' => 'ǋ',
		'ǌ' => 'ǋ',
		'ǰ' => 'ǰ',
		'ǲ' => 'ǲ',
		'ǳ' => 'ǲ',
		'ɪ' => 'ɪ',
		'ͅ' => 'ͅ',
		'ΐ' => 'ΐ',
		'ΰ' => 'ΰ',
		'և' => 'և',
		'ა' => 'ა',
		'ბ' => 'ბ',
		'გ' => 'გ',
		'დ' => 'დ',
		'ე' => 'ე',
		'ვ' => 'ვ',
		'ზ' => 'ზ',
		'თ' => 'თ',
		'ი' => 'ი',
		'კ' => 'კ',
		'ლ' => 'ლ',
		'მ' => 'მ',
		'ნ' => 'ნ',
		'ო' => 'ო',
		'პ' => 'პ',
		'ჟ' => 'ჟ',
		'რ' => 'რ',
		'ს' => 'ს',
		'ტ' => 'ტ',
		'უ' => 'უ',
		'ფ' => 'ფ',
		'ქ' => 'ქ',
		'ღ' => 'ღ',
		'ყ' => 'ყ',
		'შ' => 'შ',
		'ჩ' => 'ჩ',
		'ც' => 'ც',
		'ძ' => 'ძ',
		'წ' => 'წ',
		'ჭ' => 'ჭ',
		'ხ' => 'ხ',
		'ჯ' => 'ჯ',
		'ჰ' => 'ჰ',
		'ჱ' => 'ჱ',
		'ჲ' => 'ჲ',
		'ჳ' => 'ჳ',
		'ჴ' => 'ჴ',
		'ჵ' => 'ჵ',
		'ჶ' => 'ჶ',
		'ჷ' => 'ჷ',
		'ჸ' => 'ჸ',
		'ჹ' => 'ჹ',
		'ჺ' => 'ჺ',
		'ჽ' => 'ჽ',
		'ჾ' => 'ჾ',
		'ჿ' => 'ჿ',
		'ᲀ' => 'ᲀ',
		'ᲁ' => 'ᲁ',
		'ᲂ' => 'ᲂ',
		'ᲃ' => 'ᲃ',
		'ᲄ' => 'ᲄ',
		'ᲅ' => 'ᲅ',
		'ᲆ' => 'ᲆ',
		'ᲇ' => 'ᲇ',
		'ᲈ' => 'ᲈ',
		'ẖ' => 'ẖ',
		'ẗ' => 'ẗ',
		'ẘ' => 'ẘ',
		'ẙ' => 'ẙ',
		'ẚ' => 'ẚ',
		'ὐ' => 'ὐ',
		'ὒ' => 'ὒ',
		'ὔ' => 'ὔ',
		'ὖ' => 'ὖ',
		'ᾀ' => 'ᾈ',
		'ᾁ' => 'ᾉ',
		'ᾂ' => 'ᾊ',
		'ᾃ' => 'ᾋ',
		'ᾄ' => 'ᾌ',
		'ᾅ' => 'ᾍ',
		'ᾆ' => 'ᾎ',
		'ᾇ' => 'ᾏ',
		'ᾈ' => 'ᾈ',
		'ᾉ' => 'ᾉ',
		'ᾊ' => 'ᾊ',
		'ᾋ' => 'ᾋ',
		'ᾌ' => 'ᾌ',
		'ᾍ' => 'ᾍ',
		'ᾎ' => 'ᾎ',
		'ᾏ' => 'ᾏ',
		'ᾐ' => 'ᾘ',
		'ᾑ' => 'ᾙ',
		'ᾒ' => 'ᾚ',
		'ᾓ' => 'ᾛ',
		'ᾔ' => 'ᾜ',
		'ᾕ' => 'ᾝ',
		'ᾖ' => 'ᾞ',
		'ᾗ' => 'ᾟ',
		'ᾘ' => 'ᾘ',
		'ᾙ' => 'ᾙ',
		'ᾚ' => 'ᾚ',
		'ᾛ' => 'ᾛ',
		'ᾜ' => 'ᾜ',
		'ᾝ' => 'ᾝ',
		'ᾞ' => 'ᾞ',
		'ᾟ' => 'ᾟ',
		'ᾠ' => 'ᾨ',
		'ᾡ' => 'ᾩ',
		'ᾢ' => 'ᾪ',
		'ᾣ' => 'ᾫ',
		'ᾤ' => 'ᾬ',
		'ᾥ' => 'ᾭ',
		'ᾦ' => 'ᾮ',
		'ᾧ' => 'ᾯ',
		'ᾨ' => 'ᾨ',
		'ᾩ' => 'ᾩ',
		'ᾪ' => 'ᾪ',
		'ᾫ' => 'ᾫ',
		'ᾬ' => 'ᾬ',
		'ᾭ' => 'ᾭ',
		'ᾮ' => 'ᾮ',
		'ᾯ' => 'ᾯ',
		'ᾲ' => 'ᾲ',
		'ᾳ' => 'ᾼ',
		'ᾴ' => 'ᾴ',
		'ᾶ' => 'ᾶ',
		'ᾷ' => 'ᾷ',
		'ᾼ' => 'ᾼ',
		'ῂ' => 'ῂ',
		'ῃ' => 'ῌ',
		'ῄ' => 'ῄ',
		'ῆ' => 'ῆ',
		'ῇ' => 'ῇ',
		'ῌ' => 'ῌ',
		'ῒ' => 'ῒ',
		'ΐ' => 'ΐ',
		'ῖ' => 'ῖ',
		'ῗ' => 'ῗ',
		'ῢ' => 'ῢ',
		'ΰ' => 'ΰ',
		'ῤ' => 'ῤ',
		'ῦ' => 'ῦ',
		'ῧ' => 'ῧ',
		'ῲ' => 'ῲ',
		'ῳ' => 'ῼ',
		'ῴ' => 'ῴ',
		'ῶ' => 'ῶ',
		'ῷ' => 'ῷ',
		'ῼ' => 'ῼ',
		'ⅰ' => 'ⅰ',
		'ⅱ' => 'ⅱ',
		'ⅲ' => 'ⅲ',
		'ⅳ' => 'ⅳ',
		'ⅴ' => 'ⅴ',
		'ⅵ' => 'ⅵ',
		'ⅶ' => 'ⅶ',
		'ⅷ' => 'ⅷ',
		'ⅸ' => 'ⅸ',
		'ⅹ' => 'ⅹ',
		'ⅺ' => 'ⅺ',
		'ⅻ' => 'ⅻ',
		'ⅼ' => 'ⅼ',
		'ⅽ' => 'ⅽ',
		'ⅾ' => 'ⅾ',
		'ⅿ' => 'ⅿ',
		'ⓐ' => 'ⓐ',
		'ⓑ' => 'ⓑ',
		'ⓒ' => 'ⓒ',
		'ⓓ' => 'ⓓ',
		'ⓔ' => 'ⓔ',
		'ⓕ' => 'ⓕ',
		'ⓖ' => 'ⓖ',
		'ⓗ' => 'ⓗ',
		'ⓘ' => 'ⓘ',
		'ⓙ' => 'ⓙ',
		'ⓚ' => 'ⓚ',
		'ⓛ' => 'ⓛ',
		'ⓜ' => 'ⓜ',
		'ⓝ' => 'ⓝ',
		'ⓞ' => 'ⓞ',
		'ⓟ' => 'ⓟ',
		'ⓠ' => 'ⓠ',
		'ⓡ' => 'ⓡ',
		'ⓢ' => 'ⓢ',
		'ⓣ' => 'ⓣ',
		'ⓤ' => 'ⓤ',
		'ⓥ' => 'ⓥ',
		'ⓦ' => 'ⓦ',
		'ⓧ' => 'ⓧ',
		'ⓨ' => 'ⓨ',
		'ⓩ' => 'ⓩ',
		'ꞹ' => 'ꞹ',
		'ﬀ' => 'ﬀ',
		'ﬁ' => 'ﬁ',
		'ﬂ' => 'ﬂ',
		'ﬃ' => 'ﬃ',
		'ﬄ' => 'ﬄ',
		'ﬅ' => 'ﬅ',
		'ﬆ' => 'ﬆ',
		'ﬓ' => 'ﬓ',
		'ﬔ' => 'ﬔ',
		'ﬕ' => 'ﬕ',
		'ﬖ' => 'ﬖ',
		'ﬗ' => 'ﬗ',
		'𐓘' => '𐓘',
		'𐓙' => '𐓙',
		'𐓚' => '𐓚',
		'𐓛' => '𐓛',
		'𐓜' => '𐓜',
		'𐓝' => '𐓝',
		'𐓞' => '𐓞',
		'𐓟' => '𐓟',
		'𐓠' => '𐓠',
		'𐓡' => '𐓡',
		'𐓢' => '𐓢',
		'𐓣' => '𐓣',
		'𐓤' => '𐓤',
		'𐓥' => '𐓥',
		'𐓦' => '𐓦',
		'𐓧' => '𐓧',
		'𐓨' => '𐓨',
		'𐓩' => '𐓩',
		'𐓪' => '𐓪',
		'𐓫' => '𐓫',
		'𐓬' => '𐓬',
		'𐓭' => '𐓭',
		'𐓮' => '𐓮',
		'𐓯' => '𐓯',
		'𐓰' => '𐓰',
		'𐓱' => '𐓱',
		'𐓲' => '𐓲',
		'𐓳' => '𐓳',
		'𐓴' => '𐓴',
		'𐓵' => '𐓵',
		'𐓶' => '𐓶',
		'𐓷' => '𐓷',
		'𐓸' => '𐓸',
		'𐓹' => '𐓹',
		'𐓺' => '𐓺',
		'𐓻' => '𐓻',
		'𖹠' => '𖹠',
		'𖹡' => '𖹡',
		'𖹢' => '𖹢',
		'𖹣' => '𖹣',
		'𖹤' => '𖹤',
		'𖹥' => '𖹥',
		'𖹦' => '𖹦',
		'𖹧' => '𖹧',
		'𖹨' => '𖹨',
		'𖹩' => '𖹩',
		'𖹪' => '𖹪',
		'𖹫' => '𖹫',
		'𖹬' => '𖹬',
		'𖹭' => '𖹭',
		'𖹮' => '𖹮',
		'𖹯' => '𖹯',
		'𖹰' => '𖹰',
		'𖹱' => '𖹱',
		'𖹲' => '𖹲',
		'𖹳' => '𖹳',
		'𖹴' => '𖹴',
		'𖹵' => '𖹵',
		'𖹶' => '𖹶',
		'𖹷' => '𖹷',
		'𖹸' => '𖹸',
		'𖹹' => '𖹹',
		'𖹺' => '𖹺',
		'𖹻' => '𖹻',
		'𖹼' => '𖹼',
		'𖹽' => '𖹽',
		'𖹾' => '𖹾',
		'𖹿' => '𖹿',
		'𞤢' => '𞤢',
		'𞤣' => '𞤣',
		'𞤤' => '𞤤',
		'𞤥' => '𞤥',
		'𞤦' => '𞤦',
		'𞤧' => '𞤧',
		'𞤨' => '𞤨',
		'𞤩' => '𞤩',
		'𞤪' => '𞤪',
		'𞤫' => '𞤫',
		'𞤬' => '𞤬',
		'𞤭' => '𞤭',
		'𞤮' => '𞤮',
		'𞤯' => '𞤯',
		'𞤰' => '𞤰',
		'𞤱' => '𞤱',
		'𞤲' => '𞤲',
		'𞤳' => '𞤳',
		'𞤴' => '𞤴',
		'𞤵' => '𞤵',
		'𞤶' => '𞤶',
		'𞤷' => '𞤷',
		'𞤸' => '𞤸',
		'𞤹' => '𞤹',
		'𞤺' => '𞤺',
		'𞤻' => '𞤻',
		'𞤼' => '𞤼',
		'𞤽' => '𞤽',
		'𞤾' => '𞤾',
		'𞤿' => '𞤿',
		'𞥀' => '𞥀',
		'𞥁' => '𞥁',
		'𞥂' => '𞥂',
		'𞥃' => '𞥃',
	];

	/** @var LoggerInterface|null */
	protected $logger = null;

	/** @var int */
	protected $iwMatcherBatchSize = 4096;

	/** @var array|null */
	private $iwMatcher = null;

	/** @var bool */
	protected $rtTestMode = false;

	/** @var bool */
	protected $addHTMLTemplateParameters = false;

	/**
	 * The Parsoid/JS extension registration mechanism is short-lived and
	 * we are going to probably rely on the core extension mechanism once
	 * we integrate into core. So, for the port, it is simplest to just
	 * hardcode the list of extensions that have native equivalents in Parsoid.
	 *
	 * @var array
	 */
	private $defaultNativeExtensions = [
		'Cite', 'LST', 'Nowiki', 'Poem', 'Pre', 'Translate',
		/*
		 * Not yet ported / merged
		 *
		'Gallery', 'JSON'
		 */
	];

	/** var array */
	private $nativeExtConfig = null;

	/** @var bool */
	private $nativeExtConfigInitialized;

	public function __construct() {
		$this->nativeExtConfigInitialized = false;
		$this->nativeExtConfig = [
			'allTags'       => [],
			'nativeTags'    => [],
			'domProcessors' => [],
			'styles'        => [],
			'contentModels' => []
		];
	}

	/************************************************************************//**
	 * @name   Global config
	 * @{
	 */

	/**
	 * General log channel
	 * @return LoggerInterface
	 */
	public function getLogger(): LoggerInterface {
		if ( $this->logger === null ) {
			$this->logger = new NullLogger;
		}
		return $this->logger;
	}

	/**
	 * Log channel for traces
	 * @return LoggerInterface
	 */
	public function getTraceLogger(): LoggerInterface {
		return $this->getLogger();
	}

	/**
	 * Test which trace information to log
	 *
	 * Known flags include 'time' and 'time/dompp'.
	 *
	 * @param string $flag Flag name.
	 * @return bool
	 */
	public function hasTraceFlag( string $flag ): bool {
		return false;
	}

	/**
	 * Log channel for dumps
	 * @return LoggerInterface
	 */
	public function getDumpLogger(): LoggerInterface {
		return $this->getLogger();
	}

	/**
	 * Test which state to dump
	 *
	 * Known flags include 'dom:post-dom-diff', 'dom:post-normal', 'dom:post-builder',
	 * various other things beginning 'dom:pre-' and 'dom:post-',
	 * 'wt2html:limits', 'extoutput', and 'tplsrc'.
	 *
	 * @param string $flag Flag name.
	 * @return bool
	 */
	public function hasDumpFlag( string $flag ): bool {
		return false;
	}

	/**
	 * Test in rt test mode (changes some parse & serialization strategies)
	 * @return bool
	 */
	public function rtTestMode(): bool {
		return $this->rtTestMode;
	}

	/**
	 * When processing template parameters, parse them to HTML and add it to the
	 * template parameters data.
	 * @return bool
	 */
	public function addHTMLTemplateParameters(): bool {
		return $this->addHTMLTemplateParameters;
	}

	/**
	 * Whether to enable linter Backend.
	 * @return bool|string[] Boolean to enable/disable all linting, or an array
	 *  of enabled linting types.
	 */
	public function linting() {
		return false;
	}

	/**
	 * Maximum run length for Tidy whitespace bug
	 * @return int Length in Unicode codepoints
	 */
	public function tidyWhitespaceBugMaxLength(): int {
		return 100;
	}

	/**
	 * Statistics aggregator, for counting and timing.
	 *
	 * @todo Do we want to continue to have a wrapper that adds an endTiming()
	 *  method instead of using StatsdDataFactoryInterface directly?
	 * @return StatsdDataFactoryInterface|null
	 */
	public function metrics(): ?StatsdDataFactoryInterface {
		return null;
	}

	/**
	 * If enabled, bidi chars adjacent to category links will be stripped
	 * in the html -> wt serialization pass.
	 * @return bool
	 */
	public function scrubBidiChars(): bool {
		return false;
	}

	/**@}*/

	/************************************************************************//**
	 * @name   Wiki config
	 * @{
	 */

	/**
	 * Allowed external image URL prefixes.
	 *
	 * @return string[] The empty array matches no URLs. The empty string matches
	 *  all URLs.
	 */
	abstract public function allowedExternalImagePrefixes(): array;

	/**
	 * Site base URI
	 *
	 * This would be the URI found in `<base href="..." />`.
	 *
	 * @return string
	 */
	abstract public function baseURI(): string;

	/**
	 * Prefix for relative links
	 *
	 * Prefix to prepend to a page title to link to that page.
	 * Intended to be relative to the URI returned by baseURI().
	 *
	 * If possible, keep the default "./" so clients need not know this value
	 * to extract titles from link hrefs.
	 *
	 * @return string
	 */
	public function relativeLinkPrefix(): string {
		return './';
	}

	/**
	 * Regex matching all double-underscore magic words
	 * @return string
	 */
	abstract public function bswPagePropRegexp(): string;

	/**
	 * Map a canonical namespace name to its index
	 *
	 * @note This replaces canonicalNamespaces
	 * @param string $name all-lowercase and with underscores rather than spaces.
	 * @return int|null
	 */
	abstract public function canonicalNamespaceId( string $name ): ?int;

	/**
	 * Map a namespace name to its index
	 *
	 * @note This replaces canonicalNamespaces
	 * @param string $name
	 * @return int|null
	 */
	abstract public function namespaceId( string $name ): ?int;

	/**
	 * Map a namespace index to its preferred name
	 *
	 * @note This replaces namespaceNames
	 * @param int $ns
	 * @return string|null
	 */
	abstract public function namespaceName( int $ns ): ?string;

	/**
	 * Test if a namespace has subpages
	 *
	 * @note This replaces namespacesWithSubpages
	 * @param int $ns
	 * @return bool
	 */
	abstract public function namespaceHasSubpages( int $ns ): bool;

	/**
	 * Return namespace case setting
	 * @param int $ns
	 * @return string 'first-letter' or 'case-sensitive'
	 */
	abstract public function namespaceCase( int $ns ): string;

	/**
	 * Test if a namespace is a talk namespace
	 *
	 * @note This replaces title.getNamespace().isATalkNamespace()
	 * @param int $ns
	 * @return bool
	 */
	public function namespaceIsTalk( int $ns ): bool {
		return $ns > 0 && $ns % 2;
	}

	/**
	 * Uppercasing method for titles
	 * @param string $str
	 * @return string
	 */
	public function ucfirst( string $str ): string {
		$o = ord( $str );
		if ( $o < 96 ) { // if already uppercase...
			return $str;
		} elseif ( $o < 128 ) {
			if ( $str[0] === 'i' &&
				in_array( $this->lang(), [ 'az', 'tr', 'kaa', 'kk' ], true )
			) {
				return 'İ' . mb_substr( $str, 1 );
			}
			return ucfirst( $str ); // use PHP's ucfirst()
		} else {
			// fall back to more complex logic in case of multibyte strings
			$char = mb_substr( $str, 0, 1 );
			return $this->mbUpperChar( $char ) . mb_substr( $str, 1 );
		}
	}

	/**
	 * Convert character to uppercase, allowing overrides of the default mb_upper
	 * behaviour, which is buggy in many ways. Having a conversion table can be
	 * useful during transitions between PHP versions where unicode changes happen.
	 * This can make some resources unreachable on-wiki, see discussion at T219279.
	 * Providing such a conversion table can allow to manage the transition period.
	 *
	 * @param string $char
	 *
	 * @return string
	 */
	protected function mbUpperChar( $char ) {
		if ( array_key_exists( $char, self::OVERRIDE_UC_FIRST_CHARACTERS ) ) {
			return self::OVERRIDE_UC_FIRST_CHARACTERS[$char];
		} else {
			return mb_strtoupper( $char );
		}
	}

	/**
	 * Get the canonical name for a special page
	 * @param string $alias Special page alias
	 * @return string|null
	 */
	abstract public function canonicalSpecialPageName( string $alias ): ?string;

	/**
	 * Treat language links as magic connectors, not inline links
	 * @return bool
	 */
	abstract public function interwikiMagic(): bool;

	/**
	 * Interwiki link data
	 * @return array[] Keys are interwiki prefixes, values are arrays with the following keys:
	 *   - prefix: (string) The interwiki prefix, same as the key.
	 *   - url: (string) Target URL, containing a '$1' to be replaced by the interwiki target.
	 *   - protorel: (bool, optional) Whether the url may be accessed by both http:// and https://.
	 *   - local: (bool, optional) Whether the interwiki link is considered local (to the wikifarm).
	 *   - localinterwiki: (bool, optional) Whether the interwiki link points to the current wiki.
	 *   - language: (bool, optional) Whether the interwiki link is a language link.
	 *   - extralanglink: (bool, optional) Whether the interwiki link is an "extra language link".
	 *   - linktext: (string, optional) For "extra language links", the link text.
	 *  (booleans marked "optional" must be omitted if false)
	 */
	abstract public function interwikiMap(): array;

	/**
	 * Match interwiki URLs
	 * @param string $href Link to match against
	 * @return string[]|null Two values [ string $key, string $target ] on success, null on no match.
	 */
	public function interwikiMatcher( string $href ): ?array {
		if ( $this->iwMatcher === null ) {
			$keys = [ [], [] ];
			$patterns = [ [], [] ];
			foreach ( $this->interwikiMap() as $key => $iw ) {
				$lang = (int)( !empty( $iw['language'] ) );

				$url = $iw['url'];
				$protocolRelative = substr( $url, 0, 2 ) === '//';
				if ( !empty( $iw['protorel'] ) ) {
					$url = preg_replace( '/^https?:/', '', $url );
					$protocolRelative = true;
				}

				// full-url match pattern
				$keys[$lang][] = $key;
				$patterns[$lang][] =
					// Support protocol-relative URLs
					( $protocolRelative ? '(?:https?:)?' : '' )
					// Convert placeholder to group match
					. strtr( preg_quote( $url, '/' ), [ '\\$1' => '(.*?)' ] );

				if ( !empty( $iw['local'] ) ) {
					// ./$interwikiPrefix:$title and
					// $interwikiPrefix%3A$title shortcuts
					// are recognized and the local wiki forwards
					// these shortcuts to the remote wiki

					$keys[$lang][] = $key;
					$patterns[$lang][] = '^\\.\\/' . $iw['prefix'] . ':(.*?)';

					$keys[$lang][] = $key;
					$patterns[$lang][] = '^' . $iw['prefix'] . '%3A(.*?)';
				}
			}

			// Prefer language matches over non-language matches
			$numLangs = count( $keys[1] );
			$keys = array_merge( $keys[1], $keys[0] );
			$patterns = array_merge( $patterns[1], $patterns[0] );

			// Chunk patterns into reasonably sized regexes
			$this->iwMatcher = [];
			$batchStart = 0;
			$batchLen = 0;
			foreach ( $patterns as $i => $pat ) {
				$len = strlen( $pat );
				if ( $i !== $batchStart && $batchLen + $len > $this->iwMatcherBatchSize ) {
					$this->iwMatcher[] = [
						array_slice( $keys, $batchStart, $i - $batchStart ),
						'/^(?:' . implode( '|', array_slice( $patterns, $batchStart, $i - $batchStart ) ) . ')$/i',
						$numLangs - $batchStart,
					];
					$batchStart = $i;
					$batchLen = $len;
				} else {
					$batchLen += $len;
				}
			}
			$i = count( $patterns );
			if ( $i > $batchStart ) {
				$this->iwMatcher[] = [
					array_slice( $keys, $batchStart, $i - $batchStart ),
					'/^(?:' . implode( '|', array_slice( $patterns, $batchStart, $i - $batchStart ) ) . ')$/i',
					$numLangs - $batchStart,
				];
			}
		}

		foreach ( $this->iwMatcher as list( $keys, $regex, $numLangs ) ) {
			if ( preg_match( $regex, $href, $m, PREG_UNMATCHED_AS_NULL ) ) {
				foreach ( $keys as $i => $key ) {
					if ( isset( $m[$i + 1] ) ) {
						if ( $i < $numLangs ) {
							// Escape language interwikis with a colon
							$key = ':' . $key;
						}
						return [ $key, $m[$i + 1] ];
					}
				}
			}
		}
		return null;
	}

	/**
	 * Wiki identifier, for cache keys.
	 * Should match a key in mwApiMap()?
	 * @return string
	 */
	abstract public function iwp(): string;

	/**
	 * Legal title characters
	 *
	 * Regex is intended to match bytes, not Unicode characters.
	 *
	 * @return string Regex character class (i.e. the bit that goes inside `[]`)
	 */
	abstract public function legalTitleChars() : string;

	/**
	 * Link prefix regular expression.
	 * @return string|null
	 */
	abstract public function linkPrefixRegex(): ?string;

	/**
	 * Link trail regular expression.
	 * @return string|null
	 */
	abstract public function linkTrailRegex(): ?string;

	/**
	 * Log linter data.
	 * @note This replaces JS linterEnabled.
	 * @param LogData $logData
	 */
	public function logLinterData( LogData $logData ): void {
		// In MW, call a hook that the Linter extension will listen on
	}

	/**
	 * Wiki language code.
	 * @return string
	 */
	abstract public function lang(): string;

	/**
	 * Main page title
	 * @return string
	 */
	abstract public function mainpage(): string;

	/**
	 * Responsive references configuration
	 * @return array With two keys:
	 *  - enabled: (bool) Whether it's enabled
	 *  - threshold: (int) Threshold
	 */
	abstract public function responsiveReferences(): array;

	/**
	 * Whether the wiki language is right-to-left
	 * @return bool
	 */
	abstract public function rtl(): bool;

	/**
	 * Whether language converter is enabled for the specified language
	 * @param string $lang Language code
	 * @return bool
	 */
	abstract public function langConverterEnabled( string $lang ): bool;

	/**
	 * The URL path to index.php.
	 * @return string
	 */
	abstract public function script(): string;

	/**
	 * The base wiki path
	 * @return string
	 */
	abstract public function scriptpath(): string;

	/**
	 * The base URL of the server.
	 * @return string
	 */
	abstract public function server(): string;

	/**
	 * Get the base URL for loading resource modules
	 * This is the $wgLoadScript config value.
	 *
	 * This base class provides the default value.
	 * Derived classes should override appropriately.
	 *
	 * @return string
	 */
	public function getModulesLoadURI(): string {
		return $this->server() . $this->scriptpath() . '/load.php';
	}

	/**
	 * A regex matching a line containing just whitespace, comments, and
	 * sol transparent links and behavior switches.
	 * @return string
	 */
	abstract public function solTransparentWikitextRegexp(): string;

	/**
	 * A regex matching a line containing just comments and
	 * sol transparent links and behavior switches.
	 * @return string
	 */
	abstract public function solTransparentWikitextNoWsRegexp(): string;

	/**
	 * The wiki's time zone offset
	 * @return int Minutes east of UTC
	 */
	abstract public function timezoneOffset(): int;

	/**
	 * Language variant information
	 * @return array Keys are variant codes (e.g. "zh-cn"), values are arrays with two fields:
	 *   - base: (string) Base language code (e.g. "zh")
	 *   - fallbacks: (string[]) Fallback variants
	 */
	abstract public function variants(): array;

	/**
	 * Default thumbnail width
	 * @return int
	 */
	abstract public function widthOption(): int;

	/**
	 * List all magic words by alias
	 * @return string[] Keys are aliases, values are canonical names.
	 */
	abstract public function magicWords(): array;

	/**
	 * List all magic words by canonical name
	 * @return string[][] Keys are canonical names, values are arrays of aliases.
	 */
	abstract public function mwAliases(): array;

	/**
	 * Return canonical magic word for a function hook
	 * @param string $str
	 * @return string|null
	 */
	abstract public function getMagicWordForFunctionHook( string $str ): ?string;

	/**
	 * Return canonical magic word for a variable
	 * @param string $str
	 * @return string|null
	 */
	abstract public function getMagicWordForVariable( string $str ): ?string;

	/**
	 * Get canonical magicword name for the input word.
	 *
	 * @param string $word
	 * @return string|null
	 */
	public function magicWordCanonicalName( string $word ): ?string {
		$mws = $this->magicWords();
		return $mws[$word] ?? $mws[mb_strtolower( $word )] ?? null;
	}

	/**
	 * Check if a string is a recognized magic word.
	 *
	 * @param string $word
	 * @return bool
	 */
	public function isMagicWord( string $word ): bool {
		return $this->magicWordCanonicalName( $word ) !== null;
	}

	/**
	 * Convert the internal canonical magic word name to the wikitext alias.
	 * @param string $word Canonical magic word name
	 * @param string $suggest Suggested alias (used as fallback and preferred choice)
	 * @return string
	 */
	public function getMagicWordWT( string $word, string $suggest ): string {
		$aliases = $this->mwAliases()[$word] ?? null;
		if ( !$aliases ) {
			return $suggest;
		}
		$ind = 0;
		if ( $suggest ) {
			$ind = array_search( $suggest, $aliases, true );
		}
		return $aliases[$ind ?: 0];
	}

	/**
	 * Get a regexp matching a localized magic word, given its id.
	 *
	 * FIXME: misleading function name
	 *
	 * @param string $id
	 * @return string
	 */
	abstract public function getMagicWordMatcher( string $id ): string;

	/**
	 * Get a matcher function for fetching values out of interpolated magic words,
	 * ie those with `$1` in their aliases.
	 *
	 * The matcher takes a string and returns null if it doesn't match any of
	 * the words, or an associative array if it did match:
	 *  - k: The magic word that matched
	 *  - v: The value of $1 that was matched
	 * (the JS also returned 'a' with the specific alias that matched, but that
	 * seems to be unused and so is omitted here)
	 *
	 * @param string[] $words Magic words to match
	 * @return callable
	 */
	abstract public function getParameterizedAliasMatcher( array $words ): callable;

	/**
	 * Get the maximum template depth
	 *
	 * @return int
	 */
	abstract public function getMaxTemplateDepth(): int;

	/**
	 * Matcher for ISBN/RFC/PMID URL patterns, returning the type and number.
	 *
	 * The match method takes a string and returns false on no match or a tuple
	 * like this on match: [ 'RFC', '12345' ]
	 *
	 * @return callable
	 */
	abstract public function getExtResourceURLPatternMatcher(): callable;

	/**
	 * Serialize ISBN/RFC/PMID URL patterns
	 *
	 * @param string[] $match As returned by the getExtResourceURLPatternMatcher() matcher
	 * @param string $href Fallback link target, if $match is invalid.
	 * @param string $content Link text
	 * @return string
	 */
	public function makeExtResourceURL( array $match, string $href, string $content ): string {
		$normalized = preg_replace(
			'/[ \x{00A0}\x{1680}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]+/u', ' ',
			Util::decodeWtEntities( $content )
		);

		// TODO: T145590 ("Update Parsoid to be compatible with magic links being disabled")
		switch ( $match[0] ) {
			case 'ISBN':
				$normalized = strtoupper( preg_replace( '/[\- \t]/', '', $normalized ) );
				// validate ISBN length and format, so as not to produce magic links
				// which aren't actually magic
				$valid = preg_match( '/^ISBN(97[89])?\d{9}(\d|X)$/', $normalized );
				if ( implode( '', $match ) === $normalized && $valid ) {
					return $content;
				}
				// strip "./" prefix. TODO: Use relativeLinkPrefix() instead?
				$href = preg_replace( '!^\./!', '', $href );
				return "[[$href|$content]]";

			case 'RFC':
			case 'PMID':
				$normalized = preg_replace( '/[ \t]/', '', $normalized );
				return implode( '', $match ) === $normalized ? $content : "[$href $content]";

			default:
				throw new \InvalidArgumentException( "Invalid match type '{$match[0]}'" );
		}
	}

	/**
	 * Matcher for valid protocols, must be anchored at start of string.
	 * @param string $potentialLink
	 * @return bool Whether $potentialLink begins with a valid protocol
	 */
	abstract public function hasValidProtocol( string $potentialLink ): bool;

	/**
	 * Matcher for valid protocols, may occur at any point within string.
	 * @param string $potentialLink
	 * @return bool Whether $potentialLink contains a valid protocol
	 */
	abstract public function findValidProtocol( string $potentialLink ): bool;

	/**@}*/

	/**
	 * Fake timestamp, for unit tests.
	 * @return int|null Unix timestamp, or null to not fake it
	 */
	public function fakeTimestamp(): ?int {
		return null;
	}

	/**
	 * Get an array of defined extension tags, with the lower case name in the
	 * key, the value arbitrary. This is the set of extension tags that are
	 * configured in M/W core. $defaultNativeExtensions may already be part of it,
	 * but eventually this distinction will disappear since all extension tags
	 * have to be defined against the Parsoid's extension API.
	 *
	 * @return array
	 */
	abstract protected function getNonNativeExtensionTags(): array;

	private function constructNativeExtConfig() {
		$this->nativeExtConfig['allTags'] = array_merge( $this->nativeExtConfig['allTags'],
			$this->getNonNativeExtensionTags() ?? [] );

		// Default content model implementation for wikitext
		$this->nativeExtConfig['contentModels']['wikitext'] = new WikitextContentModelHandler();

		foreach ( $this->defaultNativeExtensions as $extName ) {
			$extPkg = '\Parsoid\Ext\\' . $extName . '\\' . $extName;
			$this->registerNativeExtension( new $extPkg() );
		}

		$this->nativeExtConfigInitialized = true;
	}

	/**
	 * Register a Parsoid-native extension
	 * @param Extension $ext
	 */
	protected function registerNativeExtension( Extension $ext ): void {
		$extConfig = $ext->getConfig();

		// This is for wt2html toDOM, html2wt fromHTML, and linter functionality
		foreach ( $extConfig['tags'] as $tagConfig ) {
			$lowerTagName = strToLower( $tagConfig['name'] );
			$this->nativeExtConfig['allTags'][$lowerTagName] = true;
			$this->nativeExtConfig['nativeTags'][$lowerTagName] = $tagConfig;
		}

		// This is for wt2htmlPostProcessor and html2wtPreProcessor functionality
		if ( isset( $extConfig['domProcessors'] ) ) {
			$this->nativeExtConfig['domProcessors'][get_class( $ext )] = $extConfig['domProcessors'];
		}

		// Does this extension export any native styles?
		// FIXME: When we integrate with core, this will probably generalize
		// to all resources (scripts, modules, etc). not just styles.
		// De-dupe styles after merging.
		$this->nativeExtConfig['styles'] = array_unique( array_merge(
			$this->nativeExtConfig['styles'], $extConfig['styles'] ?? []
		) );

		if ( isset( $extConfig['contentModels'] ) ) {
			foreach ( $extConfig['contentModels'] as $cm => $impl ) {
				// For compatibility with mediawiki core, the first
				// registered extension wins.
				if ( isset( $this->nativeExtConfig['contentModels'][$cm] ) ) {
					continue;
				}

				$this->nativeExtConfig['contentModels'][$cm] = $impl;
			}
		}
	}

	/**
	 * @return array
	 */
	private function getNativeExtensionsConfig(): array {
		if ( !$this->nativeExtConfigInitialized ) {
			$this->constructNativeExtConfig();
		}
		return $this->nativeExtConfig;
	}

	/**
	 * @param string $contentmodel
	 * @return ContentModelHandler|null
	 */
	public function getContentModelHandler( string $contentmodel ): ?ContentModelHandler {
		return ( $this->getNativeExtensionsConfig() )['contentModels'][$contentmodel];
	}

	/**
	 * Determine whether a given name, which must have already been converted
	 * to lower case, is a valid extension tag name.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function isExtensionTag( string $name ): bool {
		return isset( $this->getExtensionTagNameMap()[ $name ] );
	}

	/**
	 * Get an array of defined extension tags, with the lower case name
	 * in the key, and the value being arbitrary.
	 *
	 * @return array
	 */
	public function getExtensionTagNameMap(): array {
		$nativeExtConfig = $this->getNativeExtensionsConfig();
		return $nativeExtConfig['allTags'];
	}

	/**
	 * @param string $tagName Extension tag name
	 * @return array|null
	 */
	public function getNativeExtTagConfig( string $tagName ): ?array {
		$nativeExtConfig = $this->getNativeExtensionsConfig();
		return $nativeExtConfig['nativeTags'][ strToLower( $tagName ) ] ?? null;
	}

	/**
	 * @param string $tagName Extension tag name
	 * @return ExtensionTag|null
	 *   Returns the implementation of the named extension, if there is one.
	 */
	public function getNativeExtTagImpl( string $tagName ): ?ExtensionTag {
		$tagConfig = $this->getNativeExtTagConfig( $tagName );
		return isset( $tagConfig['class'] ) ? new $tagConfig['class']() : null;
	}

	/**
	 * @return array
	 */
	public function getNativeExtDOMProcessors(): array {
		$nativeExtConfig = $this->getNativeExtensionsConfig();
		return $nativeExtConfig['domProcessors'];
	}

	/**
	 * @return array
	 */
	public function getNativeExtStyles(): array {
		$nativeExtConfig = $this->getNativeExtensionsConfig();
		return $nativeExtConfig['styles'];
	}
}
