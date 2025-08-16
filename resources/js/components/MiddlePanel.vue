<template>
  <div class="h-full flex flex-col">
    <!-- Upload Section -->
    <div class="p-3 border-b border-gray-200">
      <h3 class="text-base font-semibold mb-3">Upload Email</h3>
      
      <!-- Drag & Drop Zone -->
      <div
        @drop="handleDrop"
        @dragover.prevent
        @dragenter.prevent
        @dragleave.prevent
        class="border-2 border-dashed border-gray-300 rounded-lg p-3 text-center hover:border-blue-400 transition-all duration-300"
        :class="{ 
          'border-blue-500 bg-blue-50 scale-105': isDragOver,
          'border-green-500 bg-green-50': uploadSuccess
        }"
      >
        <i class="fas fa-cloud-upload-alt text-2xl text-gray-400 mb-2 transition-colors"
           :class="{ 'text-blue-500': isDragOver, 'text-green-500': uploadSuccess }"></i>
        <p class="text-xs text-gray-600 mb-2">
          Drag and drop .msg files here or
        </p>
        <label class="cursor-pointer">
          <span class="bg-blue-600 text-white px-3 py-1 rounded-md hover:bg-blue-700 transition-colors inline-block text-sm">
            Choose Files
          </span>
          <input
            type="file"
            ref="fileInput"
            @change="handleFileSelect"
            accept=".msg"
            multiple
            class="hidden"
          />
        </label>
        <p class="text-xs text-gray-500 mt-1">Maximum file size: 10MB</p>
      </div>

      <!-- Upload Progress -->
      <div v-if="uploading" class="mt-3">
        <div class="flex justify-between text-xs text-gray-600 mb-1">
          <span>Uploading...</span>
          <span>{{ uploadProgress }}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
          <div
            class="bg-blue-600 h-1.5 rounded-full transition-all duration-300 ease-out"
            :style="{ width: uploadProgress + '%' }"
          ></div>
        </div>
        <p class="text-xs text-gray-500 mt-1">{{ currentFileName }}</p>
      </div>

      <!-- Upload Success Message -->
      <div v-if="uploadSuccess" class="mt-3 p-2 bg-green-50 border border-green-200 rounded-md">
        <div class="flex items-center">
          <i class="fas fa-check-circle text-green-500 mr-2 text-sm"></i>
          <span class="text-xs text-green-700">{{ uploadSuccessMessage }}</span>
        </div>
      </div>

      <!-- Upload Warning Message -->
      <div v-if="uploadWarnings.length > 0" class="mt-3 p-2 bg-yellow-50 border border-yellow-200 rounded-md">
        <div class="flex items-center mb-1">
          <i class="fas fa-exclamation-triangle text-yellow-500 mr-2 text-sm"></i>
          <span class="text-xs font-medium text-yellow-700">Upload Warnings</span>
        </div>
        <div class="space-y-1">
          <div v-for="warning in uploadWarnings" :key="warning.filename" class="text-xs text-yellow-600">
            <strong>{{ warning.filename }}:</strong> {{ warning.message }}
          </div>
        </div>
      </div>

      <!-- Upload Error Message -->
      <div v-if="uploadError" class="mt-3 p-2 bg-red-50 border border-red-200 rounded-md">
        <div class="flex items-center">
          <i class="fas fa-exclamation-triangle text-red-500 mr-2 text-sm"></i>
          <span class="text-xs text-red-700">{{ uploadError }}</span>
        </div>
      </div>
    </div>

    <!-- Search Section -->
    <div class="p-3 border-b border-gray-200">
      <h3 class="text-base font-semibold mb-3">Search & Filter</h3>
      
      <!-- Search Input -->
      <div class="mb-3">
        <div class="relative">
          <input
            v-model="searchQuery"
            @input="handleSearch"
            type="text"
            placeholder="Search emails..."
            class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors text-sm"
          />
          <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-sm"></i>
          <button 
            v-if="searchQuery"
            @click="clearSearch"
            class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600 transition-colors text-sm"
          >
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>

      <!-- Filter Options -->
      <div class="space-y-2">
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Sort & Order</label>
          <div class="flex space-x-2">
            <select v-model="sortBy" @change="handleSort" class="flex-1 border border-gray-300 rounded-md px-2 py-1 text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
              <option value="date">Date</option>
              <option value="subject">Subject</option>
              <option value="sender">Sender</option>
              <option value="size">Size</option>
            </select>
            <select v-model="sortOrder" @change="handleSort" class="flex-1 border border-gray-300 rounded-md px-2 py-1 text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
              <option value="desc">Newest First</option>
              <option value="asc">Oldest First</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Email List Header -->
    <div class="p-3 border-b border-gray-200">
      <div class="flex justify-between items-center">
        <h3 class="text-base font-semibold">Email List</h3>
        <div class="flex items-center space-x-2">
          <span class="text-xs text-gray-500">{{ emails?.length || 0 }} emails</span>
          <button
            @click="refreshEmails"
            class="text-gray-400 hover:text-gray-600"
            :class="{ 'animate-spin': loading }"
          >
            <i class="fas fa-sync-alt"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Email List -->
    <div class="flex-1 overflow-y-auto">
      <div v-if="loading" class="p-3 text-center">
        <i class="fas fa-spinner fa-spin text-xl text-gray-400"></i>
        <p class="text-xs text-gray-500 mt-1">Loading emails...</p>
      </div>

      <div v-else-if="!emails || emails.length === 0" class="p-3 text-center">
        <i class="fas fa-inbox text-2xl text-gray-300 mb-1"></i>
        <p class="text-xs text-gray-500">No emails found</p>
        <p class="text-xs text-gray-400">Upload some .msg files to get started</p>
      </div>

      <div v-else-if="emails && emails.length > 0" class="divide-y divide-gray-200">
        <div
          v-for="email in emails"
          :key="email.id"
          @click="selectEmail(email.id)"
          class="p-3 hover:bg-gray-50 cursor-pointer transition-colors relative"
          :class="{ 'bg-blue-50 border-l-4 border-blue-500': selectedEmailId === email.id }"
        >
          <!-- Email Header -->
          <div class="flex justify-between items-start mb-1">
            <div class="flex-1 min-w-0">
              <h4 class="text-sm font-medium text-gray-900 truncate">
                {{ email.subject || 'No Subject' }}
              </h4>
              <p class="text-sm text-gray-600 truncate">
                From: {{ email.sender_name || email.sender_email || 'Unknown Sender' }}
              </p>
            </div>
            <div class="flex items-center space-x-2 ml-2">
              <span v-if="email.attachments && email.attachments.length > 0" class="text-xs text-blue-600">
                <i class="fas fa-paperclip"></i>
              </span>
              <span class="text-xs text-gray-400">
                {{ formatDate(email.sent_date) }}
              </span>
            </div>
          </div>

          <!-- Email Labels -->
          <div v-if="email.labels && email.labels.length > 0" class="flex flex-wrap gap-1 mb-1">
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

          <!-- Email Preview -->
          <div class="text-xs text-gray-600 line-clamp-1 mb-1">
            {{ email.text_content || email.html_content || 'No preview available' }}
          </div>

          <!-- Email Metadata -->
          <div class="flex justify-between items-center text-xs text-gray-400">
            <span>{{ formatFileSize(email.file_size) }}</span>
            <div class="flex items-center space-x-2">
              <span v-if="email.attachments && email.attachments.length > 0">
                {{ email.attachments.length }} attachment{{ email.attachments.length !== 1 ? 's' : '' }}
              </span>
              <button
                @click.stop="toggleEmailActions(email.id)"
                class="text-gray-400 hover:text-gray-600"
              >
                <i class="fas fa-ellipsis-v"></i>
              </button>
            </div>
          </div>

          <!-- Email Actions Dropdown -->
          <div
            v-if="emailActionsOpen === email.id"
            class="absolute right-0 mt-1 mr-2 bg-white border border-gray-200 rounded-md shadow-lg z-10"
          >
            <div class="py-1">
              <button
                @click.stop="exportEmail(email.id)"
                class="w-full text-left px-3 py-1 text-xs text-gray-700 hover:bg-gray-100"
              >
                <i class="fas fa-download mr-1"></i>
                Export as PDF
              </button>
              <button
                @click.stop="downloadAttachments(email.id)"
                class="w-full text-left px-3 py-1 text-xs text-gray-700 hover:bg-gray-100"
              >
                <i class="fas fa-paperclip mr-1"></i>
                Download Attachments
              </button>
              <button
                @click.stop="deleteEmail(email.id)"
                class="w-full text-left px-3 py-1 text-xs text-red-600 hover:bg-red-50"
              >
                <i class="fas fa-trash mr-1"></i>
                Delete Email
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.total > pagination.per_page" class="p-2 border-t border-gray-200">
      <div class="flex justify-between items-center">
        <div class="text-xs text-gray-500">
          Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }}
        </div>
        <div class="flex items-center space-x-1">
          <button
            @click="changePage(pagination.current_page - 1)"
            :disabled="pagination.current_page <= 1"
            class="px-2 py-1 text-xs border border-gray-300 rounded-md disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
          >
            Prev
          </button>
          <span class="text-xs text-gray-500 px-2">
            {{ pagination.current_page }}/{{ pagination.last_page }}
          </span>
          <button
            @click="changePage(pagination.current_page + 1)"
            :disabled="pagination.current_page >= pagination.last_page"
            class="px-2 py-1 text-xs border border-gray-300 rounded-md disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Label Manager Modal -->
    <div
      v-if="showLabelManager"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="showLabelManager = false"
    >
      <div class="bg-white rounded-lg w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-semibold">Label Management</h3>
            <button
              @click="showLabelManager = false"
              class="text-gray-400 hover:text-gray-600"
            >
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>
          
          <LabelManager @labels-updated="refreshLabels" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, inject } from 'vue';
