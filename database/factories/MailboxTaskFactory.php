<?php

namespace Arzcode\Sisifo\Database\Factories;

use Arzcode\Sisifo\Enums\MailboxTaskNotificationEnum;
use Arzcode\Sisifo\Enums\MailboxTaskTypeEnum;
use Arzcode\Sisifo\Models\MailboxTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailboxTask>
 */
class MailboxTaskFactory extends Factory
{
    protected $model = MailboxTask::class;

    public function definition(): array
    {
        return [
            'name'                 => $this->faker->sentence(3),
            'type'                 => MailboxTaskTypeEnum::Summary,
            'prompt'               => $this->faker->paragraph(),
            'is_active'            => true,
            'one_shot'             => false,
            'schedule_frequency'   => 'daily',
            'schedule_days'        => [1, 2, 3, 4, 5],
            'schedule_time'        => '09:00',
            'schedule_timezone'    => 'Europe/Madrid',
            'notification_methods' => [MailboxTaskNotificationEnum::Pushover],
            'is_urgent'            => false,
        ];
    }

    public function watch(): static
    {
        return $this->state([
            'type'               => MailboxTaskTypeEnum::Watch,
            'schedule_frequency' => null,
            'schedule_days'      => null,
            'schedule_time'      => null,
        ]);
    }

    public function oneShot(): static
    {
        return $this->state([
            'one_shot' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
        ]);
    }
}
