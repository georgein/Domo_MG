<?php
/**********************************************************************************************************************
Cinema - 160
	Au démarrage : Alimente le DD, Les passerelles HDMI et le retroprojecteur, ensuite baisse les volets et met l'ambiance 'Cinéma' lorsque les lampes sont allumées complètement.
	A la fin : Eteint le vidéoprojecteur et ensuite Coupe l'alimentation du DD, des passerelles HDMI, puis lance VoletsNuit.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
	// $infCinemaEtat
	// $equipEcl

// N° des scénarios :
	$scenLuminositeSalon = 59;
	$scenAllumageSalon = 44;
	$scenVoletsJourNuit = 29;

//Variables :
	$nuitExt = mg::getVar('NuitExt');
	$nuitSalon = mg::getVar('NuitSalon');
	$timeVoletsNuit = mg::getParam('Volets', 'timeVoletsNuit');
	
// Paramètres :
	$logTimeLine = mg::getParam('Log', 'timeLine');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/

// ********************************************** MISE EN ROUTE DU CINEMA *********************************************
if (mg::getCmd($infCinemaEtat)) {
	//=================================================================================================================
	mg::MessageT('', "! MISE EN ROUTE DU CINEMA");
	//=================================================================================================================
	// Allumage TV
	mg::frameTV('Frame TV', 'Salon', 'hdmi');

	// Fermeture volets
	mg::setScenario($scenVoletsJourNuit, 'deactivate');
//	mg::unsetVar('_VoletGeneral');
	mg::VoletsGeneral( 'Salon', 'D', 1);

	// Ambiance lumière à 'Cinéma'
	mg::wait("scenario($scenAllumageSalon) == 0", 180);	
	mg::setCmd($equipEcl, 'Lampe Ambiance Slider', 3);
	mg::wait("scenario($scenAllumageSalon) == 0", 180);	

	mg::setScenario($scenAllumageSalon, 'deactivate');
	mg::setScenario($scenLuminositeSalon, 'deactivate');

	mg::Message($logTimeLine, "Cinéma - Mise en route.");

// ************************************************** ARRET DU CINEMA *************************************************
} else {
	//=================================================================================================================
	mg::MessageT('', "! ARRET DU CINEMA");
	//=================================================================================================================
	// Rallumage lumière
	mg::setVar('NuitSalon', 1);
	mg::setScenario($scenAllumageSalon, 'activate');
	sleep(5);
	mg::setCmd($equipEcl, 'Lampe Générale Slider', 50);
	mg::setCmd($equipEcl, 'Lampe Ambiance Slider', 1);
	mg::wait("scenario($scenAllumageSalon) == 0", 180);	
	
	sleep(10);
	mg::setScenario($scenLuminositeSalon, 'activate');
	mg::setScenario($scenLuminositeSalon, 'start');

	// On passe en mode 'Art'
mg::frameTV('Frame TV', 'Salon', 'art');

/*	if (!$nuitExt && time() < strtotime($timeVoletsNuit)) {
		mg::VoletsGeneral( 'Salon', 'M', 1);
	} else {
		mg::VoletsGeneral( 'Salon', 'D', 1);
	}*/

	// Rétablissement volets
	mg::unsetVar('_VoletGeneral'); // Pour 'forcer' le prochain mouvement de voletsGeneral
	mg::setScenario($scenVoletsJourNuit, 'activate');
	mg::setScenario($scenVoletsJourNuit, 'start');

	mg::Message($logTimeLine, "Cinéma - Arrêt.");
}

?>