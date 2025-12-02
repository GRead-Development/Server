# GRead API Documentation - Book & Author Pages

This document describes the API endpoints for retrieving book page and author page data for iOS/Android mobile applications.

## Base URL
All endpoints are prefixed with: `/wp-json/gread/v1/`

## Authentication
Most read endpoints are publicly accessible. Write endpoints require authentication.

---

## Book Page Endpoints

### Get Book Page Data
Retrieves comprehensive information for a single book page, including all metadata, cover image, and statistics.

**Endpoint:** `GET /book/{id}`

**Parameters:**
- `id` (required) - The book post ID

**Response:**
```json
{
  "id": 123,
  "title": "The Great Gatsby",
  "author": "F. Scott Fitzgerald",
  "description": "The story of the mysteriously wealthy Jay Gatsby...",
  "isbn": "9780743273565",
  "page_count": 180,
  "publication_year": "1925",
  "cover_image": "https://example.com/covers/great-gatsby.jpg",
  "statistics": {
    "total_readers": 1520,
    "average_rating": 4.2,
    "review_count": 342
  }
}
```

**Example:**
```
GET /wp-json/gread/v1/book/123
```

### Get Book with ISBNs
Retrieves book data including all associated ISBNs for different editions.

**Endpoint:** `GET /books/{id}`

**Parameters:**
- `id` (required) - The book post ID

**Response:**
```json
{
  "success": true,
  "book": {
    "id": 123,
    "title": "The Great Gatsby",
    "author": "F. Scott Fitzgerald",
    "page_count": 180,
    "publication_year": "1925",
    "isbn": "9780743273565",
    "description": "...",
    "gid": 456,
    "is_canonical": true,
    "cover_url": "https://example.com/covers/great-gatsby.jpg",
    "isbns": [
      {
        "isbn": "9780743273565",
        "edition": "Paperback",
        "year": 2004,
        "is_primary": true,
        "post_id": 123
      },
      {
        "isbn": "9780684830421",
        "edition": "Hardcover",
        "year": 1995,
        "is_primary": false,
        "post_id": 123
      }
    ]
  }
}
```

**Example:**
```
GET /wp-json/gread/v1/books/123
```

---

## Author Page Endpoints

### Get Author Details
Retrieves basic author information including bio, book count, and aliases.

**Endpoint:** `GET /authors/{author_id}`

**Parameters:**
- `author_id` (required) - The author ID

**Response:**
```json
{
  "success": true,
  "author": {
    "id": 42,
    "name": "J.K. Rowling",
    "canonical_name": "J.K. Rowling",
    "slug": "jk-rowling",
    "bio": "British author, best known for the Harry Potter series...",
    "created_at": "2024-01-15 10:30:00",
    "updated_at": "2024-01-15 10:30:00",
    "book_count": 15,
    "aliases": [
      {
        "id": 1,
        "author_id": 42,
        "alias_name": "Robert Galbraith",
        "alias_slug": "robert-galbraith",
        "created_at": "2024-01-15 10:35:00"
      }
    ]
  }
}
```

**Example:**
```
GET /wp-json/gread/v1/authors/42
```

### Get Author's Books
Retrieves all books by an author with complete metadata for each book.

**Endpoint:** `GET /authors/{author_id}/books`

**Parameters:**
- `author_id` (required) - The author ID

**Response:**
```json
{
  "success": true,
  "author": {
    "id": 42,
    "name": "J.K. Rowling",
    "canonical_name": "J.K. Rowling",
    "slug": "jk-rowling",
    "bio": "British author, best known for the Harry Potter series...",
    "created_at": "2024-01-15 10:30:00",
    "updated_at": "2024-01-15 10:30:00"
  },
  "books": [
    {
      "id": 101,
      "title": "Harry Potter and the Philosopher's Stone",
      "author": "J.K. Rowling",
      "description": "Harry Potter has never even heard of Hogwarts...",
      "isbn": "9780747532699",
      "page_count": 223,
      "publication_year": "1997",
      "cover_image": "https://example.com/covers/hp1.jpg",
      "statistics": {
        "total_readers": 5420,
        "average_rating": 4.8,
        "review_count": 1250
      }
    },
    {
      "id": 102,
      "title": "Harry Potter and the Chamber of Secrets",
      "author": "J.K. Rowling",
      "description": "The Dursleys were so mean and hideous that summer...",
      "isbn": "9780747538493",
      "page_count": 251,
      "publication_year": "1998",
      "cover_image": "https://example.com/covers/hp2.jpg",
      "statistics": {
        "total_readers": 4890,
        "average_rating": 4.7,
        "review_count": 1102
      }
    }
  ],
  "total": 7
}
```

**Example:**
```
GET /wp-json/gread/v1/authors/42/books
```

### Get Complete Author Page (Recommended)
**NEW ENDPOINT** - Retrieves complete author page data in a single API call, combining author info and all their books with full metadata. This is the most efficient endpoint for displaying an author page.

**Endpoint:** `GET /authors/{author_id}/page`

**Parameters:**
- `author_id` (required) - The author ID

