# Email Viewer - Laravel Web Application

A Laravel web application that acts as an email viewer for Outlook .msg files, providing a simplified Outlook-like experience in the browser.

## Features

### Core Functionality
- **File Upload**: Drag-and-drop or file picker interface for uploading .msg files
- **Local Storage**: Uploaded files stored in `storage/app/emails` with metadata in database
- **Email Parsing**: Extract content, attachments, and metadata from .msg files using Python-based parsing
- **Authentication**: Basic access control for secure file management
- **Automatic Labeling**: Automatically assigns "Inbox" or "Sent" labels based on sender domain

### Three-Panel UI
- **Left Panel**: Upload area, search bar, filter/sort options, and storage usage indicator
- **Middle Panel**: List of uploaded emails with subject, sender, date, and tags
- **Right Panel**: Detailed email view with:
  - Email content (HTML/text)
  - Attachment list with download/preview options
  - PDF export functionality
  - Tabs for raw data and PDF preview

### Advanced Features
- **Search & Filter**: Find emails by subject, sender, date, or content
- **Sorting**: Sort emails by date, sender, subject, or size
- **Attachment Management**: Download individual attachments or all at once
- **Export Options**: Export emails as PDF with formatting preserved

## Technical Stack

### Backend
- **Framework**: Laravel 10+
- **Database**: MySQL/PostgreSQL with migrations for email metadata
- **File Parsing**: Python-based parsing with extract-msg library
- **Storage**: Local file system with organized directory structure

### Frontend
- **UI Framework**: Vue.js 3 with Composition API
- **Styling**: Tailwind CSS for modern, responsive design
- **Components**: Modular Vue components for each panel
- **State Management**: Pinia for reactive state management

### Database Schema
- **emails**: Store email metadata (subject, sender, date, file path, etc.)
- **attachments**: Store attachment information (filename, size, type, etc.)
- **users**: Basic authentication system

## File Structure
```
app/
├── Http/Controllers/
│   ├── EmailController.php      # Email CRUD operations
│   ├── AttachmentController.php # Attachment handling
│   └── UploadController.php     # File upload logic
├── Models/
│   ├── Email.php               # Email model with relationships
│   ├── Attachment.php          # Attachment model
│   └── User.php                # User model
├── Services/
│   └── MsgParserService.php    # .msg file parsing logic
├── Console/Commands/
│   └── ReprocessEmails.php     # Command to reprocess stuck emails
└── Providers/
    └── AppServiceProvider.php  # Service bindings

resources/
├── js/
│   ├── components/
│   │   ├── LeftPanel.vue       # Upload and search panel
│   │   ├── MiddlePanel.vue     # Email list panel
│   │   └── RightPanel.vue      # Email detail panel
│   ├── stores/
│   │   └── emailStore.js       # Pinia store for state management
│   ├── App.vue                 # Main Vue application
│   └── app.js                  # Vue app initialization
└── views/
    └── app.blade.php           # Main application layout

storage/
├── app/
│   ├── emails/                 # Uploaded .msg files
│   ├── attachments/            # Extracted attachments
│   └── scripts/
│       └── parse_msg.py        # Python script for .msg parsing
```

## Installation & Setup

### Prerequisites
- PHP 8.1+
- Composer
- Node.js 16+
- MySQL/PostgreSQL
- Python 3.8+ (required for .msg file parsing)

### Step 1: Clone and Install Dependencies
```bash
git clone <repository-url>
cd email-viewer
composer install
npm install
```

### Step 2: Environment Configuration
```bash
cp .env.example .env
php artisan key:generate
```

Update your `.env` file with database credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=email_viewer
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### Step 3: Database Setup
```bash
php artisan migrate
php artisan db:seed
```

### Step 4: Storage Setup
```bash
php artisan storage:link
mkdir -p storage/app/emails
mkdir -p storage/app/attachments
mkdir -p storage/app/scripts
chmod -R 755 storage/app
```

### Step 5: Install Python Dependencies (Required)
For .msg file parsing capabilities:
```bash
# Windows
py -m pip install extract-msg

# Linux/Mac
pip3 install extract-msg
```

### Step 6: Development Server
```bash
# Terminal 1: Laravel development server
php artisan serve

# Terminal 2: Frontend asset compilation
npm run dev
```

Visit `http://localhost:8000` to access the application.

### Step 7: Run Tests
```bash
# Run all tests
php artisan test

# Run specific test suites
php artisan test --filter=EmailControllerTest
php artisan test --filter=UploadControllerTest
php artisan test --filter=VueComponentsTest

# Run tests with coverage (requires Xdebug)
php artisan test --coverage
```

## API Endpoints

### Email Management
- `GET /api/emails` - List emails with search/filter
- `GET /api/emails/{id}` - Get email details
- `PUT /api/emails/{id}` - Update email tags
- `DELETE /api/emails/{id}` - Delete email
- `GET /api/emails/{id}/statistics` - Get email statistics
- `GET /api/emails/{id}/export-pdf` - Export email as PDF

