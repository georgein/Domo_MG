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
	$intensiteMininimum = mg::getParam('Lumieres', 'intensiteMininimum');
	$ambianceDefaut = intval(mg::getParam('Lumieres', 'ambianceDefaut'));

// ********************************************************************************************************************
// ********************************************************************************************************************
// ********************************************************************************************************************

$oldNuitSalon = $nuitSalon;

// NE PAS DERANGER
$nePasDeranger = mg::TimeBetween(strtotime($timeVoletsNuit), time(), $heureReveil);
mg::setVar('nePasDeranger', $nePasDeranger);

if (mg::getCmd($infCinemaEtat)) return;

// SOIR
if ( 	($nuitSalon == 2 && $nbMvmt > 1 /*&& $lastMvmt < 5*/) 
		|| ($nuitSalon != 1 && ($lumSalon >= $seuilNuitSalon*1.25 && $lumSalon <= $seuilLumSalon))) {
	$nuitSalon = 1;
	$message = "NuitSalon - Passage à SoirSalon (1).";
}

// JOUR
elseif ($nuitSalon > 0 && $lumSalon >= $seuilLumSalon*1.35) {
	$nuitSalon = 0;
	$message = "NuitSalon - Passage à JourSalon (0).";
	
	mg::message($logTimeLine, "$nbMvmt - ($lumSalon >= " . $seuilLumSalon*1.35 . ") - $message"); ////////////////////////////////////////
	
}

// NUIT
if ($lumSalon < $seuilNuitSalon && $nuitExt) {
	$nuitSalon = 2;
	$message = "NuitSalon - Passage à NuitSalon (2).";
}

//$nuitSalon = 1; // POUR DEBUG //////////////////////////////

// Activation ALARME DE NUIT 
if ( $alarme == 0 && $nuitSalon == 2) {
	mg::message($logTimeLine, "Alarme - Activation de l'alarme 'Nuit'.");
	mg::setCmd($equipAlarme, 'Activation Nuit');
}
// Désactivation ALARME DE NUIT 
elseif ($alarme == 1 && $nuitSalon != 2 /*&& mg::getCmd($equipAlarme, 'Mode') == 'Activation Nuit'*/) {
	mg::message($logTimeLine, "Alarme - Désactivation de l'alarme 'Nuit'.");
	mg::setCmd($equipAlarme, 'Désactiver');
}

// ******************************************** ON SORT SI PAS DE CHANGEMENT ******************************************
if ($nuitSalon == $oldNuitSalon) return;
	
mg::setVar('NuitSalon', $nuitSalon);
if ($nuitSalon == 2 || $oldNuitSalon == 2) { mg::message($logTimeLine, $message); }

if ($alarme < 2) {
	// ********** ALLUMAGE DU SOIR ET DU MATIN **********
	if ($nuitSalon == 1 && !$etatLumiere) {
		mg::setCmd($equipEcl, 'Lampe Ambiance Slider', $ambianceDefaut);
		mg::setCmd($equipEcl, 'Lampe Générale Slider', $intensiteMininimum);
	} //else {

	// Extinction sinon
	if ($nuitSalon != 1 && $etatLumiere) {
		mg::setCmd($equipEcl, 'Lampe Générale Slider', 0);
		sleep(120); // Pour éviter yoyo sur passage à nuit OU nbMvmt
	}
}

?>