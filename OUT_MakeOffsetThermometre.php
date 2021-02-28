<?php
/**********************************************************************************************************************
MakeOffsetThermometre - 178

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
	$correction = +0.0;
	
	$TempMoyenneRef = round(scenarioExpression::averageBetween($infSalonRefRfx, "$nbHeuresMoyenne hour ago", 'now'), 1);
	$TempSalonRefRfx = round(scenarioExpression::averageBetween($infTempRef, "$nbHeuresMoyenne hour ago", 'now'), 1);
	
	$tempRef = $TempSalonRefRfx + $correction;
	
	mg::message('', "! Température moyenne sur les dernières $nbHeuresMoyenne heures : TempMoyenneRef : $TempMoyenneRef - TempSalonRefRfx : $TempSalonRefRfx - Correction : $correction - tempRef : $tempRef");
	
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
				$cmdHumanName = $cmd->getHumanName();
				$eqLogicCmd = $cmd->getId();
				$valueOffset = $cmd->getConfiguration('calculValueOffset'); 
				$historizeMode = $cmd->getConfiguration('historizeMode'); 
				$historyPurge = $cmd->getConfiguration('historyPurge'); 

				
				// Sélection des commandes à traiter
				preg_match("#$exclude#i", "$type $cmdHumanName", $foundExclu);
				if (isset($foundExclu[0])) { continue; }
				if (mg::extractPartCmd($cmdHumanName, 3) != $typeCmd) { continue; }
				if (mg::extractPartCmd($cmdHumanName, 1) != $zone) { continue; }
				
				// Calcul température et moyenne
//				mg::debug(0); $temperature = mg::getCmd($cmdHumanName); mg::debug();
				$temperatureMoyenne = round(scenarioExpression::averageBetween($eqLogicCmd, "$nbHeuresMoyenne hour ago", 'now'), 1);
				if ($temperatureMoyenne< 0.9*$tempRef) { continue; } // Si mauvaise moyenne
				
				// Lecture de la correction actuelle
				$oldCorrection = 0;
				$regex = '.*([+--][\d]*.[\d]*)';
				preg_match("/$regex/ui", $valueOffset, $found);
				if (@iconv_strlen($found[1]) > 1) {
					$oldCorrection = trim($found[1]);

				// Calcul de la novelle correction à appliquer
				$newCorrection =  0;
				$newCorrection = round($oldCorrection + ($tempRef - $temperatureMoyenne), 1);
				 mg::message('', "0 **$oldCorrection** **$newCorrection**");
				if ( $newCorrection >= 0) { $newCorrection = "+$newCorrection"; }
				} 
//				mg::message('', "1 **$oldCorrection** **$newCorrection**");
				
				//  mise en forme et recalcul de la chaine 'alueOffset'
				if ( $valueOffset == '') { $valueOffset = '(#value#)'; }
				$newValueOffset = str_replace($oldCorrection,  $newCorrection, $valueOffset); 
				
				mg::message('',"$type - $cmdHumanName - tempRef / temp : $tempRef / $temperatureMoyenne - old / NewCorrection : $oldCorrection / $newCorrection - Offset : $valueOffset => newOffset : $newValueOffset");
				
				// **************************************
				// BIEN CONTROLER LE LOG AVANT D'ENLEVER LES REM. NE PAS LANCER DEUX FOIS DE SUITE (CORRECTION CUMULEES !!! )
				// **************************************
/*				// ENregistrement des nouveaux offset
				$cmd->setConfiguration('calculValueOffset', $newValueOffset); 
				$cmd->save();
*/

			}
		}
	}

?>