<?php
declare(strict_types=1);

namespace RobertLemke\Plugin\Blog\Service;

/*
 * This file is part of the RobertLemke.Plugin.Blog package.
 *
 * (c) Robert Lemke
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\ThrowableStorageInterface;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\SymfonyMailer\Service\MailerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * A notification service
 *
 * @Flow\Scope("singleton")
 */
class NotificationService
{
    protected array $settings = [];

    #[Flow\Inject]
    protected ThrowableStorageInterface $throwableStorage;

    #[Flow\Inject]
    protected LoggerInterface $logger;

    #[Flow\Inject]
    protected ObjectManagerInterface $objectManager;

    public function injectSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    /**
     * Send a new notification that a comment has been created
     *
     */
    public function sendNewCommentNotification(NodeInterface $commentNode, NodeInterface $postNode): void
    {
        if ($this->settings['notifications']['to']['email'] === '') {
            return;
        }

        if (!class_exists(MailerService::class)) {
            $this->logger->info('The package "neos/symfonymailer" is required to send notifications!', LogEnvironment::fromMethodName(__METHOD__));

            return;
        }

        $mail = new Email();
        try {
            $mail
                ->addFrom(new Address($this->settings['notifications']['to']['email'], $this->settings['notifications']['to']['name']))
                ->addReplyTo(new Address($commentNode->getProperty('emailAddress'), $commentNode->getProperty('author')))
                ->subject('New comment on blog post "' . $postNode->getProperty('title') . '"' . ($commentNode->getProperty('spam') ? ' (SPAM)' : ''))
                ->addTo(new Address($this->settings['notifications']['to']['email'], $this->settings['notifications']['to']['name']))
                ->text($commentNode->getProperty('text'));

            $this->getMailerService()->getMailer()->send($mail);
        } catch (\Exception $e) {
            $message = $this->throwableStorage->logThrowable($e);
            $this->logger->error($message, LogEnvironment::fromMethodName(__METHOD__));
        }
    }

    private function getMailerService(): MailerService
    {
        return $this->objectManager->get(MailerService::class);
    }
}
