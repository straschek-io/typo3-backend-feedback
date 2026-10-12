<?php

declare(strict_types=1);

namespace StraschekIo\BackendFeedback\Service;

/**
 * Everything the mail reports besides the message itself.
 */
final class FeedbackContext
{
    public function __construct(
        public readonly string $moduleIdentifier,
        public readonly string $moduleTitle,
        public readonly int $pageId,
        public readonly string $pageTitle,
        public readonly string $pagePath,
        public readonly string $backendUrl,
        public readonly string $frontendUrl,
        public readonly string $fileIdentifier,
    ) {}
}
