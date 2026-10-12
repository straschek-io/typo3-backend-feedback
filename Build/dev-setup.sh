#!/usr/bin/env bash
# Bootstrap a full TYPO3 dev instance around this extension for manual testing.
# Usage from a fresh clone: ./Build/dev-setup.sh
# Re-running is safe: an existing installation and its content are left untouched.
#
# The instance runs TYPO3 14.3 on PHP 8.4. TYPO3 13.4 is covered by the functional
# tests in CI; to try it by hand, require "^13.4" for the typo3/cms-* packages first.
set -euo pipefail
cd "$(dirname "$0")/.."

BACKEND_USER='admin'
BACKEND_PASSWORD='BackendFeedback14!'
SITE_URL='http://127.0.0.1:59011'

ddev start

ddev composer install --no-progress --no-interaction

if ! ddev exec test -f config/system/settings.php; then
    ddev exec vendor/bin/typo3 setup \
        --driver=mysqli --host=db --port=3306 --dbname=db --username=db --password=db \
        --admin-username="${BACKEND_USER}" --admin-user-password="${BACKEND_PASSWORD}" \
        --admin-email=admin@example.org --project-name='Backend Feedback Dev' \
        --server-type=other --force --no-interaction
    ddev exec php Build/dev-settings.php
fi

if [ ! -f config/sites/main/config.yaml ]; then
    mkdir -p config/sites/main
    cat > config/sites/main/config.yaml <<YAML
rootPageId: 1
base: '${SITE_URL}/'
websiteTitle: 'Backend Feedback Dev'
languages:
  - title: English
    enabled: true
    languageId: 0
    base: /
    locale: en_US.UTF-8
    navigationTitle: English
    flag: us
YAML

    ddev mutagen sync >/dev/null 2>&1 || true
    ddev mysql <<'SQL'
INSERT IGNORE INTO pages (uid, pid, title, doktype, is_siteroot, slug, tstamp, crdate, sorting)
VALUES (1, 0, 'Home', 1, 1, '/', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 256);

INSERT IGNORE INTO pages (uid, pid, title, doktype, slug, tstamp, crdate, sorting)
VALUES (2, 1, 'Products', 1, '/products', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 256);
SQL
fi

ddev exec vendor/bin/typo3 cache:flush

echo ""
echo "Backend: ${SITE_URL}/typo3 (${BACKEND_USER} / ${BACKEND_PASSWORD})"
echo "Mails:   ddev mailpit"
echo "Tests:   ddev composer ci"
