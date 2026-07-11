#!/bin/sh
rm -f /tmp/cjar1.txt /tmp/cjar2.txt

curl -s -c /tmp/cjar1.txt 'http://localhost/admin/login.php' \
  -d 'username=admin&password=admin123' > /dev/null

PAGE=$(curl -s -b /tmp/cjar1.txt -c /tmp/cjar2.txt \
  'http://localhost/admin/dashboard.php')

CSRF_TOKEN=$(echo "$PAGE" | sed -n 's/.*csrf-token" content="\([^"]*\)".*/\1/p')
ACCESS_TOKEN=$(echo "$PAGE" | sed -n 's/.*access-token" content="\([^"]*\)".*/\1/p')

if [ -z "$ACCESS_TOKEN" ]; then
  ACCESS_TOKEN=$(curl -s -b /tmp/cjar2.txt \
    'http://localhost/admin/login.php' \
    -d 'username=admin&password=admin123' | \
    sed -n 's/.*"access_token":"\([^"]*\)".*/\1/p')
fi

echo "CSRF: [$CSRF_TOKEN]"
echo "TOKEN: [${ACCESS_TOKEN}]"

if [ -n "$CSRF_TOKEN" ] && [ -n "$ACCESS_TOKEN" ]; then
  RESULT=$(curl -s -X POST 'http://localhost/admin/api/listing_crud.php?action=update' \
    -b /tmp/cjar2.txt \
    -H "X-CSRF-Token: $CSRF_TOKEN" \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -d 'id=1&listing_type=furnished&title=Test Sample&city=chennai&address=Test Address&price=100&total_area_sqft=5000&available_sqft=1000-5000&inventory_type=Ready to move in&status=draft&office_space_type=rent&description=Test&existing_images=[]&amenities=[]')
  echo "RESULT: $RESULT"
else
  echo "FAILED to get CSRF or TOKEN"
fi
