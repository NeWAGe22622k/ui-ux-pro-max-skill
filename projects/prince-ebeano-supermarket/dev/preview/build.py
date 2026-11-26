"""Render the running PHP site into a static, clickable preview.

Run against a fresh demo database:
    dev/db.sh demo && php -S localhost:8000 -t public dev/router.php
    python3 dev/preview/build.py          ->  dev/out/preview/  (open home.html)

Every page is fetched from the real site for each store, links are rewritten to static
file names, CSS/JS are inlined, and shim.js adds the in-browser behaviour (search,
filters, sorting, and a staff morning update that is saved in the browser only).
"""
import html, json, os, re, shutil, urllib.parse, urllib.request, http.cookiejar

BASE = os.environ.get('BASE', 'http://localhost:8000')
HERE = os.path.dirname(os.path.abspath(__file__))
PROJECT = os.path.dirname(os.path.dirname(HERE))
OUT = os.path.join(PROJECT, 'dev', 'out', 'preview')
BRANCHES = ['jakande', 'ikeja', 'oniru', 'chevron', 'lekki-phase-1', 'wuse']
CATS = ['drinks', 'cereals', 'dairy', 'household', 'toiletries', 'mothercare', 'bakery', 'wine']
PUBLIC = ['index', 'shop', 'stores', 'about', 'gift-vouchers', 'careers', 'contact']
OWNER_PAGES = ['branches', 'categories', 'accounts', 'jobs', 'messages', 'settings']
DRINK_IDS = list(range(1, 7))


class Client:
    def __init__(self, cookies=None):
        # Shopper pages send a fixed store cookie and no session; staff pages keep a session cookie jar.
        self.jar = http.cookiejar.CookieJar()
        self.op = (urllib.request.build_opener() if cookies is not None
                   else urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar)))
        self.extra = cookies or ''

    def get(self, path):
        req = urllib.request.Request(BASE + path, headers={'Cookie': self.extra} if self.extra else {})
        with self.op.open(req) as r:
            return r.read().decode('utf-8')

    def post(self, path, form):
        body = urllib.parse.urlencode(form, doseq=True).encode()
        with self.op.open(urllib.request.Request(BASE + path, data=body)) as r:
            return r.read().decode('utf-8')

    @staticmethod
    def csrf(page_html):
        return re.search(r'name="csrf" value="([0-9a-f]+)"', page_html).group(1)

    def login(self, user, pw, name=''):
        page = self.get('/admin/login.php')
        self.post('/admin/login.php', {'csrf': self.csrf(page), 'username': user, 'password': pw, 'staff_name': name})
        return self

    def morning(self, branch_id, cat_id, statuses, prices=None):
        page = self.get('/admin/index.php')
        form = {'csrf': self.csrf(page), 'branch_id': branch_id, 'category_id': cat_id}
        for pid, st in statuses.items():
            form[f'status[{pid}]'] = st
        for pid, pr in (prices or {}).items():
            form[f'price[{pid}]'] = pr
        self.post('/admin/index.php', form)


# ---------------------------------------------------------------- a realistic morning of updates
Client().login('ikeja-cereals', 'demo-1234', 'Emeka').morning(2, 2, {10: 'in_stock', 11: 'in_stock'}, {10: '5,300', 11: '3,600'})
Client().login('ikeja-dairy', 'demo-1234', 'Ngozi').morning(2, 3, {14: 'low_stock'}, {14: '6,900'})
Client().login('wuse-drinks', 'demo-1234', 'Aisha').morning(6, 1, {1: 'low_stock', 4: 'in_stock'})
staff = Client().login('ikeja-drinks', 'demo-1234', 'Chioma')
staff.morning(2, 1, {4: 'in_stock', 5: 'low_stock'}, {4: '400', 5: '900'})
owner = Client().login('owner', 'owner-demo-123')


# ---------------------------------------------------------------- link mapping
def public_target(page, branch, query):
    ctx = branch or 'none'
    if page == 'index':
        return f'{ctx}-index.html' if branch else 'home.html'
    if page == 'shop':
        cat = (query.get('category') or [''])[0]
        if branch and cat in CATS:
            return f'{branch}-shop-{cat}.html'
        return f'{ctx}-shop.html'
    if page in PUBLIC:
        return f'{ctx}-{page}.html'
    return 'admin-login.html'


