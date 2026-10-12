<?php

declare(strict_types=1);

namespace StraschekIo\BackendFeedback\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;
use StraschekIo\BackendFeedback\Service\FeedbackScreenshot;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Http\UploadedFile;

final class FeedbackScreenshotTest extends TestCase
{
    private const PAYLOAD = '<?php echo "payload behind the picture";';

    #[Test]
    public function pngIsEncodedAgainWithoutDataBehindThePicture(): void
    {
        $screenshot = FeedbackScreenshot::fromUploadedFile($this->createUpload($this->createImage('png', 40, 20) . self::PAYLOAD));

        self::assertSame('image/png', $screenshot->mimeType);
        self::assertSame('screenshot.png', $screenshot->fileName);
        self::assertStringNotContainsString('payload behind the picture', $screenshot->contents);
        self::assertSame([40, 20], array_slice((array)getimagesizefromstring($screenshot->contents), 0, 2));
    }

    #[Test]
    public function jpegStaysJpegAndLosesItsComment(): void
    {
        $jpeg = $this->createImage('jpeg', 30, 30);
        // JPEG comment segment directly behind the start marker
        $comment = 'comment inside the picture';
        $jpeg = substr($jpeg, 0, 2) . "\xFF\xFE" . pack('n', strlen($comment) + 2) . $comment . substr($jpeg, 2);

        $screenshot = FeedbackScreenshot::fromUploadedFile($this->createUpload($jpeg));

        self::assertSame('image/jpeg', $screenshot->mimeType);
        self::assertSame('screenshot.jpg', $screenshot->fileName);
        self::assertStringNotContainsString($comment, $screenshot->contents);
        self::assertSame([30, 30], array_slice((array)getimagesizefromstring($screenshot->contents), 0, 2));
    }

    #[Test]
    public function transparencyOfPngIsKept(): void
    {
        $image = imagecreatetruecolor(2, 2);
        self::assertNotFalse($image);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, (int)imagecolorallocatealpha($image, 0, 0, 0, 127));
        ob_start();
        imagepng($image);
        $png = (string)ob_get_clean();

        $screenshot = FeedbackScreenshot::fromUploadedFile($this->createUpload($png));

        $decoded = imagecreatefromstring($screenshot->contents);
        self::assertNotFalse($decoded);
        self::assertSame(127, (imagecolorat($decoded, 0, 0) >> 24) & 0x7F);
    }

    #[Test]
    public function declaredSizeAboveLimitIsRejectedWithoutReadingTheFile(): void
    {
        $file = $this->createMock(UploadedFileInterface::class);
        $file->method('getError')->willReturn(UPLOAD_ERR_OK);
        $file->method('getSize')->willReturn(FeedbackScreenshot::MAXIMUM_SIZE + 1);
        $file->expects($this->never())->method('getStream');

        $this->expectExceptionCode(1791800002);
        FeedbackScreenshot::fromUploadedFile($file);
    }

    #[Test]
    public function contentAboveLimitIsRejectedEvenIfDeclaredSmaller(): void
    {
        $path = $this->writeTemporaryFile(str_repeat('a', FeedbackScreenshot::MAXIMUM_SIZE + 10));

        $this->expectExceptionCode(1791800004);
        FeedbackScreenshot::fromUploadedFile(new UploadedFile($path, 100, UPLOAD_ERR_OK));
    }

    #[Test]
    public function imageWithHugeDeclaredDimensionsIsRejectedBeforeDecoding(): void
    {
        // PNG signature and header chunk only: 20000 x 20000 pixels, no image data
        $header = 'IHDR' . pack('NNCCCCC', 20000, 20000, 8, 6, 0, 0, 0);
        $png = "\x89PNG\r\n\x1a\n" . pack('N', 13) . $header . pack('N', crc32($header));

        $this->expectExceptionCode(1791800005);
        FeedbackScreenshot::fromUploadedFile($this->createUpload($png));
    }

    #[Test]
    public function brokenImageDataIsRejected(): void
    {
        $png = substr($this->createImage('png', 40, 20), 0, 60);

        $this->expectException(\InvalidArgumentException::class);
        FeedbackScreenshot::fromUploadedFile($this->createUpload($png));
    }

    #[Test]
    public function otherFileTypesAreRejected(): void
    {
        $this->expectExceptionCode(1791800003);
        FeedbackScreenshot::fromUploadedFile($this->createUpload('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'));
    }

    #[Test]
    public function failedUploadIsRejected(): void
    {
        $this->expectExceptionCode(1791800001);
        FeedbackScreenshot::fromUploadedFile(new UploadedFile(new Stream('php://memory', 'r+'), 0, UPLOAD_ERR_PARTIAL));
    }

    private function createImage(string $format, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, (int)imagecolorallocate($image, 200, 30, 30));
        ob_start();
        $format === 'png' ? imagepng($image) : imagejpeg($image);

        return (string)ob_get_clean();
    }

    private function createUpload(string $contents): UploadedFile
    {
        return new UploadedFile($this->writeTemporaryFile($contents), strlen($contents), UPLOAD_ERR_OK, 'anything.bin', 'application/octet-stream');
    }

    private function writeTemporaryFile(string $contents): string
    {
        $path = (string)tempnam(sys_get_temp_dir(), 'backend-feedback-');
        file_put_contents($path, $contents);

        return $path;
    }
}
