# GRead iOS API Documentation

This document describes the REST API endpoints available for iOS apps to interact with the GRead platform.

## Base URL

All endpoints use the base URL: `/wp-json/gread/v1/`

## Authentication

Most endpoints require user authentication. The API uses WordPress authentication:
- Cookie-based authentication for web
- OAuth (Apple Sign-In and Google Sign-In) for mobile apps

### Social Authentication Endpoints

#### Sign In
```
POST /gread/v1/auth/signin
```

#### Register
```
POST /gread/v1/auth/register
```

---

## Books API

### Get Book Details
Retrieve comprehensive information about a book including authors, series, ratings, and user reviews.

```
GET /gread/v1/books/{book_id}
```

**Response:**
```json
{
  "id": 123,
  "title": "Book Title",
  "content": "Full description...",
  "description": "Short excerpt...",
  "permalink": "https://example.com/book/...",
  "publication_year": "2024",
  "page_count": 350,
  "isbn": "978-1234567890",
  "created_at": "2024-01-01 00:00:00",
  "modified_at": "2024-01-01 00:00:00",
  "authors": [
    {
      "id": 1,
      "name": "Author Name",
      "slug": "author-name",
      "order": 1
    }
  ],
  "series": [
    {
      "id": 1,
      "name": "Series Name",
      "slug": "series-name",
      "position": 1,
      "description": "Series description"
    }
  ],
  "rating": {
    "average": 4.5,
    "count": 120
  },
  "user_review": {
    "id": 1,
    "rating": 5,
    "review_text": "Great book!",
    "created_at": "2024-01-01 00:00:00",
    "updated_at": "2024-01-01 00:00:00"
  },
  "isbns": [...],
  "tags": [...],
  "cover_image": {
    "thumbnail": "https://...",
    "medium": "https://...",
    "large": "https://...",
    "full": "https://..."
  }
}
```

### Search Books
```
GET /gread/v1/books/search?query={search_term}
```

**Parameters:**
- `query` (required): Search term (minimum 3 characters)

**Response:**
```json
[
  {
    "id": 123,
    "title": "Book Title",
    "author": "Author Name",
    "isbn": "978-1234567890",
    "page_count": 350,
    "content": "Description...",
    "permalink": "https://..."
  }
]
```

### Get Book Rating Summary
Get average rating and distribution for a book.

```
GET /gread/v1/books/{book_id}/rating
```

**Response:**
```json
{
  "book_id": 123,
  "average_rating": 4.5,
  "review_count": 120,
  "rating_distribution": {
    "5": 80,
    "4": 30,
    "3": 8,
    "2": 2,
    "1": 0
  }
}
```

---

## Authors API

### List/Search Authors
```
GET /gread/v1/authors?search={name}&page={page}&per_page={per_page}
```

**Parameters:**
- `search` (optional): Author name to search for
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 20)

**Response:**
```json
{
  "authors": [
    {
      "id": 1,
      "name": "Author Name",
      "canonical_name": "Author Name",
      "slug": "author-name",
      "bio": "Biography...",
      "created_at": "2024-01-01 00:00:00",
      "updated_at": "2024-01-01 00:00:00"
    }
  ],
  "total": 100,
  "page": 1,
  "per_page": 20,
  "total_pages": 5
}
```

### Get Author Details
```
GET /gread/v1/authors/{author_id}
```

### Get Author by Name
```
GET /gread/v1/authors/by-name/{name}
```

### Get Author's Books
```
GET /gread/v1/authors/{author_id}/books
```

### Create Author
**Requires authentication**

```
POST /gread/v1/authors
```

**Body:**
```json
{
  "name": "Author Name",
  "bio": "Biography (optional)"
}
```

### Update Author
**Requires authentication**

```
PUT /gread/v1/authors/{author_id}
```

**Body:**
```json
{
  "name": "Updated Name",
  "bio": "Updated Biography"
}
```

---

## Series API

### List/Search Series
```
GET /gread/v1/series?search={name}&page={page}&per_page={per_page}
```

**Parameters:**
- `search` (optional): Series name to search for
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 20)

**Response:**
```json
{
  "series": [
    {
      "id": 1,
      "name": "Series Name",
      "slug": "series-name",
      "description": "Series description",
      "total_books": 5
    }
  ],
  "total": 50,
  "page": 1,
  "per_page": 20,
  "total_pages": 3
}
```

### Get Series Details
```
GET /gread/v1/series/{series_id}
```

### Get Books in Series
```
GET /gread/v1/series/{series_id}/books
```

**Response:**
```json
{
  "books": [
    {
      "book_id": 123,
      "series_id": 1,
      "position": 1,
      "title": "Book Title",
      "permalink": "https://...",
      "authors": [...]
    }
  ],
  "total": 5
}
```

### Create Series
**Requires authentication**

```
POST /gread/v1/series
```

**Body:**
```json
{
  "name": "Series Name",
  "description": "Description (optional)"
}
```

### Update Series
**Requires authentication**

```
PUT /gread/v1/series/{series_id}
```

### Delete Series
**Requires authentication**

```
DELETE /gread/v1/series/{series_id}
```

---

## Reviews API

### Get Book Reviews
Get all reviews for a specific book.

```
GET /gread/v1/books/{book_id}/reviews?page={page}&per_page={per_page}
```

**Parameters:**
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 20)

**Response:**
```json
{
  "reviews": [
    {
      "id": 1,
      "book_id": 123,
      "user_id": 456,
      "rating": 5,
      "review_text": "Excellent book!",
      "created_at": "2024-01-01 00:00:00",
      "updated_at": "2024-01-01 00:00:00",
      "display_name": "User Name",
      "user_login": "username"
    }
  ],
  "total": 120,
  "page": 1,
  "per_page": 20,
  "total_pages": 6
}
```

