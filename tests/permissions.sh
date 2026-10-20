#!/bin/sh
# Run inside the isolated test container as root; all changes stay in mktemp.
set -eu
fixture="$(mktemp -d)"
trap 'rm -rf "$fixture"' EXIT
mkdir -p "$fixture/storage/private-shared" "$fixture/storage/tmp" "$fixture/public/assets"
touch "$fixture/flarum" "$fixture/composer.json"
chown -R www-data:www-data "$fixture"
chown root:root "$fixture/storage/private-shared"
chmod 755 "$fixture/storage/private-shared"
set +e
su -s /bin/sh www-data -c "php /app/scripts/check-storage.php $fixture"
result=$?
set -e
test "$result" = 1
echo PERMISSION_DENIED_REPRODUCED
mkdir -p "$fixture/storage/private-shared/files"
chown -R www-data:www-data "$fixture/storage/private-shared"
chmod 700 "$fixture/storage/private-shared" "$fixture/storage/private-shared/files"
su -s /bin/sh www-data -c "php /app/scripts/check-storage.php $fixture"
echo PERMISSION_FIX_VERIFIED
