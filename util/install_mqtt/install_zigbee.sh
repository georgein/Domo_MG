#!/bin/bash -e

clear
echo
echo "${red}================================================================================${normal}"
echo "=================================== INSTALLATION ZIGBEE2MQTT =================================="
echo "${red}================================================================================${normal}"

echo "------------------------- Stopping Zigbee2MQTT..."
# sudo systemctl stop zigbee2mqtt

echo "Creation backup de la configuration..."
# cp -R data data-backup

echo "------------------------- suppression du rep zigbee2mqtt d'origine"
rm -r -f /opt/zigbee2mqtt

# Setup Node.js repository
sudo curl -sL https://deb.nodesource.com/setup_14.x | sudo -E bash -

echo "------------------------- Install Node.js"
sudo apt-get install -y nodejs git make g++ gcc

echo "${green}================================================================================${normal}"
echo "------------------------- Contrôle des version de nodeJS et npm ..."
node --version  # Should output v10.X, v12.X, v14.X or v15.X
npm --version  # Should output 6.X or 7.X

echo "${green}================================================================================${normal}"

echo "------------------------- Clone Zigbee2MQTT repository"
sudo git clone https://github.com/Koenkk/zigbee2mqtt.git /opt/zigbee2mqtt

echo "------------------------- Installation dépendances ..."
sudo chown -R root:root /opt/zigbee2mqtt

cd /opt/zigbee2mqtt 
npm ci --production

echo "${green}================================================================================${normal}"
echo "------------------------- Creation du fichier de service zigbee2mqtt.service de systemctl"

service_path="/etc/systemd/system/zigbee2mqtt.service"

echo "[Unit]
Description=zigbee2mqtt
After=network.target

[Service]
Environment='ZIGBEE2MQTT_DATA=/opt/zigbee2mqtt/data'
ExecStart=/usr/bin/npm start
WorkingDirectory=/opt/zigbee2mqtt

StandardOutput=inherit
StandardError=inherit
Restart=always
RestartSec=10

User=root

[Install]
WantedBy=multi-user.target" > $service_path

echo "${green}================================================================================${normal}"

echo "------------------------- recharge systemctl en cas de modification"
sudo systemctl --system daemon-reload

echo "------------------------- Enregistre le service"
sudo systemctl enable zigbee2mqtt.service

echo
echo "${red}================================================================================${normal}"
echo "------------------------- Port USB disponible ..."
ls -l /dev/serial/by-id
echo "${red}================================================================================${normal}"

pause

echo "------------------------- Restoration de la configuration d'origine ... "
cp -R data-backup/* data

echo "------------------------- EDITION DU FICHIER DE CONFIGURATION ..."
nano /opt/zigbee2mqtt/data/configuration.yaml

echo "------------------------- Suppression du backup ... "
rm -rf data-backup

echo "------------------------- Start Zigbee2MQTT"
sudo systemctl start zigbee2mqtt

echo "${red}================================================================================${normal}"
echo "=================================== ZIGBEE2MQTT INSTALLE !!! =================================="
echo "------------------------- Show status"
systemctl status zigbee2mqtt.service
echo "${red}================================================================================${normal}"