<?php
/**********************************************************************************************************************
Geofence - 123
Effectu un suivi des entrainements et des trajets voiture via une carte en HTML
**********************************************************************************************************************/
global $tabActivite, $pathGeofence, $lgnActivite, $IP_Jeedom, $API_Jeedom, $equipMapPresence, $tabUser, $tabGeofence, $dateSQL, $userAppel, $currentLatLngUserAppel, $latitudeHome, $longitudeHome, $altitudeHome, $homeSSID, $destinatairesSOS, $destinataires, $pathRef, $fileExport, $SSID, $latLng, $nbLignes, $pathFileExport, $colorUser, $styleUser, $typeGPX, $zone;
// Paramètres
global $layerDefaut, $epaisseur, $sizePoint, $pauseSize, $colorVoiture, $refresh, $timingSOS, $coeffDist, $intervalleMin, $recul, $pauseMin, $coeffDenivMoins, $distMin, $distMax, $nbPointsMaxAff, $scenSynthese;


// Infos, Commandes et Equipements :
//	$equipMapPresence

// N° des scénarios :
	$scenSynthese = 202;
//Variables :
	$tabActivite = '_tabActivites';

	$tabUser = mg::getTabSql('_tabUsers');
	$tabGeofence = mg::getVar('tabGeofence');

	$pathRef = mg::getParam('System', 'pathRef');	// Répertoire de référence de domoMG
	$pathGeofence = (getRootPath() . "$pathRef/geofence/"); // Répertoire de geofence
	$fileExport = getRootPath() . "$pathRef/util/geofence.html";

	$homeSSID = ' Livebox-MG';				// Valeur contenue dans le SSID deS 'HOME'
	$destinatairesSOS = "Log, SMS:@MG, Mail:MG";// Destinataires du SOS
	$destinataires = 'Log, TTS:GOOGLECAST';	// Destinataire du message d'annonce de proximité

	$layerDefaut = 'GeoportailFrance_plan';	// Nom du layer par defaut OpenTopoMap, GeoportailFrance_maps, OpenStreetMap_France
	$epaisseur = 3;							// Epaisseur de la trace
	$sizePoint = 10;						// Taille du marqueur de position voiture/entrainement
	$pauseSize = 15;						// Taille des icones de marquage trajet
	$colorVoiture = 'blue';

	$refresh = 5;							// Période de contrôle de rafraichissement de la page HTML en seconde
	$timingSOS = 30;						// Durée max de pause 'normale' EN MINUTE en Entrainement au delà de laquelle on envoi un SOS
	$coeffDist = 0.8;						// Annonce de proximité faite si DistCouranteUser * $CoeffDist < OldDistUser
	$intervalleMin = 5;						// Intervalle minimum en sec d'affichage entre deux points en voiture
	$recul = 1;								// Recul pour le calcul de l'affichage des pentes
	$pauseMin = 3;							// Durée minimum de la Pause en mn pour l'afficher
	$coeffDenivMoins = 0.3;					// Coeff sur le dénivelé moins pour calculer les Km Effort

	$distMin = 100;							// Distance min du wayPoint pour nommer la rando
	$distMax = 20000; 						// Distance max du wayPoint pour nommer la rando

	$nbPointsMaxAff = 1500;					// Nombre de points maximum affichable

// Paramètres :
	$latitudeHome = round(mg::getConfigJeedom('core', 'info::latitude'), 5);
	$longitudeHome = round(mg::getConfigJeedom('core', 'info::longitude'), 5);
	$altitudeHome = round(mg::getConfigJeedom('core', 'info::altitude'), 1);


	$IP_Jeedom = mg::getConfigJeedom('core', 'jeedom::url');
	$API_Jeedom = mg::getConfigJeedom('core');
	$logTimeLine = mg::getParam('Log', 'timeLine');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
//	$userAppel = trim(mg::getTag('userAppel'), '#');

	$typeGPX = '';
	$buffTmp = 0;
	$values = array();
	$result = array();
	$lastLatLng = '';
	$dateSQLTest = '';
	$dateSQL = date('Y\-m\-d', time()); // Date du jour au format SQL

// ***************************************** CALCUL DU USER APPEL VIA DAEMON ******************************************
if (mg::declencheur('scenario')) {
	$userAppel = mg::getTag('userAppel');
}

// Si déclencheur user (manuel POUR DEBUG)
elseif (!mg::declencheur('Tel-')) {
	$userAppel = 'Tel-NR';
	//$dateSQLTest = '2011-02-18';
}

// *********************************** BOUCLE SUR TOUS LES USERS DE TYPE 'geofence' ***********************************
foreach ($tabUser as $user => $detailsUser) {
	if ($detailsUser['geo'] == 0) continue;

	// Style pour bornes kilomètriques
	$styleUser = $styleUser."\n	.borneKm$user { border-color: ".$detailsUser['colorGeofence']."; }";

	if (mg::declencheur('user') || $user == $userAppel || @$tabGeofence[$user]['dateActive'] != $dateSQL || mg::getVar("tabGeofence_L_$user", '') == '') {
		calculUser($user, $dateSQL, $dateSQLTest);
	} else {
		unset($tabGeofence[$user]);
	}
}

if ($userAppel == '') $userAppel = $user;
// ********************************************************************************************************************
mg::setVar('tabGeofence', $tabGeofence);
$latLng_ = explode(',', mg::getCmd("#[Sys_Comm][$userAppel][Position]#"));
$currentLatLngUserAppel = $latLng_[0] . ', ' . $latLng_[1];
mg::messageT('', "! GENERATION du HTML de GEOFENCE, userAppel $userAppel pour le $dateSQL (currentLatLngUserAppel : $currentLatLngUserAppel)");

file_put_contents($fileExport, HTML($userAppel));
mg::setVar('_geofenceOK', time());
mg::setVar("_GeoLastRun_$userAppel", time());

mg::message('', print_r($tabGeofence, true));

// ********************************************************************************************************************
// ******************************************************* CALCUL USER ************************************************
// ********************************************************************************************************************
function calculUser($user, $dateSQL, $dateSQLTest) {
	global $equipMapPresence, $tabUser, $tabGeofence, $userAppel, $dateSQL, $latitudeHome, $longitudeHome, $destinataires, $refresh, $coeffDist, $fileExport, $latLng, $nbLignes;

	mg::unsetVar("tabGeofence_L_$user");
	mg::unsetVar("tabGeofence_C_$user");
	$idUser = trim(mg::toID($equipMapPresence, $user), '#');

	// ********************************************* MAJ WIDGET GOOGLE MAP ********************************************
	$latLng_ = explode(',', mg::getCmd("#[Sys_Comm][$user][Position]#"));
	$tmp = mg::getParamWidget($idUser, '');
	$tmp['from'] = $latLng_[0] . ', ' . $latLng_[1];
	mg::setParamWidget($idUser, '', $tmp);
	$tmp['to'] = $latitudeHome . ', ' . $longitudeHome;
	mg::setParamWidget($idUser, '', $tmp);

	// ***************************************** RECALCUL DES DATES DE TRAVAIL ****************************************
	if (mg::declencheur('dateGeofence')) $tabGeofence[$user]['dateActive'] = mg::getVar('dateGeofence', $dateSQL);

	if (mg::declencheur('user') && $dateSQLTest != '') { $dateSQL = $dateSQLTest; }
	elseif (mg::declencheur('scenario')) { $tabGeofence[$user]['dateActive'] = $dateSQL; }
	elseif (isset($tabGeofence[$user]['dateActive'])) { $dateSQL = $tabGeofence[$user]['dateActive']; }
	mg::messageT('', "TRAITEMENT de $user POUR LE $dateSQL");

	calculDetailsUser($user, $dateSQL, $idUser);
	mg::messageT('', "! User : $user - Nb de points affichés : $nbLignes - debTime : " . date('d\/m \à H\hi\m\ns\s', $tabGeofence[$user]['debTime']) . " - Cloture : " . date('d\/m \à H\hi\m\ns\s', $tabGeofence[$user]['cloture'])." - DateSQL : $dateSQL");

	// ************************************* SI APPEL PAR CHANGEMENT COORDONNEES **************************************
	if (mg::declencheur('scenario')) {
		$dist = mg::getDistance($latitudeHome, $longitudeHome, $latLng_[0], $latLng_[1], 'k');

		// ------------------------------------------- SIGNALEMENT PROXIMITE ------------------------------------------
		if ($user == $userAppel) {
mg::messageT('', "Approche de $user à $dist");
			mg::setVar("dist_$user", $dist);
			$distanceMax = floatval($tabUser[$user]['geo']);
			$oldDist = mg::getVar("_OldDist_$user", $dist);
mg::Message('', "+++ $user == $userAppel - max : $distanceMax - dist : $dist - old : $oldDist - ");
			if (abs($dist-$oldDist) > $distanceMax && $dist < $oldDist * $coeffDist && $dist > $distanceMax) {
				$distTxt = str_replace('.', ',', round($dist, 1));
				mg::message($destinataires, $user[4].' '.$user[5]." en approche à $distTxt kilomètres.");
				mg::setVar("_OldDist_$user", $dist);
			} else {
				mg::setVar("_OldDist_$user", max($dist, $oldDist));
			}
		}
	}
}

