import axios from 'axios';
import { refreshEmailList } from './emailList';

export function initializeUpload() {
    const form = document.getElementById('upload-form');
    const fileInput = document.getElementById('upload-input');
    const progress = document.getElementById('upload-progress');
    const fileCount = document.getElementById('file-count');
    const uploadBtn = document.getElementById('upload-btn');
    if (!form || !fileInput || !progress || !fileCount || !uploadBtn) return;

    // Handle file selection changes
    fileInput.addEventListener('change', () => {
        const files = fileInput.files;
        if (files && files.length > 0) {
            fileCount.textContent = files.length;
            fileCount.classList.remove('hidden');
            uploadBtn.disabled = false;
        } else {
            fileCount.classList.add('hidden');
            uploadBtn.disabled = true;
        }
    });

    // Initialize button state
    uploadBtn.disabled = true;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!fileInput.files || fileInput.files.length === 0) return;
        
        const formData = new FormData();
        // Handle multiple files
        for (let i = 0; i < fileInput.files.length; i++) {
            formData.append('files[]', fileInput.files[i]);
        }
        
        // Add CSRF token
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        formData.append('_token', token);
        
        console.log('Uploading files:', Array.from(fileInput.files).map(f => f.name));
        
        progress.classList.remove('hidden');
        progress.textContent = 'Uploading...';
        uploadBtn.disabled = true;
        
        try {
            const { data } = await axios.post('/api/upload', formData, {
                headers: { 
                    'Content-Type': 'multipart/form-data',
                    'X-CSRF-TOKEN': token
                },
                onUploadProgress: (event) => {
                    if (event.total) {
                        const percent = Math.round((event.loaded / event.total) * 100);
                        progress.textContent = `Uploading... ${percent}%`;
                    }
                },
            });
            
            if (data && data.success) {
                // Check if there are any errors to show
                if (data.errors && data.errors.length > 0) {
                    showUploadResults(data);
                } else {
                    progress.textContent = 'Upload complete.';
                    fileInput.value = '';
                    fileCount.classList.add('hidden');
                    uploadBtn.disabled = true;
                    refreshEmailList();
                    setTimeout(() => progress.classList.add('hidden'), 2000);
                }
            } else {
                progress.textContent = 'Upload failed.';
                uploadBtn.disabled = false;
                if (data.errors && data.errors.length > 0) {
                    showUploadResults(data);
                }
            }
        } catch (err) {
            console.error('Upload error:', err);
            let errorMessage = 'Upload failed.';
            
            if (err.response?.data) {
                if (err.response.data.errors) {
                    // Show validation errors
                    showUploadResults({
                        success: false,
                        errors: Object.entries(err.response.data.errors).map(([field, messages]) => ({
                            filename: field === 'files.0' ? 'Selected file' : field,
                            error: Array.isArray(messages) ? messages[0] : messages,
                            warning: false
                        }))
                    });
                } else if (err.response.data.message) {
                    errorMessage = err.response.data.message;
                }
            } else if (err.message) {
                errorMessage = err.message;
            }
            
            progress.textContent = errorMessage;
            uploadBtn.disabled = false;
            setTimeout(() => progress.classList.add('hidden'), 3000);
        }
    });
}

