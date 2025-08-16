<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-4">
          <div class="flex items-center">
            <h1 class="text-xl font-semibold text-gray-900">
              <i class="fas fa-envelope mr-2 text-blue-600"></i>
              Email Viewer
            </h1>
          </div>
          <div class="flex items-center space-x-4">
          </div>
        </div>
      </div>
    </header>

    <!-- Mobile Navigation Toggle -->
    <div class="lg:hidden bg-white border-b border-gray-200">
      <div class="px-4 py-2">
        <button
          @click="mobileView = mobileView === 'middle' ? 'right' : 'middle'"
          class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors flex items-center justify-center"
        >
          <i class="fas fa-bars mr-2"></i>
          {{ mobileView === 'middle' ? 'Email List' : 'Email Details' }}
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="appLoading" class="flex items-center justify-center h-screen">
      <div class="text-center">
        <div class="animate-spin rounded-full h-32 w-32 border-b-2 border-blue-600 mx-auto mb-4"></div>
        <p class="text-gray-600">Loading Email Viewer...</p>
      </div>
    </div>

    <!-- Main Content -->
    <div v-else class="flex h-screen">
      <!-- Middle Panel - Upload, Search & Email List -->
      <div 
        class="bg-white border-r border-gray-200 flex flex-col transition-all duration-300"
        :class="{
          'w-96': !isMobile,
          'w-full absolute inset-0 z-10': isMobile && mobileView === 'middle',
          'hidden': isMobile && mobileView !== 'middle'
        }"
      >
        <MiddlePanel 
          :selected-email-id="selectedEmailId"
          @email-selected="selectEmail"
        />
      </div>

      <!-- Right Panel - Email Details -->
      <div 
        class="bg-white flex flex-col transition-all duration-300"
        :class="{
          'flex-1': !isMobile,
          'w-full absolute inset-0 z-10': isMobile && mobileView === 'right',
          'hidden': isMobile && mobileView !== 'right'
        }"
      >
        <RightPanel 
          :email-id="selectedEmailId"
          v-if="selectedEmailId"
        />
        <div v-else class="flex-1 flex items-center justify-center text-gray-500">
          <div class="text-center">
            <i class="fas fa-envelope-open text-4xl mb-4"></i>
            <p>Select an email to view its contents</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Error Notification -->
    <div 
      v-if="errorMessage" 
      class="fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 max-w-md"
    >
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <i class="fas fa-exclamation-triangle mr-2"></i>
          <span>{{ errorMessage }}</span>
        </div>
        <button @click="errorMessage = null" class="ml-4 text-white hover:text-gray-200">
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>

    <!-- Success Notification -->
    <div 
      v-if="successMessage" 
      class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 max-w-md"
    >
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <i class="fas fa-check-circle mr-2"></i>
          <span>{{ successMessage }}</span>
        </div>
        <button @click="successMessage = null" class="ml-4 text-white hover:text-gray-200">
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, provide } from 'vue';
import MiddlePanel from './components/MiddlePanel.vue';
import RightPanel from './components/RightPanel.vue';
import { useEmailStore } from './stores/emailStore';

// Reactive data
const selectedEmailId = ref(null);
const errorMessage = ref(null);
const successMessage = ref(null);
const mobileView = ref('middle');
const appLoading = ref(true);

// Computed properties
const isMobile = computed(() => window.innerWidth < 1024);

// Methods
const selectEmail = (emailId) => {
  selectedEmailId.value = emailId;
  if (isMobile.value) {
    mobileView.value = 'right';
  }
};



// Handle window resize
const handleResize = () => {
  if (!isMobile.value) {
    mobileView.value = 'middle';
  }
};

// Lifecycle
onMounted(async () => {
  console.log('App.vue mounted - initializing application...');
  window.addEventListener('resize', handleResize);
  
  // Initialize the email store
  try {
    console.log('Loading email store...');
    const emailStore = useEmailStore();
    await emailStore.loadEmails();
    console.log('Email store loaded successfully');
    appLoading.value = false;
  } catch (error) {
    console.error('Failed to initialize email store:', error);
    showError('Failed to load emails. Please refresh the page.');
    appLoading.value = false;
  }
  
  // Auto-hide notifications after 5 seconds
  setInterval(() => {
    if (errorMessage.value) {
      errorMessage.value = null;
    }
    if (successMessage.value) {
      successMessage.value = null;
    }
  }, 5000);
});

// Expose methods for child components
const showError = (message) => {
  errorMessage.value = message;
};

const showSuccess = (message) => {
  successMessage.value = message;
};

// Provide to child components
provide('showError', showError);
provide('showSuccess', showSuccess);

// Error handling
const handleError = (error) => {
  console.error('Application error:', error);
  showError('An unexpected error occurred. Please refresh the page.');
};
</script>

<style scoped>
/* Custom scrollbar */
::-webkit-scrollbar {
  width: 6px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
}

::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}

/* Smooth transitions */
.transition-all {
  transition: all 0.3s ease;
}

/* Mobile optimizations */
@media (max-width: 1023px) {
  .h-screen {
    height: calc(100vh - 120px);
  }
}
</style> 