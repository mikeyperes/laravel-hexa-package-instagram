<?php

namespace hexa_package_instagram\Support;

use RuntimeException;

/**
 * Fits a picture whole onto a story (9:16) or feed post (4:5) canvas: the picture is scaled to fit
 * inside the configured box, never cropped, and the rest of the canvas is a blurred, darkened copy.
 */
class InstagramImageComposer
{
    /**
     * @param string $kind story or post
     * @return string JPEG bytes
     */
    public function compose(string $bytes, string $kind): string
    {
        $canvasConfig = (array) config('instagram.publishing.'.($kind === 'story' ? 'story_canvas' : 'post_canvas'), []);
        $width = (int) ($canvasConfig['width'] ?? 1080);
        $height = (int) ($canvasConfig['height'] ?? 1920);
        $boxWidth = min($width, (int) ($canvasConfig['box_width'] ?? $width));
        $boxHeight = min($height, (int) ($canvasConfig['box_height'] ?? $height));

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            throw new RuntimeException('The image could not be read (JPEG, PNG, WebP or GIF expected).');
        }
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        $canvas = imagecreatetruecolor($width, $height);
        $this->drawBlurredBackground($canvas, $source, $width, $height);

        $scale = min($boxWidth / $sourceWidth, $boxHeight / $sourceHeight);
        $fitWidth = (int) round($sourceWidth * $scale);
        $fitHeight = (int) round($sourceHeight * $scale);
        imagecopyresampled($canvas, $source, (int) (($width - $fitWidth) / 2), (int) (($height - $fitHeight) / 2), 0, 0, $fitWidth, $fitHeight, $sourceWidth, $sourceHeight);

        ob_start();
        imagejpeg($canvas, null, (int) config('instagram.publishing.jpeg_quality', 90));
        $jpeg = (string) ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($source);

        return $jpeg;
    }

    /** Cover-fit copy of the picture, blurred by shrinking and enlarging it, then darkened. */
    private function drawBlurredBackground(\GdImage $canvas, \GdImage $source, int $width, int $height): void
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $cover = max($width / $sourceWidth, $height / $sourceHeight);
        $cropWidth = (int) round($width / $cover);
        $cropHeight = (int) round($height / $cover);

        $small = imagecreatetruecolor(max(1, intdiv($width, 16)), max(1, intdiv($height, 16)));
        imagecopyresampled($small, $source, 0, 0, (int) (($sourceWidth - $cropWidth) / 2), (int) (($sourceHeight - $cropHeight) / 2), imagesx($small), imagesy($small), $cropWidth, $cropHeight);
        for ($pass = 0; $pass < 4; $pass++) {
            imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
        }
        // Enlarge in two steps, blurring in between, so the background is smooth rather than blocky.
        $middle = imagecreatetruecolor(max(1, intdiv($width, 4)), max(1, intdiv($height, 4)));
        imagecopyresampled($middle, $small, 0, 0, 0, 0, imagesx($middle), imagesy($middle), imagesx($small), imagesy($small));
        for ($pass = 0; $pass < 12; $pass++) {
            imagefilter($middle, IMG_FILTER_GAUSSIAN_BLUR);
        }
        imagecopyresampled($canvas, $middle, 0, 0, 0, 0, $width, $height, imagesx($middle), imagesy($middle));
        imagefilter($canvas, IMG_FILTER_BRIGHTNESS, -45);
        imagedestroy($small);
        imagedestroy($middle);
    }
}
