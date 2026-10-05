<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Models\Account;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'platform' => Platform::Instagram,
            'name' => fake()->userName(),
            'input_method' => Account::METHOD_API,
            'status' => 'not_connected',
        ];
    }

    public function platform(Platform $platform): static
    {
        return $this->state(['platform' => $platform]);
    }

    public function manual(): static
    {
        return $this->state(['input_method' => Account::METHOD_MANUAL]);
    }

    public function connected(string $externalId = '1001'): static
    {
        return $this->state([
            'external_id' => $externalId,
            'credentials' => ['access_token' => 'token-'.$externalId, 'refresh_token' => 'refresh-'.$externalId, 'expires_at' => now()->addDays(30)->toIso8601String()],
            'status' => 'active',
        ]);
    }
}
