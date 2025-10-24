# Greenhouse Octagon Job Board

A comprehensive WordPress plugin to import and display job listings from Greenhouse API with advanced search, filtering, department categorization, and integrated application forms.

## Features

- **Automatic Job Import**: Pull job listings from Greenhouse API
- **Database Storage**: Jobs stored in WordPress database for fast loading
- **Configurable Sync**: Schedule automatic imports (hourly, twice daily, or daily)
- **Manual Sync**: Trigger job imports manually from admin panel
- **Advanced Filtering**: Search by keyword, location, and department
- **Department Sidebar**: Left sidebar showing all departments with job counts
- **Responsive Design**: Mobile-friendly layout
- **Single Job Pages**: Detailed job descriptions with application forms
- **Application Forms**: Built-in forms or Greenhouse embedded application forms
- **Email Notifications**: Automatic emails to admin and applicants
- **Custom CSS**: Override styles via settings panel
- **SEO Friendly**: Clean URLs for job pages

## Installation

1. Upload the `greenhouse-octagon` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → Greenhouse Jobs to configure

## Configuration

### Settings Page (Settings → Greenhouse Jobs)

1. **Greenhouse API URL**: Enter your Greenhouse board API URL
   - Default: `https://boards-api.greenhouse.io/v1/boards/octagon/jobs?content=true`
   - Format: `https://boards-api.greenhouse.io/v1/boards/[YOUR-BOARD]/jobs?content=true`

2. **Sync Interval**: Choose how often to automatically sync jobs
   - Hourly
   - Twice Daily
   - Daily (recommended)

3. **Custom CSS**: Add custom styles to override default plugin styles

4. **Manual Sync**: Click "Sync Jobs Now" to immediately pull latest jobs

## Usage

### Display Job Listings

Add the following shortcode to any page or post:

```
[gh_octagon_jobs]
```

This will display:
- Search bar (keyword search)
- Location filter dropdown
- Department sidebar with job counts
- Job listings with pagination

### Single Job Pages

Job detail pages are automatically created with URLs like:
```
https://yoursite.com/job/[JOB-ID]
```

Each single job page includes:
- Full job description
- Job metadata (location, department, office, job ID)
- Application form (Greenhouse embedded or custom fallback)

### Creating a Careers Page

1. Create a new page (e.g., "Careers")
2. Add the shortcode: `[gh_octagon_jobs]`
3. Publish the page

## File Structure

```
greenhouse-octagon/
├── css/
│   └── style.css              # Main stylesheet
├── js/
│   └── script.js              # JavaScript functionality
├── templates/
│   ├── job-listing.php        # Job listing template
│   └── single-job.php         # Single job template
├── greenhouse-octagon.php     # Main plugin file
└── README.md                  # Documentation
```

## Database Structure

The plugin creates a table `wp_gh_octagon_jobs` with the following fields:

- `id` - Auto-increment ID
- `gh_id` - Greenhouse job ID (unique)
- `internal_job_id` - Internal job ID
- `requisition_id` - Requisition ID
- `absolute_url` - Greenhouse job URL
- `title` - Job title
- `location` - Full location string
- `location_city` - Parsed city
- `location_state` - Parsed state
- `location_country` - Parsed country
- `content` - Full job description HTML
- `metadata` - Job metadata JSON
- `departments` - Departments JSON array
- `offices` - Offices JSON array
- `updated_at` - Last update timestamp

## Customization

### Custom Styles

Add custom CSS via Settings → Greenhouse Jobs → Custom CSS, or add to your theme:

```css
/* Example: Change primary color */
.gh-btn-primary {
    background: #your-color !important;
}
```

### Template Overrides

Copy templates from `greenhouse-octagon/templates/` to your theme:

```
your-theme/
└── greenhouse-octagon/
    ├── job-listing.php
    └── single-job.php
```

## Hooks & Filters

### Actions

- `gh_octagon_before_import` - Before job import starts
- `gh_octagon_after_import` - After job import completes
- `gh_octagon_job_imported` - After each job is imported

### Filters

- `gh_octagon_job_content` - Filter job content before display
- `gh_octagon_search_fields` - Modify search query fields
- `gh_octagon_per_page` - Change jobs per page (default: 20)

## Support

For issues or questions, contact: [shojibur.com](https://shojibur.com)

## Changelog

### Version 1.0.0
- Initial release
- Job import from Greenhouse API
- Search and filter functionality
- Department sidebar
- Single job pages
- Application forms
- Email notifications
- Configurable sync schedule
- Custom CSS support

## Credits

Developed by Shajibur Rahman
Website: [https://shojibur.com](https://shojibur.com)

## License

GPLv2 or later
