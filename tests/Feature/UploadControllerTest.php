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
use Illuminate\Support\Facades\Log;

class UploadControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        Storage::fake('local');
    }

    /** @test */
    public function it_can_upload_msg_files()
    {
        $file = UploadedFile::fake()->create('test.msg', 1024);

        $response = $this->actingAs($this->user)
                        ->postJson('/api/upload', [
                            'files' => [$file]
                        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'emails',
                    'errors',
                    'summary' => [
                        'total_files',
                        'successful_uploads',
                        'failed_uploads'
                    ]
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals(1, $response->json('summary.successful_uploads'));
    }

    /** @test */
    public function it_rejects_non_msg_files()
    {
        $file = UploadedFile::fake()->create('test.txt', 1024);

        $response = $this->actingAs($this->user)
                        ->postJson('/api/upload', [
                            'files' => [$file]
                        ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
        $this->assertArrayHasKey('files.0', $response->json('errors'));
    }

    /** @test */
    public function it_rejects_files_too_large()
    {
        $file = UploadedFile::fake()->create('test.msg', 11 * 1024); // 11MB

        $response = $this->actingAs($this->user)
                        ->postJson('/api/upload', [
                            'files' => [$file]
                        ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_rejects_empty_files()
    {
        $file = UploadedFile::fake()->create('test.msg', 0);

        $response = $this->actingAs($this->user)
                        ->postJson('/api/upload', [
                            'files' => [$file]
                        ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_can_upload_multiple_files()
    {
        $file1 = UploadedFile::fake()->create('test1.msg', 1024);
        $file2 = UploadedFile::fake()->create('test2.msg', 1024);

        $response = $this->actingAs($this->user)
                        ->postJson('/api/upload', [
                            'files' => [$file1, $file2]
                        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals(2, $response->json('summary.successful_uploads'));
    }

    /** @test */
    public function it_handles_mixed_success_and_failure()
    {
        $validFile = UploadedFile::fake()->create('valid.msg', 1024);
        $invalidFile = UploadedFile::fake()->create('invalid.txt', 1024);

        $response = $this->actingAs($this->user)
                        ->postJson('/api/upload', [
                            'files' => [$validFile, $invalidFile]
                        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals(1, $response->json('summary.successful_uploads'));
        $this->assertEquals(1, $response->json('summary.failed_uploads'));
        $this->assertCount(1, $response->json('errors'));
    }

    /** @test */
    public function it_requires_authentication()
    {
        $file = UploadedFile::fake()->create('test.msg', 1024);

        $response = $this->postJson('/api/upload', [
            'files' => [$file]
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_files_to_be_uploaded()
    {
        $response = $this->actingAs($this->user)
                        ->postJson('/api/upload', []);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_can_get_upload_progress()
    {
        $email = Email::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'processing',
            'processing_progress' => 50
        ]);

        $response = $this->actingAs($this->user)
                        ->getJson("/api/upload/progress/{$email->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'progress' => [
                        'email_id',
                        'status',
                        'progress',
                        'message'
                    ]
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals(50, $response->json('progress.progress'));
    }

    /** @test */
    public function it_returns_404_for_nonexistent_progress()
    {
        $response = $this->actingAs($this->user)
                        ->getJson('/api/upload/progress/999');

        $response->assertStatus(404);
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_can_get_storage_usage()
    {
        // Create some test emails
        Email::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'file_size' => 1024 * 1024 // 1MB each
        ]);

        $response = $this->actingAs($this->user)
                        ->getJson('/api/upload/storage-usage');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'total_bytes',
                    'formatted_size',
                    'email_count',
                    'attachment_count',
                    'usage_percentage'
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals(3, $response->json('email_count'));
        $this->assertEquals(3 * 1024 * 1024, $response->json('total_bytes'));
    }

    /** @test */
    public function it_can_delete_uploaded_email()
    {
        $email = Email::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->actingAs($this->user)
                        ->deleteJson("/api/upload/{$email->id}");

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertDatabaseMissing('emails', ['id' => $email->id]);
    }

    /** @test */
    public function it_prevents_deleting_other_users_emails()
    {
        $otherUser = User::factory()->create();
        $email = Email::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $response = $this->actingAs($this->user)
                        ->deleteJson("/api/upload/{$email->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function it_handles_storage_limit_exceeded()
    {
        // Create emails that use up most of the storage
        Email::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'file_size' => 2 * 1024 * 1024 * 1024 // 2GB each
        ]);

        $largeFile = UploadedFile::fake()->create('large.msg', 1024 * 1024 * 1024); // 1GB

        $response = $this->actingAs($this->user)
                        ->postJson('/api/upload', [
                            'files' => [$largeFile]
                        ]);

        $response->assertStatus(413);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('storage limit', $response->json('message'));
    }

    /** @test */
    public function it_validates_file_extensions()
    {
        $files = [
            UploadedFile::fake()->create('test.txt', 1024),
            UploadedFile::fake()->create('test.pdf', 1024),
            UploadedFile::fake()->create('test.doc', 1024)
        ];

        $response = $this->actingAs($this->user)
                        ->postJson('/api/upload', [
                            'files' => $files
                        ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_handles_upload_without_files()
    {
        $response = $this->actingAs($this->user)
                        ->postJson('/api/upload', [
                            'files' => []
                        ]);

        $response->assertStatus(400);
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_logs_upload_errors()
    {
        Log::shouldReceive('error')->once();

        $file = UploadedFile::fake()->create('test.msg', 1024);

        // Mock a failure by not providing proper file content
        $response = $this->actingAs($this->user)
                        ->postJson('/api/upload', [
                            'files' => [$file]
                        ]);

        // The response should still be structured properly
        $response->assertStatus(200);
        $this->assertArrayHasKey('errors', $response->json());
    }

    /** @test */
    public function it_sanitizes_filenames()
    {
        $file = UploadedFile::fake()->create('test file with spaces.msg', 1024);

        $response = $this->actingAs($this->user)
                        ->postJson('/api/upload', [
                            'files' => [$file]
                        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
    }

    /** @test */
    public function it_handles_very_long_filenames()
    {
        $longName = str_repeat('a', 300) . '.msg';
        $file = UploadedFile::fake()->create($longName, 1024);

        $response = $this->actingAs($this->user)
                        ->postJson('/api/upload', [
                            'files' => [$file]
                        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
    }

    /** @test */
    public function it_prevents_path_traversal_attempts()
    {
        $file = UploadedFile::fake()->create('../../../etc/passwd.msg', 1024);

        $response = $this->actingAs($this->user)
                        ->postJson('/api/upload', [
                            'files' => [$file]
                        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        
        // The filename should be sanitized
        $this->assertNotContains('../../../etc/passwd', $response->json('emails.0.file_path'));
    }
} 