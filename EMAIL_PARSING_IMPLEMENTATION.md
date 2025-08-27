# Email Parsing Implementation Guide (Updated)

This document reflects the current implementation and endpoints in the app.

## 1) Key Features
- **Advanced .msg parsing** via Python `extract-msg` invoked from PHP with robust error handling
- **UTF-8 sanitization** for all string and JSON fields to prevent encoding issues
- **Smart MIME detection** for attachments using both file content analysis and extension fallback
- **Automatic label assignment** - Inbox vs Sent based on sender domain detection
- **Comprehensive search & filtering** with date ranges, status, sender, labels, and full-text search
- **Enhanced attachment handling** with preview capabilities, batch operations, and statistics
- **Export functionality** - PDF and HTML export for emails
- **Advanced routing** with progress tracking, storage management, and label operations

## 2) Database Schema
Migrations included in `database/migrations`:
- **Emails** (`emails`) - Core email data with HTML/text content, metadata, and status tracking
- **Attachments** (`attachments`) - File storage with MIME detection and preview capabilities  
- **Labels** (`labels`) - User-defined categorization system
- **Email-Label pivot** (`email_label`) - Many-to-many relationship for flexible labeling

Run migrations:
```bash
php artisan migrate
```

## 3) Core Models & Relationships

### Email Model (`app/Models/Email.php`)
```php
// Key relationships
public function user(): BelongsTo
public function attachments(): HasMany  
public function labels() // Many-to-many with labels

// Smart methods
public function isSentItem(): bool // Domain-based sent detection
public function getPrimaryLabelAttribute(): string // Inbox/Sent logic
public function hasPrimaryLabel(): bool
public function scopeSearch($query, $search) // Full-text search
public function scopeForUser($query, $userId) // User isolation
```

### Attachment Model (`app/Models/Attachment.php`)
```php
// Type detection methods
public function isImage(): bool
public function isPdf(): bool  
public function isDocument(): bool
public function isVideo(): bool
public function isAudio(): bool
public function isArchive(): bool

// Preview capabilities
public function canPreview(): bool
public function getPreviewType(): string // 'image', 'pdf', 'none'
public function canConvertToHtml(): bool // For Office documents

// Utility scopes
public function scopeOfType($query, $contentType)
public function scopeInline($query)
public function scopeRegular($query)
```

## 4) Service Layer - MsgParserService

### Core Parsing Flow
```php
public function parseMsgFile(string $filePath, int $userId): Email
{
    // 1. Create initial email record
    $email = $this->createEmailRecord($filePath, $userId);
    
    // 2. Parse with Python extract-msg
    $parsed = $this->parseWithPython($filePath);
    
    // 3. Update email with parsed data
    $this->updateEmailWithParsedData($email, $parsed);
    
    // 4. Process attachments with MIME detection
    $this->processAttachments($email, $parsed);
    
    // 5. Auto-assign Inbox/Sent labels
    $this->assignPrimaryLabels($email);
    
    return $email;
}
```

### Key Features
-- **Python Bridge**: Uses `storage/app/scripts/parse_msg_simple.py` (checked into repo) and invokes Python with robust Windows compatibility
- **UTF-8 Sanitization**: Ensures all data is JSON-safe before database storage
- **MIME Detection**: Combines `mime_content_type()` with comprehensive extension mapping
- **Label Assignment**: Automatically applies Inbox/Sent based on sender domain
- **Error Handling**: Comprehensive logging and status tracking

### MIME Type Detection
```php
private function detectMimeType(string $filePath, string $fallbackType): string
{
    // 1. Try PHP's mime_content_type first
    if (function_exists('mime_content_type') && file_exists($filePath)) {
        $detectedType = mime_content_type($filePath);
        if ($detectedType && $detectedType !== 'application/octet-stream') {
            return $detectedType;
        }
    }
    
    // 2. Fallback to extension-based detection
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'jpg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'zip' => 'application/zip',
        // ... comprehensive mapping for 30+ file types
    ];
    
    return $mimeTypes[$extension] ?? $fallbackType;
}
```

## 5) Python Script (`storage/app/scripts/parse_msg_simple.py`)
Script using `extract_msg` library; called from PHP with Windows-safe paths and logging:

```python
def parse_msg_file(file_path):
    msg = extract_msg.Message(file_path)
    
    data = {
        'subject': safe_json_serialize(msg.subject or ''),
        'sender_name': safe_json_serialize(msg.sender or ''),
        'sender_email': safe_json_serialize(getattr(msg, 'senderEmail', '') or ''),
        'sent_date': safe_json_serialize(msg.date) if msg.date else None,
        'html_content': safe_json_serialize(msg.htmlBody or ''),
        'text_content': safe_json_serialize(msg.body or ''),
        'recipients': [], 'attachments': [], 'headers': {},
        'message_id': safe_json_serialize(getattr(msg, 'messageId', '') or ''),
    }
    
    # Extract recipients and attachments with binary data
    # ... implementation details
```

**Install dependency** (Windows PowerShell):
```bash
py -m pip install extract-msg
```

