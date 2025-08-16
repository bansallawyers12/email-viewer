<?php

namespace Database\Factories;

use App\Models\Email;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Email>
 */
class EmailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject' => $this->faker->sentence(),
            'sender_name' => $this->faker->name(),
            'sender_email' => $this->faker->email(),
            'recipients' => [$this->faker->email()],
            'cc' => [],
            'bcc' => [],
            'sent_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'received_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'file_path' => 'storage/app/emails/' . $this->faker->uuid() . '.msg',
            'file_name' => $this->faker->word() . '.msg',
            'file_size' => $this->faker->numberBetween(1024, 1024 * 1024), // 1KB to 1MB
            'html_content' => $this->faker->randomHtml(),
            'text_content' => $this->faker->paragraph(),
            'raw_content' => $this->faker->text(),
            'headers' => [
                'From' => $this->faker->email(),
                'To' => $this->faker->email(),
                'Subject' => $this->faker->sentence(),
                'Date' => $this->faker->dateTime()->format('r'),
            ],
            'message_id' => $this->faker->uuid(),
            'thread_id' => $this->faker->uuid(),
            'tags' => [],
            'status' => 'processed',
            'error_message' => null,
        ];
    }

    /**
     * Indicate that the email has failed processing.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => 'Failed to parse email content',
        ]);
    }

    /**
     * Indicate that the email is still processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the email is important.
     */
    public function important(): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => ['important'],
        ]);
    }

    /**
     * Indicate that the email has attachments.
     */
    public function withAttachments(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_size' => $this->faker->numberBetween(1024 * 1024, 10 * 1024 * 1024), // 1MB to 10MB
        ]);
    }
}
