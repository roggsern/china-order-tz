<?php

namespace Tests\Unit\Services\ProductConfiguration;

use App\Models\AttributeDependency;
use App\Models\AttributeDependencyRule;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use App\Services\ProductConfiguration\AttributeDependencyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributeDependencyResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_allowed_target_values_from_metadata_rules(): void
    {
        $type = ProductType::query()->create([
            'name' => 'Demo Type',
            'slug' => 'demo-type',
            'has_configurations' => true,
            'allows_price_override' => true,
            'allows_moq_pricing' => true,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $source = ProductAttribute::factory()->create(['slug' => 'source-attr', 'name' => 'Source']);
        $target = ProductAttribute::factory()->create(['slug' => 'target-attr', 'name' => 'Target']);

        $sourceA = ProductAttributeValue::factory()->create([
            'product_attribute_id' => $source->id,
            'slug' => 'a',
            'value' => 'A',
        ]);
        $targetX = ProductAttributeValue::factory()->create([
            'product_attribute_id' => $target->id,
            'slug' => 'x',
            'value' => 'X',
        ]);
        $targetY = ProductAttributeValue::factory()->create([
            'product_attribute_id' => $target->id,
            'slug' => 'y',
            'value' => 'Y',
        ]);

        $dependency = AttributeDependency::query()->create([
            'product_type_id' => $type->id,
            'source_attribute_id' => $source->id,
            'target_attribute_id' => $target->id,
        ]);

        AttributeDependencyRule::query()->create([
            'attribute_dependency_id' => $dependency->id,
            'source_attribute_value_id' => $sourceA->id,
            'target_attribute_value_id' => $targetX->id,
        ]);

        $resolver = app(AttributeDependencyResolver::class);

        $allowed = $resolver->allowedValues($type, [
            $source->id => $sourceA->id,
        ]);

        $this->assertSame([$targetX->id], $allowed[$target->id]);
        $this->assertNotContains($targetY->id, $allowed[$target->id]);

        $this->assertTrue($resolver->isValidCombination($type, [
            $source->id => $sourceA->id,
            $target->id => $targetX->id,
        ]));

        $this->assertFalse($resolver->isValidCombination($type, [
            $source->id => $sourceA->id,
            $target->id => $targetY->id,
        ]));
    }
}
