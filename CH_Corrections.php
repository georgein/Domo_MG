<?php
/**********************************************************************************************************************
CH_Corrections - 196

Activation / Désactivation des éléments de résumés selon :
	L'ancienneté du 'valueDate' ('timeOutDown' pour la désactivation ou 'timeOutUp' pour la réactivation)
	Et leurs écarts en % à la valeur moyenne du résumé selon le tableau ci dessous.
NB : La première commande du résumé n'est JAMAIS désactivée par sécurité.
NB : si pcEcartMax == 0, on ne lance pas la la gestion des résumés.

Correction des offset des commandes si l'heure courante est un multiple de 'periodicite et la minute courante < au 'cron'
NB : si 'periodicite' == 0, on ne lance pas la correction
NB : Ne JAMAIS lancer le traitement à moins de 'periodicite' d'intervalle sinon cumul des corrections !!!!
**********************************************************************************************************************/
// Infos, Commandes et Equipements :

// N° des scénarios :

// Variables de Ctrl des équipements
	$tabChauffages = (array)mg::getVar('tabChauffages');

	// Paramètres :
	$cron = 5;
	$logDebug = 'Log:/_DEBUG'; //mg::getParam('Log', 'timeLine');

// ********************************************************************************************************************
// ********************************************************************************************************************
mg::setCron('', "*/$cron * * * *");

// Lecture du tableau de paramètrage
foreach ($tabChauffages as $nomChauffage => $detailsZone) {
	$zone = $detailsZone['zone'];
	$equip = $detailsZone['equip'];
	$nomResume = $detailsZone['nomResume'];
	$cleResume = $detailsZone['cleResume'];
	$timeOut = $detailsZone['timeOut'];
	$pcEcartMax = $detailsZone['pcEcartMax'];
	$periodicite = $detailsZone['periodicite'];
	if (!$equip) { continue; }

	$cmdResume = mg::toID("#[$zone][Résumé][$nomResume]#");
	$valResume = mg::getCmd($cmdResume);
	$valResumeMoyenne = round(scenarioExpression::averageBetween($cmdResume, "$periodicite hour ago", 'now'), 1);
	mg::messageT('', "! Traitement de $zone/$cleResume avec timeOuts : $timeOut - pcEcartMax : $pcEcartMax - TempMoyenneRef : $valResumeMoyenne (sur $periodicite heures)");

	$mode = mg::toID("#[$zone][Températures][Consigne Chauffage]#");
	$valMode = mg::getCmd($mode,  '', $collectDate, $valueDate);
	$lastMode = round(((time() - $valueDate)/3600), 1);
	$cdMakeOffset = ($periodicite > 0 && mg::getTag('#heure#')%$periodicite == 1 && mg::getTag('#minute#') < $cron && $lastMode > $periodicite) ? 1 : 0;
//	$cdMakeOffset = 1; // ************** POUR TEST **************

	ControleResumes($zone, $cleResume, $timeOut, $pcEcartMax, $valResume, $valResumeMoyenne, $periodicite, $cdMakeOffset, $logDebug);
}

// *******************************************************************************************************************/
// ************************************************ CONTROLE DU RESUME ***********************************************/
function ControleResumes($zone, $cleResume, $timeOut, $pcEcartMax, $valResume, $valResumeMoyenne, $periodicite, $cdMakeOffset, $logDebug) {
	// Extraction SQL de la configuration de l'objet
	$values = array();
	$sql  = "SELECT `configuration` FROM `object` WHERE `name` = '$zone'";
	$resultSql = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
	$configuration = json_decode($resultSql[0]['configuration'], true);

	// Parcours des cmd pour activation / désactivation dans le résumé
	$resultTypes = $configuration['summary'][$cleResume];
	$cptCmd = 0;
	foreach ($resultTypes as $number => $details) {
		$cptCmd++;
		$cmd = trim($details['cmd'], '#');

/////////////////////////////////////////////////////////////////
/*	if ($cptCmd == 1) {
		$valResumeMoyenne = round(scenarioExpression::averageBetween($cmd, "$periodicite hour ago", 'now'), 1);
		$valResume = mg::getCmd($cmd);
	}*/
/////////////////////////////////////////////////////////////////

		if ($pcEcartMax > 0) {
			$enable = $details['enable'];
			cmdIsOK($cmd, $valResume, $lastComm, $pcEcart, $valCmd, $enable);
			if ($valResume == 0 || $valCmd == 0) {
				mg::message($logDebug, "*** ERROR *** ".mg::toHuman('#'.$cmd.'#')." sur la/les Températures ref/Comd : $valResume/$valCmd");
				continue;
			}
			if (($lastComm > $timeOut || $pcEcart > $pcEcartMax) && $enable) {
				if ($cptCmd != 1) {
					mg::messageT($logDebug, ". DESACTIVATION de la commande ".mg::toHuman('#'.$cmd.'#')." ($cmd) - last comm $lastComm mn - $pcEcart % ($valResume/$valCmd)");
				}
				if ($cptCmd > 1) { $configuration['summary'][$cleResume][$number]['enable'] = 0; }
				//else { mg::message ($logDebug, "*** ERRO R *** La première commande du Résumé ne peut pas être désactivée !!!");}
			} elseif ($lastComm <= $timeOut && $pcEcart <= $pcEcartMax && !$enable) {
				mg::messageT($logDebug, ". REACTIVATION de la commande ".mg::toHuman('#'.$cmd.'#')." ($cmd) - last comm $lastComm mn - $pcEcart % ($valResume/$valCmd)");
				$configuration['summary'][$cleResume][$number]['enable'] = 1;
			}
		}

		// *************************** Enregistrement de la nouvelle configuration du résumé **************************
		$configurationJson = json_encode($configuration);
		$sql = "UPDATE object SET configuration = '$configurationJson' WHERE `name` = '$zone'";
		$resultSql = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
		// **************************************** FIN DU CONTROLE DU RESUME ****************************************/
		// ***********************************************************************************************************/


		// ***********************************************************************************************************/
		// **************************************** MODIFICATION DES OFFSETS *****************************************/
		// ***** *Recherche de la configuration de la commande pour le MakeOffset (à optimiser ... largement ...) *****
		if ($cdMakeOffset) {
			$eqLogics = eqLogic::all();
			foreach($eqLogics as $eqLogic) {
				$ID = $eqLogic->getId();
				if (!$eqLogic->getIsEnable()) { continue; };
				$allCmds = $eqLogic->getCmd();
				foreach($allCmds as $allCmd) {
					$break = 0;
					$eqLogicCmd = $allCmd->getId();
					if ($eqLogicCmd == $cmd) {
						$valueOffset = $allCmd->getConfiguration('calculValueOffset');
						makeOffset($cmd, $allCmd, $valResumeMoyenne, $valueOffset, $periodicite, $logDebug);
						$break = 1;
					}
					if ($break) { break; }
				}
				if ($break) { break; }
			}
		}
		// ************************************** FIN MODIFICATION DES OFFSETS ***************************************/
		// ***********************************************************************************************************/
	}
}

