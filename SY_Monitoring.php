<?php
/**********************************************************************************************************************
SY_Monitoring - 159
Extrait, stocke et affiche en couleur les LoadAVG (1; 5 et 15), Espace libre de Mem (en %), l'espace libre du volume (en %) dans des variables reprises dans les virtuels de 'Monitoring' pour l'historisation.

Cherche dans les logs le mot 'ERROR' et affiche leur nom

Parcours les équipements sur batterie et affiche :
	Ceux dont le % est en anomalie.
	Ceux dont la période d'inactivité est trop élevée, (sauf ceux d'ouverture ('_O') - (Paramètrage général ET pour les capteurs de Mvmt).

**********************************************************************************************************************/
// Infos, Commandes et Equipements :
	// $equipMonitoring

// N° des scénarios :

// Limite Variables contrôle serveur Jeedom :
	$nomTab = '_tabAlertes';
	$loadMin = 0.33;	$loadMax = 0.66;
	$memMin = 33;		$memMax = 66;
	$volumeMin = 25;	$volumeMax = 50;
	$consoCPUMin = 6;	$consoCPUMax = 10;
	$queueZwaveMin = 5;	$queueZwaveMax = 10;

// Variables de Ctrl des équipements
	$alerteComDefaut = 1440;						// temps maximum par defaut, en mn depuis dernière comm de l'équipement
	$alerteComBattery = 365*1440;					// temps maximum, en mn depuis le dernier retour de la batterie
	$alerteChgmtBattery = 2*(365)*1440;				// temps maximum, en mn depuis le dernier changement de la batterie

	// équipement des plugins exclus separés par des '|' (forme regex)
	$excludeEquip = 'virtual|asuswrt|broadlink|tvdomsamsung|blea|camera|clink|cloudsyncpro|covidattest|dataexport|doorbird|htmldisplay'; // Exclusion plugins N°1
	$excludeEquip .= '|jeedomconnect|mobile|phonemarket|telegram|netatmo*|openvpn|mail|livebox|googlecast|jeelink|phone_detection'; // Exclusion plugins N°2
	$excludeEquip .= '|cachés|aucun|test|télécommande|volet|store|meteofull|domicile'; // Exclusion Nom de l'équipement

	// Si mot contenu dans le nom de l'équipement, pas de prise en compte à affichage des contrôles de batterie
	$excludeBattery = 'xxx|yyy';
	// Si mot contenu dans le nom de l'équipement, pas de prise en compte à affichage des contrôles de communications
	$excludeActivite = 'xx|xx';

	$timerCtrlEquip = 5;								// Nb de minutes entre deux contrôles des équipements (maximum 24h (1440), multiple du cron
	$timerCtrlLog = 5;									// Nb de minutes entre deux contrôles des logs (maximum 24h (1440), multiple du cron
	$timerNettoieLog = 'all';							// Heure de nettoyage des logs (0-23), si == 'all' : toute les heures

	$nomActionZwave = 'refreshAllValues'; // testNode

	// Paramètres :
	$logTimeLine = mg::getParam('Log', 'timeLine');
// ********************************************************************************************************************
	$values = array();

	$sql = "SELECT * FROM `config` WHERE `key` REGEXP 'battery::danger'";
	$resultSql = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
	$batteryDangerDefaut = $resultSql['0']['value'];

	$sql = "SELECT * FROM `config` WHERE `key` REGEXP 'battery::warning'";
	$resultSql = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
	$batteryWarningDefaut = $resultSql['0']['value'];

	// ************************************************ MAJ DE LA TABLE ***********************************************
	if (!mg::declencheur('schedule') && !mg::declencheur('user')) {
		setAlertes($nomTab, $excludeEquip);
		goto suite;
	}

	//=================================================================================================================
	mg::MessageT('', "! SURVEILLANCE DU SERVEUR JEEDOM");
	//=================================================================================================================
