# Citation Generator - Implementation Guide

## Overview

The citation generator allows users to create properly formatted academic citations for books in multiple formats (MLA, APA, Chicago). Citations are tracked for achievements and stored in user history.

---

## Features

✅ **MLA Format (Fully Implemented)**
- Proper author formatting (Last, First)
- Italicized titles
- Edition support
- Publisher and publication year
- Page range support
- Medium types (print, web, ebook, audiobook)
- Access date for web sources

🚧 **APA & Chicago Formats (Stubs Ready)**
- Basic implementation provided
- Ready for expansion in future updates

✅ **Citation Tracking**
- All citations stored in database
- User citation count for achievements
- Full citation history with pagination

✅ **Achievement Integration**
- New metric: `citations_created`
- Create achievements for citation milestones
- Auto-tracked on generation

---

## API Endpoints

### 1. Generate Citation

**Endpoint:** `POST /gread/v2/books/{book_id}/cite`

**Authentication:** Required (logged-in user)

**Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `book_id` | integer | Yes | - | ID of the book to cite |
| `format` | string | No | `mla` | Citation format: `mla`, `apa`, or `chicago` |
| `page_range` | string | No | null | Page range (e.g., "45-67" or "23") |
| `access_date` | string | No | null | Access date for online sources (YYYY-MM-DD) |
| `medium` | string | No | `print` | Medium: `print`, `web`, `ebook`, or `audiobook` |

**Example Request:**

```bash
curl -X POST https://gread.fun/wp-json/gread/v2/books/123/cite \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "format": "mla",
    "page_range": "45-67",
    "medium": "print"
  }'
```

**Example Response:**

```json
{
  "citation_id": 42,
  "citation": "Rowling, J.K. *Harry Potter and the Sorcerer's Stone*. Scholastic, 1998, pp. 45-67.",
  "format": "mla",
  "book": {
    "id": 123,
    "title": "Harry Potter and the Sorcerer's Stone",
    "author": "J.K. Rowling"
  },
  "created_at": "2025-12-04 10:30:00",
  "total_citations": 15
}
```

---

### 2. Get Citation History

**Endpoint:** `GET /gread/v2/me/citations`

**Authentication:** Required

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | integer | 20 | Number of citations to return |
| `offset` | integer | 0 | Pagination offset |

**Example Request:**

```bash
curl https://gread.fun/wp-json/gread/v2/me/citations?limit=10 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Example Response:**

```json
{
  "citations": [
    {
      "id": 42,
      "book_id": 123,
      "book_title": "Harry Potter and the Sorcerer's Stone",
      "format": "mla",
      "citation": "Rowling, J.K. *Harry Potter and the Sorcerer's Stone*. Scholastic, 1998, pp. 45-67.",
      "page_range": "45-67",
      "access_date": null,
      "medium": "print",
      "created_at": "2025-12-04 10:30:00"
    }
  ],
  "total": 15,
  "limit": 10,
  "offset": 0
}
```

---

### 3. Get Citation Count

**Endpoint:** `GET /gread/v2/me/citations/count`

**Authentication:** Required

**Example Request:**

```bash
curl https://gread.fun/wp-json/gread/v2/me/citations/count \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Example Response:**

```json
{
  "count": 15
}
```

---

## Citation Format Examples

### MLA Format

**Basic Book:**
```
Rowling, J.K. *Harry Potter and the Sorcerer's Stone*. Scholastic, 1998.
```

**With Page Range:**
```
Rowling, J.K. *Harry Potter and the Sorcerer's Stone*. Scholastic, 1998, pp. 45-67.
```

**With Single Page:**
```
Rowling, J.K. *Harry Potter and the Sorcerer's Stone*. Scholastic, 1998, p. 23.
```

**With Edition:**
```
Tolkien, J.R.R. *The Lord of the Rings*. 50th Anniversary ed., Houghton Mifflin, 2004.
```

**Web Source:**
```
King, Stephen. *The Stand*. Anchor Books, 1990. Web. Accessed 4 Dec. 2025.
```

---

## iOS App Integration

### Swift Model

```swift
struct Citation: Codable {
    let citationId: Int
    let citation: String
    let format: String
    let book: CitationBook
    let createdAt: String
    let totalCitations: Int

    enum CodingKeys: String, CodingKey {
        case citationId = "citation_id"
        case citation, format, book
        case createdAt = "created_at"
        case totalCitations = "total_citations"
    }
}

struct CitationBook: Codable {
    let id: Int
    let title: String
    let author: String
}

struct CitationRequest: Codable {
    let format: String
    let pageRange: String?
    let accessDate: String?
    let medium: String

    enum CodingKeys: String, CodingKey {
        case format
        case pageRange = "page_range"
        case accessDate = "access_date"
        case medium
    }
}
```

### API Client

