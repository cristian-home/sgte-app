#!/usr/bin/env sh

# Launches the Laravel Boost MCP server in the right place depending on where
# Claude Code is running:
#
#   - VS Code devcontainer mode -> shell is already inside the Sail container,
#     so `php` resolves natively. Run artisan directly.
#   - Host shell -> there is no host PHP (the app runs in the `laravel.test`
#     container via Sail). Hop into the container with `docker compose exec`.
#     `-T` disables pseudo-TTY allocation, which is required because MCP speaks
#     JSON-RPC over stdio pipes (a TTY would corrupt the protocol).

if [ -f /.dockerenv ]; then
    exec php artisan boost:mcp
else
    exec docker compose exec -T -u sail laravel.test php artisan boost:mcp
fi
