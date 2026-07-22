#!/bin/bash
# Wird NACH einem Plugin-Update ausgefuehrt (als User loxberry).
# v1.4: Stellt Konfiguration und Daten aus der Sicherung wieder her.

BACKUPDIR=/tmp/renault_api_upgrade

for f in config.php session database.csv renault.log renault.log.1; do
    if [ -f "$BACKUPDIR/$f" ]; then
        echo "<INFO> Stelle $f wieder her"
        cp -f "$BACKUPDIR/$f" "REPLACELBPHTMLAUTHDIR/$f"
    fi
done
rm -rf $BACKUPDIR

# Fallback: Falls keine tmp-Sicherung existiert (z.B. Neuinstallation nach
# Deinstallation), Konfiguration aus dem Config-Verzeichnis wiederherstellen.
if ! grep -q "username = '.\+'" "REPLACELBPHTMLAUTHDIR/config.php" 2>/dev/null; then
    if [ -f "REPLACELBPCONFIGDIR/config.php.backup" ]; then
        echo "<INFO> Stelle Konfiguration aus dauerhafter Sicherung wieder her"
        cp -f "REPLACELBPCONFIGDIR/config.php.backup" "REPLACELBPHTMLAUTHDIR/config.php"
    fi
fi

exit 0
