import './bootstrap';
import { initializeEmailList } from './modules/emailList';
import { initializeSearch } from './modules/search';
import { initializeUpload } from './modules/upload';

document.addEventListener('DOMContentLoaded', () => {
    initializeSearch();
    initializeUpload();
    initializeEmailList();
});
