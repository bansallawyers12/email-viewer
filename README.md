# Email Viewer

A modern web application for viewing and managing email files (.msg format) with advanced search, filtering, and organization capabilities.

## Features

- **Email Upload**: Drag-and-drop or file picker for .msg files
- **Email Parsing**: Automatic extraction of email content, attachments, and metadata
- **Search & Filtering**: Advanced search with multiple criteria and label-based filtering
- **Label Management**: Custom labels for organizing emails
- **Attachment Handling**: View, download, and manage email attachments
- **Responsive Design**: Modern UI built with Tailwind CSS
- **Export Functionality**: Export emails in various formats
- **Storage Management**: Monitor storage usage and manage space

## Technology Stack

- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: Vanilla JavaScript with ES6 modules
- **Styling**: Tailwind CSS
- **Database**: SQLite (default) / MySQL / PostgreSQL
- **Build Tool**: Vite
- **Email Parsing**: Python with extract-msg library

## Project Structure

```
email-viewer/
├── app/
│   ├── Console/Commands/          # Artisan commands
│   ├── Http/Controllers/          # API controllers
│   ├── Models/                    # Eloquent models
│   ├── Services/                  # Business logic services
│   └── Providers/                 # Service providers
├── database/
│   ├── migrations/                # Database migrations
│   ├── seeders/                   # Database seeders
│   └── factories/                 # Model factories
├── resources/
│   ├── js/
│   │   ├── modules/               # JavaScript modules
│   │   │   ├── emailList.js       # Email list management
│   │   │   ├── search.js          # Search and filtering
│   │   │   └── upload.js          # File upload handling
│   │   ├── app.js                 # Main application entry point
│   │   └── bootstrap.js           # Bootstrap configuration
│   ├── css/
│   │   └── app.css                # Main stylesheet
│   └── views/
│       └── app.blade.php          # Main application view
├── routes/
│   └── web.php                    # Web routes
├── storage/
│   ├── app/
│   │   ├── emails/                # Uploaded email files
│   │   ├── attachments/           # Extracted attachments
│   │   └── scripts/               # Python parsing scripts
└── tests/                         # Test files
```

## Installation

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js 18+ and npm
- Python 3.8+ with pip
- Required Python packages: `extract-msg`

### Setup

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd email-viewer
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Python dependencies**
   ```bash
   pip install extract-msg
   ```

4. **Install Node.js dependencies**
   ```bash
   npm install
   ```

5. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

6. **Database setup**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

7. **Build frontend assets**
   ```bash
   npm run dev
   ```

8. **Start the application**
   ```bash
   php artisan serve
   ```

## Usage

### Uploading Emails

1. Select one or more .msg files using the file picker
2. Click "Upload" to process the files
3. Monitor upload progress and view results
4. Check for any errors or duplicate warnings

### Managing Emails

- **View**: Click on any email in the list to view its contents
- **Search**: Use the search bar to find specific emails
- **Filter**: Apply label filters to organize emails
- **Labels**: Add custom labels to categorize emails

### Managing Attachments

- **View**: Click on attachments to preview them
- **Download**: Download individual attachments or all at once
- **Export**: Export emails with attachments in various formats

## API Endpoints

### Email Management
- `GET /api/emails` - List emails with pagination and filtering
- `GET /api/emails/{id}` - Get email details
- `DELETE /api/emails/{id}` - Delete email

### Upload
- `POST /api/upload` - Upload .msg files
- `GET /api/upload/progress/{id}` - Check upload progress
- `GET /api/upload/storage-usage` - Get storage statistics

### Labels
- `GET /api/labels` - List all labels
- `POST /api/labels` - Create new label
- `PUT /api/labels/{id}` - Update label
- `DELETE /api/labels/{id}` - Delete label
- `POST /api/labels/apply` - Apply label to email
- `DELETE /api/labels/remove` - Remove label from email

### Attachments
- `GET /api/attachments/{id}` - Get attachment details
- `GET /api/attachments/{id}/download` - Download attachment
- `GET /api/attachments/{id}/preview` - Preview attachment
- `GET /api/attachments/email/{emailId}` - Get email attachments

## Development

### Frontend Development

The frontend is built with vanilla JavaScript using ES6 modules:

- **Modular Architecture**: Each feature is in its own module
- **Event-Driven**: Uses native DOM events and event listeners
- **Responsive Design**: Built with Tailwind CSS for mobile-first design
- **No Framework Dependencies**: Pure JavaScript for better performance

### Key JavaScript Modules

- **emailList.js**: Handles email list display, pagination, and selection
- **search.js**: Manages search functionality and filtering
- **upload.js**: Handles file uploads with progress and error handling

### Building Assets

```bash
# Development mode with hot reload
npm run dev

# Production build
npm run build
```

### Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=EmailControllerTest
```

## Configuration

### Environment Variables

Key configuration options in `.env`:

```env
APP_NAME="Email Viewer"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

UPLOAD_MAX_FILESIZE=10MB
MAX_STORAGE_SIZE=10GB
```

### Storage Configuration

- **Email Files**: Stored in `storage/app/emails/`
- **Attachments**: Extracted to `storage/app/attachments/`
- **Temporary Files**: Stored in `storage/app/temp/`

## Troubleshooting

### Common Issues

1. **Upload Failures**
   - Check file format (.msg files only)
   - Verify file size limits
   - Check storage space availability

2. **Python Dependencies**
   - Ensure `extract-msg` is installed
   - Check Python version compatibility

3. **Permission Issues**
   - Verify storage directory permissions
   - Check file upload directory access

### Debug Mode

Enable debug mode in `.env`:
```env
APP_DEBUG=true
```

Check Laravel logs in `storage/logs/laravel.log`

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Acknowledgments

- **Laravel** - The PHP framework for web artisans
- **Tailwind CSS** - A utility-first CSS framework
- **extract-msg** - Python library for parsing Outlook .msg files
- **Vite** - Next generation frontend tooling

---

**Built with Laravel & Vanilla JavaScript** - Modern web development stack for building robust applications.
