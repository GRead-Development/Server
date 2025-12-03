# Apple Authentication Integration Guide

This document explains how Apple Sign-In works in the GRead system and what data needs to be sent/received from your iOS app.

## Overview

The Apple authentication system uses REST API endpoints to handle both new user registration and returning user login. The server identifies users by their unique Apple User ID and maintains authentication tokens for app access.

---

## API Endpoints

### Base URL
```
https://your-domain.com/wp-json/gread/v1/auth/apple/
```

### 1. Login (Returning Users)
**Endpoint:** `POST /auth/apple/login`

**When to use:** When a user has previously registered and is signing in again.

**Request Headers:**
```
Content-Type: application/json
```

**Request Body:**
```json
{
  "appleUserID": "001234.abc123def456.7890"
}
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Login successful",
  "user_id": 123,
  "username": "johndoe",
  "email": "user@example.com",
  "token": "a1b2c3d4e5f6...",
  "display_name": "John Doe"
}
```

**Error Responses:**
- **400 Bad Request:** Missing Apple User ID
  ```json
  {
    "code": "invalid_request",
    "message": "Missing Apple User ID",
    "data": { "status": 400 }
  }
  ```

- **404 Not Found:** User not registered
  ```json
  {
    "code": "user_not_found",
    "message": "No account found with this Apple ID. Please register first.",
    "data": { "status": 404 }
  }
  ```

---

### 2. Register (New Users)
**Endpoint:** `POST /auth/apple/register`

**When to use:** When a user is signing in with Apple for the first time.

**Request Headers:**
```
Content-Type: application/json
```

**Request Body:**
```json
{
  "appleUserID": "001234.abc123def456.7890",
  "email": "user@privaterelay.appleid.com",
  "username": "johndoe",
  "fullName": "John Doe"
}
```

**Field Requirements:**
- `appleUserID` (required): Unique identifier from Apple
- `email` (required): User's email (may be Apple's private relay email)
- `username` (required):
  - 3-20 characters
  - Only letters, numbers, and underscores
  - Must be unique
- `fullName` (optional): User's full name from Apple

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Registration successful",
  "user_id": 123,
  "username": "johndoe",
  "email": "user@privaterelay.appleid.com",
  "token": "a1b2c3d4e5f6...",
  "display_name": "John Doe"
}
```

**Error Responses:**
- **400 Bad Request:** Missing required fields
  ```json
  {
    "code": "invalid_request",
    "message": "Missing required fields",
    "data": { "status": 400 }
  }
  ```

- **400 Bad Request:** Invalid username format
  ```json
  {
    "code": "invalid_username",
    "message": "Username can only contain letters, numbers, and underscores",
    "data": { "status": 400 }
  }
  ```

- **409 Conflict:** Username already exists
  ```json
  {
    "code": "username_exists",
    "message": "Username already exists",
    "data": { "status": 409 }
  }
  ```

- **409 Conflict:** Email already exists
  ```json
  {
    "code": "email_exists",
    "message": "An account with this email already exists",
    "data": { "status": 409 }
  }
  ```

- **409 Conflict:** Apple ID already registered
  ```json
  {
    "code": "apple_id_exists",
    "message": "This Apple ID is already registered",
    "data": { "status": 409 }
  }
  ```

---

## iOS Implementation Flow

### Step 1: Handle Apple Sign-In Button Tap

```swift
import AuthenticationServices

func handleAppleSignIn() {
    let request = ASAuthorizationAppleIDProvider().createRequest()
    request.requestedScopes = [.fullName, .email]

    let controller = ASAuthorizationController(authorizationRequests: [request])
    controller.delegate = self
    controller.performRequests()
}
```

### Step 2: Process Apple's Response

```swift
extension YourViewController: ASAuthorizationControllerDelegate {
    func authorizationController(controller: ASAuthorizationController,
                                didCompleteWithAuthorization authorization: ASAuthorization) {

        guard let credential = authorization.credential as? ASAuthorizationAppleIDCredential else {
            return
        }

        let appleUserID = credential.user

        // Check if this is first time sign in (Apple only provides name/email on first auth)
        let isFirstTime = credential.email != nil

        if isFirstTime {
            // Register new user
            registerWithApple(
                appleUserID: appleUserID,
                email: credential.email ?? "",
                fullName: formatFullName(credential.fullName)
            )
        } else {
            // Try to login existing user
            loginWithApple(appleUserID: appleUserID)
        }
    }
}
```

### Step 3: Format Full Name

```swift
func formatFullName(_ nameComponents: PersonNameComponents?) -> String {
    guard let components = nameComponents else { return "" }

    let formatter = PersonNameComponentsFormatter()
    return formatter.string(from: components)
}
```

### Step 4: Make API Calls

```swift
func registerWithApple(appleUserID: String, email: String, fullName: String) {
    let url = URL(string: "https://your-domain.com/wp-json/gread/v1/auth/apple/register")!

    var request = URLRequest(url: url)
    request.httpMethod = "POST"
    request.setValue("application/json", forHTTPHeaderField: "Content-Type")

    // Generate a username (you might want to let user pick one)
    let username = generateUsername(from: email, fullName: fullName)

    let body: [String: Any] = [
        "appleUserID": appleUserID,
        "email": email,
        "username": username,
        "fullName": fullName
    ]

    request.httpBody = try? JSONSerialization.data(withJSONObject: body)

    URLSession.shared.dataTask(with: request) { data, response, error in
        guard let data = data,
              let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
              let success = json["success"] as? Bool,
              success == true else {
            // Handle error
            self.handleAuthError(data: data)
            return
        }

        // Store the token for future API calls
        let token = json["token"] as? String ?? ""
        let userID = json["user_id"] as? Int ?? 0

        self.saveAuthToken(token: token, userID: userID, appleUserID: appleUserID)
        self.handleSuccessfulAuth()

    }.resume()
}

