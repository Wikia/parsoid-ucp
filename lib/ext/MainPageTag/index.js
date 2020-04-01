'use strict';

const ParsoidExtApi = module.parent.require('./extapi.js').versionCheck('^0.10.0');

const { DOMDataUtils, DOMUtils, Promise } = ParsoidExtApi;

/**
 * @typedef ParserState {Object}
 * @property {MWParserEnvironment} env
 */

/**
 * Render a blank <mainpage-leftcolumn-start /> tag - contents will be added in post processing
 * @param {ParserState} state
 * @return {Promise<Document>}
 */
function renderMainPageLeftColumnStart(state) {
	const doc = state.env.createDocument();

	const lcsContainer = doc.createElement('div');
	lcsContainer.classList.add('lcs-container');

	const leftColumnStart = doc.createElement('div');
	leftColumnStart.classList.add('main-page-tag-lcs');
	leftColumnStart.appendChild(lcsContainer);

	doc.body.appendChild(leftColumnStart);

	return Promise.resolve(doc);
}

/**
 * Render a blank <mainpage-rightcolumn-start /> tag - contents will be added in post processing
 * @param {ParserState} state
 * @return {Promise<Document>}
 */
function renderMainPageRightColumnStart(state) {
	const doc = state.env.createDocument();

	const rcsContainer = doc.createElement('div');
	rcsContainer.classList.add('rcs-container');

	const rightColumnStart = doc.createElement('div');
	rightColumnStart.classList.add('main-page-tag-rcs');
	rightColumnStart.appendChild(rcsContainer);

	doc.body.appendChild(rightColumnStart);

	return Promise.resolve(doc);
}

/**
 * Render a placeholder node for <mainpage-endcolumn /> tag - will be removed during post-processing
 * @param {ParserState} state
 * @return {Promise<Document>}
 */
function renderMainPageEndColumnPlaceholder(state) {
	const doc = state.env.createDocument();

	const placeholder = doc.createElement('div');
	doc.body.appendChild(placeholder);

	return Promise.resolve(doc);
}

class MainPageTagDomPostProcessor {
	/**
	 * wt2html DOM post processor that shifts content to the proper column when main page tag columns are present.
	 *
	 * @param {HTMLElement} body
	 * @param {MWParserEnvironment} env
	 * @param {Object} options
	 * @param {boolean} atTopLevel
	 */
	run(body, env, options, atTopLevel) {
		if (!atTopLevel) {
			return;
		}

		/**
		 * @typedef {Object} MainPageTagProcessorState
		 * @property {?Node} leftColumnTag
		 * @property {?Node} rightColumnTag
		 * @property {boolean} leftColumnTagClosed
		 * @property {boolean} rightColumnTagClosed
		 */

		const state = {
			leftColumnTag: null,
			rightColumnTag: null,
			leftColumnTagClosed: false,
			rightColumnTagClosed: false,
		};

		// Process the content and move elements to the appropriate columns
		this.process(body, state);

		if (state.leftColumnTag) {
			DOMUtils.visitDOM(state.leftColumnTag, this.preserveNowikis, env);
		}

		if (state.rightColumnTag) {
			DOMUtils.visitDOM(state.rightColumnTag, this.preserveNowikis, env);
		}
	}

	/**
	 * Hack: Preserve <nowiki> tags present in main page column content
	 * by assigning them a fake about-ID so that they do not get stripped during cleanup.
	 *
	 * @param {HTMLElement} node
	 * @param {MWParserEnvironment} env
	 */
	preserveNowikis(node, env) {
		if (DOMUtils.matchTypeOf(node, /^mw:Nowiki$/)) {
			const aboutId = env.newAboutId();
			node.setAttribute('about', aboutId);
		}
	}

	/**
	 * Close a column tag if it was open, and shift its DOM source range to encompass the entire wikitext until its closing tag
	 * @param {Node} node
	 * @param {boolean} wasClosed
	 * @param {Node} closingTag
	 * @return {boolean} whether we closed the node
	 */
	closeColumnTagIfOpen(node, wasClosed, closingTag) {
		if (!node || wasClosed) {
			return wasClosed; // node wasn't open or we already closed it
		}

		const { dsr } = DOMDataUtils.getDataParsoid(closingTag);
		const [, closeTagEnd] = dsr;

		const columnDataParsoid = DOMDataUtils.getDataParsoid(node);
		const [start, , openWidth, closeWidth] = columnDataParsoid.dsr;

		columnDataParsoid.dsr = [start, closeTagEnd, openWidth, closeWidth];

		return true;
	}

