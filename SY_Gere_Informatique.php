<?php
/**********************************************************************************************************************
Veille PC_MG - 126

Si NuitSalon == 2 et Cinéma arrété et Paramètrages OK :
	Met en veille le PC de MG.
	Coupe l'alimentation de la Frame TV.
Sinon Relance le PC-MG et la FrameTV.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
// $infNbMvmtSalon, $equipSmartThings, $infCinemaEtat, $equipPcMg, $equipFrameTV

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

// test
//	mg::eventGhost('Veille', 'PC-MG'); // Veille, Veille_Prolongee
//sleep(10);
//	mg::WakeOnLan('PC-MG');
//return;




if ($timingExtinctionPC <= 0) { return; }

if ($alarme || ( $puissancePcMg > 8 && $nuitSalon == 2 && !$etatCinema && $lastMvmt > $timingExtinctionPC )) {
	// ------------------------------------------------------------------------------------------------------------
	mg::messageT('', "! ARRET INFORMATIQUE");
	// ------------------------------------------------------------------------------------------------------------
	mg::eventGhost('Veille', 'PC-MG'); // Veille, Veille_Prolongee
	if (mg::getCmd($equipSmartThings, 'Sous tension')) { mg::setCmd($equipSmartThings, 'Éteindre'); }
	sleep(5);
	if (mg::getCmd($equipFrameTV, 'Etat')) { mg::setCmd($equipFrameTV, 'Off'); }
	mg::Message($logTimeLine, "Informatique - Arrèt.");

} elseif (!$alarme && $puissancePcMg < 8 && $nuitSalon != 2 && Time() >= ($heureReveil - 1800) && $lastMvmt < $timingExtinctionPC && $nbMvmt > 1) {
	// ------------------------------------------------------------------------------------------------------------
	mg::messageT('', "! REMISE EN ROUTE INFORMATIQUE");
	// ------------------------------------------------------------------------------------------------------------
	// Réveil PC
	mg::WakeOnLan('PC-MG');
//	if (!mg::getCmd($equipFrameTV, 'Etat')) { mg::setCmd($equipFrameTV, 'On'); }
//	sleep(5);
//	mg::WakeOnLan('Frame TV');
//	if (!mg::getCmd($equipSmartThings, 'Sous tension')) { mg::setCmd($equipSmartThings, 'Allumer'); }
	mg::Message($logTimeLine, "Informatique - Remise en route.");
} 

?>