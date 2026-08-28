# Uploader Plugin for WinterCMS

<p align="center">
  <img src="hero-uploader.png" alt="Uploader Plugin" width="100%">
</p>

A comprehensive file upload management system for WinterCMS that empowers administrators to create backend-managed upload forms with fine-grained access control and frontend components for seamless user file submission.

---

## 📋 Table of Contents

- [Quick Start](#quick-start)
- [Installation](#installation)
- [How It Works](#how-it-works)
- [Backend Setup](#backend-setup)
- [Frontend Integration](#frontend-integration)
  - [Using the Uploader Component](#using-the-uploader-component)
  - [Using Pre-built Blocks](#using-pre-built-blocks)
- [Advanced Usage](#advanced-usage)
- [Helper Functions](#helper-functions)
- [Examples](#examples)
- [Limitations & Notes](#limitations--notes)
- [License](#license)

---

## Quick Start

1. **Install** via Composer:
   ```bash
   composer require mercator/wn-uploader-plugin
   ```

2. **Migrate** the database:
   ```bash
   php artisan winter:up
   ```

3. **Create an Upload Form** in the WinterCMS backend:
   - Go to **Uploader → Forms**
   - Fill in the form details (title, description, allowed file types, etc.)
   - Add authorized users (by email, token, or ID)
   - Save and copy the **Form ID**

4. **Add to Frontend** using a component or block:
   ```twig
   [Uploader]
   formId = "YOUR_FORM_ID"
   
   {% component 'uploader' %}
   ```

---

## Installation

### Prerequisites

- WinterCMS 1.2.8+
- PHP 8.3+

### Steps

```bash
composer require mercator/wn-uploader-plugin
php artisan winter:up
```

The plugin will automatically register and create the necessary database tables.

---

## How It Works

The Uploader Plugin follows a **backend-defined, frontend-referenced** architecture:

```
┌─────────────────────────────────────────────────────────────┐
│ Backend (Administrator)                                     │
├─────────────────────────────────────────────────────────────┤
│ • Create Upload Forms                                       │
│ • Define allowed users (email, token, or ID)                │
│ • Set file constraints (type, size, count)                  │
│ • Configure email notifications                             │
│ • Get Form ID                                               │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ Frontend (Visitors/Users)                                   │
├─────────────────────────────────────────────────────────────┤
│ • Reference Form ID in page/block/component                 │
│ • Optionally provide User ID for access validation          │
│ • Upload files (validated server-side)                      │
│ • Files stored in media folder                              │
└─────────────────────────────────────────────────────────────┘
```

**Key Principle:** All upload logic is defined in the backend. No upload form can function without a matching backend definition.

---

## Backend Setup

### Creating Upload Forms

1. Navigate to **Uploader** → **Forms** in the WinterCMS backend
2. Click **+ New Form**
3. Fill in the following details:

#### Form Configuration

| Field | Type | Description |
|-------|------|-------------|
| **Form ID** | Text | Unique identifier used to reference this form on the frontend. Generated automatically; can be customized. |
| **Title** | Text | Display name for the form (shown in frontend blocks/components). |
| **Description** | Textarea | Optional description displayed to users (e.g., instructions or context). |
| **Allowed File Extensions** | Text | Comma-separated list of extensions (e.g., `jpg,png,pdf,docx`). Leave empty to allow all types. |
| **Max File Size (MB)** | Number | Maximum file size in megabytes per file. Use `0` for unlimited. |
| **Max Files per User** | Number | Maximum number of files a user can upload. Use `0` for unlimited. |
| **Restricted** | Checkbox | If enabled, only users in the "Authorized Users" list can upload. If disabled, anyone can upload (if form ID is public). |

#### Authorized Users

If **Restricted** is enabled, specify which users can access this form:

| Method | Example | Use Case |
|--------|---------|----------|
| **Email** | `user@example.com` | User identified by email address |
| **Token** | `abc123xyz456` | Anonymous access via token (useful for surveys/forms) |
| **User ID** | `42` | WinterCMS backend user ID |

#### Email Notifications (Optional)

- **Notify Email Address**: Send upload notifications to this email
- **Subject Line**: Email subject (variables: `{form_title}`, `{user_name}`)
- **Message Body**: Email body with upload instructions and link

---

## Frontend Integration

### Using the Uploader Component

The `Uploader` component renders an interactive upload form on any CMS page.

#### Component Snippet

Add this to your CMS page or layout:

```ini
[Uploader]
formId = "your_form_id_here"
userId = "optional_user_id_here"
```

#### Component Properties

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `formId` | String | Yes | The **Form ID** from the backend upload form definition. |
| `userId` | String | No | Optional user identifier (email, token, or ID). Used to validate access control. |

#### Page Markup

In your page template, render the component:

```twig
<section class="upload-container">
    <h2>Upload Your Files</h2>
    {% component 'uploader' %}
</section>
```

#### Component Output

The component renders:
- **Form title and description** (from backend)
- **File input field** with drag-and-drop support
- **Upload progress indicator**
- **Success/error messages**
- **Access denial message** if user is not authorized

**Access Denied Message:**
```
Upload form not found or user not permissioned.
```

---

### Using Pre-built Blocks

The plugin includes ready-made **blocks** for rapid frontend development. Blocks are full-featured, styled components you can add to CMS pages.

#### Available Blocks

| Block | Purpose |
|-------|---------|
| `upload.block` | Full-featured upload form with UIKit styling |
| `upload_bootstrap.block` | Full-featured upload form with Bootstrap styling |
| `qrcode.block` | QR code generator linking to upload page (UIKit) |
| `qrcode_bootstrap.block` | QR code generator linking to upload page (Bootstrap) |
| `gallery.block` | Displays a filterable gallery of uploaded files with optional category tabs |
| `slideshow.block` | Fullscreen, auto-refreshing slideshow of uploaded files (ideal for live event displays) |

#### Block Properties

##### Upload Blocks (`upload.block`, `upload_bootstrap.block`)

| Property | Type | Description |
|----------|------|-------------|
| `formId` | String | The **Form ID** from backend (required). |
| `userId` | String | Optional user ID for access validation. |
| `showDescription` | Boolean | Show the form's description text (default: `true`). |
| `allowDragDrop` | Boolean | Enable drag-and-drop file upload (default: `true`). |
| `previewUploads` | Boolean | Show uploaded files in a preview list (default: `true`). |

##### QR Code Blocks (`qrcode.block`, `qrcode_bootstrap.block`)

| Property | Type | Description |
|----------|------|-------------|
| `formId` | String | The **Form ID** from backend (required). |
| `userId` | String | Optional user ID to include in QR link. |
| `uploadPageUrl` | String | Full URL to the upload page (e.g., `/uploads`). **Required for QR code generation.** |
| `qrCodeSize` | Number | Size of the QR code in pixels (default: `200`). |
| `margin` | Number | Margin around QR code in pixels (default: `10`). |

#### QR Code Block Example

```ini
[QRCodeBlock]
formId = "survey_2024"
uploadPageUrl = "https://example.com/file-submission"
qrCodeSize = 300
margin = 15
```

This generates a QR code linking directly to `/file-submission?form=survey_2024`, which users can scan with their phone.

---

#### Gallery Block (`gallery.block`)

Displays a responsive grid/gallery of all files uploaded to a form, with optional filtering by sub-galleries (categories).

##### Properties

| Property | Type | Description |
|----------|------|-------------|
| `formId` | String | The **Form ID** from backend. Can be overridden by URL parameter `?id=FORM_ID`. |
| `restrictCategories` | Array | Limit displayed files to specific sub-galleries. Leave empty or use `["all"]` to show all files. |

##### Gallery Block Example

```ini
[GalleryBlock]
formId = "photo_contest_2024"
restrictCategories = []
```

This displays all uploaded photos from the contest in a responsive gallery grid.

**With Category Filtering:**

```ini
[GalleryBlock]
formId = "photo_contest_2024"
restrictCategories = ["landscape", "portrait"]
```

This displays only photos in the "landscape" and "portrait" categories.

**Dynamic Form via URL:**

Create a gallery page and reference it as:
```
https://example.com/gallery?id=photo_contest_2024
```

The `?id=` parameter will override the backend form selection.

---

#### Slideshow Block (`slideshow.block`)

A fullscreen, auto-refreshing slideshow displaying files from an upload form. Perfect for live event displays, exhibitions, or real-time photo feeds.

##### Features

- **Fullscreen Display** — immersive presentation of uploaded content
- **Auto-refresh** — automatically loads new uploads without page reload
- **Category Filtering** — optionally restrict to specific sub-galleries
- **Live Event Ready** — designed for displaying user-submitted content in real-time

##### Properties

| Property | Type | Description |
|----------|------|-------------|
| `formId` | String | The **Form ID** from backend. Can be overridden by URL parameter `?id=FORM_ID`. |
| `restrictCategories` | Array | Limit displayed files to specific sub-galleries. Leave empty or use `["all"]` to show all files. |

##### Slideshow Block Example

```ini
[SlideshowBlock]
formId = "event_2024_photos"
restrictCategories = []
```

This creates a fullscreen slideshow of all photos submitted to the event.

**With Category Filtering:**

```ini
[SlideshowBlock]
formId = "event_2024_photos"
restrictCategories = ["professional", "amateur"]
```

This displays only photos from the "professional" and "amateur" categories in the slideshow.

##### Setup for Live Events

1. Create an upload form in the backend (e.g., `event_photos`)
2. Create a CMS page (e.g., `/event-display`) with the slideshow block
3. Direct an event display screen to this page
4. Users upload photos via another page with the `upload.block`
5. Photos appear live in the slideshow automatically

**Example Event Page Structure:**

```yaml
title = "Live Event Display"
url = "/event-display"
layout = "bare"
===

[SlideshowBlock]
formId = "event_photos"
restrictCategories = []
```

Use a `bare` or `fullscreen` layout to maximize the display area.

---

## Advanced Usage

### Dynamic Form ID

You can pass the `formId` dynamically from page parameters:

```twig
[Uploader]
formId = "{{ request('form') }}"

{% component 'uploader' %}
```

URL: `https://example.com/upload?form=your_form_id`

### Conditional Rendering

Show the upload form only if a user is authenticated:

```twig
{% if auth.user %}
    [Uploader]
    formId = "authenticated_form"
    userId = "{{ auth.user.id }}"
    
    {% component 'uploader' %}
{% else %}
    <p>Please log in to upload files.</p>
{% endif %}
```

### Custom Styling

Override default styles by adding CSS in your layout:

```css
.uploader-form {
    max-width: 600px;
    margin: 0 auto;
}

.uploader-input {
    border: 2px dashed #007bff;
    padding: 20px;
}

.uploader-success {
    background-color: #d4edda;
    color: #155724;
    padding: 12px;
    border-radius: 4px;
}
```

---

## Helper Functions

Use these helper functions in your Twig templates or PHP code:

### `uploaderForm(form_id)`

Retrieve an upload form object by its Form ID.

```twig
{% set form = uploaderForm('my_form_id') %}
{% if form %}
    <h3>{{ form.title }}</h3>
    <p>{{ form.description }}</p>
{% endif %}
```

**Returns:**
- Form object with properties: `id`, `title`, `description`, `allowed_extensions`, `max_file_size`, `restricted`
- `null` if form not found

---

### `uploaderUserIsPermissioned(form_id, user_id)`

Check if a user has permission to upload to a specific form.

```twig
{% if uploaderUserIsPermissioned('form_123', 'user@example.com') %}
    <p>You are authorized to upload.</p>
{% else %}
    <p>You do not have permission to upload to this form.</p>
{% endif %}
```

**Returns:**
- `true` if user is authorized
- `false` if user is not authorized or form doesn't exist

---

### `uploaderOpen(form_id, user_id = null)`

Validate form access and return authorization status.

```twig
{% set status = uploaderOpen('form_123', 'user@example.com') %}
{% if status == 0 %}
    <p>Upload authorized!</p>
{% else %}
    <p>Upload denied. Error code: {{ status }}</p>
{% endif %}
```

**Returns:**
- `0` = Upload authorized
- `1` = Form not found
- `2` = User not authorized (form is restricted)
- `3` = Missing required user ID

---

### `uploaderQRCode(url, size = 200, margin = 10)`

Generate a QR code for an upload URL.

```twig
{{ uploaderQRCode('https://example.com/upload?form=my_form', 300, 15) }}
```

**Parameters:**
- `url` (String): The full URL to encode in the QR code
- `size` (Integer): QR code size in pixels (default: 200)
- `margin` (Integer): Margin/padding around QR code (default: 10)

**Returns:** HTML string with embedded QR code image

---

## Examples

### Example 1: Simple Upload Form

**Backend Setup:**
- Create upload form with ID: `documents_upload`
- Title: "Document Upload"
- Allowed extensions: `pdf,docx,xlsx`
- Max file size: `10 MB`
- Restricted: No (anyone can upload)

**Frontend Page:**
```yaml
title = "Upload Documents"
url = "/upload-documents"

[Uploader]
formId = "documents_upload"

---
<div class="container">
    <h1>{{ this.page.title }}</h1>
    {% component 'uploader' %}
</div>
```

---

### Example 2: Restricted Uploads with Authentication

**Backend Setup:**
- Create upload form with ID: `staff_files`
- Title: "Staff File Submission"
- Allowed extensions: `jpg,png,pdf`
- Restricted: Yes
- Authorized Users: `staff@company.com`, `manager@company.com`

**Frontend Page:**
```twig
{% if auth.user %}
    [Uploader]
    formId = "staff_files"
    userId = "{{ auth.user.email }}"
    
    {% component 'uploader' %}
{% else %}
    <p><a href="/login">Log in</a> to submit files.</p>
{% endif %}
```

---

### Example 3: QR Code for Event Registration

**Backend Setup:**
- Create upload form with ID: `event_2024_registration`
- Title: "Event 2024 Registration Documents"
- Allowed extensions: `pdf,jpg,png`

**Frontend Page:**
```yaml
title = "Event Registration"
url = "/event-2024"

[QRCodeBlock]
formId = "event_2024_registration"
uploadPageUrl = "https://example.com/event-2024/upload"
qrCodeSize = 300

---
<div class="event-promotion">
    <h2>Scan to Register</h2>
    {% component 'blockComponent' %}
    <p>Scan the QR code with your phone to submit registration documents.</p>
</div>
```

---

### Example 4: Multi-form Upload Page

Display multiple upload forms on one page:

```twig
<section class="uploads">
    <div class="form-group">
        <h3>ID Document</h3>
        [Uploader]
        formId = "id_document"
        userId = "{{ auth.user.id }}"
        
        {% component 'uploader' %}
    </div>
    
    <div class="form-group">
        <h3>Proof of Address</h3>
        [Uploader]
        formId = "proof_of_address"
        userId = "{{ auth.user.id }}"
        
        {% component 'uploader' %}
    </div>
</section>
```

---

### Example 5: Live Photo Event Display

Set up a dual-page system where users submit photos and a display screen shows them live:

**Page 1: Photo Submission Page**
```yaml
title = "Submit Your Photos"
url = "/event-submit"

[Uploader]
formId = "event_photos_2024"

---
<div class="container">
    <h1>{{ this.page.title }}</h1>
    <p>Submit your best photos from the event!</p>
    {% component 'uploader' %}
</div>
```

**Page 2: Live Display Screen**
```yaml
title = "Live Event Display"
url = "/event-display"
layout = "blank"

[SlideshowBlock]
formId = "event_photos_2024"
restrictCategories = []
```

**Workflow:**
1. Visitors go to `/event-submit` and upload photos
2. A display screen at `/event-display` shows the photos live in fullscreen
3. The slideshow automatically refreshes, showing new submissions

---

### Example 6: Photo Contest with Gallery

Set up a contest where submissions are organized by category:

**Page 1: Submission Form**
```yaml
title = "Photo Contest - Submit Entry"
url = "/contest-submit"

[Uploader]
formId = "photo_contest"

---
<h1>Submit Your Photos</h1>
<p>Choose your category and upload up to 3 photos.</p>
{% component 'uploader' %}
```

**Page 2: Contest Gallery (All Photos)**
```yaml
title = "Photo Contest - Gallery"
url = "/contest-gallery"

[GalleryBlock]
formId = "photo_contest"
restrictCategories = []
```

**Page 3: Category-Filtered Gallery**
```yaml
title = "Landscape Photos"
url = "/contest-gallery/landscape"

[GalleryBlock]
formId = "photo_contest"
restrictCategories = ["landscape"]
```

**Page 4: Another Category Filter**
```yaml
title = "Portrait Photos"
url = "/contest-gallery/portrait"

[GalleryBlock]
formId = "photo_contest"
restrictCategories = ["portrait"]
```

Visitors can browse submissions by category or view all photos.

---

## Limitations & Notes

### File Management
- Files are stored in the WinterCMS **media folder** using the `System\Models\File` model
- Files are **NOT** automatically deleted when an upload form is deleted
- Manually manage file cleanup through the WinterCMS backend or programmatically

### HEIF/HEIC Image Support
- HEIF/HEIC images can be uploaded if their extensions are whitelisted in the form (e.g., `heif,heic`)
- **Limitation:** Resizing and editing are disabled for HEIF/HEIC formats
- **Browser Compatibility:** Most browsers don't natively support HEIF/HEIC, so a large conversion library is lazy-loaded when needed
- This may impact bandwidth usage — use caution when allowing HEIF uploads on bandwidth-limited connections

### Supported Frameworks
- Works with both **UIKit** and **Bootstrap** frontends
- Pre-built blocks for both frameworks are included
- Custom styling via CSS override is possible

### Compatibility
- **WinterCMS:** 1.2.8+
- **PHP:** 8.3+

---

## License

MIT License. See [LICENSE](LICENSE) for details.

---

## Author

**Helmut Kaufmann**  
Küssnacht am Rigi, Switzerland  
[mercator.li](https://mercator.li)  
software@mercator.li
