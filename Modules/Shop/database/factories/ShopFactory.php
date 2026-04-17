<?php

declare(strict_types=1);

namespace Modules\Shop\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Shop\Entities\Shop;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * @var class-string<Shop>
     */
    protected $model = Shop::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->optional()->sentence(),
            'logo_url' => null,
            'company_name' => fake()->company(),
            'vat_number' => fake()->numerify('RC-CON-####-B-####'),
            'is_verified' => false,
        ];
    }
}
