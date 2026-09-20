# BuildFlow API Documentation

Base URL: `http://127.0.0.1:8000/api/v1`

All protected endpoints require the header:
```
Authorization: Bearer {token}
Content-Type: application/json
```

---

## Authentication

### Register
`POST /register`

Send **either** `full_name` (the app's single Full Name field — split on the first space into first/last name) **or**
`first_name` + `last_name` (old style, still supported).

**Body**
```json
{
    "full_name": "Sarah Jenkins",
    "email": "sarah@example.com",
    "phone": "+1 (555) 867-5309",
    "company_name": "BuildCorp Infrastructure Ltd.",
    "password": "password123",
    "password_confirmation": "password123"
}
```

| Field | Rules |
|---|---|
| `full_name` | required unless `first_name` is sent, max:80 |
| `first_name` / `last_name` | old style: `first_name` required unless `full_name` is sent (then `last_name` is required too), max:40 |
| `email` | required, unique |
| `phone` | optional, max:20, at least 6 digits, unique (compared on digits only, so `+1 (555) 867-5309` and `15558675309` are the same number) |
| `company_name` | optional — creates the user's company (`legal_name`), editable later via `POST /company` |
| `password` | required, min:8, confirmed |

Self-registration always creates an `OWNER`.

**Response `201`**
```json
{
    "message": "Registration successful.",
    "user": {
        "id": 1,
        "first_name": "Sarah",
        "last_name": "Jenkins",
        "full_name": "Sarah Jenkins",
        "email": "sarah@example.com",
        "phone": "+1 (555) 867-5309",
        "role": "OWNER",
        "company": { "id": 1, "legal_name": "BuildCorp Infrastructure Ltd." }
    },
    "token": "1|abc123..."
}
```
`company` is `null` when no `company_name` was sent.

---

### Login
`POST /login`

Log in with an email **or** a phone number in `identifier` (phone is matched on digits only, so any formatting works).
The old `email` field is still accepted instead of `identifier`.

**Body**
```json
{
    "identifier": "sarah@example.com",
    "password": "password123"
}
```
or `{ "identifier": "+1 555 867 5309", "password": "password123" }`

Wrong credentials return `422` with the error under `identifier` (or `email` if the old field was used).

**Response `200`**
```json
{
    "message": "Login successful.",
    "user": {
        "id": 1,
        "first_name": "Sarah",
        "last_name": "Jenkins",
        "full_name": "Sarah Jenkins",
        "email": "sarah@example.com",
        "phone": "+1 (555) 867-5309",
        "role": "OWNER",
        "company": { "id": 1, "legal_name": "BuildCorp Infrastructure Ltd." },
        "status": 1
    },
    "token": "1|abc123..."
}
```

---

### Get Authenticated User
`GET /me` 🔒

Returns the user's profile along with stats for all their active assigned projects.

**Response `200`**
```json
{
    "user": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "full_name": "John Doe",
        "email": "john@example.com",
        "phone": "01712345678",
        "profile_photo": "http://127.0.0.1:8000/storage/profile_photo/photo.jpg",
        "role": "OWNER",
        "status": 1
    },
    "stats": {
        "total_projects": 5,
        "total_budget": 15000000.00,
        "total_supervisors": 8
    }
}
```

---

### Logout
`POST /logout` 🔒

**Response `200`**
```json
{
    "message": "Logged out successfully."
}
```

---

### Update Profile
`POST /profile/update` 🔒

All fields are optional. Send only the fields you want to update.
Use `multipart/form-data` if uploading a profile photo, otherwise `application/json`.

**Body**
| Field | Type | Rules |
|---|---|---|
| `first_name` | string | max:40 |
| `last_name` | string | max:40 |
| `phone` | string | unique |
| `dob` | date | before today |
| `password` | string | min:8, confirmed |
| `password_confirmation` | string | required with password |
| `profile_photo` | file | image, max 2MB |

**Response `200`**
```json
{
    "message": "Profile updated successfully.",
    "user": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "full_name": "John Doe",
        "email": "john@example.com",
        "phone": "01712345678",
        "dob": "01-15-1990",
        "profile_photo": "http://127.0.0.1:8000/storage/profile_photo/photo.jpg",
        "role": "OWNER",
        "status": 1
    }
}
```

---

## Users

### List Users
`GET /users` 🔒

**Query Parameters**
| Param | Type | Description |
|---|---|---|
| `name` | string | Search by full name (partial match) |
| `role` | string | Filter by role: `SUPER-ADMIN`, `OWNER`, `SUPERVISOR`, `WORKER`, `USER` |
| `per_page` | integer | Items per page (default: 10, max: 100) |

**Example**
```
GET /users?name=john&role=WORKER&per_page=15
```

**Response `200`**
```json
{
    "data": [
        {
            "id": 5,
            "full_name": "John Smith",
            "first_name": "John",
            "last_name": "Smith",
            "email": "john@example.com",
            "phone": "01712345678",
            "role": "WORKER",
            "status": 1,
            "status_label": "Active",
            "profile_photo": "http://127.0.0.1:8000/storage/profile_photo/photo.jpg"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 10,
        "total": 25,
        "from": 1,
        "to": 10
    }
}
```

---

## Projects

### List Assigned Projects (Owner)
`GET /projects` 🔒

Returns only active projects assigned to the authenticated user.

**Query Parameters**
| Param | Type | Description |
|---|---|---|
| `search` | string | Search by project name |
| `phase` | string | `planning`, `in_progress`, `delayed`, `finishing`, `completed` (Completed / Delayed tabs) |
| `category` | string | `commercial`, `infrastructure`, `residential` |
| `per_page` | integer | Items per page (default: 10) |

"All Active" tab = no `phase` param. `assigned_date` is when the current user was assigned to the project.

**Response `200`**
```json
{
    "data": [
        {
            "id": 1,
            "project_code": "PRJ-2026-001",
            "name": "Downtown Tower",
            "location": "New York",
            "category": "commercial",
            "total_budget": 5000000,
            "used_budget": 1200000,
            "progress": 45,
            "assigned_date": "2026-07-03",
            "deadline": "2030-12-31",
            "phase": "in_progress",
            "phase_label": "In Progress",
            "status": 1,
            "status_label": "Active"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 10,
        "total": 2,
        "from": 1,
        "to": 2
    }
}
```

---

### View Project Detail
`GET /projects/{id}` 🔒

Returns full detail of a project. The user must be assigned to it.

**Response `200`**
```json
{
    "data": {
        "id": 1,
        "project_code": "PRJ-2026-001",
        "name": "Downtown Tower",
        "description": "A commercial high-rise project.",
        "location": "New York",
        "lat": 40.7128,
        "long": -74.006,
        "category": "commercial",
        "general_contractor": "Apex Builders Group",
        "lead_architect": "Studio Form+Space",
        "progress": 45,
        "budget": { "total": 5000000, "used": 1200000, "remaining": 3800000 },
        "total_budget": 5000000,
        "assigned_date": "2026-07-03",
        "deadline": "2030-12-31",
        "phase": "in_progress",
        "phase_label": "In Progress",
        "status": 1,
        "status_label": "Active",
        "supervisors_count": 2,
        "pending_tasks_count": 14,
        "assigned_users": [
            {
                "id": 3,
                "full_name": "Jane Doe",
                "email": "jane@example.com",
                "role": "SUPERVISOR"
            }
        ],
        "created_at": "2026-07-03 10:00:00",
        "updated_at": "2026-07-03 10:00:00"
    }
}
```

**Response `404`** — project not found or not assigned to user.

---

### Create Project
`POST /projects` 🔒

Use `multipart/form-data` when uploading images/documents. Use `application/json` if no files.

**Body**
| Field | Type | Rules |
|---|---|---|
| `name` | string | required, max:150 |
| `description` | string | optional |
| `location` | string | optional, max:255 |
| `lat` | number | optional, between -90 and 90 |
| `long` | number | optional, between -180 and 180 |
| `total_budget` | number | optional, min:0 |
| `used_budget` | number | optional, min:0 (default 0) |
| `deadline` | date | optional (YYYY-MM-DD) |
| `status` | integer | optional, 1=Active 2=Inactive (default: 1) |
| `phase` | string | optional: `planning` (default), `in_progress`, `delayed`, `finishing`, `completed` |
| `category` | string | optional: `commercial`, `infrastructure`, `residential` |
| `progress` | integer | optional, 0-100 (default 0) |
| `general_contractor` | string | optional, max:255 |
| `lead_architect` | string | optional, max:255 |
| `assigned_users` | array | optional, array of user IDs |
| `assigned_users.*` | integer | must exist in users table |
| `images` | array | optional, array of files |
| `images.*` | file | jpg/jpeg/png/gif/webp/pdf/doc/docx/xls/xlsx, max 10MB each |
| `image_names` | array | optional, custom names for each uploaded file |
| `image_names.*` | string | max:255 (falls back to original filename) |

**Body Example (multipart/form-data)**
```
name              = Riverside Complex
description       = Residential development project.
location          = Dhaka
lat               = 23.8103
long              = 90.4125
total_budget      = 3000000
deadline          = 2030-06-30
status            = 1
assigned_users[]  = 2
assigned_users[]  = 5
images[]          = [file: site-plan.pdf]
images[]          = [file: blueprint.png]
image_names[]     = Site Plan
image_names[]     = Blueprint
```

**Response `201`**
```json
{
    "message": "Project created successfully.",
    "data": {
        "id": 3,
        "name": "Riverside Complex",
        "description": "Residential development project.",
        "location": "Dhaka",
        "lat": 23.8103,
        "long": 90.4125,
        "total_budget": 3000000,
        "deadline": "2030-06-30",
        "status": 1,
        "status_label": "Active",
        "assigned_users": [
            { "id": 2, "full_name": "Alice Brown", "email": "alice@example.com", "role": "OWNER" }
        ],
        "images": [
            { "id": 1, "name": "Site Plan",  "url": "http://127.0.0.1:8000/storage/project_documents/3/site-plan.pdf", "created_at": "2026-07-17 10:00:00" },
            { "id": 2, "name": "Blueprint",  "url": "http://127.0.0.1:8000/storage/project_documents/3/blueprint.png", "created_at": "2026-07-17 10:00:00" }
        ],
        "created_at": "2026-07-17 10:00:00"
    }
}
```

---

### Update Project
`POST /projects/{id}/update` 🔒

All fields are optional — send only what you want to change.
Use `multipart/form-data` when uploading images, otherwise `application/json`.

**Body**
| Field | Type | Description |
|---|---|---|
| `name` | string | max:150 |
| `description` | string | |
| `location` | string | max:255 |
| `lat` | number | between -90 and 90 |
| `long` | number | between -180 and 180 |
| `total_budget` | number | min:0 |
| `used_budget` | number | min:0 |
| `deadline` | date | YYYY-MM-DD |
| `status` | integer | 1=Active, 2=Inactive |
| `phase` | string | `planning`, `in_progress`, `delayed`, `finishing`, `completed` |
| `category` | string | `commercial`, `infrastructure`, `residential` |
| `progress` | integer | 0-100 |
| `general_contractor` | string | max:255 |
| `lead_architect` | string | max:255 |
| `assigned_users` | array | replaces all assigned users |
| `assigned_users.*` | integer | must exist in users table |
| `images` | array | new files to upload |
| `images.*` | file | jpg/jpeg/png/gif/webp/pdf/doc/docx/xls/xlsx, max 10MB |
| `image_names` | array | custom names for new uploads |
| `deleted_images` | array | IDs of existing images to delete |
| `deleted_images.*` | integer | must exist in project_images table |

**Response `200`**
```json
{
    "message": "Project updated successfully.",
    "data": {
        "id": 3,
        "name": "Riverside Complex",
        "description": "Updated description.",
        "location": "Dhaka",
        "lat": 23.8103,
        "long": 90.4125,
        "total_budget": 4000000,
        "deadline": "2031-01-01",
        "status": 1,
        "status_label": "Active",
        "assigned_users": [...],
        "images": [
            { "id": 2, "name": "Blueprint", "url": "http://127.0.0.1:8000/storage/project_documents/3/blueprint.png", "created_at": "..." }
        ],
        "created_at": "2026-07-17 10:00:00",
        "updated_at": "2026-07-17 12:00:00"
    }
}
```

**Response `404`** — project not found.

---

### Delete Project Document
`DELETE /projects/{id}/images/{imageId}` 🔒

Removes the document record and deletes the physical file from storage.

**Response `200`**
```json
{
    "message": "Document deleted successfully."
}
```

**Response `404`** — project or document not found.

---

### Assign Users to Project
`POST /projects/{id}/assign` 🔒

Replaces **all** currently assigned users with the provided list.
Send an empty array to remove all users from the project.

**Body**
```json
{
    "user_ids": [2, 5, 8]
}
```

**Response `200`**
```json
{
    "message": "Users assigned successfully.",
    "project_id": 3,
    "project_name": "Riverside Complex",
    "users_count": 3,
    "assigned_users": [
        { "id": 2, "full_name": "Alice Brown", "email": "alice@example.com", "role": "OWNER" },
        { "id": 5, "full_name": "Bob Smith",   "email": "bob@example.com",   "role": "WORKER" },
        { "id": 8, "full_name": "Carol White", "email": "carol@example.com", "role": "SUPERVISOR" }
    ]
}
```

**Response `404`** — project not found.

---

## Tasks

### List Tasks for a Project
`GET /projects/{project}/tasks` 🔒

The user must be assigned to the project.

**Response `200`**
```json
{
    "data": [
        {
            "id": 1,
            "project_id": 1,
            "title": "Site Foundation Phase - Sector B",
            "is_critical_path": true,
            "deadline": "2026-10-24",
            "status": 1,
            "status_label": "Pending",
            "assigned_to": null
        }
    ],
    "meta": { "current_page": 1, "last_page": 1, "per_page": 10, "total": 1, "from": 1, "to": 1 }
}
```

---

### Create Task
`POST /projects/{project}/tasks` 🔒

**Body**
| Field | Type | Rules |
|---|---|---|
| `title` | string | required, max:150 |
| `description` | string | optional |
| `is_critical_path` | boolean | optional |
| `deadline` | date | optional (YYYY-MM-DD) |

**Response `201`**
```json
{
    "message": "Task created successfully.",
    "data": {
        "id": 1,
        "project": { "id": 1, "name": "Downtown Tower" },
        "title": "Site Foundation Phase - Sector B",
        "description": "Oversee the comprehensive excavation...",
        "is_critical_path": true,
        "deadline": "2026-10-24",
        "status": 1,
        "status_label": "Pending",
        "assigned_to": null,
        "assigned_at": null,
        "created_by": { "id": 2, "full_name": "rajdip Dimj" },
        "created_at": "2026-09-18 12:00:00",
        "updated_at": "2026-09-18 12:00:00"
    }
}
```

---

### View Task Detail
`GET /tasks/{id}` 🔒

**Response `200`** — same shape as the create response's `data`.
**Response `404`** — task not found or you don't have access.

---

### List Available Supervisors for a Task
`GET /tasks/{id}/available-supervisors` 🔒

Returns active users with the `SUPERVISOR` role, ranked by `performance_score` desc, with each one's current workload
(count of their tasks with status `Assigned` or `In Progress`, across all projects).

**Response `200`**
```json
{
    "data": [
        {
            "id": 8,
            "full_name": "Marcus Reed",
            "title": "Senior Structural Lead",
            "profile_photo": "http://127.0.0.1:8000/storage/profile_photo/photo.jpg",
            "experience_years": 14,
            "performance_score": 98,
            "current_workload": 1
        }
    ],
    "meta": { "current_page": 1, "last_page": 1, "per_page": 10, "total": 1, "from": 1, "to": 1 }
}
```

> `title`, `experience_years` and `performance_score` are new columns on `users` — existing supervisor accounts will
> return `null` until filled in (via `admin/users` edit or a future profile endpoint).

---

### Assign Supervisor to Task
`POST /tasks/{id}/assign-supervisor` 🔒

**Body**
```json
{ "supervisor_id": 8 }
```

Sets the task's `status` to `2` (Assigned) and stamps `assigned_at`. Fails with `422` if the given user doesn't have
the `SUPERVISOR` role.

**Response `200`** — same shape as the task detail response.

---

## Materials (Project Detail → Material Allocation)

The user must be assigned to the project. `status` values: `on_track`, `delayed`, `delivered`.

### List Materials
`GET /projects/{project}/materials` 🔒 — optional `per_page`.

**Response `200`**
```json
{
    "data": [
        {
            "id": 1,
            "project_id": 1,
            "name": "Structural Steel",
            "unit": "Tons",
            "estimated_qty": 4500,
            "delivered_qty": 3200,
            "status": "on_track",
            "status_label": "On Track",
            "created_at": "2026-09-22 10:00:00",
            "updated_at": "2026-09-22 10:00:00"
        }
    ],
    "meta": { "current_page": 1, "last_page": 1, "per_page": 10, "total": 1, "from": 1, "to": 1 }
}
```

### Add Material
`POST /projects/{project}/materials` 🔒

| Field | Type | Rules |
|---|---|---|
| `name` | string | required, max:150 |
| `unit` | string | required, max:50 (e.g. Tons, cu yd, sq ft) |
| `estimated_qty` | number | required, min:0 |
| `delivered_qty` | number | optional, min:0 (default 0) |
| `status` | string | optional (default `on_track`) |

**Response `201`** — `{ "message": "Material added successfully.", "data": { ...material } }`

### Update Material
`POST /materials/{id}/update` 🔒 — send only the fields to change (same fields/rules as above).

**Response `200`** — `{ "message": "Material updated successfully.", "data": { ...material } }`
**Response `404`** — material not found or you're not assigned to its project.

> Project create/update/detail now also carry a `site_info` text field (Site Information card).

---

## Milestones (Project Detail → Milestones tab)

The user must be assigned to the project. `status` values: `pending`, `in_progress`, `completed`.
`is_overdue` is computed: true when the due date has passed and the milestone isn't completed.

### List Milestones
`GET /projects/{project}/milestones` 🔒 — optional `status`, `per_page`. Sorted by due date (undated last).

**Response `200`**
```json
{
    "data": [
        {
            "id": 1,
            "project_id": 1,
            "title": "Foundation Complete",
            "description": "All footings poured",
            "due_date": "2026-08-01",
            "status": "pending",
            "status_label": "Pending",
            "is_overdue": true,
            "completed_at": null,
            "created_at": "2026-09-23 10:00:00",
            "updated_at": "2026-09-23 10:00:00"
        }
    ],
    "meta": { "current_page": 1, "last_page": 1, "per_page": 10, "total": 1, "from": 1, "to": 1 }
}
```

### Add Milestone
`POST /projects/{project}/milestones` 🔒

| Field | Type | Rules |
|---|---|---|
| `title` | string | required, max:150 |
| `description` | string | optional |
| `due_date` | date | optional (YYYY-MM-DD) |
| `status` | string | optional (default `pending`) |

**Response `201`** — `{ "message": "Milestone created successfully.", "data": { ...milestone } }`

### Update / Mark Complete
`POST /milestones/{id}/update` 🔒 — send only the fields to change. `{"status": "completed"}` marks it complete and stamps
`completed_at`; moving it back to another status clears `completed_at`.

**Response `200`** — `{ "message": "Milestone updated successfully.", "data": { ...milestone } }`
**Response `404`** — milestone not found or you're not assigned to its project.

---

## Activity (Project Detail → Recent Activity)

### List Project Activity
`GET /projects/{project}/activity` 🔒 — optional `per_page`. Newest first. The user must be assigned to the project.

Entries are written automatically — there is no create endpoint. Logged actions:

| `type` | Written when |
|---|---|
| `task_created` | a task is created |
| `task_assigned` | a supervisor is assigned to a task |
| `milestone_added` / `milestone_completed` | a milestone is added / first marked completed |
| `material_added` / `material_delivered` / `material_delayed` | a material is added / its status changes to delivered / delayed |
| `project_phase_changed` / `project_progress_updated` | the project's `phase` / `progress` actually changes |

**Response `200`**
```json
{
    "data": [
        {
            "id": 8,
            "project_id": 1,
            "type": "task_assigned",
            "description": "Everett Rowe assigned to task \"Pour Level 4 slab\"",
            "actor": { "id": 2, "full_name": "rajdip Dimj" },
            "created_at": "2026-09-24 10:00:00",
            "time_ago": "2 hours ago"
        }
    ],
    "meta": { "current_page": 1, "last_page": 1, "per_page": 10, "total": 1, "from": 1, "to": 1 }
}
```

---

## Owner Profile

Contact info + photo already come from `GET /me` and `POST /profile/update`. The two sections below are the
**Corporate Entity** and **Compliance Documents** cards.

### Get Company
`GET /company` 🔒 — `data` is `null` until the company has been created.

**Response `200`**
```json
{
    "data": {
        "id": 1,
        "legal_name": "BuildCorp Infrastructure Ltd.",
        "tax_id_masked": "**-***4921",
        "registered_address": "400 Capital Blvd, Suite 1200, Metro District, NY 10001",
        "business_type": "LLC - Commercial General Contractor",
        "primary_operations": "Heavy Civil & Infrastructure",
        "created_at": "2026-09-25 10:00:00",
        "updated_at": "2026-09-25 10:00:00"
    }
}
```
The Tax ID is stored encrypted and only ever returned masked.

### Create / Update Company
`POST /company` 🔒 — creates the user's company the first time (`201`), updates it afterwards (`200`, send only the
fields to change).

