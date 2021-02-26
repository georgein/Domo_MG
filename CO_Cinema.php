<?php
/**********************************************************************************************************************
Cinema - 160
	Au démarrage : Alimente le DD, Les passerelles HDMI et le retroprojecteur, ensuite baisse les volets et met l'ambiance 'Cinéma' lorsque les lampes sont allumées complètement.
	A la fin : Eteint le vidéoprojecteur et ensuite Coupe l'alimentation du DD, des passerelles HDMI, puis lance VoletsNuit.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
	// $infCinemaEtat, $infTV_Sendkey
	// $equipEcl, $equipSmartThings

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
	mg::WakeOnLan('Frame TV');
	sleep(5);
	mg::setCmd($equipSmartThings, 'Allumer');
	sleep(3);
	
	// Choix du mode
	mg::setCmd($equipSmartThings, 'Changer de source dentrée', 'HDMI1');

	// Fermeture volets
	mg::setScenario($scenVoletsJourNuit, 'deactivate');
	mg::unsetVar('_VoletGeneral');
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
	mg::setCmd($equipEcl, 'Lampe Générale Slider', 50);
	mg::setCmd($equipEcl, 'Lampe Ambiance Slider', 1);
	mg::wait("scenario($scenAllumageSalon) == 0", 180);	

	// On repasse en mode 'Art'
	mg::setCmd($infTV_Sendkey, '', 'KEY_POWER');
	sleep(10);
	
	// On éteint la TV si nécessaire
	mg::setCmd($equipSmartThings, 'Éteindre');

	if (!$nuitExt && time() < strtotime($timeVoletsNuit)) {
		mg::VoletsGeneral( 'Salon', 'M', 1);
	} else {
		mg::VoletsGeneral( 'Salon', 'D', 1);
	}
	
	sleep(120);
	mg::setScenario($scenLuminositeSalon, 'activate');
	mg::setScenario($scenLuminositeSalon, 'start');

	// Rétablissement volets
	mg::setScenario($scenVoletsJourNuit, 'activate');
	mg::setScenario($scenVoletsJourNuit, 'start');

	sleep(60);
	mg::setScenario($scenLuminositeSalon, 'activate');
	mg::setScenario($scenLuminositeSalon, 'start');

	mg::Message($logTimeLine, "Cinéma - Arrêt.");
}

?>