mg::setInf($equipMonitoring, 'MonitorLoadAvg', LoadAvg($equipMonitoring, $loadMin, $loadMax, $upTxt, $queueZwaveMin, $queueZwaveMax));
mg::setInf($equipMonitoring, 'MonitorUp', "$upTxt - ". ConsoCPU($equipMonitoring, $consoCPUMin, $consoCPUMax));
mg::setInf($equipMonitoring, 'MonitorMem', Mem($equipMonitoring, $memMin, $memMax));
mg::setInf($equipMonitoring, 'MonitorVol', Volume($equipMonitoring, $volumeMin, $volumeMax));

if ($nomTab == '_tabAlertes' || (mg::getTag('#heure#')*60 + mg::getTag('#minute#')) % $timerCtrlEquip == 0 || mg::getTag('#trigger#') == 'user') {
	suite:
	//=================================================================================================================
	mg::MessageT('', "! RECHERCHE ERREURS EQUIPEMENTS (Danger pcBattery par défaut : $batteryDangerDefaut %)");
	//=================================================================================================================
	$tabAlertes = getAlertes($nomTab, $excludeEquip, $excludeActivite, $excludeBattery, $batteryDangerDefaut, $batteryWarningDefaut);
	$messages = '';
	mg::unsetVar('_equipErreur');
	foreach($tabAlertes as $type => $equips) {
		foreach($equips as $equip => $detailsEquip) {
			if (boolval($detailsEquip['isEnabled']) === false) { continue; }
			// Alerte sur lastCommunication
			$lastComDuree = ($detailsEquip['lastComm'] ? round((time() - @strtotime($detailsEquip['lastComm'], date('Y-m-d H:i:s')))/60) : 0);
			$DureeMaxComDefaut = ($detailsEquip['timeOut'] == '' ? $alerteComDefaut : $detailsEquip['timeOut']);
			if (intval($DureeMaxComDefaut) > 0 && $lastComDuree > $DureeMaxComDefaut) {
				$messages .= "$type - $equip - last Comm : {$detailsEquip['cDepuis']} > $DureeMaxComDefaut mn)<br>";
			}

			if (trim($detailsEquip['batLastCom']) != '') {
			// Alerte sur last changement batterie
				$lastChgmtBattery = round((time() - @strtotime($detailsEquip['batChgmt'], date('Y-m-d H:i:s')))/60);
				if (trim($lastChgmtBattery) != '' && $lastChgmtBattery > $alerteChgmtBattery) {
				$messages .= "$type - $equip - last Chgmt Batterie : {$detailsEquip['ChgmtDepuis']}<br>";
				}

				// Alerte sur lastComBattery
				$lastComBattery = round((time() - @strtotime($detailsEquip['batLastCom'], date('Y-m-d H:i:s')))/60);
				if (trim($lastComBattery) != '' && $lastComBattery > $alerteComBattery) {
					$messages .= "$type - $equip - last Comm Batterie ( : {$detailsEquip['bDepuis']} > $alerteComBattery mn)<br>";
				}

				// Alerte sur pcBatterie
				$batteryDanger = trim($detailsEquip['batDanger']) != '' ? $detailsEquip['batDanger'] : $batteryDangerDefaut;
				if ($detailsEquip['pcBattery'] < $batteryDanger) {
					$messages .= "$type - $equip - Alerte bat. ({$detailsEquip['pcBattery']} % < $batteryDanger)<br>";
				}
			}
		}
	}

	// En tête des messages de ctrlErreurBat
	if ($messages) {
		$equipErreur = "Capteurs en ERREUR : <br>$messages";
		mg::Message('', '.');
	if ($equipErreur) mg::setVar('_equipErreur', $equipErreur);
	}
}

//=====================================================================================================================
mg::MessageT('', "! SURVEILLANCE DE FREE - Géré par JPI");
//=====================================================================================================================
$freeEnErreur = '';
if (mg::getVar('FREE_OK', 0)) {
	$freeEnErreur .= '****** Réseau FREE HS ******.<br>';
}

