<?php
/**********************************************************************************************************************
Chauffages Mode - 104
Positionne les valeurs de base des consignes de températures des chauffages.
Calcul la saison courante :
	On est en été si $tempMoyExt de la journée est supérieure à la $TempConfortEte, sinon on est en hiver.
Gestion du mode des chauffages selon les heures et conditions.
	Pour le salon :
		Mode Confort si ($heureReveil - durée préchauffage calculée) dépassé.
		Mode Eco à l'extinction des lumières.
	Pour la chambre :
		Mode Confort à $heureChaufChambre - durée préchauffage calculée.
		Mode Eco à l'heure du réveil.
	Pour la SdB :
		Mode Confort à $heureReveil - durée préchauffage calculée
		Mode Eco au bout de $dureeChaufSdB mn.
	Pour l'étage :
		Si celui ci est à OFF : Mode HG, si à ON régulation des chauffages de la SdB et des chambres QUI SONT cochées.

Le ratio de préchauffage (en °/h) est recalculé à chaque fois si > 0.1 et durée > 2mn.
**********************************************************************************************************************/
global $tabChauffagesTmp, $saison, $logChauffage, $logTimeLine, $nomChauffage, $ScenRegulation, $tempZone, $tempConfort, $correction, $tempEco, $heureReveil;

// Infos, Commandes et Equipements :
	//	$infTempExt,
	// $infPresenceEtage, $equipGeneralMaison, $infNbMvmtSalon

// N° des scénarios :
//	$ScenRegulation = 115;

//Variables :
	$nomTabChauffage = '_tabChauffages';
	$tabChauffages = mg::getTabSql($nomTabChauffage);
	$tabChauffagesTmp = mg::getVar('tabChauffagesTmp');

	$alarme = mg::getVar('Alarme');
	$nuitSalon = mg::getVar('NuitSalon');
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
	$pcDeltaTempExt = mg::getParam('Chauffages','pcDeltaTempExt');			// % du delta Consigne-TempExt à ajouter à la consigne
	$tempBypassPonderation = mg::getParam('Chauffages','tempBypassPonderation');// Température extérieur en dessous de laquelle on ne passe plus en mode eco la nuit
	$pcDeltaTempExt = mg::getParam('Chauffages','pcDeltaTempExt');			// % du delta Consigne-TempExt à ajouter à la consigne
	$tempSalonConfort = mg::getCmd("#[Salon][Températures][Consigne]#"); 	// Température confort du salon

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$zone = mg::declencheur('', 1);

//=====================================================================================================================
mg::messageT('', ". GESTION ETE/HIVER");
//=====================================================================================================================
$tempMoyExt = round(scenarioExpression::averageBetween($infTempExt, '7 days ago', 'today') +0.5, 1);
$saison = $tempMoyExt >= $salonHIVER ? 'ETE' : 'HIVER';
//$saison = $tempMoyExt >= $tempSalonConfort ? 'ETE' : 'HIVER';
mg::messageT('', "Temp Extérieure Moyenne 7 jours: $tempMoyExt ° ==> $saison");

if ($saison != mg::getVar('Saison')) {
	mg::message($logChauffage, "Passage en '$saison' (temp extérieure moyenne sur 7 jours : $tempMoyExt °)");
	mg::message($logTimeLine, "Chauffage - Passage en '$saison' (temp extérieure moyenne sur 7 jours : $tempMoyExt °)");
	mg::setCmd("#[Salon][Températures]#", 'Consigne_', $saison == 'ETE' ? $salonETE : $salonHIVER); 
} 


mg::setVar('Saison', $saison);

// Bypass du passage en eco la nuit par grand froid
$bypassPonderation = (mg::getCmd($infTempExt) < $tempBypassPonderation ? 1 : 0);

// Correction HeureRéveil si dépassée de 2 heures30
if (time() > $heureReveil+2.5*3600) { 
	mg::setVar('_Heure_Reveil', $heureReveil + 24*3600);
}