// ********************************************************************************************************************
// ************************************************ Extrait les LatLong de l'historiques ******************************
// ********************************************************************************************************************
function calculDetailsUser($user, $dateSQL, $idUser) {
	global $scenario;
	global $tabGeofence, $lgnActivite, $homeSSID, $destinatairesSOS, $timingSOS, $destinataires, $pauseMin, $intervalleMin, $SSID, $nbLignes, $pathGeofence, $equipMapPresence, $coeffDenivMoins, $latitudeHome, $longitudeHome, $altitudeHome, $nbPointsMaxAff, $typeGPX, $scenSynthese;

	$values = array();
	$result = array();

	$codeUser = trim(str_replace('Tel-', '', $user), '_'); //////////////////////////////////////
	$fileGPX = $pathGeofence."/histo_$codeUser/$dateSQL.gpx";

	$tabGeofence[$user]['dureeMouvement'] = 0;
	$tabGeofence[$user]['debTime'] = 0;
	$tabGeofence[$user]['cloture'] = 0;
	$tabGeofence[$user]['Km_E'] = 0;
	$tabGeofence[$user]['Km_V'] = 0;
	$tabGeofence[$user]['dureeTotale'] = 0;
	$tabGeofence[$user]['denivelePlus'] = 0;
	$tabGeofence[$user]['deniveleMoins'] = 0;
	$tabGeofence[$user]['sommePause'] = 0;
	$tabGeofence[$user]['SSID_Org'] = 'xxx';

	$datetime = 0;
	$pcBatterie = 0;
	$tabGeofence_L = '';
	$tabGeofence_C = '';
	$ecart = 0;
	$dureeEcart = 0;
	$altitude = 0;
	$ecartAltitude = 0;
	$vitesseEcart = 0;
	$vitesseAltitude = 0;
	$dureePause = 0;
	$deltaI_Pause = 0;
	$debPause = 0;
	$offsetAltitude = 0;
	$lastCloture = 0;
	$nbLignes = 0;
	$nbLignesGPX = 0;
	$lastPointAffichage  = 0;

	// ********************************************* FIN D'INITIALISATION *********************************************

	$sql = "
	SELECT *
		FROM (
			SELECT *
				FROM history
				WHERE cmd_id=$idUser
				AND `datetime` LIKE '%$dateSQL%'
	UNION ALL
			SELECT *
				FROM historyArch
				WHERE cmd_id=$idUser
				AND `datetime` LIKE '%$dateSQL%'
	) as dt
ORDER BY `datetime` ASC
";

	mg::message('', $sql);
	$result = DB::Prepare($sql, (array)$values, DB::FETCH_TYPE_ALL);

	// Si pas date du jour et .gpx existe on le récupère
	 if (($dateSQL != date('Y\-m\-d', time())) && file_exists($fileGPX)) {
		$result = getGPX($user, $fileGPX);
	}
	mg::messageT('', "Parcours des positions du jour ".count($result)." points réduits à $nbPointsMaxAff points maximum");
	if (!is_countable($result)) return;
	for($i=0; $i<count($result); $i++) {
		$inhibe = 0;

		// ************************************* VALEURS ET CALCULS LIGNE SUIVANTE ************************************
		$datetime_P1 = get('datetime', $result, $i+1);
		// ************************************* VALEURS ET CALCULS LIGNE COURANTE ************************************
		$datetime = get('datetime', $result, $i);

		if ($datetime == 0) $datetime = strtotime($dateSQL)+10*$i; // Pour les gpx sans horodatage

		$timeGPX = get('timeGPX', $result, $i);
		$results = explode(',', $result[$i]['value']);
		$latitude = get('latitude', $result, $i);
		$longitude = get('longitude', $result, $i);

		$latlng = get('latlng', $result, $i);
		$altitude = get('altitude', $result, $i);

		$pcBatterie = get('pcBatterie', $result, $i);
		$SSID = get('SSID', $result, $i);
		if ($SSID == 'Pas de SSID') $SSID = '';
		if ($SSID == 'PAS DE SSID') $SSID = '';

		$activite = get('activite', $result, $i);
		$activiteOrg = $activite;

		if ($i > 0) {
			$ecart = round(get('ecart', $result, $i), 3);
			$dureeEcart = get('dureeEcart', $result, $i);
			$vitesseEcart = get('vitesseEcart', $result, $i);

			$ecartAltitude = get('ecartAltitude', $result, $i);
			$vitesseAltitude = get('vitesseAltitude', $result, $i);

			// ******************************************* GESTION ANOMALIES ******************************************
			// ***** FORCE A 'I' SI SOUS WIFI *****
			//if (get('SSID', $result, $i-1) != '' && $SSID != '' && get('SSID', $result, $i+1) != '') $activite = 'I';

			// ANOMALIES ------------------------------ ANOMALIES HORS ENTRAINEMENTS ------------------------------
			if (($vitesseEcart > 6 && get('vitesseEcart', $result, $i-1) > 6 )) $activite = 'V';

			// ANOMALIES ------------------------------ ANOMALIES ENTRAINEMENTS EN COURS ------------------------------
			if ($tabGeofence[$user]['debTime'] > 0 && $tabGeofence[$user]['cloture'] == 0) {
				if ($vitesseEcart > 1 && $vitesseEcart <= 10) $activite = 'E'; // Elimination des 'in vehicul' indues
				if ($vitesseEcart < 1) $activite = 'I'; // Forçage des pauses
			}

			// ****************************************** ACTIVITE 'V'OITURE ******************************************
			if ($activite == 'V') {
				if ($vitesseEcart > 150 ) $inhibe = 1; else $tabGeofence[$user]['Km_V'] += $ecart;
			}

			// ****************************************** ACTIVITE 'I' PAUSE ******************************************
			if (strpos(" $SSID", $homeSSID) === false) {
				if ($activite == 'I') {
					// Début de pause
					if ($debPause == 0) {
						$debPause = get('datetime', $result, $i-1);;
						$dureePause = round(($datetime_P1 - $debPause)/60, 1);
						$deltaI_Pause = 1;

					// Pause en cours
					} else {
						$dureePause = round(($datetime_P1 - $debPause)/60, 1);
						$deltaI_Pause++;

						// *************** ENVOIS D'UN SOS AUTOMATIQUE ***************
						if ($tabGeofence[$user]['debTime'] > 0 && $tabGeofence[$user]['cloture'] == 0 && $tabGeofence[$user]['Km_E'] > 1 && $dureePause > $timingSOS && $tabGeofence[$user]['alerteEnCours'] == 0 && abs(time() - $datetime) < 1*60) {
							mg::message($destinatairesSOS, "SOS AUTOMATIQUE de $user, Aucun mouvement depuis ".round($dureePause)." minutes, Coordonnées ($latlng). VOIR : $IP_Jeedom/mg/util/geofence.html");
							$tabGeofence[$user]['alerteEnCours'] = $datetime;
						}
					}
				// Fin de pause
				} elseif ($debPause > 0) {
						$dureePause = round(($datetime_P1 - $debPause)/60, 1);
						if ($dureePause >= $pauseMin) $tabGeofence[$user]['sommePause'] += $dureePause;
						$debPause = 0;
						$dureePause = 0;
						$deltaI_Pause = 0;
						$tabGeofence[$user]['alerteEnCours'] = 0;
				} // ********** FIN GESTION DES PAUSES **********
			}

			// *************************************** ACTIVITE 'E' ENTRAINEMENT **************************************
			if ($activite == 'E') {
				// ********** DEMARRAGE ENTRAINEMENT
				if ($SSID == '' && countActivites($i, $result, 'V', 30) < 3 && ($tabGeofence[$user]['debTime'] <= 0 || $tabGeofence[$user]['cloture'] > 0)) {
					mg::message('', "************* DEMARRAGE entrainement");
					$tabGeofence[$user]['debTime'] = $datetime;
					$tabGeofence[$user]['cloture'] = 0;
					$tabGeofence[$user]['Km_E'] = 0;
					$tabGeofence[$user]['sommePause'] = 0;
					$tabGeofence[$user]['denivelePlus'] = 0;
					$tabGeofence[$user]['deniveleMoins'] = 0;
					$debPause = 0;
					$dureePause = 0;

					// En tête du GPX
					$gpx = "<?xml version='1.0' encoding='UTF-8'?> <gpx creator='Geofence MG'>\n<metadata> <name>\"".(isset($lgnActivite[$user]['name']) ? $lgnActivite[$user]['name'] : '*** en cours ***')."\"</name> <date>$dateSQL</date></metadata>\n<trk>\n<trkseg>";
					$gpx .= "\n<trkpt lat='$latitude' lon='$longitude'><ele>$altitude</ele><time>$timeGPX</time></trkpt>";

					if ($SSID != '' && $tabGeofence[$user]['debTime'] <= 0) { $tabGeofence[$user]['SSID_Org'] = $SSID; }
				}
			}

				// ********** ENTRAINEMENT EN COURS
			 if ($inhibe == 0 && $tabGeofence[$user]['debTime'] > 0 && $tabGeofence[$user]['cloture'] == 0) {
				if ($activite == 'E') {
					$tabGeofence[$user]['Km_E'] += $ecart;
					if ($ecartAltitude > 0) { $tabGeofence[$user]['denivelePlus'] += ($ecartAltitude); }
					if ($ecartAltitude < 0) { $tabGeofence[$user]['deniveleMoins'] += ($ecartAltitude); }
				}

				// Ligne GPX
				$gpx .= "\n<trkpt lat='$latitude' lon='$longitude'><ele>$altitude</ele><time>$timeGPX</time></trkpt>";
				$nbLignesGPX++;

				// ********** CLOTURE ENTRAINEMENT
				if ( $SSID != '' 
					|| ($i >= count($result)-1 && file_exists($fileGPX))
					|| strpos($tabGeofence[$user]['SSID_Org'], $SSID) !== false
					|| strpos($SSID, $tabGeofence[$user]['SSID_Org']) !== false
					|| countActivites($i, $result, 'V', 5) > 2 
					) 
				{
					$tabGeofence[$user]['cloture'] = $datetime;
					$tabGeofence[$user]['alerteEnCours'] = 0;
					$tabGeofence[$user]['SSID_Org'] = 'yyy';

					$gpx .= "\n</trkseg>\n";
					mg::setScenario($scenSynthese, 'start');
					mg::message('', "************* CLOTURE entrainement");
				}
			}

			// ************************* SUPPRESSION ENTRAINEMENT TROP COURT (< 1 km ou 5 mn) ************************
			if ($tabGeofence[$user]['cloture'] > 0 && $datetime +300 > $tabGeofence[$user]['cloture'] && ($tabGeofence[$user]['Km_E'] < 0.5)) {
				mg::message('', "************* Suppression entrainement trop court de $user : Distance : " . $tabGeofence[$user]['Km_E'].' - Durée : '.date('H:m:s', $tabGeofence[$user]['dureeTotale']));
				$dureePause = 0;
				$deltaI_Pause = 0;
				$tabGeofence[$user]['alerteEnCours'] = 0;
				$tabGeofence[$user]['debTime'] = 0;
				$tabGeofence[$user]['cloture'] = 0;
				$tabGeofence[$user]['dureeTotale'] = 0;
				$tabGeofence[$user]['sommePause'] = 0;
				$tabGeofence[$user]['Km_E'] = 0;
				$tabGeofence[$user]['denivelePlus'] = 0;
				$tabGeofence[$user]['deniveleMoins'] = 0;
				$dureePause = 0;
				$debPause = 0;
			}
		} // ******** FIN $i > 0 ********

		$message = date('d-m H:i:s', $datetime) . " $i ($nbLignes) $user - $SSID - $activiteOrg ($activite) - ".round($ecart, 4)." / ".round($tabGeofence[$user]['Km_E'], 2)." / ".round($tabGeofence[$user]['Km_V'], 1)." km - $dureeEcart sec - $vitesseEcart km/h - Pause : $dureePause / ".$tabGeofence[$user]['sommePause']." mn -  Alt $altitude m / ".$tabGeofence[$user]['denivelePlus'];

		// ******************************************** MEMO DES RESULTATS ********************************************
		if ($inhibe == 0) {

			// *********************** Réduction du nb de lignes à l'affichage à $nbPointsMaxAff **********************
			if (count($result) <= $nbPointsMaxAff || round(fmod($i, (count($result) / $nbPointsMaxAff))) == 0) {
				$tabGeofence_L .= "[$latlng,$datetime],";
				$tabGeofence_C .= "[".$tabGeofence[$user]['Km_E'].",$vitesseEcart,$altitude,'$activite',$pcBatterie,$dureePause,$deltaI_Pause,".$tabGeofence[$user]['Km_V']."],";
				$nbLignes++;
				$lastPointAffichage = $datetime;
			}

			// Gestion des logs
			if ($scenario->getConfiguration('logmode') == 'realtime') { mg::message('', "  $message"); }
		} else { mg::message('', "* $message"); }

	} // FIN boucle for des lignes

	// Points minimum pour le traçage du graphique
	if ($nbLignes < 3) {
		$tabGeofence_L = 	 "[$latitudeHome,$longitudeHome," . time()."],"
							."[$latitudeHome".'1'.",$longitudeHome," . (time()+1) ."],"
							."[$latitudeHome".'2'.",$longitudeHome," . (time()+2) ."],";
		$tabGeofence_C ="[0,0,0,'I',0,0.0,0,0],[0,0,0,'I',0,0.0,0,0],[0,0,0,'I',0,0.0,0,0],";
		$nbLignes = 3;
	}

	// Mémo des points de traçage pour le graphique
	mg::setVar("tabGeofence_L_$user", $tabGeofence_L);
	mg::setVar("tabGeofence_C_$user", $tabGeofence_C);
	mg::messageT('',"FIN DU TRAITEMENT de $user POUR LE $dateSQL");

	// Calcul des lastValues pour l'affichage du user
	$tabGeofence[$user]['lastTime'] = $datetime;
	$tabGeofence[$user]['lastPcBatterie'] = $pcBatterie;
	$tabGeofence[$user]['lastSSID'] = $SSID;

	// ********************************************** Finalisation du gpx *********************************************
	// Enregistrement si clôturé et Km_E > 1 et gpx inexistant
	if ($nbLignesGPX > 0 && $tabGeofence[$user]['cloture'] > 0 && $tabGeofence[$user]['Km_E'] > 1 && !file_exists($fileGPX)) {
		$gpx .= "</trk>\n</gpx>";
		file_put_contents($fileGPX, $gpx);
		mg::messageT('', "EXPORT du GPX de $user vers $fileGPX");
	}

	// **************************************** ENREGISTREMENT ACTIVITE DU JOUR ****************************************
	setActivites($user, $dateSQL, $fileGPX, $tabGeofence[$user]['Km_E'], $tabGeofence[$user]['Km_V']);

	// **************************** MEMO POUR AFFICHAGE  DE LA SYNTHESE DE L'ENTRAINEMENT *****************************
	if ($tabGeofence[$user]['debTime'] != 0) {
		$tabGeofence[$user]['dureeTotale'] = (($tabGeofence[$user]['cloture'] != 0) ? $tabGeofence[$user]['cloture'] : get('datetime', $result, $i-1)) - $tabGeofence[$user]['debTime'];
		$tabGeofence[$user]['dureeMouvement'] = $tabGeofence[$user]['dureeTotale'] - $tabGeofence[$user]['sommePause']*60;
	}
}

