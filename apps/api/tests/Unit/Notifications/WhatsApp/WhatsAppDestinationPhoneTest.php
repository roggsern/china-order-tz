<?php

namespace Tests\Unit\Notifications\WhatsApp;

use App\Services\Notifications\WhatsApp\WhatsAppDestinationPhone;
use Tests\TestCase;

class WhatsAppDestinationPhoneTest extends TestCase
{
    private WhatsAppDestinationPhone $phones;

    protected function setUp(): void
    {
        parent::setUp();
        $this->phones = new WhatsAppDestinationPhone;
    }

    public function test_strips_plus_from_e164(): void
    {
        $this->assertSame('255712345678', $this->phones->normalize('+255712345678'));
    }

    public function test_keeps_international_digits(): void
    {
        $this->assertSame('255712345678', $this->phones->normalize('255712345678'));
    }

    public function test_normalizes_local_tanzania_forms(): void
    {
        $this->assertSame('255712345678', $this->phones->normalize('0712345678'));
        $this->assertSame('255712345678', $this->phones->normalize('712345678'));
    }

    public function test_rejects_empty_and_invalid(): void
    {
        $this->assertNull($this->phones->normalize(null));
        $this->assertNull($this->phones->normalize(''));
        $this->assertNull($this->phones->normalize('not-a-phone'));
        $this->assertNull($this->phones->normalize('123'));
    }
}
