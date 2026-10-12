<?php

declare(strict_types=1);

namespace StraschekIo\BackendFeedback\Tests\Functional\Backend\ToolbarItems;

use PHPUnit\Framework\Attributes\Test;
use StraschekIo\BackendFeedback\Backend\ToolbarItems\PermissionFeedbackToolbarItem;
use StraschekIo\BackendFeedback\Tests\Functional\AbstractFeedbackTestCase;
use TYPO3\CMS\Backend\Toolbar\ToolbarItemsRegistry;

final class PermissionFeedbackToolbarItemTest extends AbstractFeedbackTestCase
{
    #[Test]
    public function toolbarItemIsRegistered(): void
    {
        $classNames = [];
        foreach ($this->get(ToolbarItemsRegistry::class)->getToolbarItems() as $toolbarItem) {
            $classNames[] = $toolbarItem::class;
        }

        self::assertContains(PermissionFeedbackToolbarItem::class, $classNames);
    }

    #[Test]
    public function buttonShowsTranslatedDefaultLabel(): void
    {
        $markup = $this->renderToolbarItem();

        self::assertStringContainsString('t3js-permission-feedback-open', $markup);
        self::assertStringContainsString('title="Report missing permission"', $markup);
        self::assertStringContainsString('data-modal-title="Report missing permission"', $markup);
        self::assertStringContainsString('<span class="toolbar-item-title">Report missing permission</span>', $markup);
    }

    #[Test]
    public function configuredLabelReplacesButtonTextAndModalTitle(): void
    {
        $this->configureExtension(['toolbarLabel' => 'Send feedback']);

        $markup = $this->renderToolbarItem();

        self::assertStringContainsString('data-modal-title="Send feedback"', $markup);
        self::assertStringContainsString('<span class="toolbar-item-title">Send feedback</span>', $markup);
        self::assertStringNotContainsString('Report missing permission', $markup);
    }

    private function renderToolbarItem(): string
    {
        $toolbarItem = $this->get(PermissionFeedbackToolbarItem::class);
        $toolbarItem->setRequest($this->createBackendRequest());

        return $toolbarItem->getItem();
    }
}
