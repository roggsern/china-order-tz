<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithCheckoutShipping;

abstract class TestCase extends ApplicationTestCase
{
    use InteractsWithCheckoutShipping;
    use RefreshDatabase;
}