	/**
	 * Recursively process an HTML node and move children to main page column tags as appropriate
	 * @param {Node} node
	 * @param {MainPageTagProcessorState} state
	 */
	process(node, state) {
		let child = node.firstChild;

		while (child !== null) {
			const nextSibling = child.nextSibling;

			if (DOMUtils.matchTypeOf(child, /^mw:Extension\/mainpage-leftcolumn-start$/)) {
				// Found left column tag
				state.leftColumnTag = child;
				const leftColumnClass = state.rightColumnTag ? 'main-page-tag-lcs-collapsed' : 'main-page-tag-lcs-exploded';
				state.leftColumnTag.classList.add(leftColumnClass);

				// Close any open right column tags
				state.rightColumnTagClosed = this.closeColumnTagIfOpen(state.rightColumnTag, state.rightColumnTagClosed, child);
			} else if (DOMUtils.matchTypeOf(child, /^mw:Extension\/mainpage-rightcolumn-start$/)) {
				// Found right column tag
				state.rightColumnTag = child;

				// Close any open left column tags
				state.leftColumnTagClosed = this.closeColumnTagIfOpen(state.leftColumnTag, state.leftColumnTagClosed, child);
			} else if (DOMUtils.matchTypeOf(child, /^mw:Extension\/mainpage-endcolumn$/)) {
				// Found end column tag - close any open tags
				state.leftColumnTagClosed = this.closeColumnTagIfOpen(state.leftColumnTag, state.leftColumnTagClosed, child);
				state.rightColumnTagClosed = this.closeColumnTagIfOpen(state.rightColumnTag, state.rightColumnTagClosed, child);

				// Remove end column placeholder
				child.remove();
			} else if (state.leftColumnTag && !state.leftColumnTagClosed) {
				// there's an open left column - shift content into it
				state.leftColumnTag.firstChild.appendChild(child);
			} else if (state.rightColumnTag && !state.rightColumnTagClosed) {
				// there's an open right column - shift content into it
				state.rightColumnTag.firstChild.appendChild(child);
			} else if (DOMUtils.isElt(child)) {
				// no columns are open - descend recursively into child node to search for columns
				this.process(child, state);
			}

			child = nextSibling;
		}
	}
}

const serialHandler = {
	/**
	 * Serialize a main page tag HTML node back to wikitext
	 *
	 * @param {Node} node
	 * @param {SerializerState} state
	 * @return {Promise<string>}
	 */
	handle: Promise.async(function *(node, state) {
		const container = node.firstChild;

		// Stash child metadata in the DOM so that it is available for the serializer
		DOMDataUtils.visitAndStoreDataAttribs(container);

		const [startTag, content] = yield Promise.all([
			state.serializer.serializeExtensionStartTag(node, state),
			state.serializer.serializeHTML({ env: state.env }, container.innerHTML),
		]);

		return startTag + content + '<mainpage-endcolumn />';
	}),
};

/**
 * Modify metadata applied to main page tag extension nodes
 *
 * @param {MWParserEnvironment} env
 * @param {Object} argDict - metadata to be associated with this extension node
 */
function modifyArgDict(env, argDict) {
	delete argDict.body; // always treat these as self-closing tags
}

module.exports = function() {
	this.config = {
		name: 'MainPageTag',
		domProcessors: {
			wt2htmlPostProcessor: MainPageTagDomPostProcessor
		},
		tags: [
			{
				name: 'mainpage-leftcolumn-start',
				toDOM: renderMainPageLeftColumnStart,
				serialHandler,
				modifyArgDict,
			},
			{
				name: 'mainpage-rightcolumn-start',
				toDOM: renderMainPageRightColumnStart,
				serialHandler,
				modifyArgDict,
			},
			{
				name: 'mainpage-endcolumn',
				toDOM: renderMainPageEndColumnPlaceholder,
			}
		],
		styles: [
			'ext.fandom.mainPageTag.css',
		],
	};
};
