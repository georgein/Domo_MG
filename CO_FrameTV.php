<?php
/**********************************************************************************************************************
FrameTV - 187

Gère le ON/Off de la Frame TV selon la présence dans le salon et NuitSalon

**********************************************************************************************************************/
// Infos, Commandes et Equipements :
	// $infMvmt, $infTV_Sendkey, $infCinema
	// $equipSmartThings, $equipFrameTV

// N° des scénarios :

//Variables :
	$nuitSalon = mg::getVar('NuitSalon');
	$lastMvmt = round(mg::lastMvmt($infMvmt, $nbMvmt)/60);
	$cinema = mg::getCmd($infCinema);

// Paramètres :
	$timingFrameTV = mg::getParam('Confort', 'timingFrameTV');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
mg::setCron('', time() + $timingFrameTV*60);

if ($nuitSalon != 2 && $nbMvmt && (mg::getCmd($equipSmartThings, 'Santé') != 'En ligne' || !mg::getCmd($equipFrameTV, 'Puissance') < 5)) {
	// =================================================================================================================
	mg::MessageT('', "! ALLUMAGE TV - lastMvmt : $lastMvmt");
	//=================================================================================================================
	if (!mg::getCmd($equipFrameTV, 'Etat')) { mg::setCmd($equipFrameTV, 'On'); }
	sleep(5);
	mg::WakeOnLan('Frame TV');
	sleep(5);
	mg::setCmd($equipSmartThings, 'Allumer');
		//sleep(3);
		//	mg::setCmd($infTV_Sendkey, '', 'KEY_POWER'); // **********************************
}

elseif (!$cinema && ($nuitSalon == 2 || $lastMvmt > $timingFrameTV) && mg::getCmd($equipSmartThings, 'Sous tension')) {
	//=================================================================================================================
	mg::MessageT('', "! ARRET DE LA TV - lastMvmt : $lastMvmt");
	//=================================================================================================================
	mg::setCmd($equipSmartThings, 'Eteindre');
//	sleep(2);
//	if (mg::getCmd($equipFrameTV, 'Etat')) { mg::setCmd($equipFrameTV, 'Off'); }
}

?>