| Field | Type | Rules |
|---|---|---|
| `legal_name` | string | required on create, max:255 |
| `tax_id` | string | optional, max:50 |
| `registered_address` | string | optional, max:1000 |
| `business_type` | string | optional, max:255 |
| `primary_operations` | string | optional, max:255 |

---

### List Compliance Documents
`GET /compliance-documents` 🔒

Always returns the three required slots — `insurance`, `w9`, `license` — as `status: "missing"` (with `id: null`) until
uploaded, followed by any `other` documents. `status` is `valid`, `expired` (expiry date in the past) or `missing`.
Returns `[]` if the user has no company yet.

**Response `200`**
```json
{
    "data": [
        {
            "id": 1,
            "type": "insurance",
            "title": "Certificate of Liability Insurance",
            "status": "valid",
            "status_label": "Valid",
            "expiry_date": "2027-10-15",
            "file_name": "insurance.pdf",
            "download_url": "http://127.0.0.1:8000/api/v1/compliance-documents/1/download",
            "updated_at": "2026-09-25"
        },
        {
            "id": null,
            "type": "license",
            "title": "Contractor License Verification",
            "status": "missing",
            "status_label": "Missing",
            "expiry_date": null,
            "file_name": null,
            "download_url": null,
            "updated_at": null
        }
    ]
}
```

