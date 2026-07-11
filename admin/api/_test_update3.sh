#!/bin/sh

# Step 1: Login and get access token + session cookie
LOGIN_RESP=$(curl -s -c /tmp/cookies2.txt 'http://localhost/admin/login.php' -d 'username=admin&password=admin123')
TOKEN=$(echo "$LOGIN_RESP" | grep -oE '"access_token":"[^"]+' | cut -d'"' -f4)
echo "Access Token: ${TOKEN}"

# Step 2: Get dashboard to establish session with CSRF token  
DASH_HTML=$(curl -s -b /tmp/cookies2.txt -c /tmp/cookies2.txt 'http://localhost/admin/dashboard.php')
CSRF_TOKEN=$(echo "$DASH_HTML" | grep -oE 'csrf-token" content="[^"]+' | cut -d'"' -f4)
echo "CSRF Token: ${CSRF_TOKEN}"

# Step 3: Make update request
UPDATE_RESP=$(curl -s -X POST 'http://localhost/admin/api/listing_crud.php?action=update' \
  -b /tmp/cookies2.txt \
  -H "X-CSRF-Token: ${CSRF_TOKEN}" \
  -H "Authorization: Bearer ${TOKEN}" \
  -d "id=1&listing_type=furnished&title=Test+Sample&city=chennai&address=Test+Address&price=100&total_area_sqft=5000&available_sqft=1000-5000&inventory_type=Ready+to+move+in&status=draft&office_space_type=rent&description=Test&existing_images=[]&amenities=[]")
echo "Update Response: ${UPDATE_RESP}"
