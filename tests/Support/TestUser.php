<?php

namespace Arzcode\Sisifo\Tests\Support;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User;
use Illuminate\Notifications\Notifiable;

class TestUser extends User
{
    use HasFactory;
    use Notifiable;

    protected $table = 'users';
    protected $guarded = [];

    protected static function newFactory(): Factory
    {
        return new class extends Factory
        {
            protected $model = TestUser::class;

            public function definition(): array
            {
                return [
                    'name'     => $this->faker->name(),
                    'email'    => $this->faker->unique()->safeEmail(),
                    'password' => bcrypt('password'),
                ];
            }
        };
    }
}
