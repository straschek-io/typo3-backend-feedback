<?php

declare(strict_types=1);

namespace StraschekIo\BackendFeedback\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use StraschekIo\BackendFeedback\Service\FeedbackContextResolver;
use StraschekIo\BackendFeedback\Service\FeedbackMailer;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * Renders the feedback form for the modal and sends the mail.
 */
final class PermissionFeedbackController
{
    private const LANGUAGE_FILE = 'LLL:EXT:backend_feedback/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private readonly BackendViewFactory $backendViewFactory,
        private readonly FeedbackContextResolver $contextResolver,
        private readonly FeedbackMailer $mailer,
        private readonly LanguageServiceFactory $languageServiceFactory,
        private readonly LoggerInterface $logger,
    ) {}

    public function formAction(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->contextResolver->resolve($request, $request->getQueryParams());
        $view = $this->backendViewFactory->create($request, ['straschek-io/typo3-backend-feedback']);
        $view->assignMultiple([
            'context' => $context,
            'values' => $request->getQueryParams(),
            'recipientsConfigured' => $this->mailer->getRecipients() !== [],
        ]);

        return new HtmlResponse($view->render('Feedback/Form'));
    }

    public function sendAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array)$request->getParsedBody();
        $message = trim((string)($body['message'] ?? ''));
        if ($message === '') {
            return $this->jsonError('error.messageMissing');
        }
        if ($this->mailer->getRecipients() === []) {
            $this->logger->error('Permission feedback not sent: no recipients configured in TYPO3_CONF_VARS/EXTENSIONS/backend_feedback/recipients');

            return $this->jsonError('error.noRecipients');
        }

        try {
            $context = $this->contextResolver->resolve($request, $body);
            $this->mailer->send($request, $GLOBALS['BE_USER'], $context, $message);
        } catch (\Throwable $exception) {
            $this->logger->error('Permission feedback mail failed: {message}', ['message' => $exception->getMessage(), 'exception' => $exception]);

            return $this->jsonError('error.sendFailed');
        }

        return new JsonResponse([
            'success' => true,
            'title' => $this->translate('notification.sent.title'),
            'message' => $this->translate('notification.sent.message'),
        ]);
    }

    private function jsonError(string $labelKey): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'title' => $this->translate('notification.failed.title'),
            'message' => $this->translate($labelKey),
        ]);
    }

    private function translate(string $key): string
    {
        return $this->getLanguageService()->sL(self::LANGUAGE_FILE . $key);
    }

    private function getLanguageService(): LanguageService
    {
        return $this->languageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER']);
    }
}