def map_url(raw, branch, role):
    url = html.unescape(raw)
    if url == '' or re.match(r'^(https?:|mailto:|tel:|data:|#|javascript:)', url):
        return raw
    parts = urllib.parse.urlsplit(url)
    path, query = parts.path, urllib.parse.parse_qs(parts.query)
    frag = ('#' + parts.fragment) if parts.fragment else ''
    if path.startswith('/assets/') or path.startswith('/uploads/'):
        return html.escape(path.lstrip('/'))
    if path.startswith('/admin/'):
        name = path[len('/admin/'):].replace('.php', '') or 'index'
        r = role or 'staff'
        if name in ('login', 'logout') or not role:
            target = 'admin-login.html'
        elif name == 'index':
            target = f'admin-{r}.html'
        elif name == 'product':
            pid = (query.get('id') or [''])[0]
            target = f'admin-{r}-product-{pid}.html' if pid and int(pid) in DRINK_IDS else f'admin-{r}-product-new.html'
        elif name in ('activity', 'password'):
            target = f'admin-{r}-{name}.html'
        elif name in OWNER_PAGES:
            target = f'admin-owner-{name}.html'
        else:
            target = f'admin-{r}.html'
        return html.escape(target + frag)
    page = path.strip('/').replace('.php', '') or 'index'
    b = (query.get('branch') or [branch])[0]
    if b not in BRANCHES:
        b = branch
    return html.escape(public_target(page, b, query) + frag)


CSS = open(os.path.join(PROJECT, 'public/assets/css/app.css')).read()
APPJS = open(os.path.join(PROJECT, 'public/assets/js/app.js')).read()
SHIM = open(os.path.join(HERE, 'shim.js')).read()
PV_CSS = """
.pv-banner{position:relative;z-index:60;background:var(--mango);color:var(--forest-deep);font-size:.85rem;font-weight:600}
.pv-banner .container{display:flex;flex-wrap:wrap;gap:.35rem 1rem;align-items:center;justify-content:space-between;padding-block:.45rem}
.pv-banner a,.pv-banner button{color:var(--forest-deep);font:inherit;font-weight:700;background:none;border:0;padding:.2rem 0;cursor:pointer;text-decoration:underline;text-underline-offset:3px}
.pv-banner .pv-actions{display:flex;gap:1rem;flex-wrap:wrap}
.pv-toast{position:fixed;left:50%;bottom:calc(var(--tabbar-h) + 16px + env(safe-area-inset-bottom,0px));transform:translate(-50%,20px);opacity:0;pointer-events:none;z-index:90;
  width:min(520px,calc(100% - 32px));background:var(--forest-deep);color:#fff;padding:.85rem 1.1rem;border-radius:12px;box-shadow:var(--shadow-lg);font-weight:600;transition:opacity .2s,transform .2s}
.pv-toast.is-on{opacity:1;transform:translate(-50%,0);pointer-events:auto}
.pv-toast a{color:var(--mango)}
body.admin .pv-toast{bottom:24px}
.pv-demo{background:var(--in-bg);color:var(--in-ink);border-radius:var(--radius-sm);padding:.8rem 1rem;margin-bottom:1rem;font-size:.92rem}
.pv-demo code{font-weight:700}
"""


def banner(admin):
    link = '<a href="home.html">Back to the shop</a>' if admin else '<a href="admin-login.html">Try the staff area</a>'
    return ('<div class="pv-banner"><div class="container"><span>Preview with demo data — stock, prices and store details are examples.</span>'
            f'<span class="pv-actions">{link}<button type="button" data-pv-reset>Reset demo</button></span></div></div>')


PILLS = {}
CHECK_ICON = ''


def transform(src, branch=None, role=None, page='', extra=None):
    out = re.sub(r'(href|src|action)="([^"]*)"', lambda m: f'{m.group(1)}="{map_url(m.group(2), branch, role)}"', src)
    out = re.sub(r'<link rel="stylesheet" href="assets/css/app\.css[^"]*">', lambda m: '<style>' + CSS + PV_CSS + '</style>', out)
    out = re.sub(r'<script src="assets/js/app\.js[^"]*" defer></script>', lambda m: '<script>' + APPJS + '</script>', out)
    out = re.sub(r'<meta property="og:image"[^>]*>', '', out)
    admin = role is not None or page == 'login'
    out = re.sub(r'(<body[^>]*>)', lambda m: m.group(1) + banner(admin), out, count=1)
    cfg = {'branch': branch, 'role': role, 'page': page, 'admin': admin, 'pills': PILLS, 'checkIcon': CHECK_ICON}
    cfg.update(extra or {})
    return out.replace('</body>', '<script>window.PE_PREVIEW=' + json.dumps(cfg) + ';</script><script>' + SHIM + '</script></body>')


