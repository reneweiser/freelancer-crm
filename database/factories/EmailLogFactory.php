<?php

namespace Database\Factories;

use App\Enums\EmailLogStatus;
use App\Enums\EmailLogType;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLog>
 */
class EmailLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(EmailLogType::cases()),
            'recipient_email' => $this->faker->safeEmail(),
            'recipient_name' => $this->faker->name(),
            'subject' => $this->faker->sentence(),
            'body' => $this->faker->paragraph(),
            'has_attachment' => $this->faker->boolean(70),
            'status' => EmailLogStatus::Queued,
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn () => [
            'user_id' => $project->user_id,
            'emailable_type' => Project::class,
            'emailable_id' => $project->id,
            'type' => EmailLogType::Offer,
            'recipient_email' => $project->client->email ?? $this->faker->safeEmail(),
            'recipient_name' => $project->client->display_name,
            'has_attachment' => true,
            'attachment_filename' => "Angebot-{$project->reference}.pdf",
        ]);
    }

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(fn () => [
            'user_id' => $invoice->user_id,
            'emailable_type' => Invoice::class,
            'emailable_id' => $invoice->id,
            'type' => EmailLogType::Invoice,
            'recipient_email' => $invoice->client->email ?? $this->faker->safeEmail(),
            'recipient_name' => $invoice->client->display_name,
            'has_attachment' => true,
            'attachment_filename' => "Rechnung-{$invoice->number}.pdf",
        ]);
    }

    public function paymentReminder(Invoice $invoice): static
    {
        return $this->state(fn () => [
            'user_id' => $invoice->user_id,
            'emailable_type' => Invoice::class,
            'emailable_id' => $invoice->id,
            'type' => EmailLogType::PaymentReminder,
            'recipient_email' => $invoice->client->email ?? $this->faker->safeEmail(),
            'recipient_name' => $invoice->client->display_name,
            'has_attachment' => false,
        ]);
    }

    public function queued(): static
    {
        return $this->state(fn () => [
            'status' => EmailLogStatus::Queued,
            'sent_at' => null,
            'error_message' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => EmailLogStatus::Sent,
            'sent_at' => now(),
            'error_message' => null,
        ]);
    }

    public function failed(string $errorMessage = 'Connection refused'): static
    {
        return $this->state(fn () => [
            'status' => EmailLogStatus::Failed,
            'sent_at' => null,
            'error_message' => $errorMessage,
        ]);
    }
}
