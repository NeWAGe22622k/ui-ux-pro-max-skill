"""Make a copy of the preview with the real store's name, logo and contact details replaced
by placeholders, for sharing the design publicly without using Prince Ebeano's identity.

    python3 dev/preview/debrand.py   ->  dev/out/preview-public/
"""
import os, re, shutil

HERE = os.path.dirname(os.path.abspath(__file__))
PROJECT = os.path.dirname(os.path.dirname(HERE))
SRC = os.path.join(PROJECT, 'dev', 'out', 'preview')
OUT = os.path.join(PROJECT, 'dev', 'out', 'preview-public')
TITLE = 'Fresh Market Stock Site'

LITERAL = [
    ('<strong>Prince Ebeano</strong>', '<strong>Sample</strong>'),
    ('PRINCE EBEANO', 'SAMPLE'),
    ('Prince Ebeano Supermarket', 'Sample Supermarket'),
    ('Prince Ebeano', 'Sample Supermarket'),
    ('princeebeano.com', 'example.com'),
    ('Prince+Ebeano+Supermarket', 'Sample+Supermarket'),
    ('Your one-stop store for all your daily household needs', 'Everything for your home, checked fresh every morning'),
    ('Two brothers, one promise: everyday quality for every family.', 'One promise: everyday quality for every family.'),
    ('is a partnership between two visionary brothers, <strong>Mr. Sunday Epede</strong> and <strong>Mr. David Ojei</strong>.',
     'is a family-run supermarket chain. This is placeholder text for your own story.'),
    ('they have grown the brand since its beginning in 2009 into more than ten outlets across Lagos and Abuja',
     'the team has grown the brand into several outlets across Lagos and Abuja'),
    ('Since 2009, Sample Supermarket has grown', 'Sample Supermarket has grown'),
    ('since 2009', 'for years'),
    ('Regional Road, Seaside Beach Resort Estate, Jakande, Lekki', '12 Example Road, Jakande, Lekki'),
    ('14 Isaac John Street, G.R.A. Ikeja', '24 Example Street, Ikeja'),
    ('1 F Kusoya Street, Oniru, Lekki', '5 Example Close, Oniru, Lekki'),
    ('Chevron Drive, Lekki Peninsula II', '8 Example Drive, Lekki'),
    ('Zone 4, 9 Cape Town Street, Wuse, FCT', '9 Example Street, Wuse, Abuja'),
    ('2349157294213', '2348000000001'),
    ('assets/img/logo-mark.svg', 'assets/img/sample-mark.svg'),
    ('assets/img/favicon.svg', 'assets/img/sample-mark.svg'),
    ('assets/img/logo-badge.png', 'assets/img/sample-badge.svg'),
    ('assets/img/logo-badge.svg', 'assets/img/sample-badge.svg'),
    ('Preview with demo data — stock, prices and store details are examples.',
     'Design preview: the store name, logo, contact details and stock are placeholders.'),
]
phones = {}


def fake_phone(m):
    return phones.setdefault(m.group(0), '0800000%04d' % (len(phones) + 1))


MARK = '''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img" aria-label="Sample Supermarket">
<defs><linearGradient id="t" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1E7339"/><stop offset=".55" stop-color="#0E4A1F"/><stop offset="1" stop-color="#082F12"/></linearGradient></defs>
<rect width="64" height="64" rx="15" fill="url(#t)"/>
<g fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" transform="translate(14 13) scale(1.5)">
<path d="m15 11-1 9"/><path d="m19 11-4-7"/><path d="M2 11h20"/><path d="m3.5 11 1.6 7.4a2 2 0 0 0 2 1.6h9.8a2 2 0 0 0 2-1.6l1.7-7.4"/><path d="M4.5 15.5h15"/><path d="m5 11 4-7"/><path d="m9 11 1 9"/></g></svg>'''
BADGE = '''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240" role="img" aria-label="Sample Supermarket">
<defs><radialGradient id="bg" cx="50%" cy="42%" r="60%"><stop offset="0" stop-color="#1E7A36"/><stop offset=".6" stop-color="#0E4A1F"/><stop offset="1" stop-color="#072C10"/></radialGradient>
<linearGradient id="t" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1E7339"/><stop offset="1" stop-color="#062A0F"/></linearGradient></defs>
<circle cx="120" cy="120" r="120" fill="url(#bg)"/>
<g transform="translate(88 44)"><rect width="64" height="64" rx="15" fill="url(#t)" stroke="#fff" stroke-opacity=".35" stroke-width="1.5"/>
<g fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" transform="translate(14 13) scale(1.5)">
<path d="m15 11-1 9"/><path d="m19 11-4-7"/><path d="M2 11h20"/><path d="m3.5 11 1.6 7.4a2 2 0 0 0 2 1.6h9.8a2 2 0 0 0 2-1.6l1.7-7.4"/><path d="M4.5 15.5h15"/><path d="m5 11 4-7"/><path d="m9 11 1 9"/></g></g>
<g fill="#fff" text-anchor="middle" font-family="'Poppins','Helvetica Neue',Arial,sans-serif" font-weight="700">
<text x="120" y="142" font-size="27" letter-spacing="2">SAMPLE</text><text x="120" y="164" font-size="14" letter-spacing="2">SUPERMARKET</text></g>
<text x="120" y="186" fill="#F2A93B" text-anchor="middle" font-family="'Poppins',Arial,sans-serif" font-weight="700" font-size="9.5" letter-spacing="1.5">YOUR LOGO GOES HERE</text></svg>'''

if os.path.isdir(OUT):
    shutil.rmtree(OUT)
os.makedirs(os.path.join(OUT, 'assets/img'))
for name in os.listdir(SRC):
    if not name.endswith('.html') or name == 'index.html':
        continue
    s = open(os.path.join(SRC, name)).read()
    for a, b in LITERAL:
        s = s.replace(a, b)
    s = re.sub(r'\b0[789][01]\d{8}\b', fake_phone, s)
    s = re.sub(r'(google\.com/maps/search/\?api=1&amp;query=)[^"]*', r'\1supermarket+Lagos', s)
    open(os.path.join(OUT, name), 'w').write(s)
open(os.path.join(OUT, 'assets/img/sample-mark.svg'), 'w').write(MARK)
shutil.copytree(os.path.join(SRC, 'assets/img/art'), os.path.join(OUT, 'assets/img/art'))
open(os.path.join(OUT, 'assets/img/sample-badge.svg'), 'w').write(BADGE)

# The artifact's main page: the first-visit home page without its document wrapper,
# because the publishing service adds its own <html>/<head>/<body>.
home = open(os.path.join(OUT, 'home.html')).read()
head = re.search(r'<head>(.*)</head>', home, re.S).group(1)
head = re.sub(r'<title>[^<]*</title>', '', head)
head = head.replace('<meta charset="utf-8">', '').replace(
    '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">', '')
body = home[re.search(r'<body[^>]*>', home).end():].replace('</body>', '').replace('</html>', '')
open(os.path.join(OUT, 'index.html'), 'w').write(f'<title>{TITLE}</title>\n' + head + body)

left = []
for name in os.listdir(OUT):
    if name.endswith('.html'):
        s = open(os.path.join(OUT, name)).read().lower()
        left += [(name, pat) for pat in ('ebeano', 'epede', 'ojei', '9157294', '8084065686', 'logo-badge', 'logo-mark') if pat in s]
print('files:', len(os.listdir(OUT)), '| leftover brand strings:', left[:10] or 'none', '| phones replaced:', len(phones))
