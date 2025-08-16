<template>
  <div class="h-full flex flex-col">
         <!-- Header -->
     <div class="p-3 border-b border-gray-200">
       <div class="flex justify-between items-start">
         <div class="flex-1 min-w-0">
           <h3 class="text-base font-semibold text-gray-900 truncate mb-2">
             {{ email?.subject || 'Email Details' }}
           </h3>
           <div class="space-y-1 text-sm text-gray-600">
             <p>From: {{ email?.sender_name || email?.sender_email || 'Unknown Sender' }}</p>
             <p>To: {{ email?.recipients || 'Unknown Recipients' }}</p>
           </div>
         </div>
         <div class="flex flex-col items-end space-y-2">
           <div class="text-sm text-gray-500 text-right">
             {{ email?.sent_date ? formatDate(email.sent_date) : 'Unknown Date' }}
           </div>
           <button
             @click="exportEmail"
             class="px-2 py-1 text-xs bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
             :disabled="!email"
           >
             <i class="fas fa-download mr-1"></i>
             Export PDF
           </button>
         </div>
       </div>
     </div>

    <!-- Content Tabs -->
    <div class="border-b border-gray-200">
      <div class="flex">
        <button
          @click="activeTab = 'content'"
          class="px-3 py-2 text-xs font-medium border-b-2 transition-colors"
          :class="activeTab === 'content' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
        >
          Content
        </button>
        <button
          @click="activeTab = 'attachments'"
          class="px-3 py-2 text-xs font-medium border-b-2 transition-colors"
          :class="activeTab === 'attachments' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
        >
          Attachments ({{ attachments.length }})
        </button>
        <button
          @click="activeTab = 'metadata'"
          class="px-3 py-2 text-xs font-medium border-b-2 transition-colors"
          :class="activeTab === 'metadata' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
        >
          Metadata
        </button>
        <button
          @click="activeTab = 'labels'"
          class="px-3 py-2 text-xs font-medium border-b-2 transition-colors"
          :class="activeTab === 'labels' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
        >
          Labels
        </button>
      </div>
    </div>

    <!-- Tab Content -->
    <div class="flex-1 overflow-y-auto">
      <!-- Loading State -->
      <div v-if="loading" class="p-8 text-center">
        <i class="fas fa-spinner fa-spin text-3xl text-gray-400 mb-4"></i>
        <p class="text-gray-500">Loading email details...</p>
      </div>

      <!-- No Email Selected -->
      <div v-else-if="!email" class="p-8 text-center">
        <i class="fas fa-envelope-open text-4xl text-gray-300 mb-4"></i>
        <p class="text-gray-500">Select an email to view its details</p>
      </div>

      <!-- Content Tab -->
      <div v-else-if="activeTab === 'content'" class="p-4">
                 <div class="space-y-4">

          <!-- Email Body -->
          <div>
            <div class="border-t border-gray-200 mb-3"></div>
            <div class="bg-white border border-gray-200 rounded-lg p-3">
              <div v-if="email.html_content" v-html="email.html_content" class="prose max-w-none"></div>
              <div v-else-if="email.text_content" class="whitespace-pre-wrap text-gray-900">{{ email.text_content }}</div>
              <div v-else class="text-gray-500 italic">No content available</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Attachments Tab -->
      <div v-else-if="activeTab === 'attachments'" class="p-6">
        <div v-if="attachments.length === 0" class="text-center py-8">
          <i class="fas fa-paperclip text-3xl text-gray-300 mb-4"></i>
          <p class="text-gray-500">No attachments found</p>
        </div>
        
        <div v-else class="space-y-4">
          <div class="flex justify-between items-center">
            <div>
              <h4 class="text-lg font-medium text-gray-900">Attachments</h4>
              <!-- Email Labels in Attachments Tab -->
              <div v-if="email.labels && email.labels.length > 0" class="flex flex-wrap gap-2 mt-2">
                <span
                  v-for="label in email.labels"
                  :key="label.id"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                  :style="{ 
                    backgroundColor: label.color + '20',
                    color: label.color,
                    border: `1px solid ${label.color}40`
                  }"
                >
                  <i :class="getLabelIcon(label)" class="text-xs"></i>
                  {{ label.name }}
                </span>
              </div>
            </div>
            <button
              @click="downloadAllAttachments"
              class="px-3 py-1 text-sm bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors"
            >
              <i class="fas fa-download mr-1"></i>
              Download All
            </button>
          </div>
          
          <div class="grid gap-4">
            <div
              v-for="attachment in attachments"
              :key="attachment.id"
              class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                  <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                    <i :class="getFileIcon(attachment.content_type)" class="text-gray-500"></i>
                  </div>
                  <div>
                    <h5 class="font-medium text-gray-900">{{ attachment.filename }}</h5>
                    <p class="text-sm text-gray-500">
                      {{ formatFileSize(attachment.file_size) }} • {{ getFileTypeDisplay(attachment.content_type) }}
                      <span v-if="attachment.can_preview" class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        <i class="fas fa-eye mr-1"></i> Preview
                      </span>
                      <span v-else class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                        <i class="fas fa-download mr-1"></i> Download Only
                      </span>
                    </p>
                  </div>
                </div>
                <div class="flex items-center space-x-2">
                  <button
                    @click="openPreview(attachment)"
                    :class="[
                      'px-3 py-1 text-sm rounded-md transition-colors',
                      attachment.can_preview 
                        ? 'text-blue-600 hover:bg-blue-50' 
                        : 'text-gray-500 hover:bg-gray-50 cursor-not-allowed'
                    ]"
                    :disabled="!attachment.can_preview"
                    :title="attachment.can_preview ? 'Preview file' : 'Preview not available for this file type'"
                  >
                    <i class="fas fa-eye mr-1"></i>
                    {{ attachment.can_preview ? 'Preview' : 'No Preview' }}
                  </button>
                  <button
                    @click="downloadAttachment(attachment)"
                    class="px-3 py-1 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                  >
                    <i class="fas fa-download mr-1"></i>
                    Download
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Metadata Tab -->
      <div v-else-if="activeTab === 'metadata'" class="p-6">
        <h4 class="text-lg font-medium text-gray-900 mb-4">Email Metadata</h4>
        
        <div class="bg-white border border-gray-200 rounded-lg p-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
              <span class="font-medium text-gray-700">File Name:</span>
              <p class="text-gray-900">{{ email.original_filename || 'Unknown' }}</p>
            </div>
            <div>
              <span class="font-medium text-gray-700">File Size:</span>
              <p class="text-gray-900">{{ formatFileSize(email.file_size) }}</p>
            </div>
            <div>
              <span class="font-medium text-gray-700">Message ID:</span>
              <p class="text-gray-900">{{ email.message_id || 'Unknown' }}</p>
            </div>
            <div>
              <span class="font-medium text-gray-700">Priority:</span>
              <p class="text-gray-900">{{ email.priority || 'Normal' }}</p>
            </div>
            <div>
              <span class="font-medium text-gray-700">Content Type:</span>
              <p class="text-gray-900">{{ email.content_type || 'Unknown' }}</p>
            </div>
            <div>
              <span class="font-medium text-gray-700">Encoding:</span>
              <p class="text-gray-900">{{ email.encoding || 'Unknown' }}</p>
            </div>
            <div>
              <span class="font-medium text-gray-700">Created At:</span>
              <p class="text-gray-900">{{ formatDateTime(email.created_at) }}</p>
            </div>
            <div>
              <span class="font-medium text-gray-700">Updated At:</span>
              <p class="text-gray-900">{{ formatDateTime(email.updated_at) }}</p>
            </div>
          </div>
          
          <!-- Email Labels in Metadata Tab -->
          <div v-if="email.labels && email.labels.length > 0" class="mt-6 pt-6 border-t border-gray-200">
            <span class="font-medium text-gray-700">Labels:</span>
            <div class="flex flex-wrap gap-2 mt-2">
              <span
                v-for="label in email.labels"
                :key="label.id"
                class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium"
                :style="{ 
                  backgroundColor: label.color + '20',
                  color: label.color,
                  border: `1px solid ${label.color}40`
                }"
              >
                <i :class="getLabelIcon(label)" class="text-sm"></i>
                {{ label.name }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Labels Tab -->
      <div v-else-if="activeTab === 'labels'" class="p-6">
        <h4 class="text-lg font-medium text-gray-900 mb-4">Email Labels</h4>
        <EmailLabeling 
          :email-id="emailId" 
          @labels-updated="loadEmail"
        />
      </div>
    </div>

    <!-- Attachment Preview Modal -->
    <div v-if="previewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg max-w-6xl w-full mx-4 max-h-[90vh] overflow-hidden">
        <div class="flex justify-between items-center p-4 border-b border-gray-200">
          <div class="flex items-center space-x-3">
            <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center">
              <i :class="getFileIcon(previewAttachment?.content_type)" class="text-gray-500"></i>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-gray-900">{{ previewAttachment?.filename }}</h3>
              <div class="flex items-center space-x-4 text-sm text-gray-500">
                <span>{{ previewAttachment?.content_type }} • {{ formatFileSize(previewAttachment?.file_size) }}</span>
                <span v-if="previewAttachment?.can_preview" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                  <i class="fas fa-eye mr-1"></i> Preview Available
                </span>
                <span v-else class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                  <i class="fas fa-info-circle mr-1"></i> Download Only
                </span>
              </div>
            </div>
          </div>
          <button @click="closePreview" class="text-gray-400 hover:text-gray-600 p-2 rounded-full hover:bg-gray-100">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="p-4 overflow-auto max-h-[calc(90vh-80px)]">
          <!-- Preview Content -->
          <div v-if="previewContent" v-html="previewContent" class="preview-content"></div>
          
          <!-- Loading State -->
          <div v-else class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-3xl text-blue-300 mb-4"></i>
            <p class="text-gray-500">Loading preview...</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import EmailLabeling from './EmailLabeling.vue';

// Props
const props = defineProps({
  emailId: {
    type: [Number, String],
    default: null
  }
});

// Reactive data
const loading = ref(false);
const email = ref(null);
const attachments = ref([]);
const activeTab = ref('content');
const previewModal = ref(false);
const previewAttachment = ref(null);
const previewContent = ref('');

// Methods
const loadEmail = async () => {
  if (!props.emailId) {
    email.value = null;
    attachments.value = [];
    return;
  }

  loading.value = true;
  try {
    const response = await fetch(`/api/emails/${props.emailId}`);
    const data = await response.json();

    if (data.success) {
      email.value = data.email;
      await loadAttachments();
    }
  } catch (error) {
    console.error('Failed to load email:', error);
  } finally {
    loading.value = false;
  }
};

const loadAttachments = async () => {
  if (!props.emailId) return;

  try {
    const response = await fetch(`/api/attachments/email/${props.emailId}`);
    const data = await response.json();

    if (data.success) {
      attachments.value = data.attachments;
    }
  } catch (error) {
    console.error('Failed to load attachments:', error);
  }
};

const exportEmail = async () => {
  if (!props.emailId) return;

  try {
    const response = await fetch(`/api/emails/${props.emailId}/export-pdf`);
    if (response.ok) {
      const data = await response.json();
      
      if (data.success) {
        if (data.pdf_url) {
          // PDF was generated successfully, download it
          const pdfResponse = await fetch(data.pdf_url);
          if (pdfResponse.ok) {
            const blob = await pdfResponse.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `email-${props.emailId}.pdf`;
            a.click();
            window.URL.revokeObjectURL(url);
          }
        } else if (data.html_url) {
          // HTML was generated as fallback
          alert('PDF generation not available. HTML version created. You can download it and print to PDF manually.');
          const htmlResponse = await fetch(data.html_url);
          if (htmlResponse.ok) {
            const blob = await htmlResponse.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `email-${props.emailId}.html`;
            a.click();
            window.URL.revokeObjectURL(url);
          }
        } else {
          alert(data.message || 'Export completed but no file generated.');
        }
      } else {
        alert(data.message || 'Export failed.');
      }
    } else {
      alert('Failed to export email. Please try again.');
    }
  } catch (error) {
    console.error('Export failed:', error);
    alert('An error occurred while exporting the email.');
  }
};

const downloadAttachment = async (attachment) => {
  try {
    const response = await fetch(`/api/attachments/${attachment.id}/download`);
    if (response.ok) {
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = attachment.filename;
      a.click();
      window.URL.revokeObjectURL(url);
    }
  } catch (error) {
    console.error('Download failed:', error);
  }
};

const downloadAllAttachments = async () => {
  try {
    const response = await fetch(`/api/attachments/email/${props.emailId}/download-all`);
    if (response.ok) {
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `attachments-${props.emailId}.zip`;
      a.click();
      window.URL.revokeObjectURL(url);
    }
  } catch (error) {
    console.error('Download failed:', error);
  }
};

const openPreview = async (attachment) => {
  previewAttachment.value = attachment;
  previewModal.value = true;
  previewContent.value = '';

  // Show loading state
  previewContent.value = `
    <div class="text-center py-8">
      <i class="fas fa-spinner fa-spin text-3xl text-blue-300 mb-4"></i>
      <p class="text-gray-500">Loading preview...</p>
    </div>
  `;

  try {
    const response = await fetch(`/api/attachments/${attachment.id}/preview`);
    
    if (response.ok) {
      // Handle PDF and image files only
      if (attachment.content_type.startsWith('image/')) {
        // Images are handled directly by the browser
        previewContent.value = `<img src="/api/attachments/${attachment.id}/preview" alt="${attachment.filename}" class="max-w-full h-auto" />`;
      } else if (attachment.content_type === 'application/pdf') {
        // PDFs get embedded viewer
        previewContent.value = `<iframe src="/api/attachments/${attachment.id}/preview" width="100%" height="600" frameborder="0"></iframe>`;
      } else {
        // Fallback for other file types
        previewContent.value = `
          <div class="text-center py-8">
            <i class="fas fa-exclamation-triangle text-3xl text-yellow-300 mb-4"></i>
            <h4 class="text-lg font-medium text-gray-900 mb-2">Preview Not Supported</h4>
            <p class="text-gray-600 mb-4">This file type cannot be previewed. Only PDF and image files are supported.</p>
            <button
              @click="downloadAttachment(previewAttachment)"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
              <i class="fas fa-download mr-2"></i>
              Download File
            </button>
          </div>
        `;
      }
    } else {
      // Handle non-previewable files
      try {
        const errorData = await response.json();
        previewContent.value = `
          <div class="text-center py-8">
            <i class="fas fa-file text-3xl text-gray-300 mb-4"></i>
            <h4 class="text-lg font-medium text-gray-900 mb-2">Preview Not Available</h4>
            <p class="text-gray-600 mb-4">${errorData.suggestion || 'This file type cannot be previewed.'}</p>
            
            <div class="bg-gray-50 rounded-lg p-4 mb-4 text-left">
              <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <span class="font-medium text-gray-700">File Type:</span>
                  <p class="text-gray-900">${errorData.content_type || 'Unknown'}</p>
                </div>
                <div>
                  <span class="font-medium text-gray-700">File Size:</span>
                  <p class="text-gray-900">${errorData.formatted_size || 'Unknown'}</p>
                </div>
              </div>
            </div>
            
            <button
              @click="downloadAttachment(previewAttachment)"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
              <i class="fas fa-download mr-2"></i>
              Download File
            </button>
          </div>
        `;
      } catch (parseError) {
        // Fallback if JSON parsing fails
        previewContent.value = `
          <div class="text-center py-8">
            <i class="fas fa-exclamation-triangle text-3xl text-yellow-300 mb-4"></i>
            <h4 class="text-lg font-medium text-gray-900 mb-2">Preview Error</h4>
            <p class="text-gray-600 mb-4">Unable to load preview for this file type.</p>
            <button
              @click="downloadAttachment(previewAttachment)"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
              <i class="fas fa-download mr-2"></i>
              Download File
            </button>
          </div>
        `;
      }
    }
  } catch (error) {
    console.error('Preview failed:', error);
    previewContent.value = `
      <div class="text-center py-8">
        <i class="fas fa-exclamation-triangle text-3xl text-red-300 mb-4"></i>
        <h4 class="text-lg font-medium text-red-900 mb-2">Preview Error</h4>
        <p class="text-red-600 mb-4">Failed to load preview. Please try downloading the file instead.</p>
        <button
          @click="downloadAttachment(previewAttachment)"
          class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
        >
          <i class="fas fa-download mr-2"></i>
          Download File
        </button>
      </div>
    `;
  }
};

const closePreview = () => {
  previewModal.value = false;
  previewAttachment.value = null;
  previewContent.value = '';
};

const getFileIcon = (mimeType) => {
  if (mimeType.startsWith('image/')) return 'fas fa-image';
  if (mimeType.startsWith('video/')) return 'fas fa-video';
  if (mimeType.startsWith('audio/')) return 'fas fa-music';
  if (mimeType.includes('pdf')) return 'fas fa-file-pdf';
  if (mimeType.includes('word') || mimeType.includes('document')) return 'fas fa-file-word';
  if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'fas fa-file-excel';
  if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) return 'fas fa-file-powerpoint';
  if (mimeType.includes('zip') || mimeType.includes('archive')) return 'fas fa-file-archive';
  if (mimeType.startsWith('text/')) return 'fas fa-file-alt';
  return 'fas fa-file';
};