// *******************************************************************************************************************/
// ************************************************* MAKE DE L'OFFSET ************************************************/
// *******************************************************************************************************************/
// Make la nouvelle valeur d'offset de la commande
function makeOffset($cmd, $allCmd, $valResumeMoyenne, $valueOffset, $periodicite, $logDebug) {
	$periodicite = $periodicite-1;
	$temperatureMoyenneCmd = round(scenarioExpression::averageBetween($cmd, "$periodicite hour ago", 'now'), 1);

	// Lecture de la correction actuelle
	$oldCorrection = '+0.0';
	$regex = '.*([+-][\d]*.[\d]*)';
	preg_match("/$regex/ui", $valueOffset, $found);
	if (@iconv_strlen($found[1]) != 0) {
		$oldCorrection = $found[1];
		if ($oldCorrection == 0) { $oldCorrection = "+0.0"; }
	}

	$regex = '(\(.*\))';
	preg_match("/$regex/ui", $valueOffset, $found);
	if (@iconv_strlen($found[1]) != 0) {
		$baseValueOffset = $found[1];
	} else {
		$baseValueOffset = "(#value#)";
	}

	// Calcul de la nouvelle correction à appliquer
	// On sort en cas d'anomalie
	if ($valResumeMoyenne == 0 || $temperatureMoyenneCmd == 0) {
		mg::message($logDebug, "*** ERRO R *** ".mg::toHuman('#'.$cmd.'#')." sur la/les Températures moyennes ref/Comd : $valResumeMoyenne/$temperatureMoyenneCmd");
		return;
	}
	$newCorrection = round($oldCorrection + ($valResumeMoyenne - $temperatureMoyenneCmd), 2);
	if ( $newCorrection >= 0) { $newCorrection = "+$newCorrection"; }
	elseif ($newCorrection == 0) { $newCorrection = "+0.0"; }

	//  Recalcul de la chaine 'ValueOffset'
	$newValueOffset = "$baseValueOffset$newCorrection";

	mg::message($logDebug, mg::toHuman("#$cmd#")." - tempRef/tempCmd : $valResumeMoyenne/$temperatureMoyenneCmd - old/New Correction : $oldCorrection/$newCorrection - ValueOffset : $newValueOffset");

	// **************************************
	// BIEN CONTROLER LE LOG AVANT D'ENLEVER LES REM.)
	// **************************************
/*	// Enregistrement des nouveaux offsets
*/	$allCmd->setConfiguration('calculValueOffset', $newValueOffset);
	$allCmd->save();
}

// *******************************************************************************************************************/
// ********************** Lit la dernière valeur et les dates associées de la commande du résumé *********************/
// *******************************************************************************************************************/
function cmdIsOK($cmd, $valResume, &$lastComm, &$pcEcart, &$valCmd, $enable) {
//	mg::debug(0);
	$valCmd = mg::getCmd($cmd, '', $collectDate, $valueDate);
	$lastComm = round(((time() - $collectDate)/60));

	$pcEcart = abs(round(($valResume-$valCmd)/$valResume*100, 1));
//	mg::debug();
	mg::message('', "Last comm $lastComm mn - $pcEcart % ($valResume/$valCmd) - enabled : $enable");
}

?>