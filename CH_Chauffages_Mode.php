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
global $tabChauffages_, $saison, $logChauffage, $logTimeLine, $nomChauffage, $ScenRegulation, $tempZone, $tempConfort, $correction, $tempEco, $heureReveil;

// Infos, Commandes et Equipements :
	//	$infTempExt,
	// $infPresenceEtage, $equipGeneralMaison

// N° des scénarios :
//	$ScenRegulation = 115;

//Variables :
	$tabChauffages = mg::getVar('tabChauffages');
	$tabChauffages_ = mg::getVar('_tabChauffages');

	$alarme = mg::getVar('Alarme');
	$nuitSalon = mg::getVar('NuitSalon');
	$heureReveil = mg::getVar('_Heure_Reveil');
	$temp_Ext = mg::getCmd($infTempExt);
	$presenceEtage = mg::getCmd($infPresenceEtage) > 0;

// Paramètres :
	$logTimeLine = mg::getParam('Log', 'timeLine');
	$logChauffage = mg::getParam('Log', 'chauffage');
	$tempHG = mg::getParam('Temperatures', 'tempHG');
	$heureChaufChambre = mg::getParam('Chauffages','heureChaufChambre');	// Heure de lancement chauffage chambre
	$heureChaufEtgChb = mg::getParam('Chauffages','heureChaufEtgChb');		// Heure de lancement chauffage chambre etg
	$dureeChaufSdB = mg::getParam('Chauffages','dureeChaufSdB');			// Durée du chauffage SdB en mn
	$pcDeltaTempExt = mg::getParam('Chauffages','pcDeltaTempExt');			// % du delta Consigne-TempExt à ajouter à la consigne
	$tempBypassPondertion = mg::getParam('Chauffages','tempBypassPondertion');	// Température extérieur en dessous de laquelle on ne passe plus en mode eco la nuit
	$tempSalonConfort = mg::getCmd("#[Salon][Températures][Consigne]#"); 	// Température confort du salon

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$declencheur = mg::getTag('#trigger#');
$zone = mg::extractPartCmd($declencheur, 1);

//=====================================================================================================================
mg::messageT('', ". GESTION ETE/HIVER");
//=====================================================================================================================
$tempMoyExt = round(scenarioExpression::averageBetween($infTempExt, '7 days ago', 'today') +0.5, 1);
$saison = $tempMoyExt >= $tempSalonConfort ? 'ETE' : 'HIVER';
mg::message('', "Temp Extérieure Moyenne 7 jours: $tempMoyExt ° ==> $saison");

if ($saison != mg::getVar('Saison')) {
	mg::message($logChauffage, "Passage en '$saison' (temp extérieure moyenne sur 7 jours : $tempMoyExt °)");
	mg::message($logTimeLine, "Chauffage - Passage en '$saison' (temp extérieure moyenne sur 7 jours : $tempMoyExt °)");
}
mg::setVar('Saison', $saison);

// Bypass du passage en eco la nuit par grand froid
$bypassPonderation = (mg::getCmd($infTempExt) < $tempBypassPondertion ? 1 : 0);

// Correction HeureRéveil si dépassée de 1 heures30
if (time() > $heureReveil+1.5*3600) {
	mg::setVar('_Heure_Reveil', $heureReveil + 24*3600);
}

