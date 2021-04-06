'use strict';

/**
 * Parsoid native extension that allows Parsoid to properly render portable infoboxes by delegating rendering
 * to the MediaWiki PHP parser.
 */

const {
	DOMUtils, DOMDataUtils, Promise, parseTokenContentsToDOM, WTUtils
} = module.parent.require('./extapi.js').versionCheck('^0.10.0');

/**
 * @typedef ParserState {Object}
 * @property {MWParserEnvironment} env
 * @property {Frame} frame
 */

/**
 * Inject a placeholder for each <infobox> into the DOM, to be handled at the post-processing stage.
 *
 * @param {ParserState} state parsing pipeline context
 * @param {string} content raw text content of the <infobox> tag
 * @param {KV} attributes attributes set on the <infobox> tag
 * @return {Promise<Document>}
 */
const toDOM = function(state, content, attributes) {
	return parseTokenContentsToDOM(state, [], '', '', {
		wrapperTag: 'aside',
		pipelineOpts: {
			extTag: 'infobox',
		},
	});
};

/**
 * DOM post-processor that operates on the complete HTML5 document constructed by Parsoid from the input content.
 */
class PostProcessingPortableInfoboxRenderer {

	/**
	 * Find portable infobox nodes within a given encapsulation wrapper
	 * @generator
	 * @param {Node} node within the encapsulation wrapper
	 * @yields {Node} portable infobox nodes within this encapsulation wrapper
	 */
	*findInfoboxNodesWithinEncapsulationWrapper(node) {
		if (DOMUtils.hasTypeOf(node, 'mw:Extension/infobox')) {
			yield node;
			return;
		}

		if (node.hasChildNodes()) {
			for (let child = node.firstChild; child !== null; child = child.nextSibling) {
				if (DOMUtils.isElt(child)) {
					// Recurse into this DOM subtree
					yield* this.findInfoboxNodesWithinEncapsulationWrapper(child);
				}
			}
		}
	}

	/**
	 * Determines if the given DOM node is an encapsulation wrapper node associated with a given transclusion.
	 * @param {Node|null} node - potential encapsulation wrapper node, or null
	 * @param {string} transclusionAboutId - Parsoid about-ID of the associated transclusion
	 * @return {boolean}
	 */
	isEncapsulationWrapperForSameTransclusion(node, transclusionAboutId) {
		return node !== null && WTUtils.hasParsoidAboutId(node) && node.getAttribute('about') === transclusionAboutId;
	}

	/**
	 * Extract all portable infobox nodes and associated template transclusions from the Parsoid DOM.
	 * @generator
	 * @param {Node} node
	 * @yields {Node[]|Node[][]} 2-tuples of template transclusions and infoboxes inside them
	 */
	*extractTranscludedInfoboxNodes(node) {
		let nextChild;
		for (let child = node.firstChild; child !== null; child = nextChild) {
			nextChild = child.nextSibling;

			if (!DOMUtils.isElt(child)) {
				continue;
			}

			// Since portable infoboxes can only render data when used via a template transclusion,
			// only look for portable infoboxes when a transclusion context exists.
			if (DOMUtils.hasTypeOf(child, 'mw:Transclusion')) {
				const infoboxNodes = [];
				const aboutId = child.getAttribute('about');
				let sibling = child;

				// Make sure to process all encapsulation wrappers associated with this transclusion,
				// since they may contain portable infoboxes or be portable infobox nodes themselves.
				while (this.isEncapsulationWrapperForSameTransclusion(sibling, aboutId)) {
					nextChild = sibling.nextSibling;

					for (const childInfoboxNode of this.findInfoboxNodesWithinEncapsulationWrapper(sibling)) {
						infoboxNodes.push(childInfoboxNode);
					}

					sibling = nextChild;
				}

				if (infoboxNodes.length > 0) {
					yield [child, infoboxNodes];
				}

				continue;
			}

			// Recurse into this DOM subtree
			if (child.hasChildNodes()) {
				yield* this.extractTranscludedInfoboxNodes(child);
			}
		}
	}

	/**
	 * Extract rendered portable infoboxes from the PHP parser's output, and mount them to the Parsoid document.
	 * @param {Node} node
	 * @param {Node[]} infoboxNodes
	 */
	attachParsedInfoboxHtml(node, infoboxNodes) {
		for (let child = node.firstChild; child !== null; child = child.nextSibling) {
			if (!DOMUtils.isElt(child)) {
				continue;
			}

			// Mount each portable infobox in the PHP parser's HTML to the associated (by index)
			// infobox node in the Parsoid document.
			if (child.classList.contains('portable-infobox')) {
				const origNode = infoboxNodes.shift();
				const imported = origNode.ownerDocument.importNode(child, true);

				origNode.appendChild(imported);
				continue;
			}

			// Recurse into this DOM subtree
			if (child.hasChildNodes()) {
				this.attachParsedInfoboxHtml(child, infoboxNodes);
			}
		}
	}

	/**
	 * Find portable infoboces associated with template transclusions in the document,
	 * and render them via the PHP parser
	 */
	run(node, env, options, atTopLevel) {
		if (!atTopLevel) {
			return;
		}

		const infoboxParseRequests = [];

		for (const infoboxTransclusion of this.extractTranscludedInfoboxNodes(node)) {
			const [transclusionStartNode, infoboxNodes] = infoboxTransclusion;

			const { parts } = DOMDataUtils.getDataMw(transclusionStartNode);
			const [templateInvocation] = parts;
			const { template } = templateInvocation;
			const { params } = template;

			const paramsAsText = Object.entries(params).reduce(
				(accum, [paramName, value]) => accum + `|${paramName}=${value.wt}`, ''
			);

			// Call the PHP parser to render this infobox for us
			const invocation = `{{${template.target.wt}${paramsAsText}}}`;
			const phpParseRequest = env.batcher.parse(env.page.name, invocation);

			const infoboxParseRequest = phpParseRequest.then((htmlContents) => {
				const docFromMw = DOMUtils.parseHTML(htmlContents);

				this.attachParsedInfoboxHtml(docFromMw.body, infoboxNodes);
			});

			infoboxParseRequests.push(infoboxParseRequest);
		}

		return Promise.all(infoboxParseRequests);
	}
}

module.exports = function() {
	this.config = {
		name: 'infobox',
		domProcessors: {
			wt2htmlPostProcessor: PostProcessingPortableInfoboxRenderer,
		},
		tags: [
			{
				name: 'infobox',
				toDOM,
			},
		],
	};
};
