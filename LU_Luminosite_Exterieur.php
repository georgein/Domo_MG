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
	$alarme = mg::getVar('Alarme');
	$nuitExt = mg::getVar('NuitExt');
	$nuitSalon = mg::getVar('NuitSalon');
	$Lum_Ext = mg::getCmd($equipLumExt, 'Luminosité');
	$heureReveil = mg::getVar('_Heure_Reveil');

// Paramètres :
	$SeuilLumExterieureNuit = mg::getParam('Lumieres', 'seuilLumExterieureNuit');
	$SeuilLumExterieureJour = mg::getParam('Lumieres', 'seuilLumExterieureJour');
	$logTimeLine = mg::getParam('Log', 'timeLine');
	$timeVoletsNuit = mg::getParam('Volets', 'timeVoletsNuit');
	$latitude = mg::getParam('Volets', 'latitude');
	$longitude = mg::getParam('Volets', 'longitude');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$tmp = mg::Soleil(time(), $latitude, $longitude); 
$aurore = min($heureReveil, $tmp['lever'])-30*60;
// Mémo pour volet ou autres
mg::setVar('_Aurore', $aurore);
$cdAurore = mg::TimeBetween($aurore, time(), $heureReveil+120*60);
$message = "Aurore à " . date('H\hi\m\n', $aurore);
mg::message('', $message);

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

// Si changement
if ( $oldNuitExt != $nuitExt) {
	mg::setVar('NuitExt', $nuitExt);
	mg::Message($logTimeLine, $message);
}

?>