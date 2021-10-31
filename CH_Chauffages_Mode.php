<?php
/**********************************************************************************************************************
Chauffages Mode - 104
Positionne les consignes de températures et les modes dans le plugin THERMOSTAT.

Calcul la saison courante :
	On est en été si $tempMoyExt de la journée est supérieure à la $TempConfortEte, sinon on est en hiver.

Gestion du mode des chauffages selon les heures et conditions.
	Pour chaque zone le calcul est individualisé.

**********************************************************************************************************************/
global $saison, $logChauffage, $newMode, $tempZone, $tempConfort, $temp_Ext, $equipement, $name;

// Infos, Commandes et Equipements :
	//	$infTempExt,
	// $infPresenceEtage, $equipGeneralMaison, $infNbMvmtSalon

// N° des scénarios :

//Variables :
	$NuitSalon = mg::getVar('NuitSalon');
	$heureReveil = mg::getVar('_Heure_Reveil');
	$temp_Ext = mg::getCmd($infTempExt);
	$presenceEtage = mg::getCmd($infPresenceEtage) > 0;
	$nbMvmtSalon = mg::getCmd($infNbMvmtSalon);

// Paramètres :
	$logTimeLine = mg::getParam('Log', 'timeLine');
	$logChauffage = mg::getParam('Log', 'chauffage');
	
	$tempHG = mg::getParam('Temperatures', 'tempHG');
	$salonETE = mg::getParam('Temperatures', 'SalonETE');					// Température confort du salon pour l'été.
	$salonHIVER = mg::getParam('Temperatures', 'SalonHIVER');				// Température confort du salon pour l'été.

	$heureChaufChambre = mg::getParam('Chauffages','heureChaufChambre');	// Heure de lancement chauffage chambre
	$heureChaufEtgChb = mg::getParam('Chauffages','heureChaufEtgChb');		// Heure de lancement chauffage chambre etg
	$dureeChaufSdB = mg::getParam('Chauffages','dureeChaufSdB');			// Durée du chauffage SdB en mn

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/

//=====================================================================================================================
mg::messageT('', ". GESTION ETE/HIVER");
//=====================================================================================================================
$tempMoyExt = round(scenarioExpression::averageBetween($infTempExt, '7 days ago', 'today') +0.5, 1);
$saison = $tempMoyExt >= $salonHIVER ? 'ETE' : 'HIVER';
mg::messageT('', "Temp Extérieure Moyenne 7 jours: $tempMoyExt ° vs $salonHIVER ° ==> $saison");

// Changement de saison
if ($saison != mg::getVar('Saison')) {
	mg::message($logChauffage, "Passage en '$saison' (temp extérieure moyenne sur 7 jours : $tempMoyExt °)");
	mg::message($logTimeLine, "Chauffage - Passage en '$saison' (temp extérieure moyenne sur 7 jours : $tempMoyExt °)");
	mg::setCmd("#[Salon][Températures]#", 'Consigne_', $saison == 'ETE' ? $salonETE : $salonHIVER);
	mg::setVar('Saison', $saison);
}

// Correction HeureRéveil si dépassée de 2 heures30
/*if (time() > $heureReveil+2.5*3600) {
	mg::setVar('_Heure_Reveil', $heureReveil + 24*3600);
}*/

