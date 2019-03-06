<?php

namespace Parsoid\Config;

use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use ParserOptions;
// use Parsoid\Config\PageConfig;
use Title;

/**
 * Page-level configuration interface for Parsoid
 *
 * @todo This belongs in MediaWiki, not Parsoid. We'll move it there when we
 *  get to the point of integrating the two.
 * @todo We should probably deprecate ParserOptions somehow, using a version of
 *  this directly instead.
 */
class MediaWikiPageConfig extends PageConfig {

	/** @var Title */
	private $title;

	/** @var ParserOptions */
	private $parserOptions;

	/** @var RevisionRecord|null */
	private $revision;

	/**
	 * @param Title $title Title being parsed
	 * @param ParserOptions $parserOptions
	 * @param RevisionRecord|null $revision
	 */
	public function __construct(
		Title $title, ParserOptions $parserOptions, RevisionRecord $revision = null
	) {
		$this->title = $title;
		$this->parserOptions = $parserOptions;
		$this->revision = $revision;
	}

	public function hasLintableContentModel(): bool {
		// @todo Check just the main slot, or all slots, or what?
		$content = $this->getRevisionContent( SlotRecord::MAIN );
		return $content && (
			$content['model'] === CONTENT_MODEL_WIKITEXT || $content['model'] === 'proofread-page'
		);
	}

	/** @inheritDoc */
	public function getTitle(): string {
		return $this->title->getPrefixedText();
	}

	/** @inheritDoc */
	public function getNs(): int {
		return $this->title->getNamespace();
	}

	/** @inheritDoc */
	public function getPageId(): int {
		return $this->title->getArticleID();
	}

	/** @inheritDoc */
	public function getPageLanguage(): string {
		return $this->title->getPageLanguage()->getCode();
	}

	/** @inheritDoc */
	public function getPageLanguageDir(): string {
		return $this->title->getPageLanguage()->getDir();
	}

	private function getRevision(): ?RevisionRecord {
		if ( $this->revision === null ) {
			$this->revision = false;
			$rev = call_user_func(
				$this->parserOptionsOptions->getCurrentRevisionCallback(), $this->title, false
			);
			if ( $rev instanceof RevisionRecord ) {
				$this->revision = $rev;
			} elseif ( $rev instanceof \Revision ) {
				$this->revision = $rev->getRevisionRecord();
			}
		}
		return $this->revision ?: null;
	}

	/** @inheritDoc */
	public function getRevisionId(): ?int {
		$rev = $this->getRevision();
		return $rev ? $rev->getId() : null;
	}

	/** @inheritDoc */
	public function getParentRevisionId(): ?int {
		$rev = $this->getRevision();
		return $rev ? $rev->getParentId() : null;
	}

	/** @inheritDoc */
	public function getRevisionTimestamp(): string {
		$rev = $this->getRevision();
		return $rev ? $rev->getTimestamp() : null;
	}

	/** @inheritDoc */
	public function getRevisionUser(): ?string {
		$rev = $this->getRevision();
		$user = $rev ? $rev->getUser() : null;
		return $user ? $user->getName() : null;
	}

	/** @inheritDoc */
	public function getRevisionUserId(): ?int {
		$rev = $this->getRevision();
		$user = $rev ? $rev->getUser() : null;
		return $user ? $user->getId() : null;
	}

	/** @inheritDoc */
	public function getRevisionSha1(): ?string {
		$rev = $this->getRevision();
		return $rev ? $rev->getSha1() : null;
	}

	/** @inheritDoc */
	public function getRevisionSize(): ?int {
		$rev = $this->getRevision();
		return $rev ? $rev->getSize() : null;
	}

	/** @inheritDoc */
	public function getRevisionSlotRoles(): array {
		$rev = $this->getRevision();
		return $rev ? $rev->getSlotRoles() : [];
	}

	/** @inheritDoc */
	public function getRevisionContent( string $role ): ?array {
		$rev = $this->getRevision();
		try {
			$content = $rev ? $rev->getContent( $role ) : null;
		} catch ( RevisionAccessException $ex ) {
			$content = null;
		}
		return $content ? [
			'model' => $content->getModel(),
			'format' => $content->getDefaultFormat(),
			'text' => $content->serialize(),
		] : null;
	}

}