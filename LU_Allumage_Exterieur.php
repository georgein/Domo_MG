<?php
/**********************************************************************************************************************
	Allumage Exterieur - 23
	Allume les lampes extérieure SI il fait nuit en cas de mouvememnt détecté.
	Programme l'extinction $timerLumExt mn plus tard si pas d'autres mouvements.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
// $tab_EquipLampes, $equipMeteoFrance
// $infNbMvmtExt
// $cmdEtatEclExt,$cmdVentFort

// N° des scénarios :

//Variables :
	$lastMvmt = round(mg::lastMvmt($infNbMvmtExt, $nbMvmt)/60);
	$ventFort = mg::getCmd($cmdVentFort);
	$etatLumCave = mg::getCmd($equipEclCave, 'Etat');
	
	$nuitExt = mg::getVar('NuitExt');

// Paramètres :
	$timerLumExt = mg::getParam('Lumieres', 'timerLumExt');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$action = 'Off'; 

if (mg::declencheur('Eclairages')) {
	if (mg::getCmd($cmdEtatEclExt)) { 
		$action = 'On'; } else { $action = 'Off'; }
	goto suite; 
}

// ==================================================== EXTINCTION =====================================================
if ( !$nuitExt || $ventFort || $lastMvmt >= $timerLumExt) { 
	$action = 'Off'; 
	mg::setCron('', '*/'.(3*$timerLumExt).' * * * *');
// ===================================================== ALLUMAGE ======================================================
} elseif ($nuitExt && ($nbMvmt > 0 || $etatLumCave)) { 
	$action = 'On'; 
	mg::setCron('', time() + $timerLumExt*60);
}

// ================================================ MODIF LAMPE GENERALE ===============================================
mg::setCmd(str_replace('Etat', $action, trim(mg::toHuman($cmdEtatEclExt), '#')));

suite:
// =============================================== PASSAGE DES COMMANDES ===============================================
mg::MessageT('', "! PASSAGE à $action des lampes");
for ($i = 0; $i < count($tab_EquipLampes); $i++) {
	if ($action == 'Off' || mg::getCmd($tab_EquipLampes[$i]) != ($action=='On' ? 1 : 0)) {
		mg::setCmd(str_replace(' Etat', " $action", trim(mg::toHuman($tab_EquipLampes[$i]), '#')));
	}
}
