# Uploader Plugin for WinterCMS

The **Uploader Plugin** extends WinterCMS with a backend-managed upload system and frontend components for user file submission.  
Editors create so-called "Upload Forms" in the backend and optionally assign which users are allowed to upload to each form.  
Frontend pages or blocks reference these backend-defined forms using their **Form ID**, and optionally include a **User ID** for controlled access.

---

## ⚙️ Overview

This plugin provides:
- **Backend-defined upload forms** (with user access lists).
- A **frontend upload component** bound to a backend form.
- **QR code generators** linking directly to upload pages.
- **Email notifications** after successful uploads.
- Themed `.blocks` for **UIKit** and **Bootstrap** frontends.

All upload logic depends on backend definitions — no form can function until it has been created there.

---

## 📦 Installation

Clone the plugin into your WinterCMS installation:

```bash
cd winter/plugins
git clone https://github.com/helmutkaufmann/wn-uploader-plugin.git mercator/uploader
````

Then apply database migrations:

```bash
php artisan winter:up
```

---

## 🧠 Concept

| Layer        | Description                                                                                                                                                                                   |
| ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Backend**  | Administrators create upload forms under **Uploader → Forms**, define allowed users (email, token, or ID), and optionally configure notification emails. Each form gets a unique **Form ID**. |
| **Frontend** | CMS components and blocks reference the **Form ID** to render upload interfaces or QR codes. The frontend can optionally specify a **User ID** to enforce access control.                     |

---

## 🧩 CMS Component: `Uploader`

### Purpose

Renders a frontend upload form connected to a backend-defined upload form.
Files are stored in the media folder. Note that files are **NOT** automatically deleted when the Upload Form is deleted.

### Usage

In your CMS page or layout:

```ini
[Uploader]
formId = "project2025"
userId = "john.doe"
```

Then in the page markup:

```twig
{% component 'uploader' %}
```

### Parameters

| Property         | Type    | Description                                                                 |
| ---------------- | ------- | --------------------------------------------------------------------------- |
| `formId`         | string  | The **Form ID** of the backend-defined upload form. Required.               |
| `userId`         | string  | Optional ID or email matching one of the users defined in the backend form. |


### Example Twig Rendering

```twig
<div class="upload-section">
    {% component 'uploader' %}
</div>

If a form is missing or access is denied, the component displays:

> “Upload form not found or user not permissioned.”

---

## 🧱 Pre-defined Blocks

Located in `/blocks/`, these provide ready-made upload and QR-code functionality.
Each block references the backend **Form ID** and automatically enforces form permissions.

| Block                    | Purpose                                 | Style     |
| ------------------------ | --------------------------------------- | --------- |
| `upload.block`           | Frontend uploader (UIKit)               | UIKit     |
| `upload_bootstrap.block` | Frontend uploader (Bootstrap)           | Bootstrap |
| `qrcode.block`           | QR code link to upload page (UIKit)     | UIKit     |
| `qrcode_bootstrap.block` | QR code link to upload page (Bootstrap) | Bootstrap |

---

## 🧮 Backend Usage

### 1. Upload Forms

Go to **Uploader → Forms** in the backend.

Each **Upload Form** defines:

* A unique **Form ID**
* **Title** and **Description** shown in frontend blocks and components
* **Allowed users**, identified by name, email, or ID
* **Upload constraints** (file size, extensions, ...)
* **Notification email settings**

Once created, the form can be referenced in frontend on CMS pages or using WinterCMS Blocks, both described above. 

### 2. Access Control

User access can be defined **per upload form**. Each form specifies which users are authorized to upload.

Permission can be validated via:

```twig
uploaderUserIsPermissioned(form_id, user_id)
```

If the form does not exist or access is restricted and the user in not on the allowed list, the upload form is not displayed.

---

### 3. Email Notifications

Each upload form can optionally send email notifications when files are uploaded:

Notifications are sent to the **authorized user** defined in the form. The email will provide a link where the user 
will be able to upload his or her files.
---

## 🔐 Access Summary

| Scenario                                                 | Allowed          |
| -------------------------------------------------------- | ---------------- |
| Valid `formId` and permitted `userId`                    | ✅ Upload allowed |
| Valid `formId`, no `userId`, and `restricted  = true`    | ✅ Upload allowed |
| Valid `formId`, invalid `userId`                         | ❌ Upload denied  |
| Missing or invalid `formId`                              | ❌ Upload denied  |

All checks are enforced server-side.

---

## 🧰 Developer Notes

* Compatible with the dev instance of **WinterCMS 1.2.8** and **PHP 8.3**
* Uses `System\Models\File` for file storage
* Works with both **UIKit** and **Bootstrap**
* Helper functions:
  * `uploaderForm(form_id)`
  * `uploaderUserIsPermissioned(form_id, user_id)`
  * `uploaderQRCode(url, size, margin)`

---

## 📄 License

MIT License.
See [LICENSE](LICENSE) for details.

---

## 👤 Author

**Helmut Kaufmann**, Küssnacht am Rigi, Switzerland, software@mercator.li
[mercator.li](https://mercator.li)