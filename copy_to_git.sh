#!/bin/bash

mkdir -p "/tmp/GitHub/ca.update.applications/source/ca.update.applications/usr/local/emhttp/plugins/ca.update.applications/"

cp /usr/local/emhttp/plugins/ca.update.applications/* /tmp/GitHub/ca.update.applications/source/ca.update.applications/usr/local/emhttp/plugins/ca.update.applications -R -v -p
find . -maxdepth 9999 -noleaf -type f -name "._*" -exec rm -v "{}" \;

