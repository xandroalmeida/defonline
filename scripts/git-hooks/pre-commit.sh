#!/bin/sh
# Recusa commit com segredo em claro ou arquivo local não enviado para secrets/ (scripts/segredos.sh).
exec bash "$(git rev-parse --show-toplevel)/scripts/segredos.sh" check
