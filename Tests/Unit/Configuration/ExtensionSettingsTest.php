<?php

declare(strict_types=1);

namespace StraschekIo\BackendFeedback\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use StraschekIo\BackendFeedback\Configuration\ExtensionSettings;

final class ExtensionSettingsTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']);
    }

    #[Test]
    public function unconfiguredExtensionYieldsEmptyValues(): void
    {
        $settings = new ExtensionSettings();

        self::assertSame([], $settings->getRecipients());
        self::assertSame('', $settings->getToolbarLabel());
        self::assertSame('', $settings->getSubjectPrefix());
    }

    #[Test]
    public function recipientsAreSplitAndTrimmed(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend_feedback']['recipients'] = ' admin@example.org, ,support@example.org ';

        self::assertSame(['admin@example.org', 'support@example.org'], (new ExtensionSettings())->getRecipients());
    }

    #[Test]
    public function labelAndPrefixAreTrimmed(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend_feedback'] = [
            'toolbarLabel' => ' Send feedback ',
            'subjectPrefix' => ' [Feedback] ',
        ];
        $settings = new ExtensionSettings();

        self::assertSame('Send feedback', $settings->getToolbarLabel());
        self::assertSame('[Feedback]', $settings->getSubjectPrefix());
    }

    #[Test]
    public function screenshotIsEnabledUnlessSwitchedOff(): void
    {
        $settings = new ExtensionSettings();
        self::assertTrue($settings->isScreenshotEnabled());

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend_feedback']['screenshot'] = '1';
        self::assertTrue($settings->isScreenshotEnabled());

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend_feedback']['screenshot'] = '0';
        self::assertFalse($settings->isScreenshotEnabled());
    }

    #[Test]
    public function configurationChangedAfterConstructionIsRead(): void
    {
        $settings = new ExtensionSettings();
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend_feedback']['subjectPrefix'] = '[Later]';

        self::assertSame('[Later]', $settings->getSubjectPrefix());
    }
}
