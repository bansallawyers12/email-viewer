import axios from 'axios';

let currentPage = 1;
let lastPage = 1;
let isLoading = false;

function getFilters() {
    const form = document.getElementById('search-form');
    const formData = new FormData(form);
    const params = {};
    for (const [key, value] of formData.entries()) {
        if (value !== '') params[key] = value;
    }
    params.page = currentPage;
    params.per_page = 20;
    return params;
}

function renderEmails(items) {
    console.log('Rendering emails:', items);
    const list = document.getElementById('email-items');
    if (!list) {
        console.error('Email list element not found');
        return;
    }
    list.innerHTML = '';
    items.forEach((email) => {
        const li = document.createElement('li');
        li.className = 'px-2 py-1 hover:bg-gray-50 cursor-pointer';
        const sentDate = email.sent_date ? new Date(email.sent_date).toLocaleString() : 'Unknown';
        const attachments = (email.attachments || []).length;
        const labels = (email.labels || []).map(l => `
            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] border" style="background:${l.color || '#eef2ff'}20">
                <i class="${l.icon || 'fas fa-tag'}"></i>
                ${l.name}
            </span>
        `).join(' ');
        li.innerHTML = `
            <div class="space-y-1">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="font-medium truncate">${email.subject || '(No subject)'}</div>
                        <div class="text-xs text-gray-600 truncate">From: ${email.sender_name || email.sender_email || 'Unknown'}</div>
                        <div class="text-xs text-gray-600 truncate">To: ${Array.isArray(email.recipients) ? email.recipients.join(', ') : (email.recipients || 'Unknown')}</div>
                        <div class="text-xs text-gray-500">${sentDate}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="text-xs text-gray-500 whitespace-nowrap">${attachments} attachments</div>
                        <button class="delete-email-btn p-1 text-red-500 hover:text-red-700 hover:bg-red-50 rounded" 
                                data-email-id="${email.id}" 
                                title="Delete email">
                            <i class="fas fa-trash text-xs"></i>
                        </button>
                    </div>
                </div>
                ${labels ? `<div class="flex flex-wrap gap-1">${labels}</div>` : ''}
            </div>
        `;
        li.addEventListener('click', async () => {
            console.log('Email clicked:', email.id, email.subject);
            console.log('Full email object:', email);
            await loadEmailDetail(email.id);
        });
        
        // Add delete button event handler
        const deleteBtn = li.querySelector('.delete-email-btn');
        deleteBtn.addEventListener('click', async (e) => {
            e.stopPropagation(); // Prevent email selection when clicking delete
            await deleteEmail(email.id, email.subject);
        });
        
        list.appendChild(li);
    });
}

function setLoading(state) {
    isLoading = state;
    const indicator = document.getElementById('loading-indicator');
    if (!indicator) return;
    if (isLoading) {
        indicator.classList.remove('hidden');
        indicator.classList.add('flex');
    } else {
        indicator.classList.add('hidden');
        indicator.classList.remove('flex');
    }
}

async function fetchEmails() {
    console.log('Fetching emails...');
    setLoading(true);
    const params = getFilters();
    console.log('Filters:', params);
    try {
        const { data } = await axios.get('/api/emails', { params });
        console.log('API response:', data);
        if (data && data.success) {
            console.log('Emails to render:', data.emails);
            renderEmails(data.emails);
            const total = data.pagination.total;
            currentPage = data.pagination.current_page;
            lastPage = data.pagination.last_page;
            document.getElementById('total-count').textContent = total;
            document.getElementById('page-info').textContent = `${currentPage} / ${lastPage}`;
        }
    } catch (error) {
        console.error('Error fetching emails:', error);
    } finally {
        setLoading(false);
    }
}

export function initializeEmailList() {
    const prev = document.getElementById('prev-page');
    const next = document.getElementById('next-page');
    
    prev.addEventListener('click', (e) => {
        e.preventDefault();
        if (currentPage > 1) {
            currentPage -= 1;
            fetchEmails();
        }
    });
    next.addEventListener('click', (e) => {
        e.preventDefault();
        if (currentPage < lastPage) {
            currentPage += 1;
            fetchEmails();
        }
    });
    
    // First load
    fetchEmails();
}

