<?php

return [
    // Set by the deployment operator. Never derive public version strings from untrusted input.
    'version' => env('HMS_RELEASE_VERSION', ''),
    'revision' => env('HMS_RELEASE_REVISION', ''),
];
