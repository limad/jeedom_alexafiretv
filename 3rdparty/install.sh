#!/bin/bash
touch /tmp/alexafiretv_dep
if [[ $EUID -ne 0 ]]; then
  sudo_prefix=sudo;
fi
echo 0 > /tmp/alexafiretv_dep
echo "╔══════════════════════[Installation des dépendances]═══════════════════════════════════════════"
echo "╠═══> Mise à jour générale"
echo "╠═══> apt-get -y update"
echo "╚═════════════════════════════════════════════════════════════════════════════════════"

echo 5 > /tmp/alexafiretv_dep
$sudo_prefix apt-get -y update
echo "╔══════════════════════[Installation des dépendances]═══════════════════════════════════════════"
echo "╠═══> Installation des dépendances"
echo "╠═══> apt-get -y install android-tools-adb"
echo "╚═════════════════════════════════════════════════════════════════════════════════════"
echo 50 > /tmp/alexafiretv_dep

$sudo_prefix apt-get -y install android-tools-adb
echo "╔══════════════════════[Installation des dépendances]═══════════════════════════════════════════"
echo "╠═══> Lancement Serveur"
echo "╠═══> adb start-server"
echo "╚═════════════════════════════════════════════════════════════════════════════════════"

echo 75 > /tmp/alexafiretv_dep
$sudo_prefix adb start-server
$sudo_prefix adb devices
echo 100 > /tmp/alexafiretv_dep
rm /tmp/alexafiretv_dep
echo "╔══════════════════════[Installation des dépendances]═══════════════════════════════════════════"
echo "╠═══> Installation terminée"
echo "╚═════════════════════════════════════════════════════════════════════════════════════"
