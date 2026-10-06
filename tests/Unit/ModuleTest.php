<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Media\Contracts\ImageProcessorInterface;
use Marko\MediaGd\Driver\GdImageProcessor;
use Marko\MediaGd\Exceptions\GdProcessingException;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * @param array<string, mixed> $config
 */
function createMediaGdContainer(
    array $config,
): Container {
    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository($config));

    $module = require dirname(__DIR__, 2) . '/module.php';

    foreach ($module['bindings'] as $id => $implementation) {
        $container->bind($id, $implementation);
    }

    return $container;
}

it('ships a media-gd config file with dimension limit defaults', function (): void {
    $config = require dirname(__DIR__, 2) . '/config/media-gd.php';

    expect($config)->toBe([
        'max_pixels' => 50_000_000,
        'max_dimension' => 16384,
    ])
        ->and(GdImageProcessor::DEFAULT_MAX_PIXELS)->toBe($config['max_pixels'])
        ->and(GdImageProcessor::DEFAULT_MAX_DIMENSION)->toBe($config['max_dimension']);
});

it('builds the image processor with limits from media-gd config', function (): void {
    $container = createMediaGdContainer([
        'media-gd.max_pixels' => 2_500,
        'media-gd.max_dimension' => 16384,
    ]);
    $processor = $container->get(ImageProcessorInterface::class);
    $sourcePath = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8)) . '.png';
    imagepng(imagecreatetruecolor(100, 100), $sourcePath);

    expect($processor)->toBeInstanceOf(GdImageProcessor::class)
        ->and($container->get(GdImageProcessor::class))->toBeInstanceOf(GdImageProcessor::class)
        ->and(fn () => $processor->convert($sourcePath, 'png'))
        ->toThrow(GdProcessingException::class, 'or 2500 total pixels');

    unlink($sourcePath);
})->skip(!extension_loaded('gd'), 'GD extension not available');
