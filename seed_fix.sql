-- =====================================================
-- CubeSpace - Corrected Seed Data (excerpt with fix)
-- =====================================================

-- Insert missing data for unfurnished_offices record with correct column count
INSERT IGNORE INTO unfurnished_offices (title, slug, description, city, area, address, latitude, longitude, price, price_label, total_seats, available_sqft, min_inventory, inventory_type, total_area_sqft, amenities, images, status, featured, feature_highlights, seo_text, office_space_type, listing_type, listing_code, created_at, updated_at) VALUES
('Unfurnished Office Marathahalli Bridge','uf-marathahalli-bridge','Unfurnished office near Marathahalli Bridge with ORR access.','bangalore','Marathahalli','Marathahalli, Outer Ring Road, Bangalore - 560037',12.9582,77.7010,85000,'Starting from',45,'1000-2000','3 seats','Unfurnished',2200,'["Power Backup","Security","CCTV","Elevator","Parking"]','["/uploads/listings/uf_marathahalli_1.jpg"]','published',0,'["ORR Access","IT Companies Nearby","Flexible Layout"]','<h3>Unfurnished Office Marathahalli</h3><p>45-seat unfurnished office near Marathahalli.</p>','rent','unfurnished','UFU007','2025-03-10 10:00:00','2025-06-05 10:00:00');
