# Achievement API Migration Guide - V1 to V2

## Overview

This document outlines the migration from the legacy V1 achievement API to the new V2 API with support for categories and hidden achievements.

## Why Migrate?

The V2 API includes these new features:
- ✅ **Hidden achievements** - Shows `?` icon and `???` text until unlocked
- ✅ **Category organization** - Filter by: Authors, Books & Series, Categories, Contributions, Career
- ✅ **All achievements visible** - Hidden achievements appear in user lists (masked when locked)
- ✅ **Better API design** - No null pointer risks, proper field masking
- ✅ **Ordered by display_order** - Achievements appear in admin-defined order

---

## 🚨 MIGRATION TIMELINE

1. **Phase 1** - Deploy server code (✅ DONE)
2. **Phase 2** - Update iOS app to use V2 endpoints
3. **Phase 3** - Test thoroughly in production
4. **Phase 4** - Remove legacy V1 endpoints from server

**DO NOT remove V1 endpoints until iOS app is fully migrated and tested!**

---

## API Endpoint Mapping

### V1 → V2 Endpoint Changes

| V1 Endpoint (Legacy) | V2 Endpoint (New) | Status |
|---------------------|-------------------|---------|
| `GET /gread/v1/achievements` | `GET /gread/v2/achievements` | ⚠️ V1 filters hidden |
| `GET /gread/v1/achievements/{id}` | `GET /gread/v2/achievements/{id}` | ✅ Same behavior |
| `GET /gread/v1/achievements/slug/{slug}` | `GET /gread/v2/achievements/slug/{slug}` | ✅ Same behavior |
| `GET /gread/v1/user/{id}/achievements` | `GET /gread/v2/user/{id}/achievements` | ⚠️ V1 filters hidden |
| `GET /gread/v1/me/achievements` | `GET /gread/v2/me/achievements` | ⚠️ V1 filters hidden |
| `POST /gread/v1/me/achievements/check` | `POST /gread/v2/me/achievements/check` | ✅ Same behavior |
| `GET /gread/v1/achievements/stats` | `GET /gread/v2/achievements/stats` | ✅ Same function |
| `GET /gread/v1/achievements/leaderboard` | `GET /gread/v2/achievements/leaderboard` | ✅ Same function |

---

## Key Differences Between V1 and V2

### 1. Hidden Achievement Handling

**V1 Behavior:**
```json
// V1 does NOT return hidden achievements at all in user lists
GET /gread/v1/user/123/achievements
// Hidden achievements are completely absent from response
```

**V2 Behavior:**
```json
// V2 returns ALL achievements, but masks hidden ones when locked
GET /gread/v2/user/123/achievements

// Locked hidden achievement response:
{
  "id": 42,
  "slug": "secret-master",
  "name": "???",                    // Masked
  "description": "???",             // Masked
  "icon": {
    "type": "question",            // Special type
    "color": "#999999",            // Gray
    "symbol": "?",                 // Question mark
    "svg_url": null                // No SVG shown
  },
  "unlock_requirements": null,     // Hidden
  "progress": null,                // Hidden
  "steps": [],                     // Empty
  "reward": null,                  // Hidden
  "is_hidden": true,
  "is_unlocked": false,
  "category": "authors"
}

// Once unlocked, same achievement reveals full details:
{
  "id": 42,
  "slug": "secret-master",
  "name": "Secret Master",         // Revealed
  "description": "Complete all secret challenges",  // Revealed
  "icon": {
    "type": "crown",               // Real icon
    "color": "#FFD700",            // Real color
    "symbol": "👑",
    "svg_url": null
  },
  "unlock_requirements": {         // Revealed
    "metric": "author_read_count",
    "value": 50
  },
  "progress": {                    // Revealed
    "current": 50,
    "required": 50,
    "percentage": 100
  },
  "reward": 500,                   // Revealed
  "is_hidden": true,
  "is_unlocked": true,
  "date_unlocked": "2025-12-04 10:30:00"
}
```

### 2. Category Support

**V2 Only:**
```bash
# Get all achievements in a specific category
GET /gread/v2/achievements?category=authors
GET /gread/v2/user/123/achievements?category=career

# Available categories:
# - authors
# - books_and_series
# - categories
# - contributions
# - career
```

### 3. New Response Fields

All V2 responses include:
```json
{
  "category": "authors",  // NEW - can be null
  // ... other fields
}
```

---

## iOS App Code Changes Required

### Swift Model Updates

Update your Achievement model to handle V2 responses:

```swift
struct Achievement: Codable {
    let id: Int
    let slug: String
    let name: String
    let description: String
    let icon: AchievementIcon
    let unlockRequirements: UnlockRequirements?  // ⚠️ NOW OPTIONAL (null for hidden)
    let progress: Progress?                       // ⚠️ NOW OPTIONAL (null for hidden)
    let steps: [AchievementStep]
    let isMultistep: Bool
    let isUnlocked: Bool
    let dateUnlocked: String?
    let reward: Int?                              // ⚠️ NOW OPTIONAL (null for hidden)
    let isHidden: Bool
    let category: String?                         // ⚠️ NEW FIELD
    let displayOrder: Int
}

struct AchievementIcon: Codable {
    let type: String      // "question" for locked hidden achievements
    let color: String
    let symbol: String    // "?" for locked hidden achievements
    let svgUrl: String?
}
```

