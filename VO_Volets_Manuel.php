<?php
/**********************************************************************************************************************
Volets Manuel - 107
Gestion du slider et de la visualisation de l'ouverture des volets roulant via leur widget.

Gère un visuel proportionnel au temps de mouvement du volet ainsi que la possibilité d'ouvrir/fermer à x %
Une image de porte fenêtre est utilisé si un équipement d'ouverture/fermeture est déclaré, sinon utilisation d'une image simple de baie vitrée

**********************************************************************************************************************/
// Infos, Commandes et Equipements :

// N° des scénarios :

//Variables :
	$alarme = mg::getVar('Alarme');
	$_InfPorte = mg::getVar('_InfPorte', '');			// déclencheur d'origine de mouvement de porte/fenetre

// Paramètres :

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
$declencheur = mg::getTag('#trigger#');
$zone = mg::ExtractPartCmd($declencheur, 1);
if ( $alarme == 1) { return; }
if ($declencheur =='user') { return; }

	// Calcul si appel par ouverture de la fenêtre
if (strpos($declencheur, 'Ouverture]') !== false) {
	mg::setVar('_InfPorte', $declencheur); // Pour stop des chauffages
	$trigger = 'Ouverture';
	$cmd = mg::ExtractPartCmd($declencheur, 2);
	$cmd = str_ireplace(array('fenêtre', 'Porte'), 'Volet', $cmd);
	$ValCmd = "";
}

// Calcul si appel par slider du volet
else {
	$trigger = 'Slider';
	$ValCmd = mg::getCmd($declencheur);
	$cmd = mg::ExtractPartCmd($declencheur, 3);
	$cmd = str_replace(' Etat', '', $cmd);
}
mg::messageT('', "Trigger : $trigger - zone : $zone, cmd : $cmd - ValCmd : $ValCmd");

mg::VoletRoulant($zone, $cmd, $trigger, $ValCmd);
mg::unsetVar('_InfPorte');

?>