<?php
/**********************************************************************************************************************
SY_heal_Resumes - 190

Activation / Désactivation des éléments de résumés selon :
	L'ancienneté du 'valueDate' ('timeOutDown' pour la désactivation ou 'timeOutUp' pour la réactivation)
	Et leurs écarts en % à la valeur moyenne du résumé selon le tableau ci dessous.
NB : La première commande du résumé n'est JAMAIS désactivée par sécurité.
NB : si pcEcartMax == 0, on ne lance pas la la gestion des résumés.

Correction des offset des commandes si l'heure courante est un multiple de 'nbHeures et la minute courante < au 'cron'
NB : si 'nbHeures' == 0, on ne lance pas la correction
NB : Ne JAMAIS lancer le traitement à moins de 'nbHeures' d'intervalle sinon cumul des corrections !!!!
**********************************************************************************************************************/
// Infos, Commandes et Equipements :

// N° des scénarios :

// Variables de Ctrl des équipements

	// Paramètres :
	//Tableau de paramètrages des Résumés à traiter
	$tabResumes = array(
array('zone'=>'Salon', 'name'=>'Température', 'type'=>'temperature', 'timeoOutDown'=>'15', 'timeoOutUp'=>'5', 'pcEcartMax'=>'3.0', 'nbHeures'=>'8', 'correction' => '+0.0'),
array('zone'=>'Chambre', 'name'=>'Température', 'type'=>'temperature', 'timeoOutDown'=>'15', 'timeoOutUp'=>'5', 'pcEcartMax'=>'3.0', 'nbHeures'=>'8', 'correction'=>'+0.0'),
array('zone'=>'RdCSdB', 'name'=>'Température', 'type'=>'temperature', 'timeoOutDown'=>'15', 'timeoOutUp'=>'5', 'pcEcartMax'=>'3.0', 'nbHeures'=>'8', 'correction'=>'+0.0'),
	 );

	$cron = 5;
	$logDebug = 'Log:/_DEBUG'; //mg::getParam('Log', 'timeLine');

// ********************************************************************************************************************
// ********************************************************************************************************************
mg::setCron('', "*/$cron * * * *");

// Lecture du tableau de paramètrage
foreach($tabResumes as $n => $detailsResumes) {
	$zone = $detailsResumes['zone'];
	$name = $detailsResumes['name'];
	$type = $detailsResumes['type'];
	$timeOutDown = $detailsResumes['timeoOutDown'];
	$timeOutUp = $detailsResumes['timeoOutUp'];
	$pcEcartMax = $detailsResumes['pcEcartMax'];
	$nbHeures = $detailsResumes['nbHeures'];
	$correction = $detailsResumes['correction'];

	$cmdResume = mg::toID("#[$zone][Résumé][$name]#");
	$valResume = mg::getCmd($cmdResume);
	$nbHeures_1 = $nbHeures -1;
	$valResumeMoyenne = round(scenarioExpression::averageBetween($cmdResume, "$nbHeures_1 hour ago", 'now'), 1) + $correction;
mg::message('', mg::toHuman($cmdResume));
	mg::messageT('', "! Traitement de $zone/$type avec timeOuts : $timeOutDown/$timeOutUp - pcEcartMax : $pcEcartMax - TempMoyenneRef : $valResumeMoyenne (sur $nbHeures heures)");

	$cdMakeOffset = ($nbHeures > 0 && mg::getTag('#heure#')%$nbHeures == 0 && mg::getTag('#minute#') < $cron) ? 1 : 0;
//	$cdMakeOffset = 1; // ************** POUR TEST **************
	
	ControleResumes($zone, $type, $timeOutDown, $timeOutUp, $pcEcartMax, $correction, $valResume, $valResumeMoyenne, $nbHeures, $cdMakeOffset, $logDebug);
}

// *******************************************************************************************************************/
// ************************************************ CONTROLE DU RESUME ***********************************************/
function ControleResumes($zone, $type, $timeOutDown, $timeOutUp, $pcEcartMax, $correction, $valResume, $valResumeMoyenne, $nbHeures, $cdMakeOffset, $logDebug) {
	// Extraction SQL de la configuration de l'objet
	$values = array();
	$sql  = "SELECT `configuration` FROM `object` WHERE `name` = '$zone'";
	$resultSql = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL);
	$configuration = json_decode($resultSql[0]['configuration'], true);

	// Parcours des cmd pour activation / désactivation dans le résumé
	$resultTypes = $configuration['summary'][$type];
	$cptCmd = 0;
	foreach ($resultTypes as $number => $details) {
		$cptCmd++;
		$cmd = trim($details['cmd'], '#');
		if ($pcEcartMax > 0) {
			$enable = $details['enable'];
			cmdIsOK($cmd, $valResume, $lastComm, $pcEcart, $valCmd, $enable);
			if ($valResume == 0 || $valCmd == 0) { 
				mg::message($logDebug, "*** ERROR *** ".mg::toHuman('#'.$cmd.'#')." sur la/les Températures ref/Comd : $valResume/$valCmd");
				continue; 
			}
			if (($lastComm > $timeOutDown || $pcEcart > $pcEcartMax) && $enable) {
				mg::messageT($logDebug, ". DESACTIVATION de la commande ".mg::toHuman('#'.$cmd.'#')." ($cmd) - last comm $lastComm mn - $pcEcart % ($valResume/$valCmd)");
				if ($cptCmd > 1) { $configuration['summary'][$type][$number]['enable'] = 0; } 
				else { mg::message ($logDebug, "*** ERRO R *** La première commande du Résumé ne peut pas être désactivée !!!");}
			} elseif ($lastComm <= $timeOutUp && $pcEcart <= $pcEcartMax && !$enable) {
				mg::messageT($logDebug, ". REACTIVATION de la commande ".mg::toHuman('#'.$cmd.'#')." ($cmd) - last comm $lastComm mn - $pcEcart % ($valResume/$valCmd)");
				$configuration['summary'][$type][$number]['enable'] = 1;
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
						makeOffset($cmd, $allCmd, $valResumeMoyenne, $valueOffset, $correction, $nbHeures, $logDebug); 
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
function makeOffset($cmd, $allCmd, $valResumeMoyenne, $valueOffset, $correction, $nbHeures, $logDebug) {
	$nbHeures = $nbHeures-1;
	$temperatureMoyenneCmd = round(scenarioExpression::averageBetween($cmd, "$nbHeures hour ago", 'now'), 1);
	
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

	mg::message($logDebug, mg::toHuman("#$cmd#")." - tempRef/tempCmd : $valResumeMoyenne/$temperatureMoyenneCmd - old/New Correction : $oldCorrection/$newCorrection - ValueOffset : $newValueOffset - correction : $correction");

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