import { useEmailStore } from '../stores/emailStore';
import LabelManager from './LabelManager.vue';

// Props
const props = defineProps({
  selectedEmailId: {
    type: [Number, String],
    default: null
  }
});

// Emits
const emit = defineEmits(['email-selected']);

// Reactive data
const loading = ref(false);
const emails = ref([]);
const emailActionsOpen = ref(null);
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0,
  from: 0,
  to: 0
});

// Upload-related data
const fileInput = ref(null);
const isDragOver = ref(false);
const uploading = ref(false);
const uploadProgress = ref(0);
const currentFileName = ref('');
const uploadSuccess = ref(false);
const uploadSuccessMessage = ref('');
const uploadError = ref('');
const uploadWarnings = ref([]);

// Search and filter data
const searchQuery = ref('');
const sortBy = ref('date');
const sortOrder = ref('desc');
const showAdvancedFilters = ref(false);
const attachmentFilter = ref('');
const sizeFilter = ref('');
const showLabelManager = ref(false);
const selectedLabel = ref(null);
const labels = ref([]);

// Store
const emailStore = useEmailStore();

// Notifications
const showSuccess = inject('showSuccess');
const showError = inject('showError');
const showWarning = inject('showWarning', (message) => {
  // Fallback if showWarning is not provided
  console.warn(message);
  showError(message);
});