**Response:**
```json
{
  "success": true,
  "author": {
    "id": 42,
    "name": "J.K. Rowling",
    "canonical_name": "J.K. Rowling",
    "slug": "jk-rowling",
    "bio": "British author, best known for the Harry Potter series...",
    "created_at": "2024-01-15 10:30:00",
    "updated_at": "2024-01-15 10:30:00",
    "book_count": 15,
    "aliases": [
      {
        "id": 1,
        "author_id": 42,
        "alias_name": "Robert Galbraith",
        "alias_slug": "robert-galbraith",
        "created_at": "2024-01-15 10:35:00"
      }
    ]
  },
  "books": [
    {
      "id": 101,
      "title": "Harry Potter and the Philosopher's Stone",
      "author": "J.K. Rowling",
      "description": "Harry Potter has never even heard of Hogwarts...",
      "isbn": "9780747532699",
      "page_count": 223,
      "publication_year": "1997",
      "cover_image": "https://example.com/covers/hp1.jpg",
      "statistics": {
        "total_readers": 5420,
        "average_rating": 4.8,
        "review_count": 1250
      }
    }
  ],
  "total_books": 15
}
```

**Example:**
```
GET /wp-json/gread/v1/authors/42/page
```

**Note:** This endpoint is optimized for mobile apps and reduces the number of API calls needed to display a complete author page.

### Get Author by Name/Slug
Find an author by their name or URL slug.

**Endpoint:** `GET /authors/by-name/{name}`

**Parameters:**
- `name` (required) - The author name or slug

**Response:** Same as "Get Author Details"

**Example:**
```
GET /wp-json/gread/v1/authors/by-name/jk-rowling
```

### List All Authors
Retrieves a paginated list of all authors.

**Endpoint:** `GET /authors`

**Parameters:**
- `search` (optional) - Search term to filter authors
- `page` (optional, default: 1) - Page number
- `per_page` (optional, default: 20) - Results per page

**Response:**
```json
{
  "success": true,
  "authors": [
    {
      "id": 42,
      "name": "J.K. Rowling",
      "canonical_name": "J.K. Rowling",
      "slug": "jk-rowling",
      "bio": "British author, best known for the Harry Potter series...",
      "created_at": "2024-01-15 10:30:00",
      "updated_at": "2024-01-15 10:30:00",
      "book_count": 15
    }
  ],
  "pagination": {
    "total": 1250,
    "page": 1,
    "per_page": 20,
    "total_pages": 63
  }
}
```

**Example:**
```
GET /wp-json/gread/v1/authors?search=rowling&page=1&per_page=10
```

---

## Usage Recommendations for Mobile Apps

### Displaying a Book Page
Use the `/book/{id}` endpoint to get all necessary data in one call:
```
GET /wp-json/gread/v1/book/123
```

This returns everything needed to display:
- Book cover image
- Title, author, description
- Page count, ISBN, publication year
- Reader statistics and ratings

### Displaying an Author Page
**Recommended:** Use the new `/authors/{author_id}/page` endpoint for the most efficient data retrieval:
```
GET /wp-json/gread/v1/authors/42/page
```

This single call provides:
- Complete author information (name, bio, aliases)
- All author's books with full metadata
- Book covers, descriptions, and statistics

**Alternative:** Make separate calls if you need to lazy-load books:
1. First, get author info: `GET /authors/42`
2. Then, get books when needed: `GET /authors/42/books`

### Performance Tips
- Use the `/page` endpoints when displaying full pages to minimize API calls
- Cache book covers and author data locally when possible
- The `/books` and `/page` endpoints return identical book data structures, so your UI components can handle both

---

## Error Responses

All endpoints return standard HTTP status codes:

**404 Not Found:**
```json
{
  "code": "book_not_found",
  "message": "Book not found",
  "data": {
    "status": 404
  }
}
```

**400 Bad Request:**
```json
{
  "code": "invalid_name",
  "message": "Author name cannot be empty",
  "data": {
    "status": 400
  }
}
```

**401 Unauthorized:**
```json
{
  "code": "not_authenticated",
  "message": "User not authenticated",
  "data": {
    "status": 401
  }
}
```

---

## Data Fields Reference

### Book Object
| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Unique book ID |
| `title` | string | Book title |
| `author` | string | Author name(s) |
| `description` | string | Book description/summary |
| `isbn` | string | Primary ISBN |
| `page_count` | integer | Number of pages |
| `publication_year` | string | Year of publication |
| `cover_image` | string | URL to cover image |
| `statistics.total_readers` | integer | Number of users who have this book |
| `statistics.average_rating` | float | Average rating (0-5) |
| `statistics.review_count` | integer | Number of reviews |

### Author Object
| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Unique author ID |
| `name` | string | Author's display name |
| `canonical_name` | string | Standardized name |
| `slug` | string | URL-friendly slug |
| `bio` | string | Author biography |
| `created_at` | datetime | When author was added |
| `updated_at` | datetime | Last modification time |
| `book_count` | integer | Total books by this author |
| `aliases` | array | Pen names/alternate names |

---

## Version History

**v1.0** (Current)
- Initial release with book page endpoints
- Enhanced author endpoints with full book metadata
- New comprehensive author page endpoint (`/authors/{id}/page`)
