<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response; // Added for Response
use App\Models\Email;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Exception;
use Illuminate\Validation\ValidationException;

class EmailController extends Controller
{
    /**
     * Display a listing of emails with search and filtering.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'search' => 'nullable|string|max:255',
                'status' => 'nullable|string|in:pending,processing,completed,failed',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'date_filter' => 'nullable|string|in:today,week,month,year',
                'sender' => 'nullable|string|max:255',
                'sort_by' => 'nullable|string|in:sent_date,date,subject,sender_email,file_size,created_at',
                'sort_order' => 'nullable|string|in:asc,desc',
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid request parameters',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = Auth::id() ?? 1; // Use user ID 1 as default if not authenticated
            
            // Cache temporarily disabled for debugging
            // $cacheKey = "emails_{$userId}_" . md5($request->getQueryString());
            // 
            // // Try to get from cache first
            // $cachedResult = Cache::get($cacheKey);
            // if ($cachedResult && !$request->has('nocache')) {
            //     return response()->json($cachedResult);
            // }

            $query = Email::where('user_id', $userId)
                         ->with(['attachments' => function ($query) {
                             $query->select('id', 'email_id', 'filename', 'content_type', 'file_size')
                                   ->orderBy('filename');
                         }, 'labels' => function ($query) {
                             $query->select('labels.id', 'labels.name', 'labels.color', 'labels.type', 'labels.icon')
                                   ->orderBy('labels.name');
                         }]);
            
            // Search functionality with multiple fields
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('subject', 'like', "%{$searchTerm}%")
                      ->orWhere('sender_email', 'like', "%{$searchTerm}%")
                      ->orWhere('sender_name', 'like', "%{$searchTerm}%")
                      ->orWhere('recipients', 'like', "%{$searchTerm}%")
                      ->orWhere('text_content', 'like', "%{$searchTerm}%");
                });
            }
            
            // Status filter
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }
            
            // Date range filter
            if ($request->has('date_from')) {
                $query->where('sent_date', '>=', $request->date_from);
            }
            
            if ($request->has('date_to')) {
                $query->where('sent_date', '<=', $request->date_to . ' 23:59:59');
            }
            
            // Date filter (today, week, month, year)
            if ($request->has('date_filter') && !empty($request->date_filter)) {
                $now = now();
                $today = $now->startOfDay();
                
                switch ($request->date_filter) {
                    case 'today':
                        $query->where('sent_date', '>=', $today);
                        break;
                    case 'week':
                        $weekAgo = $today->copy()->subDays(7);
                        $query->where('sent_date', '>=', $weekAgo);
                        break;
                    case 'month':
                        $monthAgo = $today->copy()->subMonth();
                        $query->where('sent_date', '>=', $monthAgo);
                        break;
                    case 'year':
                        $yearAgo = $today->copy()->subYear();
                        $query->where('sent_date', '>=', $yearAgo);
                        break;
                }
            }
            
            // Sender filter
            if ($request->has('sender') && !empty($request->sender)) {
                $query->where(function ($q) use ($request) {
                    $q->where('sender_email', 'like', '%' . $request->sender . '%')
                      ->orWhere('sender_name', 'like', '%' . $request->sender . '%');
                });
            }
            
            // Attachment filter
            if ($request->has('has_attachments')) {
                if ($request->has_attachments === 'true') {
                    $query->whereHas('attachments');
                } elseif ($request->has_attachments === 'false') {
                    $query->whereDoesntHave('attachments');
                }
            }
            
            // File size filter
            if ($request->has('size_filter')) {
                switch ($request->size_filter) {
                    case 'small':
                        $query->where('file_size', '<', 1024 * 1024); // < 1MB
                        break;
                    case 'medium':
                        $query->whereBetween('file_size', [1024 * 1024, 5 * 1024 * 1024]); // 1-5MB
                        break;
                    case 'large':
                        $query->where('file_size', '>', 5 * 1024 * 1024); // > 5MB
                        break;
                }
            }
            
            // Sorting
            $sortBy = $request->get('sort_by', 'sent_date');
            $sortOrder = $request->get('sort_order', 'desc');
            
            // Map frontend sort fields to database fields
            $sortFieldMap = [
                'date' => 'sent_date',
                'sent_date' => 'sent_date',
                'subject' => 'subject',
                'sender_email' => 'sender_email',
                'file_size' => 'file_size',
                'created_at' => 'created_at'
            ];
            
            if (isset($sortFieldMap[$sortBy])) {
                $query->orderBy($sortFieldMap[$sortBy], $sortOrder);
            }
            
            // Pagination
            $perPage = min($request->get('per_page', 20), 100); // Max 100 per page
            $emails = $query->paginate($perPage);
            
            $result = [
                'success' => true,
                'emails' => $emails->items(),
                'pagination' => [
                    'current_page' => $emails->currentPage(),
                    'last_page' => $emails->lastPage(),
                    'per_page' => $emails->perPage(),
                    'total' => $emails->total(),
                    'from' => $emails->firstItem(),
                    'to' => $emails->lastItem(),
                ],
                'filters' => [
                    'search' => $request->get('search'),
                    'status' => $request->get('status'),
                    'date_from' => $request->get('date_from'),
                    'date_to' => $request->get('date_to'),
                    'sender' => $request->get('sender'),
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder
                ]
            ];
            
            // Cache temporarily disabled for debugging
            // Cache::put($cacheKey, $result, 300);
            
            return response()->json($result);
            
        } catch (Exception $e) {
            Log::error('Email listing failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve emails. Please try again.'
            ], 500);
        }
    }
    
    /**
     * Display the specified email with full details.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $email = Email::where('user_id', Auth::id() ?? 1)
                         ->with(['attachments' => function ($query) {
                             $query->orderBy('filename');
                         }, 'labels' => function ($query) {
                             $query->select('labels.id', 'labels.name', 'labels.color', 'labels.type', 'labels.icon')
                                   ->orderBy('labels.name');
                         }])
                         ->findOrFail($id);
            
            // Update last accessed timestamp
            $email->update(['last_accessed_at' => now()]);
            
            return response()->json([
                'success' => true,
                'email' => $email
            ]);
            
        } catch (Exception $e) {
            Log::error('Email retrieval failed', [
                'email_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Email not found or access denied'
            ], 404);
        }
    }
    
    /**
     * Update the specified email.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'tags' => 'nullable|string|max:500',
                'notes' => 'nullable|string|max:1000',
                'is_important' => 'nullable|boolean',
                'is_read' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $email = Email::where('user_id', Auth::id() ?? 1)
                         ->findOrFail($id);
            
            $email->update($request->only(['tags', 'notes', 'is_important', 'is_read']));
            
            // Clear cache for this user
            $this->clearUserCache(Auth::id() ?? 1);
            
            Log::info('Email updated successfully', [
                'email_id' => $id,
                'user_id' => Auth::id() ?? 1,
                'updated_fields' => array_keys($request->only(['tags', 'notes', 'is_important', 'is_read']))
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Email updated successfully',
                'email' => $email->fresh()
            ]);
            
        } catch (Exception $e) {
            Log::error('Email update failed', [
                'email_id' => $id,
                'user_id' => Auth::id() ?? 1,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update email'
            ], 500);
        }
    }
    
    /**
     * Remove the specified email from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $email = Email::where('user_id', Auth::id() ?? 1)
                         ->findOrFail($id);
            
            // Delete associated files
            if ($email->file_path && file_exists($email->file_path)) {
                if (!unlink($email->file_path)) {
                    Log::warning('Failed to delete email file', [
                        'email_id' => $id,
                        'file_path' => $email->file_path
                    ]);
                }
            }
            
            // Delete attachments
            foreach ($email->attachments as $attachment) {
                if ($attachment->file_path && file_exists($attachment->file_path)) {
                    if (!unlink($attachment->file_path)) {
                        Log::warning('Failed to delete attachment file', [
                            'email_id' => $id,
                            'attachment_id' => $attachment->id,
                            'file_path' => $attachment->file_path
                        ]);
                    }
                }
            }
            
            // Delete from database
            $email->delete();
            
            // Clear cache for this user
            $this->clearUserCache(Auth::id() ?? 1);
            
            Log::info('Email deleted successfully', [
                'email_id' => $id,
                'user_id' => Auth::id() ?? 1
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Email deleted successfully'
            ]);
            
        } catch (Exception $e) {
            Log::error('Email deletion failed', [
                'email_id' => $id,
                'user_id' => Auth::id() ?? 1,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete email'
            ], 500);
        }
    }
    
    /**
     * Get email statistics.
     */
    public function statistics(): JsonResponse
    {
        try {
            $userId = Auth::id() ?? 1;
            $cacheKey = "email_stats_{$userId}";
            
            // Try to get from cache
            $cachedStats = Cache::get($cacheKey);
            if ($cachedStats) {
                return response()->json([
                    'success' => true,
                    'statistics' => $cachedStats
                ]);
            }
            
            $stats = [
                'total_emails' => Email::where('user_id', $userId)->count(),
                'total_size' => Email::where('user_id', $userId)->sum('file_size'),
                'emails_with_attachments' => Email::where('user_id', $userId)
                                                   ->whereHas('attachments')
                                                   ->count(),
                'total_attachments' => Email::where('user_id', $userId)
                                          ->withCount('attachments')
                                          ->get()
                                          ->sum('attachments_count'),
                'recent_emails' => Email::where('user_id', $userId)
                                      ->where('created_at', '>=', now()->subDays(7))
                                      ->count(),
                'status_breakdown' => Email::where('user_id', $userId)
                                         ->selectRaw('status, count(*) as count')
                                         ->groupBy('status')
                                         ->pluck('count', 'status')
                                         ->toArray()
            ];
            
            // Cache for 10 minutes
            Cache::put($cacheKey, $stats, 600);
            
            return response()->json([
                'success' => true,
                'statistics' => $stats
            ]);
            
        } catch (Exception $e) {
            Log::error('Email statistics failed', [
                'user_id' => Auth::id() ?? 1,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics'
            ], 500);
        }
    }
    