//---------------------------------------------------------------------------------------------------------------------
//------------------------------------------- BOUCLE DES ZONES DE CHAUFFAGE -------------------------------------------
//---------------------------------------------------------------------------------------------------------------------
	foreach(eqLogic::byType('thermostat') as $thermostat){
		if (!$thermostat->getIsEnable()) continue;
		$zoneID = $thermostat->getObject();
		$zone = is_object($zoneID) ? $zoneID->getName() : '';
		if ($zone == '') continue;
		
		$equipement = $thermostat->getHumanName();
		$name = $thermostat->getName();
		if (mg::getCmd($equipement, 'Mode') == 'Off') {
	mg::setInf("#[$zone][Températures][Consigne Chauffage]#", '', "<font color='red'><br>Thermostat OFF</font color>");
			continue;
		}



	// Réglage des modes autorisé pour Thermostat Jeedom ************* A GERER !!!!!!!!!!!!!! *************
/*/	if ($saison == 'HIVER')
			mg::ConfigEquiLogic('thermostat', $name, 'allow_mode', 'heat');*/
/*	if ($detailsZone['clim'] == 1 && $saison == 'ETE')
			mg::ConfigEquiLogic('thermostat', $name, 'allow_mode', 'cool');*/
	
		//=============================================================================================================
//		mg::messageT('', "! ".strtoupper($name));
		//=============================================================================================================
	$tempZone = mg::getCmd("#[$zone][Températures][Température]#");
	$tempConfort = mg::getCmd("#[$zone][Températures][Consigne]#"); // issu du widget
	$tempEco = mg::getParam('Temperatures', $zone . 'Eco');

	//-----------------------------------------------------------------------------------------------------------------
	//-------------------------------------------------------- SALON --------------------------------------------------
	//-----------------------------------------------------------------------------------------------------------------
	if ( $name == 'Thermostat Salon' ) {
		$timeDebConfort = HeureConfort($heureReveil);
		if ($NuitSalon == 2 && time() < $heureReveil) $timeFinConfort = max($heureReveil, time());
		else $timeFinConfort = time()+900; // soit JAMAIS en journée
		LancementMode($timeDebConfort, $timeFinConfort);
	}
	//-----------------------------------------------------------------------------------------------------------------
	//------------------------------------------------------- CHAMBRE -------------------------------------------------
	//-----------------------------------------------------------------------------------------------------------------
	else if ( $name == 'Thermostat Chambre' ) {
		$timeDebConfort = HeureConfort(strtotime($heureChaufChambre));
		$timeFinConfort = $heureReveil;
		LancementMode($timeDebConfort, $timeFinConfort);
	}
	//-----------------------------------------------------------------------------------------------------------------
	//------------------------------------------------------- RDC SDB -------------------------------------------------
	//-----------------------------------------------------------------------------------------------------------------
	else if ( $name == 'Thermostat RdCSdB' ) {
		$timeDebConfort = HeureConfort($heureReveil);
		$timeFinConfort = $heureReveil + $dureeChaufSdB * 60;
		LancementMode($timeDebConfort, $timeFinConfort);
	}
	//-----------------------------------------------------------------------------------------------------------------
	//--------------------------------------------------- ETAGE CHAMBRE 1 (sud) ---------------------------------------
	//-----------------------------------------------------------------------------------------------------------------
	else if ( $name == 'Thermostat EtgChb1' ) {
		if ($presenceEtage) {
			$timeDebConfort = HeureConfort(strtotime($heureChaufEtgChb));
			$timeFinConfort = $heureReveil + 3600;
		LancementMode($timeDebConfort, $timeFinConfort);
		} else {
			$newMode = 'Eco';
		}
	}
	//-----------------------------------------------------------------------------------------------------------------
	//--------------------------------------------------- ETAGE CHAMBRE 2 (ouest) -------------------------------------
	//-----------------------------------------------------------------------------------------------------------------
	else if ( $name == 'Thermostat EtgChb2' ) {
		if ($presenceEtage) {
			$timeDebConfort = HeureConfort(strtotime($heureChaufEtgChb));
			$timeFinConfort = $heureReveil + 3600;
			LancementMode($timeDebConfort, $timeFinConfort);
		} else {
			$newMode = 'Eco';
		}
	}
	//-----------------------------------------------------------------------------------------------------------------
	//------------------------------------------------------ ETAGE SDB ------------------------------------------------
	//-----------------------------------------------------------------------------------------------------------------
	else if ( $name == 'Thermostat EtgSdB' ) {
		if ($presenceEtage) {
			$timeDebConfort = HeureConfort($heureReveil);
			$timeFinConfort = $timeDebConfort + $dureeChaufSdB * 60;
			LancementMode($timeDebConfort, $timeFinConfort);
		} else {
			$newMode = 'Eco';
		}
	}
//=====================================================================================================================
//=====================================================================================================================
//=====================================================================================================================

	// Pose de la consigne textuelle du chauffage
	$modeCourant = mg::getCmd($equipement, 'Mode'); //$newMode;
	$colorCourante = ($modeCourant == 'Confort') ? 'Yellow' : (($modeCourant == 'Eco') ? 'lightGreen' : 'lightBlue');
	$ConsigneCourante = ($modeCourant == 'Confort') ? $tempConfort : (($modeCourant == 'Eco') ? $tempEco : $tempHG);
	$chaufLib = "<font color='$colorCourante'><br>Mode $modeCourant (".mg::getCmd($equipement, 'Consigne')." °)</font><br>";

	mg::setInf("#[$zone][Températures][Consigne Chauffage]#", '', $chaufLib);
}
//=====================================================================================================================
mg::messageT('', ". FIN");
//=====================================================================================================================

