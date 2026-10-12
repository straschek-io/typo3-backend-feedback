<?php

declare(strict_types=1);

namespace StraschekIo\BackendFeedback\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Resolves module, page and deep links from the values the toolbar script collected.
 */
final class FeedbackContextResolver
{
    /**
     * Main module of the file list in TYPO3 13, alias of it or of its main module from TYPO3 14 on.
     */
    private const FILE_MODULE_IDENTIFIER = 'file';

    public function __construct(
        private readonly ModuleProvider $moduleProvider,
        private readonly UriBuilder $uriBuilder,
        private readonly LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function resolve(ServerRequestInterface $request, array $values): FeedbackContext
    {
        $moduleIdentifier = trim((string)($values['module'] ?? ''));
        $pageId = (int)($values['pageId'] ?? 0);
        $endpoint = trim((string)($values['endpoint'] ?? ''));
        $isFileModule = $this->isFileModule($moduleIdentifier);
        // The stored folder outlives the file module, so it only counts there
        $fileIdentifier = $isFileModule ? trim((string)($values['fileIdentifier'] ?? '')) : '';

        // The "id" of the open view beats the stored module state
        parse_str((string)parse_url($endpoint, PHP_URL_QUERY), $endpointQuery);
        $endpointId = (string)($endpointQuery['id'] ?? '');
        if (str_contains($endpointId, ':')) {
            $fileIdentifier = $endpointId;
        } elseif (MathUtility::canBeInterpretedAsInteger($endpointId)) {
            $pageId = (int)$endpointId;
        }
        if ($isFileModule) {
            $pageId = 0;
        }

        $page = $pageId > 0 ? BackendUtility::getRecord('pages', $pageId) : null;
        $pageId = $page === null ? 0 : $pageId;

        return new FeedbackContext(
            $moduleIdentifier,
            $this->resolveModuleTitle($moduleIdentifier),
            $pageId,
            $page['title'] ?? '',
            $page === null ? '' : $this->resolvePagePath($pageId),
            $this->resolveBackendUrl($request, $endpoint, $moduleIdentifier, $pageId),
            $page === null ? '' : $this->resolveFrontendUrl($pageId),
            $fileIdentifier,
        );
    }

    private function isFileModule(string $moduleIdentifier): bool
    {
        if ($moduleIdentifier === ''
            || !$this->moduleProvider->isModuleRegistered($moduleIdentifier)
            || !$this->moduleProvider->isModuleRegistered(self::FILE_MODULE_IDENTIFIER)
        ) {
            return false;
        }
        $module = $this->moduleProvider->getModule($moduleIdentifier);
        $fileModuleIdentifier = $this->moduleProvider->getModule(self::FILE_MODULE_IDENTIFIER)?->getIdentifier();

        return $module?->getIdentifier() === $fileModuleIdentifier || $module?->getParentIdentifier() === $fileModuleIdentifier;
    }

    private function resolveModuleTitle(string $moduleIdentifier): string
    {
        if ($moduleIdentifier === '' || !$this->moduleProvider->isModuleRegistered($moduleIdentifier)) {
            return $moduleIdentifier;
        }
        $title = $this->getLanguageService()->sL($this->moduleProvider->getModule($moduleIdentifier)->getTitle());

        return $title === '' ? $moduleIdentifier : $title;
    }

    private function resolvePagePath(int $pageId): string
    {
        $titles = [];
        foreach (array_reverse(BackendUtility::BEgetRootLine($pageId)) as $rootLinePage) {
            if ((int)$rootLinePage['uid'] > 0) {
                $titles[] = $rootLinePage['title'];
            }
        }

        return implode(' / ', $titles);
    }

    /**
     * Prefers the exact view the user had open, without the session bound token.
     */
    private function resolveBackendUrl(ServerRequestInterface $request, string $endpoint, string $moduleIdentifier, int $pageId): string
    {
        $siteUrl = rtrim($request->getAttribute('normalizedParams')->getSiteUrl(), '/');
        $endpointPath = parse_url($endpoint, PHP_URL_PATH) ?? '';
        if (str_starts_with($endpointPath, '/typo3/')) {
            parse_str((string)parse_url($endpoint, PHP_URL_QUERY), $query);
            unset($query['token']);

            return $siteUrl . $endpointPath . ($query === [] ? '' : '?' . http_build_query($query));
        }
        if ($moduleIdentifier !== '' && $this->moduleProvider->isModuleRegistered($moduleIdentifier)) {
            $parameters = $pageId > 0 ? ['id' => $pageId] : [];

            return (string)$this->uriBuilder->buildUriFromRoute($moduleIdentifier, $parameters, UriBuilder::ABSOLUTE_URL);
        }

        return $siteUrl . '/typo3/';
    }

    private function resolveFrontendUrl(int $pageId): string
    {
        try {
            return (string)PreviewUriBuilder::create($pageId)->buildUri();
        } catch (\Throwable) {
            return '';
        }
    }

    private function getLanguageService(): LanguageService
    {
        return $this->languageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER']);
    }
}
