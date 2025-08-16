<template>
  <div class="email-labeling">
    <!-- Current Labels Summary -->
    <div v-if="emailLabels.length > 0" class="mb-4">
      <div class="flex items-center justify-between mb-2">
        <h4 class="text-sm font-medium text-gray-700">Current Labels ({{ emailLabels.length }})</h4>
        <span class="text-xs text-gray-500">Click X to remove</span>
      </div>
      <div class="flex flex-wrap gap-2">
        <span
          v-for="label in emailLabels"
          :key="label.id"
          class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium"
          :style="{ 
            backgroundColor: label.color + '20',
            color: label.color,
            border: `1px solid ${label.color}40`
          }"
        >
          <i :class="label.display_icon"></i>
          {{ label.name }}
          <button
            @click="removeLabel(label)"
            class="ml-1 hover:bg-white hover:bg-opacity-30 rounded-full w-4 h-4 flex items-center justify-center transition-colors"
            title="Remove label"
          >
            <i class="fas fa-times text-xs"></i>
          </button>
        </span>
      </div>
    </div>
    
    <!-- No Labels Applied Message -->
    <div v-else class="mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
      <div class="text-center text-sm text-gray-500">
        <i class="fas fa-tags text-lg mb-2 text-gray-400"></i>
        <p>No labels applied to this email yet.</p>
        <p class="text-xs mt-1">Add labels below to organize your emails.</p>
      </div>
    </div>

    <!-- Add Label Section -->
    <div class="border-t pt-4">
      <h4 class="text-sm font-medium text-gray-700 mb-2">Add Labels:</h4>
      
      <!-- Label Selection -->
      <div class="flex flex-wrap gap-2 mb-3">
        <button
          v-for="label in availableLabels"
          :key="label.id"
          @click="addLabel(label)"
          class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium border-2 border-dashed border-gray-300 hover:border-gray-400 hover:bg-gray-50 transition-colors"
          :class="{ 'opacity-50': isLabelApplied(label) }"
          :disabled="isLabelApplied(label)"
          :title="isLabelApplied(label) ? 'Label already applied' : 'Click to apply label'"
        >
          <div
            class="w-3 h-3 rounded-full"
            :style="{ backgroundColor: label.color }"
          ></div>
          <i :class="label.display_icon"></i>
          {{ label.name }}
        </button>
      </div>
      
      <!-- No Available Labels Message -->
      <div v-if="availableLabels.length === 0" class="text-sm text-gray-500 italic mb-3">
        All available labels are already applied to this email.
      </div>

      <!-- Quick Label Creation -->
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
        <h5 class="text-sm font-medium text-blue-800 mb-2">Quick Create New Label</h5>
        <div class="flex items-center gap-2">
          <input
            v-model="quickLabelName"
            type="text"
            placeholder="Enter label name..."
            class="flex-1 px-3 py-2 text-sm border border-blue-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
            @keyup.enter="createQuickLabel"
          />
          <input
            v-model="quickLabelColor"
            type="color"
            class="w-8 h-8 border border-blue-300 rounded cursor-pointer bg-white"
            title="Choose label color"
          />
          <button
            @click="createQuickLabel"
            :disabled="!quickLabelName.trim()"
            class="px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            <i class="fas fa-plus mr-1"></i>
            Create & Apply
          </button>
        </div>
        <p class="text-xs text-blue-600 mt-2">
          <i class="fas fa-info-circle mr-1"></i>
          New labels are automatically applied to this email
        </p>
      </div>
    </div>

    <!-- Label Management Link -->
    <div class="mt-4 pt-4 border-t">
      <button
        @click="showLabelManager = true"
        class="text-blue-600 hover:text-blue-700 text-sm flex items-center gap-2"
      >
        <i class="fas fa-cog"></i>
        Manage All Labels
      </button>
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

<script>
import { ref, computed, onMounted, watch } from 'vue'
import LabelManager from './LabelManager.vue'

export default {
  name: 'EmailLabeling',
  components: {
    LabelManager
  },
  props: {
    emailId: {
      type: [Number, String],
      required: true
    }
  },
  emits: ['labels-updated'],
  setup(props, { emit }) {
    const labels = ref([])
    const emailLabels = ref([])
    const showLabelManager = ref(false)
    const quickLabelName = ref('')
    const quickLabelColor = ref('#3B82F6')

    const availableLabels = computed(() => {
      return labels.value.filter(label => 
        label.is_active && !isLabelApplied(label)
      )
    })

    const isLabelApplied = (label) => {
      return emailLabels.value.some(el => el.id === label.id)
    }

    const loadLabels = async () => {
      try {
        const response = await fetch('/api/labels')
        if (response.ok) {
          const data = await response.json()
          labels.value = data.labels
        }
      } catch (error) {
        console.error('Failed to load labels:', error)
      }
    }

    const loadEmailLabels = async () => {
      try {
        const response = await fetch(`/api/emails/${props.emailId}`)
        if (response.ok) {
          const data = await response.json()
          emailLabels.value = data.email.labels || []
        }
      } catch (error) {
        console.error('Failed to load email labels:', error)
      }
    }

    const addLabel = async (label) => {
      try {
        const response = await fetch('/api/labels/apply', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
          },
          body: JSON.stringify({
            email_id: props.emailId,
            label_id: label.id
          })
        })

        if (response.ok) {
          emailLabels.value.push(label)
          emit('labels-updated')
        } else {
          const error = await response.json()
          alert(error.message || 'Failed to apply label')
        }
      } catch (error) {
        console.error('Failed to apply label:', error)
        alert('An error occurred while applying the label')
      }
    }

    const removeLabel = async (label) => {
      try {
        const response = await fetch('/api/labels/remove', {
          method: 'DELETE',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
          },
          body: JSON.stringify({
            email_id: props.emailId,
            label_id: label.id
          })
        })

        if (response.ok) {
          const index = emailLabels.value.findIndex(el => el.id === label.id)
          if (index !== -1) {
            emailLabels.value.splice(index, 1)
          }
          emit('labels-updated')
        } else {
          const error = await response.json()
          alert(error.message || 'Failed to remove label')
        }
      } catch (error) {
        console.error('Failed to remove label:', error)
        alert('An error occurred while removing the label')
      }
    }

    const createQuickLabel = async () => {
      if (!quickLabelName.value.trim()) return

      try {
        const response = await fetch('/api/labels', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
          },
          body: JSON.stringify({
            name: quickLabelName.value.trim(),
            color: quickLabelColor.value,
            description: 'Quick created label'
          })
        })

        if (response.ok) {
          const data = await response.json()
          labels.value.push(data.label)
          
          // Apply the new label to the email
          await addLabel(data.label)
          
          // Reset form
          quickLabelName.value = ''
          quickLabelColor.value = '#3B82F6'
        } else {
          const error = await response.json()
          alert(error.message || 'Failed to create label')
        }
      } catch (error) {
        console.error('Failed to create quick label:', error)
        alert('An error occurred while creating the label')
      }
    }

    const refreshLabels = () => {
      loadLabels()
      loadEmailLabels()
    }

    onMounted(() => {
      loadLabels()
      loadEmailLabels()
    })

    watch(() => props.emailId, () => {
      if (props.emailId) {
        loadEmailLabels()
      }
    })

    return {
      labels,
      emailLabels,
      showLabelManager,
      quickLabelName,
      quickLabelColor,
      availableLabels,
      isLabelApplied,
      addLabel,
      removeLabel,
      createQuickLabel,
      refreshLabels
    }
  }
}
</script>

<style scoped>
.email-labeling {
  @apply p-4;
}
</style>
