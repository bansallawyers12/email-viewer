# Email Parsing Implementation Guide (Updated)

This document reflects the current implementation and endpoints in the app.

## 1) Key Features
- Parsing `.msg` files via Python `extract-msg` invoked from PHP.
- Robust UTF-8 sanitization for all string and JSON fields.
- Attachment saving with content-type detection (file-content and extension fallback).
- Automatic primary label assignment: Inbox vs Sent based on `sender_email` domain.
- Expanded REST API: upload, progress, storage usage, labels, attachment preview and batch download, PDF/HTML export.

## 2) Database Setup
Migrations already included in `database/migrations` for:
- Emails (`emails`), Attachments (`attachments`).
- Labels (`labels`) and pivot `email_label` for many-to-many between `emails` and `labels`.

Run migrations:
```bash
php artisan migrate
```

## 3) Models (highlights)
Email (`app/Models/Email.php`):
```php
public function labels() {
    return $this->belongsToMany(Label::class, 'email_label')->withTimestamps();
}

public function isSentItem(): bool {
    $userDomain = 'bansalimmigration.com.au';
    return $this->sender_email && str_contains($this->sender_email, $userDomain);
}
```

Attachment (`app/Models/Attachment.php`) helpers include:
- `isImage()`, `isPdf()`, `isDocument()`, `isVideo()`, `isAudio()`, `isArchive()`
- `canPreview()` and `getPreviewType()`
- Scopes: `ofType()`, `inline()`, `regular()`

## 4) Service Layer
`app/Services/MsgParserService.php` includes:
- Python bridge with on-demand script creation at `storage/app/scripts/parse_msg.py`.
- UTF-8 sanitization for safe JSON casting.
- Attachment MIME detection via `mime_content_type` with extension fallback.
- Primary label auto-assignment after successful parse.

Example highlights:
```php
private function detectMimeType(string $filePath, string $fallbackType): string { /* ... */ }

private function assignPrimaryLabels(Email $email): void { /* attaches Inbox/Sent */ }
```

## 5) Python Script
Auto-generated at first run: `storage/app/scripts/parse_msg.py` using `extract_msg` to output JSON with subject, sender, recipients, html/text content, message_id, and attachments (with binary data when present).

Install dependency (Windows PowerShell):
```bash
pip install extract-msg
```
The PHP service invokes Python via `py` command.

## 6) Controllers and Upload Flow
Upload endpoint accepts one or many `.msg` files, stores them under `storage/app/emails`, parses, saves email + attachments, and returns per-file results with errors collected.

POST body field: `files[]` (multipart/form-data)

## 7) Routes (current)
```php
// Web SPA
Route::get('/', fn() => view('app'));

// Emails
Route::prefix('api/emails')->group(function () {
    Route::get('/', [EmailController::class, 'index']);
    Route::delete('/clear-all', [EmailController::class, 'clearAll']);
    Route::get('/{id}', [EmailController::class, 'show']);
    Route::put('/{id}', [EmailController::class, 'update']);
    Route::delete('/{id}', [EmailController::class, 'destroy']);
    Route::get('/{id}/statistics', [EmailController::class, 'statistics']);
    Route::get('/{id}/export-pdf', [EmailController::class, 'exportPdf']);
    Route::get('/{id}/download-pdf', [EmailController::class, 'downloadPdf']);
    Route::get('/{id}/download-html', [EmailController::class, 'downloadHtml']);
});

// Attachments
Route::prefix('api/attachments')->group(function () {
    Route::get('/email/{emailId}', [AttachmentController::class, 'index']);
    Route::get('/{id}', [AttachmentController::class, 'show']);
    Route::get('/{id}/download', [AttachmentController::class, 'download']);
    Route::get('/{id}/preview', [AttachmentController::class, 'preview']);
    Route::get('/email/{emailId}/download-all', [AttachmentController::class, 'downloadAll']);
    Route::get('/email/{emailId}/statistics', [AttachmentController::class, 'statistics']);
});

// Labels
Route::prefix('api/labels')->group(function () {
    Route::get('/', [LabelController::class, 'index']);
    Route::post('/', [LabelController::class, 'store']);
    Route::put('/{id}', [LabelController::class, 'update']);
    Route::delete('/{id}', [LabelController::class, 'destroy']);
    Route::post('/apply', [LabelController::class, 'applyToEmail']);
    Route::delete('/remove', [LabelController::class, 'removeFromEmail']);
    Route::get('/{id}/emails', [LabelController::class, 'getEmailsByLabel']);
});

// Upload
Route::prefix('api/upload')->group(function () {
    Route::post('/', [UploadController::class, 'upload']);
    Route::get('/progress/{emailId}', [UploadController::class, 'progress']);
    Route::get('/storage-usage', [UploadController::class, 'storageUsage']);
    Route::delete('/{emailId}', [UploadController::class, 'delete']);
});
```

## 8) Usage Examples
Upload one or more `.msg` files:
```bash
curl -X POST http://localhost:8000/api/upload \
  -F "files[]=@C:/path/to/email1.msg" \
  -F "files[]=@C:/path/to/email2.msg"
```

List emails, show email, and export/download:
```bash
curl http://localhost:8000/api/emails
curl http://localhost:8000/api/emails/123
curl -OJ http://localhost:8000/api/emails/123/download-pdf
curl -OJ http://localhost:8000/api/emails/123/download-html
```

Attachments:
```bash
curl http://localhost:8000/api/attachments/email/123
curl -OJ http://localhost:8000/api/attachments/456/download
curl -OJ http://localhost:8000/api/attachments/email/123/download-all
```

Labels:
```bash
curl http://localhost:8000/api/labels
```

## 9) Notes
- Windows: Python invoked with `py`. Ensure `extract-msg` is installed in the default Python used by `py`.
- Large/binary attachments are saved under `storage/app/emails/{emailId}/attachments`.
- Primary label assignment requires system labels (`Inbox`, `Sent`) to exist for the user.

## 10) Troubleshooting
- If parse returns error, check `storage/logs/laravel.log` for Python stderr and JSON decode issues.
- Verify `storage/app/scripts/parse_msg.py` exists and is readable.
- For encoding issues, sanitization ensures JSON safety; inspect source values when necessary.