### Create/Update Review
**Requires authentication**

Creates a new review or updates existing review if user has already reviewed the book.

```
POST /gread/v1/books/{book_id}/reviews
```

**Body:**
```json
{
  "rating": 5,
  "review_text": "Great book! (optional)"
}
```

**Rating:** Must be between 1-5 (integer)

**Response:**
```json
{
  "success": true,
  "review": {
    "id": 1,
    "book_id": 123,
    "user_id": 456,
    "rating": 5,
    "review_text": "Great book!",
    "created_at": "2024-01-01 00:00:00",
    "updated_at": "2024-01-01 00:00:00"
  }
}
```

### Get Single Review
```
GET /gread/v1/reviews/{review_id}
```

### Update Review
**Requires authentication**

```
PUT /gread/v1/reviews/{review_id}
```

**Body:**
```json
{
  "rating": 4,
  "review_text": "Updated review text"
}
```

### Delete Review
**Requires authentication**

```
DELETE /gread/v1/reviews/{review_id}
```

### Get User's Reviews
**Requires authentication**

Get all reviews by the authenticated user.

```
GET /gread/v1/user/reviews?page={page}&per_page={per_page}
```

**Response:**
```json
{
  "reviews": [
    {
      "id": 1,
      "book_id": 123,
      "user_id": 456,
      "rating": 5,
      "review_text": "Great!",
      "created_at": "2024-01-01 00:00:00",
      "updated_at": "2024-01-01 00:00:00",
      "book_title": "Book Title"
    }
  ],
  "total": 15,
  "page": 1,
  "per_page": 20,
  "total_pages": 1
}
```

---

## Notes API

### Get Book Notes
**Requires authentication**

```
GET /gread/v1/books/{book_id}/notes
```

### Create Note
**Requires authentication**

```
POST /gread/v1/books/{book_id}/notes
```

**Body:**
```json
{
  "note_text": "My note about this book",
  "page_number": 42,
  "is_public": true
}
```

### Get Note
**Requires authentication**

```
GET /gread/v1/notes/{note_id}
```

### Update Note
**Requires authentication**

```
PUT /gread/v1/notes/{note_id}
```

### Delete Note
**Requires authentication**

```
DELETE /gread/v1/notes/{note_id}
```

### Like/Unlike Note
**Requires authentication**

```
POST /gread/v1/notes/{note_id}/like
DELETE /gread/v1/notes/{note_id}/like
```

---

## Library Management API

### Get User's Library
**Requires authentication**

```
GET /gread/v1/library
```

### Add Book to Library
**Requires authentication**

```
POST /gread/v1/library/add
```

**Body:**
```json
{
  "book_id": 123
}
```

### Update Reading Progress
**Requires authentication**

```
POST /gread/v1/library/progress
```

**Body:**
```json
{
  "book_id": 123,
  "current_page": 150
}
```

### Remove Book from Library
**Requires authentication**

```
DELETE /gread/v1/library/remove?book_id={book_id}
```

---

## Reporting API

### Report Book Inaccuracy
**Requires authentication**

```
POST /gread/v1/books/{book_id}/report
```

**Body:**
```json
{
  "report_type": "incorrect_info",
  "description": "The publication year is wrong",
  "field": "publication_year"
}
```

### Report User
**Requires authentication**

```
POST /gread/v1/user/report
```

**Body:**
```json
{
  "user_id": 789,
  "reason": "spam"
}
```

### Get User's Reports
**Requires authentication**

```
GET /gread/v1/user/reports
```

---

## User Management API

### Get User Statistics
**Requires authentication**

```
GET /gread/v1/user/{user_id}/stats
```

### Block User
**Requires authentication**

```
POST /gread/v1/user/block
```

**Body:**
```json
{
  "user_id": 789
}
```

### Unblock User
**Requires authentication**

```
POST /gread/v1/user/unblock
```

### Mute User
**Requires authentication**

```
POST /gread/v1/user/mute
```

### Unmute User
**Requires authentication**

```
POST /gread/v1/user/unmute
```

### Get Blocked Users
**Requires authentication**

```
GET /gread/v1/user/blocked_list
```

### Get Muted Users
**Requires authentication**

```
GET /gread/v1/user/muted_list
```

---

## Activity Feed API

### Get Activity Feed
**Requires authentication**

```
GET /gread/v1/activity?page={page}&per_page={per_page}
```

Automatically filters out blocked and muted users.

---

## Error Responses

All endpoints may return error responses in the following format:

```json
{
  "code": "error_code",
  "message": "Error description",
  "data": {
    "status": 404
  }
}
```

Common error codes:
- `not_authenticated` (401): User not logged in
- `unauthorized` (403): User doesn't have permission
- `book_not_found` (404): Book doesn't exist
- `author_not_found` (404): Author doesn't exist
- `series_not_found` (404): Series doesn't exist
- `review_not_found` (404): Review doesn't exist
- `create_failed` (500): Failed to create resource
- `update_failed` (500): Failed to update resource
- `delete_failed` (500): Failed to delete resource

---

## Rate Limiting

Currently, there are no rate limits enforced. This may change in the future.

---

## Changelog

### Version 1.0 (2024-11-29)
- Added comprehensive book details endpoint
- Added series API (list, get, create, update, delete)
- Added reviews system (full CRUD)
- Added book rating summary endpoint
- Enhanced existing author endpoints
- Added proper pagination support
- Added user review tracking
