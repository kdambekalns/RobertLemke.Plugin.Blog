<?php
namespace RobertLemke\Plugin\Blog\ViewHelpers;

/*                                                                         *
 * This script belongs to the TYPO3 Flow package "RobertLemke.Plugin.Blog" *
 *                                                                         *
 * It is free software; you can redistribute it and/or modify it under     *
 * the terms of the MIT License.                                           *
 *                                                                         *
 * The TYPO3 project - inspiring people to share!                          *
 *                                                                         */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;

/**
 * This view helper crops the text of a blog post in a meaningful way.
 *
 * @api
 */
class TeaserViewHelper extends \TYPO3\Fluid\Core\ViewHelper\AbstractViewHelper {

	
	/**
	 * NOTE: This property has been introduced via code migration to ensure backwards-compatibility.
	 * @see AbstractViewHelper::isOutputEscapingEnabled()
	 * @var boolean
	 */
	protected $escapeOutput = FALSE;

	/**
	 * @Flow\Inject
	 * @var \RobertLemke\Plugin\Blog\Service\ContentService
	 */
	protected $contentService;

	/**
	 * Render a teaser
	 *
	 * @param \TYPO3\TYPO3CR\Domain\Model\NodeInterface $node
	 * @return string cropped text
	 */
	public function render(NodeInterface $node) {
		return $this->contentService->renderTeaser($node);
	}
}