/**********************************************************************************************************************
													SOUS PROGRAMMES
**********************************************************************************************************************/

/*---------------------------------------------------------------------------------------------------------------------
											CALCUL HEURE DE CONFORT PONDEREE EN HIVER
---------------------------------------------------------------------------------------------------------------------*/
function HeureConfort($heureDebTheorique) {
	global $saison, $tempZone, $tempConfort, $temp_Ext, $name;

	// Calcul de l'offset horaire
	$coeff_indoor_heat = mg::ConfigEquiLogic('thermostat', $name, 'coeff_indoor_heat');
	$coeff_outdoor_heat = mg::ConfigEquiLogic('thermostat', $name, 'coeff_outdoor_heat');
	$OffsetHoraireIn = ($tempConfort - $tempZone)*$coeff_indoor_heat;
	$OffsetHoraireOut = ($tempConfort - $temp_Ext)*$coeff_outdoor_heat;
	$deltaHeure = $OffsetHoraireIn + $OffsetHoraireOut;
	mg::message('', "+++ (IN $tempZone => $tempConfort : $OffsetHoraireIn mn) -  + (OUT $temp_Ext=> $tempConfort : $OffsetHoraireOut mn) => $deltaHeure mn");

	if ($deltaHeure < 0 || $saison != 'HIVER') { $deltaHeure = 0; }

	$timeDebConfort = $heureDebTheorique - $deltaHeure * 60;
	//=================================================================================================================
	mg::messageT('', "! CALCUL HEURE DE CONFORT de $name : " . date('d\/m\/Y \à H\hi\m\n', $timeDebConfort));
	//=================================================================================================================
	return $timeDebConfort;
}

/*---------------------------------------------------------------------------------------------------------------------
												LANCEMENT DES MODES
---------------------------------------------------------------------------------------------------------------------*/
function LancementMode($timeDebConfort, $timeFinConfort) {
	global $logChauffage, $newMode, $equipement, $name;
	//=================================================================================================================
//	mg::messageT('', ". LANCEMENT DES MODES");
	//=================================================================================================================
	$oldMode = mg::getCmd($equipement, 'Mode');

	// Calcul du mode
	if (mg::TimeBetween( $timeDebConfort, time(), $timeFinConfort)) {
		$newMode = 'Confort';
	} else $newMode = 'Eco';

	$message = 	". Mode ".strtoupper($newMode)." - Confort ==> " . date('d\/m\/Y \à H\hi\m\n', $timeDebConfort) . ' au ' . date('d\/m\/Y \à H\hi\m\n', $timeFinConfort);
	mg::messageT('', $message);

	// Si changement de mode
	if ($oldMode != $newMode) {
		mg::message($logChauffage, "$name - Passage en ".$message);

		// MaJ "Mode" de Jeedom THERMOSTAT
		mg::setCmd($equipement, $newMode);
		log::add('thermostat', 'warning', "MG - $name => Passage en mode ".$newMode);
	}
}

?>