function showUploadResults(data) {
    // Debug logging to understand the data structure
    console.log('Upload results data:', data);
    if (data.errors) {
        console.log('Errors structure:', data.errors);
        data.errors.forEach((error, index) => {
            console.log(`Error ${index}:`, error);
            console.log(`Error type:`, typeof error);
            console.log(`Error keys:`, Object.keys(error));
        });
    }
    
    // Create modal for showing upload results
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.id = 'upload-results-modal';
    
    const modalContent = document.createElement('div');
    modalContent.className = 'bg-white rounded-lg p-6 max-w-2xl w-full mx-4 max-h-[80vh] overflow-y-auto';
    
    let content = '';
    
    // Summary section
    const totalFiles = data.summary?.total_files || 0;
    const successfulUploads = data.summary?.successful_uploads || 0;
    const failedUploads = data.summary?.failed_uploads || 0;
    
    if (data.success) {
        content += `
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-green-600 mb-2">Upload Results</h3>
                <p class="text-gray-700">${data.message}</p>
                <div class="mt-2 p-3 bg-green-50 border border-green-200 rounded text-sm">
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <div class="font-semibold text-green-800">${totalFiles}</div>
                            <div class="text-xs text-green-600">Total Files</div>
                        </div>
                        <div>
                            <div class="font-semibold text-green-800">${successfulUploads}</div>
                            <div class="text-xs text-green-600">Successful</div>
                        </div>
                        <div>
                            <div class="font-semibold text-red-600">${failedUploads}</div>
                            <div class="text-xs text-red-600">Failed</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    } else {
        content += `
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-red-600 mb-2">Upload Failed</h3>
                <p class="text-gray-700">${data.message || 'Upload failed for some files.'}</p>
                ${totalFiles > 0 ? `
                    <div class="mt-2 p-3 bg-red-50 border border-red-200 rounded text-sm">
                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div>
                                <div class="font-semibold text-red-800">${totalFiles}</div>
                                <div class="text-xs text-red-600">Total Files</div>
                            </div>
                            <div>
                                <div class="font-semibold text-green-600">${successfulUploads}</div>
                                <div class="text-xs text-green-600">Successful</div>
                            </div>
                            <div>
                                <div class="font-semibold text-red-800">${failedUploads}</div>
                                <div class="text-xs text-red-800">Failed</div>
                            </div>
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
    }
    
    if (data.errors && data.errors.length > 0) {
        content += `
            <div class="mb-4">
                <h4 class="font-medium text-gray-800 mb-2">Issues Found:</h4>
                <div class="space-y-2">
        `;
        
        data.errors.forEach((error, index) => {
            // Ensure error is an object with expected properties
            if (typeof error !== 'object' || error === null) {
                console.warn(`Invalid error structure at index ${index}:`, error);
                error = { filename: `File ${index + 1}`, error: 'Invalid error format' };
            }
            
            const isWarning = error.warning === true;
            const iconClass = isWarning ? 'fas fa-exclamation-triangle text-yellow-500' : 'fas fa-times-circle text-red-500';
            const bgColor = isWarning ? 'bg-yellow-50 border-yellow-200' : 'bg-red-50 border-red-200';
            const textColor = isWarning ? 'text-yellow-800' : 'text-red-800';
            
            // Safely extract error message
            let errorMessage = 'Unknown error occurred';
            if (typeof error.error === 'string') {
                errorMessage = error.error;
            } else if (error.error && typeof error.error === 'object') {
                // Handle case where error.error might be an object with message property
                if (error.error.message) {
                    errorMessage = error.error.message;
                } else if (error.error.error) {
                    errorMessage = error.error.error;
                } else if (error.error.toString) {
                    errorMessage = error.error.toString();
                } else {
                    errorMessage = 'Invalid file format or processing error';
                }
            }
            
            // Safely extract filename
            let filename = 'Unknown file';
            if (typeof error.filename === 'string') {
                filename = error.filename;
            } else if (error.filename) {
                filename = error.filename.toString();
            }
            
            // Clean up the error message for better display
            errorMessage = errorMessage
                .replace(/^files\.\d+\./, '') // Remove Laravel validation field prefixes
                .replace(/_/g, ' ') // Replace underscores with spaces
                .replace(/\b\w/g, l => l.toUpperCase()); // Capitalize first letter of each word
            
            content += `
                <div class="p-3 rounded border ${bgColor}">
                    <div class="flex items-start gap-2">
                        <i class="${iconClass} mt-0.5"></i>
                        <div class="flex-1">
                            <div class="font-medium ${textColor}">${filename}</div>
                            <div class="text-sm ${textColor}">${errorMessage}</div>
            `;
            
            // Show duplicate information if available
            if (error.duplicate_info && typeof error.duplicate_info === 'object') {
                const duplicateInfo = error.duplicate_info;
                const hasDetails = duplicateInfo.existing_subject || duplicateInfo.existing_date || duplicateInfo.existing_filename;
                
                content += `
                            <div class="mt-2 p-2 bg-gray-100 rounded text-xs text-gray-700">
                                <div class="font-medium mb-1">Duplicate Details:</div>
                                <div>${duplicateInfo.message || 'Duplicate email detected'}</div>
                                ${hasDetails ? `
                                    <div class="mt-1 pt-1 border-t border-gray-200">
                                        ${duplicateInfo.existing_subject ? `<div class="mb-1"><span class="font-medium">Subject:</span> ${duplicateInfo.existing_subject}</div>` : ''}
                                        ${duplicateInfo.existing_date ? `<div class="mb-1"><span class="font-medium">Date:</span> ${duplicateInfo.existing_date}</div>` : ''}
                                        ${duplicateInfo.existing_filename ? `<div class="mb-1"><span class="font-medium">Filename:</span> ${duplicateInfo.existing_filename}</div>` : ''}
                                    </div>
                                ` : ''}
                            </div>
                `;
            }
            
            content += `
                        </div>
                    </div>
                </div>
            `;
        });
        
        content += `
                </div>
            </div>
        `;
    }
    
    if (data.emails && data.emails.length > 0) {
        content += `
            <div class="mb-4">
                <h4 class="font-medium text-gray-800 mb-2">Successfully Uploaded:</h4>
                <div class="space-y-1">
                    ${data.emails.map(email => `
                        <div class="p-2 bg-green-50 border border-green-200 rounded text-sm text-green-800">
                            <div class="font-medium">${email.subject || '(No subject)'}</div>
                            <div class="text-xs">From: ${email.sender_name || email.sender_email || 'Unknown'}</div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
    content += `
        <div class="flex justify-end">
            <button id="close-upload-modal" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                Close
            </button>
        </div>
    `;
    
    modalContent.innerHTML = content;
    modal.appendChild(modalContent);
    document.body.appendChild(modal);
    
    // Handle close button
    modal.querySelector('#close-upload-modal').addEventListener('click', () => {
        document.body.removeChild(modal);
        // Reset form state
        document.getElementById('upload-input').value = '';
        document.getElementById('file-count').classList.add('hidden');
        document.getElementById('upload-btn').disabled = true;
        document.getElementById('upload-progress').classList.add('hidden');
        // Refresh email list if any emails were uploaded
        if (data.success && data.emails && data.emails.length > 0) {
            refreshEmailList();
        }
    });
    
    // Close modal when clicking outside
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            document.body.removeChild(modal);
            // Reset form state
            document.getElementById('upload-input').value = '';
            document.getElementById('file-count').classList.add('hidden');
            document.getElementById('upload-btn').disabled = true;
            document.getElementById('upload-progress').classList.add('hidden');
            if (data.success && data.emails && data.emails.length > 0) {
                refreshEmailList();
            }
        }
    });
}


