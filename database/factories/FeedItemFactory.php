<?php

namespace Arzcode\Sisifo\Database\Factories;

use Arzcode\Sisifo\Models\FeedItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedItem>
 */
class FeedItemFactory extends Factory
{
    protected $model = FeedItem::class;

    public function definition(): array
    {
        $tag = 'v' . $this->faker->numberBetween(1, 9) . '.' . $this->faker->numberBetween(0, 9) . '.' . $this->faker->numberBetween(0, 9);
        $repo = $this->faker->userName() . '/' . $this->faker->slug(2);

        return [
            'external_id'  => 'tag:github.com,2008:Repository/' . $this->faker->numberBetween(1000, 999999) . '/' . $tag,
            'title'        => $tag,
            'url'          => "https://github.com/{$repo}/releases/tag/{$tag}",
            'source_ref'   => $repo,
            'body'         => $this->faker->paragraphs(3, true),
            'published_at' => $this->faker->dateTimeBetween('-30 days'),
        ];
    }
}
