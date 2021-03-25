<?php
/**********************************************************************************************************************
DoorBird - 133
Gestion de DoorBird
**********************************************************************************************************************/

//Infos, Commandes et Equipements :
	// $infAffDoorbird

// N° des scénarios :

//Variables :

// Paramètres :
	$heureReveil = mg::getVar('_Heure_Reveil');
	$timeVoletsNuit = mg::getParam('Volets', 'timeVoletsNuit');
	$designCam = 24;
	$designPrincipal = mg::getParam('Media', 'designGeneral');

	$volumeSonnette = 80;					// Volume du son de la sonnette
	$sonnettePorte = 'sonnettePorte.mp3';
	$jingleMvmt = 'jingle_07.mp3';
	$volumeMvmt = 10;
	$timerRetour = 1;						// Timer mn avant retour au design principal
	$timer = 5;								// Timer mn normal du cron

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
mg::setCron('', time() + $timer*60);

// Rétablissement de l'IP dans les param de Doorbird et de Caméra
$camIP = mg::getVar('tabUser')['Cam Doorbird']['IP'];
if ($camIP) {
	mg::ConfigEquiLogic('doorbird', 'DoorBird Ensues', 'addr', $camIP);
	mg::ConfigEquiLogic('camera', 'Cam DoorBird', 'ip', $camIP);
}

// Rien à signaler
if (mg::declencheur('user') || mg::declencheur('schedule')) {
	mg::MessageT('', "! RIEN A SIGNALER");
	if (mg::getVar('_designActif') != $designPrincipal) {
		mg::JPI('DESIGN', $designPrincipal);
		mg::setVar('_designActif', $designPrincipal);
	}
	
} elseif (mg::getCmd(mg::declencheur())) {
	// Sonnette
	if (mg::declencheur('Sonnerie')) {
		mg::MessageT('', "! SONNETTE ENTREE");
		mg::GoogleCast ('PLAY', $sonnettePorte, $volumeSonnette);
	}
	// sinon Mouvement
	if (mg::declencheur('Mouvement') && mg::TimeBetween(strtotime($timeVoletsNuit), time(), $heureReveil)) {
		mg::MessageT('', "! MOUVEMENT ENTREE");
//		mg::GoogleCast ('PLAY', $jingleMvmt, $volumeMvmt);
	}
	// Affichage cam sur JPI
	if (mg::getVar('_designActif') != $designCam) {
		mg::JPI('DESIGN', $designCam);
		mg::setVar('_designActif', $designCam);
		mg::setCron('', time() + $timerRetour*60);
	}
}

?>