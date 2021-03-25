<?php
/**********************************************************************************************************************
FrameTV - 187

Gère le ON/Off de la Frame TV selon la présence dans le salon et NuitSalon

**********************************************************************************************************************/
// Infos, Commandes et Equipements :
	// $infCinema

// N° des scénarios :

//Variables :
	$nuitSalon = mg::getVar('NuitSalon');
	$lastMvmt = round(mg::lastMvmt($infMvmt, $nbMvmt)/60);
	$cinema = mg::getCmd($infCinema);
	$frameTV = -1;
	
// Paramètres :
	$timingFrameTV = mg::getParam('Confort', 'timingFrameTV');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
if (mg::declencheur('Frame TV')) {
	$frameTV = mg::getCmd(mg::declencheur());
}

mg::setCron('', time() + $timingFrameTV*60);

if ($nuitSalon != 2 && ($nbMvmt || $frameTV == 1)) {
	// =================================================================================================================
	mg::MessageT('', "! ALLUMAGE TV - lastMvmt : $lastMvmt");
	//=================================================================================================================
	if ($frameTV == 1) { mg::frameTV('Frame TV', 'Salon', 'hdmi'); } 
	else { mg::frameTV('Frame TV', 'Salon', 'on'); }
	
} elseif (!$cinema && ($nuitSalon == 2 || $lastMvmt >= $timingFrameTV) || $frameTV == 0) {
	//=================================================================================================================
	mg::MessageT('', "! ARRET DE LA TV - lastMvmt : $lastMvmt");
	//=================================================================================================================
	mg::frameTV('Frame TV', 'Salon', 'off');
}

?>