//---------------------------------------------------------------------------------------------------------------------
//------------------------------------------- BOUCLE DES ZONES DE CHAUFFAGE -------------------------------------------
//---------------------------------------------------------------------------------------------------------------------
foreach ($tabChauffages as $nomChauffage => $detailsZone) {
	if ($zone != $nomChauffage) {continue; }
	if (!$nomChauffage || !$detailsZone['chauffage'] || !$detailsZone['equip']) { continue; }
		//=============================================================================================================
		mg::messageT('', "! $nomChauffage");
		//=============================================================================================================
	$tempZone = mg::getCmd("#[$nomChauffage][Températures][Température]#");
	$tempConfort = mg::getCmd("#[$nomChauffage][Températures][Consigne]#"); // issu du widget
	$tempEco = mg::getParam('Temperatures', $nomChauffage . 'Eco');

	$correction = $tabChauffages[$nomChauffage]['correction']; // Correction/Offset à aporter à la consigne
	$tabChauffages_[$nomChauffage]["tempConfort"] = $tempConfort;

/*	// init tableau secondaire
	if (!isset($tabChauffages_[$nomChauffage]['timeDeb'])) { $tabChauffages_[$nomChauffage]['timeDeb'] = 0; }
	if (!isset($tabChauffages_[$nomChauffage]['tempDeb'])) { $tabChauffages_[$nomChauffage]['tempDeb'] = 0; }
	if (!isset($tabChauffages_[$nomChauffage]['histoRatio'][0])) { $tabChauffages_[$nomChauffage]['histoRatio'] = array(0, 0, 0, 0, 0, 0, 0); }*/

	//-----------------------------------------------------------------------------------------------------------------
	//-------------------------------------------------------- SALON --------------------------------------------------
	//-----------------------------------------------------------------------------------------------------------------
	if ( $nomChauffage == 'Salon' ) {
		if (!isset($tabChauffages_[$nomChauffage]['ratio'])) { $tabChauffages_[$nomChauffage]['ratio'] = 4.47; }
		$timeDebConfort = HeureConfort($heureReveil);
		if ($bypassPonderation) { $timeDebConfort = $heureReveil - 8*3600; } // Pour palier partiellement au disfonctionnement de la pompe à chaleur par grand froid

		// Pour passer en mode Eco immédiatement si réveil dans plus de 2 heures
		if ($nuitSalon == 2 && time() < ($timeDebConfort - 2*3600)) { $timeFinConfort = time();	}
		// Sinon on reste en mode Confort in eternam
		else { $timeFinConfort = time() + 900; }
		LancementMode($timeDebConfort, $timeFinConfort);
	}
	//-----------------------------------------------------------------------------------------------------------------
	//------------------------------------------------------- CHAMBRE -------------------------------------------------
	//-----------------------------------------------------------------------------------------------------------------
	else if ( $nomChauffage == 'Chambre' ) {
		if (!isset($tabChauffages_[$nomChauffage]['ratio'])) { $tabChauffages_[$nomChauffage]['ratio'] = 8.01; }
		$timeDebConfort = HeureConfort(strtotime($heureChaufChambre));
		$timeFinConfort = $heureReveil;
		LancementMode($timeDebConfort, $timeFinConfort);
	}
	//-----------------------------------------------------------------------------------------------------------------
	//------------------------------------------------------- RDC SDB -------------------------------------------------
	//-----------------------------------------------------------------------------------------------------------------
	else if ( $nomChauffage == 'RdCSdB' ) {
		if (!isset($tabChauffages_[$nomChauffage]['ratio'])) { $tabChauffages_[$nomChauffage]['ratio'] = 1.85; }
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
			$tabChauffages_[$nomChauffage]['mode'] = 'Eco';
		}
	}
	//-----------------------------------------------------------------------------------------------------------------
	//--------------------------------------------------- ETAGE CHAMBRE 2 (ouest) -------------------------------------
	//-----------------------------------------------------------------------------------------------------------------
	else if ( $nomChauffage == 'EtgChb2' ) {
		if (!isset($tabChauffages_[$nomChauffage]['ratio'])) { $tabChauffages_[$nomChauffage]['ratio'] = 1.00; }
		if ($presenceEtage) {
			$timeDebConfort = HeureConfort(strtotime($heureChaufEtgChb));
			$timeFinConfort = $heureReveil + 3600;
			LancementMode($timeDebConfort, $timeFinConfort);
		} else {
			$tabChauffages_[$nomChauffage]['mode'] = 'Eco';
		}
	}
	//-----------------------------------------------------------------------------------------------------------------
	//------------------------------------------------------ ETAGE SDB ------------------------------------------------
	//-----------------------------------------------------------------------------------------------------------------
	else if ( $nomChauffage == 'EtgSdB' ) {
		if (!isset($tabChauffages_[$nomChauffage]['ratio'])) { $tabChauffages_[$nomChauffage]['ratio'] = 1.17; }
		if ($presenceEtage) {
			$timeDebConfort = HeureConfort($heureReveil);
			$timeFinConfort = $timeDebConfort + $dureeChaufSdB * 60;
			LancementMode($timeDebConfort, $timeFinConfort);
		} else {
			$tabChauffages_[$nomChauffage]['mode'] = 'Eco';
		}
	}