if os.path.isdir(OUT):
    shutil.rmtree(OUT)
os.makedirs(os.path.join(OUT, 'assets/img'))
pages = {}


def emit(name, content):
    pages[name] = content
    with open(os.path.join(OUT, name), 'w') as f:
        f.write(content)


# Status label markup, reused by shim.js when a staff change is shown on a shopper page.
probe = Client('pe_branch=ikeja').get('/shop.php')
for cls, key in (('in', 'in_stock'), ('low', 'low_stock'), ('out', 'out_of_stock')):
    PILLS[key] = re.search(r'<span class="pill pill-' + cls + r'">(?:(?!</span>).)*</span>', probe, re.S).group(0)
CHECK_ICON = re.search(r'<svg[^>]*>(?:(?!</svg>).)*?<path d="m9 12 2 2 4-4"/></svg>', PILLS['in_stock'], re.S).group(0)

for b in [None] + BRANCHES:
    c = Client(f'pe_branch={b}' if b else '')
    for p in PUBLIC:
        emit(public_target(p, b, {}), transform(c.get('/' if p == 'index' else f'/{p}.php'), b, None, p))
    if b:
        for cat in CATS:
            emit(f'{b}-shop-{cat}.html', transform(c.get(f'/shop.php?category={cat}'), b, None, 'shop'))

morning_extra = {'cat': 'drinks', 'catName': 'Drinks', 'branchName': 'Ikeja', 'shopLink': 'ikeja-shop-drinks.html', 'defaultName': 'Chioma'}
login_src = Client().get('/admin/login.php').replace(
    '<p class="muted">Log in to update',
    '<div class="pv-demo"><strong>Demo logins</strong> (any password works in this preview):<br>'
    'Staff: <code>ikeja-drinks</code> plus your name · Owner: <code>owner</code></div><p class="muted">Log in to update', 1)
emit('admin-login.html', transform(login_src, None, None, 'login'))

for role, cl in (('staff', staff), ('owner', owner)):
    q = '?branch=2&category=1' if role == 'owner' else ''
    emit(f'admin-{role}.html', transform(cl.get('/admin/index.php' + q), None, role, 'morning', dict(morning_extra, branch='ikeja')))
    emit(f'admin-{role}-product-new.html', transform(cl.get('/admin/product.php?category=1&branch=2'), None, role, 'product'))
    for pid in DRINK_IDS:
        emit(f'admin-{role}-product-{pid}.html', transform(cl.get(f'/admin/product.php?id={pid}&branch=2'), None, role, 'product'))
    emit(f'admin-{role}-activity.html', transform(cl.get('/admin/activity.php'), None, role, 'activity'))
    emit(f'admin-{role}-password.html', transform(cl.get('/admin/password.php'), None, role, 'password'))
for p in OWNER_PAGES:
    emit(f'admin-owner-{p}.html', transform(owner.get(f'/admin/{p}.php'), None, 'owner', p))

for img in ('logo-mark.svg', 'favicon.svg', 'logo-badge.png', 'logo-badge.svg'):
    shutil.copy(os.path.join(PROJECT, 'public/assets/img', img), os.path.join(OUT, 'assets/img', img))
shutil.copytree(os.path.join(PROJECT, 'public/assets/img/art'), os.path.join(OUT, 'assets/img/art'))

# index.html opens the first-visit home page (so the preview folder can be double-clicked).
shutil.copy(os.path.join(OUT, 'home.html'), os.path.join(OUT, 'index.html'))

total = sum(os.path.getsize(os.path.join(dp, f)) for dp, _, fs in os.walk(OUT) for f in fs)
print(len(pages), 'pages,', round(total / 1e6, 1), 'MB in', OUT)
left = [n for n, s in pages.items() if re.search(r'(href|action)="/[^/]', s)]
print('pages with unmapped links:', left[:5] or 'none')
