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
	mg::setCron('', "*/$timerLumExt * * * *");
	
$action = 'Off';

if (mg::declencheur('Eclairages')) {
	if (mg::getCmd($cmdEtatEclExt)) {
		$action = 'On'; } else { $action = 'Off'; }
	goto suite;
}

// ==================================================== EXTINCTION =====================================================
if ( !$nuitExt || $ventFort || $lastMvmt >= $timerLumExt) {
	$action = 'Off';
// ===================================================== ALLUMAGE ======================================================
} elseif ($nuitExt && ($nbMvmt > 0 || $etatLumCave)) {
	$action = 'On';
}

// ================================================ MODIF LAMPE GENERALE ===============================================
mg::setCmd(str_replace('Etat', $action, trim(mg::toHuman($cmdEtatEclExt), '#')));

suite:
// =============================================== PASSAGE DES COMMANDES ===============================================
mg::MessageT('', "! PASSAGE à $action des lampes");
for ($i = 0; $i < count($tab_EquipLampes); $i++) {
	if (mg::getCmd($tab_EquipLampes[$i]) != ($action=='On' ? 1 : 0)) {
		mg::setCmd(str_replace('Etat', "$action", trim(mg::toHuman($tab_EquipLampes[$i]), '#')));
	}
	usleep(0.5 * 1000000);
}
