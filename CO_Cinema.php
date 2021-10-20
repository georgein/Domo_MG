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
	mg::message($logTimeLine, "Cinéma - Mise en route.");

	mg::frameTV('Frame TV', 'Salon', 'hdmi');

	// Fermeture volets
	mg::setScenario($scenVoletsJourNuit, 'deactivate');
	mg::VoletsGeneral( 'Salon', 'D');

	mg::setScenario($scenLuminositeSalon, 'deactivate');
	
	// Ambiance lumière à 'Cinéma'
	mg::wait("scenario($scenAllumageSalon) == 0", 180);	
	mg::setCmd($equipEcl, 'Lampe Ambiance Slider', 3);
	mg::wait("scenario($scenAllumageSalon) == 0", 180);	
	mg::setScenario($scenAllumageSalon, 'deactivate');

// ************************************************** ARRET DU CINEMA *************************************************
} else {
	//=================================================================================================================
	mg::MessageT('', "! ARRET DU CINEMA");
	//=================================================================================================================
	// Rallumage lumière
	mg::setVar('NuitSalon', 1);
	mg::setCmd($equipEcl, 'Lampe Générale Slider', 50);
	mg::setCmd($equipEcl, 'Lampe Ambiance Slider', 1);
//	sleep(5);
	mg::setScenario($scenAllumageSalon, 'activate');
	mg::setScenario($scenAllumageSalon, 'start');
	mg::wait("scenario($scenAllumageSalon) == 0", 180);	
	
	sleep(15);
	mg::setScenario($scenLuminositeSalon, 'activate');
	mg::setScenario($scenLuminositeSalon, 'start');

	// On passe en mode 'Art'
mg::frameTV('Frame TV', 'Salon', 'art');

	// Rétablissement volets
	mg::setScenario($scenVoletsJourNuit, 'activate');
	mg::setScenario($scenVoletsJourNuit, 'start');

	mg::message($logTimeLine, "Cinéma - Arrêt.");
}

?>