/*if (mg::getTag('#minute#') == 0 && ((mg::getTag('#heure#') == $timerNettoieLog) || $timerNettoieLog == 'all')) {
	//=================================================================================================================
	// ********************************************** NETTOYAGE DES LOGS **********************************************
	//=================================================================================================================
	$logEnErreur = mg::getVar('_LogEnErreur');
	// Si pas d'erreur on nettoie le repertoire
	if (!$logEnErreur) {
		$result = shell_exec("sudo rm -f /var/www/html/log/scenarioLog/*");
		mg::messageT('', "! AUCUNE ERREUR => NETTOYAGE DES LOGS !!! ($result)");
	} else {
		mg::messageT('', "! PAS D'EFFACEMENT : ERREUR => '$logEnErreur' - Contrôle toutes les $timerCtrlLog mn - Nettoyage prévu à $timerNettoieLog heures.");
	}
}*/

if ($nomTab == '_alertes' || (mg::getTag('#heure#')*60 + mg::getTag('#minute#')) % $timerCtrlLog == 0 || mg::getTag('#trigger#') == 'user') {
	//=================================================================================================================
	mg::MessageT('', "! RECHERCHE ERREURS DANS LES LOGS");
	//=================================================================================================================

	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	$result = shell_exec("sudo rm -f /var/www/html/log/cron_execution"); // Supprime le log cron_execution trop volumineux
	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	$logEnErreur = mg::getVar('_LogEnErreur');
	// Uniquement le rep des scénarios 'custom'
	if ($logEnErreur) {
		shell_exec("sudo chmod -R 777 /var/www/html/log/scenarioLog");
		$result = shell_exec("sudo rm -f /var/www/html/log/scenarioLog/scenario159.log"); // Supprime le log de monitoring pour éviter le feedback du mot 'error'
	}

	mg::unsetVar('_LogEnErreur');
	$logEnErreur = trim(shell_exec("sudo grep -rn -o -i 'error' /var/www/html/log/scenarioLog --files-with-matches"));
	// Nettoyage du nom des logs en erreur
	if ($logEnErreur) {
		$regex = "scenario([0-9]*).log";
		preg_match_all("#$regex#ui", $logEnErreur, $found);
		$logEnErreur = '';
		foreach($found[1] as $num => $scenario) {
			mg::getScenario($scenario, $name);
			$logEnErreur .= " - '$name'";
		}
		$logEnErreur = trim(trim($logEnErreur, '-'));
	}

	// Nettoyage log > 1 Mo
	//@shell_exec("find /var/www/html/log -type f -size +1000k -exec rm -f {} \;"); // Permet d'économiser du CPU

	if (trim($logEnErreur)) {
		$logEnErreur = "ERREUR dans LOG : <br>$logEnErreur<br>";
		mg::setVar('_LogEnErreur', $logEnErreur);
		mg::message('', $logEnErreur);
	}
}

//=====================================================================================================================
mg::MessageT('', "! AFFICHAGE FINAL DES ERREURS");
//=====================================================================================================================
$equipErreur = mg::getVar('_equipErreur');
$logEnErreur = mg::getVar('_LogEnErreur');
$notif_ICO = mg::getVar('_Notif_ICO'); // Géré dans scénario piscine
$notif_ICO = (strpos($notif_ICO, '!') !== false ? $notif_ICO.'<br>' : '');

$messageAlerte = '*** '.date('H\ \h\ i\ \m\n', time()).' ***<br>';
$messageAlerte .= mg::NettoieChaine("$notif_ICO $freeEnErreur $equipErreur $logEnErreur");
if (trim($messageAlerte) == '') { $messageAlerte = "Aucune alerte."; }
mg::setInf($equipMonitoring, 'MessageAlerte', $messageAlerte);
mg::message('', $messageAlerte);

// ************************************************************************************************************************
// ************************************************************************************************************************
// ************************************************************************************************************************
/*---------------------------------------------------------------------------------------------------------------------
												CALCUL DE LA CONSO CPU
---------------------------------------------------------------------------------------------------------------------*/
function ConsoCPU($equipMonitoring, $consoCPUMin, $consoCPUMax) {
	$ConsoCPU =	shell_exec("vmstat -n  | grep 0 | awk '{print $15}'");

	$consoCPU_pc = round(100-intval($ConsoCPU), 1);
	mg::setInf($equipMonitoring, 'ConsoCPU_pc', $consoCPU_pc);
	$consoCPU_pc = ThreeColor($consoCPU_pc, $consoCPUMin, $consoCPUMax, '', $color);

	return ThreeColor("CPU : ", '', '', '', $color) . " ( $consoCPU_pc % ).";
}

