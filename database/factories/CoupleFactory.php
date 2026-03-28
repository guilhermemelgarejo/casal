<?php

namespace Database\Factories;

use App\Models\Couple;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Couple>
 */
class CoupleFactory extends Factory
{
    protected $model = Couple::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'invite_code' => strtoupper(Str::random(10)),
        ];
    }
}