const getFileTypeDisplay = (mimeType) => {
  if (mimeType.startsWith('image/')) return 'Image';
  if (mimeType.includes('pdf')) return 'PDF';
  if (mimeType.startsWith('text/')) return 'Text';
  if (mimeType.includes('word') || mimeType.includes('document')) return 'Document';
  if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'Spreadsheet';
  if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) return 'Presentation';
  if (mimeType.includes('zip') || mimeType.includes('archive')) return 'Archive';
  if (mimeType === 'application/octet-stream') return 'Binary File';
  return 'File';
};

const getFileTypeDescription = (attachment) => {
  if (attachment.is_image) return 'Image file that can be previewed directly';
  if (attachment.is_pdf) return 'PDF document that can be viewed in the browser';
  return 'File type not supported for preview - download to view';
};

const getLabelIcon = (label) => {
  if (label.icon) {
    return label.icon;
  }
  
  // Default icons based on label name
  const defaultIcons = {
    'inbox': 'fas fa-inbox',
    'sent': 'fas fa-paper-plane',
    'draft': 'fas fa-edit',
    'trash': 'fas fa-trash',
    'spam': 'fas fa-ban',
    'archive': 'fas fa-archive',
    'work': 'fas fa-briefcase',
    'personal': 'fas fa-user',
    'important': 'fas fa-star',
    'urgent': 'fas fa-exclamation-triangle',
  };
  
  const labelName = label.name.toLowerCase();
  return defaultIcons[labelName] || 'fas fa-tag';
};

