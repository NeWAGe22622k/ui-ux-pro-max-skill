-- Starter data: the 6 stores, 8 aisles and some demo products.
-- Import AFTER schema.sql. Safe to skip in production — you can add everything from the staff area instead.
-- Store addresses are taken from the old website; please check and complete them under Staff → Branches.

SET NAMES utf8mb4;

INSERT INTO branches (name, slug, city, address, phones, email, hours_text, sort_order) VALUES
('Jakande', 'jakande', 'Lagos', 'Regional Road, Seaside Beach Resort Estate, Jakande, Lekki', '08077025847', 'jakande@princeebeano.com', 'Mon–Sat 8:00am–9:30pm · Sun 12pm–9pm', 1),
('Ikeja',   'ikeja',   'Lagos', '14 Isaac John Street, G.R.A. Ikeja', '08123583239, 07051187146', 'ikeja@princeebeano.com', 'Mon–Sat 8:00am–9:30pm · Sun 12pm–9pm', 2),
('Oniru',   'oniru',   'Lagos', '1 F Kusoya Street, Oniru, Lekki', '', 'oniru@princeebeano.com', 'Mon–Sat 8:00am–9:30pm · Sun 12pm–9pm', 3),
('Chevron', 'chevron', 'Lagos', 'Chevron Drive, Lekki Peninsula II', '', 'chevron@princeebeano.com', 'Mon–Fri 8:00am–9:30pm · Sat 7am–9:30pm · Sun 12pm–9pm', 4),
('Lekki Phase 1', 'lekki-phase-1', 'Lagos', 'Lekki Phase 1', '', '', 'Mon–Sat 8:00am–9:30pm · Sun 12pm–9pm', 5),
('Wuse',    'wuse',    'Abuja', 'Zone 4, 9 Cape Town Street, Wuse, FCT', '07033477368', 'wuse@princeebeano.com', 'Mon–Sat 8:00am–9:30pm · Sun 12pm–9:30pm', 6);

INSERT INTO categories (name, slug, icon, tint, sort_order) VALUES
('Drinks',               'drinks',     'cup-soda',  'sky',   1),
('Cereals & Breakfast',  'cereals',    'wheat',     'mango', 2),
('Dairy',                'dairy',      'milk',      'mint',  3),
('Household',            'household',  'sparkles',  'lilac', 4),
('Toiletries',           'toiletries', 'spray-can', 'rose',  5),
('Mothercare',           'mothercare', 'baby',      'peach', 6),
('Bakery',               'bakery',     'croissant', 'sand',  7),
('Wine & Spirits',       'wine',       'wine',      'green', 8);

