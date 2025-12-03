# DNF and Pause API Documentation

This document describes the new API endpoints for marking books as DNF (Did Not Finish) and pausing/resuming books.

## Base URL
```
/wp-json/gread/v1
```

## Authentication
All endpoints require user authentication. Include WordPress authentication cookies or use appropriate authentication headers.

---

## Endpoints

### 1. Mark Book as DNF (Did Not Finish)

Mark a book as DNF with a reason. This helps us understand why users stopped reading and improve recommendations.

**Endpoint:** `POST /library/dnf`

**Request Body:**
```json
{
  "book_id": 123,
  "reason": "The pacing was too slow for my taste"
}
```

**Parameters:**
- `book_id` (integer, required): The ID of the book to mark as DNF
- `reason` (string, required): The reason why the user did not finish the book

**Success Response (200):**
```json
{
  "success": true,
  "message": "Book marked as DNF (Did Not Finish).",
  "pages_read": 142
}
```

**Error Responses:**

- **400 Bad Request** - Book not in library or invalid parameters
```json
{
  "code": "dnf_failed",
  "message": "This book is not in your library.",
  "data": {
    "status": 400
  }
}
```

- **401 Unauthorized** - User not authenticated
```json
{
  "code": "rest_forbidden",
  "message": "Sorry, you are not allowed to do that.",
  "data": {
    "status": 401
  }
}
```

**Example cURL:**
```bash
curl -X POST "https://yoursite.com/wp-json/gread/v1/library/dnf" \
  -H "Content-Type: application/json" \
  -H "Cookie: wordpress_logged_in_xxx=..." \
  -d '{
    "book_id": 123,
    "reason": "The pacing was too slow"
  }'
```

---

### 2. Pause Book

Pause a book that you're currently reading. Paused books won't clutter your active reading list but will retain your progress.

**Endpoint:** `POST /library/pause`

**Request Body:**
```json
{
  "book_id": 123
}
```

**Parameters:**
- `book_id` (integer, required): The ID of the book to pause

**Success Response (200):**
```json
{
  "success": true,
  "message": "Book paused successfully.",
  "status": "paused"
}
```

**Error Responses:**

- **400 Bad Request** - Book not in library, already paused, or completed
```json
{
  "code": "pause_failed",
  "message": "This book is already paused.",
  "data": {
    "status": 400
  }
}
```

**Example cURL:**
```bash
curl -X POST "https://yoursite.com/wp-json/gread/v1/library/pause" \
  -H "Content-Type: application/json" \
  -H "Cookie: wordpress_logged_in_xxx=..." \
  -d '{
    "book_id": 123
  }'
```

**Notes:**
- You cannot pause a completed book
- Pausing a book retains your current page progress

---

### 3. Resume Book

Resume a previously paused book.

**Endpoint:** `POST /library/resume`

**Request Body:**
```json
{
  "book_id": 123
}
```

**Parameters:**
- `book_id` (integer, required): The ID of the book to resume

**Success Response (200):**
```json
{
  "success": true,
  "message": "Book resumed successfully.",
  "status": "reading"
}
```

**Error Responses:**

- **400 Bad Request** - Book not in library or not paused
```json
{
  "code": "resume_failed",
  "message": "This book is not paused.",
  "data": {
    "status": 400
  }
}
```

**Example cURL:**
```bash
curl -X POST "https://yoursite.com/wp-json/gread/v1/library/resume" \
  -H "Content-Type: application/json" \
  -H "Cookie: wordpress_logged_in_xxx=..." \
  -d '{
    "book_id": 123
  }'
```

---

### 4. Get User Library (Updated)

The existing library endpoint now includes status and DNF information.

**Endpoint:** `GET /library`

**Success Response (200):**
```json
[
  {
    "id": 1,
    "is_pending": false,
    "book": {
      "id": 123,
      "title": "Example Book",
      "author": "John Doe",
      "isbn": "9781234567890",
      "page_count": 350,
      "content": "Book description..."
    },
    "current_page": 142,
    "status": "dnf",
    "dnf": {
      "reason": "The pacing was too slow",
      "pages_read": 142,
      "date_dnf": "2025-12-03 10:30:00"
    }
  },
  {
    "id": 2,
    "is_pending": false,
    "book": {
      "id": 456,
      "title": "Another Book",
      "author": "Jane Smith",
      "isbn": "9780987654321",
      "page_count": 280,
      "content": "Book description..."
    },
    "current_page": 100,
    "status": "paused"
  },
  {
    "id": 3,
    "is_pending": false,
    "book": {
      "id": 789,
      "title": "Current Read",
      "author": "Bob Johnson",
      "isbn": "9781122334455",
      "page_count": 400,
      "content": "Book description..."
    },
    "current_page": 250,
    "status": "reading"
  }
]
```

**Status Values:**
- `reading` - Currently reading
- `paused` - Book is paused
- `dnf` - Did not finish (includes `dnf` object with reason)

---

## Activity Tracking

All DNF, pause, and resume actions are tracked in the activity system with the following activity types:
- `dnf` - Book marked as DNF
- `paused` - Book paused
- `resumed` - Book resumed

These activities can be retrieved through the activity feed endpoints.

---

## Use Cases

### Example 1: Mark a book as DNF
```javascript
fetch('/wp-json/gread/v1/library/dnf', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce // WordPress nonce for authentication
  },
  body: JSON.stringify({
    book_id: 123,
    reason: 'Too technical for my current level'
  })
})
.then(response => response.json())
.then(data => console.log(data));
```

### Example 2: Pause a book
```javascript
fetch('/wp-json/gread/v1/library/pause', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce
  },
  body: JSON.stringify({
    book_id: 456
  })
})
.then(response => response.json())
.then(data => console.log(data));
```

### Example 3: Resume a book
```javascript
fetch('/wp-json/gread/v1/library/resume', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce
  },
  body: JSON.stringify({
    book_id: 456
  })
})
.then(response => response.json())
.then(data => console.log(data));
```

---

## Notes

- DNF reasons are stored permanently for recommendation engine improvements
- Paused books retain all progress and can be resumed at any time
- You cannot pause completed books
- DNF books keep their progress at the time they were marked DNF
- All actions trigger activity tracking for the user's activity feed