//---------------------------------------------------------------------------------------------------------------------
//------------------------------------------- BOUCLE DES ZONES DE CHAUFFAGE -------------------------------------------
//---------------------------------------------------------------------------------------------------------------------
foreach ($tabChauffages as $nomChauffage => $detailsZone) {
	if (!mg::declencheur('schedule') && !mg::declencheur('user') && $zone != $nomChauffage) {continue; }
	if (!$nomChauffage || !$detailsZone['chauffage'] || !$detailsZone['equipement']) { continue; }
		//=============================================================================================================
		mg::messageT('', "! $nomChauffage");
		//=============================================================================================================
	$tempZone = mg::getCmd("#[$nomChauffage][Températures][Température]#");
	$tempConfort = mg::getCmd("#[$nomChauffage][Températures][Consigne]#"); // issu du widget
	$tempEco = mg::getParam('Temperatures', $nomChauffage . 'Eco');
	$correction = $tabChauffages[$nomChauffage]['correction']; // Correction/Offset à aporter à la consigne
	$tabChauffagesTmp[$nomChauffage]["tempConfort"] = $tempConfort;

/*	// init tableau secondaire
	if (!isset($tabChauffagesTmp[$nomChauffage]['timeDeb'])) { $tabChauffagesTmp[$nomChauffage]['timeDeb'] = 0; }
	if (!isset($tabChauffagesTmp[$nomChauffage]['tempDeb'])) { $tabChauffagesTmp[$nomChauffage]['tempDeb'] = 0; }
	if (!isset($tabChauffagesTmp[$nomChauffage]['histoRatio'][0])) { $tabChauffagesTmp[$nomChauffage]['histoRatio'] = array(0, 0, 0, 0, 0, 0, 0); }*/

	//-----------------------------------------------------------------------------------------------------------------
	//-------------------------------------------------------- SALON --------------------------------------------------
	//-----------------------------------------------------------------------------------------------------------------
	if ( $nomChauffage == 'Salon' ) {
		if (!isset($tabChauffagesTmp[$nomChauffage]['ratio'])) { $tabChauffagesTmp[$nomChauffage]['ratio'] = 4.47; }
//		$timeDebConfort = HeureConfort($heureReveil);
		$timeDebConfort = HeureConfort(($nbMvmtSalon == 0 ? $heureReveil : time()-60)); ////////////////////////////////
		
		if ($bypassPonderation) { $timeDebConfort = $heureReveil - 8*3600; } // Pour palier partiellement au disfonctionnement de la pompe à chaleur par grand froid

		// Pour passer en mode Eco immédiatement si DebConfort dans plus de 15 mn
		if ($nuitSalon == 2 && time() < ($timeDebConfort - 90)) { $timeFinConfort = time(); }
		// Sinon on reste en mode Confort in eternam
		else { $timeFinConfort = time() + 900; }
		LancementMode($timeDebConfort, $timeFinConfort);
	}
	//-----------------------------------------------------------------------------------------------------------------
	//------------------------------------------------------- CHAMBRE -------------------------------------------------
	//-----------------------------------------------------------------------------------------------------------------
	else if ( $nomChauffage == 'Chambre' ) {
		if (!isset($tabChauffagesTmp[$nomChauffage]['ratio'])) { $tabChauffagesTmp[$nomChauffage]['ratio'] = 8.01; }
		$timeDebConfort = HeureConfort(strtotime($heureChaufChambre));
		$timeFinConfort = $heureReveil;
		LancementMode($timeDebConfort, $timeFinConfort);
	}
	//-----------------------------------------------------------------------------------------------------------------
	//------------------------------------------------------- RDC SDB -------------------------------------------------
	//-----------------------------------------------------------------------------------------------------------------
	else if ( $nomChauffage == 'RdCSdB' ) {
		if (!isset($tabChauffagesTmp[$nomChauffage]['ratio'])) { $tabChauffagesTmp[$nomChauffage]['ratio'] = 1.85; }
		$timeDebConfort = HeureConfort($heureReveil);
		$timeFinConfort = $heureReveil + $dureeChaufSdB * 60;
		LancementMode($timeDebConfort, $timeFinConfort);
	}
	//-----------------------------------------------------------------------------------------------------------------
	//--------------------------------------------------- ETAGE CHAMBRE 1 (sud) ---------------------------------------
	//-----------------------------------------------------------------------------------------------------------------
	else if ( $nomChauffage == 'EtgChb1' ) {
		if ($presenceEtage) {
			$timeDebConfort = HeureConfort(strtotime($heureChaufEtgChb));
			$timeFinConfort = $heureReveil + 3600;
		LancementMode($timeDebConfort, $timeFinConfort);
		} else {
			$tabChauffagesTmp[$nomChauffage]['mode'] = 'Eco';
		}
	}
	//-----------------------------------------------------------------------------------------------------------------
	//--------------------------------------------------- ETAGE CHAMBRE 2 (ouest) -------------------------------------
	//-----------------------------------------------------------------------------------------------------------------
	else if ( $nomChauffage == 'EtgChb2' ) {
		if (!isset($tabChauffagesTmp[$nomChauffage]['ratio'])) { $tabChauffagesTmp[$nomChauffage]['ratio'] = 1.00; }
		if ($presenceEtage) {
			$timeDebConfort = HeureConfort(strtotime($heureChaufEtgChb));
			$timeFinConfort = $heureReveil + 3600;
			LancementMode($timeDebConfort, $timeFinConfort);
		} else {
			$tabChauffagesTmp[$nomChauffage]['mode'] = 'Eco';
		}
	}
	//-----------------------------------------------------------------------------------------------------------------
	//------------------------------------------------------ ETAGE SDB ------------------------------------------------
	//-----------------------------------------------------------------------------------------------------------------
	else if ( $nomChauffage == 'EtgSdB' ) {
		if (!isset($tabChauffagesTmp[$nomChauffage]['ratio'])) { $tabChauffagesTmp[$nomChauffage]['ratio'] = 1.17; }
		if ($presenceEtage) {
			$timeDebConfort = HeureConfort($heureReveil);
			$timeFinConfort = $timeDebConfort + $dureeChaufSdB * 60;
			LancementMode($timeDebConfort, $timeFinConfort);
		} else {
			$tabChauffagesTmp[$nomChauffage]['mode'] = 'Eco';
		}
	}
//=====================================================================================================================
//=====================================================================================================================
//=====================================================================================================================
	// Calcul du ratio °/h
	CalculRatio();

	// Pose de la consigne textuelle du chauffage
	$modeCourant = $tabChauffagesTmp[$nomChauffage]['mode'];
	$colorCourante = ($modeCourant == 'Confort') ? 'Yellow' : (($modeCourant == 'Eco') ? 'lightGreen' : 'lightBlue');
	$ConsigneCourante = ($modeCourant == 'Confort') ? $tempConfort : (($modeCourant == 'Eco') ? $tempEco : $tempHG);
	$chaufLib = "<font color='$colorCourante'>Mode $modeCourant ($ConsigneCourante °)</font><br><br>Confort :";

	mg::setInf("#[$nomChauffage][Températures][Consigne Chauffage]#", '', $chaufLib);

/* ///////////////////////////////// AVEC THERMOSTAT JEEDOM
	mg::setCmd("#[$nomChauffage][Thermostat $nomChauffage][".$tabChauffagesTmp[$nomChauffage]['mode']."]#");
	$modeClim = ($saison == 'HIVER' ? 'Chauffage' : 'Climatisation') . ' seulement';
	mg::setCmd("#[$nomChauffage][Thermostat $nomChauffage][$modeClim]#");
//	mg::setCmd("#[$nomChauffage][Chauffage $nomChauffage][Consigne]#", '', $ConsigneCourante);
///////////////////////////////// */
}