export function refreshEmailList() {
    currentPage = 1;
    fetchEmails();
}

async function deleteEmail(emailId, emailSubject) {
    if (!confirm(`Are you sure you want to delete "${emailSubject || 'this email'}"? This action cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await axios.delete(`/api/emails/${emailId}`);
        if (response.data && response.data.success) {
            // Show success message
            showNotification('Email deleted successfully', 'success');
            // Refresh the email list
            refreshEmailList();
            // Clear email detail if the deleted email was selected
            const detail = document.getElementById('email-detail');
            if (detail) {
                detail.innerHTML = '<div class="h-full flex items-center justify-center text-gray-400">Select an email to view its contents</div>';
            }
        } else {
            showNotification('Failed to delete email', 'error');
        }
    } catch (error) {
        console.error('Error deleting email:', error);
        showNotification('Error deleting email: ' + (error.response?.data?.message || error.message), 'error');
    }
}



function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 max-w-sm ${
        type === 'success' ? 'bg-green-500 text-white' : 
        type === 'error' ? 'bg-red-500 text-white' : 
        'bg-blue-500 text-white'
    }`;
    notification.textContent = message;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 3000);
}

async function downloadAllAttachments(emailId, emailSubject) {
    try {
        // Show loading state
        const downloadBtn = document.getElementById('download-all-attachments');
        if (downloadBtn) {
            const originalText = downloadBtn.innerHTML;
            downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Downloading...';
            downloadBtn.disabled = true;
            
            // Download the ZIP file
            const response = await axios.get(`/api/attachments/email/${emailId}/download-all`, {
                responseType: 'blob'
            });
            
            // Create download link
            const blob = new Blob([response.data]);
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${emailSubject || 'email'}_attachments.zip`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            // Show success message
            showNotification('Attachments downloaded successfully', 'success');
            
            // Reset button
            downloadBtn.innerHTML = originalText;
            downloadBtn.disabled = false;
        }
    } catch (error) {
        console.error('Error downloading attachments:', error);
        showNotification('Error downloading attachments: ' + (error.response?.data?.message || error.message), 'error');
        
        // Reset button on error
        const downloadBtn = document.getElementById('download-all-attachments');
        if (downloadBtn) {
            downloadBtn.innerHTML = '<i class="fas fa-download mr-1"></i>Download All';
            downloadBtn.disabled = false;
        }
    }
}

async function downloadAttachment(attachmentId, filename) {
    try {
        // Download the individual attachment
        const response = await axios.get(`/api/attachments/${attachmentId}/download`, {
            responseType: 'blob'
        });
        
        // Create download link
        const blob = new Blob([response.data]);
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        // Show success message
        showNotification(`Downloaded: ${filename}`, 'success');
        
    } catch (error) {
        console.error('Error downloading attachment:', error);
        showNotification('Error downloading attachment: ' + (error.response?.data?.message || error.message), 'error');
    }
}

export async function loadEmailDetail(emailId) {
    console.log('Loading email detail for ID:', emailId);
    const detail = document.getElementById('email-detail');
    console.log('Email detail element:', detail);
    if (!detail) {
        console.error('Email detail element not found');
        return;
    }
    detail.innerHTML = '<div class="h-full flex items-center justify-center text-gray-400">Loading…</div>';
    try {
        console.log('Making API call to:', `/api/emails/${emailId}`);
        const { data } = await axios.get(`/api/emails/${emailId}`);
        console.log('API response for email detail:', data);
        if (data && data.success && data.email) {
            const email = data.email;
            
            // Debug: Log the email data to see what fields are available
            console.log('Email data received:', email);
            console.log('Sender fields:', {
                sender_name: email.sender_name,
                sender_email: email.sender_email
            });
            console.log('Recipients:', email.recipients);
            console.log('Received date:', email.received_date);
            console.log('All email fields:', Object.keys(email));
            console.log('Raw email object:', JSON.stringify(email, null, 2));
            
            const sentDate = email.sent_date ? new Date(email.sent_date).toLocaleString() : 'Unknown';
            const attachments = (email.attachments || []).map(a => `
                <li class=\"text-xs flex items-center justify-between py-1\">
                    <span>${a.filename} (${a.formatted_file_size || a.file_size || ''})</span>
                    <button class=\"download-attachment-btn ml-2 px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xs\" 
                            data-attachment-id=\"${a.id}\" 
                            data-filename=\"${a.filename}\"
                            title=\"Download ${a.filename}\">
                        <i class=\"fas fa-download\"></i>
                    </button>
                </li>
            `).join('');
            const labels = (email.labels || []).map(l => `
                <span class=\"inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs border\" style=\"background:${l.color || '#eef2ff'}20\">
                    <i class=\"${l.icon || 'fas fa-tag'}\"></i>
                    ${l.name}
                    <button class=\"remove-label text-gray-500 hover:text-red-600\" data-label-id=\"${l.id}\" title=\"Remove\">×</button>
                </span>
            `).join(' ');

            detail.innerHTML = `
                <div class=\"space-y-3\">
                    <div>
                        <div class=\"text-base font-semibold\">${email.subject || '(No subject)'}</div>
                        <div class=\"mt-1 grid grid-cols-2 gap-2 text-xs text-gray-600\">
                            <div><span class=\"font-medium\">From:</span> ${formatSenderDisplay(email)}</div>
                            <div><span class=\"font-medium\">To:</span> ${formatRecipientsDisplay(email.recipients)}</div>
                            <div><span class=\"font-medium\">Date:</span> ${sentDate}</div>
                            <div><span class=\"font-medium\">Received:</span> ${email.received_date ? new Date(email.received_date).toLocaleString() : 'Unknown'}</div>
                            <div><span class=\"font-medium\">Size:</span> ${email.formatted_file_size || (email.file_size || '')}</div>
                            <div><span class=\"font-medium\">Status:</span> ${email.status || ''}</div>
                        </div>
                        
                        ${(!email.sender_name && !email.sender_email) ? '<div class=\"mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs text-yellow-800\"><strong>Warning:</strong> Sender information is missing. This may indicate a parsing issue.</div>' : ''}
                        ${(!email.recipients || (Array.isArray(email.recipients) && email.recipients.length === 0)) ? '<div class=\"mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs text-yellow-800\"><strong>Warning:</strong> Recipient information is missing. This may indicate a parsing issue.</div>' : ''}
                        
                        ${(!email.sender_name && !email.sender_email) || (!email.recipients || (Array.isArray(email.recipients) && email.recipients.length === 0)) ? 
                            '<div class=\"mt-2\"><button id=\"reparse-email-btn\" class=\"px-3 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700\"><i class=\"fas fa-sync-alt mr-1\"></i>Reparse Email</button></div>' : ''}
                    </div>

                    <div class=\"border-t pt-2\">
                        <div class=\"text-sm font-medium mb-1\">Labels</div>
                        <div id=\"label-chips\" class=\"flex flex-wrap gap-2 mb-2\">${labels || '<span class=\\"text-xs text-gray-400\\">No labels</span>'}</div>
                        <div class=\"flex items-center gap-2\">
                            <select id=\"detail-label-select\" class=\"border rounded px-2 py-1 text-sm\"><option value=\"\">Select label</option></select>
                            <button id=\"add-label-btn\" class=\"px-2 py-1 bg-gray-800 text-white rounded text-xs\">Add Label</button>
                        </div>
                    </div>

                    <div class=\"prose prose-sm max-w-none border-t pt-2\">${email.html_content || ''}</div>
                    ${attachments ? `
                        <div class=\"pt-2 border-t\">
                            <div class=\"flex items-center justify-between mb-2\">
                                <div class=\"text-sm font-medium\">Attachments</div>
                                <button id=\"download-all-attachments\" class=\"px-3 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700\">
                                    <i class=\"fas fa-download mr-1\"></i>Download All
                                </button>
                            </div>
                            <ul class=\"list-disc pl-4\">${attachments}</ul>
                        </div>
                    ` : ''}
                </div>
            `;

            // Load all labels for the select
            try {
                const labelsRes = await axios.get('/api/labels');
                if (labelsRes.data && labelsRes.data.success && Array.isArray(labelsRes.data.labels)) {
                    const select = detail.querySelector('#detail-label-select');
                    labelsRes.data.labels.forEach(l => {
                        const opt = document.createElement('option');
                        opt.value = l.id;
                        opt.textContent = l.name;
                        select.appendChild(opt);
                    });
                }
            } catch (_) {}

            // Add label handler
            const addBtn = detail.querySelector('#add-label-btn');
            addBtn.addEventListener('click', async () => {
                const select = detail.querySelector('#detail-label-select');
                const labelId = select.value;
                if (!labelId) return;
                try {
                    await axios.post('/api/labels/apply', { email_id: emailId, label_id: Number(labelId) });
                    loadEmailDetail(emailId);
                } catch (_) {}
            });

            // Remove label handlers
            detail.querySelectorAll('.remove-label').forEach((btn) => {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const labelId = Number(btn.getAttribute('data-label-id'));
                    try {
                        await axios({ method: 'delete', url: '/api/labels/remove', data: { email_id: emailId, label_id: labelId } });
                        loadEmailDetail(emailId);
                    } catch (_) {}
                });
            });
            
            // Download all attachments handler
            const downloadAllBtn = detail.querySelector('#download-all-attachments');
            if (downloadAllBtn) {
                downloadAllBtn.addEventListener('click', async () => {
                    await downloadAllAttachments(emailId, email.subject);
                });
            }
            
            // Individual attachment download handlers
            detail.querySelectorAll('.download-attachment-btn').forEach((btn) => {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const attachmentId = btn.getAttribute('data-attachment-id');
                    const filename = btn.getAttribute('data-filename');
                    await downloadAttachment(attachmentId, filename);
                });
            });
            
            // Reparse email handler
            const reparseBtn = detail.querySelector('#reparse-email-btn');
            if (reparseBtn) {
                reparseBtn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    reparseBtn.disabled = true;
                    reparseBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Reparsing...';
                    
                    try {
                        const response = await axios.post(`/api/emails/${emailId}/reparse`);
                        if (response.data && response.data.success) {
                            showNotification('Email reparsed successfully!', 'success');
                            // Reload the email detail
                            loadEmailDetail(emailId);
                        } else {
                            showNotification('Failed to reparse email', 'error');
                        }
                    } catch (error) {
                        showNotification('Error reparsing email: ' + (error.response?.data?.message || error.message), 'error');
                    } finally {
                        reparseBtn.disabled = false;
                        reparseBtn.innerHTML = '<i class="fas fa-sync-alt mr-1"></i>Reparse Email';
                    }
                });
            }
        } else {
            detail.textContent = 'Failed to load email.';
        }
    } catch (e) {
        detail.textContent = 'Failed to load email.';
    }
}

function formatSenderDisplay(email) {
    if (email.sender_name && email.sender_email) {
        return `${email.sender_name} <${email.sender_email}>`;
    } else if (email.sender_name) {
        return email.sender_name;
    } else if (email.sender_email) {
        return email.sender_email;
    }
    return 'Unknown';
}

function formatRecipientsDisplay(recipients) {
    if (!recipients) {
        return 'Unknown';
    }
    
    if (Array.isArray(recipients)) {
        if (recipients.length === 0) {
            return 'Unknown';
        }
        return recipients.join(', ');
    }
    
    // Handle case where recipients might be a string
    if (typeof recipients === 'string') {
        return recipients || 'Unknown';
    }
    
    return 'Unknown';
}


