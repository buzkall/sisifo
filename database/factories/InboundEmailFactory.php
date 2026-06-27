<?php

namespace Arzcode\Sisifo\Database\Factories;

use Arzcode\Sisifo\Models\InboundEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InboundEmail>
 */
class InboundEmailFactory extends Factory
{
    protected $model = InboundEmail::class;

    public function definition(): array
    {
        return [
            'message_id'   => $this->faker->uuid(),
            'subject'      => $this->faker->sentence(),
            'from_address' => $this->faker->email(),
            'from_name'    => $this->faker->name(),
            'message'      => $this->faker->paragraphs(3, true),
            'text_body'    => $this->faker->paragraphs(2, true),
            'received_at'  => $this->faker->dateTimeBetween('-1 day'),
        ];
    }

    public function old(): static
    {
        return $this->state(['received_at' => now()->subDays(10)]);
    }
}