//=====================================================================================================================
mg::messageT('', ". FIN");
//=====================================================================================================================
mg::setVar('tabChauffagesTmp', $tabChauffagesTmp);
if ( $scenario->getConfiguration('logmode') == 'realtime') {
	mg::message('', print_r($tabChauffagesTmp, true));
}

/**********************************************************************************************************************
													SOUS PROGRAMMES
**********************************************************************************************************************/

/*---------------------------------------------------------------------------------------------------------------------
											CALCUL HEURE DE CONFORT PONDEREE EN HIVER
---------------------------------------------------------------------------------------------------------------------*/
function HeureConfort($heureDebTheorique) {
	global $saison, $logChauffage, $tabChauffagesTmp, $nomChauffage, $tempZone, $tempConfort, $correction;
	// Calcul de l'heure de DebConfort pondérée
	$deltaHeure = ($tabChauffagesTmp[$nomChauffage]['ratio'] > 0.5) ? ($tempConfort + $correction - $tempZone) / $tabChauffagesTmp[$nomChauffage]['ratio'] * 3600 : 0;
	if ($deltaHeure < 0 || $saison != 'HIVER') { $deltaHeure = 0; }
	$timeDebConfort = $heureDebTheorique - $deltaHeure;
	return $timeDebConfort;
	//=================================================================================================================
	mg::messageT('', ". CALCUL HEURE DE CONFORT : " . date('d\/m\/Y \à H\hi\m\n', $timeDebConfort));
	//=================================================================================================================
}

