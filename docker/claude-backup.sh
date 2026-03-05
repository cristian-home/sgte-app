#!/usr/bin/env bash
# Backup Claude Code installation and config to the project volume.
# Run from INSIDE the container as the sail user.
# The backup is saved to /var/www/html/.claude-backup/ (mounted volume, survives rebuilds).

set -euo pipefail

BACKUP_DIR="/var/www/html/.claude-backup"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)

echo "==> Backing up Claude Code..."

mkdir -p "$BACKUP_DIR"

# 1. Binary (~454 MB)
echo "  - Binary: ~/.local/share/claude/"
rsync -a --delete /home/sail/.local/share/claude/ "$BACKUP_DIR/share-claude/"

# 2. Symlink target
CLAUDE_LINK=$(readlink -f /home/sail/.local/bin/claude 2>/dev/null || true)
echo "$CLAUDE_LINK" > "$BACKUP_DIR/claude-bin-target.txt"
echo "  - Symlink target: $CLAUDE_LINK"

# 3. Config directory (~32 MB) — credentials, memory, settings, history
echo "  - Config: ~/.claude/"
rsync -a --delete /home/sail/.claude/ "$BACKUP_DIR/dot-claude/"

# 4. Record the installed version
claude --version 2>/dev/null > "$BACKUP_DIR/version.txt" || true
echo "  - Version: $(cat "$BACKUP_DIR/version.txt")"

# 5. Timestamp
echo "$TIMESTAMP" > "$BACKUP_DIR/backed-up-at.txt"

echo ""
echo "==> Backup complete: $BACKUP_DIR (~$(du -sh "$BACKUP_DIR" | cut -f1))"
echo "    Timestamp: $TIMESTAMP"
