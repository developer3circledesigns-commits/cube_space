#!/bin/sh

# Fresh start
rm -f /tmp/scookies.txt /tmp/scookies2.txt

# Login
curl -s -c /tmp/scookies.txt 'http://localhost/admin/login.php' \
  -d 'username=admin&password=admin123' > /dev/null

# Get dashboard to establish session + get CSRF token
PAGE=$(curl -s -b /tmp/scookies.txt -c /tmp/scookies2.txt \
  'http://localhost/admin/dashboard.php')

CSRF=$(echo "$PAGE" | grep -oE 'csrf-token" content="[^"]+' | cut -d'"' -f4)
echo "CSRF: $CSRF"

# Get a fresh token
LOGIN2=$(curl -s -b /tmp/scookies2.txt \
  'http://localhost/admin/login.php' \
  -d 'username=admin&password=admin123')
TOKEN=$(echo "$LOGIN2" | grep -oE '"access_token":"[^"]+' | cut -d'"' -f4)
echo "Token: $TOKEN"

# Make the update request
RESULT=$(curl -s -X POST 'http://localhost/admin/api/listing_crud.php?action=update' \
  -b /tmp/scookies2.txt \
  -H "X-CSRF-Token: $CSRF" \
  -H "Authorization: Bearer $TOKEN" \
  -d 'id=1&listing_type=furnished&title=Test Sample&city=chennai&address=Test Address&price=100&total_area_sqft=5000&available_sqft=1000-5000&inventory_type=Ready to move in&status=draft&office_space_type=rent&description=Test&existing_images=[]&amenities=[]')
echo "RESULT: $RESULT"