    /**
     * Export email as PDF.
     */
    public function exportPdf(int $id): JsonResponse
    {
        try {
            $email = Email::where('user_id', Auth::id() ?? 1)
                         ->with('attachments')
                         ->findOrFail($id);
            
            // Check if PDF already exists and is recent (less than 1 hour old)
            $pdfPath = storage_path("app/exports/email_{$id}.pdf");
            $exportDir = dirname($pdfPath);
            
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }
            
            if (file_exists($pdfPath) && (time() - filemtime($pdfPath)) < 3600) {
                return response()->json([
                    'success' => true,
                    'pdf_url' => "/api/emails/{$id}/download-pdf"
                ]);
            }
            
            // Generate PDF content
            $htmlContent = $this->generateEmailHtml($email);
            
            // Create PDF using Dompdf
            if (class_exists('\Dompdf\Dompdf')) {
                $dompdf = new \Dompdf\Dompdf();
                $dompdf->loadHtml($htmlContent);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                
                // Save PDF to file
                $pdfContent = $dompdf->output();
                file_put_contents($pdfPath, $pdfContent);
                
                return response()->json([
                    'success' => true,
                    'pdf_url' => "/api/emails/{$id}/download-pdf"
                ]);
            } else {
                // Fallback: create a simple HTML file that can be printed as PDF
                $htmlPath = storage_path("app/exports/email_{$id}.html");
                file_put_contents($htmlPath, $htmlContent);
                
                return response()->json([
                    'success' => true,
                    'message' => 'HTML version created. Please print to PDF manually.',
                    'html_url' => "/api/emails/{$id}/download-html"
                ]);
            }
            
        } catch (Exception $e) {
            Log::error('PDF export failed', [
                'email_id' => $id,
                'user_id' => Auth::id() ?? 1,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export PDF: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Download the generated PDF.
     */
    public function downloadPdf(int $id)
    {
        try {
            $email = Email::where('user_id', Auth::id() ?? 1)->findOrFail($id);
            
            $pdfPath = storage_path("app/exports/email_{$id}.pdf");
            
            if (!file_exists($pdfPath)) {
                throw new Exception('PDF not found. Please export the email first.');
            }
            
            $headers = [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="email_' . $id . '.pdf"',
                'Content-Length' => filesize($pdfPath),
            ];
            
            return response()->file($pdfPath, $headers);
            
        } catch (Exception $e) {
            Log::error('PDF download failed: ' . $e->getMessage());
            return response('PDF not found', 404);
        }
    }
    
    /**
     * Download the generated HTML version.
     */
    public function downloadHtml(int $id)
    {
        try {
            $email = Email::where('user_id', Auth::id() ?? 1)->findOrFail($id);
            
            $htmlPath = storage_path("app/exports/email_{$id}.html");
            
            if (!file_exists($htmlPath)) {
                throw new Exception('HTML not found. Please export the email first.');
            }
            
            $headers = [
                'Content-Type' => 'text/html',
                'Content-Disposition' => 'attachment; filename="email_' . $id . '.html"',
                'Content-Length' => filesize($htmlPath),
            ];
            
            return response()->file($htmlPath, $headers);
            
        } catch (Exception $e) {
            Log::error('HTML download failed: ' . $e->getMessage());
            return response('HTML not found', 404);
        }
    }
    
    /**
     * Generate HTML content for PDF export.
     */
    private function generateEmailHtml(Email $email): string
    {
        $attachmentsList = '';
        if ($email->attachments->isNotEmpty()) {
            $attachmentsList = '<h3>Attachments:</h3><ul>';
            foreach ($email->attachments as $attachment) {
                $attachmentsList .= '<li>' . htmlspecialchars($attachment->filename) . ' (' . $attachment->formatted_file_size . ')</li>';
            }
            $attachmentsList .= '</ul>';
        }
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Email: ' . htmlspecialchars($email->subject) . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
                .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                .subject { font-size: 24px; font-weight: bold; color: #333; margin-bottom: 10px; }
                .meta { color: #666; font-size: 14px; }
                .content { margin: 20px 0; }
                .attachments { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ccc; }
                .attachments ul { list-style-type: none; padding: 0; }
                .attachments li { padding: 5px 0; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="subject">' . htmlspecialchars($email->subject) . '</div>
                <div class="meta">
                    <strong>From:</strong> ' . htmlspecialchars($email->sender_name ?: $email->sender_email) . '<br>
                    <strong>To:</strong> ' . htmlspecialchars(is_array($email->recipients) ? implode(', ', $email->recipients) : ($email->recipients ?? 'Unknown')) . '<br>
                    <strong>Date:</strong> ' . ($email->sent_date ? $email->sent_date->format('F j, Y \a\t g:i A') : 'Unknown') . '<br>
                    <strong>File:</strong> ' . htmlspecialchars(basename($email->file_path)) . '
                </div>
            </div>
            
            <div class="content">
                ' . ($email->html_content ?: nl2br(htmlspecialchars($email->text_content))) . '
            </div>
            
            <div class="attachments">
                ' . $attachmentsList . '
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Clear all emails for the current user.
     */
    public function clearAll(): JsonResponse
    {
        try {
            $userId = Auth::id() ?? 1;
            
            // Get all emails for the user
            $emails = Email::where('user_id', $userId)->with('attachments')->get();
            
            $deletedCount = 0;
            $deletedSize = 0;
            
            foreach ($emails as $email) {
                // Delete associated files
                if ($email->file_path && file_exists($email->file_path)) {
                    unlink($email->file_path);
                    $deletedSize += $email->file_size;
                }
                
                // Delete attachments
                foreach ($email->attachments as $attachment) {
                    if ($attachment->file_path && file_exists($attachment->file_path)) {
                        unlink($attachment->file_path);
                        $deletedSize += $attachment->file_size;
                    }
                }
                
                $email->delete();
                $deletedCount++;
            }
            
            // Clear cache
            $this->clearUserCache($userId);
            
            Log::info('All emails cleared', [
                'user_id' => $userId,
                'deleted_count' => $deletedCount,
                'deleted_size' => $deletedSize
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$deletedCount} emails",
                'deleted_count' => $deletedCount,
                'deleted_size' => $deletedSize
            ]);
            
        } catch (Exception $e) {
            Log::error('Clear all emails failed', [
                'user_id' => Auth::id() ?? 1,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear emails'
            ], 500);
        }
    }
    
    /**
     * Clear cache for a specific user
     */
    private function clearUserCache(int $userId): void
    {
        $pattern = "emails_{$userId}_*";
        $keys = Cache::get($pattern) ?? [];
        
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        
        // Also clear statistics cache
        Cache::forget("email_stats_{$userId}");
    }
}