## 6) Controllers & API Endpoints

### EmailController - Advanced Features
```php
// Search & filtering with multiple parameters
public function index(Request $request): JsonResponse
{
    // Supports: search, status, date_from, date_to, date_filter, 
    // sender, label_id, sort_by, sort_order, per_page, page
    
    // Full-text search across: subject, sender_email, sender_name, 
    // recipients, text_content
    
    // Date filters: today, week, month, year with custom ranges
    
    // Caching support (currently disabled for debugging)
}

// Export functionality  
public function exportPdf(int $id): Response
public function downloadPdf(int $id): Response  
public function downloadHtml(int $id): Response

// Statistics and management
public function statistics(int $id): JsonResponse
public function clearAll(): JsonResponse // Bulk delete for user
```

### AttachmentController - Enhanced Operations
```php
// Comprehensive attachment listing with computed properties
public function index(int $emailId): JsonResponse
{
    // Returns: can_preview, preview_type, is_image, is_pdf, 
    // formatted_file_size, extension, etc.
}

// Download operations
public function download(int $id): Response
public function downloadAll(int $emailId): Response // Batch download

// Preview capabilities
public function preview(int $id): Response

// Statistics
public function statistics(int $emailId): JsonResponse
```

### UploadController - Robust File Handling
```php
public function upload(Request $request): JsonResponse
{
    // Multi-file upload with individual error tracking
    // File validation: .msg only, max 10MB per file
    // Returns: success status, uploaded emails, error details
}

// Additional endpoints
public function progress(int $emailId): JsonResponse
public function storageUsage(): JsonResponse  
public function delete(int $emailId): JsonResponse
```

### LabelController - Flexible Categorization
```php
// CRUD operations
public function store(Request $request): JsonResponse
public function update(Request $request, int $id): JsonResponse
public function destroy(int $id): JsonResponse

// Label application
public function applyToEmail(Request $request): JsonResponse
public function removeFromEmail(Request $request): JsonResponse

// Query operations
public function getEmailsByLabel(int $id): JsonResponse
```

## 7) Complete API Routes

```php
// Main SPA route
Route::get('/', fn() => view('app'));

// Email management
Route::prefix('api/emails')->group(function () {
    Route::get('/', [EmailController::class, 'index']);           // List with search/filter
    Route::delete('/clear-all', [EmailController::class, 'clearAll']); // Bulk delete
    Route::get('/{id}', [EmailController::class, 'show']);       // Show email
    Route::put('/{id}', [EmailController::class, 'update']);     // Update email
    Route::delete('/{id}', [EmailController::class, 'destroy']); // Delete email
    Route::get('/{id}/statistics', [EmailController::class, 'statistics']); // Email stats
    Route::get('/{id}/export-pdf', [EmailController::class, 'exportPdf']);  // PDF export
    Route::get('/{id}/download-pdf', [EmailController::class, 'downloadPdf']); // PDF download
    Route::get('/{id}/download-html', [EmailController::class, 'downloadHtml']); // HTML download
});

// Attachment operations
Route::prefix('api/attachments')->group(function () {
    Route::get('/email/{emailId}', [AttachmentController::class, 'index']);        // List attachments
    Route::get('/{id}', [AttachmentController::class, 'show']);                    // Show attachment
    Route::get('/{id}/download', [AttachmentController::class, 'download']);      // Download single
    Route::get('/{id}/preview', [AttachmentController::class, 'preview']);        // Preview attachment
    Route::get('/email/{emailId}/download-all', [AttachmentController::class, 'downloadAll']); // Batch download
    Route::get('/email/{emailId}/statistics', [AttachmentController::class, 'statistics']);    // Attachment stats
});

// Label management
Route::prefix('api/labels')->group(function () {
    Route::get('/', [LabelController::class, 'index']);                    // List labels
    Route::post('/', [LabelController::class, 'store']);                   // Create label
    Route::put('/{id}', [LabelController::class, 'update']);              // Update label
    Route::delete('/{id}', [LabelController::class, 'destroy']);          // Delete label
    Route::post('/apply', [LabelController::class, 'applyToEmail']);      // Apply label to email
    Route::delete('/remove', [LabelController::class, 'removeFromEmail']); // Remove label from email
    Route::get('/{id}/emails', [LabelController::class, 'getEmailsByLabel']); // Get emails by label
});

// File upload & management
Route::prefix('api/upload')->group(function () {
    Route::post('/', [UploadController::class, 'upload']);                // Upload .msg files
    Route::get('/progress/{emailId}', [UploadController::class, 'progress']); // Upload progress
    Route::get('/storage-usage', [UploadController::class, 'storageUsage']);  // Storage statistics
    Route::delete('/{emailId}', [UploadController::class, 'delete']);     // Delete uploaded email
});
```

## 8) Usage Examples

### Upload .msg Files
```bash
# Single file
curl -X POST http://localhost:8000/api/upload \
  -F "files[]=@C:/path/to/email.msg"

# Multiple files
curl -X POST http://localhost:8000/api/upload \
  -F "files[]=@C:/path/to/email1.msg" \
  -F "files[]=@C:/path/to/email2.msg"
```

