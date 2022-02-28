<?php
/**********************************************************************************************************************
Cinema - 160
	Au démarrage : Alimente le DD, Les passerelles HDMI et le retroprojecteur, ensuite baisse les volets et met l'ambiance 'Cinéma' lorsque les lampes sont allumées complètement.
	A la fin : Eteint le vidéoprojecteur et ensuite Coupe l'alimentation du DD, des passerelles HDMI, puis lance VoletsNuit.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
	// $infCinemaEtat
	// $equipEcl, $equipVoletsSalon, $equipFrameTV_OnOff, $equipTvDomSamsung

// N° des scénarios :
	$scenLuminositeSalon = 59;
	$scenAllumageSalon = 44;
//	$scenVoletsJourNuit = 29;

//Variables :
	$nuitExt = mg::getVar('NuitExt');
	$nuitSalon = mg::getVar('NuitSalon');
	$timeVoletsNuit = mg::getParam('Volets', 'timeVoletsNuit');
	$equipSonos = mg::getParam('Media', 'equipSonos');
	
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
	mg::message($logTimeLine, "Cinéma - Mise en route.");

	 // Allumage TV si nécessaire
	if (!mg::getCmd($equipFrameTV_OnOff, 'Etat')) mg::setCmd($equipFrameTV_OnOff, 'On');

	// Volume de Sonos
	mg::setCmd($equipSonos, 'Volume', 90);

	// Ambiance lumière à 'Cinéma'
	mg::wait("scenario($scenAllumageSalon) == 0", 180);	
	mg::setCmd($equipEcl, 'Lampe Ambiance Slider', 3);
	mg::setScenario($scenAllumageSalon, 'start');
	sleep(2);
	mg::wait("scenario($scenAllumageSalon) == 0", 180);	
	mg::setScenario($scenAllumageSalon, 'deactivate');
	
	mg::setScenario($scenLuminositeSalon, 'deactivate');

	// Fermeture volets
	mg::setVar('_disableVoletJourNuit', 1);
	mg::setCmd($equipVoletsSalon, 'Down');
	
	// Bascule en deuxième mode, normalement (HDMI)
	mg::setCmd($equipTvDomSamsung, 'off');

// ************************************************** ARRET DU CINEMA *************************************************
} else {
	//=================================================================================================================
	mg::MessageT('', "! ARRET DU CINEMA");
	//=================================================================================================================
	// Bascule de retour en mode 'Art'
	mg::setCmd($equipTvDomSamsung, 'off');

	// Rallumage lumière
	mg::setVar('NuitSalon', 1);
	mg::setCmd($equipEcl, 'Lampe Générale Slider', 50);
	mg::setCmd($equipEcl, 'Lampe Ambiance Slider', 1);
	mg::setScenario($scenAllumageSalon, 'activate');
	mg::setScenario($scenAllumageSalon, 'start');
	mg::wait("scenario($scenAllumageSalon) == 0", 180);	

	// Réactivation et Ouverture volets
	mg::unsetVar('_disableVoletJourNuit');
	if ($nuitExt == 0) mg::setCmd($equipVoletsSalon, 'up');
	
	sleep(60);
	mg::setScenario($scenLuminositeSalon, 'activate');
	mg::setScenario($scenLuminositeSalon, 'start');

	mg::message($logTimeLine, "Cinéma - Arrêt.");
}

?>