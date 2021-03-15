<?php
/**********************************************************************************************************************
Veille PC_MG - 126

Si NuitSalon == 2 et Cinéma arrété et Paramètrages OK :
	Met en veille le PC de MG.
Sinon Relance le PC-MG.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
// $infNbMvmtSalon, $infCinemaEtat, $equipPcMg

// N° des scénarios :

//Variables :
	$nuitSalon = mg::getVar('NuitSalon');
	$alarme = mg::getVar('Alarme');
	$lastMvmt = round(mg::lastMvmt($infNbMvmtSalon, $nbMvmt)/60);
	$etatCinema = mg::getCmd($infCinemaEtat);
	$puissancePcMg = mg::getCmd($equipPcMg, 'Puissance');
	$heureReveil = mg::getVar('_Heure_Reveil');

// Paramètres :
	$logTimeLine = mg::getParam('Log', 'timeLine');
	$timingExtinctionPC = mg::getParam('Confort', 'timingExtinctionPC');	// Temps sans mouvement (en mn)  avant extinction

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
if ($timingExtinctionPC <= 0) { return; }
mg::setCron('', time() + $timingExtinctionPC*60);
//mg::setCron('', "*/$timingExtinctionPC * * * *");

if ($alarme || ( $puissancePcMg > 10 && $nuitSalon == 2 && !$etatCinema && $lastMvmt >= $timingExtinctionPC )) {
	// ------------------------------------------------------------------------------------------------------------
	mg::messageT('', "! ARRET INFORMATIQUE");
	// ------------------------------------------------------------------------------------------------------------
	mg::eventGhost('Veille_Prolongee', 'PC-MG');

} elseif (!$alarme && $puissancePcMg < 10 && $nuitSalon != 2 && Time() >= ($heureReveil - 1800) && $nbMvmt) {
	// ------------------------------------------------------------------------------------------------------------
	mg::messageT('', "! REMISE EN ROUTE INFORMATIQUE");
	// ------------------------------------------------------------------------------------------------------------
	// Réveil PC
	mg::WakeOnLan('PC-MG');
	mg::Message($logTimeLine, "Informatique - Remise en route.");
}

?>