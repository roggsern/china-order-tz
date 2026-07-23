<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Admin\AdminPermissions;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    /**
     * The current password being used by the factory.
     * Plain string — Admin model casts password => hashed.
     */
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('07########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= 'password',
            /*
             * Default test actors are super-admins so legacy feature suites that
             * create plain Admin::factory()->create() keep exercising domain
             * logic rather than RBAC. Matrix tests must use ordinary()/withPermissions().
             */
            'is_super_admin' => true,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(function (array $attributes) {
            $administratorId = Role::query()->where('slug', 'administrator')->value('id');

            return array_filter([
                'is_super_admin' => true,
                'role_id' => $administratorId,
            ], static fn ($value) => $value !== null);
        });
    }

    /**
     * Non-super admin — permissions come only from the role matrix.
     */
    public function ordinary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_super_admin' => false,
        ]);
    }

    /**
     * Attach exact permissions to this admin's role (replaces role permission set).
     *
     * @param  list<string>  $permissionSlugs
     */
    public function withPermissions(array $permissionSlugs): static
    {
        return $this->ordinary()->afterCreating(function (Admin $admin) use ($permissionSlugs) {
            foreach ($permissionSlugs as $slug) {
                if (! AdminPermissions::isKnown($slug)) {
                    continue;
                }
                [$domain] = explode('.', $slug, 2);
                Permission::query()->firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => AdminPermissions::labels()[$slug] ?? $slug,
                        'domain' => $domain,
                        'description' => "Admin permission: {$slug}",
                    ],
                );
            }

            $ids = Permission::query()
                ->whereIn('slug', $permissionSlugs)
                ->pluck('id')
                ->all();

            $admin->role?->permissions()->sync($ids);
            $admin->unsetRelation('role');
        });
    }

    public function withoutPermissions(): static
    {
        return $this->withPermissions([]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
