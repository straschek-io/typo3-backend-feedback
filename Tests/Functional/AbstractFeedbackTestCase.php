<?php

declare(strict_types=1);

namespace StraschekIo\BackendFeedback\Tests\Functional;

use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class AbstractFeedbackTestCase extends FunctionalTestCase
{
    protected const BACKEND_URL = 'https://example.org/typo3/';

    protected array $coreExtensionsToLoad = ['filelist'];

    protected array $testExtensionsToLoad = ['straschek-io/typo3-backend-feedback'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/backend_user_and_page.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    /**
     * @param array<string, string> $queryParams
     * @param array<string, string> $parsedBody
     */
    protected function createBackendRequest(string $method = 'GET', array $queryParams = [], array $parsedBody = []): ServerRequest
    {
        $serverParams = [
            'HTTP_HOST' => 'example.org',
            'HTTPS' => 'on',
            'REQUEST_URI' => '/typo3/',
            'SCRIPT_NAME' => '/index.php',
            'SCRIPT_FILENAME' => $this->instancePath . '/index.php',
        ];
        $request = (new ServerRequest(self::BACKEND_URL, $method, 'php://input', [], $serverParams))
            ->withQueryParams($queryParams)
            ->withParsedBody($parsedBody)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $request = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));
        $GLOBALS['TYPO3_REQUEST'] = $request;

        return $request;
    }

    /**
     * @param array<string, string> $settings
     */
    protected function configureExtension(array $settings): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend_feedback'] = $settings;
    }
}
