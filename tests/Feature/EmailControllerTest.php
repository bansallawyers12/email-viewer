<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Email;
use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class EmailControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        Storage::fake('local');
        Cache::flush();
    }

    /** @test */
    public function it_can_list_emails_with_pagination()
    {
        // Create test emails
        Email::factory()->count(25)->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->actingAs($this->user)
                        ->getJson('/api/emails?per_page=10');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'emails',
                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                        'from',
                        'to'
                    ]
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertCount(10, $response->json('emails'));
        $this->assertEquals(25, $response->json('pagination.total'));
    }

    /** @test */
    public function it_can_search_emails()
    {
        // Create emails with specific content
        Email::factory()->create([
            'user_id' => $this->user->id,
            'subject' => 'Test Email Subject',
            'sender_email' => 'test@example.com'
        ]);

        Email::factory()->create([
            'user_id' => $this->user->id,
            'subject' => 'Another Email',
            'sender_email' => 'other@example.com'
        ]);

        $response = $this->actingAs($this->user)
                        ->getJson('/api/emails?search=Test');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('emails'));
        $this->assertEquals('Test Email Subject', $response->json('emails.0.subject'));
    }

    /** @test */
    public function it_can_filter_emails_by_date_range()
    {
        $oldEmail = Email::factory()->create([
            'user_id' => $this->user->id,
            'sent_date' => now()->subDays(10)
        ]);

        $recentEmail = Email::factory()->create([
            'user_id' => $this->user->id,
            'sent_date' => now()->subDays(2)
        ]);

        $response = $this->actingAs($this->user)
                        ->getJson('/api/emails?date_from=' . now()->subDays(5)->toDateString());

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('emails'));
        $this->assertEquals($recentEmail->id, $response->json('emails.0.id'));
    }

    /** @test */
    public function it_can_sort_emails()
    {
        Email::factory()->create([
            'user_id' => $this->user->id,
            'subject' => 'A Email'
        ]);

        Email::factory()->create([
            'user_id' => $this->user->id,
            'subject' => 'B Email'
        ]);

        $response = $this->actingAs($this->user)
                        ->getJson('/api/emails?sort_by=subject&sort_order=desc');

        $response->assertStatus(200);
        $emails = $response->json('emails');
        $this->assertEquals('B Email', $emails[0]['subject']);
        $this->assertEquals('A Email', $emails[1]['subject']);
    }

    /** @test */
    public function it_can_show_email_details()
    {
        $email = Email::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->actingAs($this->user)
                        ->getJson("/api/emails/{$email->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'email' => [
                        'id',
                        'subject',
                        'sender_email',
                        'sent_date',
                        'file_size'
                    ]
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals($email->id, $response->json('email.id'));
    }

    /** @test */
    public function it_returns_404_for_nonexistent_email()
    {
        $response = $this->actingAs($this->user)
                        ->getJson('/api/emails/999');

        $response->assertStatus(404);
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_prevents_access_to_other_users_emails()
    {
        $otherUser = User::factory()->create();
        $email = Email::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $response = $this->actingAs($this->user)
                        ->getJson("/api/emails/{$email->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function it_can_update_email()
    {
        $email = Email::factory()->create([
            'user_id' => $this->user->id
        ]);

        $updateData = [
            'tags' => 'important,work',
            'notes' => 'This is a test note',
            'is_important' => true,
            'is_read' => true
        ];

        $response = $this->actingAs($this->user)
                        ->putJson("/api/emails/{$email->id}", $updateData);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        $email->refresh();
        $this->assertEquals('important,work', $email->tags);
        $this->assertEquals('This is a test note', $email->notes);
        $this->assertTrue($email->is_important);
        $this->assertTrue($email->is_read);
    }

    /** @test */
    public function it_validates_update_data()
    {
        $email = Email::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->actingAs($this->user)
                        ->putJson("/api/emails/{$email->id}", [
                            'tags' => str_repeat('a', 501), // Too long
                            'notes' => str_repeat('b', 1001), // Too long
                            'is_important' => 'invalid' // Not boolean
                        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['tags', 'notes', 'is_important']);
    }

    /** @test */
    public function it_can_delete_email()
    {
        $email = Email::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->actingAs($this->user)
                        ->deleteJson("/api/emails/{$email->id}");

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertDatabaseMissing('emails', ['id' => $email->id]);
    }

    /** @test */
    public function it_can_get_email_statistics()
    {
        // Create emails with different statuses
        Email::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'status' => 'completed'
        ]);

        Email::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => 'failed'
        ]);

        $response = $this->actingAs($this->user)
                        ->getJson('/api/emails/statistics');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'statistics' => [
                        'total_emails',
                        'total_size',
                        'emails_with_attachments',
                        'total_attachments',
                        'recent_emails',
                        'status_breakdown'
                    ]
                ]);

        $stats = $response->json('statistics');
        $this->assertEquals(7, $stats['total_emails']);
        $this->assertEquals(5, $stats['status_breakdown']['completed']);
        $this->assertEquals(2, $stats['status_breakdown']['failed']);
    }

    /** @test */
    public function it_can_clear_all_emails()
    {
        Email::factory()->count(5)->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->actingAs($this->user)
                        ->deleteJson('/api/emails/clear-all');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals(5, $response->json('deleted_count'));
        $this->assertDatabaseCount('emails', 0);
    }

    /** @test */
    public function it_caches_email_list_results()
    {
        Email::factory()->count(3)->create([
            'user_id' => $this->user->id
        ]);

        // First request should cache the result
        $response1 = $this->actingAs($this->user)
                         ->getJson('/api/emails');

        $response1->assertStatus(200);

        // Second request should use cache
        $response2 = $this->actingAs($this->user)
                         ->getJson('/api/emails');

        $response2->assertStatus(200);
        $this->assertEquals($response1->json(), $response2->json());
    }

    /** @test */
    public function it_handles_invalid_pagination_parameters()
    {
        $response = $this->actingAs($this->user)
                        ->getJson('/api/emails?per_page=150'); // Exceeds max

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_handles_invalid_sort_parameters()
    {
        $response = $this->actingAs($this->user)
                        ->getJson('/api/emails?sort_by=invalid_field');

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->getJson('/api/emails');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_can_filter_emails_by_attachment_status()
    {
        // Email with attachments
        $emailWithAttachments = Email::factory()->create([
            'user_id' => $this->user->id
        ]);
        Attachment::factory()->count(2)->create([
            'email_id' => $emailWithAttachments->id
        ]);

        // Email without attachments
        Email::factory()->create([
            'user_id' => $this->user->id
        ]);

        // Test filtering emails with attachments
        $response = $this->actingAs($this->user)
                        ->getJson('/api/emails?has_attachments=true');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('emails'));

        // Test filtering emails without attachments
        $response = $this->actingAs($this->user)
                        ->getJson('/api/emails?has_attachments=false');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('emails'));
    }

    /** @test */
    public function it_can_filter_emails_by_size()
    {
        Email::factory()->create([
            'user_id' => $this->user->id,
            'file_size' => 500 * 1024 // 500KB
        ]);

        Email::factory()->create([
            'user_id' => $this->user->id,
            'file_size' => 2 * 1024 * 1024 // 2MB
        ]);

        Email::factory()->create([
            'user_id' => $this->user->id,
            'file_size' => 10 * 1024 * 1024 // 10MB
        ]);

        // Test small files filter
        $response = $this->actingAs($this->user)
                        ->getJson('/api/emails?size_filter=small');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('emails'));

        // Test large files filter
        $response = $this->actingAs($this->user)
                        ->getJson('/api/emails?size_filter=large');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('emails'));
    }
} 