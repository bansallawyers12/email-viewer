<template>
  <div class="label-manager">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-2xl font-bold text-gray-900">Label Management</h2>
      <button
        @click="showCreateModal = true"
        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2"
      >
        <i class="fas fa-plus"></i>
        Create Label
      </button>
    </div>

    <!-- Labels Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="label in labels"
        :key="label.id"
        class="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md transition-shadow"
      >
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-3">
            <div
              class="w-4 h-4 rounded-full"
              :style="{ backgroundColor: label.color }"
            ></div>
            <span class="font-medium text-gray-900">{{ label.name }}</span>
            <span
              v-if="label.type === 'system'"
              class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full"
            >
              System
            </span>
          </div>
          <div class="flex items-center gap-2">
            <button
              v-if="label.type === 'custom'"
              @click="editLabel(label)"
              class="text-gray-400 hover:text-blue-600 p-1"
              title="Edit label"
            >
              <i class="fas fa-edit"></i>
            </button>
            <button
              v-if="label.type === 'custom'"
              @click="deleteLabel(label)"
              class="text-gray-400 hover:text-red-600 p-1"
              title="Delete label"
            >
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </div>
        
        <p v-if="label.description" class="text-sm text-gray-600 mb-3">
          {{ label.description }}
        </p>
        
        <div class="flex items-center justify-between text-sm text-gray-500">
          <span>
            <i :class="label.display_icon"></i>
            {{ label.emails_count || 0 }} emails
          </span>
          <span v-if="label.type === 'custom'">
            <button
              @click="toggleLabelStatus(label)"
              :class="[
                'px-2 py-1 rounded text-xs',
                label.is_active
                  ? 'bg-green-100 text-green-700'
                  : 'bg-gray-100 text-gray-700'
              ]"
            >
              {{ label.is_active ? 'Active' : 'Inactive' }}
            </button>
          </span>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div
      v-if="showCreateModal || showEditModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="closeModal"
    >
      <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold mb-4">
          {{ showEditModal ? 'Edit Label' : 'Create New Label' }}
        </h3>
        
        <form @submit.prevent="showEditModal ? updateLabel() : createLabel()">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Label Name
            </label>
            <input
              v-model="form.name"
              type="text"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter label name"
            />
          </div>
          
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Color
            </label>
            <div class="flex items-center gap-3">
              <input
                v-model="form.color"
                type="color"
                class="w-12 h-10 border border-gray-300 rounded cursor-pointer"
              />
              <input
                v-model="form.color"
                type="text"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="#3B82F6"
              />
            </div>
          </div>
          
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Description (Optional)
            </label>
            <textarea
              v-model="form.description"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter description"
            ></textarea>
          </div>
          
          <div class="flex justify-end gap-3">
            <button
              type="button"
              @click="closeModal"
              class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
            >
              {{ showEditModal ? 'Update' : 'Create' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
      v-if="showDeleteModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    >
      <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold mb-4 text-red-600">Delete Label</h3>
        <p class="text-gray-600 mb-6">
          Are you sure you want to delete the label "{{ labelToDelete?.name }}"? 
          This action cannot be undone and will remove the label from all emails.
        </p>
        
        <div class="flex justify-end gap-3">
          <button
            @click="showDeleteModal = false"
            class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            @click="confirmDeleteLabel"
            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
          >
            Delete
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, reactive } from 'vue'

export default {
  name: 'LabelManager',
  setup() {
    const labels = ref([])
    const showCreateModal = ref(false)
    const showEditModal = ref(false)
    const showDeleteModal = ref(false)
    const labelToDelete = ref(null)
    const editingLabel = ref(null)
    
    const form = reactive({
      name: '',
      color: '#3B82F6',
      description: ''
    })

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

    const createLabel = async () => {
      try {
        const response = await fetch('/api/labels', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
          },
          body: JSON.stringify(form)
        })

        if (response.ok) {
          const data = await response.json()
          labels.value.push(data.label)
          closeModal()
          resetForm()
        } else {
          const error = await response.json()
          alert(error.message || 'Failed to create label')
        }
      } catch (error) {
        console.error('Failed to create label:', error)
        alert('An error occurred while creating the label')
      }
    }

    const editLabel = (label) => {
      editingLabel.value = label
      form.name = label.name
      form.color = label.color
      form.description = label.description || ''
      showEditModal.value = true
    }

    const updateLabel = async () => {
      try {
        const response = await fetch(`/api/labels/${editingLabel.value.id}`, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
          },
          body: JSON.stringify(form)
        })

        if (response.ok) {
          const data = await response.json()
          const index = labels.value.findIndex(l => l.id === editingLabel.value.id)
          if (index !== -1) {
            labels.value[index] = data.label
          }
          closeModal()
          resetForm()
        } else {
          const error = await response.json()
          alert(error.message || 'Failed to update label')
        }
      } catch (error) {
        console.error('Failed to update label:', error)
        alert('An error occurred while updating the label')
      }
    }

    const deleteLabel = (label) => {
      labelToDelete.value = label
      showDeleteModal.value = true
    }

    const confirmDeleteLabel = async () => {
      try {
        const response = await fetch(`/api/labels/${labelToDelete.value.id}`, {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
          }
        })

        if (response.ok) {
          const index = labels.value.findIndex(l => l.id === labelToDelete.value.id)
          if (index !== -1) {
            labels.value.splice(index, 1)
          }
          showDeleteModal.value = false
          labelToDelete.value = null
        } else {
          const error = await response.json()
          alert(error.message || 'Failed to delete label')
        }
      } catch (error) {
        console.error('Failed to delete label:', error)
        alert('An error occurred while deleting the label')
      }
    }

    const toggleLabelStatus = async (label) => {
      try {
        const response = await fetch(`/api/labels/${label.id}`, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
          },
          body: JSON.stringify({
            is_active: !label.is_active
          })
        })

        if (response.ok) {
          const data = await response.json()
          const index = labels.value.findIndex(l => l.id === label.id)
          if (index !== -1) {
            labels.value[index] = data.label
          }
        }
      } catch (error) {
        console.error('Failed to toggle label status:', error)
      }
    }

    const closeModal = () => {
      showCreateModal.value = false
      showEditModal.value = false
      resetForm()
    }

    const resetForm = () => {
      form.name = ''
      form.color = '#3B82F6'
      form.description = ''
      editingLabel.value = null
    }

    onMounted(() => {
      loadLabels()
    })

    return {
      labels,
      showCreateModal,
      showEditModal,
      showDeleteModal,
      labelToDelete,
      form,
      createLabel,
      editLabel,
      updateLabel,
      deleteLabel,
      confirmDeleteLabel,
      toggleLabelStatus,
      closeModal
    }
  }
}
</script>

<style scoped>
.label-manager {
  @apply p-6;
}
</style>
