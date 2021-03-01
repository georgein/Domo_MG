<?php
/**********************************************************************************************************************
Agendas Chauffages_Régulation - 115
Régulation des chauffages.
Le booster se déclenche si la température du salon est inférieur à (consigne - $deltaBooster).

*** Le nom des déclencheurs doit impérativement contenir $nomChauffage
*** L'équipement actionneur ON/Off doit être sous la forme : "#[$nomChauffage][Chauffage_$NomChauffage]#"
*** La L'info température doit être sous la forme : "[$nomChauffage][Temp $nomChauffage][Température]"
*** Si (4)ExistEtat est 'N' une variable !ChaufOnOffxxx est créé pour refléter l'état courant du chauffage, sinon c'est l'état réel qui est pris en compte
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
	// $infTempExt, $infRouteurBroadlink,
	// $equipDaemon, $EquipChauf

// N° des scénarios :

//Variables :
	$tabChauffages = mg::getVar('tabChauffages');
	$tabChauffages_ = mg::getVar('_tabChauffages');

	$saison = mg::getVar('Saison');
	$temp_Ext = mg::getCmd($infTempExt);
	$_InfPorte = mg::getVar('_InfPorte', '');					// Declencheur porte de LastMvmt(contient le nom du chauffage)

// Paramètres :
	$logTimeLine = mg::getParam('Log', 'timeLine');
	$logChauffage = mg::getParam('Log', 'chauffage');					// Pour debug
	$deltaTempChauf = mg::getParam('Chauffages','deltaTempChauf');		// Sensibilité de la régulation de température
	$timingPorteChauf = mg::getParam('Chauffages','timingPorteChauf');	// Timing avant coupure chauffage si ouverture
	$deltaBooster = mg::getParam('Chauffages','deltaBooster');			// Delta entre $tempSalon et $ConsigneSalon pour allumage du booster
	$pcDeltaTempExt = mg::getParam('Chauffages','pcDeltaTempExt');		// % du delta Consigne-TempExt à ajouter à la consigne
	$tempHG = mg::getParam('Temperatures', 'tempHG');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$declencheur = mg::getTag('#trigger#');
// Si appel par variable _InfPorte on récupère le déclencheur d'origine de mouvement de porte/fenetre
if (strpos($declencheur, '_InfPorte') > 0) { $declencheur = $_InfPorte; }

// ****************************************** RELANCE DE BROADLINK SI OFFLINE *****************************************
if (!mg::getCmd($infRouteurBroadlink) || !mg::getCmd($equipDaemon, 'Démon Broadlink')) {
	mg::setCmd($equipDaemon, 'Démarrer Broadlink');
	mg::Message($logTimeLine, "Chauffage - Relance du daemon de BroadLink.");
}

// Boucle des chauffages
foreach ($tabChauffages as $nomChauffage => $details_Chauffage) {
	if (!$nomChauffage) {continue; }
	//=================================================================================================================
	mg::messageT('', "! $nomChauffage");
	//=================================================================================================================
	$chauffage = intval($details_Chauffage['chauffage']);
	$clim =	 intval($details_Chauffage['clim']);
	$equipChauf = trim($details_Chauffage['equip']);
	$correction = $tabChauffages[$nomChauffage]['correction'];
	// ON NE GERE LE CHAUFFAGE QUE SI NECESSAIRE
	if ($saison == 'HIVER' && !$chauffage || $saison == 'ETE' && !$clim) { continue; }
	if (!$equipChauf) { continue; }

	$zone = $details_Chauffage['zone'];
	if (array_key_exists('equipBooster', $details_Chauffage)) {
		$equipBooster =	trim($details_Chauffage['equipBooster']);
	}
	$mode = $tabChauffages_[$nomChauffage]['mode'];
	$nbPortesZone = mg::getCmd("#[$nomChauffage][Ouvertures][NbPortes]");

	$tempZone = mg::getCmd("#[$nomChauffage][Températures][Température]#");
	// Calcul de la consigne
	if ($mode == 'HG') {
		$consigne = $tempHG;
	} else {
		$consigne = $tabChauffages_[$nomChauffage]["temp$mode"]+$correction;
	}
	$boosterOK = ($equipBooster && $saison == 'HIVER') ? true : false;

	//=================================================================================================================
	mg::messageT('', "! GESTION OUVERTURE PORTES/FENETRES $nomChauffage - NbPortesZone : $nbPortesZone");
	//=================================================================================================================
$nbPassages = 0;
	DebPorte:
	// Double lecture pour éviter les faux signaux
	$nbPortesZone = mg::getCmd("#[$nomChauffage][Ouvertures][NbPortes]");
	if ( $nbPassages == 0 && $nbPortesZone != 0 && mg::getCmd($equipChauf, 'Etat')) {
		sleep($timingPorteChauf);
		$nbPassages++;
		goto DebPorte;
	}
	// Arrêt chauffage si porte ouverte après la pause
	elseif ($nbPortesZone != 0) {
		// Arrêt du chauffage si en route
		if(mg::getCmd($equipChauf, 'Etat')) {
			mg::setCmd($equipChauf, 'Off');
			mg::message($logChauffage, "Chauffage - Arrêt du chauffage $nomChauffage suite à l'ouverture de $nbPortesZone porte(s).");
		}
		if ($boosterOK && mg::getCmd($equipBooster, 'Etat')) { mg::setCmd($equipBooster, 'Off'); }
		continue; // Si porte ouverte on saute la régulation
	}

	//=================================================================================================================
	mg::messageT('', "! REGULATION - Mode : $mode - $tempZone => $consigne " . ($boosterOK ? "- Booster : $boosterOK" : ""));
	//=================================================================================================================
	if ($saison == 'HIVER') {
		if ( ($consigne - $tempZone) >= $deltaTempChauf) {
			if(!mg::getCmd($equipChauf, 'Etat') || mg::getCmd($equipChauf, 'Puissance') < 50) {
				if ($clim) { mg::setCmd($equipChauf, 'Chauffage'); }
				else { mg::setCmd($equipChauf, 'On'); }
			}
			if ($boosterOK && ($consigne - $temp_Ext) >= $deltaBooster
						&& (!mg::getCmd($equipBooster, 'Etat') || mg::getCmd($equipBooster, 'Puissance') < 50)) { mg::setCmd($equipBooster, 'On'); }
		} else {
			if (mg::getCmd($equipChauf, 'Etat') || mg::getCmd($equipChauf, 'Puissance') > 50) { mg::setCmd($equipChauf, 'Off'); }
			if ($boosterOK && (mg::getCmd($equipBooster, 'Etat') || mg::getCmd($equipBooster, 'Puissance') > 50)) { mg::setCmd($equipBooster, 'Off'); }
		}
	}

	else if ($saison == 'ETE' && $clim) {
		if ( ($tempZone - $consigne) >= $deltaTempChauf ) {
			if (!mg::getCmd($equipChauf, 'Etat') || mg::getCmd($equipChauf, 'Puissance') < 50) { mg::setCmd($equipChauf, 'Climatisation'); }
		} else if (mg::getCmd($equipChauf, 'Etat') || mg::getCmd($equipChauf, 'Puissance') > 50) { mg::setCmd($equipChauf, 'Off'); }
	}

//=====================================================================================================================
mg::messageT('', ". FIN DE $nomChauffage (Etat : " . mg::getCmd($equipChauf, 'Etat') . ")");
//=====================================================================================================================
}
?>