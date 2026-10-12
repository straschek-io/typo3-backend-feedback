<?php

declare(strict_types=1);

namespace StraschekIo\BackendFeedback\Service;

use Psr\Http\Message\UploadedFileInterface;

/**
 * Image the reporter attached to the feedback.
 */
final class FeedbackScreenshot
{
    public const MAXIMUM_SIZE = 5242880;

    private const FILE_EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
    ];

    private function __construct(
        public readonly string $contents,
        public readonly string $mimeType,
        public readonly string $fileName,
    ) {}

    /**
     * @throws \InvalidArgumentException if the upload failed, is too large or is not a PNG or JPEG image
     */
    public static function fromUploadedFile(UploadedFileInterface $file): self
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Screenshot upload failed with error code ' . $file->getError(), 1791800001);
        }
        $contents = (string)$file->getStream();
        if ($contents === '' || strlen($contents) > self::MAXIMUM_SIZE) {
            throw new \InvalidArgumentException('Screenshot is empty or larger than ' . self::MAXIMUM_SIZE . ' bytes', 1791800002);
        }
        $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        if (!isset(self::FILE_EXTENSIONS[$mimeType])) {
            throw new \InvalidArgumentException('Screenshot has unsupported type ' . $mimeType, 1791800003);
        }

        return new self($contents, $mimeType, 'screenshot.' . self::FILE_EXTENSIONS[$mimeType]);
    }
}
