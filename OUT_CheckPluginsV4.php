<?php
/**********************************************************************************************************************
_test_1 - 178

Reclcul les offset des commandes sélectionner (voir les variables) par raport à une température de ref
L'écriture de l'offset est en rem (bas du programme) par sécurité
NB : NE JAMAIS LANCER PLUSIEURS FOIS, LES CORRECTIONS SONT CUMULATIVES, LE DELAIS MINIMUM EST CELUI DE $nbHeuresMoyenne
**********************************************************************************************************************/

// Infos, Commandes et Equipements =>

// N° des scénarios =>

// Variables =>

// Paramètres =>

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
	$plugin = '*'; //'openzwave';
	$typeCmd = 'Température';
	$zone = 'Salon';
	$exclude = 'virtual|Résumé|frigo|congél'; 
	$nbHeuresMoyenne = 8;
	$tempRef = 24.1;
//	$infTempRef = '#[Salon][Thermomètre Salon][Température]#';

	$TempMoyenneRef = round(scenarioExpression::averageBetween($infTempRef, "$NbHeuresMoyenne hour ago", 'now'), 1);
	mg::message('', "! Température moyenne du thermomètre de référence sur les dernières $nbHeuresMoyenne heures : $TempMoyenneRef");
	
	$eqLogics = eqLogic::all();
	// Parcours des équipements
	foreach($eqLogics as $eqLogic) {
		$ID = $eqLogic->getId();
		$type = strtolower($eqLogic->getEqType_name()); 
		$isEnabled = $eqLogic->getIsEnable();
		if (($type != $plugin && $plugin != '*')  || !$isEnabled) { continue; }
		$allCmds = $eqLogic->getCmd();
		if (count($allCmds) > 0) {
			// Parcours des commandes
			foreach($allCmds as $cmd) {
				$humanName = $cmd->getHumanName();
				$eqLogicCmd = $cmd->getId();
				$valueOffset = $cmd->getConfiguration('calculValueOffset'); 
				$historizeMode = $cmd->getConfiguration('historizeMode'); 
				$historyPurge = $cmd->getConfiguration('historyPurge'); 

				
				// Sélection des commandes à traiter
				preg_match("#$exclude#i", "$type $humanName", $foundExclu);
				if (isset($foundExclu[0])) { continue; }
				if (mg::extractPartCmd($humanName, 3) != $typeCmd) { continue; }
				if (mg::extractPartCmd($humanName, 1) != $zone) { continue; }
				
				// Calcul température et moyenne
				mg::debug(0); $temperature = mg::getCmd($humanName); mg::debug();
				$temperatureMoyenne = round(scenarioExpression::averageBetween($eqLogicCmd, "$NbHeuresMoyenne hour ago", 'now'), 1);
				
				// Lecture de la correction actuelle
				$oldCorrection = 0;
				$regex = '.*([+--][\d]*.[\d]*)';
				preg_match("/$regex/ui", $valueOffset, $found);
				if (@iconv_strlen($found[1]) > 1) {
					$oldCorrection = trim($found[1]);
				} 
				if ($oldCorrection >= 0) { $oldCorrection = "+$oldCorrection"; }

				// Calcul de la novelle correction à appliquer
				$newCorrection =  0;
				$newCorrection = round($oldCorrection + ($tempRef - $temperature), 1);
				if ( $newCorrection >= 0) { $newCorrection = "+$newCorrection"; }
				
				//  mise en forme et recalcul de la chaine 'alueOffset'
				if ( $valueOffset == '') { $valueOffset = '(#value#)'; }
				if ($oldCorrection == 0) { $valueOffset = "$valueOffset+0"; }
				$newValueOffset = str_replace($oldCorrection,  $newCorrection, $valueOffset); 
				
				mg::message('',"$type - $humanName - tempRef : $tempRef - temp : $temperature/$temperatureMoyenne - Offset : $valueOffset => newOffset : $newValueOffset");
				
				// **************************************
				// BIEN CONTROLER LE LOG AVANT D'ENLEVER LES REM. NE PAS LANCER DEUX FOIS DE SUITE (CORRECTION CUMULEES !!! )
				// **************************************
				// ENregistrement des nouveaux offset
//				$cmd->setConfiguration('calculValueOffset', $newValueOffset); 
//				$cmd->save();

			}
		}
	}

?>