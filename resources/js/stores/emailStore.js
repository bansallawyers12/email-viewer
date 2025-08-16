import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const useEmailStore = defineStore('email', () => {
  // State
  const emails = ref([]);
  const selectedEmail = ref(null);
  const loading = ref(false);
  const searchQuery = ref('');
  const sortBy = ref('date');
  const sortOrder = ref('desc');
  const selectedLabel = ref(null);
  const labels = ref([]);
  const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
    from: 0,
    to: 0
  });

  // Getters
  const filteredEmails = computed(() => {
    let filtered = [...emails.value];

    // Apply search filter
    if (searchQuery.value) {
      const query = searchQuery.value.toLowerCase();
      filtered = filtered.filter(email => 
        email.subject?.toLowerCase().includes(query) ||
        email.sender?.toLowerCase().includes(query) ||
        email.recipients?.toLowerCase().includes(query) ||
        email.preview?.toLowerCase().includes(query)
      );
    }



    // Apply sorting
    filtered.sort((a, b) => {
      let aValue, bValue;
      
      switch (sortBy.value) {
        case 'subject':
          aValue = a.subject || '';
          bValue = b.subject || '';
          break;
        case 'sender':
          aValue = a.sender || '';
          bValue = b.sender || '';
          break;
        case 'size':
          aValue = a.file_size || 0;
          bValue = b.file_size || 0;
          break;
        case 'date':
        default:
          aValue = new Date(a.date || 0);
          bValue = new Date(b.date || 0);
          break;
      }

      if (sortOrder.value === 'asc') {
        return aValue > bValue ? 1 : -1;
      } else {
        return aValue < bValue ? 1 : -1;
      }
    });

    return filtered;
  });

  const totalEmails = computed(() => emails.value.length);
  const emailsWithAttachments = computed(() => 
    emails.value.filter(email => email.has_attachments)
  );
  const totalAttachments = computed(() => 
    emails.value.reduce((total, email) => total + (email.attachment_count || 0), 0)
  );

  // Actions
  const setEmails = (newEmails) => {
    emails.value = newEmails;
  };

  const addEmail = (email) => {
    emails.value.unshift(email);
  };

  const updateEmail = (emailId, updates) => {
    const index = emails.value.findIndex(email => email.id === emailId);
    if (index !== -1) {
      emails.value[index] = { ...emails.value[index], ...updates };
    }
  };

  const removeEmail = (emailId) => {
    const index = emails.value.findIndex(email => email.id === emailId);
    if (index !== -1) {
      emails.value.splice(index, 1);
    }
  };

  const clearEmails = () => {
    emails.value = [];
    selectedEmail.value = null;
  };

  const setSelectedEmail = (email) => {
    selectedEmail.value = email;
  };

  const setLoading = (isLoading) => {
    loading.value = isLoading;
  };

  const setSearchQuery = (query) => {
    searchQuery.value = query;
  };



  const setSortOptions = (sort, order) => {
    sortBy.value = sort;
    sortOrder.value = order;
  };

  const setPagination = (paginationData) => {
    pagination.value = { ...paginationData };
  };

  const loadEmails = async (params = {}) => {
    setLoading(true);
    try {
      const queryParams = {};
      
      if (params.page || pagination.value.current_page) queryParams.page = params.page || pagination.value.current_page;
      if (params.per_page || pagination.value.per_page) queryParams.per_page = params.per_page || pagination.value.per_page;
      if (searchQuery.value) queryParams.search = searchQuery.value;
      if (sortBy.value) queryParams.sort_by = sortBy.value;
      if (sortOrder.value) queryParams.sort_order = sortOrder.value;
      
      // Add any additional params
      Object.assign(queryParams, params);
      
      const queryString = new URLSearchParams(queryParams);

      const response = await fetch(`/api/emails?${queryString}`);
      const data = await response.json();

      if (data.success) {
        setEmails(data.emails);
        setPagination({
          current_page: data.pagination.current_page,
          last_page: data.pagination.last_page,
          per_page: data.pagination.per_page,
          total: data.pagination.total,
          from: data.pagination.from,
          to: data.pagination.to
        });
      }
    } catch (error) {
      console.error('Failed to load emails:', error);
      // Set empty array to prevent undefined errors
      setEmails([]);
    } finally {
      setLoading(false);
    }
  };

  const loadEmail = async (emailId) => {
    if (!emailId) {
      setSelectedEmail(null);
      return;
    }

    setLoading(true);
    try {
      const response = await fetch(`/api/emails/${emailId}`);
      const data = await response.json();

      if (data.success) {
        setSelectedEmail(data.email);
      }
    } catch (error) {
      console.error('Failed to load email:', error);
    } finally {
      setLoading(false);
    }
  };

  const deleteEmail = async (emailId) => {
    try {
      const response = await fetch(`/api/emails/${emailId}`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success) {
          removeEmail(emailId);
          if (selectedEmail.value?.id === emailId) {
            setSelectedEmail(null);
          }
          return true;
        } else {
          console.error('Delete failed:', data.message);
          return false;
        }
      } else {
        console.error('Delete failed with status:', response.status);
        return false;
      }
    } catch (error) {
      console.error('Failed to delete email:', error);
      return false;
    }
  };

  const exportEmail = async (emailId) => {
    try {
      const response = await fetch(`/api/emails/${emailId}/export-pdf`);
      if (response.ok) {
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `email-${emailId}.pdf`;
        a.click();
        window.URL.revokeObjectURL(url);
        return true;
      }
    } catch (error) {
      console.error('Failed to export email:', error);
    }
    return false;
  };

  const getEmailStatistics = async (emailId) => {
    try {
      const response = await fetch(`/api/emails/${emailId}/statistics`);
      const data = await response.json();
      return data.success ? data.statistics : null;
    } catch (error) {
      console.error('Failed to get email statistics:', error);
      return null;
    }
  };

  const getAttachmentStatistics = async (emailId) => {
    try {
      const response = await fetch(`/api/attachments/email/${emailId}/statistics`);
      const data = await response.json();
      return data.success ? data.statistics : null;
    } catch (error) {
      console.error('Failed to get attachment statistics:', error);
      return null;
    }
  };

  // Label-related actions
  const setSelectedLabel = (label) => {
    selectedLabel.value = label;
  };

  const setLabels = (newLabels) => {
    labels.value = newLabels;
  };

  const loadLabels = async () => {
    try {
      const response = await fetch('/api/labels');
      if (response.ok) {
        const data = await response.json();
        if (data.success) {
          setLabels(data.labels);
        }
      }
    } catch (error) {
      console.error('Failed to load labels:', error);
    }
  };

  const loadEmailsByLabel = async (labelId, params = {}) => {
    setLoading(true);
    try {
      const queryParams = { ...params };
      const queryString = new URLSearchParams(queryParams);
      
      const response = await fetch(`/api/labels/${labelId}/emails?${queryString}`);
      const data = await response.json();

      if (data.success) {
        setEmails(data.emails.data || []);
        setPagination({
          current_page: data.emails.current_page || 1,
          last_page: data.emails.last_page || 1,
          per_page: data.emails.per_page || 20,
          total: data.emails.total || 0,
          from: data.emails.from || 0,
          to: data.emails.to || 0
        });
      }
    } catch (error) {
      console.error('Failed to load emails by label:', error);
      setEmails([]);
    } finally {
      setLoading(false);
    }
  };

  const clearLabelFilter = () => {
    selectedLabel.value = null;
    loadEmails();
  };

  return {
    // State
    emails,
    selectedEmail,
    loading,
    searchQuery,
    sortBy,
    sortOrder,
    selectedLabel,
    labels,
    pagination,

    // Getters
    filteredEmails,
    totalEmails,
    emailsWithAttachments,
    totalAttachments,

    // Actions
    setEmails,
    addEmail,
    updateEmail,
    removeEmail,
    clearEmails,
    setSelectedEmail,
    setLoading,
    setSearchQuery,
    setSortOptions,
    setPagination,
    loadEmails,
    loadEmail,
    deleteEmail,
    exportEmail,
    getEmailStatistics,
    getAttachmentStatistics,

    // Label actions
    setSelectedLabel,
    setLabels,
    loadLabels,
    loadEmailsByLabel,
    clearLabelFilter
  };
}); 