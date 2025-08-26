import axios from 'axios';
import { refreshEmailList } from './emailList';

export function initializeUpload() {
    const form = document.getElementById('upload-form');
    const fileInput = document.getElementById('upload-input');
    const progress = document.getElementById('upload-progress');
    if (!form || !fileInput || !progress) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!fileInput.files || fileInput.files.length === 0) return;
        const formData = new FormData();
        formData.append('files[]', fileInput.files[0]);
        
        // Add CSRF token
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        formData.append('_token', token);
        
        console.log('Uploading file:', fileInput.files[0].name, 'Size:', fileInput.files[0].size);
        console.log('Form data entries:');
        for (let [key, value] of formData.entries()) {
            console.log(key, value);
        }
        
        progress.classList.remove('hidden');
        progress.textContent = 'Uploading...';
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
                progress.textContent = 'Upload complete.';
                fileInput.value = '';
                refreshEmailList();
                setTimeout(() => progress.classList.add('hidden'), 1000);
            } else {
                progress.textContent = 'Upload failed.';
            }
        } catch (err) {
            console.error('Upload error:', err);
            progress.textContent = `Upload error: ${err.response?.data?.message || err.message || 'Unknown error'}`;
        }
    });
}