```swift
class CitationService {
    let baseURL = "https://gread.fun/wp-json/gread/v2"

    func generateCitation(
        bookId: Int,
        format: String = "mla",
        pageRange: String? = nil,
        accessDate: String? = nil,
        medium: String = "print"
    ) async throws -> Citation {
        let url = URL(string: "\(baseURL)/books/\(bookId)/cite")!
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("Bearer \(authToken)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")

        let body = CitationRequest(
            format: format,
            pageRange: pageRange,
            accessDate: accessDate,
            medium: medium
        )
        request.httpBody = try JSONEncoder().encode(body)

        let (data, _) = try await URLSession.shared.data(for: request)
        return try JSONDecoder().decode(Citation.self, from: data)
    }

    func getCitationHistory(limit: Int = 20, offset: Int = 0) async throws -> [CitationHistory] {
        // Implementation...
    }
}
```

### UI Implementation

```swift
struct CitationModalView: View {
    @State private var selectedFormat = "mla"
    @State private var pageRange = ""
    @State private var medium = "print"
    @State private var generatedCitation: String?
    @State private var isLoading = false

    let book: Book
    let citationService: CitationService

    var body: some View {
        VStack(spacing: 20) {
            Text("Generate Citation")
                .font(.headline)

            // Format Selector
            Picker("Citation Format", selection: $selectedFormat) {
                Text("MLA").tag("mla")
                Text("APA").tag("apa")
                Text("Chicago").tag("chicago")
            }
            .pickerStyle(.segmented)

            // Page Range Input
            TextField("Page Range (optional)", text: $pageRange)
                .textFieldStyle(.roundedBorder)
                .keyboardType(.numbersAndPunctuation)

            // Medium Selector
            Picker("Medium", selection: $medium) {
                Text("Print").tag("print")
                Text("Web").tag("web")
                Text("E-Book").tag("ebook")
                Text("Audiobook").tag("audiobook")
            }

            // Generate Button
            Button(action: generate Citation) {
                if isLoading {
                    ProgressView()
                } else {
                    Text("Generate Citation")
                        .frame(maxWidth: .infinity)
                }
            }
            .buttonStyle(.borderedProminent)
            .disabled(isLoading)

            // Display Generated Citation
            if let citation = generatedCitation {
                VStack(alignment: .leading, spacing: 10) {
                    Text("Generated Citation:")
                        .font(.caption)
                        .foregroundColor(.secondary)

                    Text(citation)
                        .padding()
                        .background(Color(.systemGray6))
                        .cornerRadius(8)

                    Button(action: { copyCitation(citation) }) {
                        Label("Copy to Clipboard", systemImage: "doc.on.doc")
                    }
                    .buttonStyle(.bordered)
                }
            }
        }
        .padding()
    }

    private func generateCitation() {
        isLoading = true
        Task {
            do {
                let result = try await citationService.generateCitation(
                    bookId: book.id,
                    format: selectedFormat,
                    pageRange: pageRange.isEmpty ? nil : pageRange,
                    medium: medium
                )
                generatedCitation = result.citation
            } catch {
                // Handle error
                print("Error generating citation: \(error)")
            }
            isLoading = false
        }
    }

    private func copyCitation(_ text: String) {
        UIPasteboard.general.string = text
        // Show toast/alert
    }
}
```

### Adding to Book Menu

```swift
// In your book detail view or book options menu
Menu {
    Button("Read", action: { /* ... */ })
    Button("Add to Library", action: { /* ... */ })
    Button("Mark as DNF", action: { /* ... */ })
    Button("Mark as Paused", action: { /* ... */ })

    Divider()

    Button("Cite", action: {
        showCitationModal = true
    })
}
.sheet(isPresented: $showCitationModal) {
    CitationModalView(book: book, citationService: citationService)
}
```

---

## Web Integration (JavaScript)

### API Call Example

```javascript
async function generateCitation(bookId, options = {}) {
  const {
    format = 'mla',
    pageRange = null,
    accessDate = null,
    medium = 'print'
  } = options;

  const response = await fetch(
    `https://gread.fun/wp-json/gread/v2/books/${bookId}/cite`,
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getAuthToken()}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        format,
        page_range: pageRange,
        access_date: accessDate,
        medium
      })
    }
  );

  if (!response.ok) {
    throw new Error('Failed to generate citation');
  }

  return await response.json();
}
```

### Modal UI Example

```html
<!-- Citation Modal -->
<div id="citationModal" class="modal">
  <div class="modal-content">
    <h2>Generate Citation</h2>

    <form id="citationForm">
      <label>Citation Format:</label>
      <select id="format">
        <option value="mla" selected>MLA</option>
        <option value="apa">APA (Coming Soon)</option>
        <option value="chicago">Chicago (Coming Soon)</option>
      </select>

      <label>Page Range (optional):</label>
      <input type="text" id="pageRange" placeholder="e.g., 45-67 or 23">

      <label>Medium:</label>
      <select id="medium">
        <option value="print" selected>Print</option>
        <option value="web">Web</option>
        <option value="ebook">E-Book</option>
        <option value="audiobook">Audiobook</option>
      </select>

      <button type="submit">Generate Citation</button>
    </form>

    <div id="citationResult" style="display: none;">
      <h3>Generated Citation:</h3>
      <div class="citation-box">
        <p id="citationText"></p>
      </div>
      <button id="copyCitation">Copy to Clipboard</button>
    </div>
  </div>