/*---------------------------------------------------------------------------------------------------------------------
													CALCUL DU VOLUME DISPO
---------------------------------------------------------------------------------------------------------------------*/
function Volume($equipMonitoring, $volumeMin, $volumeMax) {
	$volume =	shell_exec("df -h  | grep '/dev/sda1' | awk '{print $1, $2, $3, $4, $5}'");
	$volume = str_replace('%', '', $volume);
	$volume = str_replace('G', '', $volume);

	$volumeDetails = explode(' ', $volume);

	$volumeTotal = $volumeDetails[1];
	$volumeLibre = $volumeDetails[3];

	$volumeLibre_pc = round(100-intval($volumeDetails[4]));
	mg::setInf($equipMonitoring, 'VolumeLibre_pc', $volumeLibre_pc);
	$volumeLibre_pc = ThreeColor($volumeLibre_pc, $volumeMin, $volumeMax, '+', $color);

	$volumeUtilise = $volumeTotal - $volumeLibre;
	$volumeUtilise_pc = round(intval($volumeDetails[4]));
	$volumeUtilise_pc = ThreeColor($volumeUtilise_pc, $volumeMin, $volumeMax, '+', $color);
	return ThreeColor("Vol utilisé : ", '', '', '+', $color) . "$volumeUtilise  G / $volumeTotal  G ($volumeLibre_pc % de libre).";
}

/*---------------------------------------------------------------------------------------------------------------------
									CALCUL DE LA DUREE UP - CALCUL DES LOAD AVG 1, 5 et 15
---------------------------------------------------------------------------------------------------------------------*/
function LoadAvg($equipMonitoring, $loadMin, $loadMax, &$upTxt, $queueZwaveMin, $queueZwaveMax) {
	$Decalage = 0;
	$loadAvg =	shell_exec(" w	| grep 'load average:' | awk '{print $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, NF}'");
	$loadAvg = str_replace(', ', ' ', $loadAvg);	// Suppression virgule de séparation
	$loadAvg = str_replace(',', '.', $loadAvg);		// Point décimal
	$loadAvgDetails = explode(' ', $loadAvg);

	$Count = count($loadAvgDetails);
	$Count = intval($loadAvgDetails[$Count-1])-2;
	//mg::message('', "LoadAvg : $loadAvg - Count : $Count - " . print_r($loadAvgDetails, true));

	// Calcul de la durée de fonctionnement
	if (strpos($loadAvgDetails[1], ':') !== false) // au démarrage avec heure
	{
		$nbHeures = str_replace(':', 'h', $loadAvgDetails[1]);
		$nbJours = "0 jour et ";
	} elseif (strpos($loadAvgDetails[2], 'min') !== false)
	{
		$nbHeures = "{$loadAvgDetails[1]} mn"; // au démarrage avec minute
		$nbJours = "0 jour et ";
	}else
	{
		$nbHeures = "{$loadAvgDetails[3]} mn";	// En croisière
		$nbHeures = str_replace(':', 'h', $nbHeures);
		$nbJours = "{$loadAvgDetails[1]} jour(s) et ";
	}

	//	Calcul / affichage de la queue Zwave
/*	$queueZwave = mg::ZwaveBusy();
	$queueZwave = ThreeColor($queueZwave, $queueZwaveMin, $queueZwaveMax, '', $color);
	$queueZwave = ThreeColor("Queue Zwave : ", '', '', '', $color) . $queueZwave;	*/

	$upTxt = "Up $nbJours $nbHeures";// . ' - ' . $queueZwave;

	// Calcul et mise en couleurs des AvgLoad
	$loadAvg_1 = $loadAvgDetails[$Count-2];
	mg::setInf($equipMonitoring, 'LoadAvg_1', $loadAvg_1);
			$loadAvg_1 = ThreeColor($loadAvg_1, $loadMin, $loadMax, '', $Color_1);

	$loadAvg_5 = $loadAvgDetails[$Count-1];
	mg::setInf($equipMonitoring, 'LoadAvg_5', $loadAvg_5);
	$loadAvg_5 = ThreeColor($loadAvg_5, $loadMin, $loadMax, '', $color_5);

	$loadAvg_15 = $loadAvgDetails[$Count];
	mg::setInf($equipMonitoring, 'LoadAvg_15', $loadAvg_15);
	$loadAvg_15 = ThreeColor($loadAvg_15, $loadMin, $loadMax, '', $color_15);

	return ThreeColor("LoadAvg ", '', '', '', $color_15) . " 1mn $loadAvg_1 - 5mn $loadAvg_5 - 15mn $loadAvg_15";
}

