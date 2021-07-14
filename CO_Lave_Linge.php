<?php
/**********************************************************************************************************************
Lave_Linge - 158
Envoi un SMS et un TTS en lorsque le lave linge à terminer et des rappels toutes les Periodicites mn si il n'a pas été arrété.
Messages inhibés la nuit et après TimeOut.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
//	$infPuissanceLaveLinge

// N° des scénarios :

//Variables :
	$seuilMarche = 5;				// Seuil de puissance au delà duquel le lave linge est supposé en route (5).
	$seuilStop = 2;				// Seuil de stop physique de la machine (1.45)

	$nuitSalon = mg::getVar('NuitSalon');

// Paramètres :
	$periodicite =	mg::getParam('Confort', 'periodeLaveLinge');
	$timeoutLaveLinge =	 mg::getParam('Confort', 'timeoutLaveLinge');
	$destinataires = mg::getParam('Confort', 'destinatairesLaveLinge');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$puissanceMoyenne = mg::getExp("average(#$infPuissanceLaveLinge#, $periodicite min)");

// Mise en route
if ($puissanceMoyenne > $seuilMarche && !mg::getVar('_laveLingeOn')) {
	mg::setVar('_laveLingeOn', 1);
}

// Lave linge terminé
if ($puissanceMoyenne <= $seuilMarche && $nuitSalon != 2 && mg::getVar('_laveLingeOn')) {
	if (mg::getVar('_laveLingeOn') == 1) {
		mg::Alerte('LaveLinge', $periodicite, $timeoutLaveLinge, $destinataires, 'Le lave linge est terminé');
		mg::message('', 'Le lave linge est terminé'); ////////////////////////////////////////////////////
		mg::setVar('_laveLingeOn', time()); // Mémo de l'heure de fin du LaveLinge

	// Rappel tous les $periodicite mn.
	} else if (mg::getVar("_alerteLaveLinge")) {
		mg::Alerte('LaveLinge');
	}
}

// Fin des Alertes et annulation process
if ($puissanceMoyenne <= $seuilStop && mg::getVar('_laveLingeOn')) {
	mg::unsetVar('_laveLingeOn');
	mg::Alerte('LaveLinge', -1);
	return;
}

?>