### Attachment Management
- `GET /api/attachments/email/{emailId}` - List email attachments
- `GET /api/attachments/{id}` - Get attachment details
- `GET /api/attachments/{id}/download` - Download attachment
- `GET /api/attachments/{id}/preview` - Preview attachment
- `GET /api/attachments/email/{emailId}/download-all` - Download all attachments as ZIP
- `GET /api/attachments/email/{emailId}/statistics` - Get attachment statistics

### Upload Management
- `POST /api/upload` - Upload .msg files
- `GET /api/upload/progress/{emailId}` - Get upload progress
- `GET /api/upload/storage-usage` - Get storage usage
- `DELETE /api/upload/{emailId}` - Delete uploaded email

### Email Management (Enhanced)
- `GET /api/emails` - List emails with advanced search/filter
- `GET /api/emails/{id}` - Get email details
- `PUT /api/emails/{id}` - Update email metadata
- `DELETE /api/emails/{id}` - Delete email
- `GET /api/emails/statistics` - Get email statistics
- `DELETE /api/emails/clear-all` - Clear all emails for user

## Automatic Email Labeling

The system automatically assigns "Inbox" or "Sent" labels to emails based on the sender domain:

- **Inbox**: Emails received from external domains
- **Sent**: Emails sent from your domain (bansalimmigration.com.au)

### How It Works

1. **During Processing**: New emails are automatically labeled when uploaded
2. **Manual Command**: Run `php artisan emails:auto-label` to label existing emails
3. **System Labels**: "Inbox" and "Sent" are system labels that cannot be deleted

### Commands

```bash
# Label all emails for all users
php artisan emails:auto-label

# Label emails for a specific user
php artisan emails:auto-label --user-id=1

# Reprocess emails (includes labeling)
php artisan emails:reprocess
```

### Label Management

- **System Labels**: Inbox, Sent (automatically managed)
- **Custom Labels**: Create your own labels for organization
- **Multiple Labels**: Apply multiple labels to a single email
- **Label Manager**: Full CRUD operations for custom labels

## .msg File Parsing

### Python-Based Parsing (Primary Method)
- **Library**: extract-msg for Microsoft Outlook MSG files
- **Script**: `storage/app/scripts/parse_msg.py` - Custom Python script for parsing
- **Integration**: PHP service calls Python script via command line
- **Features**: Extracts subject, sender, recipients, date, content, and attachments

### Parsing Strategy
1. **File Validation**: Check file extension and basic format
2. **Python Processing**: Use extract-msg library to parse .msg files
3. **Metadata Extraction**: Subject, sender, recipients, date, size
4. **Content Parsing**: HTML/text body extraction
5. **Attachment Processing**: Extract and store individual attachments with base64 encoding
6. **Error Handling**: Graceful degradation for unsupported formats

### PHP Integration
- **MsgParserService**: Orchestrates Python script execution
- **Command Execution**: Secure command-line interface to Python
- **Data Processing**: Handles JSON output from Python script
- **File Management**: Saves attachments and updates database

## Frontend Components

### LeftPanel.vue
- File upload interface with drag-and-drop
- Search and filter controls
- Storage usage display
- Upload progress indicators

### MiddlePanel.vue
- Email list with pagination
- Sort and filter options
- Email preview cards
- Bulk operations

### RightPanel.vue
- Email content display (HTML/text)
- Attachment list with download/preview
- Email metadata display
- Export functionality

## Security Considerations

- **File Upload Validation**: Strict file type checking and size limits
- **Path Traversal Prevention**: Secure file path handling
- **Authentication**: User-based access control
- **File Permissions**: Proper storage directory permissions
- **Command Injection Prevention**: Secure Python script execution

## Performance Optimizations

- **Lazy Loading**: Load email content only when needed
- **Caching**: Cache parsed email metadata
- **Pagination**: Efficient email list pagination
- **Background Processing**: Queue large file uploads
- **CDN Integration**: Optional CDN for static assets

## Development Status

### ✅ Completed
- [x] Database migrations and models
- [x] Email and Attachment models with relationships
- [x] MsgParserService with Python-based parsing
- [x] UploadController with file handling
- [x] EmailController with CRUD operations
- [x] AttachmentController with download/preview
- [x] API routes and authentication
- [x] Vue.js application structure
- [x] Main App.vue component with three-panel layout
- [x] Package.json with Vue.js dependencies
- [x] **LeftPanel.vue component with upload, search, and storage features**
- [x] **MiddlePanel.vue component with email list and pagination**
- [x] **RightPanel.vue component with email content and attachment management**
- [x] **Pinia store (emailStore.js) for state management**
- [x] **Vue.js app initialization with Pinia integration**
- [x] **Python-based .msg file parsing with extract-msg library**
- [x] **ReprocessEmails command for fixing stuck emails**

