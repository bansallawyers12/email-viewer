# Email Parsing Implementation Instructions

## 1. Database Setup

### Create Email Migration
```bash
php artisan make:migration create_emails_table
```

**Migration Content:**
```php
Schema::create('emails', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('subject')->nullable();
    $table->string('sender_name')->nullable();
    $table->string('sender_email')->nullable();
    $table->text('recipients')->nullable(); // JSON array
    $table->text('cc')->nullable(); // JSON array
    $table->text('bcc')->nullable(); // JSON array
    $table->datetime('sent_date')->nullable();
    $table->datetime('received_date')->nullable();
    $table->string('file_path');
    $table->string('file_name');
    $table->bigInteger('file_size');
    $table->longText('html_content')->nullable();
    $table->text('text_content')->nullable();
    $table->text('raw_content')->nullable();
    $table->json('headers')->nullable();
    $table->string('message_id')->nullable();
    $table->string('thread_id')->nullable();
    $table->json('tags')->nullable();
    $table->enum('status', ['processed', 'processing', 'error'])->default('processing');
    $table->text('error_message')->nullable();
    $table->timestamps();
    
    $table->index(['user_id', 'sent_date']);
    $table->index(['user_id', 'subject']);
    $table->index(['user_id', 'sender_email']);
    $table->index('message_id');
    $table->index('thread_id');
});
```

### Create Attachment Migration
```bash
php artisan make:migration create_attachments_table
```

**Migration Content:**
```php
Schema::create('attachments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('email_id')->constrained()->onDelete('cascade');
    $table->string('filename');
    $table->string('display_name')->nullable();
    $table->string('content_type')->nullable();
    $table->string('file_path');
    $table->bigInteger('file_size');
    $table->string('content_id')->nullable();
    $table->boolean('is_inline')->default(false);
    $table->text('description')->nullable();
    $table->json('headers')->nullable();
    $table->timestamps();
    
    $table->index(['email_id', 'filename']);
    $table->index('content_type');
    $table->index('is_inline');
});
```

## 2. Models

### Create Email Model
```bash
php artisan make:model Email
```

**Model Content:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Email extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'subject', 'sender_name', 'sender_email', 'recipients', 'cc', 'bcc',
        'sent_date', 'received_date', 'file_path', 'file_name', 'file_size',
        'html_content', 'text_content', 'raw_content', 'headers', 'message_id',
        'thread_id', 'tags', 'status', 'error_message',
    ];

    protected $casts = [
        'sent_date' => 'datetime',
        'received_date' => 'datetime',
        'recipients' => 'array',
        'cc' => 'array',
        'bcc' => 'array',
        'headers' => 'array',
        'tags' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }
}
```

### Create Attachment Model
```bash
php artisan make:model Attachment
```

**Model Content:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_id', 'filename', 'display_name', 'content_type', 'file_path',
        'file_size', 'content_id', 'is_inline', 'description', 'headers',
    ];

    protected $casts = [
        'is_inline' => 'boolean',
        'headers' => 'array',
    ];

    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }
}
```

## 3. Service Layer

### Create MsgParserService
```bash
php artisan make:service MsgParserService
```

