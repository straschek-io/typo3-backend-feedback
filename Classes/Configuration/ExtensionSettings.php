<?php

declare(strict_types=1);

namespace StraschekIo\BackendFeedback\Configuration;

/**
 * Extension configuration, read from $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'] on every call
 * so that overrides from additional configuration or the environment apply.
 */
final class ExtensionSettings
{
    public const EXTENSION_KEY = 'backend_feedback';

    /**
     * @return list<string>
     */
    public function getRecipients(): array
    {
        $recipients = [];
        foreach (explode(',', $this->getString('recipients')) as $recipient) {
            $recipient = trim($recipient);
            if ($recipient !== '') {
                $recipients[] = $recipient;
            }
        }

        return $recipients;
    }

    /**
     * Empty means: use the translated default label.
     */
    public function getToolbarLabel(): string
    {
        return $this->getString('toolbarLabel');
    }

    /**
     * Empty means: use the translated default prefix.
     */
    public function getSubjectPrefix(): string
    {
        return $this->getString('subjectPrefix');
    }

    private function getString(string $name): string
    {
        $settings = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][self::EXTENSION_KEY] ?? [];
        $value = is_array($settings) ? ($settings[$name] ?? '') : '';

        return is_scalar($value) ? trim((string)$value) : '';
    }
}
