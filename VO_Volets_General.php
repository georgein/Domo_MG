<?php
/******************************************************************************************************************************
Volets Général 156
Ouvre ou ferme l'ensemble des volets d'une Zone via les boutons du design.
Le nom de l'Inf du déclencheur doit être celui de la pièce, Valeur de linf : 1 monter, 0 Descendre
******************************************************************************************************************************/

// Infos, Commandes et Equipements :

// N° des scénarios :

//Variables :

// Paramètres :

/*********************************************************************************************************************/
/*********************************************************************************************************************/
/*********************************************************************************************************************/
if (mg::declencheur('user')) { return; }

mg::message('', "Volets général : declencheur " . mg::declencheur('' , 3));

$zone = mg::declencheur('', 1);
$sens = mg::getCmd(mg::declencheur()) == 1 ? 'M' : 'D';
mg::messageT('', "!Volets général : $zone - $sens");
mg::VoletsGeneral($zone, $sens, 1);

?>