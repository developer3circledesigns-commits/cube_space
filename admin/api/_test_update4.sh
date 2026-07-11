#!/bin/sh

# Step 1: Login
LOGIN_RESP=$(curl -s -c /tmp/cookies3.txt 'http://localhost/admin/login.php' -d 'username=admin&password=admin123')
echo "Login response: $LOGIN_RESP"
TOKEN=$(echo "$LOGIN_RESP" | grep -oE '"access_token":"[^"]+' | cut -d'"' -f4)
echo "Token: ${TOKEN}"

# Step 2: Check if cookies are saved
echo "--- Cookie jar ---"
cat /tmp/cookies3.txt

# Step 3: Request dashboard with cookie
DASH_HTML=$(curl -s -b /tmp/cookies3.txt 'http://localhost/admin/dashboard.php' 2>&1)
echo "--- Dashboard (first 200 chars) ---"
echo "$DASH_HTML" | head -c 200

# Step 4: Try using the session from login directly
# Get a fresh CSRF token from a GET request to the listing page
PAGE_HTML=$(curl -s -b /tmp/cookies3.txt -c /tmp/cookies3.txt 'http://localhost/admin/office-space.php?mode=edit&id=1&type=furnished' 2>&1)
echo "--- Page (first 300 chars) ---"
echo "$PAGE_HTML" | head -c 300
