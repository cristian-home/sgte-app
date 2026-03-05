#!/usr/bin/env bash
# Restore Claude Code installation and config from the project volume backup.
# Run from INSIDE the container as the sail user (after a rebuild).
# Reads from /var/www/html/.claude-backup/ (created by claude-backup.sh).

set -euo pipefail

BACKUP_DIR="/var/www/html/.claude-backup"

if [ ! -d "$BACKUP_DIR" ]; then
    echo "ERROR: No backup found at $BACKUP_DIR"
    echo "Run docker/claude-backup.sh first to create a backup."
    exit 1
fi

echo "==> Restoring Claude Code..."
echo "    Backup from: $(cat "$BACKUP_DIR/backed-up-at.txt" 2>/dev/null || echo 'unknown')"
echo "    Version: $(cat "$BACKUP_DIR/version.txt" 2>/dev/null || echo 'unknown')"

# 1. Restore binary
echo "  - Binary: ~/.local/share/claude/"
mkdir -p /home/sail/.local/share/claude
rsync -a --delete "$BACKUP_DIR/share-claude/" /home/sail/.local/share/claude/

# 2. Restore symlink
CLAUDE_TARGET=$(cat "$BACKUP_DIR/claude-bin-target.txt" 2>/dev/null || true)
mkdir -p /home/sail/.local/bin
if [ -n "$CLAUDE_TARGET" ] && [ -f "$CLAUDE_TARGET" ]; then
    ln -sf "$CLAUDE_TARGET" /home/sail/.local/bin/claude
    echo "  - Symlink: ~/.local/bin/claude -> $CLAUDE_TARGET"
else
    # Fallback: find the binary in the restored directory
    LATEST=$(find /home/sail/.local/share/claude/versions -maxdepth 1 -type f | head -1)
    if [ -n "$LATEST" ]; then
        ln -sf "$LATEST" /home/sail/.local/bin/claude
        echo "  - Symlink (fallback): ~/.local/bin/claude -> $LATEST"
    else
        echo "  WARNING: Could not find claude binary to symlink"
    fi
fi

# 3. Restore config directory
echo "  - Config: ~/.claude/"
mkdir -p /home/sail/.claude
rsync -a --delete "$BACKUP_DIR/dot-claude/" /home/sail/.claude/

# 4. Ensure PATH includes ~/.local/bin
if ! echo "$PATH" | grep -q "/home/sail/.local/bin"; then
    echo 'export PATH="$HOME/.local/bin:$PATH"' >> /home/sail/.bashrc
    echo "  - Added ~/.local/bin to PATH in .bashrc"
fi

echo ""
echo "==> Restore complete!"
echo "    Run 'claude --version' to verify."
