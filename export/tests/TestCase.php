<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // The asset container and the Glide cache live on Bunny Storage (S3).
        // Without credentials every container listing throws, so the tests get
        // empty local disks instead.
        Storage::fake('bunny-assets');
        Storage::fake('bunny-glide-cache');
    }
}
