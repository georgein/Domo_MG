echo "${red}================================================================================${normal}"
echo "------------------------- INSTALLATION DE MOSQUITO -------------------------"

wget http://repo.mosquitto.org/debian/mosquitto-repo.gpg.key
sudo apt-key add mosquitto-repo.gpg.key
rm mosquitto-repo.gpg.key
cd /etc/apt/sources.list.d/
sudo wget http://repo.mosquitto.org/debian/mosquitto-jessie.list
sudo apt-get update
sudo apt-get install mosquitto


echo "${green}================================================================================${normal}"
echo "------------------------- Creation du fichier de config de Mosquito"

file_edit="/etc/mosquitto/conf.d/frugal.conf"

echo "
listener 1883
listener 61614
protocol websockets" > $file_edit

echo "------------------------- FIN INSTALLATION DE MOSQUITO -------------------------"
echo "${red}================================================================================${normal}"

sudo nano $file_edit