### Search & Filter Emails
```bash
# Basic search
curl "http://localhost:8000/api/emails?search=invoice&per_page=20"

# Advanced filtering
curl "http://localhost:8000/api/emails?date_filter=month&sender=client@example.com&label_id=5&sort_by=sent_date&sort_order=desc"

# Date range search
curl "http://localhost:8000/api/emails?date_from=2024-01-01&date_to=2024-01-31"
```

### Export & Download
```bash
# Export to PDF
curl -OJ http://localhost:8000/api/emails/123/download-pdf

# Export to HTML
curl -OJ http://localhost:8000/api/emails/123/download-html

# Download attachment
curl -OJ http://localhost:8000/api/attachments/456/download

# Batch download all attachments for an email
curl -OJ http://localhost:8000/api/attachments/email/123/download-all
```

### Label Operations
```bash
# Create label
curl -X POST http://localhost:8000/api/labels \
  -H "Content-Type: application/json" \
  -d '{"name":"Important","color":"#ff0000","type":"custom"}'

# Apply label to email
curl -X POST http://localhost:8000/api/labels/apply \
  -H "Content-Type: application/json" \
  -d '{"email_id":123,"label_id":5}'

# Get emails by label
curl http://localhost:8000/api/labels/5/emails
```

## 9) Technical Implementation Details

### UTF-8 Sanitization
```php
private function sanitizeForJson($value)
{
    if (is_string($value)) {
        // Check if already valid UTF-8
        if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }
        
        // Try common conversions: UTF-8, ISO-8859-1, Windows-1252
        $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        if ($converted === false) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        }
        return $converted !== false ? $converted : '';
    }
    
    // Handle arrays recursively
    if (is_array($value)) {
        return array_map([$this, 'sanitizeForJson'], $value);
    }
    
    return $value;
}
```

### Automatic Label Assignment
```php
private function assignPrimaryLabels(Email $email): void
{
    // Get system labels for user
    $inboxLabel = Label::forUser($email->user_id)->where('name', 'Inbox')->first();
    $sentLabel = Label::forUser($email->user_id)->where('name', 'Sent')->first();
    
    if (!$inboxLabel || !$sentLabel) {
        Log::warning('System labels not found for user', ['user_id' => $email->user_id]);
        return;
    }
    
    // Determine label based on sender domain
    $labelToApply = $email->isSentItem() ? $sentLabel : $inboxLabel;
    
    if ($labelToApply && $email->labels()->count() === 0) {
        $email->labels()->attach($labelToApply->id);
    }
}
```

## 10) Configuration & Dependencies

### Python Requirements
```bash
# Windows (PowerShell)
py -m pip install extract-msg

# The service uses the Python script at:
# storage/app/scripts/parse_msg_simple.py
```

### File Storage Structure
```
storage/app/
├── emails/
│   ├── {email_id}/
│   │   ├── email.msg
│   │   └── attachments/
│   │       ├── document1.pdf
│   │       ├── image1.jpg
│   │       └── spreadsheet.xlsx
├── scripts/
│   └── parse_msg_simple.py
└── exports/
    ├── pdf/
    └── html/
```

### Environment Variables
```env
# Ensure these are set in your .env file
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=sqlite
```

## 11) Troubleshooting & Debugging

### Common Issues
1. **Python not found**: Ensure `py` command works in PowerShell. If you see "Python was not found; run without arguments to install", open Settings → Apps → Advanced app settings → App execution aliases and disable the Store aliases for Python, then install Python from python.org.
2. **extract-msg not installed**: Run `py -m pip install extract-msg`
3. **Permission errors**: Check storage directory permissions
4. **Encoding issues**: Check Laravel logs for sanitization warnings

### Debug Commands
```bash
# Check Python availability
py --version

# Verify extract-msg installation
py -c "import extract_msg; print('OK')"

# Check storage permissions
ls -la storage/app/emails/

# View Laravel logs
tail -f storage/logs/laravel.log
```

### Windows Compatibility Notes
- The service prioritizes the `py` launcher, then falls back to `python3` and `python`.
- File paths sent to Python are normalized to forward slashes to avoid quoting/escaping issues on Windows.
- Additional logging has been added:
  - Logs each attempted Python command
  - Logs command output when detection fails
  - Distinguishes between "command not found" and runtime errors

If uploads fail, check `storage/logs/laravel.log` around the time of the upload for entries like:
```text
INFO: Trying Python parsing command {command: "py \"...\" \"...\" 2>&1"}
WARNING: No JSON found in Python output {output: "..."}
ERROR: Failed to parse .msg file: Unable to parse .msg file with Python
```

### Performance Notes
- **Caching**: Currently disabled for debugging, can be re-enabled
- **Batch operations**: Upload supports multiple files simultaneously
- **Memory management**: Large attachments are streamed, not loaded entirely into memory
- **Database indexing**: Proper indexes on user_id, sent_date, and search fields

This implementation provides a robust, feature-rich email parsing system with comprehensive API endpoints, advanced search capabilities, and professional-grade error handling.
