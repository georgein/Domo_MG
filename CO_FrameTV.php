<?php
/**********************************************************************************************************************
FrameTV - 187

Gère le ON/Off de la Frame TV selon la présence dans le salon et NuitSalon

**********************************************************************************************************************/
// Infos, Commandes et Equipements :
	// $infCinema, $equipFrameTV_OnOff, $equipTvDomSamsung

// N° des scénarios :

//Variables :
	$nuitSalon = mg::getVar('NuitSalon');
	$lastMvmt = round(mg::lastMvmt($infMvmt, $nbMvmt)/60);
	$cinema = mg::getCmd($infCinema);
	$FrameTV_Etat = mg::getCmd($equipFrameTV_OnOff, 'Etat');
	
// Paramètres :
	$timingFrameTV = mg::getParam('Confort', 'timingFrameTV');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/

if (!$FrameTV_Etat && $nuitSalon != 2 && $nbMvmt > 1) {
	// =================================================================================================================
	mg::MessageT('', "! ALLUMAGE TV - lastMvmt : $lastMvmt");
	//=================================================================================================================
	mg::setCmd($equipFrameTV_OnOff, 'On');
	mg::setCron('', time() + $timingFrameTV*60);
} 

if ($FrameTV_Etat && !$cinema && ($nuitSalon == 2 || $lastMvmt >= $timingFrameTV)) {
	//=================================================================================================================
	mg::MessageT('', "! ARRET DE LA TV - lastMvmt : $lastMvmt");
	//=================================================================================================================
	mg::setCmd($equipFrameTV_OnOff, 'Off');
}

?>