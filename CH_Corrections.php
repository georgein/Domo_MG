<?php
/**********************************************************************************************************************
CH_Corrections - 196

Activation / Désactivation des éléments de résumés selon :
	L'ancienneté du 'valueDate' ('timeOutDown' pour la désactivation ou 'timeOutUp' pour la réactivation)
	Et leurs écarts en % à la valeur moyenne du résumé selon le tableau ci dessous.
NB : La dernière commande ACTIVE du résumé n'est JAMAIS désactivée par sécurité.
NB : si pcEcartMax == 0, on ne lance pas la la gestion des résumés.

Correction des offset des commandes si le mode courant depuis plus de 'periodicite' heure ET l'heure courante est un multiple de 'periodicite + 1 ET la minute courante < au 'cron'.
NB : si 'periodicite' == 0, on ne lance pas la correction
**********************************************************************************************************************/
// Infos, Commandes et Equipements :

// N° des scénarios :

// Variables de Ctrl des équipements
	$tabChauffages = (array)mg::getVar('tabChauffages');
	$tabChauffages_ = mg::getVar('_tabChauffages');

	// Paramètres :
	$cron = 5;
	$logDebug = ''; //'Log:/_DEBUG'; 
	$timeLine = mg::getParam('Log', 'timeLine');

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
	//$correction = $detailsZone['correction'];
	if (!$equip) { continue; }

	$mode = $tabChauffages_[$nomChauffage]['mode'];

	$cmdResume = mg::toID("#[$zone][Résumé][$nomResume]#");
	$tempResume = mg::getCmd($cmdResume);
	// Température moyenne de reference sur la moyenne (dérive possible)
	$tempMoyenneRef = round(scenarioExpression::averageBetween($cmdResume, "$periodicite hour ago", 'now'), 2);
	mg::messageT('', "! Traitement de $zone/$nomChauffage avec timeOuts : $timeOut - pcEcartMax : $pcEcartMax");

	// Planification de la prochaine 'correction' à 'periodicité' + 1 heure du dernier changement de mode ET SI en mode 'Confort'
	$infMode = mg::toID("#[$zone][Températures][Consigne Chauffage]#");
	$valMode = mg::getCmd($infMode,  '', $collectDate, $valueDate);
	$lastMode = round((time() - $valueDate)/3600, 2) + 1;
	if ($periodicite > 0 && $lastMode > $periodicite && $mode == 'Confort') {
		$cdMakeOffset = 1;
		mg::setInf($infMode,  '', 'Correction');
	} else { $cdMakeOffset = 0; }
	
mg::message('', "**************** $valMode - $lastMode > $periodicite - mode : $mode - tempMoyenneRef : $tempMoyenneRef ************");

//$cdMakeOffset = 1; ///////////////////////////////

	ControleResumes($zone, $nomChauffage, $cleResume, $timeOut, $pcEcartMax, $tempResume, $tempMoyenneRef, $periodicite, $cdMakeOffset, $logDebug, $timeLine);
}