// ********************************************************************************************************************
// ********************************* RECUP DES VALEURS ET CALCULS POUR UN INDEX DONNE *********************************
// ********************************************************************************************************************
function get($value, $table, $index) {
	if ($index  < 0) return;
	$dureeEcart = 0;
	$tables = @explode(',', $table[$index]['value']);

	// valeurs lues
	if ($value == 'activite') return transpoActivite(@$tables[5]);
	elseif ($value == 'SSID') return trim($tables[4]) != '' ? trim($tables[4]) : '';
	elseif ($value == 'pcBatterie') return intval($tables[3]);

	elseif ($value == 'altitude') return round($tables[2], 0);
	elseif ($value == 'longitude') return $tables[1];
	elseif ($value == 'latitude') return $tables[0];
	elseif ($value == 'latlng') return round(get('latitude', $table, $index), 5).','.round(get('longitude', $table, $index), 5);

	// les dates
	elseif ($value == 'datetime') return @strtotime($table[$index]['datetime']);
	elseif ($value == 'timeGPX') return date('Y-m-d H:i:s', strtotime($table[$index]['datetime']));

	// Valeurs calculées
	elseif ($value == 'ecart') return mg::getDistance(get('latitude', $table, $index-1), get('longitude', $table, $index-1)
															, get('latitude', $table, $index), get('longitude', $table, $index)) / 1000;
	elseif ($value == 'dureeEcart') return get('datetime', $table, $index) - get('datetime', $table, $index-1);
	elseif ($value == 'vitesseEcart') {
		$dureeEcart = get('datetime', $table, $index) - get('datetime', $table, $index-1);
		return ($dureeEcart > 0 ? round(get('ecart', $table, $index) / $dureeEcart*3600, 1) : 0);
	}
	elseif ($value == 'ecartAltitude') return (get('altitude', $table, $index) > -999 && get('altitude', $table, $index-1) > -999 ? round(get('altitude', $table, $index) - get('altitude', $table, $index-1), 1) : 0);

	elseif ($value == 'vitesseAltitude') {
		$dureeEcart = get('datetime', $table, $index) - get('datetime', $table, $index-1);
		return  ($dureeEcart > 0 ? round(abs(get('ecartAltitude', $table, $index) / $dureeEcart), 3) : 0); // en m/s
	}
}

