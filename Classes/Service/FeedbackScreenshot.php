<?php

declare(strict_types=1);

namespace StraschekIo\BackendFeedback\Service;

use Psr\Http\Message\UploadedFileInterface;

/**
 * Image the reporter attached to the feedback, decoded and encoded again so that
 * nothing but the picture itself reaches the recipients.
 */
final class FeedbackScreenshot
{
    public const MAXIMUM_SIZE = 5242880;
    public const MAXIMUM_WIDTH = 4096;
    public const MAXIMUM_HEIGHT = 8192;
    public const MAXIMUM_PIXELS = 12000000;

    private const FILE_EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
    ];
    private const JPEG_QUALITY = 85;

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
        if ((int)$file->getSize() > self::MAXIMUM_SIZE) {
            throw new \InvalidArgumentException('Screenshot is larger than ' . self::MAXIMUM_SIZE . ' bytes', 1791800002);
        }
        $contents = $file->getStream()->read(self::MAXIMUM_SIZE + 1);
        if ($contents === '' || strlen($contents) > self::MAXIMUM_SIZE) {
            throw new \InvalidArgumentException('Screenshot is empty or larger than ' . self::MAXIMUM_SIZE . ' bytes', 1791800004);
        }
        $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        if (!isset(self::FILE_EXTENSIONS[$mimeType])) {
            throw new \InvalidArgumentException('Screenshot has unsupported type ' . $mimeType, 1791800003);
        }

        return new self(self::encodeAgain($contents, $mimeType), $mimeType, 'screenshot.' . self::FILE_EXTENSIONS[$mimeType]);
    }

    /**
     * The dimensions are read from the header first, so an image that would exhaust
     * the memory is rejected before it is decoded.
     */
    private static function encodeAgain(string $contents, string $mimeType): string
    {
        // GD reports broken image data as warnings
        set_error_handler(static fn(): bool => true);
        try {
            $size = getimagesizefromstring($contents);
            if ($size === false
                || $size[0] < 1 || $size[1] < 1
                || $size[0] > self::MAXIMUM_WIDTH || $size[1] > self::MAXIMUM_HEIGHT
                || $size[0] * $size[1] > self::MAXIMUM_PIXELS
            ) {
                throw new \InvalidArgumentException('Screenshot has no readable or too large dimensions', 1791800005);
            }
            $image = imagecreatefromstring($contents);
            if ($image === false) {
                throw new \InvalidArgumentException('Screenshot could not be decoded', 1791800006);
            }

            ob_start();
            try {
                if ($mimeType === 'image/png') {
                    imagesavealpha($image, true);
                    $encoded = imagepng($image);
                } else {
                    $encoded = imagejpeg($image, null, self::JPEG_QUALITY);
                }
            } finally {
                $result = (string)ob_get_clean();
            }
            if (!$encoded || $result === '') {
                throw new \InvalidArgumentException('Screenshot could not be encoded', 1791800007);
            }

            return $result;
        } finally {
            restore_error_handler();
        }
    }
}