### API Client Updates

```swift
// Change base URL from v1 to v2
// OLD:
let baseURL = "https://gread.fun/wp-json/gread/v1"

// NEW:
let baseURL = "https://gread.fun/wp-json/gread/v2"

// Update all endpoint calls:
// OLD: GET /gread/v1/me/achievements
// NEW: GET /gread/v2/me/achievements

// Add category filtering support:
func getAchievements(category: String? = nil) async throws -> [Achievement] {
    var url = "\(baseURL)/me/achievements"
    if let category = category {
        url += "?category=\(category)"
    }
    // ... rest of implementation
}
```

### UI Updates for Hidden Achievements

```swift
func renderAchievement(_ achievement: Achievement) {
    if achievement.isHidden && !achievement.isUnlocked {
        // Show mystery state
        iconImageView.image = UIImage(systemName: "questionmark.circle")
        nameLabel.text = "???"
        descriptionLabel.text = "???"
        progressView.isHidden = true
        requirementsLabel.isHidden = true
    } else {
        // Show normal achievement
        iconImageView.loadIcon(from: achievement.icon)
        nameLabel.text = achievement.name
        descriptionLabel.text = achievement.description

        if let progress = achievement.progress {
            progressView.isHidden = false
            progressView.progress = Float(progress.percentage) / 100.0
        }

        if let requirements = achievement.unlockRequirements {
            requirementsLabel.text = "\(requirements.metric): \(requirements.value)"
        }
    }
}
```

### Category Filtering UI

```swift
enum AchievementCategory: String, CaseIterable {
    case authors = "authors"
    case booksAndSeries = "books_and_series"
    case categories = "categories"
    case contributions = "contributions"
    case career = "career"

    var displayName: String {
        switch self {
        case .authors: return "Authors"
        case .booksAndSeries: return "Books & Series"
        case .categories: return "Categories"
        case .contributions: return "Contributions"
        case .career: return "Career"
        }
    }
}

// Implement category filter in your achievements list
class AchievementsViewController: UIViewController {
    var selectedCategory: AchievementCategory?

    func loadAchievements() async {
        let achievements = try await apiClient.getAchievements(
            category: selectedCategory?.rawValue
        )
        // Update UI...
    }
}
```

---

## Testing Checklist

Before removing V1 endpoints, verify:

- [ ] All iOS API calls changed from `/v1/` to `/v2/`
- [ ] Swift models handle optional fields (`progress`, `unlockRequirements`, `reward`)
- [ ] Hidden achievements display with `?` icon and `???` text when locked
- [ ] Hidden achievements reveal properly after unlocking
- [ ] Category filtering works correctly
- [ ] Achievement ordering respects `display_order` field
- [ ] No null pointer crashes when accessing masked fields
- [ ] Unlocked hidden achievements show full details
- [ ] User achievement counts are correct (includes hidden achievements)
- [ ] Progress bars work for non-hidden achievements
- [ ] App handles new `category` field gracefully

---

## Rollback Plan

If issues arise after migrating to V2:

1. **Immediate rollback** - Change iOS app to use `/v1/` endpoints
2. V1 endpoints remain functional and unchanged
3. No server rollback needed - both versions coexist

---

## V1 Endpoint Removal (Phase 4)

**⚠️ DO NOT PERFORM UNTIL iOS APP IS FULLY MIGRATED**

After successful iOS migration, remove these from `/includes/api/achievements.php`:

### Files to Remove

1. **Endpoint Registrations (lines 20-136):**
   - All `register_rest_route('gread/v1', ...)` calls

2. **Callback Functions (lines 265-489):**
   - `gread_get_all_achievements()`
   - `gread_get_achievement_by_id()`
   - `gread_get_achievement_by_slug()`
   - `gread_get_user_achievements_with_progress()`
   - `gread_get_current_user_achievements()`
   - `gread_check_current_user_achievements()`

All marked with:
```php
/**
 * LEGACY V1: ...
 * REMOVE AFTER iOS APP MIGRATION
 */
```

### Search and Remove

```bash
# Find all legacy functions
grep -n "REMOVE AFTER iOS APP MIGRATION" includes/api/achievements.php

# Expected output shows line numbers of functions to remove
```

---

## Example V2 API Calls

### Get All Achievements
```bash
curl https://gread.fun/wp-json/gread/v2/achievements
```

### Get Achievements by Category
```bash
curl https://gread.fun/wp-json/gread/v2/achievements?category=authors
```

### Get User Achievements
```bash
curl https://gread.fun/wp-json/gread/v2/user/123/achievements
```

### Get User Achievements (Specific Category)
```bash
curl https://gread.fun/wp-json/gread/v2/user/123/achievements?category=career
```

### Get Only Locked Achievements
```bash
curl https://gread.fun/wp-json/gread/v2/me/achievements?filter=locked \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Check for New Unlocks
```bash
curl -X POST https://gread.fun/wp-json/gread/v2/me/achievements/check \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Support & Questions

For issues during migration:
- Check this document first
- Review commit: `343ecd8` for implementation details
- Test with v1 and v2 side-by-side
- V1 remains available during entire migration

---

**Migration Status:** 🟡 Phase 1 Complete (Server Deployed)
**Next Step:** Update iOS app to use V2 endpoints
**Timeline:** TBD by iOS team