### Upload Document
`POST /compliance-documents` 🔒 — `multipart/form-data`. Requires the company to exist first (`422` otherwise).

| Field | Type | Rules |
|---|---|---|
| `type` | string | required: `insurance`, `w9`, `license`, `other` |
| `title` | string | required when `type` is `other`; otherwise defaults to the type's name |
| `expiry_date` | date | optional (YYYY-MM-DD) |
| `file` | file | required, pdf/jpg/jpeg/png/doc/docx, max 10MB |

Uploading `insurance`, `w9` or `license` again **replaces** the previous file (and its expiry date); `other` documents accumulate.

**Response `201`** — `{ "message": "Document uploaded successfully.", "data": { ...document } }`

### Download Document
`GET /compliance-documents/{id}/download` 🔒 — streams the file. Files are stored on the private disk, so the bearer
token is required (no public URL). `404` if the document isn't part of your company.

---

## Settings

Logout is `POST /logout` (see Authentication).

### Get Settings
`GET /settings` 🔒

**Response `200`**
```json
{
    "data": {
        "notifications_enabled": true,
        "language": "en",
        "language_label": "English",
        "available_languages": [
            { "code": "en", "label": "English" },
            { "code": "bn", "label": "Bengali" }
        ]
    }
}
```
Use `available_languages` to build the language picker.

### Update Settings
`POST /settings` 🔒 — send only the fields to change.

| Field | Type | Rules |
|---|---|---|
| `notifications_enabled` | boolean | optional (Email and push alerts toggle) |
| `language` | string | optional, one of the `available_languages` codes |

**Response `200`** — `{ "message": "Settings updated successfully.", "data": { ...settings } }`

### Change Password
`POST /settings/password` 🔒

| Field | Type | Rules |
|---|---|---|
| `current_password` | string | required, must match the account's current password |
| `password` | string | required, min:8, confirmed, different from the current password |
| `password_confirmation` | string | required |

The device making the request stays signed in; **every other device's token is revoked**.

**Response `200`** — `{ "message": "Password changed successfully." }`
**Response `422`** — wrong current password / validation errors.

---

## Error Responses

| Status | Meaning |
|---|---|
| `401` | Unauthenticated — missing or invalid token |
| `403` | Forbidden — insufficient permissions |
| `404` | Resource not found |
| `422` | Validation error |
| `500` | Server error |

**Validation error example (`422`)**
```json
{
    "message": "The name field is required.",
    "errors": {
        "name": ["The name field is required."]
    }
}
```