/*---------------------------------------------------------------------------------------------------------------------
												CALCUL DE LA MEMOIRE DISPO
---------------------------------------------------------------------------------------------------------------------*/
function Mem($equipMonitoring, $memMin, $memMax) {
	$mem =	shell_exec("free | grep 'Mem' | head -1 | awk '{print $1, $2, $3, $4, $5}'");
	$memDetails = explode(' ', $mem);
	//echo $mem; print_r($memDetails);

	$memTotale = $memDetails[1];
	if (($memTotale / 1000) > 1000) {
		$memTotale = round($memTotale / 1000000, 2); $unitTotale = "Go";
	}else {
		$memTotale = round($memTotale / 1000, 0); $unitTotale = "Mo";
	}

	$memLibre = $memDetails[3];
	if (($memLibre / 1000) > 1000) {
		$memLibre = round($memLibre / 1000000, 2); $unitLibre = "Go";
	}else{
		$memLibre = round($memLibre / 1000, 0); $unitLibre = "Mo";
	}
	$ratio = ( $unitTotale != $unitLibre) ? 1000 : 1;

	$memLibre_pc = round((($memDetails[2] / $memDetails[1] * 100)-1) / $ratio);
	$memLibre_pc = 100-round(($memLibre / $memTotale) * 100 / $ratio);
	mg::setInf($equipMonitoring, 'MemLibre_pc', $memLibre_pc);

	$memLibre_pc = ThreeColor($memLibre_pc, $memMin, $memMax, '+', $color);
	return ThreeColor("Mem utilisée : ", '', '', '-', $color) . "$memLibre  $unitLibre / $memTotale $unitTotale ($memLibre_pc " . "% de libre).";
	mg::setInf($equipMonitoring, 'MemLibre_pc', $memLibre_pc);
}

/*---------------------------------------------------------------------------------------------------------------------
													POSE DE LA COULEUR
---------------------------------------------------------------------------------------------------------------------*/
function ThreeColor($value, $valueMin = 0, $valueMax = 1, $sens = '+', &$color = '') {

	if ($color == '') {
		$color = 'LIGHTSALMON';
		if ($sens == '+') {
			if ($value >= $valueMax) {
				$color = 'LIGHTGREEN';
			}
			if ($value < $valueMin) {
				$color = 'RED';
			}
		}
		else
		{
			if ($value > $valueMax) {
				$color = 'RED';
			}
			if ($value <= $valueMin) {
				$color = 'LIGHTGREEN';
			}
		}
	}
	return "<font color='$color'>$value</font>";
}

