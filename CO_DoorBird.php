<?php
/**********************************************************************************************************************
DoorBird - 133
Gestion de DoorBird

flux video : http://192.168.2.2/bha-api/video.cgi?http-user=ghbbgw0001&http-password=jyf5KEtgDD
snaphot url : http://192.168.2.2/bha-api/image.cgi?http-user=ghbbgw0001&http-password=jyf5KEtgDD
**********************************************************************************************************************/

//Infos, Commandes et Equipements :
	// $infAffDoorbird

// N° des scénarios :

//Variables :

// Paramètres :
	$nePasDeranger = mg::getVar('nePasDeranger', 0);
	$designCam = 24;
	$designPrincipal = mg::getParam('Media', 'designGeneral');

	$volumeSonnette = 80;					// Volume du son de la sonnette
	$sonnettePorte = 'sonnettePorte.mp3';
	$jingleMvmt = 'jingle_07.mp3';
	$volumeMvmt = 10;
	$timerRetour = 1;						// Timer en mn avant retour au design principal
	$timer = 15;							// Timer en mn normal du cron

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
		if (mg::getVar('_designActif') != $designPrincipal) {
			mg::setVar('_designActif', $designPrincipal);
		}
	}

// Déclenchement Doorbird	
} elseif (mg::getCmd(mg::declencheur())) {
	// Sonnette
	if (mg::declencheur('Sonnerie')) {
		mg::MessageT('', "! SONNETTE ENTREE");
		mg::GoogleCast ('PLAY', $sonnettePorte, $volumeSonnette);
	}
	
	// sinon Mouvement
	elseif (mg::declencheur('Mouvement') && !$nePasDeranger) {
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