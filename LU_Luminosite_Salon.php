<?php
/**********************************************************************************************************************
Luminosité Salon - 59

Calcul le flag Jour/Soir/Nuit.
Calcul le flag nePasDeranger.
Desactive l'éclairage du salon la journée et la nuit si éteint, l'active le soir (NuitSalon == 1)
**********************************************************************************************************************/
// Infos, Commandes et equipements :
//	$equipEcl, $equipLumSalon, $infCinemaEtat
//  $infNbMvmtSalon

// N° des scénarios :

//Variables :
	$lastMvmt = round(mg::lastMvmt($infNbMvmtSalon, $nbMvmt)/60);

	$alarme = mg::getVar('Alarme');
	$nuitSalon = mg::getVar('NuitSalon');
	$nuitExt = mg::getVar('NuitExt');
	$lumSalon = mg::getCmd($equipLumSalon, 'Luminosité');
	$etatLumiere = mg::getCmd($equipEcl, 'Lampe Générale Etat');
	$heureReveil = mg::getVar('heureReveil');
	$timeVoletsNuit = mg::getParam('Volets', 'timeVoletsNuit');

// Paramètres :
	$logTimeLine = mg::getParam('Log', 'timeLine');
	$seuilLumSalon = mg::getParam('Lumieres', 'seuilLumSalon');	// Seuil luminosité ambiante de passage à l'état (1) "soir".
	$seuilNuitSalon = mg::getParam('Lumieres', 'seuilNuitSalon');	// Seuil de luminosité de passage à l'état (2) de nuit totale du salon.
	$seuilNbMvmt = 1;// mg::getParam('Lumieres', 'seuilNbMvmt');
	$intensiteMininimum = mg::getParam('Lumieres', 'intensiteMininimum');
	$ambianceDefaut = intval(mg::getParam('Lumieres', 'ambianceDefaut'));

// ********************************************************************************************************************
// ********************************************************************************************************************
// ********************************************************************************************************************

$oldNuitSalon = $nuitSalon;

// NE PAS DERANGER
$nePasDeranger = mg::TimeBetween(strtotime($timeVoletsNuit), time(), $heureReveil);
mg::setVar('nePasDeranger', $nePasDeranger);

if (mg::getCmd($infCinemaEtat)) { return; }

// SOIR
if ( ($nuitSalon == 2 && $nbMvmt >= $seuilNbMvmt)
		|| ($nuitSalon != 1 && ($lumSalon >= $seuilNuitSalon*1.25 && $lumSalon <= $seuilLumSalon))) {
	$nuitSalon = 1;
	$message = "NuitSalon - Passage à SoirSalon (1).";
}
// JOUR
elseif ($nuitSalon > 0 && $lumSalon >= $seuilLumSalon*1.35) {
	$nuitSalon = 0;
	$message = "NuitSalon - Passage à JourSalon (0).";
}
// NUIT
if ($lumSalon < $seuilNuitSalon && $nuitExt) {
	$nuitSalon = 2;
	$message = "NuitSalon - Passage à NuitSalon (2).";
}

//$nuitSalon = 1; // POUR DEBUG //////////////////////////////

// ********************************************************************************************************************
// *************************************************** SI CHANGEMENT **************************************************
// ********************************************************************************************************************
if ($nuitSalon != $oldNuitSalon) {
	mg::setVar('NuitSalon', $nuitSalon);
	if ($nuitSalon == 2 || $oldNuitSalon == 2) { mg::Message($logTimeLine, $message); }

	// ********** ALARME DE NUIT **********
	$mode = mg::getCmd($equipAlarme, 'Mode');
	// Activation
	$statutAlarme = mg::getCmd($equipAlarme, 'Statut');
	if ($nuitSalon == 2 && $statutAlarme == 0) {
		mg::Message("$logAlarme/mgDomo", "Alarme - Activation de l'alarme 'Nuit'.");
		mg::setCmd($equipAlarme, 'Activation Nuit');
	}
	// Désactivation
	elseif ($nuitSalon != 2 && $mode == 'Activation Nuit' && $statutAlarme == 1) {
		mg::Message("$logAlarme/mgDomo", "Alarme - Désactivation de l'alarme 'Nuit'.");
		mg::setCmd($equipAlarme, 'Désactiver');
	}

	// ********** ALLUMAGE DU SOIR **********
	if ($alarme != 1 && $nuitSalon == 1) {
			mg::setCmd($equipEcl, 'Lampe Ambiance Slider', $ambianceDefaut);
			mg::setCmd($equipEcl, 'Lampe Générale Slider', $intensiteMininimum);
	// Extinction sinon
	} else {
		mg::setCmd($equipEcl, 'Lampe Générale Slider', 0);
		sleep(120); // Pour éviter yoyo sur passage à nuit et nbMvmt
	}
}

?>