/**********************************************************************************************************************
															SET ALERTES
					Met à jour la Base de données JEEDOM si des modifs ont été faites dans _tabAlertes
**********************************************************************************************************************/
function setAlertes ($nomTab, $excludeEquip) {
	mg::messageT('', "! ENREGISTREMENT EN BDD DES MODIFICATIONS DE L'EQUIPEMENT");
	
	$tabAlertes = mg::getTabSql($nomTab);

	// Lecture de la BdD
	$eqLogics = eqLogic::all();
	foreach($eqLogics as $eqLogic) {
		$ID = $eqLogic->getId();
		$type = $eqLogic->getEqType_name();
		$humanName = $eqLogic->getHumanName();

		$isEnabled = intVal($eqLogic->getIsEnable());
		if (!$isEnabled) { continue; }
		$isVisible = intVal($eqLogic->getIsVisible());
		$timeout = intVal($eqLogic->getTimeout());
		$battery_danger = intVal($eqLogic->getConfiguration('battery_danger_threshold'));
		$battery_warning = intVal($eqLogic->getConfiguration('battery_warning_threshold'));
		$batteryType = $eqLogic->getConfiguration('battery_type');

		// Si équipement exclu on le saute
		preg_match("#$excludeEquip#i", " $type $humanName", $foundEquip);
		if (isset($foundEquip[0])) { continue; }

		// Lecture de _alertes pour voir si changement vs BdD
//		if (!isset($tabAlertes[$type][$humanName])) { continue; }

		$isEnabled_ = intVal($tabAlertes[$type][$humanName]['isEnabled']);
		$isVisible_ = intVal($tabAlertes[$type][$humanName]['isVisible']);
		$timeout_ = intVal($tabAlertes[$type][$humanName]['timeOut']);
		$battery_warning_ = intVal($tabAlertes[$type][$humanName]['batWarning']);
		$battery_danger_ = intVal($tabAlertes[$type][$humanName]['batDanger']);
		$batteryType_ = $tabAlertes[$type][$humanName]['batType'];

		// Pose des modifs en BdD Jeedom
		$modif =0;
		if ($isEnabled != $isEnabled_) {
			mg::message('', "$ID - $type - $humanName - isEnabled : $isEnabled => $isEnabled_");
			$eqLogic->setIsEnabled($isEnabled_ == 1 ? '1' : '0');
			$modif++;
		}
		if ($isVisible != $isVisible_) {
			mg::message('', "$ID - $type - $humanName - isVisible : $isVisible => $isVisible_");
			$eqLogic->setIsVisible($isVisible_ == 1 ? '1' : '0');
			$modif++;
		}
		if ($timeout != $timeout_) {
			mg::message('', "$ID - $type - $humanName - timeout : $timeout ==> $timeout_");
			$eqLogic->setTimeout(intval($timeout_));
			$modif++;
		}
		if ($battery_warning != $battery_warning_) {
			 mg::message('', "$ID - $type - $humanName - battery_warning : $battery_warning => $battery_warning_");
				$eqLogic->setConfiguration('battery_warning_threshold', $battery_warning_);
			$modif++;
		}
		if ($battery_danger != $battery_danger_) {
			mg::message('', "$ID - $type - $humanName - battery_danger : $battery_danger => $battery_danger_");
			$eqLogic->setConfiguration('battery_danger_threshold', $battery_danger_);
			$modif++;
		}
		if ($batteryType != $batteryType_) {
			mg::message('', "$ID - $type - $humanName - type batterie : '$batteryType' => '$batteryType_'");
			$eqLogic->setConfiguration('battery_type', $batteryType_);
			$modif++;
		}
		if ($modif) { $eqLogic->save(); }
	}
}

