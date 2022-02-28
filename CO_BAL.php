<?php
/**********************************************************************************************************************
function BAL 175()
Gestion de la boite aux lettres.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
// $inPorteEntree, $infBAL_Mvmt, $infBalAff,

// N° des scénarios :

//Variables :
	$logTimeLine = mg::getParam('Log', 'timeLine');
	$nePasDeranger = mg::getVar('nePasDeranger', 0);
	$destinataire = "$logTimeLine, Message, TTS:defaut, SMS:@MG";
	$heureReveil = mg::getVar('heureReveil');

	$lastPorteEntree = round(scenarioExpression::lastChangeStateDuration($inPorteEntree, 1) / 60);
mg::message('', "lastPorteEntree - $lastPorteEntree");

// Paramètres :
	$timeVoletsNuit = mg::getParam('Volets', 'timeVoletsNuit');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
if ($nePasDeranger) { return; }

// RàZ BAL par le cron
if (mg::declencheur('schedule') || mg::declencheur('user')) {
		mg::setInf($infBalAff, '', 0);
}
$balMvmt = mg::getCmd($infBAL_Mvmt); 

// CD NE PAS DERANGER
if (mg::TimeBetween(strtotime($timeVoletsNuit), time(), $heureReveil)) { return; }

// Si boite aux lettres ET porte entré > 2 mn on active le signalement
if (mg::declencheur('BAL') && $balMvmt && $lastPorteEntree > 2) {
	mg::message($destinataire, 'Il y a du courrier dans la boite aux lettres.');
	mg::setInf($infBalAff, '', 1);
	mg::setCron('', time() + 12*3600);
}

// Si boite aux lettres ET porte entré < 2 mn on annule le signalement
if ($balMvmt && $lastPorteEntree <= 2) {
	mg::setInf($infBalAff, '', 0);
}
?>