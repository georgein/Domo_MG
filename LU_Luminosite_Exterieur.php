<?php
/**********************************************************************************************************************
Luminosite Exterieur - 69
Calcul de $nuitExt selon la luminosité extérieure : JOUR (0), NUIT (1), AURORE (2).
L'état 'Aurore' démarre 0:30 AVANT l'aurore civile et se termine 1:00 AVANT le réveil
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
//	$equipEcl, $equipLumExt

// N° des scénarios :

//Variables :
	$nuitExt = mg::getVar('NuitExt');
	$nuitSalon = mg::getVar('NuitSalon');
	$Lum_Ext = mg::getCmd($equipLumExt, 'Luminosité');
	$heureReveil = mg::getVar('heureReveil');

// Paramètres :
	$SeuilLumExterieureNuit = mg::getParam('Lumieres', 'seuilLumExterieureNuit');
	$SeuilLumExterieureJour = mg::getParam('Lumieres', 'seuilLumExterieureJour');
	$logTimeLine = mg::getParam('Log', 'timeLine');
//	$timeVoletsNuit = mg::getParam('Volets', 'timeVoletsNuit');
	$latitude = mg::getParam('Volets', 'latitude');
	$longitude = mg::getParam('Volets', 'longitude');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$tmp = mg::Soleil(time(), $latitude, $longitude); 
$aurore = $tmp['lever'];
$coucher = $tmp['coucher'];

$cdAurore = mg::TimeBetween($aurore-60*60, time(), $aurore);
$message = "Aurore à " . date('H\hi\m\n', $aurore)." - Crépuscule à " . date('H\hi\m\n', $coucher);
mg::message('', $message);
mg::getParam('Volets', 'timeVoletsNuit', date('H:i', $coucher+900), 1);

$oldNuitExt = $nuitExt;

if (  $nuitExt == 0 && $Lum_Ext <= $SeuilLumExterieureNuit ) {
	$nuitExt = 1; // NUIT
	$message = "NuitExt - Passage à NuitExt (1) - ($message).";
}
else if ( $nuitExt > 0 && $Lum_Ext > $SeuilLumExterieureJour) {
	$nuitExt =  0; // JOUR
	$message = "NuitExt - Passage au JourExt (0).";
}
elseif ( $nuitExt == 1 && $cdAurore ) {
	$nuitExt = 2; // AURORE
	$message = "NuitExt - Passage à l'AubeExt (2).";
}

//$nuitExt = 0; // POUR DEBUG //////////////////////////////

// Si changement
if ( $oldNuitExt != $nuitExt) {
	mg::setVar('NuitExt', $nuitExt);
	mg::getParam('Volets', 'timeVoletsNuit', date('H:i', $coucher), 1);
	
/*	// Au passage à la nuit //////////////////////////////////////////////////////////////////////////////////
	if ($oldNuitExt == 0 && $nuitExt == 1) {
		$timeVoletsNuit = min(date('H:i', time() + 60*60), '23:00');
		$message .= " - timeVoletsNuit = $timeVoletsNuit";
		mg::getParam('Volets', 'timeVoletsNuit', $timeVoletsNuit, 1);
	}*/
	
	mg::Message($logTimeLine, $message);
}

?>