/**********************************************************************************************************************
															GET ALERTES
				Mets à jour la table _tabAlertes avec les dernières info de tous ce qui n'est pas 'exclude'
**********************************************************************************************************************/
function getAlertes ($nomTab, $excludeEquip, $excludeActivite, $excludeBattery, $batteryDangerDefaut, $batteryWarningDefaut) {
	mg::messageT('', "! GENERATION DE LA TABLE $nomTab");

	$eqLogics = eqLogic::all();
	// Lecture de la base de données
	foreach($eqLogics as $eqLogic) {
		$type = $eqLogic->getEqType_name();
		$humanName = $eqLogic->getHumanName();
		$isEnabled = $eqLogic->getIsEnable();
		if (!$isEnabled) { continue; }
		$isVisible = $eqLogic->getIsVisible();
		$lastCommunication = $eqLogic->getStatus('lastCommunication', date('Y-m-d H:i:s'));
		$timeout = intval($eqLogic->getTimeout());
		$status = $eqLogic->getStatus();
		$battery_danger = intVal($eqLogic->getConfiguration('battery_danger_threshold'));
		$battery_warning = intVal($eqLogic->getConfiguration('battery_warning_threshold'));
		$batteryType = $eqLogic->getConfiguration('battery_type');
		$batteryChangement = $eqLogic->getConfiguration('batterytime');
		$pcBattery = intVal(@$status['battery']);
		$batteryDatetime = @$status['batteryDatetime'];

		// Pour lastCommunication on prend le plus récent vs batteryDatetime
		$lastCommunication = max($lastCommunication, $batteryDatetime);

		// Si équipement exclu on le saute
		preg_match("#$excludeEquip#i", " $type $humanName", $foundEquip);
		if (isset($foundEquip[0])) { continue; }

		$equi = $eqLogic->getId();
		if ($equi > 0) mg::setValSql($nomTab, $type, $humanName, 'eqLogic', $equi);
		
		preg_match("#$excludeActivite#i", $humanName, $foundActivite);
		preg_match("#$excludeBattery#i", $humanName, $foundBattery);

		// ENREGISTREMENT DE _ALERTES

		// si pas dans $excludeActivite
		if (!isset($foundActivite[0])) {
			mg::setValSql($nomTab, $type, $humanName, 'lastComm', $lastCommunication);
			mg::setValSql($nomTab, $type, $humanName, 'cDepuis', mg::dateIntervalle($lastCommunication));
			mg::setValSql($nomTab, $type, $humanName, 'timeOut', $timeout);
		} else {
			mg::setValSql($nomTab, $type, $humanName, 'lastComm', '');
			mg::setValSql($nomTab, $type, $humanName, 'cDepuis', '');
			mg::setValSql($nomTab, $type, $humanName, 'timeOut', 0);
		}
		// si pas dans $excludeBattery
		if (!isset($foundBattery[0])) {
			if ($batteryChangement != '') {
			mg::setValSql($nomTab, $type, $humanName, 'batLastCom', $batteryDatetime);
			mg::setValSql($nomTab, $type, $humanName, 'bDepuis', mg::dateIntervalle($batteryDatetime, ''));
			} else {
			mg::setValSql($nomTab, $type, $humanName, 'batLastCom', '');
			mg::setValSql($nomTab, $type, $humanName, 'bDepuis', '');
			}
			mg::setValSql($nomTab, $type, $humanName, 'batChgmt', $batteryChangement);
			mg::setValSql($nomTab, $type, $humanName, 'ChgmtDepuis', mg::dateIntervalle($batteryChangement, ''));
			mg::setValSql($nomTab, $type, $humanName, 'batType', $batteryType);
			mg::setValSql($nomTab, $type, $humanName, 'pcBattery', $pcBattery*1);
			mg::setValSql($nomTab, $type, $humanName, 'batDanger', $battery_danger*1);
			mg::setValSql($nomTab, $type, $humanName, 'batWarning', $battery_warning*1);
		} else {
			mg::setValSql($nomTab, $type, $humanName, 'batLastCom', '');
			mg::setValSql($nomTab, $type, $humanName, 'bDepuis', '');
			mg::setValSql($nomTab, $type, $humanName, 'batChgmt', '');
			mg::setValSql($nomTab, $type, $humanName, 'ChgmtDepuis', '');
			mg::setValSql($nomTab, $type, $humanName, 'batType', '');
			mg::setValSql($nomTab, $type, $humanName, 'pcBattery', 0);
			mg::setValSql($nomTab, $type, $humanName, 'batWarning', 0);
			mg::setValSql($nomTab, $type, $humanName, 'batDanger', 0);
		}
			mg::setValSql($nomTab, $type, $humanName, 'isEnabled', $isEnabled != 1 ? '0' : '1');
			mg::setValSql($nomTab, $type, $humanName, 'isVisible', $isVisible != 1 ? '0' : '1');
	}
	return mg::getTabSql($nomTab);
}

?>