</div>

<script>
document.getElementById('citationForm').addEventListener('submit', async (e) => {
  e.preventDefault();

  const bookId = getCurrentBookId(); // Your function to get current book
  const format = document.getElementById('format').value;
  const pageRange = document.getElementById('pageRange').value;
  const medium = document.getElementById('medium').value;

  try {
    const result = await generateCitation(bookId, {
      format,
      pageRange: pageRange || null,
      medium
    });

    // Display result
    document.getElementById('citationText').textContent = result.citation;
    document.getElementById('citationResult').style.display = 'block';

  } catch (error) {
    alert('Error generating citation: ' + error.message);
  }
});

document.getElementById('copyCitation').addEventListener('click', () => {
  const text = document.getElementById('citationText').textContent;
  navigator.clipboard.writeText(text).then(() => {
    alert('Citation copied to clipboard!');
  });
});
</script>
```

---

## Achievements Integration

### Creating Citation Achievements

From the WordPress admin panel:

1. Go to **HotSoup Admin > Achievements**
2. Click **Add New Achievement**
3. Configure:
   - **Name:** "Citation Scholar" (or your choice)
   - **Unlock Metric:** Select "Citations Created"
   - **Unlock Value:** Enter threshold (e.g., 10, 50, 100)
   - **Category:** Select "Contributions" or "Career"
   - **Points Reward:** Set reward points
   - **Icon:** Choose icon and color

### Example Achievements

| Achievement Name | Metric | Value | Description |
|-----------------|--------|-------|-------------|
| First Citation | citations_created | 1 | Create your first citation |
| Citation Novice | citations_created | 10 | Generate 10 citations |
| Citation Expert | citations_created | 50 | Generate 50 citations |
| Citation Master | citations_created | 100 | Generate 100 citations |

---

## Database Schema

### wp_hs_citations Table

```sql
CREATE TABLE wp_hs_citations (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    book_id bigint(20) NOT NULL,
    format varchar(20) NOT NULL,
    citation text NOT NULL,
    metadata text,
    created_at datetime NOT NULL,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY book_id (book_id),
    KEY created_at (created_at)
);
```

### User Meta

- **Key:** `hs_citations_created_count`
- **Type:** Integer
- **Description:** Total number of citations created by user
- **Updated:** Automatically on each citation generation

---

## Testing the Citation Generator

### Test via API

```bash
# Generate MLA citation
curl -X POST https://gread.fun/wp-json/gread/v2/books/123/cite \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "format": "mla",
    "page_range": "45-67",
    "medium": "print"
  }'

# Get citation history
curl https://gread.fun/wp-json/gread/v2/me/citations \
  -H "Authorization: Bearer YOUR_TOKEN"

# Get citation count
curl https://gread.fun/wp-json/gread/v2/me/citations/count \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Test Achievement Unlocking

1. Generate several citations using the API
2. Check user meta: `hs_citations_created_count`
3. Create a test achievement with low threshold (e.g., 1 citation)
4. Generate a citation
5. Verify achievement unlocks automatically

---

## Future Enhancements

### Planned Features

1. **Complete APA Format Implementation**
   - DOI support
   - Multiple authors handling
   - Online source formatting

2. **Complete Chicago Format Implementation**
   - Notes and bibliography styles
   - Publisher location
   - Multiple editions

3. **Additional Features**
   - Export citations to .bib files (BibTeX)
   - Batch citation generation
   - Citation collections/projects
   - Share citations with others
   - Citation style customization

### Expanding Citation Formats

To add a new format, update `gread_generate_[format]_citation()` function in `/includes/api/citations.php`:

```php
function gread_generate_harvard_citation($book_data, $page_range, $access_date, $medium) {
    // Implement Harvard citation format
    $citation = '';

    // Harvard format: Author(s) (Year) Title. Edition. Place: Publisher.
    // ... implementation ...

    return $citation;
}
```

Then add to the switch statement in `gread_generate_book_citation()`:

```php
case 'harvard':
    $citation = gread_generate_harvard_citation($book_data, $page_range, $access_date, $medium);
    break;
```

---

## Troubleshooting

### Common Issues

**Citation not generating:**
- Verify user is authenticated
- Check book_id exists
- Ensure book has required metadata (author, title, publisher)

**Achievement not unlocking:**
- Verify `hs_citations_created_count` is incrementing
- Check achievement metric is set to `citations_created`
- Manually run achievement check from admin debug tool

**Citation format issues:**
- MLA only fully implemented in v1
- APA/Chicago return basic format (stubs)
- Custom formatting may require code changes

### Debug Endpoints

Check user citation count:
```bash
curl https://gread.fun/wp-json/gread/v2/me/citations/count \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Summary

The citation generator is fully functional with MLA format support and ready for immediate use. Key features include:

✅ Complete MLA citation generation
✅ Citation history tracking
✅ Achievement integration
✅ API v2 endpoints
✅ User metric tracking
✅ iOS/Web ready

Perfect for academic users who need properly formatted citations for their reading!
