# Greenhouse Octagon Job Board

A comprehensive WordPress plugin to import and display job listings from Greenhouse API with advanced search, filtering, department categorization, and integrated application forms.

## Features

- **Multi-Board Support**: Import jobs from multiple Greenhouse job boards
- **Automatic Job Import**: Pull job listings from Greenhouse API
- **Database Storage**: Jobs stored in WordPress database for fast loading
- **Configurable Sync**: Schedule automatic imports (hourly, twice daily, or daily)
- **Manual Sync**: Trigger job imports manually from admin panel
- **Advanced Filtering**: Search by keyword, location, department, employment type, and board
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

#### 1. Job Boards Configuration

Add multiple Greenhouse job boards to import jobs from different companies or departments.

**To Add a Board:**
- **Board Name**: Enter a unique identifier (e.g., "octagon", "sales-team", "engineering")
- **Board API URL**: Enter the full Greenhouse API URL
  - Format: `https://boards-api.greenhouse.io/v1/boards/[YOUR-BOARD]/jobs?content=true`
  - Example: `https://boards-api.greenhouse.io/v1/boards/octagon/jobs?content=true`
- Click "Add Board"

**To Remove a Board:**
- Click "Remove" next to the board name
- All jobs associated with that board will be deleted

#### 2. Sync Interval

Choose how often to automatically sync jobs from all configured boards:
- Hourly
- Twice Daily
- Daily (recommended)

#### 3. Custom CSS

Add custom styles to override default plugin styles

#### 4. Manual Sync

Click "Sync All Boards Now" to immediately pull latest jobs from all configured boards

## Usage

### Display Job Listings

#### Basic Usage

Add the following shortcode to any page or post to display all jobs from all boards:

```
[gh_octagon_jobs]
```

This will display:
- Search bar (keyword search)
- Board filter dropdown (when multiple boards are configured)
- Country, location, and employment type filters
- Department sidebar with job counts
- Job listings with pagination

#### Filter by Specific Board

To display jobs from only one specific board:

```
[gh_octagon_jobs board="octagon"]
```

Replace `octagon` with your board name.

**Examples:**

```
[gh_octagon_jobs board="engineering"]
```

```
[gh_octagon_jobs board="sales-team"]
```

#### URL Parameters

Users can filter jobs using URL parameters:

- `?gh_search=developer` - Search by keyword
- `?gh_board=octagon` - Filter by board
- `?gh_country=United States` - Filter by country
- `?gh_location=New York` - Filter by city
- `?gh_employment_type=Full-time` - Filter by employment type
- `?gh_department=Engineering` - Filter by department

**Combined filters example:**
```
https://yoursite.com/careers/?gh_board=octagon&gh_location=London&gh_employment_type=Full-time
```

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

**Multiple Boards Example:**

If you have multiple departments with separate boards, you can create dedicated pages:

- **Careers Page** (all jobs): `[gh_octagon_jobs]`
- **Engineering Careers**: `[gh_octagon_jobs board="engineering"]`
- **Sales Careers**: `[gh_octagon_jobs board="sales-team"]`

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
- `board_name` - Board identifier (e.g., "octagon", "sales-team")
- `gh_id` - Greenhouse job ID
- `internal_job_id` - Internal job ID
- `requisition_id` - Requisition ID
- `absolute_url` - Greenhouse job URL
- `title` - Job title
- `location` - Full location string
- `location_city` - Parsed city
- `location_state` - Parsed state
- `location_country` - Parsed country
- `employment_type` - Employment type (Full-time, Part-time, etc.)
- `content` - Full job description HTML
- `metadata` - Job metadata JSON
- `departments` - Departments JSON array
- `offices` - Offices JSON array
- `updated_at` - Last update timestamp

**Note:** The unique key is `(board_name, gh_id)` to allow the same job ID across different boards.

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

### Version 1.1.0
- **Multi-board support**: Import jobs from multiple Greenhouse boards
- Added board filter to job listings
- Board management UI in settings
- Database schema updated with `board_name` column
- Shortcode now accepts `board` parameter
- Display board badges when multiple boards are configured
- Backward compatible with single board installations

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