### ✅ Completed
- [x] Frontend styling and responsive design improvements
- [x] Error handling and validation enhancements
- [x] Performance optimizations
- [x] Testing implementation
- [x] **Email parsing functionality with Python integration**

### 📋 Planned
- [ ] Search and filtering functionality refinement
- [ ] Attachment management interface improvements
- [ ] PDF export implementation
- [ ] Advanced error handling and validation
- [ ] Unit and integration testing
- [ ] Documentation improvements

## Recent Updates

### Email Parsing Solution (Latest Update)
- **Python Integration**: Implemented Python-based .msg file parsing using extract-msg library
- **Robust Parsing**: Successfully parses complex .msg files with attachments and metadata
- **Fallback System**: PHP service with Python script integration for reliable parsing
- **Attachment Handling**: Proper extraction and storage of email attachments
- **Error Recovery**: ReprocessEmails command to fix stuck emails

### Frontend Components Completed
- **LeftPanel.vue**: Complete upload interface with drag-and-drop, search filters, storage usage display, and quick actions
- **MiddlePanel.vue**: Full email list with pagination, sorting, filtering, and email actions (export, download attachments, delete)
- **RightPanel.vue**: Comprehensive email viewer with content tabs, attachment management, metadata display, and preview functionality
- **emailStore.js**: Complete Pinia store with state management, filtering, sorting, and API integration

### Key Features Implemented
- **File Upload**: Drag-and-drop interface with progress tracking
- **Email Management**: List, view, delete, and export emails
- **Attachment Handling**: Download individual or all attachments, preview text files
- **Search & Filter**: Real-time search with date and sort options
- **Storage Monitoring**: Live storage usage tracking
- **Responsive Design**: Modern UI with Tailwind CSS

### Recent Improvements

#### Frontend Styling and Responsive Design Improvements
- **Mobile-First Design**: Added responsive breakpoints and mobile navigation toggle
- **Enhanced UI Components**: Improved drag-and-drop interface with visual feedback
- **Better Transitions**: Added smooth animations and hover effects
- **Accessibility**: Enhanced keyboard navigation and screen reader support
- **Error Notifications**: Added toast notifications for success and error states
- **Loading States**: Improved loading indicators and progress bars

#### Error Handling and Validation Enhancements
- **Comprehensive Validation**: Enhanced file upload validation with detailed error messages
- **User-Friendly Errors**: Mapped technical errors to user-friendly messages
- **Security Improvements**: Added filename sanitization and path traversal prevention
- **Storage Limits**: Implemented storage space checking before uploads
- **Graceful Degradation**: Better error handling for network issues and file processing failures

#### Performance Optimizations
- **Caching System**: Implemented Redis-based caching for email lists and statistics
- **Lazy Loading**: Added lazy loading for email content and attachments
- **Database Optimization**: Improved query performance with proper indexing
- **Background Processing**: Queue system for large file uploads
- **Memory Management**: Optimized memory usage for large email processing

#### Testing Implementation
- **Integration Tests**: Comprehensive API endpoint testing (`EmailControllerTest.php`, `UploadControllerTest.php`)
- **Unit Tests**: Vue.js component testing and configuration validation (`VueComponentsTest.php`)
- **Test Coverage**: Tests for authentication, validation, error handling, and edge cases
- **Mock Testing**: Proper mocking for file uploads and external dependencies
- **Performance Testing**: Tests for caching, pagination, and large dataset handling

## Troubleshooting

### Common Issues

**File Upload Fails**
- Check file permissions on `storage/app/emails`
- Verify file size limits in `.env` and `php.ini`
- Ensure proper MIME type validation

**Email Parsing Errors**
- Ensure Python is installed and accessible via `py` (Windows) or `python3` (Linux/Mac)
- Install extract-msg library: `py -m pip install extract-msg`
- Check Python script permissions in `storage/app/scripts/parse_msg.py`
- Review error logs in `storage/logs/laravel.log`

**Emails Stuck in Processing Status**
- Run `php artisan emails:reprocess` to reprocess stuck emails
- Check if Python script is working: `py storage/app/scripts/parse_msg.py <file_path>`
- Verify file paths and permissions

**Database Connection Issues**
- Verify database credentials in `.env`
- Run `php artisan migrate:status` to check migrations
- Ensure database server is running

**Frontend Build Issues**
- Run `npm install` to install dependencies
- Clear cache with `npm run build`
- Check Node.js version compatibility

**Vue.js Component Issues**
- Ensure all components are properly imported in App.vue
- Check browser console for JavaScript errors
- Verify Pinia store is properly initialized

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For support and questions:
- Create an issue in the repository
- Check the troubleshooting section above
- Review the Laravel and Vue.js documentation

---

**Built with Laravel & Vue.js** - Modern web development stack for building robust applications.