**Service Content:**
```php
<?php

namespace App\Services;

use App\Models\Email;
use App\Models\Attachment;
use Illuminate\Support\Facades\Log;
use Exception;

class MsgParserService
{
    public function parseMsgFile(string $filePath, int $userId): Email
    {
        try {
            $email = $this->createEmailRecord($filePath, $userId);
            $parsed = $this->parseWithPython($filePath);
            
            if (!$parsed) {
                throw new Exception('Unable to parse .msg file with Python');
            }
            
            $this->updateEmailWithParsedData($email, $parsed);
            $this->processAttachments($email, $parsed);
            $email->update(['status' => 'processed']);
            
            return $email;
        } catch (Exception $e) {
            Log::error('Failed to parse .msg file: ' . $e->getMessage());
            if (isset($email)) {
                $email->update(['status' => 'error', 'error_message' => $e->getMessage()]);
            }
            throw $e;
        }
    }
    
    private function createEmailRecord(string $filePath, int $userId): Email
    {
        $fileInfo = pathinfo($filePath);
        return Email::create([
            'user_id' => $userId,
            'file_path' => $filePath,
            'file_name' => $fileInfo['basename'],
            'file_size' => filesize($filePath),
            'status' => 'processing'
        ]);
    }
    
    private function parseWithPython(string $filePath): ?array
    {
        try {
            $pythonScript = storage_path('app/scripts/parse_msg.py');
            if (!file_exists($pythonScript)) {
                $this->createPythonScript($pythonScript);
            }
            
            $command = "py \"{$pythonScript}\" " . escapeshellarg($filePath);
            $output = shell_exec($command);
            
            if ($output) {
                $parsed = json_decode($output, true);
                if (json_last_error() === JSON_ERROR_NONE && !isset($parsed['error'])) {
                    return $parsed;
                }
            }
            return null;
        } catch (Exception $e) {
            Log::warning('Python parsing failed: ' . $e->getMessage());
            return null;
        }
    }
    
    private function createPythonScript(string $scriptPath): void
    {
        $script = <<<'PYTHON'
#!/usr/bin/env python3
import sys
import json
import os
from datetime import datetime

try:
    import extract_msg
except ImportError:
    print(json.dumps({'error': 'extract_msg library not installed'}))
    sys.exit(1)

def safe_json_serialize(obj):
    if isinstance(obj, bytes):
        return obj.decode('utf-8', errors='ignore')
    elif isinstance(obj, datetime):
        return obj.isoformat()
    elif hasattr(obj, '__dict__'):
        return str(obj)
    else:
        return str(obj)

def parse_msg_file(file_path):
    try:
        msg = extract_msg.Message(file_path)
        
        data = {
            'subject': safe_json_serialize(msg.subject or ''),
            'sender_name': safe_json_serialize(msg.sender or ''),
            'sender_email': safe_json_serialize(getattr(msg, 'senderEmail', '') or ''),
            'sent_date': safe_json_serialize(msg.date) if msg.date else None,
            'html_content': safe_json_serialize(msg.htmlBody or ''),
            'text_content': safe_json_serialize(msg.body or ''),
            'recipients': [],
            'attachments': [],
            'headers': {},
            'message_id': safe_json_serialize(getattr(msg, 'messageId', '') or ''),
        }
        
        if hasattr(msg, 'to') and msg.to:
            recipients = [email.strip() for email in msg.to.split(',')]
            data['recipients'] = [safe_json_serialize(email) for email in recipients]
        
        for attachment in msg.attachments:
            data['attachments'].append({
                'filename': safe_json_serialize(attachment.longFilename or attachment.shortFilename),
                'content_type': safe_json_serialize(getattr(attachment, 'contentType', 'application/octet-stream') or 'application/octet-stream'),
                'content_id': safe_json_serialize(getattr(attachment, 'contentId', '') or ''),
                'is_inline': bool(getattr(attachment, 'contentId', None)),
                'size': len(attachment.data) if attachment.data else 0,
                'data': attachment.data if attachment.data else None,
            })
        
        print(json.dumps(data))
        
    except Exception as e:
        print(json.dumps({'error': str(e)}))

if __name__ == '__main__':
    if len(sys.argv) != 2:
        print(json.dumps({'error': 'Usage: python script.py <msg_file_path>'}))
        sys.exit(1)
    
    parse_msg_file(sys.argv[1])
PYTHON;
        
        $scriptsDir = dirname($scriptPath);
        if (!is_dir($scriptsDir)) {
            mkdir($scriptsDir, 0755, true);
        }
        file_put_contents($scriptPath, $script);
        chmod($scriptPath, 0755);
    }
    
    private function updateEmailWithParsedData(Email $email, array $parsed): void
    {
        $sanitized = [
            'subject' => $this->sanitizeForJson($parsed['subject'] ?? null),
            'sender_name' => $this->sanitizeForJson($parsed['sender_name'] ?? null),
            'sender_email' => $this->sanitizeForJson($parsed['sender_email'] ?? null),
            'sent_date' => $this->parseDate($parsed['sent_date'] ?? null),
            'html_content' => $this->sanitizeForJson($parsed['html_content'] ?? null),
            'text_content' => $this->sanitizeForJson($parsed['text_content'] ?? null),
            'recipients' => $this->sanitizeForJson($parsed['recipients'] ?? []),
            'headers' => $this->sanitizeForJson($parsed['headers'] ?? []),
            'message_id' => $this->sanitizeForJson($parsed['message_id'] ?? null),
        ];
        $email->update($sanitized);
    }
    
    private function processAttachments(Email $email, array $parsed): void
    {
        if (!isset($parsed['attachments']) || empty($parsed['attachments'])) {
            return;
        }
        
        $attachmentDir = storage_path("app/emails/{$email->id}/attachments");
        if (!is_dir($attachmentDir)) {
            mkdir($attachmentDir, 0755, true);
        }
        
        foreach ($parsed['attachments'] as $attachmentData) {
            $this->createAttachmentRecord($email, $attachmentData, $attachmentDir);
        }
    }
    
    private function createAttachmentRecord(Email $email, array $attachmentData, string $attachmentDir): void
    {
        $filename = $attachmentData['filename'] ?? 'unknown';
        $filePath = $attachmentDir . '/' . $filename;
        
        if (isset($attachmentData['data']) && $attachmentData['data'] !== null) {
            $data = $attachmentData['data'];
            if (is_string($data) && base64_encode(base64_decode($data, true)) === $data) {
                $data = base64_decode($data);
            }
            file_put_contents($filePath, $data);
        }
        
        Attachment::create([
            'email_id' => $email->id,
            'filename' => $this->sanitizeForJson($filename),
            'display_name' => $this->sanitizeForJson($attachmentData['display_name'] ?? $filename),
            'content_type' => $this->sanitizeForJson($attachmentData['content_type'] ?? 'application/octet-stream'),
            'file_path' => $filePath,
            'file_size' => $attachmentData['size'] ?? 0,
            'content_id' => $this->sanitizeForJson($attachmentData['content_id'] ?? null),
            'is_inline' => $attachmentData['is_inline'] ?? false,
            'description' => $this->sanitizeForJson($attachmentData['description'] ?? null),
            'headers' => $this->sanitizeForJson($attachmentData['headers'] ?? []),
        ]);
    }
    
    private function parseDate(?string $dateString): ?string
    {
        if (!$dateString) return null;
        try {
            return \Carbon\Carbon::parse($dateString)->toDateTimeString();
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function sanitizeForJson($value)
    {
        if (is_array($value)) {
            $sanitizedArray = [];
            foreach ($value as $key => $val) {
                $sanitizedKey = is_string($key) ? $this->sanitizeForJson($key) : $key;
                $sanitizedArray[$sanitizedKey] = $this->sanitizeForJson($val);
            }
            return $sanitizedArray;
        }

        if (is_string($value)) {
            if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
                return $value;
            }
            $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            if ($converted === false) {
                $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
            }
            return $converted !== false ? $converted : '';
        }

        return $value;
    }
}
```

