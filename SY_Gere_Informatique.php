<?php
/**********************************************************************************************************************
Veille PC_MG - 126

	Si NuitSalon == 2 depuis plus de $timingExtinctionPC et puissance ordi > 5 et Cinéma arrété et Paramètrages OK :
		Met en Veille ou Veille_Prolongee le PC de MG.

**********************************************************************************************************************/

// Infos, Commandes et Equipements :
// $infCinemaEtat, $equipPcMg, $Ctrl_PCMg, $infNuitSalon

// N° des scénarios :

//Variables :
	$alarme = mg::getVar('Alarme');
	$nuitSalon = mg::getCmd($infNuitSalon, '', $valueDate, $collectDate);
	$lastNuitSalon = round((time() - $valueDate) / 60, 1);

// Paramètres :
	$logTimeLine = mg::getParam('Log', 'timeLine');
	$timingExtinctionPC = mg::getParam('Confort', 'timingExtinctionPC');	// Temps sans mouvement (en mn)  avant extinction

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
//mg::setCron('', "*/$timingExtinctionPC * * * *");

if (mg::getCmd($equipPcMg, 'Puissance') > 5 && !mg::getCmd($infCinemaEtat, 0) && (($nuitSalon == 2 && $lastNuitSalon >= $timingExtinctionPC) || $alarme) ) {
	// ------------------------------------------------------------------------------------------------------------
	mg::messageT('', "! Mise en veille du PC-MG");
	mg::Message($logTimeLine, "Informatique - Mise en veille du PC-MG.");
	// ------------------------------------------------------------------------------------------------------------
	mg::setCmd($Ctrl_PCMg, 'Hibernate'); // Suspend, Hibernate
}

?>