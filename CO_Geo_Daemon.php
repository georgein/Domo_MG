<?php
/**********************************************************************************************************************
Geo_Daemon - 203
Enregistre le flux des nouveaux points de géolocalisation
Ne traite pas :
-	Si doublon de position.
-	Les points trop fréquent (< $timingEntrePoint sec).
-	Relance le tracking JC si pas de point depuis plus de $timingRelanceJC sec.
-	Lance le calcul d'affichage 'Geofence' si plus de $refreshCalcul sec depuis le dernier refresh.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
	//	$equipPresence, $equipJC

// N° des scénarios :
	$scenGeofence = 123;

//Variables :
	$tabGeoDaemon = mg::getVar('tabGeoDaemon');

	$timingRelanceJC = 90;			// Durée avant relance service/tracking de JC si pas de nouvelle
	$timingEntrePoint = 9;			// Intervalle de temps minimum (en sec) entre chaque point

	$refreshCalcul = 60;			// Période de rafraichissement MINIMUM du recalcul du HTML
	$homeSSID = ' Livebox-MG';		// Valeur contenue dans le SSID deS 'HOME'
	$formatDate = 'Y-m-d H:i:s';

// Paramètres :
	$latitudeHome = round(mg::getConfigJeedom('core', 'info::latitude'), 5);
	$longitudeHome = round(mg::getConfigJeedom('core', 'info::longitude'), 5);
	$altitudeHome = round(mg::getConfigJeedom('core', 'info::altitude'), 1);

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
// INIT
if (mg::declencheur('Tel-')) $user = mg::declencheur('', 2);
else $user = 'Tel-NR'; // Pour appel manuel 'user'
$equipJC = "#[Sys_Comm][$user]#";

$cpt = 0;
$distEcart = 0;
$values = array();

// Init des 'old' de l'appel précédent
$oldDatePosition = $tabGeoDaemon[$user]['oldDatePosition'];
$oldLatitude = $tabGeoDaemon[$user]['oldLatitude'];
$oldLongitude = $tabGeoDaemon[$user]['oldLongitude'];
$oldSSID = $tabGeoDaemon[$user]['oldSSID'];

$idUserJC = trim(mg::toID($equipJC, "Position"), '#');
$idEtatWIFI = trim(mg::toID($equipJC, 'Etat Wifi'), '#');
$idSSID = trim(mg::toID($equipJC, 'Réseau wifi (SSID)'), '#');

// Lecture des dernières position depuis $oldDatePosition
$sql = "SELECT * FROM `history` WHERE (`cmd_id` = '$idUserJC' AND `datetime` > '$oldDatePosition') ORDER BY `datetime` ASC";
mg::message('', $sql);
$result = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);

// Parcours et lecture des dernières positions
foreach ($result as $userEach => $detailsUser) {
	$datetime = $detailsUser['datetime'];
	$value = $detailsUser['value'];
	$newLatlngs = explode(',', $value);

	$latitude = round($newLatlngs[0], 5);
	$longitude = round($newLatlngs[1], 5);
	$altitude = round($newLatlngs[2], 1);
	$activite = $newLatlngs[3];
	$batterie = $newLatlngs[4];

	// ************************************** ON PASSE si écart temps trop court **************************************
if (!$oldDatePosition) $oldDatePosition = $datetime;
	$intervalleTemps = abs(strtotime($oldDatePosition) - strtotime($datetime));
	if ($intervalleTemps < $timingEntrePoint) continue;

	// *************************************** ON PASSE si doublon de position ****************************************
	$distHome = round(mg::getDistance($latitudeHome, $longitudeHome, $latitude, $longitude, 'k'), 1);
	if ($oldLatitude == $latitude && $oldLongitude == $longitude) continue;

	// ********************************************** Lecture Etat WIFI	***********************************************
	$sql = "SELECT * FROM `history` WHERE (`cmd_id` = '$idEtatWIFI' AND `datetime`>= '$datetime') ORDER BY `datetime` ASC LIMIT 1";
	$result = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
	$etatWIFI = $result[0]['value'];

	// ************************************************* Lecture SSID *************************************************
	if ($etatWIFI) {
		$sql = "SELECT * FROM `history` WHERE (`cmd_id` = '$idSSID' AND `datetime`>= '$datetime') ORDER BY `datetime` ASC LIMIT 1";
		$result = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
		$SSID = $result[0]['value'];
	} else {
		$SSID = '';
	}

	// *************************************** ON PASSE si doublon de SSID ****************************************
	if ($SSID != '' && $SSID == $oldSSID) continue;

	// **************************************************** SI AT HOME ****************************************************
	if ($distHome < 0.15 || strpos(" $SSID", $homeSSID) !== false) {
		$latitude = $latitudeHome;
		$longitude = $longitudeHome;
		$altitude = $altitudeHome;
		$activite = 'still';
		$distHome = 0.0;
		mg::unsetVar("_OldDist_$user");
	}

	++$cpt;
	// *********************************************** ENREGISTREMENT EN BDD **********************************************

	if ($distHome > 0) $altitude = round(mg::getAltitude($latitude, $longitude), 1);
	if ($oldLatitude > 0) $distEcart = round(mg::getDistance($oldLatitude, $oldLongitude, $latitude, $longitude, ''), 1);

	$idUser = trim(mg::toID($equipPresence, "Position_$user"), '#');
	$newValue = "$latitude,$longitude,$altitude,$batterie,$SSID,$activite,$distHome,$distEcart";
	$sql = "INSERT INTO `history` (cmd_id, datetime, value) VALUES ('$idUser', '$datetime', '$newValue') ON DUPLICATE KEY UPDATE value='$newValue'";
	mg::message('', $sql);
	$result = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);

	mg::messageT('', "! ($cpt) $user => Enreg. du " . substr(date($datetime), - 8) . " - SSID : '$SSID' - EcartDist. : $distEcart m / dist: $distHome km -  alt. : $altitude m - activité : $activite");

	// Mémo des 'old' pour la boucle suivante
	$oldDatePosition = $datetime;
	$oldLatitude = $latitude;
	$oldLongitude = $longitude;
	$oldSSID = $SSID;
}

if ($latitude > 0) {
	// *********************************************** REFRESH TRACKING JC ************************************************
	if (time() - strtotime($datetime) > $timingRelanceJC && !$SSID) {
		mg::setCmd($equipJC, 'Modifier Préférences Appli', 'ON', 'tracking');
	}
	// Mémo des 'old' pour l'appel suivant
	$tabGeoDaemon[$user]['oldDatePosition'] = $datetime;
	$tabGeoDaemon[$user]['oldLatitude'] = $latitude;
	$tabGeoDaemon[$user]['oldLongitude'] = $longitude;
	$tabGeoDaemon[$user]['oldSSID'] = $SSID;
	mg::setVar('tabGeoDaemon', $tabGeoDaemon);
}

// ************************************************* APPEL GEOFENCE ***************************************************
if ($cpt > 0) {
	mg::setScenario($scenGeofence, 'start', "userAppel=$user");
	if (mg::getVar("dist_$user") != $distHome) mg::setVar("dist_$user", $distHome); // Mémo distance Home pour Alarme_Présence
}
?>