## 4. Controller

### Create UploadController
```bash
php artisan make:controller UploadController
```

**Controller Content:**
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\MsgParserService;
use App\Models\Email;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class UploadController extends Controller
{
    protected MsgParserService $msgParserService;
    
    public function __construct(MsgParserService $msgParserService)
    {
        $this->msgParserService = $msgParserService;
    }
    
    public function upload(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'files.*' => ['required', 'file', 'mimes:msg', 'max:10240']
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $uploadedEmails = [];
            $errors = [];
            
            foreach ($request->file('files') as $file) {
                try {
                    $email = $this->processUploadedFile($file);
                    $uploadedEmails[] = $email;
                } catch (Exception $e) {
                    $errors[] = [
                        'filename' => $file->getClientOriginalName(),
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            return response()->json([
                'success' => count($uploadedEmails) > 0,
                'emails' => $uploadedEmails,
                'errors' => $errors
            ]);
            
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Upload failed'], 500);
        }
    }
    
    private function processUploadedFile($file): Email
    {
        $userId = Auth::id() ?? 1;
        $originalName = $file->getClientOriginalName();
        $safeName = $this->sanitizeFilename($originalName);
        $filename = time() . '_' . uniqid() . '_' . $safeName;
        $filePath = storage_path('app/emails/' . $filename);
        
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        if (!$file->move($directory, $filename)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        return $this->msgParserService->parseMsgFile($filePath, $userId);
    }
    
    private function sanitizeFilename(string $filename): string
    {
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        if (strlen($filename) > 255) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $filename = substr($name, 0, 255 - strlen($extension) - 1) . '.' . $extension;
        }
        return $filename;
    }
}
```

## 5. Routes

### Add Routes
```php
// In routes/web.php or routes/api.php
Route::prefix('api/upload')->group(function () {
    Route::post('/', [UploadController::class, 'upload']);
});

Route::prefix('api/emails')->group(function () {
    Route::get('/', [EmailController::class, 'index']);
    Route::get('/{id}', [EmailController::class, 'show']);
    Route::delete('/{id}', [EmailController::class, 'destroy']);
});

Route::prefix('api/attachments')->group(function () {
    Route::get('/email/{emailId}', [AttachmentController::class, 'index']);
    Route::get('/{id}/download', [AttachmentController::class, 'download']);
});
```

## 6. Python Dependencies

### Install Python Requirements
```bash
pip install extract-msg
```

## 7. Run Migrations
```bash
php artisan migrate
```

## 8. Usage
- Upload .msg files via POST to `/api/upload`
- Retrieve emails via GET `/api/emails`
- Download attachments via GET `/api/attachments/{id}/download`
