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

**Body**
```json
{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Response `201`**
```json
{
    "message": "Registration successful.",
    "user": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "full_name": "John Doe",
        "email": "john@example.com",
        "role": "OWNER"
    },
    "token": "1|abc123..."
}
```

---

### Login
`POST /login`

**Body**
```json
{
    "email": "john@example.com",
    "password": "password123"
}
```

**Response `200`**
```json
{
    "message": "Login successful.",
    "user": {
        "id": 1,
        "full_name": "John Doe",
        "email": "john@example.com",
        "role": "OWNER",
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
| `per_page` | integer | Items per page (default: 10) |

**Response `200`**
```json
{
    "data": [
        {
            "id": 1,
            "name": "Downtown Tower",
            "location": "New York",
            "total_budget": 5000000,
            "deadline": "2030-12-31",
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
        "name": "Downtown Tower",
        "description": "A commercial high-rise project.",
        "location": "New York",
        "lat": 40.7128,
        "long": -74.006,
        "total_budget": 5000000,
        "deadline": "2030-12-31",
        "status": 1,
        "status_label": "Active",
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
| `deadline` | date | optional (YYYY-MM-DD) |
| `status` | integer | optional, 1=Active 2=Inactive (default: 1) |
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
| `deadline` | date | YYYY-MM-DD |
| `status` | integer | 1=Active, 2=Inactive |
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
