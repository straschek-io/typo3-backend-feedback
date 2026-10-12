<?php

declare(strict_types=1);

namespace StraschekIo\BackendFeedback\Service;

use Psr\Http\Message\ServerRequestInterface;
use StraschekIo\BackendFeedback\Configuration\ExtensionSettings;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;

/**
 * Sends the feedback mail to the configured recipients.
 */
final class FeedbackMailer
{
    private const MAIL_CSS_FILE = 'EXT:backend_feedback/Resources/Private/Css/permission-feedback-mail.css';
    private const LANGUAGE_FILE = 'LLL:EXT:backend_feedback/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LanguageServiceFactory $languageServiceFactory,
        private readonly ExtensionSettings $settings,
    ) {}

    /**
     * @return Address[]
     */
    public function getRecipients(): array
    {
        $recipients = [];
        foreach ($this->settings->getRecipients() as $recipient) {
            if (GeneralUtility::validEmail($recipient)) {
                $recipients[] = new Address($recipient);
            }
        }

        return $recipients;
    }

    public function send(ServerRequestInterface $request, BackendUserAuthentication $reporter, FeedbackContext $context, string $message): void
    {
        $reporterName = trim((string)($reporter->user['realName'] ?? '')) ?: (string)$reporter->user['username'];
        $reporterEmail = (string)($reporter->user['email'] ?? '');
        $languageService = $this->languageServiceFactory->createFromUserPreferences($reporter);
        $subjectPrefix = $this->settings->getSubjectPrefix() ?: $languageService->sL(self::LANGUAGE_FILE . 'mail.subjectPrefix');

        $email = GeneralUtility::makeInstance(FluidEmail::class);
        $email
            ->setRequest($request)
            ->to(...$this->getRecipients())
            ->from(new Address(MailUtility::getSystemFromAddress(), MailUtility::getSystemFromName() ?? ''))
            ->subject(sprintf('%s %s: %s', $subjectPrefix, $reporterName, $this->summarize($message)))
            ->format(FluidEmail::FORMAT_BOTH)
            ->setTemplate('PermissionFeedback')
            ->assignMultiple([
                'reporterName' => $reporterName,
                'reporterUsername' => (string)$reporter->user['username'],
                'reporterEmail' => $reporterEmail,
                'reporterGroups' => implode(', ', array_column($reporter->userGroups, 'title')),
                'context' => $context,
                'message' => $message,
                'sentAt' => new \DateTimeImmutable(),
                'languageCode' => $languageService->getLocale()?->getLanguageCode() ?: 'en',
                'styles' => file_get_contents(GeneralUtility::getFileAbsFileName(self::MAIL_CSS_FILE)) ?: '',
            ]);
        if (GeneralUtility::validEmail($reporterEmail)) {
            $email->replyTo(new Address($reporterEmail, $reporterName));
        }

        $this->mailer->send($email);
    }

    private function summarize(string $message): string
    {
        $summary = trim(preg_replace('/\s+/', ' ', $message) ?? '');

        return mb_strlen($summary) > 60 ? mb_substr($summary, 0, 57) . '...' : $summary;
    }
}