//=====================================================================================================================
//=====================================================================================================================
//=====================================================================================================================
	// Calcul du ratio °/h
	CalculRatio();

	// Pose de la consigne textuelle du chauffage
	$modeCourant = $tabChauffages_[$nomChauffage]['mode'];
	$colorCourante = ($modeCourant == 'Confort') ? 'Yellow' : (($modeCourant == 'Eco') ? 'lightGreen' : 'lightBlue');
	$ConsigneCourante = ($modeCourant == 'Confort') ? $tempConfort : (($modeCourant == 'Eco') ? $tempEco : $tempHG);
/*	$chaufLib =
		"<font color='$colorCourante'>  Mode ".$modeCourant.'</font><br><br> ('
		.($modeCourant == 'Confort' ? "<font color='$colorCourante'>".round($tempConfort, 1).'</font>' : round($tempConfort, 1))
		.' | '.($modeCourant=='Eco' ? "<font color='$colorCourante'>".round($tempEco, 1).'</font>' : round($tempEco, 1))
		.' | '.($modeCourant=='HG' ? "<font color='$colorCourante'>".round($tempHG, 1).'</font>' : round($tempHG, 1)).')';*/

	$chaufLib = "<font color='$colorCourante'>Mode $modeCourant ($ConsigneCourante °)</font><br><br>Confort :";
	mg::setInf("#[$nomChauffage][Températures][Consigne Chauffage]#", '', $chaufLib);
}

//=====================================================================================================================
mg::messageT('', ". FIN");
//=====================================================================================================================
mg::setVar('_tabChauffages', $tabChauffages_);
//mg::message('', print_r($tabChauffages_, true));

/**********************************************************************************************************************
													SOUS PROGRAMMES
**********************************************************************************************************************/

/*---------------------------------------------------------------------------------------------------------------------
											CALCUL HEURE DE CONFORT PONDEREE EN HIVER
---------------------------------------------------------------------------------------------------------------------*/
function HeureConfort($heureDebTheorique) {
	global $saison, $logChauffage, $tabChauffages_, $nomChauffage, $tempZone, $tempConfort, $correction;
	// Calcul de l'heure de DebConfort pondérée
	$deltaHeure = ($tabChauffages_[$nomChauffage]['ratio'] > 0.5) ? ($tempConfort + $correction - $tempZone) / $tabChauffages_[$nomChauffage]['ratio'] * 3600 : 0;
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
	global $tempZone, $logChauffage, $tabChauffages_, $nomChauffage, $ScenRegulation, $nomChauffage, $heureReveil;
	//=================================================================================================================
	mg::messageT('', ". LANCEMENT DES MODES");
	//=================================================================================================================
	$oldMode = $tabChauffages_[$nomChauffage]['mode'];
	mg::message('', 'Confort ==> Debut : ' . date('d\/m\/Y \à H\hi\m\n', $timeDebConfort) . ' - Fin : ' . date('d\/m\/Y \à H\hi\m\n', $timeFinConfort));

	// Calcul du mode
	if (mg::TimeBetween( $timeDebConfort, time(),	$timeFinConfort)) {
		$tabChauffages_[$nomChauffage]['mode'] = 'Confort';

	} elseif ($tabChauffages_[$nomChauffage]['timeDeb'] == 0) { // on ne passe pas en eco si calcul ratio en cours
			$tabChauffages_[$nomChauffage]['mode'] = 'Eco';
	}
	mg::messageT('', ". MODE ".strtoupper($tabChauffages_[$nomChauffage]['mode']));

	// Si changement de mode
	if ($oldMode != $tabChauffages_[$nomChauffage]['mode']) {
			// Init variables pour ratio si confort et pas de calcul en cours (timedeb == 0)
		if ($tabChauffages_[$nomChauffage]['mode'] == 'Confort' && $tabChauffages_[$nomChauffage]['timeDeb'] == 0) {
			$tabChauffages_[$nomChauffage]['timeDeb'] = time();
			$tabChauffages_[$nomChauffage]['tempDeb'] = $tempZone;
			mg::message($logChauffage, "$nomChauffage : RATIO ==> Init du calcul du ratio.");
		}
		mg::message($logChauffage, "$nomChauffage - Passage en mode ".$tabChauffages_[$nomChauffage]['mode']." ==> Debut : " . date('d\/m\/Y \à H\hi\m\n', $timeDebConfort) . ' - Fin : ' . date('d\/m\/Y \à H\hi\m\n', $timeFinConfort));
	}
}

