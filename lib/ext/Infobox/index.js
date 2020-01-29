'use strict';
/**
 * Parsoid native extension that allows Parsoid to properly render portable infoboxes by delegating rendering
 * to the MediaWiki PHP parser.
 */

const {
	DOMUtils, DOMDataUtils, Promise, parseTokenContentsToDOM
} = module.parent.require('./extapi.js').versionCheck('^0.10.0');

/**
 * Inject a placeholder for each <infobox> into the DOM, to be handled at the post-processing stage.
 *
 * @param state parsing pipeline context
 * @param content raw text content of the <infobox> tag
 * @param attributes attributes set on the <infobox> tag
 * @return {*|PromiseLike<Document>|Promise<Document>}
 */
const toDOM = function (state, content, attributes) {
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

		const actualInfoboxNodes = [];

		potentialInfoboxNodes.forEach(node => {
			// Only handle actual infoboxes and not any <aside> tags
			if (DOMUtils.hasTypeOf(node, 'mw:Extension/infobox')) {
				// Only handle <infobox> tags rendered via template transclusions
				if (DOMUtils.hasTypeOf(node, 'mw:Transclusion')) {
					// Found an <infobox> included on the page as a template - we can handle it
					actualInfoboxNodes.push(node);
				}
			}
		});

		const infoboxParseRequests = [];

		/**
		 * Create a wikitext template invocation and push it to the queue of infoboxes to be parsed
		 * @param template template transclusion metadata from Parsoid
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
		actualInfoboxNodes.forEach(node => {
			const metaInfo = DOMDataUtils.getDataMw(node);

			metaInfo.parts.forEach(pushWikitextTemplateInvocation);
		});

		// Parse all infoboxes in parallel and inject their contents back into the document
		return Promise.all(infoboxParseRequests)
			.then(htmlContents => {
				htmlContents.forEach((html, i) => {
					const docFromMw = DOMUtils.parseHTML(html);

					// only import the infobox and discard other template contents to avoid duplication
					const portableInfobox = docFromMw.querySelector('.portable-infobox');
					const imported = document.importNode(portableInfobox, true);

					// add the rendered infobox to the output document in the proper location
					actualInfoboxNodes[i].appendChild(imported);
				})
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
		styles: [
			'ext.portableInfobox.css',
			'ext.portableInfobox.runtime.css',

			// We have no way of detecting here if the current wiki has Europa theme enabled, so always load its styles
			// If the Europa theme is disabled, the infobox HTML will not include Europa classes, so the styles won't have an effect
			'ext.portableInfobox.europaTheme.css',
			'ext.portableInfobox.europaTheme.runtime.css',
		],
	};
};
