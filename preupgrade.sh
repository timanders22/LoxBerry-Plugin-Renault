#!/bin/bash
# Wird VOR einem Plugin-Update ausgefuehrt (als User loxberry).
# v1.4: Sichert Konfiguration und Daten, damit sie das Update ueberleben.

BACKUPDIR=/tmp/renault_api_upgrade
mkdir -p $BACKUPDIR

for f in config.php session database.csv renault.log renault.log.1; do
    if [ -f "REPLACELBPHTMLAUTHDIR/$f" ]; then
        echo "<INFO> Sichere $f"
        cp -f "REPLACELBPHTMLAUTHDIR/$f" "$BACKUPDIR/$f"
    fi
done

# Zusaetzliche dauerhafte Sicherung der Konfiguration im Config-Verzeichnis
# (uebersteht auch Deinstallation/Neuinstallation)
mkdir -p REPLACELBPCONFIGDIR
if [ -f "REPLACELBPHTMLAUTHDIR/config.php" ]; then
    cp -f "REPLACELBPHTMLAUTHDIR/config.php" "REPLACELBPCONFIGDIR/config.php.backup"
fi

exit 0
