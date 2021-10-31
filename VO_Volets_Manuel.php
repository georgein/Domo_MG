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
if ( $alarme || mg::declencheur('user') || mg::declencheur('scenario')) { return; }

$zone = mg::declencheur('', 1);

	// Calcul si appel par ouverture de la fenêtre
if (mg::declencheur('Ouverture]')) {
	mg::setVar('_InfPorte', mg::declencheur()); // Pour stop des chauffages
	$trigger = 'Ouverture';
	$cmd = mg::declencheur('', 2);
	$cmd = str_ireplace(array('fenêtre', 'Porte'), 'Volet', $cmd);
	$ValCmd = "";
}

// Calcul si appel par slider du volet
else {
	$trigger = 'Slider';
	$ValCmd = mg::getCmd(mg::declencheur());
	$cmd = mg::declencheur('', 3);
	$cmd = str_replace(' Etat', '', $cmd);
}
mg::messageT('', "Trigger : $trigger - zone : $zone, cmd : $cmd - ValCmd : $ValCmd");

mg::VoletRoulant($zone, $cmd, $trigger, $ValCmd, 'manuel');
mg::unsetVar('_InfPorte');

?>