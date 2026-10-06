<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Media\Contracts\ImageProcessorInterface;
use Marko\MediaGd\Driver\GdImageProcessor;

$createProcessor = static function (ContainerInterface $container): GdImageProcessor {
    $config = $container->get(ConfigRepositoryInterface::class);

    return new GdImageProcessor(
        maxPixels: $config->getInt(key: 'media-gd.max_pixels'),
        maxDimension: $config->getInt(key: 'media-gd.max_dimension'),
    );
};

return [
    'bindings' => [
        ImageProcessorInterface::class => $createProcessor,
        GdImageProcessor::class => $createProcessor,
    ],
];