// *******************************************************************************************************************/
// ************************************************ CONTROLE DU RESUME ***********************************************/
function ControleResumes($zone, $nomChauffage, $cleResume, $timeOut, $pcEcartMax, $tempResume, $tempMoyenneRef, $periodicite, $cdMakeOffset, $logDebug, $timeLine) {
	// Extraction SQL de la configuration de l'objet
	$values = array();
	$sql  = "SELECT `configuration` FROM `object` WHERE `name` = '$zone'";
	$resultSql = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
	$configuration = json_decode($resultSql[0]['configuration'], true);
	$resultTypes = $configuration['summary'][$cleResume];

	// Décompte des capteurs actifs
	$nbEnabledOK = 0;
	foreach ($resultTypes as $number => $details) {
			if ($details['enable']) { $nbEnabledOK++; }
		}

	// Parcours des cmd pour activation / désactivation dans le résumé
	$num = 0;
	foreach ($resultTypes as $number => $details) {
		$cmd = trim($details['cmd'], '#');
		if ($pcEcartMax > 0) {
			$enable = $details['enable'];
			cmdIsOK($cmd, $tempResume, $lastComm, $pcEcart, $valCmd, $enable);
			if ($tempResume == 0 || $valCmd == 0) {
				mg::message($logDebug, "*** ERROR *** ".mg::toHuman('#'.$cmd.'#')." sur la/les Températures ref/Comd : $tempResume/$valCmd");
				continue;
			}
			
			if (($lastComm > $timeOut || $pcEcart > $pcEcartMax) && $enable) {
				// Température moyenne de reference sur le PREMIER équipement ACTIF
				if ($num == 0) {	
					//$tempMoyenneRef = round(scenarioExpression::averageBetween($cmd, "$periodicite hour ago", 'now'), 2);
					$num++;
				}			

				if ($nbEnabledOK > 1) {
					mg::messageT($logDebug, ". DESACTIVATION de la commande ".mg::toHuman('#'.$cmd.'#')." ($cmd) - last comm $lastComm mn - $pcEcart % ($tempResume/$valCmd)");
					$configuration['summary'][$cleResume][$number]['enable'] = 0;
					$nbEnabledOK--;
				} else {
					mg::message ('', "*** ERRO R *** La dernière commande active du Résumé ne peut pas être désactivée !!!");
				}

			} elseif ($lastComm <= $timeOut && $pcEcart <= $pcEcartMax && !$enable) {
				mg::messageT($logDebug, ". REACTIVATION de la commande ".mg::toHuman('#'.$cmd.'#')." ($cmd) - last comm $lastComm mn - $pcEcart % ($tempResume/$valCmd)");
				$configuration['summary'][$cleResume][$number]['enable'] = 1;
				$nbEnabledOK++;
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
						makeOffset($cmd, $allCmd, $tempMoyenneRef, $valueOffset, $periodicite, $logDebug, $timeLine);
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
	mg::messageT('', "! Fin de Traitement de $zone/$nomChauffage - Nb de commandes actives : $nbEnabledOK - TempMoyenne/Ref : $tempResume");
}

// *******************************************************************************************************************/
// ************************************************* MAKE DE L'OFFSET ************************************************/
// *******************************************************************************************************************/
// Make la nouvelle valeur d'offset de la commande
function makeOffset($cmd, $allCmd, $tempMoyenneRef, $valueOffset, $periodicite, $logDebug, $timeLine) {
	$periodicite = $periodicite-1;
	$temperatureMoyenneCmd = round(scenarioExpression::averageBetween($cmd, "$periodicite hour ago", 'now'), 2);

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
	if ($tempMoyenneRef == 0 || $temperatureMoyenneCmd == 0) {
		mg::message($logDebug, "*** ERRO R *** ".mg::toHuman('#'.$cmd.'#')." sur la/les Températures moyennes ref/Comd : $tempMoyenneRef/$temperatureMoyenneCmd");
		return;
	}
	$newCorrection = round(/*$oldCorrection +*/ ($tempMoyenneRef - $temperatureMoyenneCmd), 2);
	if ( $newCorrection >= 0) { $newCorrection = "+$newCorrection"; }
	elseif ($newCorrection == 0) { $newCorrection = "+0.0"; }

	//  Recalcul de la chaine 'ValueOffset'
	$newValueOffset = "$baseValueOffset$newCorrection";

	mg::message($timeLine, mg::toHuman("#$cmd#")." - tempRef/tempCmd : $tempMoyenneRef/$temperatureMoyenneCmd (sur $periodicite heures) - old/New Correction : $oldCorrection/$newCorrection - ValueOffset : $newValueOffset");

	// **************************************
	// BIEN CONTROLER LE LOG AVANT D'ENLEVER LES REM.)
	// **************************************
/*	// Enregistrement des nouveaux offsets */
	$allCmd->setConfiguration('calculValueOffset', $newValueOffset);
	$allCmd->save();
}

// *******************************************************************************************************************/
// ********************** Lit la dernière valeur et les dates associées de la commande du résumé *********************/
// *******************************************************************************************************************/
function cmdIsOK($cmd, $tempResume, &$lastComm, &$pcEcart, &$valCmd, $enable) {
//	mg::debug(0);
	$valCmd = mg::getCmd($cmd, '', $collectDate, $valueDate);
	$lastComm = round(((time() - $collectDate)/60));

	$pcEcart = abs(round(($tempResume-$valCmd)/$tempResume*100, 1));
//	mg::debug();
	mg::message('', "Last comm $lastComm mn - $pcEcart % ($tempResume/$valCmd) - enabled : $enable");
}

?>