INSERT INTO products (category_id, name, description) VALUES
((SELECT id FROM categories WHERE slug='drinks'), 'Coca-Cola 50cl', 'Chilled soft drink'),
((SELECT id FROM categories WHERE slug='drinks'), 'Fanta Orange 50cl', ''),
((SELECT id FROM categories WHERE slug='drinks'), 'Chivita 100% Orange Juice 1L', 'No added sugar'),
((SELECT id FROM categories WHERE slug='drinks'), 'Nestlé Pure Life Water 75cl', ''),
((SELECT id FROM categories WHERE slug='drinks'), 'Malta Guinness 33cl Can', ''),
((SELECT id FROM categories WHERE slug='drinks'), 'Hollandia Yoghurt Strawberry 1L', ''),
((SELECT id FROM categories WHERE slug='cereals'), 'Nestlé Milo 400g Tin', 'Chocolate malt drink'),
((SELECT id FROM categories WHERE slug='cereals'), 'Kellogg''s Corn Flakes 500g', ''),
((SELECT id FROM categories WHERE slug='cereals'), 'Nutribom Rice Infant Cereal 400g', ''),
((SELECT id FROM categories WHERE slug='cereals'), 'Golden Morn 900g', 'Maize & soya cereal'),
((SELECT id FROM categories WHERE slug='cereals'), 'Quaker Oats 500g', ''),
((SELECT id FROM categories WHERE slug='dairy'), 'Peak Full Cream Milk Powder 400g', ''),
((SELECT id FROM categories WHERE slug='dairy'), 'Three Crowns Evaporated Milk 160g', ''),
((SELECT id FROM categories WHERE slug='dairy'), 'Farm Fresh Eggs (Crate of 30)', ''),
((SELECT id FROM categories WHERE slug='dairy'), 'Blue Band Margarine 450g', ''),
((SELECT id FROM categories WHERE slug='household'), 'Morning Fresh Dishwashing Liquid 900ml', ''),
((SELECT id FROM categories WHERE slug='household'), 'Hypo Bleach 1L', ''),
((SELECT id FROM categories WHERE slug='household'), 'Kenwood Steam Iron', 'Non-stick soleplate'),
((SELECT id FROM categories WHERE slug='household'), 'Rite-Tek Blender 1.5L', ''),
((SELECT id FROM categories WHERE slug='toiletries'), 'Dettol Antiseptic Liquid 500ml', ''),
((SELECT id FROM categories WHERE slug='toiletries'), 'Closeup Toothpaste 140g', ''),
((SELECT id FROM categories WHERE slug='toiletries'), 'Nivea Body Lotion 400ml', ''),
((SELECT id FROM categories WHERE slug='mothercare'), 'Nestlé NAN Optipro 1 400g', 'Infant formula, 0–6 months'),
((SELECT id FROM categories WHERE slug='mothercare'), 'Molfix Diapers Size 4 (Jumbo)', ''),
((SELECT id FROM categories WHERE slug='mothercare'), 'Pampers Baby Wipes (64)', ''),
((SELECT id FROM categories WHERE slug='bakery'), 'Sliced Family Bread', 'Baked fresh every morning'),
((SELECT id FROM categories WHERE slug='bakery'), 'McVitie''s Hobnobs 300g', ''),
((SELECT id FROM categories WHERE slug='bakery'), 'Meat Pie', 'From our in-store bakery'),
((SELECT id FROM categories WHERE slug='wine'), 'Four Cousins Sweet Red 75cl', ''),
((SELECT id FROM categories WHERE slug='wine'), 'Carlo Rossi Red 75cl', ''),
((SELECT id FROM categories WHERE slug='wine'), 'Martell VS Cognac 70cl', '');

-- Demo stock: every store carries every product, with a deterministic mix of statuses and prices.
INSERT INTO branch_stock (branch_id, product_id, stock_status, price_kobo, updated_at, updated_by_name)
SELECT b.id, p.id,
       CASE WHEN (b.id * 7 + p.id * 3) % 11 = 0 THEN 'out_of_stock'
            WHEN (b.id * 5 + p.id) % 7 = 0 THEN 'low_stock'
            ELSE 'in_stock' END,
       (50000 + ((p.id * 7919 + b.id * 131) % 120) * 5000),
       NOW() - INTERVAL ((p.id + b.id) % 90) MINUTE,
       'Demo'
FROM branches b CROSS JOIN products p;

INSERT INTO category_reviews (branch_id, category_id, reviewed_at, staff_name)
SELECT b.id, c.id, NOW() - INTERVAL ((b.id + c.id) % 5) HOUR, 'Demo'
FROM branches b CROSS JOIN categories c;

INSERT INTO jobs (title, branch_id, summary, is_open) VALUES
('Sales Associate', (SELECT id FROM branches WHERE slug='ikeja'), 'Help customers find what they need, keep shelves full and tidy, and support the morning stock check.', 1),
('Cashier', (SELECT id FROM branches WHERE slug='wuse'), 'Friendly, accurate and quick at the till. Previous retail experience is a plus.', 1),
('Supervisor', (SELECT id FROM branches WHERE slug='oniru'), 'Lead a team of sales associates and keep the store running smoothly.', 0),
('Sales Manager', (SELECT id FROM branches WHERE slug='lekki-phase-1'), 'Own store sales targets, supplier relationships and team performance.', 0);
