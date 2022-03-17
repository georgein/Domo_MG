<?php
/**********************************************************************************************************************
DoorBird - 133
Gestion de DoorBird

flux video : http://192.168.2.2/bha-api/video.cgi?http-user=ghbbgw0001&http-password=jyf5KEtgDD
snaphot url : http://192.168.2.2/bha-api/image.cgi?http-user=ghbbgw0001&http-password=jyf5KEtgDD
**********************************************************************************************************************/

//Infos, Commandes et Equipements :
	// $equipJC_PC_MG, $equipJC_JPI

// N° des scénarios :

//Variables :

// Paramètres :
	$nePasDeranger = mg::getVar('nePasDeranger', 0);

	$camJC = 369;
	$salonJC = 220;
	
	$designPrincipal = mg::getParam('Media', 'designGeneral');
	$designCam = 24;

	$volumeSonnette = 80;					// Volume du son de la sonnette
	$sonnettePorte = 'sonnettePorte.mp3';
	$jingleMvmt = 'jingle_07.mp3';
	$volumeMvmt = 10;
	$timerRetour = 2;						// Timer en mn avant retour au design principal
//	$timer = 15;							// Timer en mn normal du cron

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
mg::setCron('', "*/5 * * * *");
//		mg::setCmd($equipJC_PC_MG, 'Afficher page', $salonJC, $salonJC);

// Rétablissement de l'IP dans les param de Doorbird et de Caméra
$camIP = mg::getValSql('_tabUsers', 'Cam Doorbird', '', 'IP'); 
if ($camIP) {
	mg::ConfigEquiLogic('doorbird', 'DoorBird Ensues', 'addr', $camIP);
	mg::ConfigEquiLogic('camera', 'Cam DoorBird', 'ip', $camIP);
}

// Rien à signaler
if (mg::declencheur('user') || mg::declencheur('schedule')) {
	mg::MessageT('', "! RIEN A SIGNALER");
	if (mg::getVar('_designActif') != $designPrincipal) {
		mg::setVar('_designActif', $designPrincipal);
			
		// mg::JPI('DESIGN', $designPrincipal);
				
		mg::setCmd($equipJC_PC_MG, 'Afficher page', $salonJC, '');
		mg::setCmd($equipJC_JPI, 'Afficher page', $salonJC, '');   
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
		mg::setVar('_designActif', $designCam);
		mg::setCron('', time() + $timerRetour*60);
		
		// mg::JPI('DESIGN', $designCam);

		mg::setCmd($equipJC_PC_MG, 'Afficher page', $camJC, '');
		mg::setCmd($equipJC_JPI, 'Afficher page', $camJC, '');
	}
}

?>