/*---------------------------------------------------------------------------------------------------------------------
												CALCUL DU RATIO
---------------------------------------------------------------------------------------------------------------------*/
function CalculRatio() {
	global $saison, $logChauffage, $logTimeLine, $tabChauffages_, $nomChauffage, $tempZone, $tempConfort, $correction;
		// init tableau secondaire
		if (!isset($tabChauffages_[$nomChauffage]['ratio'])) { $tabChauffages_[$nomChauffage]['ratio'] = 1.0; }
		if (!isset($tabChauffages_[$nomChauffage]['timeDeb'])) { $tabChauffages_[$nomChauffage]['timeDeb'] = 0; }
		if (!isset($tabChauffages_[$nomChauffage]['tempDeb'])) { $tabChauffages_[$nomChauffage]['tempDeb'] = 0; }
		if (!isset($tabChauffages_[$nomChauffage]['histoRatio'][0])) { $tabChauffages_[$nomChauffage]['histoRatio'] = array(0, 0, 0, 0, 0, 0, 0); }
	 // Rien à traiter
	if ($tabChauffages_[$nomChauffage]['timeDeb'] == 0 || $saison != 'HIVER') { return; }

	// Si température confort atteinte ou trop de temps passé, calcul/memo du ratio
	if ($tempZone >= ($tempConfort + $correction) || $tabChauffages_[$nomChauffage]['timeDeb'] < time()-6*3600) {
		$deltaTemp = abs($tempZone - $tabChauffages_[$nomChauffage]['tempDeb']);
		$deltaTime = time() - $tabChauffages_[$nomChauffage]['timeDeb'];
		if ($deltaTime > 1800 && $deltaTemp > 0.5) {
			$ratio = round($deltaTemp / ($deltaTime / 3600), 2);
			$moyenne = getMoyenne($ratio);

			// Enregistrement final du ratio
			$tabChauffages_[$nomChauffage]['ratio'] = $moyenne;
			mg::message($logChauffage . trim($logTimeLine, 'Log:'), "$nomChauffage : RATIO ==> Delta température : $deltaTemp - Durée : " . date('H\hi\m\n', $deltaTime - 3600). " ==> RatioJour : $ratio - NewratioMoyen : $moyenne (sur les 7 derniers jours)");
		}

		// RaZ après calcul
		if ($tabChauffages_[$nomChauffage]['timeDeb'] != 0) {
			$tabChauffages_[$nomChauffage]['timeDeb'] = 0;
			$tabChauffages_[$nomChauffage]['tempDeb'] = 0;
			mg::message($logChauffage, "$nomChauffage : RATIO ==> RAZ du calcul courant du ratio après calcul.");
		}
	}
}


/*---------------------------------------------------------------------------------------------------------------------
								CALCULE LA MOYENNE DES X DERNIERES VALEURS DU TABLEAU
---------------------------------------------------------------------------------------------------------------------*/
function getMoyenne($ratioJour) {
	global $tabChauffages_, $nomChauffage;

	$jour = date('w');
	$tabChauffages_[$nomChauffage]['histoRatio'][$jour] = $ratioJour;

	$ratioMin = 1;
	$nb = 0;
	$somme = 0;
	foreach($tabChauffages_[$nomChauffage]['histoRatio'] as $key => $ratio) {
		if ($ratio < $ratioMin) {continue; }
		$somme += $ratio;
		$nb++;
	}
	$result = ($somme > 0 && $nb > 0) ? round($somme / $nb, 2) : 0;
//	mg::message('', "jour : $jour - ratioJour : $ratioJour - key : $key - somme des $nb premiers != 0 : $somme => Newratio : $result");
	return $result;
}

?>