/*---------------------------------------------------------------------------------------------------------------------
												LANCEMENT DES MODES
---------------------------------------------------------------------------------------------------------------------*/
function LancementMode($timeDebConfort, $timeFinConfort) {
	global $tempZone, $logChauffage, $tabChauffagesTmp, $nomChauffage, $ScenRegulation, $nomChauffage, $heureReveil;
	//=================================================================================================================
	mg::messageT('', ". LANCEMENT DES MODES");
	//=================================================================================================================
	$oldMode = $tabChauffagesTmp[$nomChauffage]['mode'];
	mg::message('', 'Confort ==> Debut : ' . date('d\/m\/Y \à H\hi\m\n', $timeDebConfort) . ' - Fin : ' . date('d\/m\/Y \à H\hi\m\n', $timeFinConfort));

	// Calcul du mode
	if (mg::TimeBetween( $timeDebConfort, time(), $timeFinConfort)) {
		$tabChauffagesTmp[$nomChauffage]['mode'] = 'Confort';

	} elseif ($tabChauffagesTmp[$nomChauffage]['timeDeb'] == 0) { // on ne passe pas en eco si calcul ratio en cours
			$tabChauffagesTmp[$nomChauffage]['mode'] = 'Eco';
	}
	mg::messageT('', ". MODE ".strtoupper($tabChauffagesTmp[$nomChauffage]['mode']));

	// Si changement de mode
	if ($oldMode != $tabChauffagesTmp[$nomChauffage]['mode']) {
			// Init variables pour ratio si confort et pas de calcul en cours (timedeb == 0)
		if ($tabChauffagesTmp[$nomChauffage]['mode'] == 'Confort' && $tabChauffagesTmp[$nomChauffage]['timeDeb'] == 0) {
			$tabChauffagesTmp[$nomChauffage]['timeDeb'] = time();
			$tabChauffagesTmp[$nomChauffage]['tempDeb'] = $tempZone;
			mg::message($logChauffage, "$nomChauffage : RATIO ==> Init du calcul du ratio.");
		}
		mg::message($logChauffage, "$nomChauffage - Passage en mode ".$tabChauffagesTmp[$nomChauffage]['mode']." ==> Debut : " . date('d\/m\/Y \à H\hi\m\n', $timeDebConfort) . ' - Fin : ' . date('d\/m\/Y \à H\hi\m\n', $timeFinConfort));
	}
}