// Methods
const loadEmails = async () => {
  loading.value = true;
  try {
    const queryParams = {};
    
    if (pagination.value.current_page) queryParams.page = pagination.value.current_page;
    if (pagination.value.per_page) queryParams.per_page = pagination.value.per_page;
    if (emailStore.searchQuery) queryParams.search = emailStore.searchQuery;
    if (emailStore.sortBy) queryParams.sort_by = emailStore.sortBy;
    if (emailStore.sortOrder) queryParams.sort_order = emailStore.sortOrder;
    
    const params = new URLSearchParams(queryParams);

    const response = await fetch(`/api/emails?${params}`);
    const data = await response.json();

    if (data.success) {
      emails.value = data.emails;
      pagination.value = {
        current_page: data.pagination.current_page,
        last_page: data.pagination.last_page,
        per_page: data.pagination.per_page,
        total: data.pagination.total,
        from: data.pagination.from,
        to: data.pagination.to
      };
    }
      } catch (error) {
      console.error('Failed to load emails:', error);
      // Set empty array to prevent undefined errors
      emails.value = [];
    } finally {
      loading.value = false;
    }
};

// Upload methods
const handleDrop = (event) => {
  event.preventDefault();
  isDragOver.value = false;
  
  const files = Array.from(event.dataTransfer.files);
  const msgFiles = files.filter(file => file.name.toLowerCase().endsWith('.msg'));
  
  if (msgFiles.length === 0) {
    showError('Please select only .msg files');
    return;
  }
  
  uploadFiles(msgFiles);
};

