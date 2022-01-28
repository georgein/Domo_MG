<?php
/**********************************************************************************************************************
Veille PC_MG - 126

Si NuitSalon == 2 et Cinéma arrété et Paramètrages OK :
	Met en Veille ou Veille_Prolongee le PC de MG.
Sinon Relance le PC-MG.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
// $infNbMvmtSalon, $infCinemaEtat, $equipPcMg

// N° des scénarios :

//Variables :
	$nuitSalon = mg::getVar('NuitSalon');
	$lastMvmt = round(mg::lastMvmt($infNbMvmtSalon, $nbMvmt)/60);
	$etatCinema = mg::getCmd($infCinemaEtat);
	$puissancePcMg = mg::getCmd($equipPcMg, 'Puissance');
	$heureReveil = mg::getVar('heureReveil');

// Paramètres :
	$logTimeLine = mg::getParam('Log', 'timeLine');
	$timingExtinctionPC = mg::getParam('Confort', 'timingExtinctionPC');	// Temps sans mouvement (en mn)  avant extinction

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
if ($timingExtinctionPC <= 0) { return; }
mg::setCron('', time() + $timingExtinctionPC*60);

if ($puissancePcMg > 20 && $nuitSalon == 2 && !$etatCinema && $lastMvmt >= $timingExtinctionPC ) {
	// ------------------------------------------------------------------------------------------------------------
	mg::messageT('', "! Mise en veille du PC-MG");
	// ------------------------------------------------------------------------------------------------------------
	mg::Message($logTimeLine, "Informatique - Mise en veille du PC-MG.");
	mg::eventGhost('Veille', 'PC-MG'); // Veille, Veille_Prolongee /////////////////////////////////////////////////////

} elseif ($puissancePcMg < 20 && $nuitSalon != 2 && Time() >= ($heureReveil) && $nbMvmt) {
	// ------------------------------------------------------------------------------------------------------------
	mg::messageT('', "! Réveil du PC-MG");
	// ------------------------------------------------------------------------------------------------------------
	mg::Message($logTimeLine, "Informatique - Réveil du PC-MG.");
	// Réveil PC
	mg::WakeOnLan('PC-MG');
}

?>