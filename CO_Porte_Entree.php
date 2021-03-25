<?php
/**********************************************************************************************************************
Porte entrée - 103
Joue un son à l'ouverture de la porte.
**********************************************************************************************************************/

// Infos, Commandes et Equipements :
	// $equipEclCuisine
	
// N° des scénarios :

//Variables :
	$nuitSalon = mg::getVar('NuitSalon');
	
// Paramètres :
	$sonOuverture = mg::getParam('Confort', 'sonOuverture');
	$volOuverture = mg::getParam('Confort', 'volOuverture');

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
if(mg::declencheur('user')) { return; }

// Ouverture
if (mg::getCmd(mg::declencheur()) == 1) {
	if ($nuitSalon) { mg::setCmd($equipEclCuisine, 'On'); }
	mg::GoogleCast ('PLAY', $sonOuverture, $volOuverture);
}

?>