const handleFileSelect = (event) => {
  const files = Array.from(event.target.files);
  uploadFiles(files);
};

const uploadFiles = async (files) => {
  if (files.length === 0) return;
  
  uploading.value = true;
  uploadProgress.value = 0;
  uploadError.value = '';
  uploadSuccess.value = false;
  uploadWarnings.value = [];
  
  const formData = new FormData();
  files.forEach(file => formData.append('files[]', file));
  
  try {
    const response = await fetch('/api/upload', {
      method: 'POST',
      body: formData,
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      }
    });
    
    const data = await response.json();
    
    if (data.success) {
      uploadSuccess.value = true;
      uploadSuccessMessage.value = `${data.emails.length} file(s) uploaded successfully`;
      showSuccess(uploadSuccessMessage.value);
      
      // Refresh email list
      await emailStore.loadEmails();
      await loadEmails();
    } else {
      uploadError.value = data.message || 'Upload failed';
      showError(uploadError.value);
    }
    
    // Handle warnings (including duplicates)
    if (data.errors && data.errors.length > 0) {
      const warnings = data.errors.filter(error => error.warning);
      const errors = data.errors.filter(error => !error.warning);
      
      // Populate warnings for display
      uploadWarnings.value = warnings.map(warning => ({
        filename: warning.filename,
        message: warning.error === 'Duplicate email detected' 
          ? `${warning.error} - ${warning.duplicate_info.message}`
          : warning.error
      }));
      
      // Show warnings for duplicates
      warnings.forEach(warning => {
        if (warning.error === 'Duplicate email detected') {
          showWarning(`${warning.filename}: ${warning.error} - ${warning.duplicate_info.message}`);
        } else {
          showWarning(`${warning.filename}: ${warning.error}`);
        }
      });
      
      // Show errors for other issues
      errors.forEach(error => {
        showError(`${error.filename}: ${error.error}`);
      });
    }
  } catch (error) {
    uploadError.value = 'Network error occurred';
    showError(uploadError.value);
  } finally {
    uploading.value = false;
    uploadProgress.value = 0;
    currentFileName.value = '';
    
    // Clear file input
    if (fileInput.value) {
      fileInput.value.value = '';
    }
  }
};

// Search and filter methods
const handleSearch = () => {
  emailStore.setSearchQuery(searchQuery.value);
  emailStore.loadEmails();
  loadEmails();
};

const clearSearch = () => {
  searchQuery.value = '';
  emailStore.setSearchQuery('');
  emailStore.loadEmails();
  loadEmails();
};

const handleFilter = () => {
  // Update store filters and reload with API-supported params
  const params = {
    // Convert UI to backend query params
    ...(attachmentFilter.value === 'with' ? { has_attachments: 'true' } : {}),
    ...(attachmentFilter.value === 'without' ? { has_attachments: 'false' } : {}),
    ...(sizeFilter.value ? { size_filter: sizeFilter.value } : {}),
  };
  emailStore.loadEmails(params);
  loadEmails();
};

const handleSort = () => {
  emailStore.setSortOptions(sortBy.value, sortOrder.value);
  emailStore.loadEmails();
  loadEmails();
};

// Label-related methods
const selectLabel = (label) => {
  selectedLabel.value = label;
  emailStore.setSelectedLabel(label);
  emailStore.loadEmailsByLabel(label.id);
  loadEmails();
};

const clearLabelFilter = () => {
  selectedLabel.value = null;
  emailStore.clearLabelFilter();
  loadEmails();
};

const refreshLabels = async () => {
  try {
    const response = await fetch('/api/labels');
    if (response.ok) {
      const data = await response.json();
      if (data.success) {
        labels.value = data.labels;
      }
    }
  } catch (error) {
    console.error('Failed to refresh labels:', error);
  }
};

