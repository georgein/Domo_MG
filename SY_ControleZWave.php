<?php
/**********************************************************************************************************************
SY_ControleZWave - 194

Recherche les équipements Z-Wave qui n'ont pas communiqués depuis plus de $alerteComDefaut mn et tente de les soigner.
Si plus de $nbMaxHS équipement en anomalie, relance le Z-Wave

**********************************************************************************************************************/
// Infos, Commandes et Equipements :
	// $equipJeelink

// N° des scénarios :

// Variables de Ctrl des équipements

	$debug = true;					// Traitement sans interventions sur le protocole, juste les messages
	$plugin = 'openzwave'; 			// Protocole à surveiller
	$alerteComDefaut = 15;			// temps maximum par defaut, en mn depuis dernière comm de l'équipement
	$nbMaxHS = 5;					// Nb max d'équipement en défaut avant relance de Zwave
	$nomActionZwave = 'healNode';	// Nom de l'action à tenter sur l'équipement Z-Wave (healNode, testnode)

	// Paramètres :
	$logTimeLine = mg::getParam('Log', 'timeLine');

// ********************************************************************************************************************
// ********************************************************************************************************************

	//=============================================================================
	mg::MessageT('', "! Controle de $plugin en mode ".($debug ? 'DEBUG' : 'NORMAL'));
	//=============================================================================

	$cptHS = 0;

	$eqLogics = eqLogic::all();
	// Lecture de la base de données
	foreach($eqLogics as $eqLogic) {
		$logicalID = $eqLogic->getLogicalId();
		$type = strtolower($eqLogic->getEqType_name());
		$humanName = $eqLogic->getHumanName();
		$isEnabled = $eqLogic->getIsEnable();
		$lastCommunication = $eqLogic->getStatus('lastCommunication', date('Y-m-d H:i:s'));
		$status = $eqLogic->getStatus();
		$pcBattery = @$status['battery'];
		if ($type != $plugin || $logicalID == 1 || !$isEnabled || $pcBattery != '') { continue; }

		// Alerte sur lastCommunication
		$lastComDuree = ($lastCommunication ? round((time() - @strtotime($lastCommunication, date('Y-m-d H:i:s')))/60) : 0);
		if ($lastComDuree > $alerteComDefaut) {
			$cptHS++;
			//=========================================================================================================
			mg::MessageT('', "! nbHS : $cptHS - $type - $humanName - ID : $logicalID - last comm' : $lastCommunication - depuis $lastComDuree mn");
			//=========================================================================================================

			// ********************************** TENTATIVE DE SOINS DE L'EQUIPEMENT **********************************
			mg::ZwaveBusy(1);
			mg::message($logTimeLine, "Tentative de $nomActionZwave sur $humanName ne communiquant plus depuis plus de $lastComDuree mn.");
			if (!$debug || true) { mg::ZwaveAction($logicalID, $nomActionZwave); }
			mg::message('', "Traitement en cours ...");
			mg::ZwaveBusy(10);

			// ******************************************* RELANCE DE ZWAVE *******************************************
			if ($cptHS > $nbMaxHS) {
				// Arrêt Zwave
				mg::message($logTimeLine, "ARRET ZWAVE après $cptHS équipements ne communiquant plus depuis plus de $alerteComDefaut mn.");

				if (!$debug) { mg::setCmd($equipJeelink, 'Arrêter Z-Wave'); }
				while( mg::getCmd($equipJeelink, 'Démon Z-Wave') == 1) {
					sleep(5);
					if (!$debug) { mg::setCmd($equipJeelink, 'Arrêter Z-Wave'); }
				}

				// Démarrer Zwave
				mg::message($logTimeLine, "Relance de ZWAVE.");
				if (!$debug) { mg::setCmd($equipJeelink, 'Démarrer Z-Wave'); }
				while( mg::getCmd($equipJeelink, 'Démon Z-Wave') == 0) {
					sleep(5);
				}
				// Attente Z-Wave
				mg::ZwaveBusy(10);
				mg::message($logTimeLine, "ZWAVE est redémarrer.");
			}
		}
	}

?>