#!/bin/sh
# Downloads the development tools into tools/ (not in git). Pinned version, checked with SHA-256.
# Only used while developing. The app never loads anything from tools/.
set -eu
cd "$(dirname "$0")/.."
mkdir -p tools
VERSION=2.1.17
SHA256=767edc1d6a596308c9bdd1fa7e42bf27c88ce3772b8e527cba87dc1c19bd1c3b
curl -sSfL -o tools/phpstan.phar.tmp "https://github.com/phpstan/phpstan/releases/download/$VERSION/phpstan.phar"
echo "$SHA256  tools/phpstan.phar.tmp" | sha256sum -c -
mv tools/phpstan.phar.tmp tools/phpstan.phar
php tools/phpstan.phar --version