// Email list methods
const selectEmail = (emailId) => {
  emit('email-selected', emailId);
  emailActionsOpen.value = null;
};

const toggleEmailActions = (emailId) => {
  emailActionsOpen.value = emailActionsOpen.value === emailId ? null : emailId;
};

const changePage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) {
    pagination.value.current_page = page;
    loadEmails();
  }
};

const refreshEmails = () => {
  loadEmails();
};

const exportEmail = async (emailId) => {
  try {
    const response = await fetch(`/api/emails/${emailId}/export-pdf`);
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
            a.download = `email-${emailId}.pdf`;
            a.click();
            window.URL.revokeObjectURL(url);
            showSuccess('Email exported successfully as PDF');
          }
        } else if (data.html_url) {
          // HTML was generated as fallback
          showSuccess('HTML version created. You can download it and print to PDF manually.');
          const htmlResponse = await fetch(data.html_url);
          if (htmlResponse.ok) {
            const blob = await htmlResponse.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `email-${emailId}.html`;
            a.click();
            window.URL.revokeObjectURL(url);
          }
        } else {
          showSuccess(data.message || 'Export completed');
        }
      } else {
        showError(data.message || 'Export failed');
      }
    } else {
      showError('Failed to export email');
    }
  } catch (error) {
    console.error('Export failed:', error);
    showError('An error occurred while exporting the email');
  }
  emailActionsOpen.value = null;
};

const downloadAttachments = async (emailId) => {
  try {
    const response = await fetch(`/api/attachments/email/${emailId}/download-all`);
    if (response.ok) {
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `attachments-${emailId}.zip`;
      a.click();
      window.URL.revokeObjectURL(url);
      showSuccess('Attachments downloaded successfully');
    } else {
      showError('Failed to download attachments');
    }
  } catch (error) {
    console.error('Download failed:', error);
    showError('An error occurred while downloading attachments');
  }
  emailActionsOpen.value = null;
};

const deleteEmail = async (emailId) => {
  if (confirm('Are you sure you want to delete this email? This action cannot be undone.')) {
    try {
      const success = await emailStore.deleteEmail(emailId);
      if (success) {
        await loadEmails();
        if (props.selectedEmailId === emailId) {
          emit('email-selected', null);
        }
        showSuccess('Email deleted successfully');
      } else {
        showError('Failed to delete email');
      }
    } catch (error) {
      console.error('Delete failed:', error);
      showError('An error occurred while deleting the email');
    }
  }
  emailActionsOpen.value = null;
};

const formatDate = (dateString) => {
  if (!dateString) return 'Unknown Date';
  
  const date = new Date(dateString);
  const now = new Date();
  const diffTime = Math.abs(now - date);
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

  if (diffDays === 1) {
    return 'Today';
  } else if (diffDays === 2) {
    return 'Yesterday';
  } else if (diffDays <= 7) {
    return date.toLocaleDateString('en-US', { weekday: 'short' });
  } else {
    return date.toLocaleDateString('en-US', { 
      month: 'short', 
      day: 'numeric',
      year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
    });
  }
};

const formatFileSize = (bytes) => {
  if (!bytes) return '0 B';
  
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
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

// Watchers
watch(() => emailStore.searchQuery, () => {
  pagination.value.current_page = 1;
  loadEmails();
});

watch(() => emailStore.sortBy, () => {
  pagination.value.current_page = 1;
  loadEmails();
});

watch(() => emailStore.sortOrder, () => {
  pagination.value.current_page = 1;
  loadEmails();
});

// Lifecycle
onMounted(() => {
  // Ensure pagination has default values
  if (!pagination.value.current_page) pagination.value.current_page = 1;
  if (!pagination.value.per_page) pagination.value.per_page = 20;
  
  loadEmails();
  refreshLabels();
  
  // Close dropdown when clicking outside
  document.addEventListener('click', (event) => {
    if (!event.target.closest('.email-actions')) {
      emailActionsOpen.value = null;
    }
  });
});
</script>

<style scoped>
.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Make email list items more compact */
.email-list-item {
  min-height: 80px;
}

/* Responsive adjustments for compact layout */
@media (max-width: 1024px) {
  .email-list-item {
    min-height: 70px;
  }
}
</style> 