// ********************************************************************************************************************
// ******************************************************** TRANSPO DE L'ACTIVITE *************************************
// ********************************************************************************************************************
function transpoActivite($activite) {
			$activite = trim($activite);
			if ($activite == 'still')  return 'I';
			elseif ($activite == 'walking')  return 'E';
			elseif ($activite == 'on_foot')  return 'E';
			elseif ($activite == 'running')  return 'E';
			elseif ($activite == 'on_bicycle')  return 'E';
			elseif ($activite == 'in_vehicle')  return 'V';
}

// ********************************************************************************************************************
// ***************************************** RECUPERE / ENREGISTRE UNE ACTIVITE ***************************************
// ********************************************************************************************************************
function setActivites($user, $dateSQL, $fileGPX, $km_E=0, $km_V=0) {
	global $tabActivite, $tabGeofence, $coeffDenivMoins, $zone;
	$values = array();

	$codeUser = trim(str_replace('Tel-', '', $user), '_'); //////////////////////////////////////
	// Lecture de l'activité existante
	$lgnActivite = getActivites($codeUser, $dateSQL);

	// Récupération des km_x
	$km_E = round($km_E > 0 ? $km_E : $lgnActivite[$codeUser]['km_E'], 1);
	$km_V = round($km_V > 0 ? $km_V : $lgnActivite[$codeUser]['km_V']);

		$zone = '';
		$name = getNomGPX($codeUser, $fileGPX);
		$name = ($name != '' ? $name : $lgnActivite[$codeUser]['name']);
	// *********************************************** LECTURE DE L'IBP ***********************************************
	if (file_exists($fileGPX)) {

		$ibp = new IBP($fileGPX);
		$ibpindex_json = $ibp->ibp;
		$someArray = json_decode($ibpindex_json, true);
	}

		$Type = 'hiking';
		$IBP = (@$someArray[$Type]['ibp'] > 0 ? $someArray[$Type]['ibp'] : 0);
		$km_E = round(@$someArray[$Type]["totlengthkm"], 1);
		$deniv_P = round(@$someArray[$Type]["accuclimb"]);
		$deniv_M = round(@$someArray[$Type]["accudescent"]);
		$km_Effort = round($km_E + $deniv_P/100 + $deniv_M/100*$coeffDenivMoins, 1);
		$vit_Glob = round(@$someArray[$Type]["totalspeed"], 1);
		$vit_Mvmt = round(@$someArray[$Type]["averagespeed"], 1);
		$duree_Mvmt = (@$someArray[$Type]['movementtime'] != '' ? $someArray[$Type]['movementtime'] : 0);
		$duree_Pause = (@$someArray[$Type]['stoptime'] != '' ? $someArray[$Type]['stoptime'] : 0);
		$duree_Glob = (@$someArray[$Type]['totaltime'] != '' ? $someArray[$Type]['totaltime'] : 0);
		$reference = (@$someArray['reference'] != '' ? $someArray['reference'] : '');

	// ********************************************** MAJ DE L'ACTIVITE *********************************************
	if ($lgnActivite[$codeUser]['verrou'] != 1 && $km_E > 1 || $km_V > 1) {
		$sql = "INSERT INTO `$tabActivite` (user,datetime,name,zone,IBP,km_E,deniv_P,deniv_M,km_Effort,duree_Mvmt,vit_Mvmt,duree_Pause,duree_Glob,vit_Glob,km_V,reference)
				VALUES 	('$codeUser', '$dateSQL','$name','$zone','$IBP','$km_E','$deniv_P','$deniv_M','$km_Effort','$duree_Mvmt','$vit_Mvmt','$duree_Pause','$duree_Glob','$vit_Glob','$km_V','$reference')
				ON DUPLICATE KEY UPDATE name='$name',zone='$zone',IBP='$IBP',km_E='$km_E',deniv_P='$deniv_P',deniv_M='$deniv_M',km_Effort='$km_Effort',duree_Mvmt='$duree_Mvmt',vit_Mvmt='$vit_Mvmt',duree_Pause='$duree_Pause',duree_Glob='$duree_Glob',vit_Glob='$vit_Glob',km_V='$km_V',reference='$reference'";
		mg::message('', " +++ $sql");
		$result = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);

		// ******************* MàJ $tabGeofence **************
		if ($km_E > 1) $tabGeofence[$user]['urlIBP'] = "https://www.ibpindex.com/ibpindex/ibp_analisis_completo.php?REF=".@$someArray['reference']."&amp;LAN=fr&amp;MOD=HKG";

		mg::messageT('', "$user - $km_V - Calcul index IBP (".$lgnActivite[$codeUser]['IBP'].") au $dateSQL pour $name ($reference)");
	}
}

// ********************************************************************************************************************
// ****************************************** LIT LES DONNEES D'UNE ACTIVITE ******************************************
// ********************************************************************************************************************
function getActivites($user, $dateSQL, $name='') {
	global $tabActivite, $lgnActivite;
	$values = array();

	if ($name == '') {
	$sql = "SELECT *
		FROM `$tabActivite`
		WHERE `user` = '$user' AND `datetime` = '$dateSQL'
		ORDER BY `datetime` DESC
		LIMIT 1";
	} else {
	$sql = "SELECT *
		FROM `$tabActivite`
		WHERE `name` LIKE '%$name%'
		ORDER BY `datetime` DESC
		LIMIT 1";
	}

	$result = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
	if (count($result) == 0) return;

	$lgnActivite[$user] = $result[0];
	return $lgnActivite;
}

// ********************************************************************************************************************
// ************************************************** RECUP D'UN GPX **************************************************
// ********************************************************************************************************************
function getGPX($user, $fileGPX) {
	global $tabGeofence, $lgnActivite, $dateSQL;
	if (!file_exists($fileGPX)) return;

	$result = array();

	$index = 0;
	$obj = simplexml_load_file($fileGPX);

 	foreach($obj->trk->trkseg->trkpt as $trkpt) {
		$datetime = $trkpt->time;
		$result[$index]['datetime'] = $datetime;
		$result[$index]['value'] = round($trkpt['lat'], 7).','.round($trkpt['lon'], 7).','.round($trkpt->ele, 1).',99, ,walking';

		$index++;

		$datetime = str_replace('T', ' ', $datetime);
		$datetime = str_replace('.000Z', '', $datetime);
	}
	$lgnActivite = getActivites($user, $dateSQL);
	mg::messageT('', "Recup du .GPX $fileGPX de $user, nb de lignes ".count($result));
	return $result;
}

