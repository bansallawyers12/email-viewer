<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\Models\Attachment;
use App\Models\Email;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class AttachmentController extends Controller
{
    /**
     * Display a listing of attachments for a specific email.
     */
    public function index(int $emailId): JsonResponse
    {
        try {
            $email = Email::where('user_id', Auth::id() ?? 1)
                         ->findOrFail($emailId);
            
            $attachments = $email->attachments()->get();
            
            // Enhance attachments with computed properties
            $attachments = $attachments->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'display_name' => $attachment->display_name,
                    'content_type' => $attachment->content_type,
                    'file_path' => $attachment->file_path,
                    'file_size' => $attachment->file_size,
                    'formatted_file_size' => $attachment->formatted_file_size,
                    'content_id' => $attachment->content_id,
                    'is_inline' => $attachment->is_inline,
                    'description' => $attachment->description,
                    'headers' => $attachment->headers,
                    'extension' => $attachment->extension,
                    'can_preview' => $attachment->canPreview(),
                    'preview_type' => $attachment->getPreviewType(),
                    'is_image' => $attachment->isImage(),
                    'is_pdf' => $attachment->isPdf(),
                    'created_at' => $attachment->created_at,
                    'updated_at' => $attachment->updated_at,
                ];
            });
            
            return response()->json([
                'success' => true,
                'attachments' => $attachments
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Email not found'
            ], 404);
        }
    }
    
    /**
     * Display the specified attachment.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $attachment = Attachment::whereHas('email', function ($query) {
                $query->where('user_id', Auth::id() ?? 1);
            })->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'attachment' => $attachment
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found'
            ], 404);
        }
    }
    
    /**
     * Download the specified attachment.
     */
    public function download(int $id): Response
    {
        try {
            $attachment = Attachment::whereHas('email', function ($query) {
                $query->where('user_id', Auth::id() ?? 1);
            })->findOrFail($id);
            
            if (!file_exists($attachment->file_path)) {
                throw new Exception('Attachment file not found');
            }
            
            $headers = [
                'Content-Type' => $attachment->content_type,
                'Content-Disposition' => 'attachment; filename="' . $attachment->filename . '"',
                'Content-Length' => $attachment->file_size,
            ];
            
            return response()->file($attachment->file_path, $headers);
            
        } catch (Exception $e) {
            Log::error('Attachment download failed: ' . $e->getMessage());
            
            return response('Attachment not found', 404);
        }
    }
    
    /**
     * Preview the specified attachment (for images and PDFs only).
     */
    public function preview(int $id): Response
    {
        try {
            $attachment = Attachment::whereHas('email', function ($query) {
                $query->where('user_id', Auth::id() ?? 1);
            })->findOrFail($id);
            
            if (!file_exists($attachment->file_path)) {
                throw new Exception('Attachment file not found');
            }
            
            if (!$attachment->canPreview()) {
                return response()->json([
                    'error' => 'Preview not available for this file type',
                    'filename' => $attachment->filename,
                    'content_type' => $attachment->content_type,
                    'file_size' => $attachment->file_size,
                    'formatted_size' => $attachment->formatted_file_size,
                    'suggestion' => 'Only PDF and image files can be previewed. Please download this file to view it.'
                ], 400);
            }
            
            $headers = [
                'Content-Type' => $attachment->content_type,
                'Content-Disposition' => 'inline; filename="' . $attachment->filename . '"',
                'Content-Length' => $attachment->file_size,
            ];
            
            return response()->file($attachment->file_path, $headers);
            
        } catch (Exception $e) {
            Log::error('Attachment preview failed: ' . $e->getMessage());
            
            return response('Preview not available', 404);
        }
    }
    
    /**
     * Download all attachments for an email as a ZIP file.
     */
    public function downloadAll(int $emailId): Response
    {
        try {
            $email = Email::where('user_id', Auth::id() ?? 1)
                         ->with('attachments')
                         ->findOrFail($emailId);
            
            if ($email->attachments->isEmpty()) {
                throw new Exception('No attachments found');
            }
            
            $zipPath = storage_path('app/temp/attachments_' . $emailId . '_' . time() . '.zip');
            $zipDir = dirname($zipPath);
            
            if (!is_dir($zipDir)) {
                mkdir($zipDir, 0755, true);
            }
            
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE) !== TRUE) {
                throw new Exception('Could not create ZIP file');
            }
            
            foreach ($email->attachments as $attachment) {
                if (file_exists($attachment->file_path)) {
                    $zip->addFile($attachment->file_path, $attachment->filename);
                }
            }
            
            $zip->close();
            
            $headers = [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="attachments_' . $email->id . '.zip"',
                'Content-Length' => filesize($zipPath),
            ];
            
            $response = response()->file($zipPath, $headers);
            
            // Clean up the temporary ZIP file after sending
            register_shutdown_function(function () use ($zipPath) {
                if (file_exists($zipPath)) {
                    unlink($zipPath);
                }
            });
            
            return $response;
            
        } catch (Exception $e) {
            Log::error('Bulk attachment download failed: ' . $e->getMessage());
            
            return response('Download failed', 500);
        }
    }
    
    /**
     * Get attachment statistics for an email.
     */
    public function statistics(int $emailId): JsonResponse
    {
        try {
            $email = Email::where('user_id', Auth::id() ?? 1)
                         ->findOrFail($emailId);
            
            $attachments = $email->attachments;
            
            $stats = [
                'total_count' => $attachments->count(),
                'total_size' => $attachments->sum('file_size'),
                'formatted_size' => $this->formatBytes($attachments->sum('file_size')),
                'by_type' => $attachments->groupBy('content_type')
                                       ->map(function ($group) {
                                           return [
                                               'count' => $group->count(),
                                               'total_size' => $group->sum('file_size'),
                                               'formatted_size' => $this->formatBytes($group->sum('file_size'))
                                           ];
                                       }),
                'previewable_count' => $attachments->filter(function ($attachment) {
                    return $attachment->canPreview();
                })->count(),
                'image_count' => $attachments->filter(function ($attachment) {
                    return $attachment->isImage();
                })->count(),
                'document_count' => $attachments->filter(function ($attachment) {
                    return $attachment->isDocument();
                })->count(),
            ];
            
            return response()->json([
                'success' => true,
                'statistics' => $stats
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get attachment statistics'
            ], 500);
        }
    }
    
    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
