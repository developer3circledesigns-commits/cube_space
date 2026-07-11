#!/bin/sh
# Login and get token
LOGIN=$(curl -s 'http://localhost/admin/login.php' -d 'username=admin&password=admin123')
TOKEN=$(echo "$LOGIN" | grep -oE '"access_token":"[^"]+' | cut -d'"' -f4)
echo "Token: ${TOKEN}"

# Make update request with auth
RESULT=$(curl -s -X POST 'http://localhost/admin/api/listing_crud.php?action=update' \
  -H "Authorization: Bearer $TOKEN" \
  -d "id=1&listing_type=furnished&title=Test+Sample&city=chennai&address=Test+Address&price=100&total_area_sqft=5000&available_sqft=1000-5000&inventory_type=Ready+to+move+in&status=draft&office_space_type=rent&description=Test&existing_images=[]&amenities=[]")
echo "Result: $RESULT"
