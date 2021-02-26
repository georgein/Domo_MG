<?php
/**********************************************************************************************************************
Luminosité Salon - 59
Calcul le flag Jour/Soir/Nuit.
Calcul le flag Jour/Soir/Nuit.
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

// Paramètres :
	$logTimeLine = mg::getParam('Log', 'timeLine');
	$seuilLumSalon = mg::getParam('Lumieres', 'seuilLumSalon');	// Seuil luminosité ambiante de passage à l'état (1) "soir".
	$seuilNuitSalon = mg::getParam('Lumieres', 'seuilNuitSalon');	// Seuil de luminosité de passage à l'état (2) de nuit totale du salon.
	$seuilNbMvmt = mg::getParam('Lumieres', 'seuilNbMvmt');
	$intensiteMininimum = mg::getParam('Lumieres', 'intensiteMininimum');
	$ambianceDefaut = mg::getParam('Lumieres', 'ambianceDefaut');

/**********************************************************************************************************************/
/**********************************************************************************************************************/
/**********************************************************************************************************************/
//$nuitSalon = 1;
	$oldNuitSalon = $nuitSalon;
	
if (mg::getCmd($infCinemaEtat)) { return; }

// SOIR
if ( ($nuitSalon == 2 && $nbMvmt >= $seuilNbMvmt)
		|| ($nuitSalon != 1 && $lumSalon >= $seuilNuitSalon*1.25 && $lumSalon <= $seuilLumSalon)) {
	$nuitSalon = 1;
	$message = "NuitSalon - Passage à SoirSalon (1).";
}
// JOUR
elseif ($nuitSalon != 0 && $lumSalon >= $seuilLumSalon*1.25) {
	$nuitSalon = 0;
	$message = "NuitSalon - Passage à JourSalon (0).";
}
// NUIT
if ($lumSalon < $seuilNuitSalon && $nuitExt) {
	$nuitSalon = 2;
	$message = "NuitSalon - Passage à NuitSalon (2).";
}

// Si changement
if ($nuitSalon != $oldNuitSalon) {
	mg::setVar('NuitSalon', $nuitSalon);
	mg::Message($logTimeLine, $message);
	sleep(180); // Pour éviter yoyo sur passage à nuit et nbMvmt
}

// Allumage le soir et pas en alarme
if (!$alarme && $nuitSalon == 1) {
	if ($etatLumiere < $intensiteMininimum && $nbMvmt >= $seuilNbMvmt) {
		mg::setCmd($equipEcl, 'Lampe Générale Slider', $intensiteMininimum);
		mg::setCmd($equipEcl, 'Lampe Ambiance Slider', $ambianceDefaut);
	}
// Extinction sinon	
} else {
	mg::setCmd($equipEcl, 'Lampe Générale Slider', 0);
}

?>