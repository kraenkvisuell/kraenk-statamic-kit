# Stop on errors or unset variables.
set -eu

# Bloom provides the original checkout path and this workspace's ID.
# Start with the original project's environment settings.
cp "$BLOOM_ROOT_PATH/.env" .env

# Bloom runs this non-interactively, so ~/.zshrc is not sourced – and that is
# where Herd's PHP and nvm's node are put on the PATH. Without this, composer
# (shebang #!/usr/bin/env php) dies with "env: php: No such file or directory".
export PATH="$HOME/Library/Application Support/Herd/bin:$PATH"
export PHP_INI_SCAN_DIR="$HOME/Library/Application Support/Herd/config/php/:${PHP_INI_SCAN_DIR:-}"

export NVM_DIR="$HOME/.nvm"
if [ -s "$NVM_DIR/nvm.sh" ]; then
  . "$NVM_DIR/nvm.sh"
  nvm use default >/dev/null
fi

composer install

npm install

# Herd parks ~/Code, so it only serves the original checkout – a workspace under
# ~/bloom/workspaces.noindex has no host of its own and the browser would keep
# showing the original site (and its built assets) instead of this code. Give
# every workspace its own secured site, named "<project>-<workspace>", and let
# Herd rewrite APP_URL to match. `vite.config.js` reads the host back out of
# APP_URL to find the TLS cert, because laravel-vite-plugin would otherwise
# derive the cert name from the directory – which here is the branch name.
site_name="$(printf '%s-%s' "$(basename "$BLOOM_ROOT_PATH")" "$(basename "$PWD")" \
  | tr '[:upper:]' '[:lower:]' | tr -c '[:alnum:]' '-' | sed 's/-\{1,\}/-/g; s/^-//; s/-$//')"

herd link "$site_name" --secure --update-env --no-interaction

echo "This workspace is served at https://${site_name}.test"

# No `npm run dev` here: the Vite dev server runs in the foreground and setup
# would never finish. Start it in a terminal pane when you need HMR.
