<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * 비-platform 사용자(pharma/cso)는 기본 제약사(DEFAULT)에 소속시킨다.
     * (GAP-10 MT-4-finalize: 도메인 tenant_id NOT NULL — admin/sales 가 tenant 컨텍스트를 가져야
     *  HTTP 생성 경로에서 tenant_id 가 자동 주입됨. platform 또는 명시 tenant_id 는 그대로 둔다.)
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            if ($user->role !== User::ROLE_PLATFORM && $user->tenant_id === null) {
                $user->tenant_id = Tenant::default()->id;
            }
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
