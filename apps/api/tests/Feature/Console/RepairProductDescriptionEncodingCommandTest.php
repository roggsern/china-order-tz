<?php

namespace Tests\Feature\Console;

use App\Models\Product;
use App\Support\Catalog\ProductDescriptionEncodingRepair;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RepairProductDescriptionEncodingCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_dry_run_reports_without_writing(): void
    {
        $original = 'Bullet • and ellipsis …';
        $corrupt = $this->simulateLegacySanitizerMojibake($original);

        $product = Product::factory()->tzLocal()->create([
            'name' => 'Mojibake Dry Run',
            'description' => $corrupt,
            'short_description' => 'Clean short',
        ]);

        $this->artisan('products:repair-description-encoding')
            ->assertSuccessful();

        $this->assertSame($corrupt, $product->fresh()->description);
    }

    public function test_write_mode_repairs_with_confirm(): void
    {
        Log::spy();

        $original = 'Features – “quoted” …';
        $corrupt = $this->simulateLegacySanitizerMojibake($original);

        $product = Product::factory()->tzLocal()->create([
            'name' => 'Mojibake Repair',
            'description' => $corrupt,
            'short_description' => $this->simulateLegacySanitizerMojibake('Short •'),
        ]);

        $this->artisan('products:repair-description-encoding', [
            '--force' => true,
            '--confirm' => ProductDescriptionEncodingRepair::CONFIRMATION_PHRASE,
            '--product' => $product->id,
        ])->assertSuccessful();

        $fresh = $product->fresh();
        $this->assertSame($original, $fresh->description);
        $this->assertSame('Short •', $fresh->short_description);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($message) => $message === 'product_description_encoding_repaired')
            ->atLeast()
            ->once();
    }

    public function test_write_mode_requires_confirm(): void
    {
        $this->artisan('products:repair-description-encoding', [
            '--force' => true,
            '--confirm' => 'WRONG',
        ])->assertFailed();
    }

    private function simulateLegacySanitizerMojibake(string $utf8): string
    {
        $bytes = unpack('C*', $utf8);
        $latin1 = '';
        foreach ($bytes as $byte) {
            $latin1 .= chr($byte);
        }

        return mb_convert_encoding($latin1, 'UTF-8', 'ISO-8859-1');
    }
}
