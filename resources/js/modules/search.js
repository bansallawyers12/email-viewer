import { refreshEmailList } from './emailList';
import axios from 'axios';

export function initializeSearch() {
    const form = document.getElementById('search-form');
    if (!form) return;
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        refreshEmailList();
    });

    // Populate labels
    const labelSelect = document.getElementById('label_id');
    if (labelSelect) {
        axios.get('/api/labels')
            .then(({ data }) => {
                if (data && data.success && Array.isArray(data.labels)) {
                    data.labels.forEach((label) => {
                        const option = document.createElement('option');
                        option.value = label.id;
                        option.textContent = label.name;
                        labelSelect.appendChild(option);
                    });
                }
            })
            .catch(() => {});
    }
}


