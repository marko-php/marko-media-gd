<?php

declare(strict_types=1);

return [
    // Largest total pixel count (width × height) GD will decode or create.
    // A decoded truecolor image costs roughly 5 bytes per pixel, so the
    // 50 MP default needs ~250 MB of memory. Raise memory_limit to match
    // before raising this.
    'max_pixels' => 50_000_000,
    // Largest width or height, in pixels, GD will decode or create.
    'max_dimension' => 16384,
];
