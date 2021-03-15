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
	run(node, env, options, atTopLevel) {
		if (!atTopLevel) {
			return;
		}

		const document = node.ownerDocument;
		const potentialInfoboxNodes = Array.from(document.getElementsByTagName('aside'));

		// array of [transclusionStartNode,infoboxNode] 2-tuples
		const infoboxTransclusions = [];

		potentialInfoboxNodes.forEach((node) => {
			// Only handle actual infoboxes and not any <aside> tags
			if (!DOMUtils.hasTypeOf(node, 'mw:Extension/infobox')) {
				return;
			}

			// If the current node is part of a template transclusion, find the starting node of the transclusion.
			// Typically this will be the same node as the infobox itself, but this may not be the case if there is
			// additional content in the template before the infobox, e.g. a category link.
			const transclusionStartNode = WTUtils.findFirstEncapsulationWrapperNode(node);

			if (transclusionStartNode && DOMUtils.hasTypeOf(transclusionStartNode, 'mw:Transclusion')) {
				// Found an <infobox> included on the page as a template - we can handle it
				infoboxTransclusions.push([transclusionStartNode, node]);
			}
		});

		const infoboxParseRequests = [];

		/**
		 * Create a wikitext template invocation and push it to the queue of infoboxes to be parsed
		 * @param {Object} template template transclusion metadata from Parsoid
		 */
		const pushWikitextTemplateInvocation = ({ template }) => {
			// build wikitext for transclusion params
			const paramsAsText = Object.entries(template.params).reduce(
				(accum, [paramName, value]) => accum + `|${paramName}=${value.wt}`, ''
			);

			const invocation = `{{${template.target.wt}${paramsAsText}}}`;
			const phpParseRequest = env.batcher.parse(env.page.name, invocation);

			infoboxParseRequests.push(phpParseRequest);
		};

		// Process each <infobox> template transclusion and issue a request to render them via the MW PHP parser
		infoboxTransclusions.forEach(([transclusionStartNode]) => {
			const { parts } = DOMDataUtils.getDataMw(transclusionStartNode);
			const [templateInvocation] = parts;

			pushWikitextTemplateInvocation(templateInvocation);
		});

		// Parse all infoboxes in parallel and inject their contents back into the document
		return Promise.all(infoboxParseRequests)
			.then((htmlContents) => {
				htmlContents.forEach((html, i) => {
					const docFromMw = DOMUtils.parseHTML(html);

					// only import the infobox and discard other template contents to avoid duplication
					let portableInfobox = docFromMw.querySelector('.portable-infobox');

					if (portableInfobox === undefined) {
						// if infobox is empty and does not render anything, lets create a placeholder for it
						portableInfobox = document.createElement('aside');
						portableInfobox.setAttribute('class', 'portable-infobox portable-infobox-placeholder');
					}

					const imported = document.importNode(portableInfobox, true);
					const [,infoboxNode] = infoboxTransclusions[i];

					// add the rendered infobox to the output document in the proper location
					infoboxNode.appendChild(imported);
				});
			});
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
