<?php
/**********************************************************************************************************************
Geo_Daemon - 203
Enregistre le flux des nouveaux points de géolocalisation (METTRE multi taches à 'OUI')
Lance le calcul d'affichage 'Geofence' si plus de $refreshCalcul sec depuis le dernier refresh
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
	//	$equipMapPresence

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

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$userAppel = 'MG';

// Si déclencheur JeedomConnect
if (mg::declencheur('Position')) { 
	$userAppel = str_replace('Tel-', '', mg::declencheur('', 2)); 
}

	// Si déclencheur SSID
elseif (mg::declencheur('SSID')) {
	$userAppel = str_replace('variable(', '', mg::declencheur('', 1));
	mg::message('', mg::declencheur('SSID').' - '.$userAppel);
	$userAppel = str_replace('_SSID)', '', $userAppel);
}

if ($userAppel != '') {
// ******************************** GESTION DES DECLENCHEURS ET ENREGISTREMENT EN BDD *********************************

	$PositionJeedomConnect = mg::getCmd("#[Sys_Comm][Tel-$userAppel][Position]#", '', $collectDate, $valueDate);
	$tmp = explode(',', $PositionJeedomConnect);
	$PositionJeedomConnect =  $tmp[0].','. $tmp[1].','. $tmp[2];
	//$AltitudeJeedomConnect =  $tmp[2]; 
	$ActiviteJeedomConnect =  $tmp[3];
	$GeofencepcBat = $tmp[4];
	
	$GeofenceSSID = mg::getVar($userAppel.'_SSID', 'Pas de SSID');

	// DECLENCHEUR SSID
	if (mg::declencheur('SSID')) {
		$valueDate = time();
		//  SI at HOME
		if (strpos(" $GeofenceSSID", $homeSSID) !== false) {
			$latLng_Home = $latitudeHome.','.$longitudeHome.','.round($altitudeHome);
			if (mg::declencheur('SSID')) $PositionJeedomConnect = $latLng_Home; 
			$ActiviteJeedomConnect = 'still';
			mg::setVar("dist_Tel-$userAppel", -1);
			mg::unsetVar("_OldDist_$userAppel");
		}
		// En WIFI mais pas at home et au repos
		else if ($ActiviteJeedomConnect ==  'still') {
			$ActiviteJeedomConnect = "$ActiviteJeedomConnect, org_SSID";
		}
	}

	// **************************************** ENREGISTREMENT DU NOUVEAU POINT ***************************************
	if (mg::declencheur('Position') || mg::declencheur('SSID')) {
			SetPoint($tabGeofence, $userAppel, $valueDate, $PositionJeedomConnect,$GeofencepcBat,$GeofenceSSID,$ActiviteJeedomConnect, $equipMapPresence);
	}
}

// ******************* ON appelle Geofence si plus de $refreshCalcul secondes depuis dernier point ********************
$lastCalcul = mg::getVar("_GeoLastRun_$userAppel");
if ((time() - $lastCalcul) > $refreshCalcul) { 
	mg::setScenario($scenGeofence, 'start', "userAppel=$userAppel"); 
	mg::setVar("_GeoLastRun_$userAppel", time());
}

// ********************************************************************************************************************
// ******************************************* ENREGISTREMENT DU NOUVEAU POINT DANS LA COMMANDE ***********************
// ********************************************************************************************************************
function setPoint($tabGeofence, $user, $valueDate, $PositionJeedomConnect, $GeofencepcBat, $GeofenceSSID, $ActiviteJeedomConnect, $equipMapPresence) {
	$values = array();
	$formatDate = 'Y-m-d H:i:s';

	$latlngs = explode(',', $PositionJeedomConnect);
	$position = $latlngs[0].','.$latlngs[1];

	//$altitude = mg::getAltitudeGoogle($position);	// Calibrage de l'altitude avec Google /////////////////////////////////
	$altitude = round(mg::getAltitude($latlngs[0], $latlngs[1]),1);	// Calibrage de l'altitude avec Geoservice /////////////
	$PositionJeedomConnect = $latlngs[0].','.$latlngs[1].','.$altitude;

	$newValue = "$PositionJeedomConnect,$GeofencepcBat,$GeofenceSSID,$ActiviteJeedomConnect";
	$idUser = trim(mg::toID($equipMapPresence, $user), '#');
	$valueDateTxt = date($formatDate, $valueDate);

	mg::messageT('', "! User : $user => Enregistrement d'un point ".mg::declencheur('', 2)." au $valueDateTxt $GeofenceSSID - altitude : $altitude");

	// Enregistrement position courante
	$sql = "INSERT INTO `history` (cmd_id, datetime, value) VALUES ('$idUser', '$valueDateTxt', '$newValue') ON DUPLICATE KEY UPDATE value='$newValue'";
//	mg::message('', $sql);
	$result = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Suppression doublon position courante
		
	if ($tabGeofence[$user]['cloture'] > 0) { $dateMin = $tabGeofence[$user]['cloture']; }
	else { $dateMin = $tabGeofence[$user]['debTime']; }
	$DateMinTxt = date($formatDate, $dateMin);

	$sql = "DELETE from `history` WHERE `cmd_id` = '$idUser' AND `value` REGEXP '$position' and `datetime` < '$valueDateTxt' and `datetime` > '$DateMinTxt'   ORDER BY `datetime` DESC limit 1";
	mg::message('', $sql);
	$result = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
	
if (count($result) > 0 ) 	mg::message('Log:/_JC', "$user - ".count($result)." suppression de doublon : $sql");

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
}

?>