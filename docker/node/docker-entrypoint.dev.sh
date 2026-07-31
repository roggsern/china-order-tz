#!/bin/sh
set -e

# apps/web is bind-mounted at /app; node_modules and .next use named Docker volumes.
# Never remove those mount points — `rm -rf node_modules` fails with
# "Resource busy" and crashes the container into a restart loop.
#
# /app/.next is a named volume so dev compiles avoid Windows bind-mount I/O.
# Source edits still hot-reload through the bind mount; only build output is isolated.
#
# Named volumes can lag behind the image after new dependencies are added.
# Overlay image-built modules into the volume in place when packages are missing.
if [ -d /opt/node_modules ]; then
  mkdir -p node_modules

  if [ ! -f node_modules/libphonenumber-js/max/index.cjs ]; then
    echo "Syncing node_modules from image (missing packages)..."
    cp -a /opt/node_modules/. node_modules/
  fi
fi

exec "$@"
