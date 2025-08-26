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
                <span class="text-[8px]">${l.icon || ''}</span>
                ${l.name}
            </span>
        `).join(' ');
        li.innerHTML = `
            <div class="space-y-1">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-medium truncate">${email.subject || '(No subject)'}</div>
                        <div class="text-xs text-gray-600 truncate">From: ${email.sender_name || email.sender_email || 'Unknown'}</div>
                        <div class="text-xs text-gray-500">${sentDate}</div>
                    </div>
                    <div class="text-xs text-gray-500 whitespace-nowrap">${attachments} attachments</div>
                </div>
                ${labels ? `<div class="flex flex-wrap gap-1">${labels}</div>` : ''}
            </div>
        `;
        li.addEventListener('click', async () => {
            console.log('Email clicked:', email.id, email.subject);
            console.log('Full email object:', email);
            await loadEmailDetail(email.id);
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
            const sentDate = email.sent_date ? new Date(email.sent_date).toLocaleString() : 'Unknown';
            const attachments = (email.attachments || []).map(a => `<li class=\"text-xs\">${a.filename} (${a.formatted_file_size || a.file_size || ''})</li>`).join('');
            const labels = (email.labels || []).map(l => `
                <span class=\"inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs border\" style=\"background:${l.color || '#eef2ff'}20\">
                    <span class=\"text-[10px]\">${l.icon || ''}</span>
                    ${l.name}
                    <button class=\"remove-label text-gray-500 hover:text-red-600\" data-label-id=\"${l.id}\" title=\"Remove\">×</button>
                </span>
            `).join(' ');

            detail.innerHTML = `
                <div class=\"space-y-3\">
                    <div>
                        <div class=\"text-base font-semibold\">${email.subject || '(No subject)'}</div>
                        <div class=\"mt-1 grid grid-cols-2 gap-2 text-xs text-gray-600\">
                            <div><span class=\"font-medium\">From:</span> ${email.sender_name || email.sender_email || 'Unknown'}</div>
                            <div><span class=\"font-medium\">Date:</span> ${sentDate}</div>
                            <div><span class=\"font-medium\">Size:</span> ${email.formatted_file_size || (email.file_size || '')}</div>
                            <div><span class=\"font-medium\">Status:</span> ${email.status || ''}</div>
                        </div>
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
                    ${attachments ? `<div class=\"pt-2 border-t\"><div class=\"text-sm font-medium mb-1\">Attachments</div><ul class=\"list-disc pl-4\">${attachments}</ul></div>` : ''}
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
        } else {
            detail.textContent = 'Failed to load email.';
        }
    } catch (e) {
        detail.textContent = 'Failed to load email.';
    }
}


