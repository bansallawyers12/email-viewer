<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Label;
use App\Models\Email;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Exception;

class LabelController extends Controller
{
    /**
     * Display a listing of labels for the current user.
     */
    public function index(): JsonResponse
    {
        try {
            $userId = Auth::id() ?? 1;
            
            $labels = Label::forUser($userId)
                          ->active()
                          ->orderBy('type')
                          ->orderBy('name')
                          ->get();
            
            return response()->json([
                'success' => true,
                'labels' => $labels
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load labels'
            ], 500);
        }
    }

    /**
     * Store a newly created label.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
                'description' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = Auth::id() ?? 1;
            
            // Check if label name already exists for this user
            $existingLabel = Label::forUser($userId)
                                 ->where('name', $request->name)
                                 ->first();
            
            if ($existingLabel) {
                return response()->json([
                    'success' => false,
                    'message' => 'A label with this name already exists'
                ], 422);
            }

            $label = Label::create([
                'user_id' => $userId,
                'name' => $request->name,
                'color' => $request->color ?: '#3B82F6',
                'type' => 'custom',
                'description' => $request->description,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Label created successfully',
                'label' => $label
            ], 201);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create label'
            ], 500);
        }
    }

    /**
     * Update the specified label.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $userId = Auth::id() ?? 1;
            
            $label = Label::forUser($userId)->find($id);
            
            if (!$label) {
                return response()->json([
                    'success' => false,
                    'message' => 'Label not found'
                ], 404);
            }

            // Prevent editing system labels
            if ($label->isSystem()) {
                return response()->json([
                    'success' => false,
                    'message' => 'System labels cannot be modified'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
                'description' => 'nullable|string|max:1000',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if new name conflicts with existing labels
            if ($request->name !== $label->name) {
                $existingLabel = Label::forUser($userId)
                                     ->where('name', $request->name)
                                     ->where('id', '!=', $id)
                                     ->first();
                
                if ($existingLabel) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A label with this name already exists'
                    ], 422);
                }
            }

            $label->update($request->only(['name', 'color', 'description', 'is_active']));

            return response()->json([
                'success' => true,
                'message' => 'Label updated successfully',
                'label' => $label
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update label'
            ], 500);
        }
    }

    /**
     * Remove the specified label.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $userId = Auth::id() ?? 1;
            
            $label = Label::forUser($userId)->find($id);
            
            if (!$label) {
                return response()->json([
                    'success' => false,
                    'message' => 'Label not found'
                ], 404);
            }

            // Prevent deleting system labels
            if ($label->isSystem()) {
                return response()->json([
                    'success' => false,
                    'message' => 'System labels cannot be deleted'
                ], 422);
            }

            // Remove label from all emails
            $label->emails()->detach();
            
            // Delete the label
            $label->delete();

            return response()->json([
                'success' => true,
                'message' => 'Label deleted successfully'
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete label'
            ], 500);
        }
    }

    /**
     * Apply a label to an email.
     */
    public function applyToEmail(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email_id' => 'required|integer|exists:emails,id',
                'label_id' => 'required|integer|exists:labels,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = Auth::id() ?? 1;
            
            $email = Email::where('user_id', $userId)->find($request->email_id);
            $label = Label::forUser($userId)->find($request->label_id);
            
            if (!$email || !$label) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email or label not found'
                ], 404);
            }

            // Check if label is already applied
            if ($email->labels()->where('label_id', $label->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Label is already applied to this email'
                ], 422);
            }

            $email->labels()->attach($label->id);

            return response()->json([
                'success' => true,
                'message' => 'Label applied successfully'
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to apply label'
            ], 500);
        }
    }

    /**
     * Remove a label from an email.
     */
    public function removeFromEmail(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email_id' => 'required|integer|exists:emails,id',
                'label_id' => 'required|integer|exists:labels,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = Auth::id() ?? 1;
            
            $email = Email::where('user_id', $userId)->find($request->email_id);
            $label = Label::forUser($userId)->find($request->label_id);
            
            if (!$email || !$label) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email or label not found'
                ], 404);
            }

            $email->labels()->detach($label->id);

            return response()->json([
                'success' => true,
                'message' => 'Label removed successfully'
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove label'
            ], 500);
        }
    }

    /**
     * Get emails by label.
     */
    public function getEmailsByLabel(int $labelId): JsonResponse
    {
        try {
            $userId = Auth::id() ?? 1;
            
            $label = Label::forUser($userId)->find($labelId);
            
            if (!$label) {
                return response()->json([
                    'success' => false,
                    'message' => 'Label not found'
                ], 404);
            }

            $emails = $label->emails()
                           ->where('user_id', $userId)
                           ->with(['attachments', 'labels'])
                           ->orderBy('sent_date', 'desc')
                           ->paginate(20);

            return response()->json([
                'success' => true,
                'label' => $label,
                'emails' => $emails
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load emails'
            ], 500);
        }
    }
}
