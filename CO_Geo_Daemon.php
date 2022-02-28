<?php
/**********************************************************************************************************************
Geo_Daemon - 203
Enregistre le flux des nouveaux points de géolocalisation (METTRE multi taches à 'OUI')
Lance le calcul d'affichage 'Geofence' si plus de $refreshCalcul sec depuis le dernier refresh
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
	//	$equipMapPresence, $equipPresence

// N° des scénarios :

// N° des scénarios :
	$scenGeofence = 123; 

//Variables :
	$refreshCalcul = 60;					// Période de rafraichissement MINIMUM du recalcul du HTML
	$homeSSID = ' Livebox-MG';				// Valeur contenue dans le SSID deS 'HOME'

// Paramètres :
	$tabGeofence = mg::getVar('tabGeofence');
	$latitudeHome = round(mg::getConfigJeedom('core', 'info::latitude'), 5);
	$longitudeHome = round(mg::getConfigJeedom('core', 'info::longitude'), 5);
	$altitudeHome = round(mg::getConfigJeedom('core', 'info::altitude'), 1);
	$PositionHome = $latitudeHome.','.$longitudeHome.','.round($altitudeHome);
	$tabUser = mg::getTabSql('_tabUsers');
/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$userAppel = mg::declencheur('', 2);
if (!mg::declencheur('Tel-')) $userAppel = 'Tel-NR'; // Pour appel manuel 'user'

// ******************** Calcul SSID ********************
//$etatWifi = mg::getCmd("#[Sys_Comm][$userAppel][Etat Wifi]#");
$GeofenceSSID = 'PAS de SSID';
$GeofenceSSID = mg::getCmd("#[Sys_Comm][$userAppel][Réseau wifi (SSID)]#", $collectDate, $valueDate);

// Relance 'tracking' de JC si changement de connection
if (/*mg::declencheur('Etat Wifi') ||*/ mg::declencheur('SSID') /*&& $GeofenceSSID == 'PAS de SSID'*/) {
	$equipJC = "#[Sys_Comm][$userAppel]#";
	mg::message($logTimeLine, "Chgmt SSID JC - Reactivation tracking JC de $userAppel.");
	mg::setCmd($equipJC, 'Modifier Préférences Appli', 'OFF', 'tracking'); usleep(0.5 * 1000000);
	mg::setCmd($equipJC, 'Modifier Préférences Appli', 'ON', 'tracking');
}

// ******************** Calcul positionJC et $dist ********************
$PositionJeedomConnect = mg::getCmd("#[Sys_Comm][$userAppel][Position]#", '', $collectDate, $valueDate);
$latlngs = explode(',', $PositionJeedomConnect);
$distanceMax = floatval($tabUser["$userAppel"]['geo']);
$dist = round(mg::getDistance($latitudeHome, $longitudeHome, $latlngs[0], $latlngs[1], 'k'), 2);
if (mg::getVar("dist_$userAppel") != $dist) mg::setVar("dist_$userAppel", $dist);
$latlngs[2] = round(mg::getAltitude($latlngs[0], $latlngs[1]), 0);	
$PositionJeedomConnect = $latlngs[0].','.$latlngs[1].','.$latlngs[2];

	//  ******************** SI at HOME ********************
	if (strpos(" $GeofenceSSID", $homeSSID) !== false) {
		$latlngs = explode(',', $PositionHome);
		mg::setVar("dist_$userAppel", -1);
		mg::unsetVar("_OldDist_$userAppel");
	}
//}

// *********************************************** ENREGISTREMENT EN BDD **********************************************
	SetPoint($tabGeofence, $userAppel, $valueDate, $GeofenceSSID, $homeSSID, $latlngs, $dist, $equipMapPresence);

// **** ON appelle Geofence si plus de $refreshCalcul secondes depuis dernier point  ou changement de connection ******
$lastCalcul = mg::getVar("_GeoLastRun_$userAppel");
if ((time() - $lastCalcul) > $refreshCalcul || mg::declencheur('SSID') || mg::declencheur('Etat Wifi')) {
	mg::setScenario($scenGeofence, 'start', "userAppel=$userAppel");
	mg::setVar("_GeoLastRun_$userAppel", time());
}

// ********************************************************************************************************************
// ******************************************* ENREGISTREMENT DU NOUVEAU POINT DANS LA COMMANDE ***********************
// ********************************************************************************************************************
function setPoint($tabGeofence, $userAppel, $valueDate, $GeofenceSSID, $homeSSID, $latlngs, $dist, $equipMapPresence) {
	$values = array();
	$formatDate = 'Y-m-d H:i:s';
	$batteryJC = mg::getCmd("#[Sys_Comm][$userAppel][Batterie]#"); 
	$ActiviteJC =  mg::getCmd("#[Sys_Comm][$userAppel][Activité]#");
	$PositionJeedomConnect =  $latlngs[0].','. $latlngs[1].','. $latlngs[2];
	$altitude = $latlngs[2];
	
	//  ******************** SI at HOME ********************
	if (strpos(" $GeofenceSSID", $homeSSID) !== false) $ActiviteJC = 'still';

	// Calcul de la chaine finale 'newValue' à mémoriser
	$newValue = "$PositionJeedomConnect,$batteryJC,$GeofenceSSID,$ActiviteJC,$dist";
	$idUser = trim(mg::toID($equipMapPresence, $userAppel), '#');
	$valueDateTxt = date($formatDate, $valueDate);

	mg::messageT('', "! User : $userAppel => Enreg. d'un point au $valueDateTxt - SSID : '$GeofenceSSID' - Dist. : $dist km -  alt. : $altitude m - Activité : $ActiviteJC");

	// Enregistrement position courante
	$sql = "INSERT INTO `history` (cmd_id, datetime, value) VALUES ('$idUser', '$valueDateTxt', '$newValue') ON DUPLICATE KEY UPDATE value='$newValue'";
//	mg::message('', $sql);
	$result = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);

return;
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Suppression doublon position courante

	if ($tabGeofence[$userAppel]['cloture'] > 0) { $dateMin = $tabGeofence[$userAppel]['cloture']; }
	else { $dateMin = $tabGeofence[$userAppel]['debTime']; }
	$DateMinTxt = date($formatDate, $dateMin);

	$sql = "DELETE from `history` WHERE `cmd_id` = '$idUser' AND `value` REGEXP '$position' and `datetime` < '$valueDateTxt' and `datetime` > '$DateMinTxt'   ORDER BY `datetime` DESC limit 1";
	mg::message('', $sql);
	$result = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);

	if (count($result) > 0 ) 	mg::message('Log:/_JC', "$userAppel - ".count($result)." suppression de doublon : $sql");
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
}

?>