// ********************************************************************************************************************
// ********************************************** CALCUL LE NOM D'UN GPX **********************************************
// ********************************************************************************************************************
function getNomGPX($user, $fileGPX) {
	global $tabGeofence, $distMin, $distMax, $zone;
	$values = array();

	if (!file_exists($fileGPX)) return;
	$nomRando = '';
	$oldName = '';
	$cpt = 0;

	$lat_Min = 90;
	$lat_Max = -90;
	$lon_Min = 180;
	$lon_Max = -180;

	// ***** Recherche lat/lon min/max *****
		$obj = simplexml_load_file($fileGPX);
 	foreach(@$obj->trk->trkseg->trkpt as $trkpt) {
		$datetime = $trkpt->time;
		$latGPX = round($trkpt['lat'], 7);
		$lonGPX = round($trkpt['lon'], 7);

		$lat_Min = ($latGPX < $lat_Min ? $latGPX : $lat_Min);
		$lat_Max = ($latGPX > $lat_Max ? $latGPX : $lat_Max);
		$lon_Min = ($lonGPX < $lon_Min ? $lonGPX : $lon_Min);
		$lon_Max = ($lonGPX > $lon_Max ? $lonGPX : $lon_Max);
	}

	$sql = "
		SELECT nom, zone, latitude, longitude
		FROM `_tabWayPoints`
		WHERE 		`latitude` >= '$lat_Min'
				AND `latitude` <= '$lat_Max'
				AND `longitude` >= '$lon_Min'
				AND `longitude` <= '$lon_Max'
	";
	$result = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
	//mg::message('', $sql);
	$obj = simplexml_load_file($fileGPX);
 	foreach(@$obj->trk->trkseg->trkpt as $trkpt) {
		$datetime = $trkpt->time;
		$latGPX = round($trkpt['lat'], 7);
		$lonGPX = round($trkpt['lon'], 7);


		foreach($result as $num => $detailWayPoint) {
			$cpt++;

			// Calcul du nom
			$dist = round(mg::getDistance($latGPX, $lonGPX, round($detailWayPoint['latitude'], 7), round($detailWayPoint['longitude'], 7)));
			if ($dist < $distMin) {
				if ($detailWayPoint['nom'] != $oldName) {
					$zone = $detailWayPoint['zone'];
					$nomRando .= ' - '. $detailWayPoint['nom'];
					$oldName = $detailWayPoint['nom'];
				}
			}
		}
	}

	$nomRando = trim(trim(trim($nomRando), '-'));
	mg::messageT('', "$cpt it. - Nom calculé : ($zone) $nomRando");
	return substr($nomRando, 0 , 255);
}

// ********************************************************************************************************************
// ******************* RECALCUL LE NOM ET LE DETAIL IBP DE L'ACTIVITE DE TOUS GPX PRESENT DANS LE REP USER ************
// ********************************************************************************************************************
function RecalculBase ($user = 'MG') {
	global $pathGeofence, $equipMapPresence;

	$i = 0;
	$pathGPX = $pathGeofence."histo_$user/";
	$idUser = trim(mg::toID($equipMapPresence, $user), '#');

	// Lecture de la liste des gpx du rep
	foreach (new DirectoryIterator($pathGPX) as $fileInfo) {
		if($fileInfo->isDot()) continue;

		$fileGPX = $pathGPX.'/'.$fileInfo->getFilename();
		$date = str_replace('.gpx', '', $fileInfo);

		$i++;
		mg::message('', "N° $i : $date - $fileGPX");
		setActivites($user, $date, $fileGPX);
	}
}

// ********************************************************************************************************************
// *************************** CALCUL LE NB D'ACTVITE D'UN CERTAIN TYPE SUR LES X LIGNES SUIVANTES ********************
// ********************************************************************************************************************
function countActivites($i, $result, $activite, $nb=1) {
	$count = 0;
	for ($ii=1; $ii<$nb; $ii++) {
		//mg::message('', get('activite', $result, $i+$ii)." +++ $ii - $count");
		if (get('activite', $result, $i+$ii) == $activite) $count++;
	}
	return $count;
}

