<?php

namespace App\Services;

use App\Models\Email;
use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class MsgParserService
{
    /**
     * Parse a .msg file and extract all information.
     */
    public function parseMsgFile(string $filePath, int $userId): Email
    {
        try {
            // Create email record first
            $email = $this->createEmailRecord($filePath, $userId);
            
            // Use Python as the primary parsing method
            $parsed = $this->parseWithPython($filePath);
            
            if (!$parsed) {
                throw new Exception('Unable to parse .msg file with Python');
            }
            
            // Update email with parsed data
            $this->updateEmailWithParsedData($email, $parsed);
            
            // Process attachments
            $this->processAttachments($email, $parsed);
            
            // Mark as processed
            $email->update(['status' => 'processed']);
            
            // Automatically assign Inbox/Sent labels
            $this->assignPrimaryLabels($email);
            
            return $email;
            
        } catch (Exception $e) {
            Log::error('Failed to parse .msg file: ' . $e->getMessage(), [
                'file_path' => $filePath,
                'user_id' => $userId
            ]);
            
            if (isset($email)) {
                $email->update([
                    'status' => 'error',
                    'error_message' => $e->getMessage()
                ]);
            }
            
            throw $e;
        }
    }
    
    /**
     * Create initial email record.
     */
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
    
    /**
     * Parse .msg file using Python extract_msg library.
     */
    public function parseWithPython(string $filePath): ?array
    {
        try {
            $scriptPath = storage_path('app/scripts/parse_msg_simple.py');
            
            // Ensure the working script exists
            if (!file_exists($scriptPath)) {
                Log::error('Python parsing script not found', ['script_path' => $scriptPath]);
                return null;
            }
            
            // Try different Python command variations for Windows compatibility
            // Prioritize 'py' for Windows systems
            $pythonCommands = ['py', 'python3', 'python'];
            $output = null;
            $command = null;
            
            foreach ($pythonCommands as $pythonCmd) {
                // Normalize file paths for Windows compatibility
                $normalizedScriptPath = str_replace('\\', '/', $scriptPath);
                $normalizedFilePath = str_replace('\\', '/', $filePath);
                $command = "\"$pythonCmd\" \"$normalizedScriptPath\" \"$normalizedFilePath\" 2>&1";
                
                Log::info('Trying Python parsing command', [
                    'command' => $command
                ]);
                
                $output = shell_exec($command);
                
                Log::info('Python command output', [
                    'command' => $command,
                    'output' => $output,
                    'exit_code' => $output ? 'success' : 'no_output'
                ]);
                
                // Check if command executed successfully (not command not found errors)
                if ($output && 
                    !str_contains($output, 'command not found') && 
                    !str_contains($output, 'is not recognized') &&
                    !str_contains($output, 'Python was not found') &&
                    !str_contains($output, 'run without arguments to install')) {
                    Log::info('Python command succeeded', ['command' => $command]);
                    break;
                }
            }
            
            if (!$output) {
                Log::error('All Python commands failed');
                return null;
            }
            
            Log::info('Executing Python parsing command', [
                'command' => $command
            ]);
            
            if ($output) {
                // Extract JSON from output (filter out debug messages)
                $lines = explode("\n", $output);
                $jsonLine = null;
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, '{') && str_ends_with($line, '}')) {
                        $jsonLine = $line;
                        break;
                    }
                }
                
                if (!$jsonLine) {
                    Log::warning('No JSON found in Python output', ['output' => $output]);
                    return null;
                }
                
                $parsed = json_decode($jsonLine, true);
                
                if (json_last_error() === JSON_ERROR_NONE && !isset($parsed['error'])) {
                    Log::info('Successfully parsed email data', [
                        'subject' => $parsed['subject'] ?? 'N/A',
                        'sender_name' => $parsed['sender_name'] ?? 'N/A',
                        'sender_email' => $parsed['sender_email'] ?? 'N/A',
                        'recipients' => $parsed['recipients'] ?? 'N/A',
                        'received_date' => $parsed['received_date'] ?? 'N/A'
                    ]);
                    
                    // Log the raw parsed data for debugging
                    Log::debug('Raw parsed data', $parsed);
                    
                    return $parsed;
                } else {
                    Log::warning('Python parsing returned error: ' . ($parsed['error'] ?? 'Unknown error'));
                    return null;
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            Log::warning('Python parsing failed: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create Python script for .msg parsing.
     */
    private function createPythonScript(string $scriptPath): void
    {
        // Always regenerate the script to ensure latest version
        if (file_exists($scriptPath)) {
            unlink($scriptPath);
        }
        
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
    """Convert object to JSON-serializable format"""
    if obj is None:
        return None
    elif isinstance(obj, str):
        return obj
    elif isinstance(obj, bytes):
        try:
            return obj.decode('utf-8', errors='ignore')
        except:
            return ""
    elif isinstance(obj, datetime):
        return obj.isoformat()
    elif isinstance(obj, (int, float, bool)):
        return obj
    elif isinstance(obj, (list, tuple)):
        return [safe_json_serialize(item) for item in obj]
    elif isinstance(obj, dict):
        return {str(k): safe_json_serialize(v) for k, v in obj.items()}
    else:
        # Convert any other object to string
        try:
            return str(obj)
        except:
            return ""

def extract_email_from_string(text):
    """Extract email address from string that might contain name and email"""
    if not text:
        return None, None
    
    text = str(text).strip()
    
    # Format: "Name <email@domain.com>"
    if '<' in text and '>' in text:
        try:
            email_part = text.split('<')[1].split('>')[0].strip()
            name_part = text.split('<')[0].strip()
            
            # Validate email
            if '@' in email_part and '.' in email_part.split('@')[1]:
                return name_part if name_part else None, email_part
        except:
            pass
    
    # Format: "email@domain.com" or "Name email@domain.com"
    if '@' in text:
        parts = text.split()
        email_part = None
        name_parts = []
        
        for part in parts:
            if '@' in part and '.' in part.split('@')[1]:
                email_part = part
            else:
                name_parts.append(part)
        
        if email_part:
            name_part = ' '.join(name_parts) if name_parts else None
            return name_part, email_part
    
    # No valid email found
    return text if text else None, None

def parse_msg_file(file_path):
    try:
        msg = extract_msg.Message(file_path)
        
        # Comprehensive debugging - print all available attributes and their values
        print(f"DEBUG: Available attributes: {dir(msg)}", file=sys.stderr)
        print(f"DEBUG: Subject: {msg.subject}", file=sys.stderr)
        
        # Try ALL possible sender-related fields with more variations
        sender_fields = [
            'sender', 'from', 'senderEmail', 'senderEmailAddress', 'senderName', 
            'from_', 'fromAddress', 'fromAddresses', 'fromEmail', 'fromEmailAddress',
            'fromName', 'fromDisplayName', 'fromDisplay', 'fromUser', 'fromUserEmail',
            'senderAddress', 'senderAddresses', 'senderDisplayName', 'senderDisplay',
            'senderUser', 'senderUserEmail', 'senderEmailAddresses', 'senderEmails'
        ]
        
        sender_info = None
        sender_field_used = None
        
        print("DEBUG: Checking sender fields:", file=sys.stderr)
        for field in sender_fields:
            try:
                if hasattr(msg, field):
                    value = getattr(msg, field)
                    if value:
                        print(f"DEBUG: {field}: {value}", file=sys.stderr)
                        if not sender_info:
                            sender_info = value
                            sender_field_used = field
                else:
                    print(f"DEBUG: {field}: Not available", file=sys.stderr)
            except Exception as e:
                print(f"DEBUG: Error accessing {field}: {e}", file=sys.stderr)
        
        # Try ALL possible recipient-related fields with more variations
        recipient_fields = [
            'to', 'recipients', 'toRecipients', 'toAddress', 'toAddresses',
            'toEmail', 'toEmails', 'toEmailAddress', 'toEmailAddresses',
            'toName', 'toNames', 'toDisplayName', 'toDisplayNames',
            'recipient', 'recipientAddress', 'recipientAddresses',
            'recipientEmail', 'recipientEmails', 'recipientEmailAddress',
            'recipientEmailAddresses', 'recipientName', 'recipientNames'
        ]
        
        recipients_info = []
        
        print("DEBUG: Checking recipient fields:", file=sys.stderr)
        for field in recipient_fields:
            try:
                if hasattr(msg, field):
                    value = getattr(msg, field)
                    if value:
                        print(f"DEBUG: {field}: {value}", file=sys.stderr)
                        if isinstance(value, str):
                            recipients_info.extend([r.strip() for r in value.split(',')])
                        elif isinstance(value, list):
                            recipients_info.extend([str(r).strip() for r in value])
                        elif hasattr(value, '__iter__') and not isinstance(value, (str, bytes)):
                            recipients_info.extend([str(r).strip() for r in value])
                else:
                    print(f"DEBUG: {field}: Not available", file=sys.stderr)
            except Exception as e:
                print(f"DEBUG: Error accessing {field}: {e}", file=sys.stderr)
        
        # Also try to access properties that might be hidden
        print("DEBUG: Trying to access properties directly:", file=sys.stderr)
        try:
            # Try to get properties directly
            if hasattr(msg, 'properties'):
                props = msg.properties
                print(f"DEBUG: Properties object: {props}", file=sys.stderr)
                if hasattr(props, 'get'):
                    # Try common property names
                    for prop_name in ['from', 'to', 'sender', 'recipient', 'fromAddress', 'toAddress']:
                        try:
                            prop_value = props.get(prop_name)
                            if prop_value:
                                print(f"DEBUG: Property {prop_name}: {prop_value}", file=sys.stderr)
                                if 'from' in prop_name.lower() and not sender_info:
                                    sender_info = prop_value
                                    sender_field_used = f"properties.{prop_name}"
                                elif 'to' in prop_name.lower() or 'recipient' in prop_name.lower():
                                    if isinstance(prop_value, str):
                                        recipients_info.extend([r.strip() for r in prop_value.split(',')])
                                    elif isinstance(prop_value, list):
                                        recipients_info.extend([str(r).strip() for r in prop_value])
                        except Exception as e:
                            print(f"DEBUG: Error accessing property {prop_name}: {e}", file=sys.stderr)
        except Exception as e:
            print(f"DEBUG: Error accessing properties: {e}", file=sys.stderr)
        
        # Fallback: Try to extract from headers if available
        print("DEBUG: Trying to extract from headers:", file=sys.stderr)
        try:
            if hasattr(msg, 'headers'):
                headers = msg.headers
                print(f"DEBUG: Headers object: {headers}", file=sys.stderr)
                if isinstance(headers, dict):
                    for header_name, header_value in headers.items():
                        print(f"DEBUG: Header {header_name}: {header_value}", file=sys.stderr)
                        if header_name.lower() in ['from', 'sender'] and not sender_info:
                            sender_info = header_value
                            sender_field_used = f"parsed_headers.{header_name}"
                        elif header_name.lower() in ['to', 'recipient']:
                            if isinstance(header_value, str):
                                recipients_info.extend([r.strip() for r in header_value.split(',')])
                            elif isinstance(header_value, list):
                                recipients_info.extend([str(r).strip() for r in header_value])
                elif isinstance(headers, str):
                    print(f"DEBUG: Headers as string: {headers}", file=sys.stderr)
                    # Try to parse headers manually
                    for line in headers.split('\n'):
                        line = line.strip()
                        if ':' in line:
                            header_name, header_value = line.split(':', 1)
                            header_name = header_name.strip().lower()
                            header_value = header_value.strip()
                            print(f"DEBUG: Parsed header {header_name}: {header_value}", file=sys.stderr)
                            if header_name in ['from', 'sender'] and not sender_info:
                                sender_info = header_value
                                sender_field_used = f"parsed_headers.{header_name}"
                            elif header_name in ['to', 'recipient']:
                                recipients_info.extend([r.strip() for r in header_value.split(',')])
        except Exception as e:
            print(f"DEBUG: Error accessing headers: {e}", file=sys.stderr)
        
        # Fallback: Try to extract from raw content if available
        print("DEBUG: Trying to extract from raw content:", file=sys.stderr)
        try:
            if hasattr(msg, 'raw') and msg.raw:
                raw_content = msg.raw
                print(f"DEBUG: Raw content available, length: {len(raw_content)}", file=sys.stderr)
                # Look for common email patterns in raw content
                raw_str = str(raw_content)
                
                # Look for From: patterns
                if not sender_info:
                    import re
                    from_match = re.search(r'From:\s*([^\r\n]+)', raw_str, re.IGNORECASE)
                    if from_match:
                        sender_info = from_match.group(1).strip()
                        sender_field_used = "raw_content_from_regex"
                        print(f"DEBUG: Found sender in raw content: {sender_info}", file=sys.stderr)
                
                # Look for To: patterns
                to_match = re.search(r'To:\s*([^\r\n]+)', raw_str, re.IGNORECASE)
                if to_match:
                    to_value = to_match.group(1).strip()
                    recipients_info.extend([r.strip() for r in to_value.split(',')])
                    print(f"DEBUG: Found recipients in raw content: {to_value}", file=sys.stderr)
        except Exception as e:
            print(f"DEBUG: Error accessing raw content: {e}", file=sys.stderr)
        
        # Remove duplicates and empty values
        recipients_info = list(set([r for r in recipients_info if r]))
        
        print(f"DEBUG: Found sender in '{sender_field_used}': {sender_info}", file=sys.stderr)
        print(f"DEBUG: Found recipients: {recipients_info}", file=sys.stderr)
        
        # Extract basic information
        data = {
            'subject': safe_json_serialize(msg.subject or ''),
            'sender_name': '',
            'sender_email': '',
            'sent_date': safe_json_serialize(msg.date) if msg.date else None,
            'received_date': None,  # We'll set this separately
            'html_content': safe_json_serialize(msg.htmlBody or ''),
            'text_content': safe_json_serialize(msg.body or ''),
            'recipients': [],
            'attachments': [],
            'headers': safe_json_serialize({}),  # Initialize as empty dict
            'message_id': safe_json_serialize(getattr(msg, 'messageId', '') or ''),
        }
        
        # Debug: Check if any basic fields contain bytes
        print("DEBUG: Checking basic fields for bytes:", file=sys.stderr)
        for key, value in data.items():
            if isinstance(value, bytes):
                print(f"DEBUG: Basic field '{key}' contains bytes: {value}", file=sys.stderr)
                # Re-serialize this field
                data[key] = safe_json_serialize(value)
        
        # Process sender information
        if sender_info:
            sender_name, sender_email = extract_email_from_string(sender_info)
            data['sender_name'] = sender_name or ''
            data['sender_email'] = sender_email or ''
            print(f"DEBUG: Parsed sender - Name: '{data['sender_name']}', Email: '{data['sender_email']}'", file=sys.stderr)
        else:
            print("DEBUG: No sender information found!", file=sys.stderr)
        
        # Process recipients information
        processed_recipients = []
        for recipient in recipients_info:
            # Handle Recipient objects
            if hasattr(recipient, '__class__') and 'Recipient' in str(recipient.__class__):
                try:
                    # Try to get email from recipient object
                    if hasattr(recipient, 'email'):
                        recipient_email = safe_json_serialize(recipient.email)
                        if recipient_email:
                            processed_recipients.append(recipient_email)
                    elif hasattr(recipient, 'address'):
                        recipient_email = safe_json_serialize(recipient.address)
                        if recipient_email:
                            processed_recipients.append(recipient_email)
                    elif hasattr(recipient, 'to'):
                        recipient_email = safe_json_serialize(recipient.to)
                        if recipient_email:
                            processed_recipients.append(recipient_email)
                    else:
                        # Convert recipient object to string and try to extract email
                        recipient_str = safe_json_serialize(recipient)
                        if recipient_str:
                            recipient_name, recipient_email = extract_email_from_string(recipient_str)
                            if recipient_email:
                                processed_recipients.append(recipient_email)
                            elif recipient_name:
                                processed_recipients.append(recipient_name)
                except Exception as e:
                    print(f"DEBUG: Error processing recipient object: {e}", file=sys.stderr)
                    # Fallback: convert to string
                    recipient_str = safe_json_serialize(recipient)
                    if recipient_str:
                        processed_recipients.append(recipient_str)
            else:
                # Handle string recipients
                recipient_name, recipient_email = extract_email_from_string(recipient)
                if recipient_email:
                    processed_recipients.append(recipient_email)
                elif recipient_name:
                    # If no email found, store the name (might be useful for display)
                    processed_recipients.append(recipient_name)
        
        data['recipients'] = processed_recipients
        print(f"DEBUG: Final recipients: {data['recipients']}", file=sys.stderr)
        
        # Set received date - for incoming emails, this is usually the same as sent_date
        # For sent emails, this might be different
        if data['sent_date']:
            data['received_date'] = data['sent_date']
        
        # Debug final sender data
        print(f"DEBUG: Final sender data - Name: {data['sender_name']}, Email: {data['sender_email']}", file=sys.stderr)
        
        # Extract attachments
        for attachment in msg.attachments:
            try:
                attachment_data = {
                    'filename': safe_json_serialize(attachment.longFilename or attachment.shortFilename),
                    'content_type': safe_json_serialize(getattr(attachment, 'contentType', 'application/octet-stream') or 'application/octet-stream'),
                    'content_id': safe_json_serialize(getattr(attachment, 'contentId', '') or ''),
                    'is_inline': bool(getattr(attachment, 'contentId', None)),
                    'size': len(attachment.data) if attachment.data else 0,
                }
                
                # Only include data if it's not too large and can be serialized
                if attachment.data and len(attachment.data) < 1000000:  # 1MB limit
                    try:
                        attachment_data['data'] = safe_json_serialize(attachment.data)
                    except:
                        attachment_data['data'] = None
                else:
                    attachment_data['data'] = None
                
                data['attachments'].append(attachment_data)
            except Exception as e:
                print(f"DEBUG: Error processing attachment: {e}", file=sys.stderr)
                # Add basic attachment info if detailed processing fails
                data['attachments'].append({
                    'filename': 'Unknown',
                    'content_type': 'application/octet-stream',
                    'content_id': '',
                    'is_inline': False,
                    'size': 0,
                    'data': None
                })
        
        # Ensure all data is JSON serializable before output
        try:
            # Test serialization
            json.dumps(data)
            print(json.dumps(data))
        except Exception as e:
            print(f"DEBUG: JSON serialization failed: {e}", file=sys.stderr)
            
            # Debug: Check each field for bytes objects
            print("DEBUG: Checking each field for bytes objects:", file=sys.stderr)
            for key, value in data.items():
                try:
                    if isinstance(value, bytes):
                        print(f"DEBUG: Field '{key}' contains bytes: {value}", file=sys.stderr)
                    elif isinstance(value, list):
                        for i, item in enumerate(value):
                            if isinstance(item, bytes):
                                print(f"DEBUG: Field '{key}[{i}]' contains bytes: {item}", file=sys.stderr)
                    elif isinstance(value, dict):
                        for k, v in value.items():
                            if isinstance(v, bytes):
                                print(f"DEBUG: Field '{key}[{k}]' contains bytes: {v}", file=sys.stderr)
                except Exception as debug_e:
                    print(f"DEBUG: Error checking field '{key}': {debug_e}", file=sys.stderr)
            
            # Fallback: create a simplified version
            fallback_data = {
                'subject': safe_json_serialize(data.get('subject', '')),
                'sender_name': safe_json_serialize(data.get('sender_name', '')),
                'sender_email': safe_json_serialize(data.get('sender_email', '')),
                'sent_date': safe_json_serialize(data.get('sent_date')),
                'received_date': safe_json_serialize(data.get('received_date')),
                'html_content': safe_json_serialize(data.get('html_content', '')),
                'text_content': safe_json_serialize(data.get('text_content', '')),
                'recipients': safe_json_serialize(data.get('recipients', [])),
                'attachments': [],
                'headers': {},
                'message_id': safe_json_serialize(data.get('message_id', '')),
            }
            print(json.dumps(fallback_data))
        
    except Exception as e:
        print(json.dumps({'error': str(e)}))
        import traceback
        print(f"DEBUG: Exception details: {traceback.format_exc()}", file=sys.stderr)

if __name__ == '__main__':
    if len(sys.argv) != 2:
        print(json.dumps({'error': 'Usage: python script.py <msg_file_path>'}))
        sys.exit(1)
    
    parse_msg_file(sys.argv[1])
PYTHON;
        
        // Create scripts directory if it doesn't exist
        $scriptsDir = dirname($scriptPath);
        if (!is_dir($scriptsDir)) {
            mkdir($scriptsDir, 0755, true);
        }
        
        file_put_contents($scriptPath, $script);
        chmod($scriptPath, 0755);
    }
    
    /**
     * Update email record with parsed data.
     */
    public function updateEmailWithParsedData(Email $email, array $parsed): void
    {
        // Sanitize fields to ensure valid UTF-8 before JSON encoding/casting
        $sanitized = [
            'subject' => $this->sanitizeForJson($parsed['subject'] ?? null),
            'sender_name' => $this->sanitizeForJson($parsed['sender_name'] ?? null),
            'sender_email' => $this->sanitizeForJson($parsed['sender_email'] ?? null),
            'sent_date' => $this->parseDate($parsed['sent_date'] ?? null),
            'received_date' => $this->parseDate($parsed['received_date'] ?? null),
            'html_content' => $this->sanitizeForJson($parsed['html_content'] ?? null),
            'text_content' => $this->sanitizeForJson($parsed['text_content'] ?? null),
            'recipients' => $this->sanitizeForJson($parsed['recipients'] ?? []),
            'headers' => $this->sanitizeForJson($parsed['headers'] ?? []),
            'message_id' => $this->sanitizeForJson($parsed['message_id'] ?? null),
        ];

        $email->update($sanitized);
    }
    
    /**
     * Process attachments from parsed data.
     */
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
    
    /**
     * Create attachment record and save file.
     */
    private function createAttachmentRecord(Email $email, array $attachmentData, string $attachmentDir): void
    {
        $filename = $attachmentData['filename'] ?? 'unknown';
        $filePath = $attachmentDir . '/' . $filename;
        
        // Save attachment file if data is available
        if (isset($attachmentData['data']) && $attachmentData['data'] !== null) {
            // Decode base64 data if it's encoded
            $data = $attachmentData['data'];
            if (is_string($data) && base64_encode(base64_decode($data, true)) === $data) {
                // It's base64 encoded, decode it
                $data = base64_decode($data);
            }
            file_put_contents($filePath, $data);
        }
        
        // Detect actual MIME type from file content or extension
        $detectedContentType = $this->detectMimeType($filePath, $attachmentData['content_type'] ?? 'application/octet-stream');
        
        Attachment::create([
            'email_id' => $email->id,
            'filename' => $this->sanitizeForJson($filename),
            'display_name' => $this->sanitizeForJson($attachmentData['display_name'] ?? $filename),
            'content_type' => $detectedContentType,
            'file_path' => $filePath,
            'file_size' => $attachmentData['size'] ?? 0,
            'content_id' => $this->sanitizeForJson($attachmentData['content_id'] ?? null),
            'is_inline' => $attachmentData['is_inline'] ?? false,
            'description' => $this->sanitizeForJson($attachmentData['description'] ?? null),
            'headers' => $this->sanitizeForJson($attachmentData['headers'] ?? []),
        ]);
    }

    /**
     * Detect MIME type from file content or extension.
     */
    private function detectMimeType(string $filePath, string $fallbackType): string
    {
        // First try to detect from file content using PHP's mime_content_type
        if (function_exists('mime_content_type') && file_exists($filePath)) {
            $detectedType = mime_content_type($filePath);
            if ($detectedType && $detectedType !== 'application/octet-stream') {
                return $detectedType;
            }
        }
        
        // Fallback to extension-based detection
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            // Images
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'tiff' => 'image/tiff',
            'ico' => 'image/x-icon',
            
            // PDFs
            'pdf' => 'application/pdf',
            
            // Documents
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'rtf' => 'application/rtf',
            'html' => 'text/html',
            'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'csv' => 'text/csv',
            
            // Archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            'bz2' => 'application/x-bzip2',
        ];
        
        if (isset($mimeTypes[$extension])) {
            return $mimeTypes[$extension];
        }
        
        // If we still can't determine, return the fallback type
        return $fallbackType;
    }
    
    /**
     * Parse date string to Carbon instance.
     */
    private function parseDate(?string $dateString): ?string
    {
        if (!$dateString) {
            return null;
        }
        
        try {
            return \Carbon\Carbon::parse($dateString)->toDateTimeString();
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Ensure a value (string/array) can be safely JSON-encoded by converting to valid UTF-8.
     */
    private function sanitizeForJson($value)
    {
        if (is_array($value)) {
            $sanitizedArray = [];
            foreach ($value as $key => $val) {
                // Sanitize keys too, since json_encode expects UTF-8 keys
                $sanitizedKey = is_string($key) ? $this->sanitizeForJson($key) : $key;
                $sanitizedArray[$sanitizedKey] = $this->sanitizeForJson($val);
            }
            return $sanitizedArray;
        }

        if (is_string($value)) {
            // If already valid UTF-8, return as is
            if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
                return $value;
            }
            // Try common conversions; fall back to stripping invalid bytes
            $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            if ($converted === false) {
                $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
            }
            return $converted !== false ? $converted : '';
        }

        return $value;
    }
    
    /**
     * Automatically assign primary labels (Inbox/Sent) to an email.
     */
    private function assignPrimaryLabels(Email $email): void
    {
        try {
            // Get system labels for this user
            $inboxLabel = \App\Models\Label::forUser($email->user_id)
                ->where('name', 'Inbox')
                ->first();
            
            $sentLabel = \App\Models\Label::forUser($email->user_id)
                ->where('name', 'Sent')
                ->first();
            
            if (!$inboxLabel || !$sentLabel) {
                Log::warning('System labels not found for user', [
                    'user_id' => $email->user_id,
                    'email_id' => $email->id
                ]);
                return;
            }
            
            // Check if email already has labels
            if ($email->labels()->count() > 0) {
                return; // Already labeled
            }
            
            // Determine and apply the appropriate label
            $labelToApply = $email->isSentItem() ? $sentLabel : $inboxLabel;
            
            if ($labelToApply) {
                $email->labels()->attach($labelToApply->id);
                Log::info('Primary label assigned automatically', [
                    'email_id' => $email->id,
                    'label_name' => $labelToApply->name,
                    'user_id' => $email->user_id
                ]);
            }
            
        } catch (Exception $e) {
            Log::error('Failed to assign primary labels', [
                'email_id' => $email->id,
                'user_id' => $email->user_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Test parsing on a specific file and return debug information.
     */
    public function testParsing(string $filePath): array
    {
        try {
            $scriptPath = storage_path('app/scripts/parse_msg_simple.py');
            
            // Ensure the working script exists
            if (!file_exists($scriptPath)) {
                return [
                    'success' => false,
                    'error' => 'Python parsing script not found: ' . $scriptPath
                ];
            }
            
            // Try different Python command variations for Windows compatibility
            $pythonCommands = ['python3', 'python', 'py'];
            $output = null;
            $command = null;
            
            foreach ($pythonCommands as $pythonCmd) {
                $command = "\"$pythonCmd\" \"$scriptPath\" \"$filePath\" 2>&1";
                
                $output = shell_exec($command);
                
                if ($output && !str_contains($output, 'command not found') && !str_contains($output, 'is not recognized')) {
                    break;
                }
            }
            
            // Extract JSON from output (filter out debug messages)
            $parsed = null;
            if ($output) {
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, '{') && str_ends_with($line, '}')) {
                        $parsed = json_decode($line, true);
                        break;
                    }
                }
            }
            
            return [
                'success' => true,
                'command' => $command,
                'output' => $output,
                'parsed' => $parsed,
                'json_error' => $parsed ? json_last_error() : null,
                'json_error_msg' => $parsed ? json_last_error_msg() : null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }
} 