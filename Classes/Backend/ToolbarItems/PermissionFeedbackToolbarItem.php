<?php

declare(strict_types=1);

namespace StraschekIo\BackendFeedback\Backend\ToolbarItems;

use Psr\Http\Message\ServerRequestInterface;
use StraschekIo\BackendFeedback\Configuration\ExtensionSettings;
use TYPO3\CMS\Backend\Toolbar\RequestAwareToolbarItemInterface;
use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Page\PageRenderer;

/**
 * Toolbar button that opens the permission feedback form in every backend view.
 */
final class PermissionFeedbackToolbarItem implements ToolbarItemInterface, RequestAwareToolbarItemInterface
{
    private const LANGUAGE_FILE = 'EXT:backend_feedback/Resources/Private/Language/locallang.xlf';
    private const STYLESHEET_FILE = 'EXT:backend_feedback/Resources/Public/Css/permission-feedback.css';

    private ServerRequestInterface $request;

    public function __construct(
        private readonly BackendViewFactory $backendViewFactory,
        private readonly PageRenderer $pageRenderer,
        private readonly LanguageServiceFactory $languageServiceFactory,
        private readonly ExtensionSettings $settings,
    ) {}

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function checkAccess(): bool
    {
        return true;
    }

    public function getItem(): string
    {
        $this->pageRenderer->addInlineLanguageLabelFile(self::LANGUAGE_FILE);
        $this->pageRenderer->addCssFile(self::STYLESHEET_FILE);
        $languageService = $this->languageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER']);
        $label = $this->settings->getToolbarLabel();
        $view = $this->backendViewFactory->create($this->request, ['straschek-io/typo3-backend-feedback']);
        $view->assignMultiple([
            'label' => $label ?: $languageService->sL('LLL:' . self::LANGUAGE_FILE . ':toolbar.title'),
            'modalTitle' => $label ?: $languageService->sL('LLL:' . self::LANGUAGE_FILE . ':modal.title'),
        ]);

        return $view->render('ToolbarItems/PermissionFeedbackToolbarItem');
    }

    public function hasDropDown(): bool
    {
        return false;
    }

    public function getDropDown(): string
    {
        return '';
    }

    public function getAdditionalAttributes(): array
    {
        return [];
    }

    public function getIndex(): int
    {
        return 65;
    }
}
