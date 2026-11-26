#!/bin/bash
# Local database helper. Needs MariaDB/MySQL running and the `mysql` client.
#
#   dev/db.sh setup   create the database, its user and app/config.php (once)
#   dev/db.sh reset   empty database with sample stores, aisles and products (no logins)
#   dev/db.sh demo    like reset, plus demo logins, a few messages and some stock changes
#
# Demo logins: owner / owner-demo-123, and staff (ikeja-drinks, ikeja-cereals, ikeja-dairy,
# wuse-drinks, lekki-phase-1-drinks) with password demo-1234.
set -euo pipefail
cd "$(dirname "$0")/.."

DB=ebeano
USER=ebeano
PASS=devpass

setup() {
  mysql -e "CREATE DATABASE IF NOT EXISTS $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
            CREATE USER IF NOT EXISTS '$USER'@'localhost' IDENTIFIED BY '$PASS';
            GRANT ALL ON $DB.* TO '$USER'@'localhost';"
  if [ ! -f app/config.php ]; then
    cat > app/config.php <<EOF
<?php
// Local development settings. Never commit this file.
\$c = require __DIR__ . '/config.sample.php';
\$c['db'] = ['host' => 'localhost', 'name' => '$DB', 'user' => '$USER', 'password' => '$PASS'];
\$c['base_url'] = 'http://localhost:8000';
\$c['secure_cookies'] = false;
return \$c;
EOF
    echo "Wrote app/config.php"
  fi
}

reset() {
  mysql -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql $DB < database/schema.sql
  mysql $DB < database/sample-data.sql
  find public/uploads -type f ! -name '.htaccess' ! -name '.gitkeep' -delete
}

demo() {
  reset
  php -r '
    $pdo = new PDO("mysql:host=localhost;dbname='$DB';charset=utf8mb4", "'$USER'", "'$PASS'");
    $owner = password_hash("owner-demo-123", PASSWORD_DEFAULT);
    $staff = password_hash("demo-1234", PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO accounts (username, password_hash, role) VALUES (?, ?, \"owner\")")->execute(["owner", $owner]);
    $add = $pdo->prepare("INSERT INTO accounts (username, password_hash, role, branch_id, category_id) VALUES (?, ?, \"staff\", ?, ?)");
    foreach ([["ikeja-drinks", 2, 1], ["ikeja-cereals", 2, 2], ["ikeja-dairy", 2, 3], ["wuse-drinks", 6, 1], ["lekki-phase-1-drinks", 5, 1]] as [$u, $b, $c]) {
        $add->execute([$u, $staff, $b, $c]);
    }'
  mysql --default-character-set=utf8mb4 $DB -e "
    INSERT INTO contact_messages (name, email, phone, subject, branch_id, message, created_at) VALUES
    ('Ada Okafor', 'ada@example.com', '08031234567', 'Do you stock ofada rice?', 2,
     'Good morning, please do you have ofada rice at the Ikeja store? I want to buy 2 bags this weekend.', NOW() - INTERVAL 40 MINUTE),
    ('Kunle Adeyemi', '', '08098765432', 'Thank you', 6,
     'Your staff at Wuse were very helpful yesterday. Keep it up!', NOW() - INTERVAL 1 DAY);"
}

case "${1:-}" in
  setup) setup ;;
  reset) reset ;;
  demo)  demo ;;
  *) echo "usage: dev/db.sh setup|reset|demo" >&2; exit 1 ;;
esac
