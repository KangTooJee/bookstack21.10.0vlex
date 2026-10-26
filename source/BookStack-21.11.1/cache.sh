#!/bin/sh
# Local BookStack cache maintenance; no external dependencies.
set -eu
cd "$(dirname "$0")"
php artisan cache:clear
php artisan view:clear
