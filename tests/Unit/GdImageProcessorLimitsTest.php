<?php

declare(strict_types=1);

use Marko\MediaGd\Driver\GdImageProcessor;
use Marko\MediaGd\Exceptions\GdProcessingException;

/**
 * Write a tiny PNG that is only a signature, an IHDR chunk declaring the
 * given dimensions, and an IEND chunk — no pixel data. getimagesize() reads
 * the declared size from the header; decoding it would ask GD to allocate
 * the full canvas.
 */
function createPngHeaderDeclaring(
    int $width,
    int $height,
): string {
    $chunk = static function (string $type, string $data): string {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    };

    $ihdr = pack('NN', $width, $height) . chr(8) . chr(6) . chr(0) . chr(0) . chr(0);
    $png = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', $ihdr) . $chunk('IEND', '');

    $path = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8)) . '.png';
    file_put_contents($path, $png);

    return $path;
}

function createSolidPng(
    int $width,
    int $height,
): string {
    $path = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8)) . '.png';
    imagepng(imagecreatetruecolor($width, $height), $path);

    return $path;
}

it('rejects a PNG whose header declares 40000x40000 before decoding it', function (): void {
    $processor = new GdImageProcessor();
    $sourcePath = createPngHeaderDeclaring(40000, 40000);

    expect(filesize($sourcePath))->toBeLessThan(100)
        ->and(fn () => $processor->resize($sourcePath, 100, 100))
        ->toThrow(GdProcessingException::class, 'is 40000x40000, which exceeds the GD limit');

    unlink($sourcePath);
})->skip(!extension_loaded('gd'), 'GD extension not available');

it('rejects an oversized source for every operation', function (): void {
    $processor = new GdImageProcessor();
    $sourcePath = createPngHeaderDeclaring(40000, 40000);

    expect(fn () => $processor->crop($sourcePath, 0, 0, 10, 10))
        ->toThrow(GdProcessingException::class, 'exceeds the GD limit')
        ->and(fn () => $processor->convert($sourcePath, 'jpeg'))
        ->toThrow(GdProcessingException::class, 'exceeds the GD limit')
        ->and(fn () => $processor->thumbnail($sourcePath, 100))
        ->toThrow(GdProcessingException::class, 'exceeds the GD limit');

    unlink($sourcePath);
})->skip(!extension_loaded('gd'), 'GD extension not available');

it('rejects a source wider than the max dimension even when under the pixel limit', function (): void {
    $processor = new GdImageProcessor();
    $sourcePath = createPngHeaderDeclaring(20000, 1);

    expect(fn () => $processor->convert($sourcePath, 'jpeg'))
        ->toThrow(GdProcessingException::class, 'is 20000x1, which exceeds the GD limit of 16384 px per side');

    unlink($sourcePath);
})->skip(!extension_loaded('gd'), 'GD extension not available');

it('applies configured limits instead of the defaults', function (): void {
    $processor = new GdImageProcessor(maxPixels: 2_500, maxDimension: 16384);
    $sourcePath = createSolidPng(100, 100);

    expect(fn () => $processor->convert($sourcePath, 'png'))
        ->toThrow(
            GdProcessingException::class,
            'is 100x100, which exceeds the GD limit of 16384 px per side or 2500 total pixels',
        );

    unlink($sourcePath);
})->skip(!extension_loaded('gd'), 'GD extension not available');

it('throws GdProcessingException for a file that is not an image', function (): void {
    $processor = new GdImageProcessor();
    $path = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8)) . '.png';
    file_put_contents($path, 'not an image');

    expect(fn () => $processor->convert($path, 'png'))
        ->toThrow(GdProcessingException::class, "GD image processing failed during 'load'");

    unlink($path);
})->skip(!extension_loaded('gd'), 'GD extension not available');

it('rejects zero and negative resize dimensions with GdProcessingException', function (): void {
    $processor = new GdImageProcessor();
    $sourcePath = createSolidPng(100, 100);

    expect(fn () => $processor->resize($sourcePath, 0, 50))
        ->toThrow(GdProcessingException::class, "Invalid target dimensions 0x50 for 'resize'")
        ->and(fn () => $processor->resize($sourcePath, 50, -1, false))
        ->toThrow(GdProcessingException::class, "Invalid target dimensions 50x-1 for 'resize'");

    unlink($sourcePath);
})->skip(!extension_loaded('gd'), 'GD extension not available');

it('rejects resize targets above the configured limits', function (): void {
    $processor = new GdImageProcessor();
    $sourcePath = createSolidPng(100, 100);

    expect(fn () => $processor->resize($sourcePath, 16385, 10, false))
        ->toThrow(GdProcessingException::class, "Invalid target dimensions 16385x10 for 'resize'")
        ->and(fn () => $processor->resize($sourcePath, 10000, 10000, false))
        ->toThrow(GdProcessingException::class, "Invalid target dimensions 10000x10000 for 'resize'");

    unlink($sourcePath);
})->skip(!extension_loaded('gd'), 'GD extension not available');

it('rejects invalid crop dimensions and offsets', function (): void {
    $processor = new GdImageProcessor();
    $sourcePath = createSolidPng(100, 100);

    expect(fn () => $processor->crop($sourcePath, 0, 0, 0, 10))
        ->toThrow(GdProcessingException::class, "Invalid target dimensions 0x10 for 'crop'")
        ->and(fn () => $processor->crop($sourcePath, 0, 0, 20000, 10))
        ->toThrow(GdProcessingException::class, "Invalid target dimensions 20000x10 for 'crop'")
        ->and(fn () => $processor->crop($sourcePath, -5, 0, 10, 10))
        ->toThrow(GdProcessingException::class, 'Invalid crop offset (-5, 0)');

    unlink($sourcePath);
})->skip(!extension_loaded('gd'), 'GD extension not available');

it('rejects a zero thumbnail dimension', function (): void {
    $processor = new GdImageProcessor();
    $sourcePath = createSolidPng(100, 100);

    expect(fn () => $processor->thumbnail($sourcePath, 0))
        ->toThrow(GdProcessingException::class, "Invalid target dimensions 0x0 for 'thumbnail'");

    unlink($sourcePath);
})->skip(!extension_loaded('gd'), 'GD extension not available');

it('keeps aspect-ratio resize results at least 1px on each side', function (): void {
    $processor = new GdImageProcessor();
    $sourcePath = createSolidPng(1000, 1);

    $resultPath = $processor->resize($sourcePath, 10, 10);
    [$width, $height] = getimagesize($resultPath);

    expect($width)->toBe(10)
        ->and($height)->toBe(1);

    unlink($sourcePath);
    unlink($resultPath);
})->skip(!extension_loaded('gd'), 'GD extension not available');

it('throws GdProcessingException when a limit is not positive', function (): void {
    expect(fn () => new GdImageProcessor(maxPixels: 0))
        ->toThrow(GdProcessingException::class, "Invalid GD image limit 'max_pixels': 0")
        ->and(fn () => new GdImageProcessor(maxDimension: -1))
        ->toThrow(GdProcessingException::class, "Invalid GD image limit 'max_dimension': -1");
})->skip(!extension_loaded('gd'), 'GD extension not available');