// ********************************************************************************************************************
// ********************************************************************************************************************
// ********************************************************************************************************************
function HTML($userAppel) {
	global $IP_Jeedom, $API_Jeedom, $tabUser, $tabGeofence, $dateSQL, $latitudeHome, $longitudeHome, $refresh, $layerDefaut, $recul, $epaisseur, $colorVoiture, $pauseMin, $pathRef, $colorUser;

	$tailleBoutonsNum = '96';
	$tailleBoutons = $tailleBoutonsNum.'px';

$geofence = HTML_tete();
$geofence .= HTML_tete_JS();


/* *********************************************** BOUCLE USERS GEOFENCE ******************************************* */
	$polylineAll = '';
	$numUser = 0;

	foreach ($tabGeofence as $user => $detailsUser) {
		$latlngs = mg::getVar("tabGeofence_L_$user", '');
		$complements = mg::getVar("tabGeofence_C_$user");

		$debTime = intval($tabGeofence[$user]['debTime']);
		$lastTime = intval($tabGeofence[$user]['lastTime']);
		$lastPcBatterie = intval($tabGeofence[$user]['lastPcBatterie']);
		$lastSSID = $tabGeofence[$user]['lastSSID'];

		// Valeurs reprises du tableau des activités
		$codeUser = trim(str_replace('Tel-', '', $user), '_'); //////////////////////////////////////
		$lgnActivite = getActivites($codeUser, $dateSQL);
		if (is_countable($lgnActivite)) {
			$Km_E = $lgnActivite[$codeUser]['km_E'];
			$Km_V = $lgnActivite[$codeUser]['km_V'];
			$IBP = $lgnActivite[$codeUser]['IBP'];
			$name = htmlentities($lgnActivite[$codeUser]['name']);
			$vitesseMoyenneTotale = $lgnActivite[$codeUser]['vit_Glob'];
			$vitesseMoyenneMouvement = $lgnActivite[$codeUser]['vit_Mvmt'];
			$deniv_P = $lgnActivite[$codeUser]['deniv_P'];
			$deniv_M = $lgnActivite[$codeUser]['deniv_M'];
			$km_Effort = $lgnActivite[$codeUser]['km_Effort'];

			$urlIBP = $tabGeofence[$user]['urlIBP'];

			$duree_Glob = substr($lgnActivite[$codeUser]['duree_Glob'], 0, -3);
			$duree_Mvmt = substr($lgnActivite[$codeUser]['duree_Mvmt'], 0 ,-3);
			$duree_Pause = substr($lgnActivite[$codeUser]['duree_Pause'], 0, -3);
		}
		else {
		// Valeurs reprises de $tabGeofence (Affichage activité en cours non terminée)
			$name = '*** Entrainement en cours ***';
			$km_Effort = '*** en cours ***';
			$urlIBP = '';
			$IBP = '*** en cours ***';
			$Km_E = round($tabGeofence[$user]['Km_E'], 1);
			$Km_V = round(@$tabGeofence[$user]['km_V']);
			$deniv_P = $tabGeofence[$user]['denivelePlus'];
			$deniv_M = $tabGeofence[$user]['deniveleMoins'];

			$vitesseMoyenneTotale = round(($tabGeofence[$user]['dureeTotale'] - 3600)/3600, 1);
			$vitesseMoyenneMouvement = round(($tabGeofence[$user]['dureeTotale']-$tabGeofence[$user]['sommePause']*60 - 3600)/3600, 1);
			$duree_Glob = date('H:i', $tabGeofence[$user]['dureeTotale'] - 3600);
			$duree_Pause = date('H:i', $tabGeofence[$user]['sommePause'] * 60 - 3600);
			$duree_Mvmt = date('H:i', $tabGeofence[$user]['dureeTotale']-$tabGeofence[$user]['sommePause']*60 - 3600);

		}

		$debTimeStr = date('d/m \&#224;\ H:i', $debTime);
		$lastTimeStr = date('d/m \&#224;\ H:i', $lastTime);
		$cloture = intval(($tabGeofence[$user]['cloture'] == 0 ? $tabGeofence[$user]['lastTime'] : $tabGeofence[$user]['cloture']));
		$clotureStr = date('d/m \&#224;\ H:i', $cloture);

		$sommePauseStr = gmdate('H\h\ i\ \m\n', intval($tabGeofence[$user]['sommePause']*60));

		$polylineAll .= "polyline$user,";

		$imgBouton = "$codeUser.png";
		$colorUser = $tabUser["$user"]['colorGeofence'];
		$numUser++;

		$geofence .= HTML_userJS($codeUser, $numUser, $latlngs, $complements, $recul, $colorUser, $imgBouton, $debTime, $deniv_P, $deniv_M,$debTimeStr,  $clotureStr, $duree_Glob, $duree_Mvmt, $duree_Pause, $vitesseMoyenneTotale, $vitesseMoyenneMouvement, $Km_E, $Km_V, $km_Effort, $IBP, $name, $urlIBP, $lastTimeStr, $lastPcBatterie, $lastSSID);
	}

	$geofence .= HTML_Pied($user, $userAppel, $layerDefaut, $pathRef, $polylineAll);
	return $geofence;
}

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
function HTML_Tete() {
global $pathRef, $tailleBoutonsNum, $tailleBoutons, $styleUser, $IP_Jeedom;

	$tailleBoutonsNum = '96';
	$tailleBoutons = $tailleBoutonsNum.'px';

	$dateMin = '2010-01-01'; //date('Y\-m\-d', time()-date('t')*24*3600); // min = moins 1 mois (selon l'historique)
	$dateMax = date('Y\-m\-d', time());

return "
<!DOCTYPE html>
<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"fr\">
	<head>
		<meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\" />
		<link rel=\"stylesheet\" href=\"$pathRef/_ressources/leaflet/leaflet.css\" />
		<script src=\"$pathRef/_ressources/leaflet/leaflet.js\"></script>
		<link rel=\"stylesheet\" href=\"$pathRef/_ressources/leaflet/easy-button.css\" />
		<script src=\"$pathRef/_ressources/leaflet/easy-button.js\"></script>

		<script src='https://code.jquery.com/jquery-3.4.1.min.js'></script>
	</head>

	<style>
		html, body {
			height: 100%
		}

		.leaflet-marker-icon.leaflet-interactive {
			border-radius: 50% /* Mise cercle des icones */
		}

		.leaflet-bar button, .leaflet-bar button:hover, .leaflet-bar a, .leaflet-bar a:hover, .leaflet-control-layers-toggle {
			width: $tailleBoutons!important; /* Largeur bouton image de zoom */
			height: $tailleBoutons!important; /* Hauteur bouton image de zoom */
			line-height: $tailleBoutons;
		}

		.leaflet-control-zoom-in, .leaflet-control-zoom-out {
			font: bold $tailleBoutons 'Lucida Console', Monaco, monospace;
		}

		.leaflet-bar, .leaflet-bar button:first-of-type, .leaflet-bar button:last-of-type, .leaflet-bar a:first-child, .leaflet-bar a:last-child {
		  border-radius: 50%;
		}

		.leaflet-bar {
			box-shadow: 0 15px 15px rgba(0,0,0,0.65);
		}

		/* general typography */
		.leaflet-container {
		font: 40px/2.5 'Helvetica Neue', Arial, Helvetica, sans-serif!important; /* fonte du pop up de choix des layers */
		}

		.leaflet-bar, .leaflet-bar button:first-of-type, .leaflet-bar button:last-of-type, .leaflet-bar a:first-child, .leaflet-bar a:last-child {
			border-top-left-radius: 50%!important;
			border-top-right-radius: 50%!important;
			border-bottom-right-radius: 50%!important;
			border-bottom-left-radius: 50%!important;
		}

		.leaflet-top {
			top: $tailleBoutons !important;
		}

			.leaflet-control-layers-toggle {
			/*	margin-top: -$tailleBoutons; */
		}

		.leaflet-bar button, .leaflet-bar button:hover, .leaflet-bar a, .leaflet-bar a:hover, .leaflet-control-layers-toggle {
			 background-size:  $tailleBoutons!important;
			background-color: darkorange!important;
		}

		.leaflet-touch .leaflet-bar a {
			width: $tailleBoutons!important;
			height: $tailleBoutons!important;
			line-height: $tailleBoutons!important;
		}

			.leaflet-touch .leaflet-control-zoom-out {
			font-size: $tailleBoutons!important;
			color:darkred;
		}
			.leaflet-touch .leaflet-control-zoom-in {
			font-size: $tailleBoutons!important;
			color:darkgreen;
		}

		.leaflet-control-scale-line {
			border: 2px solid #777;
			border-top: none;
			line-height: 1.1;
			padding: 5px 5px 5px;
			margin-bottom:10px;
			font-size: 25px;
			color:white;
			white-space: nowrap;
			overflow: hidden;
			-moz-box-sizing: border-box;
			box-sizing: border-box;
			background: darkred;
		}

		.leaflet-popup-content {
			margin: 5px 10px;
			line-height: 1.4!important;
			font-size: 22px!important;
			width:600px!important;
			}

		.leaflet-tooltip { /* tooltip des icones user *************************** */
			font-size: 32px;
			line-height: 1.4!important;
		}

		.form_date {
			z-index: 9999!important;
			position: absolute;
		}

		.input_date {
			position: relative;
			font-size: 55px;
			color: black;
			background-color: darkorange;
			   width: 100%;
		}

		.borneKm, borneKmMG, borneKmNR {
			font-size: 20px!important;
			color:black!important;
			text-align:center!important;

			background-color: white;
			border-radius: 50%;
			height: 25px;
			width: 25px;

			border-width:2px;
			border-style:solid;
			border-color:black;
		}

		.barre_button {
		text-align: end;
			z-index: 9999!important;
			position: relative;
		    height: 1px;
		}

		.button {
			 background-color:grey;
			 border: none;
			 color: white;
			 text-align: center;
			 text-decoration: none;
			 display: inline-block;
			 font-size: 30px;
			 padding: 10px 15px;
			 margin: 4px 2px;
			 cursor: pointer;
		}

		/* Styles spécifiques aux users */
		$styleUser
	</style>

	<body onload=\"__initialize()\">

		<div class='form_date'>
			<form >
				<!-- label for='date_geofence'>Veuillez choisir la date :</label -->
				<input class='input_date' type='date' id='date_geofence' name='date_geofence' min='$dateMin' max='$dateMax' size='30'>
			</form>
		</div>

		<div class='barre_button'>
		  <a href='$IP_Jeedom/mg/util/synthese_MG.html' class='button'>Synthese MG</a>
		  <a href='$IP_Jeedom/mg/util/synthese_NR.html' class='button'>Synthese NR</a>
		  <a href='$IP_Jeedom/mg/tabulator/tabulator.html' class='button'>Historique</a>
		</div>

		<!-- <button class='bt-reload' value='Reload' id='reload' onclick='reload();' title='Recharge la page.'>--- Reload ---</button> -->

		<div id=\"map\" style=\"width:100%; height:100%\"></div>
	</body>
	</html>

"; }

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
function HTML_Tete_JS() {
	global $IP_Jeedom, $API_Jeedom, $dateSQL, $currentLatLngUserAppel, $latitudeHome, $longitudeHome, $refresh, $pauseSize, $colorVoiture, $pathRef;

return "
<script type=\"text/javascript\">
	/* ************************************ INIT VARIABLES GLOBALES ET SAISIE ************************************** */
	document.getElementById('date_geofence').value = '$dateSQL';
	var numUser = -1;

/* ******************************************************** FONCTIONS JS ******************************************* */

function __getCookie(sName) {
	var oRegex = new RegExp('(?:; )?' + sName + '=([^;]*);?');
	if (oRegex.test(document.cookie)) {
			return decodeURIComponent(RegExp['$1']);
	} else {
			return null;
	}
}

function __setCookie(sName, sValue) {
	var today = new Date(), expires = new Date();
	expires.setTime(today.getTime() + (365*24*60*60*1000));
	document.cookie = sName + '=' + encodeURIComponent(sValue) + ';expires=' + expires.toGMTString();
}

	/* Recharge la page au changement de valeur d'une variable Jeedom */
	function __loadRefresh(varName, refresh=5, newValue='') {
		IP = '$IP_Jeedom';
		apiJeedom = '$API_Jeedom';
		requete = IP+'/core/api/jeeApi.php?apikey='+apiJeedom+'&type=variable&name='+varName+ (newValue != '' ? '&value=\"'+newValue+'\"' : '');
		var old_geofenceOK = __getCookie('_geofenceOK');
		$.get(requete, function( data, status ) {
			$('#result').html( data );
			if ((data-old_geofenceOK) > refresh) {
				__setCookie('_geofenceOK', data);
				__reload();
			}
		setTimeout('__loadRefresh(\"_geofenceOK\")',refresh*1000);
		});
	}

/* ----------------------------------------------------- API JEEDOM ------------------------------------------------ */
	/* ************************************** Lit / écrit une variable Jeedom ************************************** */
	function __setVarJeedom(varName, newValue='') {
		IP = '$IP_Jeedom';
		apiJeedom = '$API_Jeedom';
		requete = IP+'/core/api/jeeApi.php?apikey='+apiJeedom+'&type=variable&name='+varName+ (newValue != '' ? '&value='+newValue : '');
		$.get(requete, function( data, status ) {
			$('#result').html( data );
		});
	}

	/* Lit une commande info de Jeedom */
	function __getCmd(id) {
		IP = '$IP_Jeedom';
		apiJeedom = '$API_Jeedom';
		requete = IP+'/core/api/jeeApi.php?apikey='+apiJeedom+'&type=cmd&id='+id;
		$.get(requete, function( data, status ) {
			$('#result').html( data );
		});
		return data;
	}

/* ********************************************** MODIF DATE GEOFENCE ********************************************** */
document.getElementById('date_geofence').addEventListener('change', __changeDate);
function __changeDate(date) {
	var newDate = document.getElementById('date_geofence').value;
	__setVarJeedom('dateGeofence', newDate);
}

 function __reload() { document.location.reload(); }

__loadRefresh('_geofenceOK', $refresh);

function __initialize() {
	var latLng_Home= L.latLng($latitudeHome, $longitudeHome);
	var currentLatLngUserAppel = L.latLng($currentLatLngUserAppel);

	var map = L.map('map').setView(currentLatLngUserAppel, (__getCookie('zoom') > 0 ? __getCookie('zoom') : 14));

	L.control.scale({position: 'bottomright', maxWidth:500, imperial:false}).addTo(map); // Pose de l'échelle

	/* ******************** DOC SYNTAXE LEAFLET : https://leafletjs.com/reference-1.6.0.html#path *********************/

	/* ********************************************* MARQUEUR POINT DE TRACE ******************************************** */
	var iconPauseLongue = new L.Icon({
	  iconUrl: \"$pathRef/img/img_Binaire/presences/sleepingEmoji.png\",
	  iconSize: [$pauseSize*3, $pauseSize*3], iconAnchor: [$pauseSize/2, $pauseSize/2], popupAnchor: [1, -1.2*$pauseSize], });

"; }

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
function HTML_UserJS($user, $numUser, $latlngs, $complements, $recul, $colorUser, $imgBouton, $debTime, $deniv_P, $deniv_M,$debTimeStr,  $clotureStr, $duree_Glob, $duree_Mvmt, $duree_Pause, $vitesseMoyenneTotale, $vitesseMoyenneMouvement, $Km_E, $Km_V, $km_Effort, $IBP, $name, $urlIBP, $lastTimeStr, $lastPcBatterie, $lastSSID) {

	global $epaisseur, $sizePoint, $pauseMin, $pathRef, $tailleBoutonsNum, $tailleBoutons, $colorVoiture;

return "

	// *********************************************** DEBUT DE LA TRACE $user ****************************************
	numUser = numUser+1;

	// ************************************ EVENEMENTS POUR MISE A JOUR DES COOKIES ***********************************
	// Intercepte les changements de zoom et MàJ du cookie
	var prevZoom = map.getZoom();
	map.on('zoomend',function(e){
		var currZoom = map.getZoom();
		if(currZoom != prevZoom){
			__setCookie('zoom', currZoom);
		}
		prevZoom = currZoom;
	});

	/* Intercepte les changements de layer */
	map.on('baselayerchange', function (e) {
	__setCookie('layer', e.layer.getAttribution());
	});
	var latlngs = [$latlngs];
	var complements = [$complements];
	var dureePause = 0;
	var polyline$user = L.polyline(latlngs, {color:'$colorUser', weight:'$epaisseur' }).addTo(map);

	var iconUser = new L.icon({
		iconUrl: \"$pathRef/img/img_Binaire/presences/$user.png\",
		iconSize:	  [$tailleBoutonsNum, $tailleBoutonsNum], // taille de l'icone
		iconAnchor:	  [$tailleBoutonsNum/2, $tailleBoutonsNum/1*$numUser - $tailleBoutonsNum/1], // point de l'icone qui correspondra à la position du marker
		popupAnchor:  [-3, 42 -$tailleBoutonsNum/2*$numUser] // point depuis lequel la popup doit s'ouvrir relativement à l'iconAnchor
	});

	var dateStringPause = '';
// ********************************************* PARCOURS DE LA TRACE *********************************************
	for ( var i=1; i < latlngs.length-1; ++i ) {
		var time = latlngs[i][2]*1000;

		// ******************************************* FORMATAGE DE LA DATE *******************************************
		// Conversion date en string
		var date = new Date(time);
		annee = date.getFullYear();
		moi = date.getMonth();
	    mois = new Array('Janvier', 'F&eacute;vrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Ao&ucirc;t', 'Septembre', 'Octobre', 'Novembre', 'D&eacute;cembre');
		j = date.getDate();
		jour = date.getDay();
		jours = new Array('Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi');
		h = date.getHours();
		if(h<10) { h = '0'+h; }
		m = date.getMinutes();
		if(m<10) { m = '0'+m; }
		s = date.getSeconds();
		if(s<10) { s = '0'+s; }
		dateString = j+' '+mois[moi] +' '+annee + ' &#224; ' + h + ':' + m + ':' + s;
		// Fin de la conversion

		// ******************************************** INIT VARIABLES USER *******************************************
		var coordonnee = latlngs[latlngs.length-1].toString();
		var	 latLng = coordonnee.split(',');
		latLng_ = L.latLng(latlngs[latlngs.length-1]);
		var latitude = latLng[0];
		var longitude = latLng[1];
		var Km_E = parseFloat(complements[i][0]);
		var vitesseEcart = parseFloat(complements[i][1]);
		var altitude = parseFloat(complements[i][2]);
		var activite = complements[i][3];
		var pcBatterie = parseInt(complements[i][4]);
		var dureePause = parseInt(complements[i][5]);
		var deltaI_Pause = parseInt(complements[i-1][6]);
		var Km_V = parseFloat(complements[i][7]);

		// ********************************************* Marqueur VOITURE *********************************************
		if (activite == 'V') {
			message = '<center> $user : ' + dateString + ' - ' + Math.round(Km_V) + ' km - Vit. ' + Math.round(vitesseEcart) + ' km/h - Alt. ' + Math.round(altitude) + ' m - Bat. ' + pcBatterie+ ' %.';
			L.circle(latlngs[i], $sizePoint, { 'color': '$colorVoiture', 'fill': true, 'fillColor': '$colorVoiture', 'fillOpacity': 1}).bindTooltip( message).addTo(map);

		// ******************************************* Marqueur ENTRAINEMENT ******************************************
		} else if (activite == 'E') {
			var pente = 0;

			if (i >= $recul) {
				var deltaAltitude = Math.round((altitude-parseFloat(complements[i-$recul][2]))*10)/10;
				var deltaDistance = Math.round((Km_E-parseFloat(complements[i-$recul][0]))*1000*10)/10;
				if (deltaDistance != 0) pente = Math.round((deltaAltitude / deltaDistance)*100*10)/10;
			}

			message = '<center> $user : ' + dateString + ' - ' + Km_E + ' km - Alt. ' + Math.round(altitude) + ' m - pente ' + pente + ' %' + '<br>Coordonn&eacute;es : (' + latitude + ' , ' + longitude + ') ' + ' - Bat. ' + pcBatterie + ' %';

			//colorPente = 'grey';
			if 		(pente > 25) colorPente = 'DarkRed';
			else if (pente > 14.5) colorPente = 'red';
			else if (pente > 8.5) colorPente = 'OrangeRed';
			else if (pente > 5) colorPente = 'Orange';
			else if (pente > 3) colorPente =  'gold';
			else if (pente > 1.7) colorPente =  'Khaki';
			else if ( pente <= 1.7 && pente >= -1.7) colorPente = 'YellowGreen';
			else if (pente > -1.7) colorPente =  'SkyBlue';
			else if (pente > -3) colorPente =  'DeepSkyBlue';
			else if (pente > -5) colorPente =  'DodgerBlue';
			else if (pente > -8.5) colorPente =  'RoyalBlue';
			else if (pente > -14.5) colorPente =  'MediumBlue';
			else if (pente > -25) colorPente =  'MidnightBlue';

			L.circle(latlngs[i], $sizePoint, { 'color': colorPente, 'fill': true, 'fillColor': colorPente,'fillOpacity': 1}).bindTooltip( message).addTo(map);

		// ********************************************** Marqueur PAUSE **********************************************
		} else if (activite == 'I') {
			if (deltaI_Pause == 0) { dateStringPause = dateString; } else { dateStringPause = dateStringPause; }
			// Marqueur de PAUSE LONGUE, en fin de pause on affiche l'icone à l'heure de début de pause
			if (dureePause > $pauseMin) {
				L.marker( latlngs[i-deltaI_Pause], {icon: iconPauseLongue} ).bindTooltip('$user : ' + dateStringPause + ' - PAUSE de ' + Math.round(dureePause) + ' mn - Alt ' + altitude + ' m').addTo( map );
			}

			// ******************************************** Marqueur ANOMALIES ********************************************
			message = '<center> $user : ' + dateString + ' - *** ANOMALIE *** - '+dureePause + ' - ' + deltaI_Pause + '< $pauseMin - Bat. ' + pcBatterie+ ' %.';
			L.circle(latlngs[i], $sizePoint, { 'color': '$colorVoiture', 'fill': true, 'fillColor': '$colorVoiture', 'fillOpacity': 1}).bindTooltip( message).addTo(map);
		}

		// ******************************************* BORNES KILOMETRIQUES *******************************************
		if (activite == 'V' && Km_V > 0) {
			var newKm = Math.round(Km_V/10-0.5)-Math.round(complements[i-1][7]/10-0.5);
			if (newKm != 0) {
				L.tooltip({
					permanent: true,
					direction: 'center',
					className: 'borneKm borneKm$user'
				})
				.setContent(Math.round(Km_V-0.5)+'')
				.setLatLng(latlngs[i]).addTo(map);
			}
		} else if (activite != 'V' && Km_E > 0) {
			var newKm = Math.round(Km_E-0.5)-Math.round(complements[i-1][0]-0.5);
			if (newKm != 0) {
				L.tooltip({
					permanent: true,
					direction: 'center',
					className: 'borneKm borneKm$user'
				})
				.setContent(Math.round(Km_E-0.5)+'')
				.setLatLng(latlngs[i]).addTo(map);
			}
		}

	} // Fin parcours trace

	// ************************************************** ICONE USER **************************************************
	message = '';
	if ($debTime > 0) {
	message = ' <center>********************* ENTRAINEMENT $user *********************'
	+ '<br>$name'
	+ '<br>du $debTimeStr au $clotureStr'
	+ '<br>Dur&eacute;e $duree_Glob, en mouvement $duree_Mvmt, ($duree_Pause de pause)'
	+ '<br>Dist. $Km_E km - d&eacute;niv. +$deniv_P m / -$deniv_M m - Vit. $vitesseMoyenneTotale / $vitesseMoyenneMouvement km/h'
	+ '<br>Km Effort : $km_Effort km - <a href=\"$urlIBP\" target=\"_blank\" /> Analyse IBP : $IBP </a>'
	+  '<br> -------------------------------------------------------------------------------<br>';
	}
	message = message + '<center>$user : $lastTimeStr : Batterie $lastPcBatterie % - $lastSSID'
	+ '<br>Kilom&egrave;trage voiture du jour : ' + $Km_V + ' km.'
	+'<br> Coordonn&eacute;es : (' + latitude + ' , ' + longitude + ')';

	L.marker(latLng_, {icon: iconUser}).addTo(map).bindPopup( message ).openPopup();
//	L.marker(latLng_, {icon: iconUser}).addTo(map).bindTooltip( message ).openPopup();

	// ******************************************** BOUTONS de zoom $user *********************************************
	L . easyButton ( '<img src = \"$pathRef/img/img_Binaire/presences/$imgBouton\">' ,  function ( btn , map ) {
		var	 $user	=  L.latLng(latlngs[latlngs.length-1]);
		var group = new L.featureGroup([polyline$user]);
		map.fitBounds(group.getBounds());
	} ) . addTo ( map ) ;

	// ********************************************* FIN DE LA TRACE $user ********************************************

"; }

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
function HTML_Pied($user, $userAppel, $layerDefaut, $pathRef, $polylineAll) {

return "
	if ('$polylineAll' != '') {
		// **************************************** ZOOM sur le userAppel  ou ALL *****************************************
/* *************************** ZOOM USER ET ALL INCOMPATIBLE AVEC LA MEMO DU ZOOM VIA COOKIE ***************************
		if ('$userAppel' == '$user') {
			var group = new L.featureGroup([polyline$userAppel]);
			map.fitBounds(group.getBounds());
		}
		else {
			var group = new L.featureGroup([$polylineAll]);
			map.fitBounds(group.getBounds());
		}
*/

		// ********************************************* BOUTONS de zoom All **********************************************
		L . easyButton ( '<img src = \"$pathRef/img/img_Binaire/presences/monde.png\">' , function ( btn , map ) {
			var group = new L.featureGroup([$polylineAll]);
			map.fitBounds(group.getBounds());
		} ) . addTo ( map ) ;
	}

	// *************************************** POSE DU REPERE HOME (REGION RONDE) *************************************
	var HOME = L.circle(latLng_Home, 100, {
		'color': 'red',
		'fill': true,
		'fillColor': 'red',
		'fillOpacity': 0.2,
	}).addTo(map);

	// ****************************************************************************************************************
	// ****************************************************************************************************************
	// *************** LISTE DES LAYERS SUR : http://leaflet-extras.github.io/leaflet-providers/preview/ **************
	// ***************** SITE DE LEAFLET : https://leafletjs.com/ *****************

	// **************************************** DEFINITIONS DES LAYERS PROPOSES ***************************************

	var OpenStreetMap_France = L.tileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', {
		maxZoom: 20,
		attribution: 'OpenStreetMap_France'
	});

	var GeoportailFrance_plan =
	L.tileLayer('https://wxs.ign.fr/{apikey}/geoportail/wmts?REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE={style}&TILEMATRIXSET=PM&FORMAT={format}&LAYER=GEOGRAPHICALGRIDSYSTEMS.PLANIGNV2&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}', {
		attribution: 'GeoportailFrance_plan',
		bounds: [[-75, -180], [81, 180]],
		minZoom: 2,
		maxZoom: 19,
		apikey: 'choisirgeoportail',
		format: 'image/png',
		style: 'normal'
	});

	var GeoportailFrance_orthos = L.tileLayer('https://wxs.ign.fr/{apikey}/geoportail/wmts?REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE={style}&TILEMATRIXSET=PM&FORMAT={format}&LAYER=ORTHOIMAGERY.ORTHOPHOTOS&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}', {
		attribution: 'GeoportailFrance_orthos',
		bounds: [[-75, -180], [81, 180]],
		minZoom: 2,
		maxZoom: 19,
		apikey: 'choisirgeoportail',
		format: 'image/jpeg',
		style: 'normal'
	});
/********** FIN INIT DES LAYERS **********/

// ******************************************* AFFICHAGE DU CHOIX DES LAYERS ******************************************
	var layer = __getCookie('layer') ? __getCookie('layer') : $layerDefaut;
	map.addLayer(eval(layer)); // Le layer par défaut

	map.addControl(new L.Control.Layers( {
		'Plan IGN': GeoportailFrance_plan,
		'Satellite': GeoportailFrance_orthos,
		'Plan OSM': OpenStreetMap_France,
		}, {})
	);

	// ************************************ FIN DE L'AFFICHAGE DU CHOIX DES LAYERS ************************************
}
</script>
"; }

/*********************************************************************************************************************/
/*************************************************** API IBP INDEX /**************************************************/
/*********************************************************************************************************************/
/*************** DOC SUR : https://www.ibpindex.com/index.php/fr/ibp-services-fr/ibp-index-api-v2-0 ******************/
/*********************************************************************************************************************/
class IBP {
   var $filename; //Source filename
   var $ibp; //Resoult: JSON Object)
   function IBP($filename = false){ //Constructor
	   if(!empty($filename)) $this->getIBP($filename);
   }
   function getIBP($filename) {
	   if(file_exists($filename)) {

            //Post fields
            $post_data = array();
            $ch = curl_init();
            if ((version_compare(PHP_VERSION, '5.5') >= 0)) {
                $post_data['file'] = new CURLFile($filename);
                curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
            } else {
                $post_data['file'] = "@".$filename;
            }
            $post_data['key'] = 'i3x95bxbdk9pfgwgks0x';  // Your api key
            //Curl connection
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://www.ibpindex.com/api/" );  // or "https://www.ibpindex.com/api/index.php"
            curl_setopt($ch, CURLOPT_POST, 1 );
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $postResult = curl_exec($ch); //return result
            if (curl_errno($ch)) {
               die(curl_error($ch)); //this stops the execution under a Curl failure
            }
            curl_close($ch); //close connection
            $this->ibp = $postResult;
            return $postResult;
	   }
   }
}
//# usage: $ibp = new IBP('path/to/file.gpx');
/*********************************************************************************************************************/

?>