#!/bin/bash -e

clear
echo
echo "${red}================================================================================${normal}"
echo "=================================== INSTALLATION ZWAVEJS2MQTT ================================="
echo "${red}================================================================================${normal}"

echo "------------------------- Stopping Zwavejs2MQTT..."
sudo systemctl stop zwavejs2mqtt

echo "Creation backup de la configuration..."
#cp -R data data-backup

echo "------------------------- suppression du rep zigbee2mqtt d'origine"
rm -r -f /opt/zwavejs2mqtt

# Setup Node.js repository
sudo curl -sL https://deb.nodesource.com/setup_14.x | sudo -E bash -

echo "------------------------- Install Node.js"
sudo apt-get install -y nodejs git make g++ gcc

echo "${green}================================================================================${normal}"
echo "------------------------- Contrôle des version de nodeJS et npm ..."
node --version  # Should output v10.X, v12.X, v14.X or v15.X
npm --version  # Should output 6.X or 7.X

echo "${red}================================================================================${normal}"
echo "------------------------- INSTALLATION DE ZWAVEJS2MQTT -------------------------"

echo "------------------------- Clone zwavejs2mqtt repository"
sudo git clone https://github.com/zwave-js/zwavejs2mqtt /opt/zwavejs2mqtt
cd /opt/zwavejs2mqtt
npm install
npm run build
npm start

echo "------------------------- PARAMETRAGE du lancement automatique de zwavejs2mqtt -------------------------"
file_edit="/etc/systemd/system/zwavejs2mqtt.service"

[Unit]
Description=zwavejs2mqtt
After=network.target

[Service]
ExecStart=/usr/bin/npm start
WorkingDirectory=/opt/zwavejs2mqtt
StandardOutput=inherit
StandardError=inherit
Restart=always
User=root

[Install]
WantedBy=multi-user.target  > $file_edit

echo "${red}================================================================================${normal}"

sudo nano $file_edit

echo "------------------------- inscription au démarrage -------------------------"
systemctl enable zwavejs2mqtt.service

echo "------------------------- Démarrage du service -------------------------"
systemctl start zwavejs2mqtt

echo "------------------------- FIN INSTALLATION DE ZWAVEJS2MQTT -------------------------"
echo "${red}================================================================================${normal}"


