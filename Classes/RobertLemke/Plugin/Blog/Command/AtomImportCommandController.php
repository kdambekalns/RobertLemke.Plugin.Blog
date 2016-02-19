<?php
namespace RobertLemke\Plugin\Blog\Command;

/*
 * This file is part of the RobertLemke.Plugin.Blog package.
 *
 * (c) Robert Lemke
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Eel\FlowQuery\FlowQuery;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cli\CommandController;
use TYPO3\Neos\Domain\Service\ContentContextFactory;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Model\NodeTemplate;
use TYPO3\TYPO3CR\Domain\Service\NodeTypeManager;

/**
 * BlogCommand command controller for the RobertLemke.Plugin.Blog package
 *
 * @Flow\Scope("singleton")
 */
class AtomImportCommandController extends CommandController
{

    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @Flow\Inject
     * @var ContentContextFactory
     */
    protected $contentContextFactory;

    /**
     * Imports atom data into the blog
     *
     * @param string $workspace The workspace to work in
     * @param string $targetNode The target node (expressed as a FlowQuery find condition)
     * @param string $atomFile The atom file to import
     * @return void
     */
    public function migrateCommand($workspace, $targetNode, $atomFile)
    {
        if (!class_exists(\SimplePie::class)) {
            $this->outputLine('The Atom import needs simplepie/simplepie, which you can install using composer.');
            $this->quit(1);
        }

        $context = $this->contentContextFactory->create(['workspaceName' => $workspace]);
        $q = new FlowQuery([$context->getRootNode()]);
        $blogNode = $q->find($targetNode)->get(0);
        if (!($blogNode instanceof NodeInterface)) {
            $this->outputLine('<error>Target node not found.</error>');
            $this->quit(1);
        }

        $parser = new \SimplePie();
        $parser->enable_order_by_date();
        $parser->enable_cache(false);

        $parser->set_raw_data(file_get_contents($atomFile));
        $parser->strip_attributes();
        $parser->strip_htmltags(array_merge($parser->strip_htmltags, array('span')));
        $parser->init();
        $items = $parser->get_items();

        $comments = array();
        /** @var $item \SimplePie_Item */
        foreach ($items as $item) {
            $categories = $item->get_categories();

            if (!is_array($categories)) {
                continue;
            }

            /** @var $category \SimplePie_Category */
            foreach ($categories as $category) {
                if ($category->get_term() === 'http://schemas.google.com/blogger/2008/kind#comment') {
                    $inReplyTo = current($item->get_item_tags('http://purl.org/syndication/thread/1.0', 'in-reply-to'));
                    $inReplyTo = current($inReplyTo['attribs']);
                    $comments[$inReplyTo['ref']][$item->get_date('U')] = $item;
                }
            }
        }

        $textNodeType = $this->nodeTypeManager->getNodeType('TYPO3.Neos.NodeTypes:Text');
        $commentNodeType = $this->nodeTypeManager->getNodeType('RobertLemke.Plugin.Blog:Comment');
        $counter = 0;
        foreach ($parser->get_items() as $item) {
            $categories = $item->get_categories();
            if (!is_array($categories)) {
                continue;
            }

            $tags = array();
            $itemIsPost = false;
            foreach ($categories as $category) {
                if ($category->get_term() === 'http://schemas.google.com/blogger/2008/kind#post') {
                    $itemIsPost = true;
                }
                if ($category->get_scheme() === 'http://www.blogger.com/atom/ns#') {
                    $tags[] = $category->get_term();
                }
            }
            if (!$itemIsPost) {
                continue;
            }

            $nodeTemplate = new NodeTemplate();
            $nodeTemplate->setNodeType($this->nodeTypeManager->getNodeType('RobertLemke.Plugin.Blog:Post'));
            $nodeTemplate->setProperty('title', $item->get_title());
            $nodeTemplate->setProperty('author', $item->get_author()->get_name());
            $published = new \DateTime();
            $published->setTimestamp($item->get_date('U'));
            $nodeTemplate->setProperty('datePublished', $published);
            $nodeTemplate->setProperty('tags', implode(',', $tags));

            $slug = strtolower(str_replace(array(' ', ',', ':', 'ü', 'à', 'é', '?', '!', '[', ']', '.', '\''), array('-', '', '', 'u', 'a', 'e', '', '', '', '', '-', ''), $item->get_title()));
            $postNode = $blogNode->createNodeFromTemplate($nodeTemplate, $slug);
            $postNode->getNode('main')->createNode(uniqid('node'), $textNodeType)->setProperty('text', $item->get_content());

            $postComments = isset($comments[$item->get_id()]) ? $comments[$item->get_id()] : array();
            if ($postComments !== array()) {
                $commentsNode = $postNode->getNode('comments');
                /** @var $postComment \SimplePie_Item */
                foreach ($postComments as $postComment) {
                    $commentNode = $commentsNode->createNode(uniqid('comment-'), $commentNodeType);
                    $commentNode->setProperty('author', html_entity_decode($postComment->get_author()->get_name(), ENT_QUOTES, 'utf-8'));
                    $commentNode->setProperty('emailAddress', $postComment->get_author()->get_email());
                    $commentNode->setProperty('uri', $postComment->get_author()->get_link());
                    $commentNode->setProperty('datePublished', new \DateTime($postComment->get_date()));
                    $commentText = preg_replace('/<br[ \/]*>/i', chr(10), $postComment->get_content());
                    $commentText = html_entity_decode($commentText, ENT_QUOTES, 'utf-8');
                    $commentNode->setProperty('text', $commentText);
                    $commentNode->setProperty('spam', false);
                    $previousCommentNode = $commentNode;
                    if ($previousCommentNode !== null) {
                        $commentNode->moveAfter($previousCommentNode);
                    }
                }
            }

            $counter++;
            $this->outputLine($postNode->getProperty('title') . ' by ' . $postNode->getProperty('author'));
        }

        $this->outputLine('Imported %s blog posts.', array($counter));
    }

}
