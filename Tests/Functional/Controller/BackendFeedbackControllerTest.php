<?php

declare(strict_types=1);

namespace StraschekIo\BackendFeedback\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use StraschekIo\BackendFeedback\Controller\BackendFeedbackController;
use StraschekIo\BackendFeedback\Tests\Functional\AbstractFeedbackTestCase;
use TYPO3\CMS\Core\Http\UploadedFile;

final class BackendFeedbackControllerTest extends AbstractFeedbackTestCase
{
    private const PAGE_VIEW = [
        'module' => 'web_layout',
        'pageId' => '2',
        'endpoint' => '/typo3/module/web/layout?token=secret&id=1&language=0',
        'fileIdentifier' => '',
    ];

    private const FOLDER_VIEW = [
        'module' => 'media_management',
        'pageId' => '1',
        'endpoint' => '/typo3/module/file/list?token=secret&id=1%3A%2Fuser_upload%2F',
        'fileIdentifier' => '',
    ];

    private const PNG_IMAGE = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private string $mailboxFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mailboxFile = $this->instancePath . '/typo3temp/var/transient/backend-feedback.mbox';
        @unlink($this->mailboxFile);
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport'] = 'mbox';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_mbox_file'] = $this->mailboxFile;
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = 'system@example.org';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = 'Example Site';
        $this->configureExtension(['recipients' => 'admin@example.org, not-an-address, support@example.org']);
    }

    #[Test]
    public function formShowsPageOfTheOpenViewAndLinkWithoutToken(): void
    {
        $markup = $this->renderForm(self::PAGE_VIEW);

        self::assertStringContainsString('Home &amp; Start [1]', $markup);
        self::assertStringContainsString('<dd class="backend-feedback-form__link">https://example.org/typo3/module/web/layout?id=1&amp;language=0</dd>', $markup);
        self::assertStringNotContainsString('backend-feedback-form__warning', $markup);
        self::assertStringContainsString('name="message"', $markup);
    }

    #[Test]
    public function formShowsFolderInsteadOfPageInTheFileModule(): void
    {
        $markup = $this->renderForm(self::FOLDER_VIEW);

        self::assertStringContainsString('<dd>1:/user_upload/</dd>', $markup);
        self::assertStringNotContainsString('Home &amp; Start', $markup);
    }

    #[Test]
    public function formIgnoresStoredFolderOutsideTheFileModule(): void
    {
        $markup = $this->renderForm(['fileIdentifier' => '1:/user_upload/'] + self::PAGE_VIEW);

        self::assertStringContainsString('Home &amp; Start [1]', $markup);
        self::assertStringNotContainsString('<dd>1:/user_upload/</dd>', $markup);
    }

    #[Test]
    public function formShowsStoredFolderInTheFileModuleWithoutFolderInTheAddress(): void
    {
        $markup = $this->renderForm(['fileIdentifier' => '1:/user_upload/', 'endpoint' => '/typo3/module/file/list?token=secret'] + self::FOLDER_VIEW);

        self::assertStringContainsString('<dd>1:/user_upload/</dd>', $markup);
        self::assertStringNotContainsString('Home &amp; Start', $markup);
    }

    #[Test]
    public function formWarnsWithoutRecipients(): void
    {
        $this->configureExtension(['recipients' => '']);

        self::assertStringContainsString('backend-feedback-form__warning', $this->renderForm(self::PAGE_VIEW));
    }

    #[Test]
    public function sendDeliversMailWithContextAndReporterAsReplyTo(): void
    {
        $result = $this->send(self::PAGE_VIEW + ['message' => "No content & files here.\nSecond line."]);

        self::assertTrue($result['success']);
        self::assertSame('Feedback sent', $result['title']);

        $mail = $this->readMail();
        self::assertStringContainsString('Subject: [TYPO3 permissions] Erika Editor: No content & files here. Second line.', $mail);
        self::assertStringContainsString('To: admin@example.org, support@example.org', $mail);
        self::assertStringContainsString('From: Example Site <system@example.org>', $mail);
        self::assertStringContainsString('Reply-To: Erika Editor <editor@example.org>', $mail);
        self::assertStringContainsString('From: Erika Editor (editor, editor@example.org)', $mail);
        self::assertStringContainsString('No content & files here.', $mail);
        self::assertStringContainsString('Page: Home & Start [1]', $mail);
        self::assertStringContainsString('Backend: https://example.org/typo3/module/web/layout?id=1&language=0', $mail);
        self::assertStringContainsString('<blockquote class="backend-feedback-mail__message">No content &amp; files here.<br />', $mail);
        self::assertStringContainsString('.backend-feedback-mail__container', $mail);
        self::assertStringContainsString('<html lang="en">', $mail);
        self::assertStringNotContainsString('token', $mail);
        self::assertStringNotContainsString(' style=', $mail);
    }

    #[Test]
    public function sendReportsFolderOfTheFileModule(): void
    {
        $this->send(self::FOLDER_VIEW + ['message' => 'I cannot upload here.']);

        $mail = $this->readMail();
        self::assertStringContainsString('Folder: 1:/user_upload/', $mail);
        self::assertStringNotContainsString('Page:', $mail);
    }

    #[Test]
    public function sendUsesConfiguredSubjectPrefix(): void
    {
        $this->configureExtension(['recipients' => 'admin@example.org', 'subjectPrefix' => '[Feedback]']);

        $this->send(self::PAGE_VIEW + ['message' => 'Something is missing.']);

        self::assertStringContainsString('Subject: [Feedback] Erika Editor: Something is missing.', $this->readMail());
    }

    #[Test]
    public function sendRejectsEmptyMessage(): void
    {
        $result = $this->send(self::PAGE_VIEW + ['message' => "  \n "]);

        self::assertFalse($result['success']);
        self::assertSame('Please describe what is missing.', $result['message']);
        self::assertFileDoesNotExist($this->mailboxFile);
    }

    #[Test]
    public function sendFailsWithoutRecipients(): void
    {
        $this->configureExtension(['recipients' => 'not-an-address']);

        $result = $this->send(self::PAGE_VIEW + ['message' => 'Something is missing.']);

        self::assertFalse($result['success']);
        self::assertSame('No recipients are configured. Please contact the system maintenance directly.', $result['message']);
        self::assertFileDoesNotExist($this->mailboxFile);
    }

    #[Test]
    public function formOffersScreenshotUnlessSwitchedOff(): void
    {
        self::assertStringContainsString('t3js-backend-feedback-screenshot-capture', $this->renderForm(self::PAGE_VIEW));

        $this->configureExtension(['recipients' => 'admin@example.org', 'screenshot' => '0']);

        self::assertStringNotContainsString('screenshot', $this->renderForm(self::PAGE_VIEW));
    }

    #[Test]
    public function sendAttachesScreenshot(): void
    {
        $result = $this->send(self::PAGE_VIEW + ['message' => 'See the picture.'], $this->createUpload(self::PNG_IMAGE));

        self::assertTrue($result['success']);
        $mail = $this->readMail();
        self::assertStringContainsString('Content-Type: image/png; name=screenshot.png', $mail);
        self::assertStringContainsString('Content-Disposition: attachment; name=screenshot.png; filename=screenshot.png', $mail);
        // start of every base64 encoded PNG file
        self::assertStringContainsString("\r\n\r\niVBORw0KGgo", $mail);
        self::assertStringContainsString('Screenshot: see attachment', $mail);
    }

    #[Test]
    public function sendWithoutScreenshotDoesNotMentionOne(): void
    {
        $this->send(self::PAGE_VIEW + ['message' => 'No picture.']);

        $mail = $this->readMail();
        self::assertStringNotContainsString('Screenshot', $mail);
        self::assertStringNotContainsString('Content-Disposition: attachment', $mail);
    }

    #[Test]
    public function sendRejectsUploadThatIsNotAnImage(): void
    {
        $result = $this->send(self::PAGE_VIEW + ['message' => 'See the picture.'], $this->createUpload(base64_encode('<?php echo 1;')));

        self::assertFalse($result['success']);
        self::assertSame('The screenshot could not be used. Please attach a PNG or JPEG image of at most 5 MB.', $result['message']);
        self::assertFileDoesNotExist($this->mailboxFile);
    }

    #[Test]
    public function sendRejectsFailedUpload(): void
    {
        $result = $this->send(self::PAGE_VIEW + ['message' => 'See the picture.'], $this->createUpload(self::PNG_IMAGE, UPLOAD_ERR_INI_SIZE));

        self::assertFalse($result['success']);
        self::assertFileDoesNotExist($this->mailboxFile);
    }

    #[Test]
    public function sendIgnoresScreenshotWhenSwitchedOff(): void
    {
        $this->configureExtension(['recipients' => 'admin@example.org', 'screenshot' => '0']);

        $result = $this->send(self::PAGE_VIEW + ['message' => 'See the picture.'], $this->createUpload(self::PNG_IMAGE));

        self::assertTrue($result['success']);
        self::assertStringNotContainsString('Content-Disposition: attachment', $this->readMail());
    }

    private function createUpload(string $base64Contents, int $error = UPLOAD_ERR_OK): UploadedFile
    {
        $file = $this->instancePath . '/typo3temp/var/transient/upload-' . bin2hex(random_bytes(4));
        file_put_contents($file, base64_decode($base64Contents));

        return new UploadedFile($file, (int)filesize($file), $error, 'anything.bin', 'application/octet-stream');
    }

    /**
     * @param array<string, string> $values
     */
    private function renderForm(array $values): string
    {
        $response = $this->get(BackendFeedbackController::class)->formAction($this->createBackendRequest('GET', $values));

        return (string)$response->getBody();
    }

    /**
     * @param array<string, string> $values
     * @return array{success: bool, title: string, message: string}
     */
    private function send(array $values, ?UploadedFile $screenshot = null): array
    {
        $uploadedFiles = $screenshot === null ? [] : ['screenshot' => $screenshot];
        $response = $this->get(BackendFeedbackController::class)->sendAction($this->createBackendRequest('POST', [], $values, $uploadedFiles));
        self::assertInstanceOf(ResponseInterface::class, $response);

        return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function readMail(): string
    {
        self::assertFileExists($this->mailboxFile);

        $mail = quoted_printable_decode((string)file_get_contents($this->mailboxFile));

        // unfold long header lines
        return (string)preg_replace('/\r\n[ \t]+/', ' ', $mail);
    }
}
