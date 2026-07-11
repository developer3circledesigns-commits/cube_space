# Login and get cookies
curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt 'http://localhost/admin/login.php' -d 'username=admin&password=admin123' > /dev/null

# Get edit page and extract CSRF token
CSRF=$(curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt 'http://localhost/admin/office-space.php?mode=edit&id=1&type=furnished' | grep -oE 'csrf-token" content="[^"]+' | cut -d'"' -f4)
echo "CSRF: $CSRF"

# Get access token from meta
TOKEN=$(curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt 'http://localhost/admin/office-space.php?mode=edit&id=1&type=furnished' | grep -oE 'access-token" content="[^"]+' | cut -d'"' -f4)
echo "Token: ${TOKEN:0:30}..."

# Make update request
echo "---"
curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt -X POST 'http://localhost/admin/api/listing_crud.php?action=update' \
  -H "X-CSRF-Token: $CSRF" \
  -H "Authorization: Bearer $TOKEN" \
  -d "id=1&listing_type=furnished&title=Test Sample&city=chennai&area=&address=Test Address&price=100&total_area_sqft=5000&available_sqft=1000-5000&inventory_type=Ready+to+move+in&status=draft&office_space_type=rent&featured=0&remarks=Test&description=Test+description&existing_images=[]&amenities=[]"