/*---------------------------------------------------------------------------------------------------------------------
												CALCUL DU RATIO
---------------------------------------------------------------------------------------------------------------------*/
function CalculRatio() {
	global $saison, $logChauffage, $logTimeLine, $tabChauffagesTmp, $nomChauffage, $tempZone, $tempConfort, $correction;
		// init tableau secondaire
		if (!isset($tabChauffagesTmp[$nomChauffage]['ratio'])) { $tabChauffagesTmp[$nomChauffage]['ratio'] = 1.0; }
		if (!isset($tabChauffagesTmp[$nomChauffage]['timeDeb'])) { $tabChauffagesTmp[$nomChauffage]['timeDeb'] = 0; }
		if (!isset($tabChauffagesTmp[$nomChauffage]['tempDeb'])) { $tabChauffagesTmp[$nomChauffage]['tempDeb'] = 0; }
		if (!isset($tabChauffagesTmp[$nomChauffage]['histoRatio'][0])) { $tabChauffagesTmp[$nomChauffage]['histoRatio'] = array(0, 0, 0, 0, 0, 0, 0); }
	 // Rien à traiter
	if ($tabChauffagesTmp[$nomChauffage]['timeDeb'] == 0 || $saison != 'HIVER') { return; }

	// Si température confort atteinte ou trop de temps passé, calcul/memo du ratio
	if ($tempZone > ($tempConfort + $correction) || $tabChauffagesTmp[$nomChauffage]['timeDeb'] < time()-6*3600) {
		$deltaTemp = abs($tempZone - $tabChauffagesTmp[$nomChauffage]['tempDeb']);
		$deltaTime = time() - $tabChauffagesTmp[$nomChauffage]['timeDeb'];
		if ($deltaTime > 1800 && $deltaTemp > 0.5) {
			$ratio = round($deltaTemp / ($deltaTime / 3600), 2);
			$moyenne = getMoyenne($ratio);

			// Enregistrement final du ratio
			$tabChauffagesTmp[$nomChauffage]['ratio'] = $moyenne;
			mg::message($logChauffage . trim($logTimeLine, 'Log:'), "$nomChauffage : RATIO ==> Delta température : $deltaTemp - Durée : " . date('H\hi\m\n', $deltaTime - 3600). " ==> RatioJour : $ratio - NewratioMoyen : $moyenne (sur les 7 derniers jours)");
		}

		// RaZ après calcul
		if ($tabChauffagesTmp[$nomChauffage]['timeDeb'] != 0) {
			$tabChauffagesTmp[$nomChauffage]['timeDeb'] = 0;
			$tabChauffagesTmp[$nomChauffage]['tempDeb'] = 0;
			mg::message($logChauffage, "$nomChauffage : RATIO ==> RAZ du calcul courant du ratio après calcul.");
		}
	}
}


/*---------------------------------------------------------------------------------------------------------------------
								CALCULE LA MOYENNE DES X DERNIERES VALEURS DU TABLEAU
---------------------------------------------------------------------------------------------------------------------*/
function getMoyenne($ratioJour) {
	global $tabChauffagesTmp, $nomChauffage;

	$jour = date('w');
	$tabChauffagesTmp[$nomChauffage]['histoRatio'][$jour] = $ratioJour;

	$ratioMin = 1;
	$nb = 0;
	$somme = 0;
	foreach($tabChauffagesTmp[$nomChauffage]['histoRatio'] as $key => $ratio) {
		if ($ratio < $ratioMin) {continue; }
		$somme += $ratio;
		$nb++;
	}
	$result = ($somme > 0 && $nb > 0) ? round($somme / $nb, 2) : 0;
//	mg::message('', "jour : $jour - ratioJour : $ratioJour - key : $key - somme des $nb premiers != 0 : $somme => Newratio : $result");
	return $result;
}

?>