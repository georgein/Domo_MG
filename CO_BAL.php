<?php
/**********************************************************************************************************************
function BAL 175()
Gestion de la boite aux lettres.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
// $inPorteEntree, $infBAL_Mvmt, $infBalAff,

// N° des scénarios :

//Variables :
	$nuitSalon = mg::getVar('NuitSalon');
	$destinataire = "Log:/_TimeLine, Message, TTS:GOOGLECAST, SMS:@MG";
	$heureReveil = mg::getVar('_Heure_Reveil');

	$lastPorteEntree = round(scenarioExpression::lastChangeStateDuration($inPorteEntree, 1) / 60);
mg::message('', "lastPorteEntree - $lastPorteEntree");

// Paramètres :
	$logTimeLine = mg::getParam('Log', 'timeLine');
	$timeVoletsNuit = mg::getParam('Volets', 'timeVoletsNuit');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$declencheur = mg::getTag('#trigger#');

// RàZ BAL par le cron
if ($declencheur == 'schedule' || $declencheur == 'user') {
		mg::setInf($infBalAff, '', 0);
}

// CD NE PAS DERANGER
if (mg::TimeBetween(strtotime($timeVoletsNuit), time(), $heureReveil)) { return; }

// Si boite aux lettres ET porte entré > 2 mn on active le signalement
if (strpos($declencheur, 'BAL') !== false && mg::getCmd($infBAL_Mvmt) && $lastPorteEntree > 2) {
	mg::message($destinataire, 'Il y a du courrier dans la boite aux lettres.');
	mg::setInf($infBalAff, '', 1);
	mg::setCron('', time() + 12*3600);
}

// Si boite aux lettres ET porte entré < 2 mn on annule le signalement
if (mg::getCmd($infBAL_Mvmt) && $lastPorteEntree <= 2) {
	mg::setInf($infBalAff, '', 0);
}
?>