const formatDate = (dateString) => {
  if (!dateString) return 'Unknown Date';
  
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const formatDateTime = (dateString) => {
  if (!dateString) return 'Unknown';
  
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });
};

const formatFileSize = (bytes) => {
  if (!bytes) return '0 B';
  
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
};

// Watchers
watch(() => props.emailId, () => {
  loadEmail();
});

// Lifecycle
onMounted(() => {
  if (props.emailId) {
    loadEmail();
  }
});
</script>

<style scoped>
.prose {
  @apply text-gray-900;
}

.prose h1, .prose h2, .prose h3, .prose h4, .prose h5, .prose h6 {
  @apply font-semibold text-gray-900 mb-2;
}

.prose p {
  @apply mb-3;
}

/* Preview Content Styles */
.preview-content {
  @apply w-full;
}

.preview-content img {
  @apply max-w-full h-auto rounded-lg shadow-sm;
}

.preview-content video {
  @apply max-w-full h-auto rounded-lg shadow-sm;
}

.preview-content audio {
  @apply w-full;
}

.preview-content iframe {
  @apply rounded-lg shadow-sm;
}

.preview-content pre {
  @apply bg-gray-50 p-4 rounded-lg overflow-auto max-h-96 text-sm font-mono;
}

/* File Type Specific Styles */
.preview-content .text-content {
  @apply whitespace-pre-wrap text-gray-900;
}

.preview-content .html-content {
  @apply prose max-w-none;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .preview-content iframe {
    height: 400px;
  }
  
  .preview-content video {
    max-height: 400px;
  }
}
</style> 