func loginWithApple(appleUserID: String) {
    let url = URL(string: "https://your-domain.com/wp-json/gread/v1/auth/apple/login")!

    var request = URLRequest(url: url)
    request.httpMethod = "POST"
    request.setValue("application/json", forHTTPHeaderField: "Content-Type")

    let body: [String: Any] = [
        "appleUserID": appleUserID
    ]

    request.httpBody = try? JSONSerialization.data(withJSONObject: body)

    URLSession.shared.dataTask(with: request) { data, response, error in
        guard let data = data else { return }

        if let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
           let success = json["success"] as? Bool,
           success == true {

            let token = json["token"] as? String ?? ""
            let userID = json["user_id"] as? Int ?? 0

            self.saveAuthToken(token: token, userID: userID, appleUserID: appleUserID)
            self.handleSuccessfulAuth()

        } else if let errorJson = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                  let code = errorJson["code"] as? String,
                  code == "user_not_found" {

            // User needs to register first
            // Show registration flow (need to prompt for email and name)
            self.showRegistrationPrompt()
        } else {
            self.handleAuthError(data: data)
        }

    }.resume()
}
```

### Step 5: Store Authentication

```swift
func saveAuthToken(token: String, userID: Int, appleUserID: String) {
    UserDefaults.standard.set(token, forKey: "gread_auth_token")
    UserDefaults.standard.set(userID, forKey: "gread_user_id")
    UserDefaults.standard.set(appleUserID, forKey: "gread_apple_user_id")
}

func getAuthToken() -> String? {
    return UserDefaults.standard.string(forKey: "gread_auth_token")
}
```

### Step 6: Use Token for Authenticated Requests

```swift
func makeAuthenticatedRequest(endpoint: String) {
    guard let token = getAuthToken() else {
        // User not logged in
        return
    }

    let url = URL(string: "https://your-domain.com/wp-json/gread/v1/\(endpoint)")!

    var request = URLRequest(url: url)
    request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
    request.setValue(token, forHTTPHeaderField: "X-WP-Nonce") // For WordPress nonce

    // Make your request...
}
```

---

## Important Notes for iOS Implementation

### 1. **Apple User ID is Persistent**
- The `appleUserID` is the same across all sign-ins for the same user
- Store this locally after first successful auth
- Use it to check if user should login vs register

### 2. **Name and Email Only Available Once**
Apple only provides the full name and email on the **first authorization**. On subsequent sign-ins, these fields are `nil`. Therefore:

- **Store locally:** Save the email and name when you first receive them
- **Handle gracefully:** If user deletes app and reinstalls, you may need to prompt for email/username again
- **Check server first:** Always try `/login` first, fall back to registration if user not found

### 3. **Username Generation**
Since Apple doesn't provide usernames, you need to either:
- Generate one automatically (e.g., from email or name)
- Prompt the user to choose a username during registration
- Use a hybrid approach: suggest one, allow editing

Example username generation:
```swift
func generateUsername(from email: String, fullName: String) -> String {
    // Try email prefix
    if let prefix = email.components(separatedBy: "@").first,
       prefix.count >= 3 {
        return prefix.lowercased()
    }

    // Try name-based
    let cleanName = fullName
        .lowercased()
        .replacingOccurrences(of: " ", with: "")
        .filter { $0.isLetter || $0.isNumber }

    return cleanName.isEmpty ? "user\(Int.random(in: 1000...9999))" : cleanName
}
```

### 4. **Error Handling**
Always handle these cases:
- Network failures
- Username conflicts (prompt user to try another)
- Email conflicts (user may have registered another way)
- Server errors

### 5. **Private Email Relay**
Users may use Apple's private relay email (`@privaterelay.appleid.com`). This is fine - treat it like any other email. Apple will forward communications to the user's real email.

---

## Testing Checklist

- [ ] First-time sign in creates new account
- [ ] Subsequent sign-ins authenticate existing account
- [ ] Token is stored and used for API calls
- [ ] Username conflicts show appropriate error
- [ ] Email conflicts show appropriate error
- [ ] Network errors are handled gracefully
- [ ] User can log out and log back in
- [ ] App reinstall handles missing name/email
- [ ] Private relay emails work correctly

---

## Common Issues & Solutions

### Issue: "user_not_found" on every sign-in
**Cause:** The app is not storing or sending the correct `appleUserID`

**Solution:** Ensure you're:
1. Storing the Apple User ID after first auth
2. Sending the exact same ID on subsequent logins (it should be identical)
3. Not regenerating or modifying the Apple User ID

### Issue: Getting name/email as nil on first sign-in
**Cause:** User has previously authorized the app

**Solution:**
- In development, revoke app access in Settings > Apple ID > Password & Security > Apps Using Apple ID
- Or handle nil values by prompting user for missing info

### Issue: 409 conflicts on registration
**Cause:** Username or email already taken

**Solution:**
- Catch 409 errors specifically
- Show user-friendly message
- For username conflicts, prompt user to choose another
- For email conflicts, suggest they log in instead

---

## Server-Side Implementation Reference

The server stores:
- `apple_user_id` in user meta (used to find user)
- `auth_provider` = 'apple' in user meta (tracks auth method)
- `gread_auth_token` hashed token in user meta (for API authentication)
- Standard WordPress user fields (email, username, display name, password hash)

Token validation happens via WordPress nonce system for REST API calls.

---

## Questions?

If you encounter issues:
1. Check the exact Apple User ID being sent vs stored
2. Verify the request body JSON is properly formatted
3. Check server response for specific error codes
4. Ensure Content-Type header is set to application/json
5. Test with a fresh Apple ID that hasn't been used before
